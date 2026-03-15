<?php
require_once __DIR__ . '/../config/auth.php';
requireLogin();

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

header('Content-Type: application/json');

$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    echo json_encode(['success' => true, 'data' => []]);
    exit;
}

try {
    $results = [];
    $searchParam = "%$query%";

    // Search Lots
    $stmt = $db->prepare("
        SELECT cl.id, cl.lot_number as title, s.name as section_name, 'lot' as type 
        FROM cemetery_lots cl
        LEFT JOIN sections s ON cl.section_id = s.id
        LEFT JOIN blocks b ON s.block_id = b.id
        WHERE cl.lot_number LIKE ? OR s.name LIKE ? OR b.name LIKE ?
        LIMIT 5
    ");
    $stmt->execute([$searchParam, $searchParam, $searchParam]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'id' => $row['id'],
            'title' => "Lot " . $row['title'],
            'subtitle' => "Section: " . ($row['section_name'] ?: 'No Section'),
            'type' => 'lot',
            'url' => "index.php?search=" . urlencode($row['title'])
        ];
    }

    // Search Deceased Records
    $stmt = $db->prepare("
        SELECT id, full_name as title, date_of_death, 'deceased' as type 
        FROM deceased_records 
        WHERE full_name LIKE ?
        LIMIT 5
    ");
    $stmt->execute([$searchParam]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'subtitle' => "Deceased Record" . ($row['date_of_death'] ? " (Died: " . $row['date_of_death'] . ")" : ""),
            'type' => 'deceased',
            'url' => "burial-records.php?search=" . urlencode($row['title'])
        ];
    }

    echo json_encode(['success' => true, 'data' => $results]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>