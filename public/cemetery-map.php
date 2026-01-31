<?php
require_once __DIR__ . '/../config/auth.php';
requireLogin();

$user = getUserInfo();
$userInitials = getInitials($user['full_name']);

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$lots = [];
$mapImage = 'cemetery-map.jpg'; // Default map image name

// Check if map image exists
$mapPath = __DIR__ . '/../assets/images/' . $mapImage;
if (!file_exists($mapPath)) {
    $mapImage = null;
}

if ($conn) {
    try {
        // Get all lots with their map coordinates
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
  <title>PeacePlot Admin - Cemetery Map</title>
  <link rel="stylesheet" href="../assets/css/styles.css" />
  <style>
    .map-container {
      background: white;
      border-radius: 12px;
      padding: 24px;
      margin-top: 20px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      position: relative;
    }
    
    .map-image-wrapper {
      position: relative;
      width: 100%;
      overflow: hidden;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      cursor: grab;
    }

    .map-image-wrapper.grabbing {
      cursor: grabbing;
    }

    .map-canvas {
      position: relative;
      transform-origin: 0 0;
    }
    
    .map-image {
      width: 100%;
      height: auto;
      display: block;
      -webkit-user-drag: none;
      user-select: none;
    }
    
    .lot-marker {
      position: absolute;
      border: 3px solid;
      cursor: pointer;
      transition: all 0.3s;
      box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    }
    
    .lot-marker:hover {
      border-width: 4px;
      z-index: 100;
      box-shadow: 0 4px 16px rgba(0,0,0,0.5);
    }
    
    .lot-marker.vacant {
      border-color: #22c55e;
      background: rgba(34, 197, 94, 0.3);
    }
    
    .lot-marker.occupied {
      border-color: #f97316;
      background: rgba(249, 115, 22, 0.3);
    }
    
    .lot-marker.reserved {
      border-color: #8b5cf6;
      background: rgba(139, 92, 246, 0.3);
    }
    
    .lot-marker.maintenance {
      border-color: #6b7280;
      background: rgba(107, 114, 128, 0.3);
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
    
    .no-map-message {
      text-align: center;
      padding: 60px 20px;
      color: var(--muted);
    }
    
    .no-map-message h3 {
      margin-bottom: 12px;
      color: var(--text);
    }
    
    .map-legend {
      display: flex;
      gap: 20px;
      margin-bottom: 20px;
      padding: 16px;
      background: rgba(255,255,255,0.95);
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      position: relative;
      z-index: 10;
    }
    
    .legend-item {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 14px;
      font-weight: 500;
    }
    
    .legend-box {
      width: 28px;
      height: 28px;
      border-radius: 3px;
      border: 2px solid rgba(0,0,0,0.2);
      box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    
    .legend-box.vacant { 
      background: linear-gradient(135deg, #a8d5a8 0%, #8bc98b 100%);
    }
    .legend-box.occupied { 
      background: linear-gradient(135deg, #e8e8e8 0%, #c5c5c5 100%);
    }
    .legend-box.reserved { 
      background: linear-gradient(135deg, #ffd9a8 0%, #ffb366 100%);
    }
    .legend-box.maintenance { 
      background: linear-gradient(135deg, #b8b8b8 0%, #8a8a8a 100%);
    }
    
    
    .modal-map {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.5);
      align-items: center;
      justify-content: center;
    }
    
    .modal-map-content {
      background: white;
      border-radius: 12px;
      width: 90%;
      max-width: 500px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }
    
    .modal-map-header {
      padding: 20px 24px;
      border-bottom: 1px solid var(--border);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .modal-map-header h3 {
      margin: 0;
      font-size: 18px;
      color: var(--text);
    }
    
    .modal-map-close {
      background: none;
      border: none;
      font-size: 24px;
      color: var(--muted);
      cursor: pointer;
      padding: 0;
      width: 32px;
      height: 32px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 6px;
      transition: all 0.2s;
    }
    
    .modal-map-close:hover {
      background: var(--page);
      color: var(--text);
    }
    
    .modal-map-body {
      padding: 24px;
    }
    
    .detail-row {
      display: flex;
      justify-content: space-between;
      padding: 12px 0;
      border-bottom: 1px solid var(--border);
    }
    
    .detail-row:last-child {
      border-bottom: none;
    }
    
    .detail-label {
      font-weight: 500;
      color: var(--muted);
    }
    
    .detail-value {
      font-weight: 600;
      color: var(--text);
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
        <a href="lot-availability.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20" /><path d="M2 12h20" /><path d="M4 4l16 16" /></svg></span><span>Lot Availability</span></a>
        <a href="cemetery-map.php" class="active"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2V6z" /><path d="M9 4v14" /><path d="M15 6v14" /></svg></span><span>Cemetery Map</span></a>
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
        <h1 class="page-title">Cemetery Map</h1>
      </div>

      <?php if (isset($error)): ?>
        <div class="card" style="padding:20px; color:#ef4444;">
          Error loading data: <?php echo htmlspecialchars($error); ?>
        </div>
      <?php else: ?>

      <div class="map-container">
        <div class="map-legend">
          <div class="legend-item">
            <div class="legend-box vacant"></div>
            <span>Vacant</span>
          </div>
          <div class="legend-item">
            <div class="legend-box occupied"></div>
            <span>Occupied</span>
          </div>
          <div class="legend-item">
            <div class="legend-box reserved"></div>
            <span>Reserved</span>
          </div>
          <div class="legend-item">
            <div class="legend-box maintenance"></div>
            <span>Maintenance</span>
          </div>
        </div>

        <?php if ($mapImage): ?>
          <div class="map-image-wrapper">
            <div class="map-canvas" id="mapCanvas">
              <img src="../assets/images/<?php echo htmlspecialchars($mapImage); ?>" 
                   alt="Cemetery Map" 
                   class="map-image"
                   id="cemeteryMapImage"
                   draggable="false">
            
            <?php foreach ($lots as $lot): ?>
              <?php if ($lot['map_x'] !== null && $lot['map_y'] !== null && $lot['map_width'] !== null && $lot['map_height'] !== null): ?>
                <div class="lot-marker <?php echo strtolower($lot['status']); ?>"
                     style="left: <?php echo $lot['map_x']; ?>%; 
                            top: <?php echo $lot['map_y']; ?>%;
                            width: <?php echo $lot['map_width']; ?>%;
                            height: <?php echo $lot['map_height']; ?>%;"
                     onclick="showLotDetails(<?php echo htmlspecialchars(json_encode($lot)); ?>)"
                     title="<?php echo htmlspecialchars($lot['lot_number']); ?>">
                  <div class="lot-label"><?php echo htmlspecialchars($lot['lot_number']); ?></div>
                </div>
              <?php endif; ?>
            <?php endforeach; ?>
            </div>
          </div>
          
          <?php 
          $lotsWithoutCoordinates = array_filter($lots, function($lot) {
            return $lot['map_x'] === null || $lot['map_y'] === null || $lot['map_width'] === null || $lot['map_height'] === null;
          });
          ?>
          
          <?php if (!empty($lotsWithoutCoordinates)): ?>
            <div style="margin-top: 20px; padding: 16px; background: #fff3cd; border-radius: 8px; border-left: 4px solid #ffc107;">
              <strong>Note:</strong> <?php echo count($lotsWithoutCoordinates); ?> lot(s) don't have map coordinates assigned yet.
              <a href="map-editor.php" style="color: var(--primary); text-decoration: underline; font-weight: 600;">
                Open Map Editor to mark lots on the map
              </a>
            </div>
          <?php endif; ?>
          
        <?php else: ?>
          <div class="no-map-message">
            <h3>No Map Image Found</h3>
            <p>Please upload a cemetery map image to <code>assets/images/cemetery-map.jpg</code></p>
          </div>
        <?php endif; ?>
      </div>

      <?php endif; ?>
    </main>
  </div>

  <div id="lotModal" class="modal-map">
    <div class="modal-map-content">
      <div class="modal-map-header">
        <h3 id="modalTitle">Lot Details</h3>
        <button class="modal-map-close" onclick="closeLotModal()">&times;</button>
      </div>
      <div class="modal-map-body" id="modalBody">
        <!-- Details will be populated by JavaScript -->
      </div>
    </div>
  </div>

  <script>
    let zoom = 1;
    let panX = 0;
    let panY = 0;
    let isPanning = false;
    let startPanX, startPanY;
    let panStartClientX = 0;
    let panStartClientY = 0;
    let didPan = false;

    const mapWrapper = document.querySelector('.map-image-wrapper');
    const mapCanvas = document.getElementById('mapCanvas');

    function clampPan() {
      if (!mapWrapper || !mapCanvas) return;

      const wrapperW = mapWrapper.clientWidth;
      const wrapperH = mapWrapper.clientHeight;
      const contentW = mapCanvas.offsetWidth * zoom;
      const contentH = mapCanvas.offsetHeight * zoom;

      if (contentW <= wrapperW) {
        panX = (wrapperW - contentW) / 2;
      } else {
        const minX = wrapperW - contentW;
        const maxX = 0;
        panX = Math.min(maxX, Math.max(minX, panX));
      }

      if (contentH <= wrapperH) {
        panY = (wrapperH - contentH) / 2;
      } else {
        const minY = wrapperH - contentH;
        const maxY = 0;
        panY = Math.min(maxY, Math.max(minY, panY));
      }
    }

    function updateTransform() {
      if (!mapCanvas) return;
      clampPan();
      mapCanvas.style.transform = `translate(${panX}px, ${panY}px) scale(${zoom})`;
    }

    function setZoomAt(newZoom, clientX, clientY) {
      if (!mapWrapper) return;
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

    if (mapWrapper && mapCanvas) {
      mapWrapper.addEventListener('dragstart', (e) => {
        e.preventDefault();
      });

      mapWrapper.addEventListener('wheel', (e) => {
        e.preventDefault();

        const step = 0.15;
        const direction = e.deltaY > 0 ? -1 : 1;
        const newZoom = Math.min(3, Math.max(0.5, zoom + direction * step));

        if (newZoom !== zoom) {
          setZoomAt(newZoom, e.clientX, e.clientY);
        }
      }, { passive: false });

      mapWrapper.addEventListener('click', (e) => {
        if (!didPan) return;
        e.preventDefault();
        e.stopPropagation();
        didPan = false;
      }, true);

      mapWrapper.addEventListener('mousedown', (e) => {
        if (e.button !== 0) return;
        isPanning = true;
        startPanX = e.clientX - panX;
        startPanY = e.clientY - panY;
        panStartClientX = e.clientX;
        panStartClientY = e.clientY;
        didPan = false;
        mapWrapper.classList.add('grabbing');
        document.body.style.userSelect = 'none';
      });

      window.addEventListener('mousemove', (e) => {
        if (!isPanning) return;

        const dx = e.clientX - panStartClientX;
        const dy = e.clientY - panStartClientY;
        if (!didPan && (Math.abs(dx) > 3 || Math.abs(dy) > 3)) {
          didPan = true;
        }

        panX = e.clientX - startPanX;
        panY = e.clientY - startPanY;
        updateTransform();
      });

      window.addEventListener('mouseup', () => {
        if (!isPanning) return;
        isPanning = false;
        mapWrapper.classList.remove('grabbing');
        document.body.style.userSelect = '';
      });
    }

    function showLotDetails(lot) {
      const modal = document.getElementById('lotModal');
      const modalBody = document.getElementById('modalBody');
      const modalTitle = document.getElementById('modalTitle');
      
      modalTitle.textContent = 'Lot ' + lot.lot_number;
      
      let html = `
        <div class="detail-row">
          <span class="detail-label">Lot Number:</span>
          <span class="detail-value">${lot.lot_number}</span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Section:</span>
          <span class="detail-value">${lot.section}</span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Block:</span>
          <span class="detail-value">${lot.block || '—'}</span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Position:</span>
          <span class="detail-value">${lot.position || '—'}</span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Status:</span>
          <span class="detail-value">
            <span class="badge ${lot.status.toLowerCase()}">${lot.status}</span>
          </span>
        </div>
      `;
      
      if (lot.deceased_name) {
        html += `
          <div class="detail-row">
            <span class="detail-label">Deceased:</span>
            <span class="detail-value">${lot.deceased_name}</span>
          </div>
        `;
      }
      
      if (lot.size_sqm) {
        html += `
          <div class="detail-row">
            <span class="detail-label">Size:</span>
            <span class="detail-value">${lot.size_sqm} sqm</span>
          </div>
        `;
      }
      
      if (lot.price) {
        html += `
          <div class="detail-row">
            <span class="detail-label">Price:</span>
            <span class="detail-value">₱${parseFloat(lot.price).toLocaleString()}</span>
          </div>
        `;
      }
      
      modalBody.innerHTML = html;
      modal.style.display = 'flex';
    }
    
    function closeLotModal() {
      document.getElementById('lotModal').style.display = 'none';
    }
    
    // Close modal when clicking outside
    document.getElementById('lotModal').onclick = function(e) {
      if (e.target === this) {
        closeLotModal();
      }
    };
  </script>
  <script src="../assets/js/app.js"></script>
</body>
</html>
