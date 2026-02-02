<?php
header('Content-Type: application/json');

require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    // Create sample cemetery lots
    $sampleLots = [
        ['A-001', 'Section A', 'Block 1', 'Vacant', 12.0, 25000.00],
        ['A-002', 'Section A', 'Block 1', 'Vacant', 12.0, 25000.00],
        ['A-003', 'Section A', 'Block 1', 'Occupied', 12.0, 25000.00],
        ['A-004', 'Section A', 'Block 1', 'Vacant', 12.0, 25000.00],
        ['A-005', 'Section A', 'Block 1', 'Reserved', 12.0, 25000.00],
        ['B-001', 'Section B', 'Block 1', 'Vacant', 15.0, 30000.00],
        ['B-002', 'Section B', 'Block 1', 'Vacant', 15.0, 30000.00],
        ['B-003', 'Section B', 'Block 1', 'Occupied', 15.0, 30000.00],
        ['B-004', 'Section B', 'Block 1', 'Vacant', 15.0, 30000.00],
        ['C-001', 'Section C', 'Block 1', 'Vacant', 18.0, 35000.00],
        ['C-002', 'Section C', 'Block 1', 'Vacant', 18.0, 35000.00],
        ['C-003', 'Section C', 'Block 1', 'Occupied', 18.0, 35000.00],
    ];
    
    $inserted = 0;
    $updated = 0;
    
    foreach ($sampleLots as $lot) {
        // Check if lot already exists
        $checkStmt = $conn->prepare("SELECT id FROM cemetery_lots WHERE lot_number = :lot_number");
        $checkStmt->execute([':lot_number' => $lot[0]]);
        $existing = $checkStmt->fetch();
        
        if ($existing) {
            // Update existing lot
            $updateStmt = $conn->prepare("
                UPDATE cemetery_lots 
                SET section = :section, block = :block, status = :status, 
                    size_sqm = :size_sqm, price = :price
                WHERE lot_number = :lot_number
            ");
            $updateStmt->execute([
                ':lot_number' => $lot[0],
                ':section' => $lot[1],
                ':block' => $lot[2],
                ':status' => $lot[3],
                ':size_sqm' => $lot[4],
                ':price' => $lot[5]
            ]);
            $updated++;
        } else {
            // Insert new lot
            $insertStmt = $conn->prepare("
                INSERT INTO cemetery_lots 
                (lot_number, section, block, status, size_sqm, price) 
                VALUES 
                (:lot_number, :section, :block, :status, :size_sqm, :price)
            ");
            $insertStmt->execute([
                ':lot_number' => $lot[0],
                ':section' => $lot[1],
                ':block' => $lot[2],
                ':status' => $lot[3],
                ':size_sqm' => $lot[4],
                ':price' => $lot[5]
            ]);
            $inserted++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Successfully created $inserted new lots and updated $updated existing lots",
        'total_lots' => $inserted + $updated
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
