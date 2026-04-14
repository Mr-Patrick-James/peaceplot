<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
if (!$conn) die("No connection");

// Find Block 5 Section 1 id
$sec = $conn->query("SELECT s.id, s.name, b.name as block FROM sections s LEFT JOIN blocks b ON s.block_id = b.id WHERE b.name = 'Block 5' AND s.name = 'Section 1'")->fetch(PDO::FETCH_ASSOC);
if (!$sec) die("<p style='font-family:sans-serif;color:red;padding:20px;'>Block 5 / Section 1 not found. Please create it first.</p>");

// Find global max
$rows = $conn->query("SELECT lot_number FROM cemetery_lots WHERE lot_number LIKE 'l-%'")->fetchAll(PDO::FETCH_COLUMN);
$max = 0;
foreach ($rows as $l) {
    if (preg_match('/^l-(\d+)$/', $l, $m) && intval($m[1]) > $max) $max = intval($m[1]);
}

$start = $max + 1;
$end   = $max + 300;
$secId = $sec['id'];

echo "<pre style='font-family:monospace;padding:20px;'>";
echo "Section: {$sec['name']} | {$sec['block']} (id:$secId)\n";
echo "Global max: l-$max\n";
echo "Will create: l-$start to l-$end\n";
echo "</pre>";

if (isset($_POST['go'])) {
    $conn->beginTransaction();
    for ($i = $start; $i <= $end; $i++) {
        $stmt = $conn->prepare("INSERT INTO cemetery_lots (lot_number, section_id, status, layers) VALUES (?, ?, 'Vacant', 1)");
        $stmt->execute(["l-$i", $secId]);
        $lotId = $conn->lastInsertId();
        $conn->prepare("INSERT INTO lot_layers (lot_id, layer_number, is_occupied) VALUES (?, 1, 0)")->execute([$lotId]);
    }
    $conn->commit();
    echo "<p style='color:green;font-family:sans-serif;padding:20px;font-size:16px;'>✓ Created 300 lots (l-$start to l-$end) in Block 5, Section 1.</p>";
    echo "<a href='public/index.php' style='font-family:sans-serif;'>Go to Lot Management</a>";
} else {
    echo "<form method='POST' style='font-family:sans-serif;padding:0 20px;'>
        <button name='go' style='padding:10px 24px;background:#2f6df6;color:#fff;border:none;border-radius:8px;font-size:15px;cursor:pointer;'>
            Confirm — Create l-$start to l-$end
        </button>
    </form>";
}
