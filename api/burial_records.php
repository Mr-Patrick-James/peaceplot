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
        
        $stmt = $conn->prepare("
            INSERT INTO deceased_records 
            (lot_id, full_name, date_of_birth, date_of_death, date_of_burial, age, 
             cause_of_death, next_of_kin, next_of_kin_contact, remarks) 
            VALUES 
            (:lot_id, :full_name, :date_of_birth, :date_of_death, :date_of_burial, :age,
             :cause_of_death, :next_of_kin, :next_of_kin_contact, :remarks)
        ");
        
        $stmt->bindParam(':lot_id', $input['lot_id']);
        $stmt->bindParam(':full_name', $input['full_name']);
        $stmt->bindParam(':date_of_birth', $input['date_of_birth']);
        $stmt->bindParam(':date_of_death', $input['date_of_death']);
        $stmt->bindParam(':date_of_burial', $input['date_of_burial']);
        $stmt->bindParam(':age', $input['age']);
        $stmt->bindParam(':cause_of_death', $input['cause_of_death']);
        $stmt->bindParam(':next_of_kin', $input['next_of_kin']);
        $stmt->bindParam(':next_of_kin_contact', $input['next_of_kin_contact']);
        $stmt->bindParam(':remarks', $input['remarks']);
        
        if ($stmt->execute()) {
            $lastId = $conn->lastInsertId();
            
            $conn->exec("UPDATE cemetery_lots SET status = 'Occupied' WHERE id = " . intval($input['lot_id']));
            
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
        
        $stmt = $conn->prepare("SELECT lot_id FROM deceased_records WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $record = $stmt->fetch();
        
        $stmt = $conn->prepare("DELETE FROM deceased_records WHERE id = :id");
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            if ($record && $record['lot_id']) {
                $checkStmt = $conn->prepare("SELECT COUNT(*) FROM deceased_records WHERE lot_id = :lot_id");
                $checkStmt->bindParam(':lot_id', $record['lot_id']);
                $checkStmt->execute();
                $count = $checkStmt->fetchColumn();
                
                if ($count == 0) {
                    $conn->exec("UPDATE cemetery_lots SET status = 'Vacant' WHERE id = " . intval($record['lot_id']));
                }
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
