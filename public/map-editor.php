<?php
require_once __DIR__ . '/../config/auth.php';
requireLogin();

$user = getUserInfo();
$userInitials = getInitials($user['full_name']);

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$lots = [];
$mapImage = 'cemetery-map.jpg';

if ($conn) {
    try {
        $stmt = $conn->query("
            SELECT cl.*, dr.full_name as deceased_name 
            FROM cemetery_lots cl 
            LEFT JOIN deceased_records dr ON cl.id = dr.lot_id 
            ORDER BY cl.lot_number
        ");
        $lots = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>PeacePlot Admin - Map Editor</title>
  <link rel="stylesheet" href="../assets/css/styles.css" />
  <style>
    .editor-container {
      background: white;
      border-radius: 12px;
      padding: 24px;
      margin-top: 20px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .editor-toolbar {
      display: flex;
      gap: 12px;
      margin-bottom: 20px;
      padding: 16px;
      background: var(--page);
      border-radius: 8px;
      flex-wrap: wrap;
      align-items: center;
    }
    
    .tool-btn {
      padding: 10px 16px;
      border: 2px solid var(--border);
      background: white;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .tool-btn:hover {
      border-color: var(--primary);
      background: var(--primary);
      color: white;
    }
    
    .tool-btn.active {
      background: var(--primary);
      color: white;
      border-color: var(--primary);
    }
    
    .zoom-controls {
      display: flex;
      gap: 8px;
      align-items: center;
    }
    
    .zoom-btn {
      width: 36px;
      height: 36px;
      border: 2px solid var(--border);
      background: white;
      border-radius: 6px;
      cursor: pointer;
      font-size: 18px;
      font-weight: 700;
      transition: all 0.2s;
    }
    
    .zoom-btn:hover {
      border-color: var(--primary);
      color: var(--primary);
    }
    
    .zoom-level {
      font-weight: 600;
      min-width: 60px;
      text-align: center;
    }
    
    .map-canvas-wrapper {
      position: relative;
      width: 100%;
      height: 600px;
      overflow: hidden;
      border-radius: 8px;
      border: 2px solid var(--border);
      background: #f5f5f5;
      cursor: grab;
    }
    
    .map-canvas-wrapper.grabbing {
      cursor: grabbing;
    }
    
    .map-canvas-wrapper.crosshair {
      cursor: crosshair;
    }
    
    .map-canvas {
      position: absolute;
      transform-origin: 0 0;
    }
    
    .map-canvas img {
      display: block;
      user-select: none;
      pointer-events: none;
    }
    
    .lot-rectangle {
      position: absolute;
      border: 3px solid;
      box-sizing: border-box;
      pointer-events: all;
      cursor: pointer;
      transition: all 0.2s;
    }

    .lot-remove-btn {
      position: absolute;
      top: -10px;
      right: -10px;
      width: 22px;
      height: 22px;
      border-radius: 999px;
      border: 2px solid rgba(0,0,0,0.2);
      background: rgba(255,255,255,0.95);
      color: #111827;
      font-weight: 900;
      font-size: 14px;
      line-height: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      z-index: 200;
    }

    .lot-remove-btn:hover {
      background: #ef4444;
      color: white;
      border-color: rgba(239,68,68,0.6);
    }
    
    .lot-rectangle:hover {
      border-width: 4px;
      box-shadow: 0 0 12px rgba(0,0,0,0.3);
      z-index: 100;
    }
    
    .lot-rectangle.vacant {
      border-color: #22c55e;
      background: rgba(34, 197, 94, 0.2);
    }
    
    .lot-rectangle.occupied {
      border-color: #f97316;
      background: rgba(249, 115, 22, 0.2);
    }
    
    .lot-rectangle.reserved {
      border-color: #8b5cf6;
      background: rgba(139, 92, 246, 0.2);
    }
    
    .lot-rectangle.maintenance {
      border-color: #6b7280;
      background: rgba(107, 114, 128, 0.2);
    }
    
    .lot-label {
      position: absolute;
      top: 4px;
      left: 4px;
      background: rgba(0,0,0,0.8);
      color: white;
      padding: 2px 6px;
      border-radius: 4px;
      font-size: 11px;
      font-weight: 700;
      pointer-events: none;
    }
    
    .drawing-rect {
      position: absolute;
      border: 3px dashed var(--primary);
      background: rgba(47, 109, 246, 0.1);
      pointer-events: none;
    }
    
    .assign-modal {
      display: none;
      position: fixed;
      z-index: 2000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.5);
      align-items: center;
      justify-content: center;
    }
    
    .assign-modal-content {
      background: white;
      border-radius: 12px;
      width: 90%;
      max-width: 500px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }
    
    .assign-modal-header {
      padding: 20px 24px;
      border-bottom: 1px solid var(--border);
    }
    
    .assign-modal-header h3 {
      margin: 0;
      font-size: 18px;
    }
    
    .assign-modal-body {
      padding: 24px;
    }
    
    .form-group {
      margin-bottom: 16px;
    }
    
    .form-group label {
      display: block;
      margin-bottom: 6px;
      font-weight: 600;
      font-size: 14px;
    }
    
    .form-group select {
      width: 100%;
      padding: 10px;
      border: 2px solid var(--border);
      border-radius: 8px;
      font-size: 14px;
    }
    
    .assign-modal-footer {
      padding: 16px 24px;
      border-top: 1px solid var(--border);
      display: flex;
      gap: 12px;
      justify-content: flex-end;
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
        <a href="cemetery-map.php" class="active"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2V6z" /><path d="M9 4v14" /><path d="M15 6v14" /></svg></span><span>Map Editor</span></a>
        <a href="burial-records.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" /><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" /><path d="M8 6h8" /><path d="M8 10h8" /></svg></span><span>Burial Records</span></a>
        <a href="reports.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18" /><path d="M7 14v4" /><path d="M11 10v8" /><path d="M15 6v12" /><path d="M19 12v6" /></svg></span><span>Reports</span></a>
      </nav>

      <div class="sidebar-footer">
        <div class="user">
          <div class="avatar"><?php echo htmlspecialchars($userInitials); ?></div>
          <div>
            <div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
            <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
          </div>
        </div>

        <a class="logout" href="logout.php">
          <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><path d="M16 17l5-5-5-5" /><path d="M21 12H9" /></svg></span>
          <span>Logout</span>
        </a>
      </div>
    </aside>

    <main class="main">
      <div class="page-header">
        <h1 class="page-title">Cemetery Map Editor</h1>
        <p style="color:var(--muted); margin-top:8px;">Draw rectangles on the map to mark cemetery lots</p>
      </div>

      <div class="editor-container">
        <div class="editor-toolbar">
          <button class="tool-btn active" id="drawBtn" onclick="setTool('draw')">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="3" width="18" height="18" rx="2"/>
            </svg>
            Draw Rectangle
          </button>
          
          <button class="tool-btn" id="panBtn" onclick="setTool('pan')">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M5 9l-3 3 3 3M9 5l3-3 3 3M15 19l-3 3-3-3M19 9l3 3-3 3M2 12h20M12 2v20"/>
            </svg>
            Pan
          </button>
          
          <div class="zoom-controls">
            <button class="zoom-btn" onclick="zoomOut()">−</button>
            <span class="zoom-level" id="zoomLevel">100%</span>
            <button class="zoom-btn" onclick="zoomIn()">+</button>
          </div>
          
          <button class="btn-primary" onclick="saveAllLots()" style="margin-left:auto;">
            Save All Changes
          </button>
        </div>

        <div class="map-canvas-wrapper" id="mapWrapper">
          <div class="map-canvas" id="mapCanvas">
            <img src="../assets/images/<?php echo htmlspecialchars($mapImage); ?>" 
                 alt="Cemetery Map" 
                 id="mapImage">
            <div id="rectanglesContainer"></div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <div id="assignModal" class="assign-modal">
    <div class="assign-modal-content">
      <div class="assign-modal-header">
        <h3>Assign Lot to Rectangle</h3>
      </div>
      <div class="assign-modal-body">
        <div class="form-group">
          <label>Select Cemetery Lot:</label>
          <select id="lotSelect">
            <option value="">-- Select a lot --</option>
            <?php foreach ($lots as $lot): ?>
              <option value="<?php echo $lot['id']; ?>" 
                      data-lot='<?php echo htmlspecialchars(json_encode($lot)); ?>'>
                <?php echo htmlspecialchars($lot['lot_number']); ?> - 
                <?php echo htmlspecialchars($lot['section']); ?> 
                (<?php echo htmlspecialchars($lot['status']); ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="assign-modal-footer">
        <button class="btn-secondary" onclick="closeAssignModal()">Cancel</button>
        <button class="btn-primary" onclick="assignLot()">Assign</button>
      </div>
    </div>
  </div>

  <script>
    const lotsData = <?php echo json_encode($lots); ?>;
    let currentTool = 'draw';
    let zoom = 1;
    let panX = 0;
    let panY = 0;
    let isPanning = false;
    let startPanX, startPanY;
    let isDrawing = false;
    let startX, startY;
    let currentRect = null;
    let rectangles = [];
    let pendingRect = null;

    const mapWrapper = document.getElementById('mapWrapper');
    const mapCanvas = document.getElementById('mapCanvas');
    const mapImage = document.getElementById('mapImage');
    const rectanglesContainer = document.getElementById('rectanglesContainer');

    // Load existing rectangles from lots
    lotsData.forEach(lot => {
      if (lot.map_x !== null && lot.map_y !== null && lot.map_width !== null && lot.map_height !== null) {
        addRectangle(lot.map_x, lot.map_y, lot.map_width, lot.map_height, lot);
      }
    });

    function setTool(tool) {
      currentTool = tool;
      document.getElementById('drawBtn').classList.toggle('active', tool === 'draw');
      document.getElementById('panBtn').classList.toggle('active', tool === 'pan');
      
      if (tool === 'draw') {
        mapWrapper.classList.add('crosshair');
        mapWrapper.classList.remove('grabbing');
      } else {
        mapWrapper.classList.remove('crosshair');
      }
    }

    function zoomIn() {
      zoom = Math.min(zoom + 0.25, 3);
      updateTransform();
    }

    function zoomOut() {
      zoom = Math.max(zoom - 0.25, 0.5);
      updateTransform();
    }

    function setZoomAt(newZoom, clientX, clientY) {
      const rect = mapWrapper.getBoundingClientRect();
      const mouseX = clientX - rect.left;
      const mouseY = clientY - rect.top;

      const worldX = (mouseX - panX) / zoom;
      const worldY = (mouseY - panY) / zoom;

      zoom = newZoom;
      panX = mouseX - worldX * zoom;
      panY = mouseY - worldY * zoom;
      updateTransform();
    }

    function updateTransform() {
      mapCanvas.style.transform = `translate(${panX}px, ${panY}px) scale(${zoom})`;
      document.getElementById('zoomLevel').textContent = Math.round(zoom * 100) + '%';
    }

    mapWrapper.addEventListener('wheel', (e) => {
      e.preventDefault();

      const step = 0.15;
      const direction = e.deltaY > 0 ? -1 : 1;
      const newZoom = Math.min(3, Math.max(0.5, zoom + direction * step));

      if (newZoom !== zoom) {
        setZoomAt(newZoom, e.clientX, e.clientY);
      }
    }, { passive: false });

    mapWrapper.addEventListener('mousedown', (e) => {
      const rect = mapWrapper.getBoundingClientRect();
      const x = (e.clientX - rect.left - panX) / zoom;
      const y = (e.clientY - rect.top - panY) / zoom;

      if (currentTool === 'pan') {
        isPanning = true;
        startPanX = e.clientX - panX;
        startPanY = e.clientY - panY;
        mapWrapper.classList.add('grabbing');
      } else if (currentTool === 'draw') {
        isDrawing = true;
        startX = x;
        startY = y;
        
        currentRect = document.createElement('div');
        currentRect.className = 'drawing-rect';
        currentRect.style.left = x + 'px';
        currentRect.style.top = y + 'px';
        rectanglesContainer.appendChild(currentRect);
      }
    });

    mapWrapper.addEventListener('mousemove', (e) => {
      if (isPanning) {
        panX = e.clientX - startPanX;
        panY = e.clientY - startPanY;
        updateTransform();
      } else if (isDrawing && currentRect) {
        const rect = mapWrapper.getBoundingClientRect();
        const x = (e.clientX - rect.left - panX) / zoom;
        const y = (e.clientY - rect.top - panY) / zoom;
        
        const width = Math.abs(x - startX);
        const height = Math.abs(y - startY);
        const left = Math.min(x, startX);
        const top = Math.min(y, startY);
        
        currentRect.style.left = left + 'px';
        currentRect.style.top = top + 'px';
        currentRect.style.width = width + 'px';
        currentRect.style.height = height + 'px';
      }
    });

    mapWrapper.addEventListener('mouseup', (e) => {
      if (isPanning) {
        isPanning = false;
        mapWrapper.classList.remove('grabbing');
      } else if (isDrawing && currentRect) {
        isDrawing = false;
        
        const imageWidth = mapImage.offsetWidth;
        const imageHeight = mapImage.offsetHeight;

        const leftPx = parseFloat(currentRect.style.left) || 0;
        const topPx = parseFloat(currentRect.style.top) || 0;
        const widthPx = parseFloat(currentRect.style.width) || 0;
        const heightPx = parseFloat(currentRect.style.height) || 0;

        const x = (leftPx / imageWidth) * 100;
        const y = (topPx / imageHeight) * 100;
        const width = (widthPx / imageWidth) * 100;
        const height = (heightPx / imageHeight) * 100;
        
        currentRect.remove();
        currentRect = null;
        
        if (width > 0.5 && height > 0.5) {
          pendingRect = { x, y, width, height };
          showAssignModal();
        }
      }
    });

    function addRectangle(x, y, width, height, lotData) {
      const rect = document.createElement('div');
      rect.className = 'lot-rectangle ' + lotData.status.toLowerCase();
      rect.style.left = x + '%';
      rect.style.top = y + '%';
      rect.style.width = width + '%';
      rect.style.height = height + '%';
      
      const label = document.createElement('div');
      label.className = 'lot-label';
      label.textContent = lotData.lot_number;
      rect.appendChild(label);

      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'lot-remove-btn';
      removeBtn.textContent = '×';
      removeBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        removeMarkForLot(lotData.id);
      });
      rect.appendChild(removeBtn);
      
      rect.onclick = (e) => {
        if (e && e.shiftKey) {
          removeMarkForLot(lotData.id);
          return;
        }
        showLotDetails(lotData);
      };
      rect.addEventListener('contextmenu', (e) => {
        e.preventDefault();
        removeMarkForLot(lotData.id);
      });
      
      rectanglesContainer.appendChild(rect);
      rectangles.push({ rect, lotData, x, y, width, height });
    }

    function showAssignModal() {
      document.getElementById('assignModal').style.display = 'flex';
    }

    function closeAssignModal() {
      document.getElementById('assignModal').style.display = 'none';
      pendingRect = null;
    }

    function assignLot() {
      const select = document.getElementById('lotSelect');
      const lotId = select.value;
      
      if (!lotId || !pendingRect) {
        alert('Please select a lot');
        return;
      }
      
      const option = select.options[select.selectedIndex];
      const lotData = JSON.parse(option.getAttribute('data-lot'));
      
      lotData.map_x = pendingRect.x;
      lotData.map_y = pendingRect.y;
      lotData.map_width = pendingRect.width;
      lotData.map_height = pendingRect.height;
      
      addRectangle(pendingRect.x, pendingRect.y, pendingRect.width, pendingRect.height, lotData);
      
      closeAssignModal();
      saveAllLots(true);
    }

    function showLotDetails(lot) {
      alert(`Lot: ${lot.lot_number}\nSection: ${lot.section}\nStatus: ${lot.status}\nDeceased: ${lot.deceased_name || 'None'}`);
    }

    async function saveAllLots(silent = false) {
      const updates = rectangles.map(r => ({
        id: r.lotData.id,
        map_x: r.x,
        map_y: r.y,
        map_width: r.width,
        map_height: r.height
      }));
      
      try {
        const response = await fetch('../api/save_map_coordinates.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ lots: updates })
        });
        
        const result = await response.json();
        if (result.success) {
          if (!silent) alert('All lot positions saved successfully!');
        } else {
          alert('Error saving: ' + result.message);
        }
      } catch (error) {
        alert('Error: ' + error.message);
      }
    }

    async function removeMarkForLot(lotId) {
      const targetIndex = rectangles.findIndex(r => String(r.lotData.id) === String(lotId));
      if (targetIndex === -1) return;

      const target = rectangles[targetIndex];
      const ok = confirm(`Remove mark for Lot ${target.lotData.lot_number}?`);
      if (!ok) return;

      target.rect.remove();
      rectangles.splice(targetIndex, 1);

      try {
        const response = await fetch('../api/save_map_coordinates.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            lots: [{ id: target.lotData.id, map_x: null, map_y: null, map_width: null, map_height: null }]
          })
        });

        const result = await response.json();
        if (!result.success) {
          alert('Error removing mark: ' + result.message);
        }
      } catch (error) {
        alert('Error removing mark: ' + error.message);
      }
    }
  </script>
  <script src="../assets/js/app.js"></script>
</body>
</html>
