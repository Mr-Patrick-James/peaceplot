<?php
require_once __DIR__ . '/../config/auth.php';
requireLogin();

$user = getUserInfo();
$userInitials = getInitials($user['full_name']);

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

require_once __DIR__ . '/includes/page_tracker.php';

$stats = [
    'total_lots' => 0,
    'vacant_lots' => 0,
    'occupied_lots' => 0,
    'total_burials' => 0,
    'sections' => [],
    'blocks' => [],
    'recent_burials' => []
];

if ($conn) {
    try {
        $stats['total_lots']    = $conn->query("SELECT COUNT(*) FROM cemetery_lots")->fetchColumn();
        $stats['vacant_lots']   = $conn->query("SELECT COUNT(*) FROM cemetery_lots WHERE status = 'Vacant'")->fetchColumn();
        $stats['occupied_lots'] = $conn->query("SELECT COUNT(*) FROM cemetery_lots WHERE status = 'Occupied'")->fetchColumn();
        $stats['total_burials'] = $conn->query("SELECT COUNT(*) FROM deceased_records")->fetchColumn();

        // Section-wise summary
        $stmt = $conn->query("
            SELECT s.name as section, b.name as block_name,
                COUNT(cl.id) as total,
                SUM(CASE WHEN cl.status = 'Occupied' THEN 1 ELSE 0 END) as occupied,
                SUM(CASE WHEN cl.status = 'Vacant' THEN 1 ELSE 0 END) as vacant
            FROM sections s
            LEFT JOIN blocks b ON s.block_id = b.id
            LEFT JOIN cemetery_lots cl ON s.id = cl.section_id
            GROUP BY s.id
            ORDER BY b.name, s.name
        ");
        $stats['sections'] = $stmt->fetchAll();

        // Block-wise summary (sections count + lots count)
        $stmt = $conn->query("
            SELECT b.name as block,
                COUNT(DISTINCT s.id) as section_count,
                COUNT(cl.id) as total_lots,
                SUM(CASE WHEN cl.status = 'Occupied' THEN 1 ELSE 0 END) as occupied,
                SUM(CASE WHEN cl.status = 'Vacant' THEN 1 ELSE 0 END) as vacant
            FROM blocks b
            LEFT JOIN sections s ON s.block_id = b.id
            LEFT JOIN cemetery_lots cl ON cl.section_id = s.id
            GROUP BY b.id
            ORDER BY b.name
        ");
        $stats['blocks'] = $stmt->fetchAll();

        // All burials (no limit)
        $stmt = $conn->query("
            SELECT dr.*, cl.lot_number, s.name as section, b.name as block
            FROM deceased_records dr
            LEFT JOIN cemetery_lots cl ON dr.lot_id = cl.id
            LEFT JOIN sections s ON cl.section_id = s.id
            LEFT JOIN blocks b ON s.block_id = b.id
            WHERE dr.is_archived = 0
            ORDER BY dr.date_of_burial DESC
        ");
        $stats['recent_burials'] = $stmt->fetchAll();

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
  <title>PeacePlot Admin - Reports</title>
  <link rel="stylesheet" href="../assets/css/styles.css" />
  <style>
    .dashboard-header {
      background: #fff;
      padding: 24px 32px;
      border-radius: 16px;
      margin-bottom: 24px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.03);
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: relative; /* Added for absolute search positioning */
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
    .breadcrumbs {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      color: #94a3b8;
      margin-bottom: 8px;
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
            <a href="blocks.php"><span>Manage Blocks</span></a>
            <a href="sections.php"><span>Manage Sections</span></a>
            <a href="lot-availability.php"><span>Lots</span></a>
            <a href="map-editor.php"><span>Map Editor</span></a>
          </div>
        </div>
        <a href="cemetery-map.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2V6z" /><path d="M9 4v14" /><path d="M15 6v14" /></svg></span><span>Cemetery Map</span></a>
        <a href="burial-records.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" /><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" /><path d="M8 6h8" /><path d="M8 10h8" /></svg></span><span>Burial Records</span></a>
        <a href="reports.php" class="active"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18" /><path d="M7 14v4" /><path d="M11 10v8" /><path d="M15 6v12" /><path d="M19 12v6" /></svg></span><span>Reports</span></a>
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
          <div class="breadcrumbs">
            <a href="dashboard.php">Dashboard</a>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
            <span class="current">Reports</span>
          </div>
          <h1 class="title">Reports</h1>
          <p class="subtitle">Comprehensive cemetery statistics and data exports</p>
        </div>

        <div class="header-search">
          <div class="universal-search-wrapper">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <input type="text" class="universal-search-input" id="universalSearch" placeholder="Global Search lots, deceased names...">
          </div>
          <div class="search-results-dropdown" id="searchResults">
            <!-- Results will be injected here -->
          </div>
        </div>

        <div class="header-actions">
          <button class="btn-outline" onclick="printReport('all')">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7" /><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" /><path d="M6 14h12v8H6z" /></svg>
            Print Full Report
          </button>
        </div>
      </header>

      <?php if (isset($error)): ?>
        <div class="card" style="padding:20px; color:#ef4444;">
          Error loading data: <?php echo htmlspecialchars($error); ?>
        </div>
      <?php else: ?>

      <div class="reports-grid">
        <div class="report-card report-blue">
          <div class="report-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
              <path d="M14 2v6h6" />
              <path d="M16 13H8" />
              <path d="M16 17H8" />
              <path d="M10 9H8" />
            </svg>
          </div>
          <div class="report-number"><?php echo $stats['total_lots']; ?></div>
          <div class="report-title">Total Cemetery Lots Report</div>
          <div class="report-desc">Complete overview of all cemetery lots including status and location</div>
          <div class="report-actions">
            <a href="index.php" class="report-btn report-btn-view">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                <circle cx="12" cy="12" r="3" />
              </svg>
              <span>View Report</span>
            </a>
            <button class="report-btn report-btn-print" onclick="printReport('all_lots')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/></svg>
              <span>Print</span>
            </button>
            <button class="report-btn report-btn-export" onclick="exportToCSV('all_lots')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              <span>Export CSV</span>
            </button>
          </div>
        </div>

        <div class="report-card report-green">
          <div class="report-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>
          </div>
          <div class="report-number"><?php echo $stats['vacant_lots']; ?></div>
          <div class="report-title">Vacant Lots Report</div>
          <div class="report-desc">Detailed list of all available cemetery lots for new burials</div>
          <div class="report-actions">
            <a href="lot-availability.php?status=Vacant" class="report-btn report-btn-view">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <span>View Report</span>
            </a>
            <button class="report-btn report-btn-print" onclick="printReport('vacant_lots')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/></svg>
              <span>Print</span>
            </button>
            <button class="report-btn report-btn-export" onclick="exportToCSV('vacant_lots')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              <span>Export CSV</span>
            </button>
          </div>
        </div>

        <div class="report-card report-orange">
          <div class="report-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>
          </div>
          <div class="report-number"><?php echo $stats['occupied_lots']; ?></div>
          <div class="report-title">Occupied Lots Report</div>
          <div class="report-desc">Comprehensive record of all occupied lots with burial information</div>
          <div class="report-actions">
            <a href="lot-availability.php?status=Occupied" class="report-btn report-btn-view">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <span>View Report</span>
            </a>
            <button class="report-btn report-btn-print" onclick="printReport('occupied_lots')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/></svg>
              <span>Print</span>
            </button>
            <button class="report-btn report-btn-export" onclick="exportToCSV('occupied_lots')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              <span>Export CSV</span>
            </button>
          </div>
        </div>

        <div class="report-card" style="border-top-color: #f43f5e;">
          <div class="report-icon" style="color: #f43f5e; background: #fff1f2;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/><path d="M8 6h8"/><path d="M8 10h8"/></svg>
          </div>
          <div class="report-number"><?php echo $stats['total_burials']; ?></div>
          <div class="report-title">Total Burial Records</div>
          <div class="report-desc">Complete database of all registered deceased individuals</div>
          <div class="report-actions">
            <a href="burial-records.php" class="report-btn report-btn-view">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <span>View Report</span>
            </a>
            <button class="report-btn report-btn-print" onclick="printReport('deceased_records')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/></svg>
              <span>Print</span>
            </button>
            <button class="report-btn report-btn-export" onclick="exportToCSV('deceased_records')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              <span>Export CSV</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Block Report Table -->
      <section class="card" style="margin-top:24px">
        <div class="card-head" style="padding:16px 18px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
          <div>
            <h2 class="card-title">Block Summary Report</h2>
            <p class="card-sub" id="blockCount">All <?php echo count($stats['blocks']); ?> blocks</p>
          </div>
          <div style="display:flex; gap:10px; align-items:center;">
            <div style="position:relative;">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <input type="text" id="blockSearch" placeholder="Search block..." oninput="applyBlockFilters()"
                style="padding:8px 12px 8px 34px; border:1px solid #e2e8f0; border-radius:8px; font-size:13px; outline:none; width:180px;">
            </div>
            <div style="position:relative;">
              <button id="blockFilterBtn" onclick="togglePopover('blockFilterPopover','blockFilterBtn')" style="padding:8px 16px; background:#2f6df6; color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:6px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                Filters
                <span id="blockFilterBadge" style="display:none; background:#fff; color:#2f6df6; border-radius:10px; padding:1px 6px; font-size:11px; font-weight:700;"></span>
              </button>
              <div id="blockFilterPopover" style="display:none; position:absolute; right:0; top:calc(100% + 8px); background:#fff; border:1px solid #e2e8f0; border-radius:16px; box-shadow:0 10px 40px rgba(0,0,0,0.12); z-index:500; width:280px; padding:20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                  <span style="font-size:15px; font-weight:700; color:#1e293b;">Filters</span>
                  <button onclick="clearBlockFilters()" style="font-size:13px; color:#ef4444; background:none; border:none; cursor:pointer; font-weight:600;">Clear all</button>
                </div>
                <div style="font-size:13px; font-weight:700; color:#1e293b; margin-bottom:8px;">Occupancy Status</div>
                <div style="display:flex; flex-direction:column; gap:8px;">
                  <label style="display:flex; align-items:center; gap:8px; font-size:13px; color:#475569; cursor:pointer;">
                    <input type="checkbox" id="blockHasOccupied" onchange="applyBlockFilters()" style="width:16px;height:16px;cursor:pointer;">
                    Has Occupied Lots
                  </label>
                  <label style="display:flex; align-items:center; gap:8px; font-size:13px; color:#475569; cursor:pointer;">
                    <input type="checkbox" id="blockHasVacant" onchange="applyBlockFilters()" style="width:16px;height:16px;cursor:pointer;">
                    Has Vacant Lots
                  </label>
                </div>
              </div>
            </div>
            <button class="report-btn report-btn-print" onclick="printReport('blocks')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/></svg>
              Print
            </button>
          </div>
        </div>
        <div class="table-wrap">
          <table class="table" id="blockTable">
            <thead>
              <tr>
                <th align="left">Block</th>
                <th align="right">Sections</th>
                <th align="right">Total Lots</th>
                <th align="right">Occupied</th>
                <th align="right">Vacant</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($stats['blocks'])): ?>
                <tr><td colspan="5" style="text-align:center; color:#6b7280;">No blocks found</td></tr>
              <?php else: ?>
                <?php foreach ($stats['blocks'] as $block): ?>
                  <tr data-block="<?php echo strtolower(htmlspecialchars($block['block'])); ?>"
                      data-occupied="<?php echo $block['occupied']; ?>"
                      data-vacant="<?php echo $block['vacant']; ?>">
                    <td><?php echo htmlspecialchars($block['block']); ?></td>
                    <td align="right"><?php echo $block['section_count']; ?></td>
                    <td align="right"><?php echo $block['total_lots']; ?></td>
                    <td align="right"><span class="table-number occupied-color"><?php echo $block['occupied']; ?></span></td>
                    <td align="right"><span class="table-number vacant-color"><?php echo $block['vacant']; ?></span></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Section Report Table -->
      <section class="card" style="margin-top:24px">
        <div class="card-head" style="padding:16px 18px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
          <div>
            <h2 class="card-title">Section Summary Report</h2>
            <p class="card-sub" id="sectionCount">All <?php echo count($stats['sections']); ?> sections</p>
          </div>
          <div style="display:flex; gap:10px; align-items:center;">
            <div style="position:relative;">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <input type="text" id="sectionSearch" placeholder="Search section..." oninput="applySectionFilters()"
                style="padding:8px 12px 8px 34px; border:1px solid #e2e8f0; border-radius:8px; font-size:13px; outline:none; width:180px;">
            </div>
            <div style="position:relative;">
              <button id="sectionFilterBtn" onclick="togglePopover('sectionFilterPopover','sectionFilterBtn')" style="padding:8px 16px; background:#2f6df6; color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:6px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                Filters
                <span id="sectionFilterBadge" style="display:none; background:#fff; color:#2f6df6; border-radius:10px; padding:1px 6px; font-size:11px; font-weight:700;"></span>
              </button>
              <div id="sectionFilterPopover" style="display:none; position:absolute; right:0; top:calc(100% + 8px); background:#fff; border:1px solid #e2e8f0; border-radius:16px; box-shadow:0 10px 40px rgba(0,0,0,0.12); z-index:500; width:320px; padding:20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                  <span style="font-size:15px; font-weight:700; color:#1e293b;">Filters</span>
                  <button onclick="clearSectionFilters()" style="font-size:13px; color:#ef4444; background:none; border:none; cursor:pointer; font-weight:600;">Clear all</button>
                </div>
                <div style="font-size:13px; font-weight:700; color:#1e293b; margin-bottom:8px;">Blocks</div>
                <div style="display:flex; flex-direction:column; gap:8px; margin-bottom:16px; max-height:140px; overflow-y:auto;">
                  <?php foreach ($stats['blocks'] as $b): ?>
                    <label style="display:flex; align-items:center; gap:8px; font-size:13px; color:#475569; cursor:pointer;">
                      <input type="checkbox" class="sec-block-cb" value="<?php echo strtolower(htmlspecialchars($b['block'])); ?>" onchange="applySectionFilters()" style="width:16px;height:16px;cursor:pointer;">
                      <?php echo htmlspecialchars($b['block']); ?>
                    </label>
                  <?php endforeach; ?>
                </div>
                <div style="font-size:13px; font-weight:700; color:#1e293b; margin-bottom:8px;">Occupancy Status</div>
                <div style="display:flex; flex-direction:column; gap:8px;">
                  <label style="display:flex; align-items:center; gap:8px; font-size:13px; color:#475569; cursor:pointer;">
                    <input type="checkbox" id="secHasOccupied" onchange="applySectionFilters()" style="width:16px;height:16px;cursor:pointer;">
                    Has Occupied Lots
                  </label>
                  <label style="display:flex; align-items:center; gap:8px; font-size:13px; color:#475569; cursor:pointer;">
                    <input type="checkbox" id="secHasVacant" onchange="applySectionFilters()" style="width:16px;height:16px;cursor:pointer;">
                    Has Vacant Lots
                  </label>
                </div>
              </div>
            </div>
            <button class="report-btn report-btn-print" onclick="printReport('sections')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/></svg>
              Print
            </button>
          </div>
        </div>
        <div class="table-wrap">
          <table class="table" id="sectionTable">
            <thead>
              <tr>
                <th align="left">Section</th>
                <th align="left">Block</th>
                <th align="right">Total Lots</th>
                <th align="right">Occupied</th>
                <th align="right">Vacant</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($stats['sections'])): ?>
                <tr><td colspan="5" style="text-align:center; color:#6b7280;">No sections found</td></tr>
              <?php else: ?>
                <?php foreach ($stats['sections'] as $section): ?>
                  <tr data-section="<?php echo strtolower(htmlspecialchars($section['section'])); ?>"
                      data-block="<?php echo strtolower(htmlspecialchars($section['block_name'] ?? '')); ?>"
                      data-occupied="<?php echo $section['occupied']; ?>"
                      data-vacant="<?php echo $section['vacant']; ?>">
                    <td><?php echo htmlspecialchars($section['section']); ?></td>
                    <td><?php echo htmlspecialchars($section['block_name'] ?? '—'); ?></td>
                    <td align="right"><?php echo $section['total']; ?></td>
                    <td align="right"><span class="table-number occupied-color"><?php echo $section['occupied']; ?></span></td>
                    <td align="right"><span class="table-number vacant-color"><?php echo $section['vacant']; ?></span></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Burial Records Table with Filter Panel -->
      <section class="card" style="margin-top:24px">
        <div class="card-head" style="padding:16px 18px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
          <div>
            <h2 class="card-title">Burial Records</h2>
            <p class="card-sub" id="burialCount">All <?php echo count($stats['recent_burials']); ?> records</p>
          </div>
          <div style="display:flex; gap:10px; align-items:center;">
            <div style="position:relative;">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <input type="text" id="burialSearch" placeholder="Search name..." oninput="applyBurialFilters()"
                style="padding:8px 12px 8px 34px; border:1px solid #e2e8f0; border-radius:8px; font-size:13px; outline:none; width:200px;">
            </div>
            <div style="position:relative;">
              <button id="burialFilterBtn" onclick="togglePopover('burialFilterPopover','burialFilterBtn')" style="padding:8px 16px; background:#2f6df6; color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:6px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                Filters
                <span id="burialFilterBadge" style="display:none; background:#fff; color:#2f6df6; border-radius:10px; padding:1px 6px; font-size:11px; font-weight:700;"></span>
              </button>

              <!-- Filter Popover -->
              <div id="burialFilterPopover" style="display:none; position:absolute; right:0; top:calc(100% + 8px); background:#fff; border:1px solid #e2e8f0; border-radius:16px; box-shadow:0 10px 40px rgba(0,0,0,0.12); z-index:500; width:520px; padding:20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                  <span style="font-size:15px; font-weight:700; color:#1e293b;">Filters</span>
                  <div style="display:flex; gap:12px;">
                    <button onclick="clearBurialFilters()" style="font-size:13px; color:#ef4444; background:none; border:none; cursor:pointer; font-weight:600;">Clear all</button>
                  </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                  <!-- Blocks -->
                  <div>
                    <div style="font-size:13px; font-weight:700; color:#1e293b; margin-bottom:10px; display:flex; align-items:center; gap:6px;">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                      Blocks
                    </div>
                    <div style="display:flex; flex-direction:column; gap:8px; max-height:160px; overflow-y:auto;">
                      <?php foreach ($stats['blocks'] as $b): ?>
                        <label style="display:flex; align-items:center; gap:8px; font-size:13px; color:#475569; cursor:pointer;">
                          <input type="checkbox" class="burial-block-cb" value="<?php echo strtolower(htmlspecialchars($b['block'])); ?>" onchange="applyBurialFilters()" style="width:16px;height:16px;border-radius:4px;cursor:pointer;">
                          <?php echo htmlspecialchars($b['block']); ?>
                        </label>
                      <?php endforeach; ?>
                    </div>
                  </div>
                  <!-- Sections -->
                  <div>
                    <div style="font-size:13px; font-weight:700; color:#1e293b; margin-bottom:10px; display:flex; align-items:center; gap:6px;">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                      Sections
                    </div>
                    <div style="display:flex; flex-direction:column; gap:8px; max-height:160px; overflow-y:auto;">
                      <?php foreach ($stats['sections'] as $s): ?>
                        <label style="display:flex; align-items:center; gap:8px; font-size:13px; color:#475569; cursor:pointer;">
                          <input type="checkbox" class="burial-section-cb" value="<?php echo strtolower(htmlspecialchars($s['section'])); ?>" onchange="applyBurialFilters()" style="width:16px;height:16px;border-radius:4px;cursor:pointer;">
                          <?php echo htmlspecialchars($s['section']); ?>
                        </label>
                      <?php endforeach; ?>
                    </div>
                  </div>
                  <!-- Date Range -->
                  <div style="grid-column:1/-1;">
                    <div style="font-size:13px; font-weight:700; color:#1e293b; margin-bottom:10px; display:flex; align-items:center; gap:6px;">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                      Date of Burial Range
                    </div>
                    <div style="display:flex; gap:10px; align-items:center;">
                      <input type="date" id="burialDateFrom" onchange="applyBurialFilters()" style="padding:8px 10px; border:1px solid #e2e8f0; border-radius:8px; font-size:13px; outline:none; flex:1;">
                      <span style="color:#94a3b8; font-size:13px;">to</span>
                      <input type="date" id="burialDateTo" onchange="applyBurialFilters()" style="padding:8px 10px; border:1px solid #e2e8f0; border-radius:8px; font-size:13px; outline:none; flex:1;">
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <button class="report-btn report-btn-print" onclick="printReport('deceased_records')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/></svg>
              Print
            </button>
            <button class="report-btn report-btn-export" onclick="exportToCSV('deceased_records')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              Export CSV
            </button>
          </div>
        </div>
        <div class="table-wrap">
          <table class="table" id="burialTable">
            <thead>
              <tr>
                <th align="left">Full Name</th>
                <th align="left">Lot</th>
                <th align="left">Section</th>
                <th align="left">Block</th>
                <th align="left">Date of Burial</th>
                <th align="left">Age</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($stats['recent_burials'])): ?>
                <tr><td colspan="6" style="text-align:center; color:#6b7280;">No burial records found</td></tr>
              <?php else: ?>
                <?php foreach ($stats['recent_burials'] as $burial): ?>
                  <tr data-name="<?php echo strtolower(htmlspecialchars($burial['full_name'])); ?>"
                      data-section="<?php echo strtolower(htmlspecialchars($burial['section'] ?? '')); ?>"
                      data-block="<?php echo strtolower(htmlspecialchars($burial['block'] ?? '')); ?>"
                      data-date="<?php echo $burial['date_of_burial'] ?? ''; ?>">
                    <td><?php echo htmlspecialchars($burial['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($burial['lot_number'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($burial['section'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($burial['block'] ?? '—'); ?></td>
                    <td><?php echo $burial['date_of_burial'] ? date('M d, Y', strtotime($burial['date_of_burial'])) : '—'; ?></td>
                    <td><?php echo $burial['age'] ?: '—'; ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <?php endif; ?>
    </main>
  </div>

  <script>
    function exportToCSV(reportType) {
      window.location.href = '../api/export_csv.php?type=' + reportType;
    }

    // ── Popover toggle ─────────────────────────────────────────
    const POPOVERS = ['blockFilterPopover','sectionFilterPopover','burialFilterPopover'];

    function togglePopover(popId) {
      const isOpen = document.getElementById(popId).style.display === 'block';
      POPOVERS.forEach(id => document.getElementById(id).style.display = 'none');
      if (!isOpen) document.getElementById(popId).style.display = 'block';
    }

    document.addEventListener('click', e => {
      POPOVERS.forEach(popId => {
        const pop = document.getElementById(popId);
        if (!pop || pop.style.display !== 'block') return;
        const btnId = popId.replace('FilterPopover','FilterBtn');
        const btn   = document.getElementById(btnId);
        if (!pop.contains(e.target) && (!btn || !btn.contains(e.target))) {
          pop.style.display = 'none';
        }
      });
    });

    // ── Block filters ──────────────────────────────────────────
    function applyBlockFilters() {
      const search      = (document.getElementById('blockSearch').value || '').toLowerCase().trim();
      const hasOccupied = document.getElementById('blockHasOccupied').checked;
      const hasVacant   = document.getElementById('blockHasVacant').checked;
      let visible = 0;
      document.querySelectorAll('#blockTable tbody tr').forEach(row => {
        const name     = (row.dataset.block || '').toLowerCase();
        const occupied = parseInt(row.dataset.occupied || 0);
        const vacant   = parseInt(row.dataset.vacant   || 0);
        let show = !search || name.includes(search);
        if (hasOccupied) show = show && occupied > 0;
        if (hasVacant)   show = show && vacant   > 0;
        row.style.display = show ? '' : 'none';
        if (show) visible++;
      });
      document.getElementById('blockCount').textContent = `Showing ${visible} block${visible !== 1 ? 's' : ''}`;
      const active = (hasOccupied ? 1 : 0) + (hasVacant ? 1 : 0);
      const badge = document.getElementById('blockFilterBadge');
      badge.style.display = active > 0 ? 'inline' : 'none';
      badge.textContent = active;
    }
    function clearBlockFilters() {
      document.getElementById('blockHasOccupied').checked = false;
      document.getElementById('blockHasVacant').checked   = false;
      document.getElementById('blockSearch').value = '';
      applyBlockFilters();
    }

    // ── Section filters ────────────────────────────────────────
    function applySectionFilters() {
      const search      = (document.getElementById('sectionSearch').value || '').toLowerCase().trim();
      const blocks      = [...document.querySelectorAll('.sec-block-cb:checked')].map(c => c.value);
      const hasOccupied = document.getElementById('secHasOccupied').checked;
      const hasVacant   = document.getElementById('secHasVacant').checked;
      let visible = 0;
      document.querySelectorAll('#sectionTable tbody tr').forEach(row => {
        const sec      = (row.dataset.section || '').toLowerCase();
        const blk      = (row.dataset.block   || '').toLowerCase();
        const occupied = parseInt(row.dataset.occupied || 0);
        const vacant   = parseInt(row.dataset.vacant   || 0);
        let show = !search || sec.includes(search) || blk.includes(search);
        if (blocks.length)   show = show && blocks.includes(blk);
        if (hasOccupied)     show = show && occupied > 0;
        if (hasVacant)       show = show && vacant   > 0;
        row.style.display = show ? '' : 'none';
        if (show) visible++;
      });
      document.getElementById('sectionCount').textContent = `Showing ${visible} section${visible !== 1 ? 's' : ''}`;
      const active = blocks.length + (hasOccupied ? 1 : 0) + (hasVacant ? 1 : 0);
      const badge = document.getElementById('sectionFilterBadge');
      badge.style.display = active > 0 ? 'inline' : 'none';
      badge.textContent = active;
    }
    function clearSectionFilters() {
      document.querySelectorAll('.sec-block-cb').forEach(cb => cb.checked = false);
      document.getElementById('secHasOccupied').checked = false;
      document.getElementById('secHasVacant').checked   = false;
      document.getElementById('sectionSearch').value = '';
      applySectionFilters();
    }

    // ── Burial filters ─────────────────────────────────────────
    function applyBurialFilters() {
      const search   = (document.getElementById('burialSearch').value || '').toLowerCase().trim();
      const dateFrom = document.getElementById('burialDateFrom').value;
      const dateTo   = document.getElementById('burialDateTo').value;
      const blocks   = [...document.querySelectorAll('.burial-block-cb:checked')].map(c => c.value);
      const sections = [...document.querySelectorAll('.burial-section-cb:checked')].map(c => c.value);
      let visible = 0;
      document.querySelectorAll('#burialTable tbody tr').forEach(row => {
        const name = (row.dataset.name    || '').toLowerCase();
        const sec  = (row.dataset.section || '').toLowerCase();
        const blk  = (row.dataset.block   || '').toLowerCase();
        const date =  row.dataset.date    || '';
        let show = !search || name.includes(search);
        if (blocks.length)   show = show && blocks.includes(blk);
        if (sections.length) show = show && sections.includes(sec);
        if (dateFrom)        show = show && date >= dateFrom;
        if (dateTo)          show = show && date <= dateTo;
        row.style.display = show ? '' : 'none';
        if (show) visible++;
      });
      document.getElementById('burialCount').textContent = `Showing ${visible} record${visible !== 1 ? 's' : ''}`;
      const activeCount = blocks.length + sections.length + (dateFrom || dateTo ? 1 : 0);
      const badge = document.getElementById('burialFilterBadge');
      badge.style.display = activeCount > 0 ? 'inline' : 'none';
      badge.textContent = activeCount;
    }
    function clearBurialFilters() {
      document.querySelectorAll('.burial-block-cb, .burial-section-cb').forEach(cb => cb.checked = false);
      document.getElementById('burialDateFrom').value = '';
      document.getElementById('burialDateTo').value   = '';
      document.getElementById('burialSearch').value   = '';
      applyBurialFilters();
    }

    // Report data from PHP
    const reportData = {
      stats: {
        total_lots: <?php echo $stats['total_lots']; ?>,
        vacant_lots: <?php echo $stats['vacant_lots']; ?>,
        occupied_lots: <?php echo $stats['occupied_lots']; ?>,
        total_burials: <?php echo $stats['total_burials']; ?>
      },
      sections: <?php echo json_encode($stats['sections']); ?>,
      blocks: <?php echo json_encode($stats['blocks']); ?>,
      recent_burials: <?php echo json_encode($stats['recent_burials']); ?>
    };

    const reportTitles = {
      all_lots:         'Total Cemetery Lots Report',
      vacant_lots:      'Vacant Lots Report',
      occupied_lots:    'Occupied Lots Report',
      deceased_records: 'Total Burial Records Report',
      blocks:           'Block Summary Report',
      sections:         'Section Summary Report',
      all:              'Full Cemetery Report'
    };

    function printReport(type) {
      const title = reportTitles[type] || 'Cemetery Report';
      const now = new Date().toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });

      let bodyHtml = '';

      // Summary stats block
      if (type === 'all' || type === 'all_lots') {
        bodyHtml += `
          <div class="stat-row">
            <div class="stat-box"><div class="stat-num">${reportData.stats.total_lots}</div><div class="stat-lbl">Total Lots</div></div>
            <div class="stat-box"><div class="stat-num" style="color:#22c55e">${reportData.stats.vacant_lots}</div><div class="stat-lbl">Vacant</div></div>
            <div class="stat-box"><div class="stat-num" style="color:#f97316">${reportData.stats.occupied_lots}</div><div class="stat-lbl">Occupied</div></div>
            <div class="stat-box"><div class="stat-num" style="color:#3b82f6">${reportData.stats.total_burials}</div><div class="stat-lbl">Burial Records</div></div>
          </div>`;
      }

      // Section summary table
      if (type === 'all' || type === 'all_lots' || type === 'vacant_lots' || type === 'occupied_lots') {
        bodyHtml += `<h3>Section-wise Summary</h3>
          <table>
            <thead><tr><th>Section</th><th>Total Lots</th><th>Occupied</th><th>Vacant</th></tr></thead>
            <tbody>`;
        reportData.sections.forEach(s => {
          if (type === 'vacant_lots' && s.vacant == 0) return;
          if (type === 'occupied_lots' && s.occupied == 0) return;
          bodyHtml += `<tr><td>${s.section}</td><td>${s.total}</td><td>${s.occupied}</td><td>${s.vacant}</td></tr>`;
        });
        bodyHtml += `</tbody></table>`;
      }

      // Block summary table
      if (type === 'blocks' || type === 'all') {
        bodyHtml += `<h3>Block Summary</h3>
          <table>
            <thead><tr><th>Block</th><th>Sections</th><th>Total Lots</th><th>Occupied</th><th>Vacant</th></tr></thead>
            <tbody>`;
        reportData.blocks.forEach(b => {
          bodyHtml += `<tr><td>${b.block}</td><td>${b.section_count}</td><td>${b.total_lots}</td><td>${b.occupied}</td><td>${b.vacant}</td></tr>`;
        });
        bodyHtml += `</tbody></table>`;
      }

      // Sections with block reference
      if (type === 'sections' || type === 'all') {
        bodyHtml += `<h3>Section Summary</h3>
          <table>
            <thead><tr><th>Section</th><th>Block</th><th>Total Lots</th><th>Occupied</th><th>Vacant</th></tr></thead>
            <tbody>`;
        reportData.sections.forEach(s => {
          bodyHtml += `<tr><td>${s.section}</td><td>${s.block_name || '—'}</td><td>${s.total}</td><td>${s.occupied}</td><td>${s.vacant}</td></tr>`;
        });
        bodyHtml += `</tbody></table>`;
      }

      // Recent burials / deceased records table
      if (type === 'all' || type === 'deceased_records') {
        bodyHtml += `<h3>Burial Records</h3>
          <table>
            <thead><tr><th>Full Name</th><th>Lot</th><th>Section</th><th>Date of Burial</th><th>Age</th></tr></thead>
            <tbody>`;
        reportData.recent_burials.forEach(b => {
          const burialDate = b.date_of_burial ? new Date(b.date_of_burial).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : '—';
          bodyHtml += `<tr>
            <td>${b.full_name}</td>
            <td>${b.lot_number || '—'}</td>
            <td>${b.section || '—'}</td>
            <td>${burialDate}</td>
            <td>${b.age || '—'}</td>
          </tr>`;
        });
        bodyHtml += `</tbody></table>`;
      }

      const win = window.open('', '_blank', 'width=900,height=700');
      win.document.write(`<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>${title}</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 13px; color: #1e293b; padding: 40px; }
    .header { text-align: center; border-bottom: 2px solid #1e293b; padding-bottom: 16px; margin-bottom: 24px; }
    .header h1 { font-size: 22px; font-weight: 700; }
    .header .org { font-size: 15px; color: #475569; margin-top: 4px; }
    .header .meta { font-size: 12px; color: #94a3b8; margin-top: 6px; }
    .stat-row { display: flex; gap: 16px; margin-bottom: 28px; }
    .stat-box { flex: 1; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; text-align: center; }
    .stat-num { font-size: 28px; font-weight: 700; }
    .stat-lbl { font-size: 11px; color: #64748b; text-transform: uppercase; margin-top: 4px; }
    h3 { font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; margin: 24px 0 10px; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #f8fafc; text-align: left; padding: 9px 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; border-bottom: 2px solid #e2e8f0; }
    td { padding: 9px 12px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
    tr:last-child td { border-bottom: none; }
    .footer { margin-top: 40px; text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 12px; }
    @media print {
      body { padding: 20px; }
      button { display: none; }
    }
  </style>
</head>
<body>
  <div class="header">
    <div class="org">Barcenaga Holy Spirit Parish</div>
    <h1>${title}</h1>
    <div class="meta">Generated on ${now} &nbsp;|&nbsp; PeacePlot Cemetery Management System</div>
  </div>
  ${bodyHtml}
  <div class="footer">© 2025 Barcenaga Holy Spirit Parish — PeacePlot Cemetery Management System</div>
  <script>window.onload = function(){ window.print(); }<\/script>
</body>
</html>`);
      win.document.close();
    }
  </script>
  <script src="../assets/js/app.js"></script>
</body>
</html>
