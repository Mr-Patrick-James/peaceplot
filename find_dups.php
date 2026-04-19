<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
if (!$conn) die("No connection");

$result = $conn->query("
    SELECT full_name, COUNT(*) as cnt, GROUP_CONCAT(id) as ids
    FROM deceased_records
    WHERE lot_id IS NULL
    GROUP BY full_name
    HAVING COUNT(*) > 1
    ORDER BY full_name
");

$rows = $result->fetchAll(PDO::FETCH_ASSOC);
echo "Duplicate unassigned records: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo $r['full_name'] . " | count=" . $r['cnt'] . " | ids=" . $r['ids'] . "\n";
}
