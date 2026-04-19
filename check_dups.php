<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db_file = __DIR__ . '/database/peaceplot.db';
echo "DB path: $db_file\n";
echo "DB exists: " . (file_exists($db_file) ? 'YES' : 'NO') . "\n";

try {
    $conn = new PDO("sqlite:" . $db_file);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $total = $conn->query("SELECT COUNT(*) FROM deceased_records")->fetchColumn();
    echo "Total records: $total\n";

    $unassigned = $conn->query("SELECT COUNT(*) FROM deceased_records WHERE lot_id IS NULL")->fetchColumn();
    echo "Unassigned (lot_id IS NULL): $unassigned\n";

    $assigned = $conn->query("SELECT COUNT(*) FROM deceased_records WHERE lot_id IS NOT NULL")->fetchColumn();
    echo "Assigned: $assigned\n";

    // Duplicates across all records
    $result = $conn->query("
        SELECT full_name, COUNT(*) as cnt, GROUP_CONCAT(id) as ids, GROUP_CONCAT(COALESCE(lot_id,'NULL')) as lot_ids
        FROM deceased_records
        GROUP BY full_name
        HAVING COUNT(*) > 1
        ORDER BY full_name
    ");
    $rows = $result->fetchAll(PDO::FETCH_ASSOC);
    echo "\nDuplicate names: " . count($rows) . "\n";
    foreach ($rows as $r) {
        echo "  [{$r['full_name']}] count={$r['cnt']} ids={$r['ids']} lot_ids={$r['lot_ids']}\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
