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

if ($conn) {
    try {
        // Fetch unique sections for filtering
        $sectionStmt = $conn->query("SELECT DISTINCT section FROM cemetery_lots WHERE section IS NOT NULL AND section != '' ORDER BY LENGTH(section), section");
        $sections = $sectionStmt->fetchAll(PDO::FETCH_COLUMN);

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
    .pagination-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        background: #fff;
        border-top: 1px solid #e2e8f0;
        border-bottom-left-radius: 12px;
        border-bottom-right-radius: 12px;
        margin-top: -1px; /* Overlap with table-wrap border if any */
    }
    .pagination-info {
        font-size: 14px;
        color: #64748b;
    }
    .pagination-controls {
        display: flex;
        gap: 5px;
        align-items: center;
    }
    .pagination-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 36px;
        padding: 0 8px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #1e293b;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .pagination-btn:hover:not(:disabled) {
        border-color: #3b82f6;
        color: #3b82f6;
        background: #f0f7ff;
    }
    .pagination-btn.active {
        background: #3b82f6;
        color: #fff;
        border-color: #3b82f6;
    }
    .pagination-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    .pagination-ellipsis {
        color: #94a3b8;
        padding: 0 5px;
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
      <div class="page-header">
        <h1 class="page-title">Burial Records</h1>
        <div style="display:flex; gap:10px;">
          <button id="viewArchivedBtn" class="btn-secondary" style="display:flex; align-items:center; gap:8px;">
            <span class="icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 8v13H3V8"></path>
                <path d="M1 3h22v5H1z"></path>
                <path d="M10 12h4"></path>
              </svg>
            </span>
            <span id="viewArchivedText">View Archived Records</span>
          </button>
          <button class="btn-primary" data-action="add">
            <span class="icon" style="color:#fff">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 5v14" />
                <path d="M5 12h14" />
              </svg>
            </span>
            <span>Add New Burial Record</span>
          </button>
        </div>
      </div>

      <section class="card">
        <div class="card-head" style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
          <div>
            <h2 class="card-title">All Burial Records</h2>
            <p class="card-sub">Manage deceased person records and burial information</p>
          </div>
          <div style="display:flex; gap:10px; align-items:center;">
            <select id="sectionFilter" style="padding:12px 14px; border:2px solid #e2e8f0; border-radius:12px; font-size:14px; color:#475569; outline:none; transition:all 0.2s; background:white; cursor:pointer;" onfocus="this.style.borderColor='#3b82f6';" onblur="this.style.borderColor='#e2e8f0';">
              <option value="">All Sections</option>
              <?php foreach ($sections as $section): ?>
                <option value="<?php echo htmlspecialchars($section); ?>"><?php echo htmlspecialchars($section); ?></option>
              <?php endforeach; ?>
            </select>
            <select id="statusFilter" style="padding:12px 14px; border:2px solid #e2e8f0; border-radius:12px; font-size:14px; color:#475569; outline:none; transition:all 0.2s; background:white; cursor:pointer;" onfocus="this.style.borderColor='#3b82f6';" onblur="this.style.borderColor='#e2e8f0';">
              <option value="">All Statuses</option>
              <option value="Vacant">Vacant</option>
              <option value="Occupied">Occupied</option>
              <option value="Maintenance">Maintenance</option>
            </select>
            <div style="display:flex; align-items:center; gap:8px; background:#fff; padding:8px 15px; border:2px solid #e2e8f0; border-radius:12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
              <label for="startDate" style="font-size:13px; font-weight:600; color:#64748b;">Death From:</label>
              <input type="date" id="startDate" style="border:none; outline:none; font-size:14px; color:#1e293b; cursor:pointer;">
              <div style="width:1px; height:20px; background:#e2e8f0; margin:0 5px;"></div>
              <label for="endDate" style="font-size:13px; font-weight:600; color:#64748b;">To:</label>
              <input type="date" id="endDate" style="border:none; outline:none; font-size:14px; color:#1e293b; cursor:pointer;">
            </div>
            <input 
              id="recordSearch" 
              type="text" 
              placeholder="🔍 Search records…" 
              style="padding:12px 20px; border:2px solid #e2e8f0; border-radius:12px; font-size:16px; width:300px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06); transition: all 0.2s ease; outline: none;"
              onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59, 130, 246, 0.3), 0 4px 6px -1px rgba(0,0,0,0.1)';"
              onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06)';">
          </div>
        </div>

        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th align="left">Full Name</th>
                <th align="left">Lot Number</th>
                <th align="left">Layer</th>
                <th align="left">Section</th>
                <th align="left">Date of Death</th>
                <th align="left">Date of Burial</th>
                <th align="left">Age</th>
                <th align="left">Notes</th>
                <th align="left">Relationship</th>
                <th align="right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td colspan="10" style="text-align:center;">Loading records...</td>
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
