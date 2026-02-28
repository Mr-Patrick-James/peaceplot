<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('HTTP/1.1 403 Forbidden');
    exit('Unauthorized');
}

$reportType = $_GET['type'] ?? 'all_lots';
$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    die("Database connection failed");
}

try {
    $data = [];
    $filename = "report_" . $reportType . "_" . date('Y-m-d') . ".csv";
    $headers = [];

    switch ($reportType) {
        case 'all_lots':
            $stmt = $conn->query("
                SELECT cl.lot_number, cl.section, cl.block, cl.position, cl.status, cl.size_sqm, cl.price, dr.full_name as deceased_name 
                FROM cemetery_lots cl 
                LEFT JOIN deceased_records dr ON cl.id = dr.lot_id 
                ORDER BY cl.lot_number
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $headers = ['Lot Number', 'Section', 'Block', 'Position', 'Status', 'Size (sqm)', 'Price', 'Deceased Name'];
            break;

        case 'vacant_lots':
            $stmt = $conn->query("
                SELECT lot_number, section, block, position, status, size_sqm, price 
                FROM cemetery_lots 
                WHERE status = 'Vacant'
                ORDER BY lot_number
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $headers = ['Lot Number', 'Section', 'Block', 'Position', 'Status', 'Size (sqm)', 'Price'];
            break;

        case 'occupied_lots':
            $stmt = $conn->query("
                SELECT cl.lot_number, cl.section, cl.block, dr.full_name, dr.date_of_birth, dr.date_of_death, dr.date_of_burial 
                FROM cemetery_lots cl 
                JOIN deceased_records dr ON cl.id = dr.lot_id 
                WHERE cl.status = 'Occupied'
                ORDER BY cl.lot_number
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $headers = ['Lot Number', 'Section', 'Block', 'Deceased Name', 'Date of Birth', 'Date of Death', 'Date of Burial'];
            break;

        case 'reserved_lots':
            $stmt = $conn->query("
                SELECT cl.lot_number, cl.section, cl.block, cl.position, cl.status, cl.size_sqm, cl.price
                FROM cemetery_lots cl 
                WHERE cl.status = 'Reserved'
                ORDER BY cl.lot_number
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $headers = ['Lot Number', 'Section', 'Block', 'Position', 'Status', 'Size (sqm)', 'Price'];
            break;

        case 'recent_burials':
            $stmt = $conn->query("
                SELECT dr.full_name, cl.lot_number, cl.section, dr.date_of_birth, dr.date_of_death, dr.date_of_burial, dr.age 
                FROM deceased_records dr
                LEFT JOIN cemetery_lots cl ON dr.lot_id = cl.id
                ORDER BY dr.date_of_burial DESC
                LIMIT 100
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $headers = ['Full Name', 'Lot Number', 'Section', 'Date of Birth', 'Date of Death', 'Date of Burial', 'Age'];
            break;

        default:
            die("Invalid report type");
    }

    // Set headers for download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    // Create a file pointer connected to the output stream
    $output = fopen('php://output', 'w');

    // Add CSV headers
    fputcsv($output, $headers);

    // Add data rows
    foreach ($data as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;

} catch (PDOException $e) {
    die("Error generating report: " . $e->getMessage());
}
?>