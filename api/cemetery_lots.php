<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';

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
            INSERT INTO cemetery_lots (lot_number, section, block, position, status, size_sqm, price) 
            VALUES (:lot_number, :section, :block, :position, :status, :size_sqm, :price)
        ");
        
        $stmt->bindParam(':lot_number', $input['lot_number']);
        $stmt->bindParam(':section', $input['section']);
        $stmt->bindParam(':block', $input['block']);
        $stmt->bindParam(':position', $input['position']);
        $stmt->bindParam(':status', $input['status']);
        $stmt->bindParam(':size_sqm', $input['size_sqm']);
        $stmt->bindParam(':price', $input['price']);
        
        if ($stmt->execute()) {
            $lastId = $conn->lastInsertId();
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
                size_sqm = :size_sqm, 
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
        $stmt->bindParam(':size_sqm', $input['size_sqm']);
        $stmt->bindParam(':price', $input['price']);
        
        if ($stmt->execute()) {
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
        
        $stmt = $conn->prepare("DELETE FROM cemetery_lots WHERE id = :id");
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Lot deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete lot']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
