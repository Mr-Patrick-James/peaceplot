<?php
require_once __DIR__ . '/../config/auth.php';
requireLogin();

$user = getUserInfo();
$userInitials = getInitials($user['full_name']);

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$stats = [
    'total_lots' => 0,
    'available_lots' => 0,
    'occupied_lots' => 0,
    'total_sections' => 0,
    'total_blocks' => 0,
    'sections' => []
];

if ($conn) {
    try {
        // Use subquery to get actual status for each lot first
        $statusQuery = "
            SELECT 
                cl.id,
                cl.section,
                CASE 
                    WHEN (SELECT COUNT(*) FROM deceased_records dr WHERE dr.lot_id = cl.id) > 0 THEN 'Occupied'
                    WHEN (SELECT COUNT(*) FROM lot_layers ll WHERE ll.lot_id = cl.id AND ll.is_occupied = 1) > 0 THEN 'Occupied'
                    ELSE cl.status
                END as actual_status
            FROM cemetery_lots cl
        ";

        $stats['total_lots'] = $conn->query("SELECT COUNT(*) FROM cemetery_lots")->fetchColumn();
        
        $stats['available_lots'] = $conn->query("
            SELECT COUNT(*) FROM ($statusQuery) as lots WHERE actual_status = 'Vacant'
        ")->fetchColumn();
        
        $stats['occupied_lots'] = $conn->query("
            SELECT COUNT(*) FROM ($statusQuery) as lots WHERE actual_status = 'Occupied'
        ")->fetchColumn();

        // Fetch Section and Block counts
        $stats['total_sections'] = $conn->query("SELECT COUNT(*) FROM sections")->fetchColumn();
        $stats['total_blocks'] = $conn->query("SELECT COUNT(*) FROM blocks")->fetchColumn();
        
        $stmt = $conn->query("
            SELECT 
                section,
                COUNT(*) as total,
                SUM(CASE WHEN actual_status = 'Occupied' THEN 1 ELSE 0 END) as occupied,
                SUM(CASE WHEN actual_status = 'Vacant' THEN 1 ELSE 0 END) as vacant
            FROM ($statusQuery) as lots
            GROUP BY section
            ORDER BY section
        ");
        $stats['sections'] = $stmt->fetchAll();
        
        $available_percent = $stats['total_lots'] > 0 ? round(($stats['available_lots'] / $stats['total_lots']) * 100, 1) : 0;
        $occupied_percent = $stats['total_lots'] > 0 ? round(($stats['occupied_lots'] / $stats['total_lots']) * 100, 1) : 0;
        
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
  <title>PeacePlot Admin - Dashboard</title>
  <link rel="stylesheet" href="../assets/css/styles.css" />
  <style>
    /* Dashboard specific modern UI */
    .dashboard-header {
      background: #fff;
      padding: 24px 32px;
      border-radius: 16px;
      margin-bottom: 24px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.03);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .header-left .title {
      font-size: 24px;
      font-weight: 700;
      color: #1e293b;
      margin: 0 0 4px 0;
    }
    .header-left .subtitle {
      font-size: 14px;
      color: #64748b;
      margin: 0;
    }
    
    /* Universal Search Styles */
    .search-container {
      position: relative;
      width: 400px;
    }
    .universal-search-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }
    .universal-search-wrapper svg {
      position: absolute;
      left: 16px;
      color: #94a3b8;
      pointer-events: none;
    }
    .universal-search-input {
      width: 100%;
      padding: 12px 16px 12px 48px;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      font-size: 14px;
      outline: none;
      transition: all 0.2s;
      background: #f8fafc;
    }
    .universal-search-input:focus {
      background: #fff;
      border-color: #3b82f6;
      box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    }
    .search-results-dropdown {
      position: absolute;
      top: calc(100% + 8px);
      left: 0;
      right: 0;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
      border: 1px solid #e2e8f0;
      z-index: 1000;
      display: none;
      overflow: hidden;
    }
    .search-result-item {
      padding: 12px 16px;
      display: flex;
      align-items: center;
      gap: 12px;
      cursor: pointer;
      transition: background 0.2s;
      text-decoration: none;
      border-bottom: 1px solid #f1f5f9;
    }
    .search-result-item:last-child { border-bottom: none; }
    .search-result-item:hover { background: #f8fafc; }
    .result-icon {
      width: 32px;
      height: 32px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .icon-lot { background: #eff6ff; color: #3b82f6; }
    .icon-deceased { background: #fef2f2; color: #ef4444; }
    .result-info { flex: 1; min-width: 0; }
    .result-title {
      font-size: 14px;
      font-weight: 600;
      color: #1e293b;
      display: block;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .result-subtitle {
      font-size: 12px;
      color: #64748b;
      display: block;
    }

    /* Stats Cards Styles */
    .dashboard-stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 24px;
      margin-bottom: 32px;
    }
    .dash-stat-card {
      background: #fff;
      padding: 24px;
      border-radius: 16px;
      border-left: 5px solid #0e1f35; /* Sidebar background color */
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    }
    .dash-stat-info .label {
      font-size: 13px;
      font-weight: 500;
      color: #94a3b8;
      margin-bottom: 4px;
    }
    .dash-stat-info .value {
      font-size: 28px;
      font-weight: 700;
      color: #1e293b;
      line-height: 1;
    }
    .dash-stat-info .subtext {
      font-size: 12px;
      color: #64748b;
      margin-top: 8px;
    }
    .dash-stat-icon {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .bg-blue { background: #eff6ff; color: #3b82f6; }
    .bg-green { background: #f0fdf4; color: #22c55e; }
    .bg-orange { background: #fff7ed; color: #f97316; }

    .content-card {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.03);
      margin-bottom: 24px;
      overflow: hidden;
    }
    .content-card-header {
      padding: 20px 24px;
      border-bottom: 1px solid #f1f5f9;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .content-card-title {
      font-size: 16px;
      font-weight: 700;
      color: #1e293b;
      margin: 0;
    }

    /* Advanced Filter Control Styles */
    .dashboard-controls {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
      gap: 16px;
    }
    .controls-left {
      display: flex;
      gap: 12px;
      align-items: center;
    }
    .controls-right {
      display: flex;
      gap: 12px;
      align-items: center;
    }
    .btn-filter {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 10px 16px;
      background: #3b82f6;
      color: #fff;
      border: none;
      border-radius: 10px;
      font-weight: 600;
      font-size: 14px;
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
      transition: all 0.2s;
      position: relative;
    }
    .btn-filter:hover { background: #2563eb; transform: translateY(-1px); }
    .filter-badge {
      background: #fff;
      color: #3b82f6;
      width: 20px;
      height: 20px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 11px;
      font-weight: 700;
    }
    .filter-popover {
      position: absolute;
      top: calc(100% + 12px);
      left: 0;
      width: 320px;
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.15);
      border: 1px solid #e2e8f0;
      z-index: 1000;
      display: none;
      overflow: hidden;
      color: #1e293b;
      text-align: left;
    }
    .filter-popover.active { display: block; }
    .popover-header {
      padding: 16px 20px;
      border-bottom: 1px solid #f1f5f9;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .popover-header h3 { font-size: 15px; font-weight: 700; margin: 0; }
    .btn-save-view { font-size: 13px; color: #3b82f6; text-decoration: none; font-weight: 600; }
    .popover-body { padding: 12px 0; max-height: 400px; overflow-y: auto; }
    
    .filter-category { border-bottom: 1px solid #f8fafc; }
    .filter-category:last-child { border-bottom: none; }
    .category-toggle {
      width: 100%;
      padding: 12px 20px;
      display: flex;
      align-items: center;
      gap: 10px;
      background: none;
      border: none;
      cursor: pointer;
      font-size: 14px;
      font-weight: 600;
      color: #1e293b;
      transition: background 0.2s;
    }
    .category-toggle:hover { background: #f8fafc; }
    .category-toggle svg { 
      width: 16px; height: 16px; color: #94a3b8; 
      transition: transform 0.2s;
    }
    .filter-category.active .category-toggle svg { transform: rotate(90deg); }
    
    .category-content { display: none; padding: 0 20px 12px 46px; }
    .filter-category.active .category-content { display: block; }
    
    .filter-option {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 6px 0;
      cursor: pointer;
      font-size: 13.5px;
      color: #475569;
    }
    .filter-option input[type="checkbox"] {
      width: 16px;
      height: 16px;
      border-radius: 4px;
      border: 2px solid #cbd5e1;
      cursor: pointer;
    }
    
    .active-filters-row {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 24px;
    }
    .filter-chip {
      display: flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      background: #eff6ff;
      color: #3b82f6;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
    }
    .filter-chip .remove {
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0.7;
    }
    .filter-chip .remove:hover { opacity: 1; }

    .search-inline-wrapper {
      position: relative;
      width: 240px;
    }
    .search-inline-wrapper input {
      width: 100%;
      padding: 10px 16px 10px 40px;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      font-size: 14px;
      outline: none;
      background: #fff;
    }
    .search-inline-wrapper svg {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #94a3b8;
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
        <a href="dashboard.php" class="active"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 13h8V3H3v10z" /><path d="M13 21h8V11h-8v10z" /><path d="M13 3h8v6h-8V3z" /><path d="M3 21h8v-6H3v6z" /></svg></span><span>Dashboard</span></a>
        <div class="dropdown">
          <a href="#" class="dropdown-toggle" onclick="this.parentElement.classList.toggle('active'); return false;">
            <div style="display: flex; align-items: center;">
              <span class="icon">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M4 7h16" />
                  <path d="M4 12h16" />
                  <path d="M4 17h16" />
                  <path d="M8 7v10" />
                  <path d="M16 7v10" />
                </svg>
              </span>
              <span>Lot Management</span>
            </div>
            <svg class="arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
          </a>
          <div class="dropdown-content">
            <a href="index.php"><span>Manage Lots</span></a>
            <a href="sections.php"><span>Manage Sections</span></a>
          </div>
        </div>
        <a href="lot-availability.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20" /><path d="M2 12h20" /><path d="M4 4l16 16" /></svg></span><span>Lots</span></a>
        <a href="cemetery-map.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2V6z" /><path d="M9 4v14" /><path d="M15 6v14" /></svg></span><span>Cemetery Map</span></a>
        <a href="map-editor.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" /><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" /></svg></span><span>Map Editor</span></a>
        <a href="burial-records.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" /><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" /><path d="M8 6h8" /><path d="M8 10h8" /></svg></span><span>Burial Records</span></a>
        <a href="reports.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18" /><path d="M7 14v4" /><path d="M11 10v8" /><path d="M15 6v12" /><path d="M19 12v6" /></svg></span><span>Reports</span></a>
        <a href="history.php"><span class="icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span><span>History</span></a>
      </nav>

      <div class="sidebar-footer">
        <div class="user" onclick="window.location.href='settings.php'" style="cursor:pointer;">
          <div class="avatar"><?php echo htmlspecialchars($userInitials); ?></div>
          <div class="user-info-text">
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
      <header class="dashboard-header">
        <div class="header-left">
          <h1 class="title">Dashboard Overview</h1>
          <p class="subtitle">Quick overview of cemetery operations and statistics</p>
        </div>
        
        <div class="search-container">
          <div class="universal-search-wrapper">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <input type="text" class="universal-search-input" id="universalSearch" placeholder="Search lots, deceased names...">
          </div>
          <div class="search-results-dropdown" id="searchResults">
            <!-- Results will be injected here -->
          </div>
        </div>
      </header>

      <div class="dashboard-controls">
        <div class="controls-left">
          <div style="position: relative;">
            <button class="btn-filter" id="filterBtn">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
              Filters
              <span class="filter-badge" id="filterBadge">0</span>
            </button>
            
            <div class="filter-popover" id="filterPopover">
              <div class="popover-header">
                <h3>Filters</h3>
                <a href="#" class="btn-save-view">Save view</a>
              </div>
              <div class="popover-body">
                <!-- Sections Category -->
                <div class="filter-category active">
                  <button class="category-toggle" onclick="toggleCategory(this)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    Sections
                  </button>
                  <div class="category-content">
                    <?php foreach ($stats['sections'] as $section): ?>
                      <label class="filter-option">
                        <input type="checkbox" name="section" value="<?php echo htmlspecialchars($section['section']); ?>" onchange="updateFilters()">
                        <?php echo htmlspecialchars($section['section']); ?>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </div>
                
                <!-- Status Category -->
                <div class="filter-category">
                  <button class="category-toggle" onclick="toggleCategory(this)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    Status
                  </button>
                  <div class="category-content">
                    <label class="filter-option">
                      <input type="checkbox" name="status" value="Vacant" onchange="updateFilters()"> Vacant
                    </label>
                    <label class="filter-option">
                      <input type="checkbox" name="status" value="Occupied" onchange="updateFilters()"> Occupied
                    </label>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <div class="search-inline-wrapper">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <input type="text" placeholder="Search in results..." id="inlineSearch">
          </div>
        </div>
        
        <div class="controls-right">
          <select class="form-group" style="margin:0; width: 160px; padding: 8px 12px; border-radius: 10px;">
            <option>Last 30 days</option>
            <option>Last 90 days</option>
            <option>Last year</option>
            <option>All time</option>
          </select>
        </div>
      </div>

      <div class="active-filters-row" id="activeFilters">
        <!-- Chips will be injected here -->
      </div>

      <?php if (isset($error)): ?>
        <div class="card" style="padding:20px; color:#ef4444;">
          Error loading dashboard data: <?php echo htmlspecialchars($error); ?>
        </div>
      <?php else: ?>

      <div class="dashboard-stats">
        <div class="dash-stat-card">
          <div class="dash-stat-info">
            <div class="label">Total Cemetery Lots</div>
            <div class="value"><?php echo $stats['total_lots']; ?></div>
            <div class="subtext">All sections</div>
          </div>
          <div class="dash-stat-icon bg-blue">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
              <circle cx="12" cy="10" r="3" />
            </svg>
          </div>
        </div>

        <div class="dash-stat-card">
          <div class="dash-stat-info">
            <div class="label">Total Sections</div>
            <div class="value"><?php echo $stats['total_sections']; ?></div>
            <div class="subtext">Cemetery Areas</div>
          </div>
          <div class="dash-stat-icon bg-blue">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M4 7h16" /><path d="M4 12h16" /><path d="M4 17h16" />
            </svg>
          </div>
        </div>

        <div class="dash-stat-card">
          <div class="dash-stat-info">
            <div class="label">Total Blocks</div>
            <div class="value"><?php echo $stats['total_blocks']; ?></div>
            <div class="subtext">Categorized Blocks</div>
          </div>
          <div class="dash-stat-icon bg-blue">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/>
            </svg>
          </div>
        </div>

        <div class="dash-stat-card">
          <div class="dash-stat-info">
            <div class="label">Available Lots</div>
            <div class="value"><?php echo $stats['available_lots']; ?></div>
            <div class="subtext"><?php echo $available_percent; ?>% available</div>
          </div>
          <div class="dash-stat-icon bg-green">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
              <circle cx="12" cy="12" r="3" />
            </svg>
          </div>
        </div>

        <div class="dash-stat-card">
          <div class="dash-stat-info">
            <div class="label">Occupied Lots</div>
            <div class="value"><?php echo $stats['occupied_lots']; ?></div>
            <div class="subtext"><?php echo $occupied_percent; ?>% occupied</div>
          </div>
          <div class="dash-stat-icon bg-orange">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
              <circle cx="9" cy="7" r="4" />
              <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
              <path d="M16 3.13a4 4 0 0 1 0 7.75" />
            </svg>
          </div>
        </div>
      </div>

      <div class="content-card">
        <div class="content-card-header">
          <h2 class="content-card-title">Section Summary</h2>
        </div>

        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th align="left">Section</th>
                <th align="right">Total Lots</th>
                <th align="right">Occupied</th>
                <th align="right">Vacant</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($stats['sections'])): ?>
                <tr>
                  <td colspan="4" style="text-align:center; padding: 40px; color:#94a3b8;">No sections found</td>
                </tr>
              <?php else: ?>
                <?php foreach ($stats['sections'] as $section): ?>
                  <tr>
                    <td><strong><?php echo htmlspecialchars($section['section']); ?></strong></td>
                    <td align="right"><?php echo $section['total']; ?></td>
                    <td align="right"><span style="color:#f97316; font-weight:600;"><?php echo $section['occupied']; ?></span></td>
                    <td align="right"><span style="color:#22c55e; font-weight:600;"><?php echo $section['vacant']; ?></span></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="content-card">
        <div class="content-card-header">
          <div>
            <h2 class="content-card-title">Cemetery Status by Section</h2>
            <p style="font-size:12px; color:#94a3b8; margin:4px 0 0 0;">Comparison of Vacant vs Occupied lots</p>
          </div>
        </div>
        <div class="chart-placeholder" style="padding: 32px;">
          <div class="chart-bar-container">
            <div class="chart-y-axis">
              <span>20</span>
              <span>15</span>
              <span>10</span>
              <span>5</span>
              <span>0</span>
            </div>
            <div class="chart-bars">
              <?php foreach ($stats['sections'] as $section): ?>
                <div class="chart-bar-group">
                  <div style="display:flex; gap:4px; align-items:flex-end; height:200px; width:100%; justify-content:center;">
                    <div class="chart-bar" style="height:<?php echo min($section['vacant'] * 10, 200); ?>px; background:#22c55e; width:24px; border-radius:4px 4px 0 0;" title="Vacant: <?php echo $section['vacant']; ?>"></div>
                    <div class="chart-bar" style="height:<?php echo min($section['occupied'] * 10, 200); ?>px; background:#f97316; width:24px; border-radius:4px 4px 0 0;" title="Occupied: <?php echo $section['occupied']; ?>"></div>
                  </div>
                  <span class="chart-label" style="font-weight:600; color:#1e293b; margin-top:12px;"><?php echo htmlspecialchars($section['section']); ?></span>
                  <div style="display:flex; flex-direction:column; gap:2px;">
                    <span class="chart-label" style="font-size:10px; color:#22c55e"><?php echo $section['vacant']; ?> V</span>
                    <span class="chart-label" style="font-size:10px; color:#f97316"><?php echo $section['occupied']; ?> O</span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div style="display:flex; justify-content:center; gap:24px; margin-top:32px; font-size:13px; font-weight:500;">
            <div style="display:flex; align-items:center; gap:8px;"><div style="width:12px; height:12px; background:#22c55e; border-radius:3px;"></div> Vacant Lots</div>
            <div style="display:flex; align-items:center; gap:8px;"><div style="width:12px; height:12px; background:#f97316; border-radius:3px;"></div> Occupied Lots</div>
          </div>
        </div>
      </div>

      <?php endif; ?>
    </main>
  </div>

  <script>
    // Universal Search Logic
    const searchInput = document.getElementById('universalSearch');
    const searchResults = document.getElementById('searchResults');
    let searchTimeout = null;

    searchInput.addEventListener('input', (e) => {
      const query = e.target.value.trim();
      clearTimeout(searchTimeout);

      if (query.length < 2) {
        searchResults.style.display = 'none';
        return;
      }

      searchTimeout = setTimeout(async () => {
        try {
          const response = await fetch(`../api/universal_search.php?q=${encodeURIComponent(query)}`);
          const result = await response.json();

          if (result.success && result.data.length > 0) {
            searchResults.innerHTML = result.data.map(item => `
              <a href="${item.url}" class="search-result-item">
                <div class="result-icon icon-${item.type}">
                  ${item.type === 'lot' ? 
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>' : 
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>'
                  }
                </div>
                <div class="result-info">
                  <span class="result-title">${item.title}</span>
                  <span class="result-subtitle">${item.subtitle}</span>
                </div>
              </a>
            `).join('');
            searchResults.style.display = 'block';
          } else {
            searchResults.innerHTML = '<div style="padding: 16px; text-align: center; color: #94a3b8; font-size: 13px;">No results found</div>';
            searchResults.style.display = 'block';
          }
        } catch (error) {
          console.error('Search error:', error);
        }
      }, 300);
    });

    // Close results when clicking outside
    document.addEventListener('click', (e) => {
      if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
        searchResults.style.display = 'none';
      }
      
      // Close filter popover when clicking outside
      const filterBtn = document.getElementById('filterBtn');
      const filterPopover = document.getElementById('filterPopover');
      if (filterBtn && filterPopover && !filterBtn.contains(e.target) && !filterPopover.contains(e.target)) {
        filterPopover.classList.remove('active');
      }
    });

    // Advanced Filter Control Logic
    const filterBtn = document.getElementById('filterBtn');
    const filterPopover = document.getElementById('filterPopover');
    const filterBadge = document.getElementById('filterBadge');
    const activeFiltersRow = document.getElementById('activeFilters');
    const inlineSearch = document.getElementById('inlineSearch');

    if (filterBtn) {
      filterBtn.addEventListener('click', () => {
        filterPopover.classList.toggle('active');
      });
    }

    function toggleCategory(btn) {
      btn.parentElement.classList.toggle('active');
    }

    function updateFilters() {
      const checkboxes = document.querySelectorAll('.filter-popover input[type="checkbox"]:checked');
      const activeFilters = [];
      
      checkboxes.forEach(cb => {
        activeFilters.push({
          name: cb.name,
          value: cb.value
        });
      });

      // Update badge
      filterBadge.textContent = activeFilters.length;
      filterBadge.style.display = activeFilters.length > 0 ? 'flex' : 'none';

      // Update chips
      activeFiltersRow.innerHTML = activeFilters.map(filter => `
        <div class="filter-chip">
          ${filter.value}
          <span class="remove" onclick="removeFilter('${filter.name}', '${filter.value}')">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
          </span>
        </div>
      `).join('');

      // Filter the table rows (Section Summary)
      filterDashboardData();
    }

    function removeFilter(name, value) {
      const cb = document.querySelector(`.filter-popover input[name="${name}"][value="${value}"]`);
      if (cb) {
        cb.checked = false;
        updateFilters();
      }
    }

    function filterDashboardData() {
      const activeSections = Array.from(document.querySelectorAll('input[name="section"]:checked')).map(cb => cb.value);
      const activeStatuses = Array.from(document.querySelectorAll('input[name="status"]:checked')).map(cb => cb.value);
      const searchTerm = inlineSearch.value.toLowerCase();

      const tableRows = document.querySelectorAll('.content-card table tbody tr');
      tableRows.forEach(row => {
        if (row.cells.length < 2) return;
        
        const sectionName = row.cells[0].textContent.trim();
        const matchesSection = activeSections.length === 0 || activeSections.includes(sectionName);
        const matchesSearch = sectionName.toLowerCase().includes(searchTerm);

        if (matchesSection && matchesSearch) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });

      // Also filter the chart groups
      const chartGroups = document.querySelectorAll('.chart-bar-group');
      chartGroups.forEach(group => {
        const label = group.querySelector('.chart-label').textContent.trim();
        const matchesSection = activeSections.length === 0 || activeSections.includes(label);
        const matchesSearch = label.toLowerCase().includes(searchTerm);

        if (matchesSection && matchesSearch) {
          group.style.display = 'flex';
        } else {
          group.style.display = 'none';
        }
      });
    }

    if (inlineSearch) {
      inlineSearch.addEventListener('input', filterDashboardData);
    }

    // Initialize badge state
    filterBadge.style.display = 'none';
  </script>
  <script src="../assets/js/app.js"></script>
</body>
</html>
