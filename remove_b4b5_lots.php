<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
if (!$conn) die("No connection");

$conn->beginTransaction();
$deleted = 0;
for ($i = 902; $i <= 980; $i++) {
    $stmt = $conn->prepare("SELECT id FROM cemetery_lots WHERE lot_number = ?");
    $stmt->execute(["l-$i"]);
    $id = $stmt->fetchColumn();
    if ($id) {
        $conn->prepare("DELETE FROM lot_layers WHERE lot_id = ?")->execute([$id]);
        $conn->prepare("DELETE FROM cemetery_lots WHERE id = ?")->execute([$id]);
        $deleted++;
    }
}
$conn->commit();

echo "<p style='color:green;font-family:sans-serif;padding:20px;font-size:16px;'>✓ Deleted $deleted lots (l-902 to l-980).</p>";
echo "<a href='public/index.php' style='font-family:sans-serif;'>Go to Lot Management</a>";
