<?php
require_once __DIR__ . '/../config/auth.php';
requireLogin();

$user = getUserInfo();
$userInitials = getInitials($user['full_name']);

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$availableLots = [];
$sections = [];
$blocks = [];

if ($conn) {
    try {
        // Fetch unique sections for filtering
        $sectionStmt = $conn->query("SELECT DISTINCT section FROM cemetery_lots WHERE section IS NOT NULL AND section != '' ORDER BY LENGTH(section), section");
        $sections = $sectionStmt->fetchAll(PDO::FETCH_COLUMN);

        // Fetch unique blocks for filtering
        $blockStmt = $conn->query("SELECT DISTINCT block FROM cemetery_lots WHERE block IS NOT NULL AND block != '' ORDER BY LENGTH(block), block");
        $blocks = $blockStmt->fetchAll(PDO::FETCH_COLUMN);

        $lotsStmt = $conn->query("
            SELECT id, lot_number, section, block 
            FROM cemetery_lots 
            ORDER BY lot_number
        ");
        $availableLots = $lotsStmt->fetchAll();
        
    } catch (PDOException $e) {
        $error = $e->getMessage();
        $availableLots = [];
        $sections = [];
    }
} else {
    $availableLots = [];
    $sections = [];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>PeacePlot Admin - Burial Records</title>
  <link rel="stylesheet" href="../assets/css/styles.css" />
  <style>
    /* Modern Dashboard Header & UI */
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
      margin: 0 0 16px 0;
    }
    .breadcrumbs {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      color: #94a3b8;
    }
    .breadcrumbs a { color: #94a3b8; text-decoration: none; }
    .breadcrumbs .current { color: #1e293b; font-weight: 600; }
    .header-actions { display: flex; gap: 12px; }
    
    .btn-outline {
      padding: 10px 16px;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      background: #fff;
      color: #475569;
      font-size: 14px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      transition: all 0.2s;
    }
    .btn-outline:hover { background: #f8fafc; border-color: #cbd5e1; }
    .btn-primary-modern {
      background: #3b82f6;
      color: #fff;
      padding: 10px 20px;
      border-radius: 10px;
      border: none;
      font-weight: 700;
      font-size: 14px;
      display: flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
      transition: all 0.2s;
    }
    .btn-primary-modern:hover { background: #2563eb; transform: translateY(-1px); }

    /* Stats Row */
    .stats-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 24px;
      margin-bottom: 32px;
    }
    .stat-box {
      background: #fff;
      padding: 24px;
      border-radius: 16px;
      border-left: 5px solid #0e1f35;
      display: flex;
      align-items: center;
      gap: 20px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    }
    .stat-icon-wrap {
      width: 48px;
      height: 48px;
      background: #eff6ff;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #2563eb;
    }
    .stat-info .stat-label { font-size: 13px; font-weight: 600; color: #94a3b8; margin-bottom: 4px; }
    .stat-info .stat-number { font-size: 28px; font-weight: 700; color: #1e293b; line-height: 1; }
    .stat-info .stat-sub { font-size: 12px; margin-top: 8px; color: #64748b; }

    /* Filter Controls */
    .content-section {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.03);
      overflow: hidden;
    }
    .content-header {
      padding: 24px 32px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid #f1f5f9;
    }
    .filter-controls { display: flex; gap: 12px; align-items: center; }
    
    .search-wrapper { position: relative; }
    .search-wrapper svg { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
    .search-wrapper input {
      padding: 10px 16px 10px 40px;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      font-size: 14px;
      width: 280px;
      outline: none;
      transition: all 0.2s;
    }
    .search-wrapper input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }

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
      right: 0;
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
    .category-toggle svg { width: 16px; height: 16px; color: #94a3b8; transition: transform 0.2s; }
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
    .filter-option input[type="checkbox"] { width: 16px; height: 16px; border-radius: 4px; border: 2px solid #cbd5e1; cursor: pointer; }
    
    .active-filters-row {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin: 16px 32px 0 32px;
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
    .filter-chip .remove { cursor: pointer; display: flex; align-items: center; justify-content: center; opacity: 0.7; }
    .filter-chip .remove:hover { opacity: 1; }

    /* Date Range Wrapper */
    .date-range-wrapper {
      display: flex;
      align-items: center;
      gap: 8px;
      background: #fff;
      padding: 8px 12px;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
    }
    .date-range-wrapper label { font-size: 12px; font-weight: 600; color: #64748b; }
    .date-range-wrapper input { border: none; outline: none; font-size: 13px; color: #1e293b; background: transparent; }

    /* Table & Pagination */
    .table-wrap { width: 100%; overflow-x: auto; }
    .table thead th {
      background: #f8fafc;
      color: #94a3b8;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      padding: 16px 32px;
    }
    .table tbody td { padding: 16px 32px; font-size: 14px; color: #475569; vertical-align: middle; }
    
    .pagination-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 32px;
        background: #fff;
        border-top: 1px solid #f1f5f9;
    }
    .pagination-info { font-size: 13px; color: #94a3b8; }
    .pagination-controls { display: flex; gap: 8px; }
    .pagination-btn {
        min-width: 32px;
        height: 32px;
        padding: 0 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #94a3b8;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    .pagination-btn:hover:not(:disabled) { background: #f8fafc; color: #3b82f6; border-color: #3b82f6; }
    .pagination-btn.active { background: #3b82f6; color: #fff; border-color: #3b82f6; }
    .pagination-btn:disabled { opacity: 0.5; cursor: not-allowed; }
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
            <a href="blocks.php"><span>Manage Blocks</span></a>
          </div>
        </div>
        <a href="lot-availability.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20" /><path d="M2 12h20" /><path d="M4 4l16 16" /></svg></span><span>Lots</span></a>
        <a href="cemetery-map.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2V6z" /><path d="M9 4v14" /><path d="M15 6v14" /></svg></span><span>Cemetery Map</span></a>
        <a href="map-editor.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" /><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" /></svg></span><span>Map Editor</span></a>
        <a href="burial-records.php" class="active"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" /><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" /><path d="M8 6h8" /><path d="M8 10h8" /></svg></span><span>Burial Records</span></a>
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
          <h1 class="title">Burial Records</h1>
          <p class="subtitle">Manage deceased person records and burial information</p>
          <div class="breadcrumbs">
            <a href="dashboard.php">Dashboard</a>
            <span>&rsaquo;</span>
            <span class="current">Burial Records</span>
          </div>
        </div>
        <div class="header-actions">
          <button id="viewArchivedBtn" class="btn-outline">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8v13H3V8M1 3h22v5H1zM10 12h4"/></svg>
            <span id="viewArchivedText">View Archived</span>
          </button>
          <button class="btn-primary-modern" data-action="add">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Add Record
          </button>
        </div>
      </header>

      <?php
      // Quick stats for the top row
      $totalRecords = 0;
      $thisMonth = 0;
      if ($conn) {
          $totalRecords = $conn->query("SELECT COUNT(*) FROM deceased_records WHERE is_archived = 0")->fetchColumn();
          $thisMonth = $conn->query("SELECT COUNT(*) FROM deceased_records WHERE is_archived = 0 AND strftime('%Y-%m', created_at) = strftime('%Y-%m', 'now')")->fetchColumn();
      }
      ?>

      <div class="stats-row">
        <div class="stat-box">
          <div class="stat-icon-wrap">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" /><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" /></svg>
          </div>
          <div class="stat-info">
            <div class="stat-label">Total Records</div>
            <div class="stat-number"><?php echo $totalRecords; ?></div>
            <div class="stat-sub">+<?php echo $thisMonth; ?> this month</div>
          </div>
        </div>
        <div class="stat-box">
          <div class="stat-icon-wrap" style="background: #f0fdf4; color: #10b981;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </div>
          <div class="stat-info">
            <div class="stat-label">Active Records</div>
            <div class="stat-number"><?php echo $totalRecords; ?></div>
            <div class="stat-sub">Excluding archived</div>
          </div>
        </div>
        <div class="stat-box">
          <div class="stat-icon-wrap" style="background: #fff7ed; color: #f97316;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          </div>
          <div class="stat-info">
            <div class="stat-label">Recent Burials</div>
            <div class="stat-number"><?php echo $thisMonth; ?></div>
            <div class="stat-sub">Last 30 days</div>
          </div>
        </div>
      </div>

      <section class="content-section">
        <div class="content-header">
          <div class="content-title-wrap">
            <h2 class="title">Burial Records List</h2>
            <p class="subtitle">Manage and filter deceased person records</p>
          </div>
          <div class="filter-controls">
            <div class="date-range-wrapper">
              <label>From:</label>
              <input type="date" id="startDate">
              <label>To:</label>
              <input type="date" id="endDate">
            </div>
            <div class="search-wrapper">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
              <input id="recordSearch" type="text" placeholder="Search records...">
            </div>
            
            <div style="position: relative;">
              <button class="btn-filter" id="filterBtn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                Filters
                <span class="filter-badge" id="filterBadge" style="display: none;">0</span>
              </button>
              
              <div class="filter-popover" id="filterPopover">
                <div class="popover-header">
                  <h3>Filters</h3>
                  <a href="#" class="btn-save-view" onclick="clearAllFilters(); return false;">Clear all</a>
                </div>
                <div class="popover-body">
                  <!-- Blocks Category -->
                  <div class="filter-category active">
                    <button class="category-toggle" onclick="toggleCategory(this)">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                      Blocks
                    </button>
                    <div class="category-content">
                      <?php foreach ($blocks as $block): ?>
                        <label class="filter-option">
                          <input type="checkbox" name="block" value="<?php echo htmlspecialchars($block); ?>" onchange="updateFilters()">
                          <?php echo htmlspecialchars($block); ?>
                        </label>
                      <?php endforeach; ?>
                    </div>
                  </div>

                  <!-- Sections Category -->
                  <div class="filter-category">
                    <button class="category-toggle" onclick="toggleCategory(this)">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                      Sections
                    </button>
                    <div class="category-content">
                      <?php foreach ($sections as $section): ?>
                        <label class="filter-option">
                          <input type="checkbox" name="section" value="<?php echo htmlspecialchars($section); ?>" onchange="updateFilters()">
                          <?php echo htmlspecialchars($section); ?>
                        </label>
                      <?php endforeach; ?>
                    </div>
                  </div>
                  
                  <!-- Status Category -->
                  <div class="filter-category">
                    <button class="category-toggle" onclick="toggleCategory(this)">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                      Lot Status
                    </button>
                    <div class="category-content">
                      <label class="filter-option">
                        <input type="checkbox" name="status" value="Vacant" onchange="updateFilters()"> Vacant
                      </label>
                      <label class="filter-option">
                        <input type="checkbox" name="status" value="Occupied" onchange="updateFilters()"> Occupied
                      </label>
                      <label class="filter-option">
                        <input type="checkbox" name="status" value="Maintenance" onchange="updateFilters()"> Maintenance
                      </label>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="active-filters-row" id="activeFilters">
          <!-- Chips will be injected here -->
        </div>

        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th align="left">Full Name</th>
                <th align="left">Lot Details</th>
                <th align="left">Layer</th>
                <th align="left">Position</th>
                <th align="left">Dates</th>
                <th align="left">Age</th>
                <th align="right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td colspan="7" style="text-align:center; padding: 60px; color:#94a3b8;">
                  <div class="loading-spinner" style="display: inline-block; width: 24px; height: 24px; border: 2px solid rgba(0,0,0,0.1); border-radius: 50%; border-top-color: #3b82f6; animation: spin 1s ease-in-out infinite;"></div>
                  <div style="margin-top: 12px; font-size: 13px;">Loading records...</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="pagination-container"></div>
      </section>
    </main>
  </div>

  <script>
    const availableLots = <?php echo json_encode($availableLots ?: []); ?>;
    console.log('Available lots loaded:', availableLots);
  </script>
  <script src="../assets/js/app.js"></script>
  <script src="../assets/js/api.js"></script>
  <script src="../assets/js/burial-records.js"></script>
</body>
</html>
