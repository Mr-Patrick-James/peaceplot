<?php
require_once __DIR__ . '/config/database.php';

$database = new Database();
$conn = $database->getConnection();

$lots = [];
if ($conn) {
    try {
        $stmt = $conn->query("SELECT id, lot_number, section, block, status FROM cemetery_lots ORDER BY lot_number");
        $lots = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $lot_id = intval($_POST['lot_id']);
        $layer = intval($_POST['layer']);
        $full_name = trim($_POST['full_name']);
        $date_of_birth = $_POST['date_of_birth'] ?: null;
        $date_of_death = $_POST['date_of_death'] ?: null;
        $date_of_burial = $_POST['date_of_burial'] ?: null;
        $age = intval($_POST['age']) ?: null;
        $cause_of_death = trim($_POST['cause_of_death']) ?: null;
        $next_of_kin = trim($_POST['next_of_kin']) ?: null;
        $next_of_kin_contact = trim($_POST['next_of_kin_contact']) ?: null;
        $remarks = trim($_POST['remarks']) ?: null;
        
        // Validate required fields
        if (!$lot_id || !$layer || !$full_name) {
            throw new Exception("Lot, Layer, and Full Name are required");
        }
        
        // Check if layer is available
        $stmt = $conn->prepare("SELECT is_occupied FROM lot_layers WHERE lot_id = :lot_id AND layer_number = :layer");
        $stmt->bindParam(':lot_id', $lot_id);
        $stmt->bindParam(':layer', $layer);
        $stmt->execute();
        $layerInfo = $stmt->fetch();
        
        if (!$layerInfo) {
            throw new Exception("Layer $layer does not exist for this lot");
        }
        
        if ($layerInfo['is_occupied']) {
            throw new Exception("Layer $layer is already occupied");
        }
        
        // Insert burial record
        $stmt = $conn->prepare("
            INSERT INTO deceased_records 
            (lot_id, layer, full_name, date_of_birth, date_of_death, date_of_burial, age, 
             cause_of_death, next_of_kin, next_of_kin_contact, remarks) 
            VALUES 
            (:lot_id, :layer, :full_name, :date_of_birth, :date_of_death, :date_of_burial, :age,
             :cause_of_death, :next_of_kin, :next_of_kin_contact, :remarks)
        ");
        
        $stmt->bindParam(':lot_id', $lot_id);
        $stmt->bindParam(':layer', $layer);
        $stmt->bindParam(':full_name', $full_name);
        $stmt->bindParam(':date_of_birth', $date_of_birth);
        $stmt->bindParam(':date_of_death', $date_of_death);
        $stmt->bindParam(':date_of_burial', $date_of_burial);
        $stmt->bindParam(':age', $age);
        $stmt->bindParam(':cause_of_death', $cause_of_death);
        $stmt->bindParam(':next_of_kin', $next_of_kin);
        $stmt->bindParam(':next_of_kin_contact', $next_of_kin_contact);
        $stmt->bindParam(':remarks', $remarks);
        
        $conn->beginTransaction();
        
        if ($stmt->execute()) {
            $burialRecordId = $conn->lastInsertId();
            
            // Update the lot layer to mark it as occupied
            $updateStmt = $conn->prepare("
                UPDATE lot_layers 
                SET is_occupied = 1, burial_record_id = :burial_record_id 
                WHERE lot_id = :lot_id AND layer_number = :layer
            ");
            $updateStmt->bindParam(':burial_record_id', $burialRecordId);
            $updateStmt->bindParam(':lot_id', $lot_id);
            $updateStmt->bindParam(':layer', $layer);
            $updateStmt->execute();
            
            // Update lot status
            $checkStmt = $conn->prepare("SELECT COUNT(*) as occupied_count FROM lot_layers WHERE lot_id = :lot_id AND is_occupied = 1");
            $checkStmt->bindParam(':lot_id', $lot_id);
            $checkStmt->execute();
            $result = $checkStmt->fetch();
            
            $status = $result['occupied_count'] > 0 ? 'Occupied' : 'Vacant';
            $conn->exec("UPDATE cemetery_lots SET status = '$status' WHERE id = $lot_id");
            
            $conn->commit();
            
            $success = "✅ Burial record added successfully for $full_name in Layer $layer";
        } else {
            throw new Exception("Failed to create burial record");
        }
        
    } catch (Exception $e) {
        if (isset($conn)) $conn->rollback();
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Burial Record - PeacePlot</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .layer-selection {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .layer-option {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .layer-option:hover {
            border-color: #007bff;
            transform: translateY(-1px);
        }
        .layer-option.vacant {
            border-color: #28a745;
            background: rgba(40, 167, 69, 0.1);
        }
        .layer-option.occupied {
            border-color: #dc3545;
            background: rgba(220, 53, 69, 0.1);
            cursor: not-allowed;
            opacity: 0.6;
        }
        .layer-option.selected {
            border-color: #007bff;
            background: rgba(0, 123, 255, 0.1);
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.2);
        }
        .layer-number {
            font-weight: 600;
            margin-bottom: 4px;
        }
        .layer-status {
            font-size: 12px;
            color: #666;
        }
        .status-message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        input, select, textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        textarea {
            height: 80px;
            resize: vertical;
        }
        .btn-primary {
            background: #007bff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn-primary:hover {
            background: #0056b3;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-secondary:hover {
            background: #545b62;
        }
    </style>
</head>
<body>
    <div class="app">
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-title">PeacePlot Admin</div>
                <div class="brand-sub">Cemetery Management</div>
            </div>

            <nav class="nav">
                <a href="dashboard.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 13h8V3H3v10z" /><path d="M13 21h8V11h-8v10z" /><path d="M13 3h8v6h-8V3z" /><path d="M3 21h8v-6H3v6z" /></svg></span><span>Dashboard</span></a>
                <a href="index.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16" /><path d="M4 12h16" /><path d="M4 17h16" /><path d="M8 7v10" /><path d="M16 7v10" /></svg></span><span>Cemetery Lot Management</span></a>
                <a href="burial-records.php" class="active"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" /><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" /><path d="M8 6h8" /><path d="M8 10h8" /></svg></span><span>Burial Records</span></a>
                <a href="cemetery-map.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2V6z" /><path d="M9 4v14" /><path d="M15 6v14" /></svg></span><span>Cemetery Map</span></a>
            </nav>
        </aside>

        <main class="main">
            <div class="page-header">
                <h1 class="page-title">Add New Burial Record</h1>
            </div>

            <div class="form-container">
                <?php if (isset($success)): ?>
                    <div class="status-message success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="status-message error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="full_name">Full Name *</label>
                            <input type="text" id="full_name" name="full_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="age">Age</label>
                            <input type="number" id="age" name="age" min="0">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="lot_id">Cemetery Lot *</label>
                        <select id="lot_id" name="lot_id" required onchange="loadLayers(this.value)">
                            <option value="">Select a lot</option>
                            <?php foreach ($lots as $lot): ?>
                                <option value="<?php echo $lot['id']; ?>">
                                    <?php echo htmlspecialchars($lot['lot_number']); ?> - 
                                    <?php echo htmlspecialchars($lot['section']); ?>
                                    <?php echo $lot['block'] ? ' - ' . htmlspecialchars($lot['block']) : ''; ?>
                                    (<?php echo htmlspecialchars($lot['status']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" id="layerGroup" style="display: none;">
                        <label>Burial Layer *</label>
                        <div id="layerOptions" class="layer-selection">
                            <!-- Layers will be loaded here -->
                        </div>
                        <input type="hidden" id="selectedLayer" name="layer" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth">
                        </div>
                        
                        <div class="form-group">
                            <label for="date_of_death">Date of Death</label>
                            <input type="date" id="date_of_death" name="date_of_death">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="date_of_burial">Date of Burial</label>
                        <input type="date" id="date_of_burial" name="date_of_burial">
                    </div>

                    <div class="form-group">
                        <label for="cause_of_death">Cause of Death</label>
                        <input type="text" id="cause_of_death" name="cause_of_death">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="next_of_kin">Next of Kin</label>
                            <input type="text" id="next_of_kin" name="next_of_kin">
                        </div>
                        
                        <div class="form-group">
                            <label for="next_of_kin_contact">Next of Kin Contact</label>
                            <input type="text" id="next_of_kin_contact" name="next_of_kin_contact">
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label for="remarks">Remarks</label>
                        <textarea id="remarks" name="remarks"></textarea>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="btn-primary">Add Burial Record</button>
                        <a href="burial-records.php" class="btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        async function loadLayers(lotId) {
            const layerGroup = document.getElementById('layerGroup');
            const layerOptions = document.getElementById('layerOptions');
            const selectedLayerInput = document.getElementById('selectedLayer');
            
            if (!lotId) {
                layerGroup.style.display = 'none';
                selectedLayerInput.value = '';
                return;
            }
            
            try {
                const response = fetch(`../api/lot_layers.php?lot_id=${lotId}`);
                const data = await response.json();
                
                if (data.success && data.data) {
                    layerGroup.style.display = 'block';
                    
                    layerOptions.innerHTML = data.data.map(layer => {
                        const isOccupied = layer.is_occupied;
                        return `
                            <div class="layer-option ${isOccupied ? 'occupied' : 'vacant'}" 
                                 data-layer="${layer.layer_number}"
                                 onclick="selectLayer(${layer.layer_number}, ${isOccupied})">
                                <div class="layer-number">Layer ${layer.layer_number}</div>
                                <div class="layer-status">
                                    ${isOccupied ? `Occupied by ${layer.deceased_name || 'Unknown'}` : 'Vacant'}
                                </div>
                            </div>
                        `;
                    }).join('');
                } else {
                    layerGroup.style.display = 'none';
                    layerOptions.innerHTML = '<p style="color: #dc3545;">Error loading layers</p>';
                }
            } catch (error) {
                console.error('Error loading layers:', error);
                layerGroup.style.display = 'none';
                layerOptions.innerHTML = '<p style="color: #dc3545;">Error loading layers</p>';
            }
        }
        
        function selectLayer(layerNumber, isOccupied) {
            if (isOccupied) {
                alert(`Layer ${layerNumber} is already occupied and cannot be selected.`);
                return;
            }
            
            // Remove previous selection
            document.querySelectorAll('.layer-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selection to clicked layer
            const selectedOption = document.querySelector(`.layer-option[data-layer="${layerNumber}"]`);
            if (selectedOption) {
                selectedOption.classList.add('selected');
                document.getElementById('selectedLayer').value = layerNumber;
            }
        }
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const selectedLayer = document.getElementById('selectedLayer').value;
            if (!selectedLayer) {
                e.preventDefault();
                alert('Please select a burial layer.');
                return false;
            }
        });
    </script>
</body>
</html>
