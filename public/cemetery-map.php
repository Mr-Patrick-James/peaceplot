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
      padding: 16px;
      margin-top: 16px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      position: relative;
    }
    
    .map-image-wrapper {
      position: relative;
      width: 100%;
      height: 450px;
      overflow: hidden;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      cursor: grab;
      background: #f5f5f5;
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
    
    /* Google Maps Style Card */
    .google-maps-card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.15);
      overflow: hidden;
      margin: 0;
    }
    
    .card-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      padding: 20px 20px 16px;
      background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
      border-bottom: 1px solid #e8eaed;
    }
    
    .lot-info {
      flex: 1;
    }
    
    .lot-title {
      margin: 0 0 8px 0;
      font-size: 20px;
      font-weight: 600;
      color: #202124;
      line-height: 1.2;
    }
    
    .lot-location {
      display: flex;
      align-items: center;
      gap: 6px;
      color: #5f6368;
      font-size: 14px;
    }
    
    .lot-location svg {
      opacity: 0.7;
    }
    
    .status-badge {
      padding: 6px 12px;
      border-radius: 16px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .status-badge.vacant {
      background: #e8f5e8;
      color: #2e7d32;
    }
    
    .status-badge.occupied {
      background: #fff3e0;
      color: #f57c00;
    }
    
    .status-badge.reserved {
      background: #f3e5f5;
      color: #7b1fa2;
    }
    
    .status-badge.maintenance {
      background: #fafafa;
      color: #757575;
    }
    
    .card-content {
      padding: 20px;
    }
    
    .info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
      gap: 16px;
      margin-bottom: 24px;
    }
    
    .info-item {
      display: flex;
      align-items: flex-start;
      gap: 12px;
    }
    
    .info-item svg {
      color: #5f6368;
      flex-shrink: 0;
      margin-top: 2px;
    }
    
    .info-label {
      font-size: 12px;
      color: #5f6368;
      font-weight: 500;
      margin-bottom: 2px;
    }
    
    .info-value {
      font-size: 14px;
      color: #202124;
      font-weight: 500;
    }
    
    .deceased-section {
      background: #f8f9fa;
      border-radius: 8px;
      padding: 16px;
      margin-bottom: 20px;
    }
    
    .section-title {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 14px;
      font-weight: 600;
      color: #202124;
      margin-bottom: 12px;
    }
    
    .section-title svg {
      color: #5f6368;
    }
    
    .deceased-name {
      font-size: 16px;
      color: #202124;
      font-weight: 500;
    }
    
    .images-section {
      margin-bottom: 24px;
    }
    
    .images-section:not(:first-child) {
      border-top: 1px solid #e8eaed;
      padding-top: 20px;
    }
    
    .images-section .section-title {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 16px;
    }
    
    .view-images-btn {
      display: flex;
      align-items: center;
      gap: 6px;
      background: #1a73e8;
      color: white;
      border: none;
      padding: 8px 16px;
      border-radius: 20px;
      font-size: 13px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    
    .view-images-btn:hover {
      background: #1557b0;
      transform: translateY(-1px);
      box-shadow: 0 2px 8px rgba(26, 115, 232, 0.3);
    }
    
    .images-container {
      background: #f8f9fa;
      border-radius: 8px;
      padding: 16px;
      margin-top: 12px;
    }
    
    .images-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
      font-size: 14px;
      font-weight: 600;
      color: #202124;
    }
    
    .close-images-btn {
      background: none;
      border: none;
      font-size: 20px;
      color: #5f6368;
      cursor: pointer;
      width: 24px;
      height: 24px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      transition: background 0.2s ease;
    }
    
    .close-images-btn:hover {
      background: #e8eaed;
    }
    
    .images-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
      gap: 12px;
    }
    
    .images-grid img {
      width: 100%;
      height: 100px;
      object-fit: cover;
      border-radius: 8px;
      cursor: pointer;
      transition: transform 0.2s ease;
    }
    
    .images-grid img:hover {
      transform: scale(1.05);
    }
    
    .map-legend {
      display: flex;
      gap: 16px;
      margin-bottom: 16px;
      padding: 12px 16px;
      background: rgba(255,255,255,0.95);
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      position: relative;
      z-index: 10;
      flex-wrap: wrap;
    }
    
    .legend-item {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 13px;
      font-weight: 500;
    }
    
    .legend-box {
      width: 20px;
      height: 20px;
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
        <div class="google-maps-card">
          <div class="card-header">
            <div class="lot-info">
              <h2 class="lot-title">Lot ${lot.lot_number}</h2>
              <div class="lot-location">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                  <circle cx="12" cy="10" r="3"/>
                </svg>
                <span>${lot.section}${lot.block ? ', ' + lot.block : ''}</span>
              </div>
            </div>
            <div class="status-badge ${lot.status.toLowerCase()}">
              ${lot.status}
            </div>
          </div>
          
          <div class="card-content">
            <div class="images-section">
              <div class="section-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                  <circle cx="8.5" cy="8.5" r="1.5"/>
                  <polyline points="21 15 16 10 5 21"/>
                </svg>
                <span>Grave Images</span>
              </div>
              <div id="graveImagesContainer" class="images-container">
                <div class="images-header">
                  <span>Grave Photos</span>
                </div>
                <div id="graveImagesGrid" class="images-grid"></div>
              </div>
            </div>
            
            <div class="info-grid">
              ${lot.position ? `
                <div class="info-item">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/>
                  </svg>
                  <div>
                    <div class="info-label">Position</div>
                    <div class="info-value">${lot.position}</div>
                  </div>
                </div>
              ` : ''}
              
              ${lot.size_sqm ? `
                <div class="info-item">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                    <line x1="9" y1="9" x2="15" y2="9"/>
                    <line x1="9" y1="15" x2="15" y2="15"/>
                  </svg>
                  <div>
                    <div class="info-label">Size</div>
                    <div class="info-value">${lot.size_sqm} sqm</div>
                  </div>
                </div>
              ` : ''}
              
              ${lot.price ? `
                <div class="info-item">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="1" x2="12" y2="23"/>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                  </svg>
                  <div>
                    <div class="info-label">Price</div>
                    <div class="info-value">₱${parseFloat(lot.price).toLocaleString()}</div>
                  </div>
                </div>
              ` : ''}
            </div>
            
            ${lot.deceased_name ? `
              <div class="deceased-section">
                <div class="section-title">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                  </svg>
                  <span>Deceased Information</span>
                </div>
                <div class="deceased-name">${lot.deceased_name}</div>
              </div>
            ` : ''}
          </div>
        </div>
      `;
      
      modalBody.innerHTML = html;
      modal.style.display = 'flex';
      
      // Auto-load grave images when modal opens
      loadGraveImages(lot.id);
    }
    
    async function loadGraveImages(lotId) {
      const grid = document.getElementById('graveImagesGrid');
      
      grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: var(--muted);">Loading images...</div>';
      
      try {
        // First get the burial record for this lot
        const burialResponse = await fetch(`../api/burial_records.php`);
        const burialData = await burialResponse.json();
        
        if (burialData.success && burialData.data) {
          const burialRecord = burialData.data.find(record => record.lot_id == lotId);
          
          if (burialRecord) {
            // Get images for this burial record
            const imagesResponse = await fetch(`../api/burial_images.php?burial_record_id=${burialRecord.id}`);
            const imagesData = await imagesResponse.json();
            
            if (imagesData.success && imagesData.data && imagesData.data.length > 0) {
              grid.innerHTML = imagesData.data.map(img => `
                <div style="border: 1px solid var(--border); border-radius: 8px; overflow: hidden; cursor: pointer;" onclick="showImageGallery('${burialRecord.id}', '${img.id}')">
                  <img src="../${img.image_path}" alt="${img.image_caption || 'Grave image'}" style="width: 100%; height: 120px; object-fit: cover;">
                  <div style="padding: 8px; font-size: 12px; color: var(--muted); text-align: center;">${img.image_caption || 'No caption'}</div>
                </div>
              `).join('');
            } else {
              grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: var(--muted); padding: 20px;">No images available</div>';
            }
          } else {
            grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: var(--muted); padding: 20px;">No burial record found</div>';
          }
        } else {
          grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: var(--muted); padding: 20px;">Failed to load burial records</div>';
        }
      } catch (error) {
        console.error('Error loading grave images:', error);
        grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: #ef4444; padding: 20px;">Error loading images</div>';
      }
    }
    
    function showImageGallery(burialRecordId, currentImageId = null) {
      // Fetch all images for the burial record
      fetch(`../api/burial_images.php?burial_record_id=${burialRecordId}`)
        .then(response => response.json())
        .then(result => {
          if (result.success && result.data && result.data.length > 0) {
            const modal = createImageGalleryModal(result.data, currentImageId);
            document.body.appendChild(modal);
            modal.style.display = 'flex';
          } else {
            alert('No images available for this burial record');
          }
        })
        .catch(error => {
          console.error('Error fetching images:', error);
          alert('Error loading images');
        });
    }
    
    function createImageGalleryModal(images, currentImageId = null) {
      const modal = document.createElement('div');
      modal.className = 'modal-map';
      modal.style.cssText = 'background:rgba(0,0,0,0.95); z-index: 2000;';
      
      const currentIndex = currentImageId ? images.findIndex(img => img.id == currentImageId) : 0;
      const currentImage = images[currentIndex] || images[0];
      
      modal.innerHTML = `
        <div class="modal-map-content" style="max-width:95vw; max-height:95vh; background:transparent; box-shadow:none; border:none;">
          <div class="modal-map-header" style="border:none; padding:15px 20px;">
            <h3 style="color:white; margin:0;">Grave Images</h3>
            <button class="modal-map-close" style="color:white; font-size:28px;" onclick="closeImageGallery(this.closest('.modal-map'))">&times;</button>
          </div>
          <div style="padding:0 20px 20px; text-align:center;">
            <div style="position:relative; display:inline-block; max-width:100%;">
              <img src="../${currentImage.image_path}" alt="${currentImage.image_caption || 'Grave image'}" style="max-width:100%; max-height:75vh; object-fit:contain; border-radius:8px; box-shadow: 0 10px 40px rgba(0,0,0,0.5);">
              <div style="position:absolute; bottom:0; left:0; right:0; background:linear-gradient(transparent, rgba(0,0,0,0.9)); color:white; padding:25px; border-radius:0 0 8px 8px; text-align:left;">
                <h4 style="margin:0 0 8px; font-size:18px;">${currentImage.image_caption || 'Grave Image'}</h4>
                <p style="margin:0; opacity:0.8; font-size:14px;">${currentImage.image_type || 'grave_photo'}</p>
              </div>
            </div>
            
            ${images.length > 1 ? `
            <div style="display:flex; justify-content:center; gap:12px; margin-top:25px; flex-wrap:wrap; max-height:120px; overflow-y:auto;">
              ${images.map((img, index) => `
                <img src="../${img.image_path}" alt="${img.image_caption || 'Grave image'}" 
                     style="width:90px; height:70px; object-fit:cover; border:3px solid ${index === currentIndex ? 'white' : 'transparent'}; border-radius:6px; cursor:pointer; opacity:${index === currentIndex ? '1' : '0.7'}; transition:all 0.2s;"
                     onclick="updateGalleryImage(${index})"
                     onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity=${index === currentIndex ? '1' : '0.7'}">
              `).join('')}
            </div>
            
            <button onclick="navigateGallery(-1)" style="position:absolute; left:25px; top:50%; transform:translateY(-50%); background:rgba(255,255,255,0.2); color:white; border:none; border-radius:50%; width:60px; height:60px; cursor:pointer; font-size:24px; backdrop-filter:blur(10px); transition:all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">‹</button>
            <button onclick="navigateGallery(1)" style="position:absolute; right:25px; top:50%; transform:translateY(-50%); background:rgba(255,255,255,0.2); color:white; border:none; border-radius:50%; width:60px; height:60px; cursor:pointer; font-size:24px; backdrop-filter:blur(10px); transition:all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">›</button>
            ` : ''}
          </div>
        </div>
      `;
      
      // Store images and current index for navigation
      modal.galleryImages = images;
      modal.galleryIndex = currentIndex;
      
      modal.onclick = (e) => { 
        if (e.target === modal) closeImageGallery(modal); 
      };
      
      // Add navigation functions to global scope
      window.updateGalleryImage = function(index) {
        modal.galleryIndex = index;
        updateGalleryDisplay(modal);
      };
      
      window.navigateGallery = function(direction) {
        modal.galleryIndex = (modal.galleryIndex + direction + modal.galleryImages.length) % modal.galleryImages.length;
        updateGalleryDisplay(modal);
      };
      
      // Keyboard navigation
      const handleKeydown = (e) => {
        if (e.key === 'ArrowLeft') window.navigateGallery(-1);
        if (e.key === 'ArrowRight') window.navigateGallery(1);
        if (e.key === 'Escape') closeImageGallery(modal);
      };
      
      document.addEventListener('keydown', handleKeydown);
      modal.addEventListener('close', () => document.removeEventListener('keydown', handleKeydown));
      
      return modal;
    }
    
    function updateGalleryDisplay(modal) {
      const images = modal.galleryImages;
      const index = modal.galleryIndex;
      const currentImage = images[index];
      
      const mainImg = modal.querySelector('.modal-map-content img');
      const caption = modal.querySelector('.modal-map-content h4');
      const type = modal.querySelector('.modal-map-content p');
      
      mainImg.src = `../${currentImage.image_path}`;
      mainImg.alt = currentImage.image_caption || 'Grave image';
      caption.textContent = currentImage.image_caption || 'Grave Image';
      type.textContent = currentImage.image_type || 'grave_photo';
      
      // Update thumbnails
      const thumbnails = modal.querySelectorAll('.modal-map-content div[style*="flex-wrap"] img');
      thumbnails.forEach((thumb, i) => {
        thumb.style.borderColor = i === index ? 'white' : 'transparent';
        thumb.style.opacity = i === index ? '1' : '0.7';
      });
    }
    
    function closeImageGallery(modal) {
      modal.style.display = 'none';
      modal.remove();
      
      // Clean up global functions
      delete window.updateGalleryImage;
      delete window.navigateGallery;
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
