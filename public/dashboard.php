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
    'occupied_lots' => 0,
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
        <a href="index.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16" /><path d="M4 12h16" /><path d="M4 17h16" /><path d="M8 7v10" /><path d="M16 7v10" /></svg></span><span>Cemetery Lot Management</span></a>
        <a href="lot-availability.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20" /><path d="M2 12h20" /><path d="M4 4l16 16" /></svg></span><span>Lot Availability</span></a>
        <a href="cemetery-map.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2V6z" /><path d="M9 4v14" /><path d="M15 6v14" /></svg></span><span>Cemetery Map</span></a>
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
        <h1 class="page-title">Dashboard Overview</h1>
      </div>

      <?php if (isset($error)): ?>
        <div class="card" style="padding:20px; color:#ef4444;">
          Error loading dashboard data: <?php echo htmlspecialchars($error); ?>
        </div>
      <?php else: ?>

      <div class="dashboard-stats">
        <div class="dash-stat-card">
          <div class="dash-stat-content">
            <div class="dash-stat-info">
              <div class="dash-stat-label">Total Cemetery Lots</div>
              <div class="dash-stat-number"><?php echo $stats['total_lots']; ?></div>
              <div class="dash-stat-sub">All sections</div>
            </div>
            <div class="dash-stat-icon dash-icon-blue">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                <circle cx="12" cy="10" r="3" />
              </svg>
            </div>
          </div>
        </div>

        <div class="dash-stat-card">
          <div class="dash-stat-content">
            <div class="dash-stat-info">
              <div class="dash-stat-label">Available Lots</div>
              <div class="dash-stat-number"><?php echo $stats['available_lots']; ?></div>
              <div class="dash-stat-sub"><?php echo $available_percent; ?>% available</div>
            </div>
            <div class="dash-stat-icon dash-icon-green">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                <circle cx="12" cy="12" r="3" />
              </svg>
            </div>
          </div>
        </div>

        <div class="dash-stat-card">
          <div class="dash-stat-content">
            <div class="dash-stat-info">
              <div class="dash-stat-label">Occupied Lots</div>
              <div class="dash-stat-number"><?php echo $stats['occupied_lots']; ?></div>
              <div class="dash-stat-sub"><?php echo $occupied_percent; ?>% occupied</div>
            </div>
            <div class="dash-stat-icon dash-icon-orange">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" />
                <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
              </svg>
            </div>
          </div>
        </div>
      </div>

      <section class="card" style="margin-top:20px">
        <div class="card-head" style="padding:16px 18px; border-bottom:1px solid var(--border)">
          <h2 class="card-title">Section Summary</h2>
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
                  <td colspan="4" style="text-align:center; color:#6b7280;">No sections found</td>
                </tr>
              <?php else: ?>
                <?php foreach ($stats['sections'] as $section): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($section['section']); ?></td>
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

      <section class="card" style="margin-top:20px">
        <div class="card-head" style="padding:16px 18px; border-bottom:1px solid var(--border)">
          <h2 class="card-title">Cemetery Status by Section</h2>
          <p class="card-sub">Comparison of Vacant vs Occupied lots</p>
        </div>
        <div class="chart-placeholder">
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
                    <div class="chart-bar" style="height:<?php echo min($section['vacant'] * 10, 200); ?>px; background:#22c55e; width:20px;" title="Vacant: <?php echo $section['vacant']; ?>"></div>
                    <div class="chart-bar" style="height:<?php echo min($section['occupied'] * 10, 200); ?>px; background:#ff8c42; width:20px;" title="Occupied: <?php echo $section['occupied']; ?>"></div>
                  </div>
                  <span class="chart-label"><?php echo htmlspecialchars($section['section']); ?></span>
                  <div style="display:flex; flex-direction:column; gap:2px;">
                    <span class="chart-label" style="font-size:10px; color:#22c55e"><?php echo $section['vacant']; ?> V</span>
                    <span class="chart-label" style="font-size:10px; color:#ff8c42"><?php echo $section['occupied']; ?> O</span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div style="display:flex; justify-content:center; gap:20px; margin-top:20px; font-size:12px;">
            <div style="display:flex; align-items:center; gap:6px;"><div style="width:12px; height:12px; background:#22c55e; border-radius:2px;"></div> Vacant</div>
            <div style="display:flex; align-items:center; gap:6px;"><div style="width:12px; height:12px; background:#ff8c42; border-radius:2px;"></div> Occupied</div>
          </div>
        </div>
      </section>

      <?php endif; ?>
    </main>
  </div>

  <script src="../assets/js/app.js"></script>
</body>
</html>
