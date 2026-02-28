<?php
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$stats = [
    'total_lots' => 0,
    'vacant_lots' => 0,
    'occupied_lots' => 0,
    'occupied_lots' => 0,
    'sections' => [],
    'recent_burials' => []
];

if ($conn) {
    try {
        // Get overall statistics
        $stats['total_lots'] = $conn->query("SELECT COUNT(*) FROM cemetery_lots")->fetchColumn();
        $stats['vacant_lots'] = $conn->query("SELECT COUNT(*) FROM cemetery_lots WHERE status = 'Vacant'")->fetchColumn();
        $stats['occupied_lots'] = $conn->query("SELECT COUNT(*) FROM cemetery_lots WHERE status = 'Occupied'")->fetchColumn();
        
        // Get section-wise summary
        $stmt = $conn->query("
            SELECT 
                section,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Occupied' THEN 1 ELSE 0 END) as occupied,
                SUM(CASE WHEN status = 'Vacant' THEN 1 ELSE 0 END) as vacant
            FROM cemetery_lots
            GROUP BY section
            ORDER BY section
        ");
        $stats['sections'] = $stmt->fetchAll();
        
        // Get recent burials
        $stmt = $conn->query("
            SELECT dr.*, cl.lot_number, cl.section 
            FROM deceased_records dr
            LEFT JOIN cemetery_lots cl ON dr.lot_id = cl.id
            ORDER BY dr.date_of_burial DESC
            LIMIT 10
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
        <a href="cemetery-map.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2V6z" /><path d="M9 4v14" /><path d="M15 6v14" /></svg></span><span>Cemetery Map</span></a>
        <a href="burial-records.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" /><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" /><path d="M8 6h8" /><path d="M8 10h8" /></svg></span><span>Burial Records</span></a>
        <a href="reports.php" class="active"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18" /><path d="M7 14v4" /><path d="M11 10v8" /><path d="M15 6v12" /><path d="M19 12v6" /></svg></span><span>Reports</span></a>
      </nav>

      <div class="sidebar-footer">
        <div class="user">
          <div class="avatar">AD</div>
          <div>
            <div class="user-name">Admin User</div>
            <div class="user-email">admin@peaceplot.com</div>
          </div>
        </div>

        <a class="logout" href="#" onclick="return false;">
          <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><path d="M16 17l5-5-5-5" /><path d="M21 12H9" /></svg></span>
          <span>Logout</span>
        </a>
      </div>
    </aside>

    <main class="main">
      <div class="page-header">
        <h1 class="page-title">Reports</h1>
      </div>

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
            <button class="report-btn report-btn-print" onclick="window.print()">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 9V2h12v7" />
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                <path d="M6 14h12v8H6z" />
              </svg>
              <span>Print</span>
            </button>
            <button class="report-btn report-btn-export" onclick="exportToCSV('all_lots')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="7 10 12 15 17 10" />
                <line x1="12" y1="15" x2="12" y2="3" />
              </svg>
              <span>Export CSV</span>
            </button>
          </div>
        </div>

        <div class="report-card report-green">
          <div class="report-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
              <path d="M14 2v6h6" />
              <path d="M16 13H8" />
              <path d="M16 17H8" />
              <path d="M10 9H8" />
            </svg>
          </div>
          <div class="report-number"><?php echo $stats['vacant_lots']; ?></div>
          <div class="report-title">Vacant Lots Report</div>
          <div class="report-desc">Detailed list of all available cemetery lots for new burials</div>
          <div class="report-actions">
            <a href="lot-availability.php?status=Vacant" class="report-btn report-btn-view">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                <circle cx="12" cy="12" r="3" />
              </svg>
              <span>View Report</span>
            </a>
            <button class="report-btn report-btn-print" onclick="window.print()">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 9V2h12v7" />
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                <path d="M6 14h12v8H6z" />
              </svg>
              <span>Print</span>
            </button>
            <button class="report-btn report-btn-export" onclick="exportToCSV('vacant_lots')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="7 10 12 15 17 10" />
                <line x1="12" y1="15" x2="12" y2="3" />
              </svg>
              <span>Export CSV</span>
            </button>
          </div>
        </div>

        <div class="report-card report-orange">
          <div class="report-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
              <path d="M14 2v6h6" />
              <path d="M16 13H8" />
              <path d="M16 17H8" />
              <path d="M10 9H8" />
            </svg>
          </div>
          <div class="report-number"><?php echo $stats['occupied_lots']; ?></div>
          <div class="report-title">Occupied Lots Report</div>
          <div class="report-desc">Comprehensive record of all occupied lots with burial information</div>
          <div class="report-actions">
            <a href="lot-availability.php?status=Occupied" class="report-btn report-btn-view">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                <circle cx="12" cy="12" r="3" />
              </svg>
              <span>View Report</span>
            </a>
            <button class="report-btn report-btn-print" onclick="window.print()">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 9V2h12v7" />
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                <path d="M6 14h12v8H6z" />
              </svg>
              <span>Print</span>
            </button>
            <button class="report-btn report-btn-export" onclick="exportToCSV('occupied_lots')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="7 10 12 15 17 10" />
                <line x1="12" y1="15" x2="12" y2="3" />
              </svg>
              <span>Export CSV</span>
            </button>
          </div>
        </div>
      </div>

      <section class="card" style="margin-top:24px">
        <div class="card-head" style="padding:16px 18px; border-bottom:1px solid var(--border)">
          <h2 class="card-title">Section-wise Summary Report</h2>
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

      <section class="card" style="margin-top:24px">
        <div class="card-head" style="padding:16px 18px; border-bottom:1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
          <div>
            <h2 class="card-title">Recent Burials</h2>
            <p class="card-sub">Last 10 burial records</p>
          </div>
          <button class="report-btn report-btn-export" onclick="exportToCSV('recent_burials')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
              <polyline points="7 10 12 15 17 10" />
              <line x1="12" y1="15" x2="12" y2="3" />
            </svg>
            <span>Export CSV</span>
          </button>
        </div>

        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th align="left">Full Name</th>
                <th align="left">Lot Number</th>
                <th align="left">Section</th>
                <th align="left">Date of Burial</th>
                <th align="left">Age</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($stats['recent_burials'])): ?>
                <tr>
                  <td colspan="5" style="text-align:center; color:#6b7280;">No burial records found</td>
                </tr>
              <?php else: ?>
                <?php foreach ($stats['recent_burials'] as $burial): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($burial['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($burial['lot_number'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($burial['section'] ?: '—'); ?></td>
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
  </script>
  <script src="../assets/js/app.js"></script>
</body>
</html>
