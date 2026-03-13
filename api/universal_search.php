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
        SELECT id, lot_number as title, section, 'lot' as type 
        FROM cemetery_lots 
        WHERE lot_number LIKE ? OR section LIKE ? OR block LIKE ?
        LIMIT 5
    ");
    $stmt->execute([$searchParam, $searchParam, $searchParam]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'id' => $row['id'],
            'title' => "Lot " . $row['title'],
            'subtitle' => "Section: " . $row['section'],
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