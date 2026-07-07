<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
if (!$conn) die("No connection");

// Find Section 5 under Block 1
$section = $conn->prepare("SELECT s.id, s.name, b.name as block_name FROM sections s JOIN blocks b ON s.block_id = b.id WHERE b.name = 'Block 1' AND s.name = 'Section 5'");
$section->execute();
$sectionRow = $section->fetch();

if (!$sectionRow) die("<p style='color:red;font-family:sans-serif;padding:20px;'>Section 5 under Block 1 not found. Please create it first.</p>");

$sectionId = $sectionRow['id'];

$conn->beginTransaction();
$updated = 0;
$notFound = [];

for ($i = 154; $i <= 172; $i++) {
    $stmt = $conn->prepare("UPDATE cemetery_lots SET section_id = ?, updated_at = CURRENT_TIMESTAMP WHERE lot_number = ?");
    $stmt->execute([$sectionId, "l-$i"]);
    if ($stmt->rowCount() > 0) $updated++; else $notFound[] = "l-$i";
}

$conn->commit();

echo "<div style='font-family:sans-serif;padding:20px;'>";
echo "<p style='color:green;font-size:16px;'>✓ Updated $updated lots (l-154 to l-172) to Block 1 / Section 5 (section_id=$sectionId).</p>";

if (!empty($notFound)) {
    echo "<p style='color:orange;'>⚠ Not found: " . implode(', ', $notFound) . "</p>";
}

echo "<a href='public/index.php'>Go to Lot Management</a>";
echo "</div>";
