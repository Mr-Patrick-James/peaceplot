<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
if (!$conn) die("No connection");

// Step 1: Show summary
$total = $conn->query("SELECT COUNT(*) FROM deceased_records")->fetchColumn();
$unassigned = $conn->query("SELECT COUNT(*) FROM deceased_records WHERE lot_id IS NULL")->fetchColumn();
$assigned = $conn->query("SELECT COUNT(*) FROM deceased_records WHERE lot_id IS NOT NULL")->fetchColumn();

echo "<pre style='font-family:monospace;font-size:13px;padding:20px;'>";
echo "Total records  : $total\n";
echo "Assigned (map) : $assigned\n";
echo "Unassigned     : $unassigned\n\n";

// Step 2: Find duplicate names where at least one copy is unassigned
// Keep: the one with lot_id (assigned), or if all unassigned keep the lowest id
// Delete: all unassigned duplicates except the one to keep
$result = $conn->query("
    SELECT full_name, COUNT(*) as cnt,
           GROUP_CONCAT(id) as ids,
           GROUP_CONCAT(COALESCE(lot_id, 'NULL')) as lot_ids
    FROM deceased_records
    GROUP BY full_name
    HAVING COUNT(*) > 1
    ORDER BY full_name
");
$rows = $result->fetchAll(PDO::FETCH_ASSOC);

echo "Duplicate names found: " . count($rows) . "\n\n";

$toDelete = [];

foreach ($rows as $r) {
    $ids = explode(',', $r['ids']);
    $lotIds = explode(',', $r['lot_ids']);

    // Pair them up: [id => lot_id]
    $pairs = [];
    for ($i = 0; $i < count($ids); $i++) {
        $pairs[(int)$ids[$i]] = ($lotIds[$i] === 'NULL') ? null : (int)$lotIds[$i];
    }

    // Separate assigned vs unassigned
    $assignedIds   = array_keys(array_filter($pairs, fn($v) => $v !== null));
    $unassignedIds = array_keys(array_filter($pairs, fn($v) => $v === null));

    if (empty($unassignedIds)) continue; // no unassigned dups, skip

    if (!empty($assignedIds)) {
        // Has assigned copy — delete ALL unassigned copies
        foreach ($unassignedIds as $id) {
            $toDelete[] = $id;
        }
        echo "  [{$r['full_name']}] keep assigned id(s)=" . implode(',', $assignedIds) . " | delete unassigned=" . implode(',', $unassignedIds) . "\n";
    } else {
        // All unassigned — keep lowest id, delete the rest
        sort($unassignedIds);
        $keep = array_shift($unassignedIds);
        foreach ($unassignedIds as $id) {
            $toDelete[] = $id;
        }
        echo "  [{$r['full_name']}] all unassigned — keep id=$keep | delete=" . implode(',', $unassignedIds) . "\n";
    }
}

echo "\nTotal to delete: " . count($toDelete) . "\n";

if (empty($toDelete)) {
    echo "\nNothing to delete.\n";
    echo "</pre>";
    exit;
}

// Step 3: Delete
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    $placeholders = implode(',', array_fill(0, count($toDelete), '?'));
    $stmt = $conn->prepare("DELETE FROM deceased_records WHERE id IN ($placeholders)");
    $stmt->execute($toDelete);
    $deleted = $stmt->rowCount();
    echo "\n✓ Deleted $deleted duplicate records.\n";

    $newTotal = $conn->query("SELECT COUNT(*) FROM deceased_records")->fetchColumn();
    echo "New total: $newTotal\n";
} else {
    echo "\n<a href='remove_dups.php?confirm=yes' style='background:#c0392b;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px;font-size:14px;'>Confirm — Delete these duplicates</a>\n";
}

echo "</pre>";
echo "<br><a href='public/burial-records.php' style='font-family:sans-serif;'>Go to Burial Records</a>";
