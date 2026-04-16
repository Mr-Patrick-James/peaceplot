<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
if (!$conn) die("No connection");

// Find global max
$rows = $conn->query("SELECT lot_number FROM cemetery_lots WHERE lot_number LIKE 'l-%'")->fetchAll(PDO::FETCH_COLUMN);
$max = 0;
foreach ($rows as $l) {
    if (preg_match('/^l-(\d+)$/', $l, $m) && intval($m[1]) > $max) $max = intval($m[1]);
}

$start = $max + 1;
$end   = $max + 205;

$sec = $conn->query("SELECT s.id FROM sections s LEFT JOIN blocks b ON s.block_id = b.id WHERE b.name = 'Block 9' AND s.name = 'Section 1'")->fetch();
if (!$sec) die("<p style='font-family:sans-serif;color:red;padding:20px;'>Block 3 / Section 1 not found. Please create it first.</p>");
$secId = $sec['id'];

$conn->beginTransaction();
for ($i = $start; $i <= $end; $i++) {
    $stmt = $conn->prepare("INSERT INTO cemetery_lots (lot_number, section_id, status, layers) VALUES (?, ?, 'Vacant', 1)");
    $stmt->execute(["l-$i", $secId]);
    $lotId = $conn->lastInsertId();
    $conn->prepare("INSERT INTO lot_layers (lot_id, layer_number, is_occupied) VALUES (?, 1, 0)")->execute([$lotId]);
}
$conn->commit();

echo "<p style='color:green;font-family:sans-serif;padding:20px;font-size:16px;'>✓ Created 100 lots (l-$start to l-$end) in Block 3, Section 1.</p>";
echo "<a href='public/index.php' style='font-family:sans-serif;'>Go to Lot Management</a>";
