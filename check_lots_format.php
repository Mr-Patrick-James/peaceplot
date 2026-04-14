<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$rows = $conn->query("SELECT id, lot_number, section_id FROM cemetery_lots ORDER BY id LIMIT 25")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre style='padding:20px;font-family:monospace;'>";
foreach ($rows as $r) {
    $hex = bin2hex($r['lot_number']);
    echo "id:{$r['id']} | raw:'{$r['lot_number']}' | hex:$hex | section:{$r['section_id']}\n";
}
// Also show the max detection
$all = $conn->query("SELECT lot_number FROM cemetery_lots")->fetchAll(PDO::FETCH_COLUMN);
$max = 0;
foreach ($all as $l) {
    if (preg_match('/^I-(\d+)$/u', trim($l), $m)) {
        if (intval($m[1]) > $max) $max = intval($m[1]);
    }
}
echo "\nDetected max: I-$max\n";
echo "</pre>";
