<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
if (!$conn) die("No connection");

// Get all lots with l-410 and above
$rows = $conn->query("SELECT id, lot_number FROM cemetery_lots WHERE lot_number LIKE 'l-%'")->fetchAll(PDO::FETCH_ASSOC);
$toDelete = [];
foreach ($rows as $r) {
    if (preg_match('/^l-(\d+)$/', $r['lot_number'], $m) && intval($m[1]) >= 410) {
        $toDelete[] = $r['id'];
    }
}

$conn->beginTransaction();
foreach ($toDelete as $lotId) {
    $conn->prepare("DELETE FROM lot_layers WHERE lot_id = ?")->execute([$lotId]);
    $conn->prepare("DELETE FROM cemetery_lots WHERE id = ?")->execute([$lotId]);
}
$conn->commit();

echo "<p style='color:green;font-family:sans-serif;padding:20px;font-size:16px;'>✓ Deleted " . count($toDelete) . " lots (l-410 and above).</p>";
echo "<a href='public/index.php' style='font-family:sans-serif;'>Go to Lot Management</a>";
