<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/logger.php';

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

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
        $id = isset($_GET['id']) ? intval($_GET['id']) : null;
        $showArchived = isset($_GET['archived']) && $_GET['archived'] === '1' ? 1 : 0;
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : null;
        $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 10;
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $filterSection = isset($_GET['section']) ? trim($_GET['section']) : '';
        $filterBlock = isset($_GET['block']) ? trim($_GET['block']) : '';
        $filterStatus = isset($_GET['status']) ? trim($_GET['status']) : '';
        $startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
        $endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
        
        // Check if burial_record_images table exists
        $tableCheck = $conn->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name='burial_record_images'");
        $tableCheck->execute();
        $imagesTableExists = $tableCheck->fetch() !== false;
        
        if ($id) {
            $stmt = $conn->prepare("
                SELECT dr.*, cl.lot_number, cl.section, cl.block 
                FROM deceased_records dr 
                LEFT JOIN cemetery_lots cl ON dr.lot_id = cl.id 
                WHERE dr.id = :id
            ");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result) {
                // Only fetch images if table exists
                if ($imagesTableExists) {
                    try {
                        $imageStmt = $conn->prepare("
                            SELECT * FROM burial_record_images 
                            WHERE burial_record_id = :burial_record_id 
                            ORDER BY display_order ASC, created_at ASC
                        ");
                        $imageStmt->bindParam(':burial_record_id', $id);
                        $imageStmt->execute();
                        $images = $imageStmt->fetchAll();
                        $result['images'] = $images;
                    } catch (PDOException $e) {
                        // If images query fails, set empty array
                        $result['images'] = [];
                    }
                } else {
                    $result['images'] = [];
                }
                
                echo json_encode(['success' => true, 'data' => $result]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Record not found']);
            }
        } else {
            // Build query with filters
            $whereClause = "WHERE dr.is_archived = :is_archived";
            $params = [':is_archived' => $showArchived];
            
            if ($search) {
                $whereClause .= " AND (dr.full_name LIKE :search OR cl.lot_number LIKE :search OR cl.section LIKE :search OR dr.deceased_info LIKE :search OR dr.remarks LIKE :search)";
                $params[':search'] = "%$search%";
            }

            if ($filterSection) {
                $sectionArray = explode(',', $filterSection);
                $placeholders = [];
                foreach ($sectionArray as $i => $s) {
                    $placeholder = ":section_$i";
                    $placeholders[] = $placeholder;
                    $params[$placeholder] = trim($s);
                }
                $whereClause .= " AND cl.section IN (" . implode(',', $placeholders) . ")";
            }

            if ($filterBlock) {
                $blockArray = explode(',', $filterBlock);
                $placeholders = [];
                foreach ($blockArray as $i => $b) {
                    $placeholder = ":block_$i";
                    $placeholders[] = $placeholder;
                    $params[$placeholder] = trim($b);
                }
                $whereClause .= " AND cl.block IN (" . implode(',', $placeholders) . ")";
            }

            if ($filterStatus) {
                $statusArray = explode(',', $filterStatus);
                $placeholders = [];
                foreach ($statusArray as $i => $s) {
                    $placeholder = ":status_$i";
                    $placeholders[] = $placeholder;
                    $params[$placeholder] = trim($s);
                }
                $whereClause .= " AND cl.status IN (" . implode(',', $placeholders) . ")";
            }

            if ($startDate) {
                $whereClause .= " AND dr.date_of_death >= :start_date";
                $params[':start_date'] = $startDate;
            }

            if ($endDate) {
                $whereClause .= " AND dr.date_of_death <= :end_date";
                $params[':end_date'] = $endDate;
            }
            
            // Get total count for pagination
            $countStmt = $conn->prepare("
                SELECT COUNT(*) 
                FROM deceased_records dr 
                LEFT JOIN cemetery_lots cl ON dr.lot_id = cl.id 
                $whereClause
            ");
            foreach ($params as $key => $val) {
                $countStmt->bindValue($key, $val);
            }
            $countStmt->execute();
            $totalRecords = intval($countStmt->fetchColumn());
            
            $sql = "
                SELECT dr.*, cl.lot_number, cl.section, cl.block, cl.status as lot_status
                FROM deceased_records dr 
                LEFT JOIN cemetery_lots cl ON dr.lot_id = cl.id 
                $whereClause
                ORDER BY dr.created_at DESC, dr.id DESC
            ";
            
            if ($page !== null) {
                $offset = ($page - 1) * $limit;
                $sql .= " LIMIT :limit OFFSET :offset";
            }
            
            $stmt = $conn->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            
            if ($page !== null) {
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            }
            
            $stmt->execute();
            $results = $stmt->fetchAll();
            
            // Get images for each burial record only if table exists
            if ($imagesTableExists) {
                foreach ($results as &$record) {
                    try {
                        $imageStmt = $conn->prepare("
                            SELECT * FROM burial_record_images 
                            WHERE burial_record_id = :burial_record_id 
                            ORDER BY display_order ASC, created_at ASC
                        ");
                        $imageStmt->bindParam(':burial_record_id', $record['id']);
                        $imageStmt->execute();
                        $images = $imageStmt->fetchAll();
                        $record['images'] = $images;
                    } catch (PDOException $e) {
                        // If images query fails, set empty array
                        $record['images'] = [];
                    }
                }
            } else {
                // Set empty images array for all records if table doesn't exist
                foreach ($results as &$record) {
                    $record['images'] = [];
                }
            }
            
            $response = [
                'success' => true, 
                'data' => $results,
                'pagination' => null
            ];
            
            if ($page !== null) {
                $response['pagination'] = [
                    'total_records' => $totalRecords,
                    'total_pages' => ceil($totalRecords / $limit),
                    'current_page' => $page,
                    'limit' => $limit
                ];
            }
            
            echo json_encode($response);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handlePost($conn, $input) {
    try {
        if (!isset($input['full_name'])) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            return;
        }

        $lotIdRaw = $input['lot_id'] ?? null;
        $lotId = ($lotIdRaw === '' || $lotIdRaw === null) ? null : intval($lotIdRaw);

        if ($lotId === null) {
            $stmt = $conn->prepare("
                INSERT INTO deceased_records 
                (lot_id, layer, full_name, date_of_birth, date_of_death, date_of_burial, age, 
                 cause_of_death, next_of_kin, next_of_kin_contact, deceased_info, remarks) 
                VALUES 
                (NULL, NULL, :full_name, :date_of_birth, :date_of_death, :date_of_burial, :age,
                 :cause_of_death, :next_of_kin, :next_of_kin_contact, :deceased_info, :remarks)
            ");

            $stmt->bindValue(':full_name', $input['full_name']);
            $stmt->bindValue(':date_of_birth', $input['date_of_birth'] ?? null);
            $stmt->bindValue(':date_of_death', $input['date_of_death'] ?? null);
            $stmt->bindValue(':date_of_burial', $input['date_of_burial'] ?? null);
            $stmt->bindValue(':age', $input['age'] ?? null);
            $stmt->bindValue(':cause_of_death', $input['cause_of_death'] ?? null);
            $stmt->bindValue(':next_of_kin', $input['next_of_kin'] ?? null);
            $stmt->bindValue(':next_of_kin_contact', $input['next_of_kin_contact'] ?? null);
            $stmt->bindValue(':deceased_info', $input['deceased_info'] ?? null);
            $stmt->bindValue(':remarks', $input['remarks'] ?? null);

            if ($stmt->execute()) {
                $lastId = $conn->lastInsertId();
                logActivity($conn, 'ADD_RECORD', 'deceased_records', $lastId, "New burial record for " . $input['full_name'] . " is added (lot unassigned)");
                echo json_encode(['success' => true, 'message' => 'Record created successfully', 'id' => $lastId]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create record']);
            }
            return;
        }

        if (!isset($input['layer']) || $input['layer'] === '' || $input['layer'] === null) {
            echo json_encode(['success' => false, 'message' => 'Missing burial layer']);
            return;
        }

        $layer = intval($input['layer']);

        $stmt = $conn->prepare("SELECT is_occupied, burial_record_id FROM lot_layers WHERE lot_id = :lot_id AND layer_number = :layer");
        $stmt->bindParam(':lot_id', $lotId);
        $stmt->bindParam(':layer', $layer);
        $stmt->execute();
        $lotLayer = $stmt->fetch();

        if (!$lotLayer) {
            echo json_encode(['success' => false, 'message' => 'Layer ' . $layer . ' does not exist for this lot']);
            return;
        }

        if ($lotLayer['is_occupied']) {
            echo json_encode(['success' => false, 'message' => 'Layer ' . $layer . ' is already occupied']);
            return;
        }

        $stmt = $conn->prepare("
            INSERT INTO deceased_records 
            (lot_id, layer, full_name, date_of_birth, date_of_death, date_of_burial, age, 
             cause_of_death, next_of_kin, next_of_kin_contact, deceased_info, remarks) 
            VALUES 
            (:lot_id, :layer, :full_name, :date_of_birth, :date_of_death, :date_of_burial, :age,
             :cause_of_death, :next_of_kin, :next_of_kin_contact, :deceased_info, :remarks)
        ");

        $stmt->bindValue(':lot_id', $lotId);
        $stmt->bindValue(':layer', $layer);
        $stmt->bindValue(':full_name', $input['full_name']);
        $stmt->bindValue(':date_of_birth', $input['date_of_birth'] ?? null);
        $stmt->bindValue(':date_of_death', $input['date_of_death'] ?? null);
        $stmt->bindValue(':date_of_burial', $input['date_of_burial'] ?? null);
        $stmt->bindValue(':age', $input['age'] ?? null);
        $stmt->bindValue(':cause_of_death', $input['cause_of_death'] ?? null);
        $stmt->bindValue(':next_of_kin', $input['next_of_kin'] ?? null);
        $stmt->bindValue(':next_of_kin_contact', $input['next_of_kin_contact'] ?? null);
        $stmt->bindValue(':deceased_info', $input['deceased_info'] ?? null);
        $stmt->bindValue(':remarks', $input['remarks'] ?? null);

        if ($stmt->execute()) {
            $lastId = $conn->lastInsertId();

            $updateStmt = $conn->prepare("
                UPDATE lot_layers 
                SET is_occupied = 1, burial_record_id = :burial_record_id 
                WHERE lot_id = :lot_id AND layer_number = :layer
            ");
            $updateStmt->bindParam(':burial_record_id', $lastId);
            $updateStmt->bindParam(':lot_id', $lotId);
            $updateStmt->bindParam(':layer', $layer);
            $updateStmt->execute();

            updateLotStatus($conn, $lotId);

            // Get lot number for description
            $lotStmt = $conn->prepare("SELECT lot_number FROM cemetery_lots WHERE id = :id");
            $lotStmt->bindParam(':id', $lotId);
            $lotStmt->execute();
            $lotNum = $lotStmt->fetchColumn() ?: 'Unknown';

            logActivity($conn, 'ADD_RECORD', 'deceased_records', $lastId, $input['full_name'] . " assigned on $lotNum ($lotNum occupied)");

            echo json_encode(['success' => true, 'message' => 'Record created successfully', 'id' => $lastId]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create record']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handlePut($conn, $input) {
    try {
        if (!isset($input['id'])) {
            echo json_encode(['success' => false, 'message' => 'Missing record ID']);
            return;
        }

        $recordStmt = $conn->prepare("SELECT lot_id, layer FROM deceased_records WHERE id = :id");
        $recordStmt->bindParam(':id', $input['id']);
        $recordStmt->execute();
        $existing = $recordStmt->fetch();

        if (!$existing) {
            echo json_encode(['success' => false, 'message' => 'Record not found']);
            return;
        }

        $oldLotId = $existing['lot_id'] !== null ? intval($existing['lot_id']) : null;
        $oldLayer = $existing['layer'] !== null ? intval($existing['layer']) : null;

        $lotIdRaw = $input['lot_id'] ?? null;
        $newLotId = ($lotIdRaw === '' || $lotIdRaw === null) ? null : intval($lotIdRaw);

        if ($newLotId === null) {
            $stmt = $conn->prepare("
                UPDATE deceased_records 
                SET lot_id = NULL,
                    layer = NULL,
                    full_name = :full_name,
                    date_of_birth = :date_of_birth,
                    date_of_death = :date_of_death,
                    date_of_burial = :date_of_burial,
                    age = :age,
                    cause_of_death = :cause_of_death,
                    next_of_kin = :next_of_kin,
                    next_of_kin_contact = :next_of_kin_contact,
                    deceased_info = :deceased_info,
                    remarks = :remarks,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");

            $stmt->bindValue(':id', $input['id']);
            $stmt->bindValue(':full_name', $input['full_name'] ?? null);
            $stmt->bindValue(':date_of_birth', $input['date_of_birth'] ?? null);
            $stmt->bindValue(':date_of_death', $input['date_of_death'] ?? null);
            $stmt->bindValue(':date_of_burial', $input['date_of_burial'] ?? null);
            $stmt->bindValue(':age', $input['age'] ?? null);
            $stmt->bindValue(':cause_of_death', $input['cause_of_death'] ?? null);
            $stmt->bindValue(':next_of_kin', $input['next_of_kin'] ?? null);
            $stmt->bindValue(':next_of_kin_contact', $input['next_of_kin_contact'] ?? null);
            $stmt->bindValue(':deceased_info', $input['deceased_info'] ?? null);
            $stmt->bindValue(':remarks', $input['remarks'] ?? null);

            if ($stmt->execute()) {
                if ($oldLotId !== null && $oldLayer !== null) {
                    // Get old lot number for description
                    $oldLotStmt = $conn->prepare("SELECT lot_number FROM cemetery_lots WHERE id = :id");
                    $oldLotStmt->bindParam(':id', $oldLotId);
                    $oldLotStmt->execute();
                    $oldLotNum = $oldLotStmt->fetchColumn() ?: 'Unknown';

                    $updateStmt = $conn->prepare("
                        UPDATE lot_layers 
                        SET is_occupied = 0, burial_record_id = NULL 
                        WHERE lot_id = :lot_id AND layer_number = :layer AND burial_record_id = :burial_record_id
                    ");
                    $updateStmt->bindParam(':lot_id', $oldLotId);
                    $updateStmt->bindParam(':layer', $oldLayer);
                    $updateStmt->bindParam(':burial_record_id', $input['id']);
                    $updateStmt->execute();
                    updateLotStatus($conn, $oldLotId);

                    logActivity($conn, 'UPDATE_RECORD', 'deceased_records', $input['id'], ($input['full_name'] ?? 'Record') . " is unassigned from lot $oldLotNum");
                } else {
                    logActivity($conn, 'UPDATE_RECORD', 'deceased_records', $input['id'], ($input['full_name'] ?? 'Record') . " is updated (lot unassigned)");
                }

                echo json_encode(['success' => true, 'message' => 'Record updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update record']);
            }
            return;
        }

        if (!isset($input['layer']) || $input['layer'] === '' || $input['layer'] === null) {
            echo json_encode(['success' => false, 'message' => 'Missing burial layer']);
            return;
        }

        $newLayer = intval($input['layer']);

        $ensureLayerStmt = $conn->prepare("
            INSERT OR IGNORE INTO lot_layers (lot_id, layer_number, is_occupied) 
            VALUES (:lot_id, :layer_number, 0)
        ");
        $ensureLayerStmt->bindParam(':lot_id', $newLotId);
        $ensureLayerStmt->bindParam(':layer_number', $newLayer);
        $ensureLayerStmt->execute();

        $layerCheckStmt = $conn->prepare("SELECT is_occupied, burial_record_id FROM lot_layers WHERE lot_id = :lot_id AND layer_number = :layer");
        $layerCheckStmt->bindParam(':lot_id', $newLotId);
        $layerCheckStmt->bindParam(':layer', $newLayer);
        $layerCheckStmt->execute();
        $layerRow = $layerCheckStmt->fetch();

        if ($layerRow && $layerRow['is_occupied'] && intval($layerRow['burial_record_id']) !== intval($input['id'])) {
            echo json_encode(['success' => false, 'message' => 'Layer ' . $newLayer . ' is already occupied']);
            return;
        }

        $stmt = $conn->prepare("
            UPDATE deceased_records 
            SET lot_id = :lot_id,
                layer = :layer,
                full_name = :full_name,
                date_of_birth = :date_of_birth,
                date_of_death = :date_of_death,
                date_of_burial = :date_of_burial,
                age = :age,
                cause_of_death = :cause_of_death,
                next_of_kin = :next_of_kin,
                next_of_kin_contact = :next_of_kin_contact,
                deceased_info = :deceased_info,
                remarks = :remarks,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        $stmt->bindValue(':id', $input['id']);
        $stmt->bindValue(':lot_id', $newLotId);
        $stmt->bindValue(':layer', $newLayer);
        $stmt->bindValue(':full_name', $input['full_name'] ?? null);
        $stmt->bindValue(':date_of_birth', $input['date_of_birth'] ?? null);
        $stmt->bindValue(':date_of_death', $input['date_of_death'] ?? null);
        $stmt->bindValue(':date_of_burial', $input['date_of_burial'] ?? null);
        $stmt->bindValue(':age', $input['age'] ?? null);
        $stmt->bindValue(':cause_of_death', $input['cause_of_death'] ?? null);
        $stmt->bindValue(':next_of_kin', $input['next_of_kin'] ?? null);
        $stmt->bindValue(':next_of_kin_contact', $input['next_of_kin_contact'] ?? null);
        $stmt->bindValue(':deceased_info', $input['deceased_info'] ?? null);
        $stmt->bindValue(':remarks', $input['remarks'] ?? null);

        if ($stmt->execute()) {
            if ($oldLotId !== null && $oldLayer !== null && ($oldLotId !== $newLotId || $oldLayer !== $newLayer)) {
                $freeStmt = $conn->prepare("
                    UPDATE lot_layers 
                    SET is_occupied = 0, burial_record_id = NULL 
                    WHERE lot_id = :lot_id AND layer_number = :layer AND burial_record_id = :burial_record_id
                ");
                $freeStmt->bindParam(':lot_id', $oldLotId);
                $freeStmt->bindParam(':layer', $oldLayer);
                $freeStmt->bindParam(':burial_record_id', $input['id']);
                $freeStmt->execute();
                updateLotStatus($conn, $oldLotId);
            }

            $occupyStmt = $conn->prepare("
                UPDATE lot_layers 
                SET is_occupied = 1, burial_record_id = :burial_record_id, updated_at = CURRENT_TIMESTAMP
                WHERE lot_id = :lot_id AND layer_number = :layer
            ");
            $occupyStmt->bindParam(':burial_record_id', $input['id']);
            $occupyStmt->bindParam(':lot_id', $newLotId);
            $occupyStmt->bindParam(':layer', $newLayer);
            $occupyStmt->execute();

            updateLotStatus($conn, $newLotId);

            // Get lot number for description
            $lotStmt = $conn->prepare("SELECT lot_number FROM cemetery_lots WHERE id = :id");
            $lotStmt->bindParam(':id', $newLotId);
            $lotStmt->execute();
            $lotNum = $lotStmt->fetchColumn() ?: 'Unknown';

            $name = $input['full_name'] ?? 'Record';
            if ($oldLotId !== null && ($oldLotId !== $newLotId || $oldLayer !== $newLayer)) {
                logActivity($conn, 'UPDATE_RECORD', 'deceased_records', $input['id'], "$name is move in $lotNum layer $newLayer ($lotNum occupied)");
            } else {
                logActivity($conn, 'UPDATE_RECORD', 'deceased_records', $input['id'], "$name assigned on $lotNum ($lotNum occupied)");
            }

            echo json_encode(['success' => true, 'message' => 'Record updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update record']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleDelete($conn, $input) {
    try {
        $id = isset($input['id']) ? $input['id'] : (isset($_GET['id']) ? $_GET['id'] : null);
        $action = $input['action'] ?? 'archive'; // Default to archive
        
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Missing record ID']);
            return;
        }
        
        // Get the burial record info before deletion
        $stmt = $conn->prepare("
            SELECT dr.full_name, dr.lot_id, dr.layer, cl.lot_number 
            FROM deceased_records dr 
            LEFT JOIN cemetery_lots cl ON dr.lot_id = cl.id 
            WHERE dr.id = :id
        ");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $record = $stmt->fetch();
        
        if (!$record) {
            echo json_encode(['success' => false, 'message' => 'Record not found']);
            return;
        }
        
        $recordName = $record['full_name'];
        $lotInfo = $record['lot_number'] ? "lot " . $record['lot_number'] : "lot unassigned";
        
        if ($action === 'restore') {
            $stmt = $conn->prepare("UPDATE deceased_records SET is_archived = 0 WHERE id = :id");
            $stmt->bindParam(':id', $id);
            if ($stmt->execute()) {
                logActivity($conn, 'RESTORE_RECORD', 'deceased_records', $id, "Burial record for $recordName is restored ($lotInfo)");
                echo json_encode(['success' => true, 'message' => 'Record restored successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to restore record']);
            }
            return;
        }

        if ($action === 'permanent_delete') {
            $stmt = $conn->prepare("DELETE FROM deceased_records WHERE id = :id");
            $stmt->bindParam(':id', $id);
            if ($stmt->execute()) {
                logActivity($conn, 'DELETE_RECORD', 'deceased_records', $id, "Burial record for $recordName is permanently removed ($lotInfo)");
                echo json_encode(['success' => true, 'message' => 'Record permanently deleted']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to permanently delete record']);
            }
            return;
        }

        // Default: Archive
        $stmt = $conn->prepare("UPDATE deceased_records SET is_archived = 1 WHERE id = :id");
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
             logActivity($conn, 'ARCHIVE_RECORD', 'deceased_records', $id, "Burial record for $recordName is moved to archive ($lotInfo)");
             // Update the lot layer to mark it as vacant
            if ($record && $record['lot_id']) {
                $updateStmt = $conn->prepare("
                    UPDATE lot_layers 
                    SET is_occupied = 0, burial_record_id = NULL 
                    WHERE lot_id = :lot_id AND layer_number = :layer
                ");
                $updateStmt->bindParam(':lot_id', $record['lot_id']);
                $updateStmt->bindParam(':layer', $record['layer']);
                $updateStmt->execute();
                
                // Update lot status based on remaining occupied layers
                $checkStmt = $conn->prepare("SELECT COUNT(*) as occupied_count FROM lot_layers WHERE lot_id = :lot_id AND is_occupied = 1");
                $checkStmt->bindParam(':lot_id', $record['lot_id']);
                $checkStmt->execute();
                $count = $checkStmt->fetch();
                
                $status = $count['occupied_count'] > 0 ? 'Occupied' : 'Vacant';
                $conn->exec("UPDATE cemetery_lots SET status = '{$status}' WHERE id = " . intval($record['lot_id']));
            }
            
            echo json_encode(['success' => true, 'message' => 'Record archived successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to archive record']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateLotStatus($conn, $lotId) {
    $checkStmt = $conn->prepare("SELECT COUNT(*) as occupied_count FROM lot_layers WHERE lot_id = :lot_id AND is_occupied = 1");
    $checkStmt->bindParam(':lot_id', $lotId);
    $checkStmt->execute();
    $result = $checkStmt->fetch();

    $status = ($result && intval($result['occupied_count']) > 0) ? 'Occupied' : 'Vacant';
    $updateStmt = $conn->prepare("UPDATE cemetery_lots SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :lot_id");
    $updateStmt->bindParam(':status', $status);
    $updateStmt->bindParam(':lot_id', $lotId);
    $updateStmt->execute();
}
?>
