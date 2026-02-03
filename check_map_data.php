<?php
require_once 'config/database.php';

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    die("Database connection failed");
}

echo "Checking cemetery lots and map coordinates...\n\n";

// Check if map coordinate columns exist
$stmt = $conn->query("PRAGMA table_info(cemetery_lots)");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

$coordColumns = ['map_x', 'map_y', 'map_width', 'map_height'];
$hasCoords = true;

foreach ($coordColumns as $col) {
    $found = false;
    foreach ($columns as $column) {
        if ($column['name'] === $col) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        echo "❌ Missing column: $col\n";
        $hasCoords = false;
    } else {
        echo "✅ Found column: $col\n";
    }
}

if (!$hasCoords) {
    echo "\n⚠️  Map coordinate columns are missing. Please run database/add_map_coordinates.php\n";
    exit;
}

// Check lots and their coordinates
echo "\nChecking lots with map coordinates...\n";

$stmt = $conn->query("SELECT id, lot_number, section, map_x, map_y, map_width, map_height FROM cemetery_lots ORDER BY lot_number");
$lots = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($lots)) {
    echo "❌ No lots found in database.\n";
    echo "Please run create_sample_lots.php or add lots manually.\n";
    exit;
}

$totalLots = count($lots);
$lotsWithCoords = 0;
$lotsWithoutCoords = 0;

echo "\nFound $totalLots lots:\n";
echo str_repeat("=", 80) . "\n";
printf("%-10s %-10s %-10s %-10s %-10s %-10s %-10s\n", 
    "Lot#", "Section", "Map X", "Map Y", "Width", "Height", "Status");
echo str_repeat("-", 80) . "\n";

foreach ($lots as $lot) {
    $hasCoord = $lot['map_x'] !== null && $lot['map_y'] !== null && 
                $lot['map_width'] !== null && $lot['map_height'] !== null;
    
    if ($hasCoord) {
        $lotsWithCoords++;
        $status = "✅ Ready";
    } else {
        $lotsWithoutCoords++;
        $status = "❌ No coords";
    }
    
    printf("%-10s %-10s %-10s %-10s %-10s %-10s %-10s\n", 
        $lot['lot_number'], 
        $lot['section'], 
        $lot['map_x'] ?? 'NULL', 
        $lot['map_y'] ?? 'NULL', 
        $lot['map_width'] ?? 'NULL', 
        $lot['map_height'] ?? 'NULL', 
        $status
    );
}

echo str_repeat("=", 80) . "\n";
echo "\n📊 Summary:\n";
echo "• Total lots: $totalLots\n";
echo "• Lots with coordinates: $lotsWithCoords\n";
echo "• Lots without coordinates: $lotsWithoutCoords\n";

if ($lotsWithoutCoords > 0) {
    echo "\n⚠️  $lotsWithoutCoords lot(s) don't have map coordinates assigned.\n";
    echo "These lots won't appear on the cemetery map.\n";
    echo "Use the Map Editor to assign coordinates to these lots.\n";
} else {
    echo "\n✅ All lots have map coordinates assigned!\n";
}

// Check if map image exists
$mapPath = __DIR__ . '/assets/images/cemetery-map.jpg';
if (file_exists($mapPath)) {
    echo "\n✅ Map image found: assets/images/cemetery-map.jpg\n";
} else {
    echo "\n❌ Map image not found: assets/images/cemetery-map.jpg\n";
    echo "Please add a cemetery map image to see lots on the map.\n";
}
?>
