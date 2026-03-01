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
        // Get all lots with their map coordinates and layer information
        $stmt = $conn->query("
            SELECT cl.*, 
                   COUNT(DISTINCT ll.layer_number) as total_layers,
                   SUM(CASE WHEN ll.is_occupied = 1 THEN 1 ELSE 0 END) as occupied_layers,
                   COUNT(DISTINCT dr.id) as burial_count,
                   GROUP_CONCAT(DISTINCT dr.full_name || '|' || COALESCE(dr.layer, 1)) as burial_info,
                   CASE 
                       WHEN COUNT(DISTINCT dr.id) > 0 THEN 'Occupied'
                       WHEN COUNT(DISTINCT ll.id) > 0 AND SUM(CASE WHEN ll.is_occupied = 1 THEN 1 ELSE 0 END) > 0 THEN 'Occupied'
                       ELSE cl.status
                   END as actual_status
            FROM cemetery_lots cl 
            LEFT JOIN lot_layers ll ON cl.id = ll.lot_id
            LEFT JOIN deceased_records dr ON cl.id = dr.lot_id
            GROUP BY cl.id
            ORDER BY cl.lot_number
        ");
        $lots = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
} else {
    // Use sample data if database connection fails
    $sampleFile = __DIR__ . '/../database/sample_lots.json';
    if (file_exists($sampleFile)) {
        $lots = json_decode(file_get_contents($sampleFile), true);
        $error = "Using sample data - database connection failed";
    } else {
        $lots = [];
        $error = "No database connection and no sample data available";
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
      margin-top: 16px;
      width: 95%;
      max-width: 1400px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.08);
      position: relative;
    }
    
    .map-image-wrapper {
      position: relative;
      width: 100%;
      height: 70vh;
      min-height: 500px;
      max-height: 850px;
      overflow: hidden;
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.12);
      cursor: grab;
      background: #f8f9fa;
      border: 1px solid #e9ecef;
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
    
    .hidden-marker {
      display: none !important;
    }
    
    .highlighted-marker {
      z-index: 105 !important;
      border-width: 4px !important;
      box-shadow: 0 0 0 4px white, 0 0 0 8px #3b82f6, 0 4px 20px rgba(0,0,0,0.5) !important;
      animation: pulse-ring 2s infinite;
    }
    
    @keyframes pulse-ring {
      0% { box-shadow: 0 0 0 4px white, 0 0 0 8px rgba(59, 130, 246, 0.8), 0 4px 20px rgba(0,0,0,0.5); }
      50% { box-shadow: 0 0 0 4px white, 0 0 0 12px rgba(59, 130, 246, 0.4), 0 4px 20px rgba(0,0,0,0.5); }
      100% { box-shadow: 0 0 0 4px white, 0 0 0 8px rgba(59, 130, 246, 0.8), 0 4px 20px rgba(0,0,0,0.5); }
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
    
    .google-maps-card {
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      background: white;
      max-height: 100%;
      display: flex;
      flex-direction: column;
    }
    
    @media (max-width: 768px) {
      .map-container {
        width: 100%;
        padding: 16px;
      }
      
      .map-image-wrapper {
        height: 50vh;
        min-height: 350px;
        max-height: 600px;
        border-radius: 8px;
      }
      
      .map-legend {
        flex-wrap: wrap;
        gap: 8px;
        padding: 8px 12px;
        font-size: 12px;
      }
      
      .legend-item {
        gap: 4px;
      }
      
      .legend-box {
        width: 16px;
        height: 16px;
      }
    }
    
    @media (max-width: 480px) {
      .map-image-wrapper {
        height: 40vh;
        min-height: 300px;
        max-height: 500px;
        border-radius: 6px;
      }
      
      .map-legend {
        padding: 6px 8px;
        font-size: 11px;
      }
      
      .legend-box {
        width: 14px;
        height: 14px;
      }
    }
    
    .card-header {
      padding: 16px 20px;
      background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
      border-bottom: 1px solid #e9ecef;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-shrink: 0;
    }
    
    @media (max-width: 768px) {
      .card-header {
        padding: 12px 16px;
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
      }
    }
    
    .lot-info h2 {
      margin: 0;
      font-size: 18px;
      color: #202124;
      font-weight: 600;
    }
    
    @media (max-width: 768px) {
      .lot-info h2 {
        font-size: 16px;
      }
    }
    
    .lot-location {
      display: flex;
      align-items: center;
      gap: 6px;
      color: #5f6368;
      font-size: 14px;
      margin-top: 4px;
    }
    
    @media (max-width: 768px) {
      .lot-location {
        font-size: 13px;
      }
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
    
    .status-badge.maintenance {
      background: #fafafa;
      color: #757575;
    }
    
    .card-content {
      padding: 20px;
      overflow-y: auto;
      flex: 1;
    }
    
    @media (max-width: 768px) {
      .card-content {
        padding: 16px;
      }
    }
    
    @media (max-width: 480px) {
      .card-content {
        padding: 12px;
      }
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
      max-height: 90vh;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }
    
    @media (max-width: 768px) {
      .modal-map-content {
        width: 95%;
        max-width: none;
        max-height: 95vh;
        border-radius: 12px 12px 0 0;
        margin: 0;
      }
    }
    
    @media (max-width: 480px) {
      .modal-map-content {
        width: 100%;
        max-height: 100vh;
        border-radius: 0;
      }
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
      overflow-y: auto;
      flex: 1;
      scrollbar-width: thin;
      scrollbar-color: rgba(47, 109, 246, 0.3) transparent;
    }
    
    /* Modern transparent scrollbar for Webkit browsers */
    .modal-map-body::-webkit-scrollbar {
      width: 6px;
    }
    
    .modal-map-body::-webkit-scrollbar-track {
      background: transparent;
    }
    
    .modal-map-body::-webkit-scrollbar-thumb {
      background: rgba(47, 109, 246, 0.3);
      border-radius: 3px;
      transition: background 0.3s ease;
    }
    
    .modal-map-body::-webkit-scrollbar-thumb:hover {
      background: rgba(47, 109, 246, 0.5);
    }
    
    @media (max-width: 768px) {
      .modal-map-body {
        padding: 16px;
      }
    }
    
    @media (max-width: 480px) {
      .modal-map-body {
        padding: 12px;
      }
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
    
    /* Layer Management Styles */
    .layer-info {
      background: #f8f9fa;
      border-radius: 8px;
      padding: 16px;
      margin-bottom: 16px;
    }
    
    .layer-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
    }
    
    .layer-title {
      font-size: 14px;
      font-weight: 600;
      color: #202124;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .layer-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
      gap: 8px;
    }
    
    @media (max-width: 768px) {
      .layer-grid {
        grid-template-columns: repeat(auto-fill, minmax(70px, 1fr));
        gap: 6px;
      }
    }
    
    @media (max-width: 480px) {
      .layer-grid {
        grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));
        gap: 4px;
      }
    }
    
    .layer-item {
      border: 2px solid #e8eaed;
      border-radius: 6px;
      padding: 8px;
      text-align: center;
      cursor: pointer;
      transition: all 0.2s ease;
      position: relative;
    }
    
    .layer-item:hover {
      border-color: #1a73e8;
      transform: translateY(-1px);
    }
    
    .layer-item.occupied .layer-status {
      color: #ea580c;
    }
    
    .view-details-btn {
      position: absolute;
      bottom: 4px;
      right: 4px;
      background: rgba(47, 109, 246, 0.9);
      color: white;
      font-size: 10px;
      padding: 2px 6px;
      border-radius: 4px;
      opacity: 0;
      transition: opacity 0.2s ease;
    }
    
    .layer-item:hover .view-details-btn {
      opacity: 1;
    }
    
    .layer-item.occupied {
      background: #fff3e0;
      border-color: #f57c00;
    }
    
    .layer-item.vacant {
      background: #e8f5e8;
      border-color: #2e7d32;
    }
    
    .layer-number {
      font-weight: 600;
      font-size: 14px;
      color: #202124;
    }
    
    .layer-status {
      font-size: 11px;
      color: #5f6368;
      margin-top: 2px;
    }
    
    .layer-indicator {
      position: absolute;
      top: 2px;
      right: 2px;
      width: 8px;
      height: 8px;
      border-radius: 50%;
    }
    
    .layer-indicator.occupied {
      background: #f57c00;
    }
    .layer-indicator.vacant {
      background: #2e7d32;
    }
    
    .layer-actions {
      display: flex;
      gap: 8px;
      align-items: center;
    }
    
    .add-layer-btn, .remove-layer-btn {
      padding: 6px 12px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 12px;
      font-weight: 600;
      transition: all 0.2s ease;
    }
    
    .add-layer-btn {
      background: var(--primary);
      color: white;
    }
    
    .add-layer-btn:hover {
      background: #0056b3;
      transform: translateY(-1px);
    }
    
    .remove-layer-btn {
      background: #dc3545;
      color: white;
    }
    
    .remove-layer-btn:hover {
      background: #c82333;
      transform: translateY(-1px);
    }
    
    .remove-layer-btn:disabled {
      background: #6c757d;
      cursor: not-allowed;
      transform: none;
    }
    
    .lot-layer-indicator {
      position: absolute;
      top: 2px;
      right: 2px;
      background: rgba(0,0,0,0.8);
      color: white;
      border-radius: 50%;
      width: 16px;
      height: 16px;
      font-size: 9px;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      pointer-events: none;
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
        <a href="index.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16" /><path d="M4 12h16" /><path d="M4 17h16" /><path d="M8 7v10" /><path d="M16 7v10" /></svg></span><span>Lot Management</span></a>
        <a href="lot-availability.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20" /><path d="M2 12h20" /><path d="M4 4l16 16" /></svg></span><span>Lots</span></a>
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
        <div class="actions">
          <button onclick="window.print()" class="btn-primary" style="background: #6b7a90;">
            <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px">
              <path d="M6 9V2h12v7" />
              <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
              <path d="M6 14h12v8H6z" />
            </svg>
            Print Map
          </button>
        </div>
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
              <?php 
              // Check if lot has map coordinates (works for both DB and sample data)
              $hasCoords = isset($lot['map_x']) && isset($lot['map_y']) && 
                          isset($lot['map_width']) && isset($lot['map_height']) &&
                          $lot['map_x'] !== null && $lot['map_y'] !== null && 
                          $lot['map_width'] !== null && $lot['map_height'] !== null;
              ?>
              <?php if ($hasCoords): ?>
                <?php 
                // Handle different data structures
                $totalLayers = isset($lot['total_layers']) ? $lot['total_layers'] : 1;
                $occupiedLayers = isset($lot['occupied_layers']) ? $lot['occupied_layers'] : 0;
                $actualStatus = isset($lot['actual_status']) ? $lot['actual_status'] : $lot['status'];
                $deceasedName = isset($lot['deceased_name']) ? $lot['deceased_name'] : null;
                ?>
                <div class="lot-marker <?php echo strtolower($actualStatus); ?>"
                     data-lot-id="<?php echo $lot['id']; ?>"
                     style="left: <?php echo $lot['map_x']; ?>%; 
                            top: <?php echo $lot['map_y']; ?>%;
                            width: <?php echo $lot['map_width']; ?>%;
                            height: <?php echo $lot['map_height']; ?>%;"
                     onclick="showLotDetails(<?php echo htmlspecialchars(json_encode($lot)); ?>)"
                     title="<?php echo htmlspecialchars($lot['lot_number']); ?> - <?php echo $actualStatus; ?>">
                  <div class="lot-label"><?php echo htmlspecialchars($lot['lot_number']); ?></div>
                  <?php if ($totalLayers > 1): ?>
                    <div class="lot-layer-indicator" title="<?php echo $occupiedLayers; ?>/<?php echo $totalLayers; ?> layers occupied"><?php echo $occupiedLayers; ?>/<?php echo $totalLayers; ?></div>
                  <?php endif; ?>
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
            <div style="margin-top: 24px; padding: 16px 20px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 12px; color: #92400e; font-size: 14px; display: flex; align-items: center; gap: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
              <span style="font-size: 20px;">⚠️</span>
              <div>
                <strong>Note:</strong> <?php echo count($lotsWithoutCoordinates); ?> lot(s) don't have map coordinates assigned yet.
                <a href="map-editor.php" style="color: #2563eb; text-decoration: underline; font-weight: 600; margin-left: 4px;">
                  Open Map Editor to mark lots on the map
                </a>
              </div>
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
      
      // Parse burial info if available
      let burialInfo = [];
      if (lot.burial_info) {
        burialInfo = lot.burial_info.split(',').map(info => {
          const [name, layer] = info.split('|');
          return { name, layer: parseInt(layer) || 1 };
        });
      }
      
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
            <!-- Layer Management Section -->
            <div class="layer-info">
              <div class="layer-header">
                <div class="layer-title">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5z"/>
                    <circle cx="12" cy="12" r="3"/>
                  </svg>
                  <span>Burial Layers</span>
                </div>
                <div class="layer-actions">
                  <button class="add-layer-btn" onclick="addNewLayer(${lot.id})">
                    + Add Layer
                  </button>
                  <button class="remove-layer-btn" onclick="removeLayer(${lot.id})" title="Remove highest layer">
                    ✕ Remove Layer
                  </button>
                </div>
              </div>
              <div id="layerGrid" class="layer-grid">
                <!-- Layers will be populated by JavaScript -->
              </div>
            </div>
            
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
            </div>
            
            ${burialInfo.length > 0 ? `
              <div class="deceased-section">
                <div class="section-title">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                  </svg>
                  <span>Deceased Information</span>
                </div>
                ${burialInfo.map(burial => `
                  <div style="margin-bottom: 8px; padding: 8px; background: white; border-radius: 4px;">
                    <strong>${burial.name}</strong> - Layer ${burial.layer}
                  </div>
                `).join('')}
              </div>
            ` : ''}
          </div>
        </div>
      `;
      
      modalBody.innerHTML = html;
      modal.style.display = 'flex';
      
      // Load layer information
      loadLotLayers(lot.id);
      
      // Auto-load grave images when modal opens
      loadGraveImages(lot.id);
    }
    
    async function loadGraveImages(lotId) {
      const grid = document.getElementById('graveImagesGrid');
      
      grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: var(--muted);">Loading images...</div>';
      
      try {
        const burialResponse = await fetch(`../api/burial_records.php`);
        const burialData = await burialResponse.json();
        
        if (burialData.success && burialData.data) {
          const lotBurials = burialData.data.filter(record => record.lot_id == lotId);
          if (lotBurials.length > 0) {
            const imageFetches = lotBurials.map(record => 
              fetch(`../api/burial_images.php?burial_record_id=${record.id}`)
                .then(res => res.json())
                .then(data => ({
                  recordId: record.id,
                  images: (data.success && data.data) ? data.data : []
                }))
                .catch(() => ({ recordId: record.id, images: [] }))
            );
            const results = await Promise.all(imageFetches);
            const allImages = results.flatMap(r => r.images.map(img => ({ ...img, burial_record_id: r.recordId })));
            if (allImages.length > 0) {
              grid.innerHTML = allImages.map(img => `
                <div style="border: 1px solid var(--border); border-radius: 8px; overflow: hidden; cursor: pointer;" onclick="showImageGallery('${img.burial_record_id}', '${img.id}')">
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
    
    async function removeLayer(lotId) {
      try {
        // Get current layers to find the highest one
        const response = await fetch(`../api/lot_layers.php?lot_id=${lotId}`);
        const data = await response.json();
        
        if (!data.success || !data.data || data.data.length === 0) {
          alert('No layers found for this lot');
          return;
        }
        
        // Find the highest layer number
        const layers = data.data;
        const highestLayer = Math.max(...layers.map(l => l.layer_number));
        
        // Check if the highest layer is occupied
        const highestLayerData = layers.find(l => l.layer_number === highestLayer);
        if (highestLayerData.is_occupied) {
          alert(`Cannot remove Layer ${highestLayer} - it is occupied by ${highestLayerData.deceased_name || 'someone'}`);
          return;
        }
        
        if (!confirm(`Are you sure you want to remove Layer ${highestLayer}? This action cannot be undone.`)) {
          return;
        }
        
        // Delete the highest layer
        const deleteResponse = await fetch(`../api/lot_layers.php`, {
          method: 'DELETE',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            lot_id: lotId,
            layer_number: highestLayer
          })
        });
        
        const deleteResult = await deleteResponse.json();
        
        if (deleteResult.success) {
          alert(`Layer ${highestLayer} has been removed successfully`);
          // Reload layers and lot details
          loadLotLayers(lotId);
          // Instead of full reload, just refresh the lot data in the background if needed
          // or at least don't reload if we want to keep the modal open.
          // location.reload();
        } else {
          alert('Failed to remove layer: ' + deleteResult.message);
        }
        
      } catch (error) {
        console.error('Error removing layer:', error);
        alert('Error removing layer: ' + error.message);
      }
    }
    
    
    function showLayerDetails(lotId, layerNumber, isOccupied, deceasedName) {
      if (isOccupied === 'false') {
        // For vacant layers, redirect to burial records with pre-selected lot and layer
        window.location.href = `burial-records.php?lot_id=${lotId}&layer=${layerNumber}`;
        return;
      }
      
      // For occupied layers, show detailed modal with burial information and images
      showLayerBurialDetails(lotId, layerNumber, deceasedName);
    }
    
    async function showLayerBurialDetails(lotId, layerNumber, deceasedName) {
      try {
        // Fetch burial records for this specific lot and layer
        const response = await fetch(`../api/burial_records.php`);
        const data = await response.json();
        
        if (!data.success || !data.data) {
          alert('Error loading burial records');
          return;
        }
        
        // Find the burial record for this specific layer
        const burialRecord = data.data.find(record => 
          record.lot_id == lotId && record.layer == layerNumber
        );
        
        if (!burialRecord) {
          alert('Burial record not found for this layer');
          return;
        }
        
        // Create a new modal for layer-specific details
        const layerModal = document.createElement('div');
        layerModal.className = 'modal-map';
        layerModal.style.zIndex = '2000';
        
        layerModal.innerHTML = `
          <div class="modal-map-content" style="max-width: 600px;">
            <div class="modal-map-header">
              <h3>Layer ${layerNumber} - Burial Details</h3>
              <button class="modal-map-close" onclick="closeLayerModal()">&times;</button>
            </div>
            <div class="modal-map-body">
              <div class="google-maps-card">
                <div class="card-header">
                  <div class="lot-info">
                    <h2>${burialRecord.full_name}</h2>
                    <div class="lot-location">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                        <circle cx="12" cy="10" r="3"/>
                      </svg>
                      <span>Lot ${burialRecord.lot_number} - Layer ${layerNumber}</span>
                    </div>
                  </div>
                  <div class="status-badge occupied">Occupied</div>
                </div>
                
                <div class="card-content">
                  <div class="info-grid">
                    ${burialRecord.age ? `
                      <div class="info-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                          <circle cx="12" cy="7" r="4"/>
                        </svg>
                        <div>
                          <div class="info-label">Age</div>
                          <div class="info-value">${burialRecord.age} years old</div>
                        </div>
                      </div>
                    ` : ''}
                    
                    ${burialRecord.date_of_birth ? `
                      <div class="info-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                          <line x1="16" y1="2" x2="16" y2="6"/>
                          <line x1="8" y1="2" x2="8" y2="6"/>
                          <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        <div>
                          <div class="info-label">Date of Birth</div>
                          <div class="info-value">${new Date(burialRecord.date_of_birth).toLocaleDateString()}</div>
                        </div>
                      </div>
                    ` : ''}
                    
                    ${burialRecord.date_of_death ? `
                      <div class="info-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                        </svg>
                        <div>
                          <div class="info-label">Date of Death</div>
                          <div class="info-value">${new Date(burialRecord.date_of_death).toLocaleDateString()}</div>
                        </div>
                      </div>
                    ` : ''}
                    
                    ${burialRecord.date_of_burial ? `
                      <div class="info-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                          <polyline points="14 2 14 8 20 8"/>
                          <line x1="16" y1="13" x2="8" y2="13"/>
                          <line x1="16" y1="17" x2="8" y2="17"/>
                          <polyline points="10 9 9 9 8 9"/>
                        </svg>
                        <div>
                          <div class="info-label">Date of Burial</div>
                          <div class="info-value">${new Date(burialRecord.date_of_burial).toLocaleDateString()}</div>
                        </div>
                      </div>
                    ` : ''}
                    
                    ${burialRecord.cause_of_death ? `
                      <div class="info-item" style="grid-column: 1 / -1;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                          <line x1="12" y1="9" x2="12" y2="13"/>
                          <line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                        <div>
                          <div class="info-label">Cause of Death</div>
                          <div class="info-value">${burialRecord.cause_of_death}</div>
                        </div>
                      </div>
                    ` : ''}
                  </div>
                  
                  ${burialRecord.next_of_kin ? `
                    <div class="deceased-section">
                      <div class="section-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                          <circle cx="8.5" cy="7" r="4"/>
                          <line x1="20" y1="8" x2="20" y2="14"/>
                          <line x1="23" y1="11" x2="17" y2="11"/>
                        </svg>
                        <span>Next of Kin Information</span>
                      </div>
                      <div style="padding: 12px; background: white; border-radius: 6px;">
                        <div style="font-weight: 600; margin-bottom: 4px;">${burialRecord.next_of_kin}</div>
                        ${burialRecord.next_of_kin_contact ? `
                          <div style="font-size: 14px; color: var(--muted);">Contact: ${burialRecord.next_of_kin_contact}</div>
                        ` : ''}
                      </div>
                    </div>
                  ` : ''}
                  
                  ${burialRecord.deceased_info ? `
                    <div class="deceased-section">
                      <div class="section-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                          <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                        <span>Notes</span>
                      </div>
                      <div style="padding: 12px; background: white; border-radius: 6px;">
                        ${burialRecord.deceased_info}
                      </div>
                    </div>
                  ` : ''}
                  
                  ${burialRecord.remarks ? `
                    <div class="deceased-section">
                      <div class="section-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                          <polyline points="14 2 14 8 20 8"/>
                          <line x1="16" y1="13" x2="8" y2="13"/>
                          <line x1="16" y1="17" x2="8" y2="17"/>
                        </svg>
                        <span>Remarks</span>
                      </div>
                      <div style="padding: 12px; background: white; border-radius: 6px; font-style: italic;">
                        ${burialRecord.remarks}
                      </div>
                    </div>
                  ` : ''}
                  
                  <div class="images-section">
                    <div class="section-title">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                        <circle cx="8.5" cy="8.5" r="1.5"/>
                        <polyline points="21 15 16 10 5 21"/>
                      </svg>
                      <span>Grave Images</span>
                    </div>
                    <div id="layerGraveImagesContainer" class="images-container">
                      <div class="images-header">
                        <span>Grave Photos</span>
                      </div>
                      <div id="layerGraveImagesGrid" class="images-grid"></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        `;
        
        document.body.appendChild(layerModal);
        layerModal.style.display = 'flex';
        
        // Load grave images for this burial record
        loadLayerGraveImages(burialRecord.id);
        
        // Close modal when clicking outside
        layerModal.onclick = (e) => { 
          if (e.target === layerModal) closeLayerModal(); 
        };
        
      } catch (error) {
        console.error('Error loading layer details:', error);
        alert('Error loading layer details: ' + error.message);
      }
    }
    
    function closeLayerModal() {
      const layerModal = document.querySelector('.modal-map[style*="z-index: 2000"]');
      if (layerModal) {
        layerModal.remove();
      }
    }
    
    async function loadLayerGraveImages(burialRecordId) {
      const grid = document.getElementById('layerGraveImagesGrid');
      
      if (!grid) return;
      
      grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: var(--muted);">Loading images...</div>';
      
      try {
        const imagesResponse = await fetch(`../api/burial_images.php?burial_record_id=${burialRecordId}`);
        const imagesData = await imagesResponse.json();
        
        if (imagesData.success && imagesData.data && imagesData.data.length > 0) {
          grid.innerHTML = imagesData.data.map(img => `
            <div style="border: 1px solid var(--border); border-radius: 8px; overflow: hidden; cursor: pointer;" onclick="showImageGallery('${burialRecordId}', '${img.id}')">
              <img src="../${img.image_path}" alt="${img.image_caption || 'Grave image'}" style="width: 100%; height: 120px; object-fit: cover;">
              <div style="padding: 8px; font-size: 12px; color: var(--muted); text-align: center;">${img.image_caption || 'No caption'}</div>
            </div>
          `).join('');
        } else {
          grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: var(--muted); padding: 20px;">No images available</div>';
        }
      } catch (error) {
        console.error('Error loading grave images:', error);
        grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: #ef4444; padding: 20px;">Error loading images</div>';
      }
    }
    
    function closeLotModal() {
      document.getElementById('lotModal').style.display = 'none';
    }
    
    // Layer Management Functions
    async function loadLotLayers(lotId) {
      const layerGrid = document.getElementById('layerGrid');
      
      try {
        // Get both layers and burial records for this lot
        const [layersResponse, burialsResponse] = await Promise.all([
          fetch(`../api/lot_layers.php?lot_id=${lotId}`),
          fetch(`../api/burial_records.php`)
        ]);
        
        const layersData = await layersResponse.json();
        const burialsData = await burialsResponse.json();
        
        if (!layersData.success) {
          layerGrid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: #ef4444;">Error loading layers</div>';
          return;
        }
        
        // Get burial records for this lot
        const lotBurials = burialsData.success && burialsData.data ? 
          burialsData.data.filter(record => record.lot_id == lotId) : [];
        
        // Create a map of occupied layers from burial records
        const occupiedLayers = {};
        lotBurials.forEach(burial => {
          if (burial.layer) {
            occupiedLayers[burial.layer] = burial.full_name;
          }
        });
        
        // Update lot_layers table to match burial records
        await syncLayersWithBurials(lotId, layersData.data || [], occupiedLayers);
        
        // Get updated layers after sync
        const updatedLayersResponse = await fetch(`../api/lot_layers.php?lot_id=${lotId}`);
        const updatedLayersData = await updatedLayersResponse.json();
        
        const layers = updatedLayersData.success ? updatedLayersData.data : [];
        
        if (layers.length === 0) {
          layerGrid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: #6b7280;">No layers available</div>';
          return;
        }
        
        layerGrid.innerHTML = layers.map(layer => {
          const isOccupied = occupiedLayers[layer.layer_number] || layer.is_occupied;
          const deceasedName = occupiedLayers[layer.layer_number] || layer.deceased_name || '';
          
          return `
            <div class="layer-item ${isOccupied ? 'occupied' : 'vacant'}" 
                 onclick="showLayerDetails(${lotId}, ${layer.layer_number}, '${isOccupied}', '${deceasedName.replace(/'/g, "\\'")}')"
                 style="cursor: pointer;">
              <div class="layer-number">Layer ${layer.layer_number}</div>
              <div class="layer-status">
                ${isOccupied ? `Occupied by ${deceasedName}` : 'Vacant'}
              </div>
              <div class="layer-indicator ${isOccupied ? 'occupied' : 'vacant'}"></div>
              ${isOccupied ? '<div class="view-details-btn">📋 View Details</div>' : ''}
            </div>
          `;
        }).join('');
        
      } catch (error) {
        console.error('Error loading layers:', error);
        layerGrid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: #ef4444;">Error loading layers</div>';
      }
    }
    
    // Function to sync layers with burial records
    async function syncLayersWithBurials(lotId, layers, occupiedLayers) {
      try {
        for (const layer of layers) {
          const shouldBeOccupied = occupiedLayers[layer.layer_number];
          const currentlyOccupied = layer.is_occupied;
          
          // Update layer if occupation status doesn't match
          if (shouldBeOccupied && !currentlyOccupied) {
            // Find the burial record for this layer
            const burialResponse = await fetch(`../api/burial_records.php`);
            const burialsData = await burialResponse.json();
            
            if (burialsData.success) {
              const burial = burialsData.data.find(record => 
                record.lot_id == lotId && record.layer == layer.layer_number
              );
              
              if (burial) {
                // Update the layer to mark it as occupied
                await fetch(`../api/lot_layers.php`, {
                  method: 'PUT',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({
                    id: layer.id,
                    is_occupied: 1,
                    burial_record_id: burial.id
                  })
                });
              }
            }
          } else if (!shouldBeOccupied && currentlyOccupied) {
            // Update the layer to mark it as vacant
            await fetch(`../api/lot_layers.php`, {
              method: 'PUT',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                id: layer.id,
                is_occupied: 0,
                burial_record_id: null
              })
            });
          }
        }
      } catch (error) {
        console.error('Error syncing layers:', error);
      }
    }
    
    function selectLayer(lotId, layerNumber) {
      // Redirect to burial records page with pre-selected lot and layer
      window.location.href = `burial-records.php?lot_id=${lotId}&layer=${layerNumber}`;
    }
    
    async function addNewLayer(lotId) {
      if (!confirm('Add a new burial layer to this lot? This will allow additional burials in the same location.')) {
        return;
      }
      
      try {
        const response = await fetch('../api/lot_layers.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            lot_id: lotId,
            action: 'add_layer'
          })
        });
        
        const data = await response.json();
        
        if (data.success) {
          alert('New layer added successfully!');
          loadLotLayers(lotId); // Reload the layer display
          // Instead of full reload, just refresh the lot data in the background if needed
          // or at least don't reload if we want to keep the modal open.
          // location.reload(); 
        } else {
          alert('Error adding layer: ' + data.message);
        }
      } catch (error) {
        console.error('Error adding layer:', error);
        alert('Error adding layer');
      }
    }
    
    // Close modal when clicking outside
    document.getElementById('lotModal').onclick = function(e) {
      if (e.target === this) {
        closeLotModal();
      }
    };


    
    // Highlight lot functionality
    function highlightLotOnMap() {
      console.log('highlightLotOnMap called'); // Debug log
      
      const urlParams = new URLSearchParams(window.location.search);
      const highlightLotId = urlParams.get('highlight_lot');
      
      console.log('Highlight data:', { highlightLotId }); // Debug log
      
      if (highlightLotId) {
        // Find the lot marker on the map
        const lotMarkers = document.querySelectorAll('.lot-marker');
        console.log('Found markers:', lotMarkers.length); // Debug log
        
        let targetMarker = null;
        
        lotMarkers.forEach(marker => {
          if (marker.getAttribute('data-lot-id') === highlightLotId) {
            targetMarker = marker;
            marker.classList.add('highlighted-marker');
            console.log('Found target marker!'); // Debug log
          } else {
            marker.classList.add('hidden-marker');
          }
        });
        
        if (targetMarker) {
          console.log('Creating pin overlay'); // Debug log
          
          // Get screen size for responsive pin
          const isMobile = window.innerWidth <= 768;
          const isSmallMobile = window.innerWidth <= 480;
          
          // Create smaller responsive pin overlay
          const pinOverlay = document.createElement('div');
          const pinSize = isSmallMobile ? 25 : (isMobile ? 30 : 40);
          const fontSize = isSmallMobile ? 18 : (isMobile ? 22 : 28);
          
          pinOverlay.style.cssText = `
            position: absolute;
            z-index: 1006;
            pointer-events: none;
            animation: pinDrop 0.5s ease-out, pinBounce 2s infinite;
            font-size: ${fontSize}px;
            text-align: center;
            line-height: 1;
            filter: drop-shadow(0 ${isSmallMobile ? 4 : 6}px ${isSmallMobile ? 8 : 12}px rgba(239, 68, 68, 0.6));
            background: rgba(255, 255, 255, 0.9);
            border-radius: 50%;
            border: ${isSmallMobile ? 2 : 3}px solid #ef4444;
            display: flex;
            align-items: center;
            justify-content: center;
            width: ${pinSize}px;
            height: ${pinSize}px;
          `;
          
          // Use emoji pin with responsive styling
          pinOverlay.innerHTML = '📍';
          pinOverlay.style.color = '#ef4444';
          
          // Add the pin overlay to the map canvas instead of the marker
          const mapCanvas = document.getElementById('mapCanvas');
          if (mapCanvas) {
            // Position relative to the map canvas
            const markerRect = targetMarker.getBoundingClientRect();
            const canvasRect = mapCanvas.getBoundingClientRect();
            
            const relativeLeft = markerRect.left - canvasRect.left + (markerRect.width / 2) - (pinSize / 2);
            const relativeTop = markerRect.top - canvasRect.top - pinSize + 20;
            
            pinOverlay.style.left = relativeLeft + 'px';
            pinOverlay.style.top = relativeTop + 'px';
            
            mapCanvas.appendChild(pinOverlay);
          } else {
            // Fallback to adding to marker
            pinOverlay.style.top = `-${pinSize - 20}px`;
            pinOverlay.style.left = '50%';
            pinOverlay.style.transform = 'translateX(-50%)';
            targetMarker.style.position = 'relative';
            targetMarker.appendChild(pinOverlay);
          }
          
          // Center the map on the highlighted lot
          setTimeout(() => {
            const mapWrapper = document.querySelector('.map-image-wrapper');
            const mapCanvas = document.getElementById('mapCanvas');
            
            console.log('Centering map...'); // Debug log
            
            if (mapWrapper && mapCanvas) {
              const markerRect = targetMarker.getBoundingClientRect();
              const wrapperRect = mapWrapper.getBoundingClientRect();
              
              console.log('Marker rect:', markerRect); // Debug log
              console.log('Wrapper rect:', wrapperRect); // Debug log
              
              // Reset zoom and pan first
              zoom = 1;
              panX = 0;
              panY = 0;
              updateTransform();
              
              // Calculate the center position for the marker
              const markerCenterX = markerRect.left + markerRect.width / 2 - wrapperRect.left;
              const markerCenterY = markerRect.top + markerRect.height / 2 - wrapperRect.top;
              
              console.log('Marker center:', { markerCenterX, markerCenterY }); // Debug log
              
              // Calculate required pan to center the marker
              const wrapperCenterX = wrapperRect.width / 2;
              const wrapperCenterY = wrapperRect.height / 2;
              
              const targetPanX = wrapperCenterX - markerCenterX;
              const targetPanY = wrapperCenterY - markerCenterY;
              
              console.log('Target pan:', { targetPanX, targetPanY }); // Debug log
              
              // Apply pan and zoom
              panX = targetPanX;
              panY = targetPanY;
              zoom = 1.8; // Set a reasonable zoom level
              updateTransform();
              
              console.log('Map centered and zoomed'); // Debug log
            }
          }, 200);
          
          // Add clear highlight button to the header
          const actionsContainer = document.querySelector('.page-header .actions');
          if (actionsContainer) {
             const clearBtn = document.createElement('button');
             clearBtn.className = 'btn-primary';
             clearBtn.style.background = '#6b7280';
             clearBtn.innerHTML = '✕ Clear Highlight';
             clearBtn.onclick = function() {
                 window.location.href = 'cemetery-map.php';
             };
             actionsContainer.appendChild(clearBtn);
          }
          
          // Show notification
          showNotification(`Showing only selected lot. Click "Clear Highlight" to show all.`, 'success');
        } else {
          showNotification(`Lot not found on map`, 'warning');
        }
      }
    }
    
    function showNotification(message, type = 'info') {
      // Create notification element
      const notification = document.createElement('div');
      notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 16px 20px;
        border-radius: 8px;
        color: white;
        font-weight: 500;
        z-index: 10000;
        max-width: 300px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transform: translateX(100%);
        transition: transform 0.3s ease;
      `;
      
      // Set background color based on type
      switch(type) {
        case 'success':
          notification.style.background = '#22c55e';
          break;
        case 'warning':
          notification.style.background = '#f59e0b';
          break;
        case 'error':
          notification.style.background = '#ef4444';
          break;
        default:
          notification.style.background = '#3b82f6';
      }
      
      notification.textContent = message;
      document.body.appendChild(notification);
      
      // Slide in
      setTimeout(() => {
        notification.style.transform = 'translateX(0)';
      }, 100);
      
      // Remove after 4 seconds
      setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
          if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
          }
        }, 300);
      }, 4000);
    }
    
    // Add CSS animation for pin effects
    const style = document.createElement('style');
    style.textContent = `
      @keyframes pinDrop {
        0% {
          transform: translateX(-50%) translateY(-100px) scale(0.5);
          opacity: 0;
        }
        50% {
          transform: translateX(-50%) translateY(-10px) scale(1.1);
          opacity: 1;
        }
        75% {
          transform: translateX(-50%) translateY(5px) scale(0.95);
        }
        100% {
          transform: translateX(-50%) translateY(0) scale(1);
        }
      }
      
      @keyframes pinBounce {
        0%, 100% {
          transform: translateX(-50%) translateY(0) scale(1);
        }
        50% {
          transform: translateX(-50%) translateY(-8px) scale(1.05);
        }
      }
      
      @keyframes pulse {
        0% {
          box-shadow: 0 0 20px rgba(59, 130, 246, 0.8), 0 0 40px rgba(59, 130, 246, 0.4);
        }
        50% {
          box-shadow: 0 0 30px rgba(59, 130, 246, 1), 0 0 60px rgba(59, 130, 246, 0.6);
        }
        100% {
          box-shadow: 0 0 20px rgba(59, 130, 246, 0.8), 0 0 40px rgba(59, 130, 246, 0.4);
        }
      }
    `;
    document.head.appendChild(style);
    
    // Initialize highlighting when page loads
    document.addEventListener('DOMContentLoaded', function() {
      setTimeout(highlightLotOnMap, 500); // Small delay to ensure map is loaded
    });
  </script>
  <script src="../assets/js/app.js"></script>
</body>
</html>
