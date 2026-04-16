<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
if (!$conn) die("No connection");

$conn->beginTransaction();
$moved = 0;

for ($i = 1236; $i <= 1374; $i++) {
    $lot = $conn->prepare("SELECT id FROM cemetery_lots WHERE lot_number = ?");
    $lot->execute(["l-$i"]);
    $lotId = $lot->fetchColumn();
    if (!$lotId) continue;

    // Move all burial records on layer 1 to layer 2
    $conn->prepare("UPDATE deceased_records SET layer = 2 WHERE lot_id = ? AND layer = 1 AND is_archived = 0")->execute([$lotId]);
    $count = $conn->prepare("SELECT COUNT(*) FROM deceased_records WHERE lot_id = ? AND layer = 2 AND is_archived = 0");
    $count->execute([$lotId]);

    // Update lot_layers: free layer 1, occupy layer 2
    $conn->prepare("UPDATE lot_layers SET is_occupied = 0, burial_record_id = NULL WHERE lot_id = ? AND layer_number = 1")->execute([$lotId]);
    $conn->prepare("UPDATE lot_layers SET is_occupied = 1 WHERE lot_id = ? AND layer_number = 2 AND EXISTS (SELECT 1 FROM deceased_records WHERE lot_id = ? AND layer = 2 AND is_archived = 0)")->execute([$lotId, $lotId]);

    $moved++;
}

$conn->commit();
echo "<p style='color:green;font-family:sans-serif;padding:20px;font-size:16px;'>✓ Moved layer 1 burials to layer 2 for $moved lots (l-1236 to l-1374).</p>";
echo "<a href='public/index.php' style='font-family:sans-serif;'>Go to Lot Management</a>";
