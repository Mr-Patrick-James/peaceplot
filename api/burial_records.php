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
        $id = isset($_GET['id']) ? intval($_GET['id']) : null;
        
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
            $stmt = $conn->query("
                SELECT dr.*, cl.lot_number, cl.section, cl.block 
                FROM deceased_records dr 
                LEFT JOIN cemetery_lots cl ON dr.lot_id = cl.id 
                ORDER BY dr.date_of_death DESC
            ");
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
            
            echo json_encode(['success' => true, 'data' => $results]);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handlePost($conn, $input) {
    try {
        if (!isset($input['lot_id']) || !isset($input['full_name'])) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            return;
        }
        
        // Get the layer number (default to 1 if not provided)
        $layer = isset($input['layer']) ? intval($input['layer']) : 1;
        
        // Check if the specified layer is available
        $stmt = $conn->prepare("SELECT is_occupied, burial_record_id FROM lot_layers WHERE lot_id = :lot_id AND layer_number = :layer");
        $stmt->bindParam(':lot_id', $input['lot_id']);
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
        
        $stmt->bindParam(':lot_id', $input['lot_id']);
        $stmt->bindParam(':layer', $layer);
        $stmt->bindParam(':full_name', $input['full_name']);
        $stmt->bindParam(':date_of_birth', $input['date_of_birth']);
        $stmt->bindParam(':date_of_death', $input['date_of_death']);
        $stmt->bindParam(':date_of_burial', $input['date_of_burial']);
        $stmt->bindParam(':age', $input['age']);
        $stmt->bindParam(':cause_of_death', $input['cause_of_death']);
        $stmt->bindParam(':next_of_kin', $input['next_of_kin']);
        $stmt->bindParam(':next_of_kin_contact', $input['next_of_kin_contact']);
        $stmt->bindParam(':deceased_info', $input['deceased_info']);
        $stmt->bindParam(':remarks', $input['remarks']);
        
        if ($stmt->execute()) {
            $lastId = $conn->lastInsertId();
            
            // Update the lot layer to mark it as occupied
            $updateStmt = $conn->prepare("
                UPDATE lot_layers 
                SET is_occupied = 1, burial_record_id = :burial_record_id 
                WHERE lot_id = :lot_id AND layer_number = :layer
            ");
            $updateStmt->bindParam(':burial_record_id', $lastId);
            $updateStmt->bindParam(':lot_id', $input['lot_id']);
            $updateStmt->bindParam(':layer', $layer);
            $updateStmt->execute();
            
            // Update lot status based on layer occupancy
            $checkStmt = $conn->prepare("SELECT COUNT(*) as occupied_count FROM lot_layers WHERE lot_id = :lot_id AND is_occupied = 1");
            $checkStmt->bindParam(':lot_id', $input['lot_id']);
            $checkStmt->execute();
            $result = $checkStmt->fetch();
            
            $status = $result['occupied_count'] > 0 ? 'Occupied' : 'Vacant';
            $conn->exec("UPDATE cemetery_lots SET status = '{$status}' WHERE id = " . intval($input['lot_id']));
            
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
        
        $stmt = $conn->prepare("
            UPDATE deceased_records 
            SET lot_id = :lot_id,
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
        
        $stmt->bindParam(':id', $input['id']);
        $stmt->bindParam(':lot_id', $input['lot_id']);
        $stmt->bindParam(':full_name', $input['full_name']);
        $stmt->bindParam(':date_of_birth', $input['date_of_birth']);
        $stmt->bindParam(':date_of_death', $input['date_of_death']);
        $stmt->bindParam(':date_of_burial', $input['date_of_burial']);
        $stmt->bindParam(':age', $input['age']);
        $stmt->bindParam(':cause_of_death', $input['cause_of_death']);
        $stmt->bindParam(':next_of_kin', $input['next_of_kin']);
        $stmt->bindParam(':next_of_kin_contact', $input['next_of_kin_contact']);
        $stmt->bindParam(':deceased_info', $input['deceased_info']);
        $stmt->bindParam(':remarks', $input['remarks']);
        
        if ($stmt->execute()) {
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
        
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Missing record ID']);
            return;
        }
        
        // Get the burial record info before deletion
        $stmt = $conn->prepare("SELECT lot_id, layer FROM deceased_records WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $record = $stmt->fetch();
        
        if (!$record) {
            echo json_encode(['success' => false, 'message' => 'Record not found']);
            return;
        }
        
        $stmt = $conn->prepare("DELETE FROM deceased_records WHERE id = :id");
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
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
            
            echo json_encode(['success' => true, 'message' => 'Record deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete record']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
