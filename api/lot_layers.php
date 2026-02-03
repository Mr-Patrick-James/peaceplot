<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';

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
        $lotId = isset($_GET['lot_id']) ? intval($_GET['lot_id']) : null;
        
        if ($lotId) {
            // Get layers for a specific lot with burial information
            $stmt = $conn->prepare("
                SELECT ll.*, dr.full_name as deceased_name, dr.id as burial_record_id
                FROM lot_layers ll
                LEFT JOIN deceased_records dr ON ll.burial_record_id = dr.id
                WHERE ll.lot_id = :lot_id
                ORDER BY ll.layer_number ASC
            ");
            $stmt->bindParam(':lot_id', $lotId);
            $stmt->execute();
            $layers = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $layers]);
        } else {
            // Get all layers
            $stmt = $conn->query("
                SELECT ll.*, cl.lot_number, dr.full_name as deceased_name
                FROM lot_layers ll
                LEFT JOIN cemetery_lots cl ON ll.lot_id = cl.id
                LEFT JOIN deceased_records dr ON ll.burial_record_id = dr.id
                ORDER BY cl.lot_number, ll.layer_number
            ");
            $layers = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $layers]);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handlePost($conn, $input) {
    try {
        $action = isset($input['action']) ? $input['action'] : 'add_layer';
        
        if ($action === 'add_layer') {
            if (!isset($input['lot_id'])) {
                echo json_encode(['success' => false, 'message' => 'Lot ID is required']);
                return;
            }
            
            $lotId = intval($input['lot_id']);
            
            // Get the next layer number for this lot
            $stmt = $conn->prepare("SELECT MAX(layer_number) as max_layer FROM lot_layers WHERE lot_id = :lot_id");
            $stmt->bindParam(':lot_id', $lotId);
            $stmt->execute();
            $result = $stmt->fetch();
            $nextLayer = ($result['max_layer'] ?: 0) + 1;
            
            // Insert new layer
            $stmt = $conn->prepare("
                INSERT INTO lot_layers (lot_id, layer_number, is_occupied) 
                VALUES (:lot_id, :layer_number, 0)
            ");
            $stmt->bindParam(':lot_id', $lotId);
            $stmt->bindParam(':layer_number', $nextLayer);
            
            if ($stmt->execute()) {
                // Update the lot's total layers count
                $stmt = $conn->prepare("UPDATE cemetery_lots SET layers = :layers WHERE id = :lot_id");
                $stmt->bindParam(':layers', $nextLayer);
                $stmt->bindParam(':lot_id', $lotId);
                $stmt->execute();
                
                echo json_encode(['success' => true, 'message' => 'Layer added successfully', 'layer_number' => $nextLayer]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add layer']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handlePut($conn, $input) {
    try {
        if (!isset($input['lot_id']) || !isset($input['layer_number'])) {
            echo json_encode(['success' => false, 'message' => 'Lot ID and layer number are required']);
            return;
        }
        
        $lotId = intval($input['lot_id']);
        $layerNumber = intval($input['layer_number']);
        $burialRecordId = isset($input['burial_record_id']) ? intval($input['burial_record_id']) : null;
        $isOccupied = isset($input['is_occupied']) ? intval($input['is_occupied']) : 0;
        
        // Update layer
        $stmt = $conn->prepare("
            UPDATE lot_layers 
            SET is_occupied = :is_occupied, 
                burial_record_id = :burial_record_id,
                updated_at = CURRENT_TIMESTAMP
            WHERE lot_id = :lot_id AND layer_number = :layer_number
        ");
        $stmt->bindParam(':lot_id', $lotId);
        $stmt->bindParam(':layer_number', $layerNumber);
        $stmt->bindParam(':is_occupied', $isOccupied);
        $stmt->bindParam(':burial_record_id', $burialRecordId);
        
        if ($stmt->execute()) {
            // Update lot status based on layer occupancy
            updateLotStatus($conn, $lotId);
            
            echo json_encode(['success' => true, 'message' => 'Layer updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update layer']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleDelete($conn, $input) {
    try {
        if (!isset($input['lot_id']) || !isset($input['layer_number'])) {
            echo json_encode(['success' => false, 'message' => 'Lot ID and layer number are required']);
            return;
        }
        
        $lotId = intval($input['lot_id']);
        $layerNumber = intval($input['layer_number']);
        
        // Check if layer is occupied
        $stmt = $conn->prepare("SELECT is_occupied FROM lot_layers WHERE lot_id = :lot_id AND layer_number = :layer_number");
        $stmt->bindParam(':lot_id', $lotId);
        $stmt->bindParam(':layer_number', $layerNumber);
        $stmt->execute();
        $layer = $stmt->fetch();
        
        if ($layer && $layer['is_occupied']) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete occupied layer']);
            return;
        }
        
        // Delete layer
        $stmt = $conn->prepare("DELETE FROM lot_layers WHERE lot_id = :lot_id AND layer_number = :layer_number");
        $stmt->bindParam(':lot_id', $lotId);
        $stmt->bindParam(':layer_number', $layerNumber);
        
        if ($stmt->execute()) {
            // Update lot's total layers count
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM lot_layers WHERE lot_id = :lot_id");
            $stmt->bindParam(':lot_id', $lotId);
            $stmt->execute();
            $count = $stmt->fetch();
            
            $stmt = $conn->prepare("UPDATE cemetery_lots SET layers = :layers WHERE id = :lot_id");
            $stmt->bindParam(':layers', $count['count']);
            $stmt->bindParam(':lot_id', $lotId);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Layer deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete layer']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateLotStatus($conn, $lotId) {
    // Check if any layers are occupied
    $stmt = $conn->prepare("SELECT COUNT(*) as occupied_count FROM lot_layers WHERE lot_id = :lot_id AND is_occupied = 1");
    $stmt->bindParam(':lot_id', $lotId);
    $stmt->execute();
    $result = $stmt->fetch();
    
    $occupiedCount = $result['occupied_count'];
    $status = $occupiedCount > 0 ? 'Occupied' : 'Vacant';
    
    // Update lot status
    $stmt = $conn->prepare("UPDATE cemetery_lots SET status = :status WHERE id = :lot_id");
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':lot_id', $lotId);
    $stmt->execute();
}
?>
