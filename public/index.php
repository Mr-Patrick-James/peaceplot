<?php
require_once __DIR__ . '/../config/auth.php';
requireLogin();

$user = getUserInfo();
$userInitials = getInitials($user['full_name']);

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$lots = [];
$sections = [];
$blocks = [];
$stats = [
    'total' => 0,
    'vacant' => 0,
    'occupied' => 0
];

if ($conn) {
    try {
        // Fetch stats
        $stats['total'] = $conn->query("SELECT COUNT(*) FROM cemetery_lots")->fetchColumn();
        $stats['vacant'] = $conn->query("SELECT COUNT(*) FROM cemetery_lots WHERE status = 'Vacant'")->fetchColumn();
        $stats['occupied'] = $conn->query("SELECT COUNT(*) FROM cemetery_lots WHERE status = 'Occupied'")->fetchColumn();

        // Fetch unique sections for filtering
        $sectionStmt = $conn->query("SELECT DISTINCT section FROM cemetery_lots WHERE section IS NOT NULL AND section != '' ORDER BY LENGTH(section), section");
        $sections = $sectionStmt->fetchAll(PDO::FETCH_COLUMN);

        // Fetch unique blocks for filtering
        $blockStmt = $conn->query("SELECT DISTINCT block FROM cemetery_lots WHERE block IS NOT NULL AND block != '' ORDER BY LENGTH(block), block");
        $blocks = $blockStmt->fetchAll(PDO::FETCH_COLUMN);

        $stmt = $conn->query("
            SELECT cl.*, 
                   (SELECT GROUP_CONCAT(full_name, ', ') 
                    FROM (SELECT full_name FROM deceased_records WHERE lot_id = cl.id AND is_archived = 0 ORDER BY created_at DESC, id DESC)
                   ) as deceased_name 
            FROM cemetery_lots cl 
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
  <title>PeacePlot Admin - Cemetery Lot Management</title>
  <link rel="stylesheet" href="../assets/css/styles.css" />
  <style>
    /* Specific styles for the new UI based on the image */
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
    .breadcrumbs a {
      color: #94a3b8;
      text-decoration: none;
    }
    .breadcrumbs .current {
      color: #1e293b;
      font-weight: 600;
    }
    .header-actions {
      display: flex;
      gap: 12px;
    }
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
    .btn-outline:hover {
      background: #f8fafc;
      border-color: #cbd5e1;
    }
    .btn-yellow {
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
    .btn-yellow:hover {
      background: #2563eb;
      transform: translateY(-1px);
    }
    .btn-assign {
      background: #eff6ff;
      color: #3b82f6;
      border: 1px solid #dbeafe;
    }
    .btn-assign:hover {
      background: #dbeafe;
      border-color: #bfdbfe;
    }

    .stats-row {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 24px;
      margin-bottom: 32px;
    }
    .stat-box {
      background: #fff;
      padding: 24px;
      border-radius: 16px;
      border-left: 5px solid #0e1f35; /* Sidebar background color */
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
    .stat-info .stat-label {
      font-size: 13px;
      font-weight: 600;
      color: #94a3b8;
      margin-bottom: 4px;
    }
    .stat-info .stat-number {
      font-size: 28px;
      font-weight: 700;
      color: #1e293b;
      line-height: 1;
    }
    .stat-info .stat-sub {
      font-size: 12px;
      margin-top: 8px;
    }
    .stat-sub.growth { color: #10b981; }
    .stat-sub.percent { color: #64748b; }

    .content-section {
      background: #fff;
      border-radius: 16px;
      padding: 0;
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
    .content-title-wrap .title {
      font-size: 18px;
      font-weight: 700;
      color: #1e293b;
      margin: 0 0 4px 0;
    }
    .content-title-wrap .subtitle {
      font-size: 13px;
      color: #94a3b8;
      margin: 0;
    }
    .filter-controls {
      display: flex;
      gap: 12px;
      align-items: center;
    }
    .search-wrapper {
      position: relative;
    }
    .search-wrapper svg {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #94a3b8;
    }
    .search-wrapper input {
      padding: 10px 16px 10px 40px;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      font-size: 14px;
      width: 280px;
      outline: none;
      transition: all 0.2s;
    }
    .search-wrapper input:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    .select-styled {
      padding: 10px 16px;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      font-size: 14px;
      background: #fff;
      color: #1e293b;
      outline: none;
      cursor: pointer;
    }
    .icon-btn-outline {
      width: 40px;
      height: 40px;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #64748b;
      cursor: pointer;
      background: #fff;
    }

    .table thead th {
      background: #f8fafc;
      color: #94a3b8;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      padding: 16px 32px;
    }
    .table tbody td {
      padding: 16px 32px;
      font-size: 14px;
      color: #475569;
      vertical-align: middle;
    }
    .lot-name-cell {
      display: flex;
      align-items: center;
      gap: 16px;
    }
    .lot-icon {
      width: 36px;
      height: 36px;
      background: #3b82f6;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
    }
    .lot-name-info .name {
      font-weight: 600;
      color: #1e293b;
      display: block;
    }
    .lot-name-info .sub {
      font-size: 12px;
      color: #94a3b8;
    }
    .status-badge {
      padding: 4px 12px;
      border-radius: 6px;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
    }
    .status-badge.active { background: #dcfce7; color: #10b981; }
    .status-badge.vacant { background: #eff6ff; color: #3b82f6; }
    .status-badge.maintenance { background: #f1f5f9; color: #64748b; }

    .pagination-footer {
      padding: 20px 32px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-top: 1px solid #f1f5f9;
    }
    .pagination-text {
      font-size: 13px;
      color: #94a3b8;
    }
    .pagination-controls {
      display: flex;
      gap: 8px;
    }
    .page-num {
      width: 32px;
      height: 32px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
    }
    .page-num.active {
      background: #3b82f6;
      color: #fff;
    }
    .page-arrow {
      width: 32px;
      height: 32px;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      color: #94a3b8;
      cursor: pointer;
    }
  </style>
</head>
<body>
  <div class="app">
    <!-- Sidebar included as usual -->
    <aside class="sidebar">
      <div class="brand">
        <div class="brand-title">PeacePlot Admin</div>
        <div class="brand-sub">Cemetery Management</div>
      </div>

      <nav class="nav">
        <a href="dashboard.php">
          <span class="icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 13h8V3H3v10z" />
              <path d="M13 21h8V11h-8v10z" />
              <path d="M13 3h8v6h-8V3z" />
              <path d="M3 21h8v-6H3v6z" />
            </svg>
          </span>
          <span>Dashboard</span>
        </a>

        <div class="dropdown active">
           <a href="#" class="dropdown-toggle active" onclick="this.parentElement.classList.toggle('active'); return false;">
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
            <a href="index.php" class="active"><span>Manage Lots</span></a>
            <a href="sections.php"><span>Manage Sections</span></a>
            <a href="blocks.php"><span>Manage Blocks</span></a>
          </div>
        </div>

        <a href="lot-availability.php">
          <span class="icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 2v20" />
              <path d="M2 12h20" />
              <path d="M4 4l16 16" />
            </svg>
          </span>
          <span>Lots</span>
        </a>

        <a href="cemetery-map.php">
          <span class="icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2V6z" />
              <path d="M9 4v14" />
              <path d="M15 6v14" />
            </svg>
          </span>
          <span>Cemetery Map</span>
        </a>

        <a href="map-editor.php">
          <span class="icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
            </svg>
          </span>
          <span>Map Editor</span>
        </a>

        <a href="burial-records.php">
          <span class="icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" />
              <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" />
              <path d="M8 6h8" />
              <path d="M8 10h8" />
            </svg>
          </span>
          <span>Burial Records</span>
        </a>

        <a href="reports.php">
          <span class="icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 3v18h18" />
              <path d="M7 14v4" />
              <path d="M11 10v8" />
              <path d="M15 6v12" />
              <path d="M19 12v6" />
            </svg>
          </span>
          <span>Reports</span>
        </a>

        <a href="history.php">
          <span class="icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          </span>
          <span>History</span>
        </a>
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
          <h1 class="title">Manage Lots</h1>
          <p class="subtitle">Manage all cemetery lots in the institution</p>
          <div class="breadcrumbs">
            <a href="dashboard.php">Dashboard</a>
            <span>&rsaquo;</span>
            <span class="current">Manage Lots</span>
          </div>
        </div>
        <div class="header-actions">
          <button class="btn-outline">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8v13H3V8M1 3h22v5H1zM10 12h4"/></svg>
            Archived
          </button>
          <button class="btn-outline">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
            Export
          </button>
          <button class="btn-yellow" data-action="add">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Add Lot
          </button>
        </div>
      </header>

      <div class="stats-row">
        <div class="stat-box">
          <div class="stat-icon-wrap">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
          </div>
          <div class="stat-info">
            <div class="stat-label">Total Cemetery Lots</div>
            <div class="stat-number"><?php echo $stats['total']; ?></div>
            <div class="stat-sub growth">+0 this month</div>
          </div>
        </div>
        <div class="stat-box">
          <div class="stat-icon-wrap">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          </div>
          <div class="stat-info">
            <div class="stat-label">Vacant</div>
            <div class="stat-number"><?php echo $stats['vacant']; ?></div>
            <div class="stat-sub percent"><?php echo $stats['total'] > 0 ? round(($stats['vacant']/$stats['total'])*100) : 0; ?>%</div>
          </div>
        </div>
        <div class="stat-box">
          <div class="stat-icon-wrap">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
          <div class="stat-info">
            <div class="stat-label">Occupied</div>
            <div class="stat-number"><?php echo $stats['occupied']; ?></div>
            <div class="stat-sub percent"><?php echo $stats['total'] > 0 ? round(($stats['occupied']/$stats['total'])*100) : 0; ?>%</div>
          </div>
        </div>
      </div>

      <section class="content-section">
        <div class="content-header">
          <div class="content-title-wrap">
            <h2 class="title">Cemetery Lot List</h2>
            <p class="subtitle">All cemetery lots and their details</p>
          </div>
          <div class="filter-controls">
            <div class="search-wrapper">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
              <input id="lotSearch" type="text" placeholder="Search lots...">
            </div>
            <select id="blockFilter" class="select-styled">
              <option value="">All Blocks</option>
              <?php foreach ($blocks as $block): ?>
                <option value="<?php echo htmlspecialchars($block); ?>"><?php echo htmlspecialchars($block); ?></option>
              <?php endforeach; ?>
            </select>
            <select id="sectionFilter" class="select-styled">
              <option value="">All Sections</option>
              <?php foreach ($sections as $section): ?>
                <option value="<?php echo htmlspecialchars($section); ?>"><?php echo htmlspecialchars($section); ?></option>
              <?php endforeach; ?>
            </select>
            <button class="icon-btn-outline">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
            </button>
          </div>
        </div>

        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th align="left">ID</th>
                <th align="left">Lot Details</th>
                <th align="left">Position</th>
                <th align="left">Occupant</th>
                <th align="left">Status</th>
                <th align="right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <!-- Data will be loaded via JS -->
              <tr>
                <td colspan="6" style="text-align:center; padding: 60px; color:#94a3b8;">
                  <div class="loading-spinner" style="display: inline-block; width: 24px; height: 24px; border: 2px solid rgba(0,0,0,0.1); border-radius: 50%; border-top-color: #fbbf24; animation: spin 1s ease-in-out infinite;"></div>
                  <div style="margin-top: 12px; font-size: 13px;">Loading data...</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="pagination-footer">
          <div class="pagination-text">
            Showing <span id="paginationRange">-</span> of <span id="paginationTotal">-</span> lots
          </div>
          <div class="pagination-controls">
            <!-- Pagination buttons will be rendered here -->
          </div>
        </div>
      </section>
    </main>
  </div>

  <style>
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
    /* Simple overrides for existing JS-generated elements */
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
        white-space: nowrap;
      }
     .pagination-btn:disabled {
       opacity: 0.5;
       cursor: not-allowed;
     }
     .pagination-btn.active {
          background: #3b82f6;
          color: #fff;
          border-color: #3b82f6;
        }
    .pagination-btn:hover:not(:disabled) {
      background: #f8fafc;
    }
    .pagination-ellipsis {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 32px;
      height: 32px;
      color: #94a3b8;
      font-size: 13px;
    }
  </style>

  <script src="../assets/js/app.js"></script>
  <script src="../assets/js/api.js"></script>
  <script src="../assets/js/cemetery-lots.js"></script>
</body>
</html>
