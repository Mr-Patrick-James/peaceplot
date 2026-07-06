<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
if (!$conn) die("No connection");

$conn->beginTransaction();
$updated = 0;

for ($i = 1102; $i <= 1235; $i++) {
    $lot = $conn->prepare("SELECT id FROM cemetery_lots WHERE lot_number = ?");
    $lot->execute(["l-$i"]);
    $lotId = $lot->fetchColumn();
    if (!$lotId) continue;

    $layersAdded = false;
    
    // Add layers 1, 2, 3, 4 if they don't exist
    for ($layer = 1; $layer <= 4; $layer++) {
        $exists = $conn->prepare("SELECT id FROM lot_layers WHERE lot_id = ? AND layer_number = ?");
        $exists->execute([$lotId, $layer]);
        if (!$exists->fetch()) {
            $conn->prepare("INSERT INTO lot_layers (lot_id, layer_number, is_occupied) VALUES (?, ?, 0)")->execute([$lotId, $layer]);
            $layersAdded = true;
        }
    }
    
    if ($layersAdded) {
        $conn->prepare("UPDATE cemetery_lots SET layers = 4 WHERE id = ?")->execute([$lotId]);
        $updated++;
    }
}

$conn->commit();
echo "<p style='color:green;font-family:sans-serif;padding:20px;font-size:16px;'>✓ Added 4 layers to $updated lots (l-1102 to l-1235).</p>";
echo "<a href='public/index.php' style='font-family:sans-serif;'>Go to Lot Management</a>";
