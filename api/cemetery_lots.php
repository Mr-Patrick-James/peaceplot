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
        $id = isset($_GET['id']) ? intval($_GET['id']) : null;
        
        if ($id) {
            if ($conn) {
                $stmt = $conn->prepare("
                    SELECT cl.*, dr.full_name as deceased_name 
                    FROM cemetery_lots cl 
                    LEFT JOIN deceased_records dr ON cl.id = dr.lot_id 
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
                $stmt = $conn->query("
                    SELECT cl.*, dr.full_name as deceased_name 
                    FROM cemetery_lots cl 
                    LEFT JOIN deceased_records dr ON cl.id = dr.lot_id 
                    ORDER BY cl.lot_number
                ");
                $results = $stmt->fetchAll();
                echo json_encode(['success' => true, 'data' => $results]);
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
        if (!isset($input['lot_number']) || !isset($input['section']) || !isset($input['status'])) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            return;
        }
        
        $stmt = $conn->prepare("
            INSERT INTO cemetery_lots (lot_number, section, block, position, status, price) 
            VALUES (:lot_number, :section, :block, :position, :status, :price)
        ");
        
        $stmt->bindParam(':lot_number', $input['lot_number']);
        $stmt->bindParam(':section', $input['section']);
        $stmt->bindParam(':block', $input['block']);
        $stmt->bindParam(':position', $input['position']);
        $stmt->bindParam(':status', $input['status']);
        $stmt->bindParam(':price', $input['price']);
        
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
        if (!isset($input['id'])) {
            echo json_encode(['success' => false, 'message' => 'Missing lot ID']);
            return;
        }
        
        $stmt = $conn->prepare("
            UPDATE cemetery_lots 
            SET lot_number = :lot_number, 
                section = :section, 
                block = :block, 
                position = :position, 
                status = :status, 
                price = :price,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        
        $stmt->bindParam(':id', $input['id']);
        $stmt->bindParam(':lot_number', $input['lot_number']);
        $stmt->bindParam(':section', $input['section']);
        $stmt->bindParam(':block', $input['block']);
        $stmt->bindParam(':position', $input['position']);
        $stmt->bindParam(':status', $input['status']);
        $stmt->bindParam(':price', $input['price']);
        
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
