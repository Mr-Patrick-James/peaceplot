<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/logger.php';

$database = new Database();
$conn = $database->getConnection();

// Check if connection failed, but don't exit - handle gracefully in functions

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        handleGet($conn);
        break;
    case 'POST':
        handlePost($conn, $input);
        break;
    case 'PUT':
        handlePut($conn, $input);
        break;
    case 'DELETE':
        handleDelete($conn, $input);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;
}

function handleGet($conn) {
    try {
        if ($conn) {
            // Force status sync based on active burials for all lots
            $conn->exec("
                UPDATE cemetery_lots 
                SET status = CASE 
                    WHEN EXISTS (
                        SELECT 1 FROM deceased_records 
                        WHERE lot_id = cemetery_lots.id AND is_archived = 0
                    ) THEN 'Occupied' 
                    ELSE 'Vacant' 
                END
            ");
        }

        $id = isset($_GET['id']) ? intval($_GET['id']) : null;
        
        if ($id) {
            if ($conn) {
                $stmt = $conn->prepare("
                    SELECT cl.*, 
                           (SELECT GROUP_CONCAT(full_name, ', ') 
                            FROM (SELECT full_name FROM deceased_records WHERE lot_id = cl.id AND is_archived = 0 ORDER BY created_at DESC, id DESC)
                           ) as deceased_name,
                           COALESCE(NULLIF((SELECT COUNT(*) FROM lot_layers ll WHERE ll.lot_id = cl.id), 0), cl.layers, 1) as total_layers_count,
                           (SELECT COUNT(DISTINCT layer) FROM deceased_records WHERE lot_id = cl.id AND is_archived = 0) as occupied_layers_count
                    FROM cemetery_lots cl 
                    WHERE cl.id = :id
                ");
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                $result = $stmt->fetch();
                
                if ($result) {
                    echo json_encode(['success' => true, 'data' => $result]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Lot not found']);
                }
            } else {
                // Use sample data if database connection fails
                $sampleFile = __DIR__ . '/../database/sample_lots.json';
                if (file_exists($sampleFile)) {
                    $lots = json_decode(file_get_contents($sampleFile), true);
                    $lot = null;
                    foreach ($lots as $sampleLot) {
                        if ($sampleLot['id'] == $id) {
                            $lot = $sampleLot;
                            break;
                        }
                    }
                    if ($lot) {
                        echo json_encode(['success' => true, 'data' => $lot]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Lot not found in sample data']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'No database connection and no sample data available']);
                }
            }
        } else {
            if ($conn) {
                $status = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;
                $section = isset($_GET['section']) && $_GET['section'] !== '' ? $_GET['section'] : null;
                $block = isset($_GET['block']) && $_GET['block'] !== '' ? $_GET['block'] : null;
                $occupancy = isset($_GET['occupancy']) && $_GET['occupancy'] !== '' ? $_GET['occupancy'] : null;
                $sortOrder = isset($_GET['sort_order']) && in_array(strtoupper($_GET['sort_order']), ['ASC', 'DESC']) ? strtoupper($_GET['sort_order']) : 'ASC';
                $all = isset($_GET['all']) && $_GET['all'] === 'true';
                $search = isset($_GET['search']) ? trim($_GET['search']) : '';
                
                $whereClauses = [];
                $params = [];
                
                if ($status) {
                    $statusArray = explode(',', $status);
                    $placeholders = [];
                    foreach ($statusArray as $i => $s) {
                        $placeholder = ":status_$i";
                        $placeholders[] = $placeholder;
                        $params[$placeholder] = trim($s);
                    }
                    $whereClauses[] = "cl.status IN (" . implode(',', $placeholders) . ")";
                }

                if ($section) {
                    $sectionArray = explode(',', $section);
                    $placeholders = [];
                    foreach ($sectionArray as $i => $s) {
                        $placeholder = ":section_$i";
                        $placeholders[] = $placeholder;
                        $params[$placeholder] = trim($s);
                    }
                    $whereClauses[] = "cl.section IN (" . implode(',', $placeholders) . ")";
                }

                if ($block) {
                    $blockArray = explode(',', $block);
                    $placeholders = [];
                    foreach ($blockArray as $i => $b) {
                        $placeholder = ":block_$i";
                        $placeholders[] = $placeholder;
                        $params[$placeholder] = trim($b);
                    }
                    $whereClauses[] = "cl.block IN (" . implode(',', $placeholders) . ")";
                }

                if ($occupancy) {
                    $occArray = explode(',', $occupancy);
                    $occClauses = [];
                    foreach ($occArray as $occ) {
                        if (trim($occ) === 'Assigned') {
                            $occClauses[] = "EXISTS (SELECT 1 FROM lot_layers ll WHERE ll.lot_id = cl.id AND ll.is_occupied = 1)";
                        } elseif (trim($occ) === 'Unassigned') {
                            $occClauses[] = "NOT EXISTS (SELECT 1 FROM lot_layers ll WHERE ll.lot_id = cl.id AND ll.is_occupied = 1)";
                        }
                    }
                    if (count($occClauses) > 0) {
                        $whereClauses[] = "(" . implode(" OR ", $occClauses) . ")";
                    }
                }
                
                if ($search) {
                    $whereClauses[] = "(cl.lot_number = :exact_search OR cl.lot_number LIKE :search OR cl.section LIKE :search OR cl.position LIKE :search OR dr.full_name LIKE :search)";
                    $params[':exact_search'] = $search;
                    $params[':search'] = "%$search%";
                }
                
                $whereSQL = count($whereClauses) > 0 ? " WHERE " . implode(" AND ", $whereClauses) : "";

                // Get total count for pagination
                $countQuery = "SELECT COUNT(DISTINCT cl.id) FROM cemetery_lots cl";
                if ($search) {
                    $countQuery .= " LEFT JOIN deceased_records dr ON cl.id = dr.lot_id";
                }
                $countQuery .= $whereSQL;
                
                $countStmt = $conn->prepare($countQuery);
                foreach ($params as $key => $val) {
                    $countStmt->bindValue($key, $val);
                }
                $countStmt->execute();
                $totalLots = (int)$countStmt->fetchColumn();

                // Pagination parameters
                $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                $limit = $all ? ($totalLots ?: 1) : (isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 20);
                $offset = ($page - 1) * $limit;

                // Main query with search, order, and limit
                // Use a subquery for deceased_name to ensure latest records appear first
                $query = "
                    SELECT cl.*, 
                           (SELECT GROUP_CONCAT(full_name, ', ') 
                            FROM (SELECT full_name FROM deceased_records WHERE lot_id = cl.id AND is_archived = 0 ORDER BY created_at DESC, id DESC)
                           ) as deceased_name,
                           COALESCE(NULLIF((SELECT COUNT(*) FROM lot_layers ll WHERE ll.lot_id = cl.id), 0), cl.layers, 1) as total_layers_count,
                           (SELECT COUNT(DISTINCT layer) FROM deceased_records WHERE lot_id = cl.id AND is_archived = 0) as occupied_layers_count
                    FROM cemetery_lots cl 
                    " . ($search ? "LEFT JOIN deceased_records dr ON cl.id = dr.lot_id" : "") . "
                    $whereSQL
                    GROUP BY cl.id
                ";
                
                // Prioritize exact matches in sorting, then natural-ish length sort
                $orderBy = " ORDER BY ";
                if ($search) {
                    $orderBy .= "CASE WHEN cl.lot_number = :exact_search THEN 0 ELSE 1 END, ";
                }
                $orderBy .= "LENGTH(cl.lot_number) $sortOrder, cl.lot_number $sortOrder";
                
                if (!$all) {
                    $query .= $orderBy . " LIMIT :limit OFFSET :offset";
                } else {
                    $query .= $orderBy;
                }

                $stmt = $conn->prepare($query);
                foreach ($params as $key => $val) {
                    $stmt->bindValue($key, $val);
                }
                
                if (!$all) {
                    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                }
                
                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true, 
                    'data' => $results,
                    'pagination' => [
                        'total' => $totalLots,
                        'page' => $page,
                        'limit' => $limit,
                        'pages' => $totalLots > 0 ? ceil($totalLots / $limit) : 0
                    ]
                ]);
            } else {
                // Use sample data if database connection fails
                $sampleFile = __DIR__ . '/../database/sample_lots.json';
                if (file_exists($sampleFile)) {
                    $lots = json_decode(file_get_contents($sampleFile), true);
                    echo json_encode(['success' => true, 'data' => $lots]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'No database connection and no sample data available']);
                }
            }
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handlePost($conn, $input) {
    try {
        if (empty($input['lot_number']) || empty($input['section']) || empty($input['block']) || empty($input['status'])) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields (Lot Number, Section, Block, and Status)']);
            return;
        }
        
        // Check if lot number already exists in the same section
        $checkStmt = $conn->prepare("SELECT id FROM cemetery_lots WHERE lot_number = :lot_number AND section = :section");
        $checkStmt->bindParam(':lot_number', $input['lot_number']);
        $checkStmt->bindParam(':section', $input['section']);
        $checkStmt->execute();
        if ($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => "Lot number '" . $input['lot_number'] . "' already exists in '" . $input['section'] . "'."]);
            return;
        }
        
        $stmt = $conn->prepare("
            INSERT INTO cemetery_lots (lot_number, section, block, position, status) 
            VALUES (:lot_number, :section, :block, :position, :status)
        ");
        
        $stmt->bindParam(':lot_number', $input['lot_number']);
        $stmt->bindParam(':section', $input['section']);
        $stmt->bindParam(':block', $input['block']);
        $stmt->bindParam(':position', $input['position']);
        $stmt->bindParam(':status', $input['status']);
        
        if ($stmt->execute()) {
            $lastId = $conn->lastInsertId();
            
            // Create default layer 1 for the new lot
            try {
                // Initialize with 1 layer
                $updateStmt = $conn->prepare("UPDATE cemetery_lots SET layers = 1 WHERE id = :id");
                $updateStmt->bindParam(':id', $lastId);
                $updateStmt->execute();
                
                // Add entry to lot_layers
                $layerStmt = $conn->prepare("
                    INSERT INTO lot_layers (lot_id, layer_number, is_occupied) 
                    VALUES (:lot_id, 1, 0)
                ");
                $layerStmt->bindParam(':lot_id', $lastId);
                $layerStmt->execute();
            } catch (Exception $e) {
                // Ignore layer creation errors, just log them
                error_log("Failed to create default layer for lot $lastId: " . $e->getMessage());
            }
            
            logActivity($conn, 'ADD_LOT', 'cemetery_lots', $lastId, "New lot " . $input['lot_number'] . " is added to the system");
            
            echo json_encode(['success' => true, 'message' => 'Lot created successfully', 'id' => $lastId]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create lot']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handlePut($conn, $input) {
    try {
        if (empty($input['id']) || empty($input['lot_number']) || empty($input['section']) || empty($input['block']) || empty($input['status'])) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            return;
        }
        
        // Check if new lot number and section already exists elsewhere
        $checkStmt = $conn->prepare("SELECT id FROM cemetery_lots WHERE lot_number = :lot_number AND section = :section AND id != :id");
        $checkStmt->bindParam(':lot_number', $input['lot_number']);
        $checkStmt->bindParam(':section', $input['section']);
        $checkStmt->bindParam(':id', $input['id']);
        $checkStmt->execute();
        if ($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => "Another lot with number '" . $input['lot_number'] . "' already exists in '" . $input['section'] . "'."]);
            return;
        }
        
        $stmt = $conn->prepare("
            UPDATE cemetery_lots 
            SET lot_number = :lot_number, 
                section = :section, 
                block = :block, 
                position = :position, 
                status = :status, 
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        
        $stmt->bindParam(':id', $input['id']);
        $stmt->bindParam(':lot_number', $input['lot_number']);
        $stmt->bindParam(':section', $input['section']);
        $stmt->bindParam(':block', $input['block']);
        $stmt->bindParam(':position', $input['position']);
        $stmt->bindParam(':status', $input['status']);
        
        if ($stmt->execute()) {
            logActivity($conn, 'UPDATE_LOT', 'cemetery_lots', $input['id'], "Lot " . $input['lot_number'] . " is updated (" . $input['status'] . ")");
            echo json_encode(['success' => true, 'message' => 'Lot updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update lot']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleDelete($conn, $input) {
    try {
        $id = isset($input['id']) ? $input['id'] : (isset($_GET['id']) ? $_GET['id'] : null);
        
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Missing lot ID']);
            return;
        }
        
        // Get lot number before deletion
        $lotStmt = $conn->prepare("SELECT lot_number FROM cemetery_lots WHERE id = :id");
        $lotStmt->bindParam(':id', $id);
        $lotStmt->execute();
        $lotNum = $lotStmt->fetchColumn() ?: 'ID ' . $id;
        
        $stmt = $conn->prepare("DELETE FROM cemetery_lots WHERE id = :id");
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            logActivity($conn, 'DELETE_LOT', 'cemetery_lots', $id, "Lot $lotNum is removed from the system");
            echo json_encode(['success' => true, 'message' => 'Lot deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete lot']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
