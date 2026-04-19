<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
if (!$conn) die("No connection");

// Get lot IDs for l-307 to l-347 (any section)
$lotIds = [];
for ($i = 808; $i <= 811; $i++) {
    $stmt = $conn->prepare("SELECT id FROM cemetery_lots WHERE lot_number = ?");
    $stmt->execute(["l-$i"]);
    $lot = $stmt->fetchColumn();
    if ($lot) $lotIds[] = $lot;
}

if (empty($lotIds)) {
    die("<p style='font-family:sans-serif;padding:20px;color:red;'>No lots found for l-307 to l-347.</p>");
}

$placeholders = implode(',', array_fill(0, count($lotIds), '?'));

$stmt = $conn->prepare("UPDATE deceased_records SET lot_id = NULL, layer = NULL WHERE lot_id IN ($placeholders)");
$stmt->execute($lotIds);
$updated = $stmt->rowCount();

$conn->prepare("UPDATE lot_layers SET is_occupied = 0, burial_record_id = NULL WHERE lot_id IN ($placeholders)")->execute($lotIds);
$conn->prepare("UPDATE cemetery_lots SET status = 'Vacant' WHERE id IN ($placeholders)")->execute($lotIds);

echo "<p style='color:green;font-family:sans-serif;padding:20px;font-size:16px;'>✓ Unassigned $updated burial records from lots l-174 to l-201.</p>";
echo "<a href='public/burial-records.php' style='font-family:sans-serif;'>Go to Burial Records</a>";
