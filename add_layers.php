<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
if (!$conn) die("No connection");

$conn->beginTransaction();
$updated = 0;

for ($i = 1236; $i <= 1374; $i++) {
    $lot = $conn->prepare("SELECT id FROM cemetery_lots WHERE lot_number = ?");
    $lot->execute(["l-$i"]);
    $lotId = $lot->fetchColumn();
    if (!$lotId) continue;

    // Add layer 2 if not exists
    $exists = $conn->prepare("SELECT id FROM lot_layers WHERE lot_id = ? AND layer_number = 2");
    $exists->execute([$lotId]);
    if (!$exists->fetch()) {
        $conn->prepare("INSERT INTO lot_layers (lot_id, layer_number, is_occupied) VALUES (?, 2, 0)")->execute([$lotId]);
        $conn->prepare("UPDATE cemetery_lots SET layers = 2 WHERE id = ?")->execute([$lotId]);
        $updated++;
    }
}

$conn->commit();
echo "<p style='color:green;font-family:sans-serif;padding:20px;font-size:16px;'>✓ Added layer 2 to $updated lots (l-1236 to l-1374).</p>";
echo "<a href='public/index.php' style='font-family:sans-serif;'>Go to Lot Management</a>";
