<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
if (!$conn) die("No connection");

// Delete l-174 to l-218 in Section 1, Block 2 (section_id=2)
$conn->beginTransaction();
$deleted = 0;
for ($i = 174; $i <= 218; $i++) {
    $stmt = $conn->prepare("SELECT id FROM cemetery_lots WHERE lot_number = ? AND section_id = 2");
    $stmt->execute(["l-$i"]);
    $lot = $stmt->fetch();
    if ($lot) {
        $conn->prepare("DELETE FROM lot_layers WHERE lot_id = ?")->execute([$lot['id']]);
        $conn->prepare("DELETE FROM cemetery_lots WHERE id = ?")->execute([$lot['id']]);
        $deleted++;
    }
}
$conn->commit();

echo "<p style='color:green;font-family:sans-serif;padding:20px;font-size:16px;'>✓ Deleted $deleted lots (l-174 to l-218) from Section 1, Block 2.</p>";
echo "<a href='public/index.php' style='font-family:sans-serif;'>Go to Lot Management</a>";
