<?php
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$sections = [];
$filterStatus = isset($_GET['status']) ? $_GET['status'] : 'Vacant';
$filterSection = isset($_GET['section']) ? $_GET['section'] : '';

if ($conn) {
    try {
        // Get section statistics
        $stmt = $conn->query("
            SELECT 
                section,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Vacant' THEN 1 ELSE 0 END) as vacant,
                SUM(CASE WHEN status = 'Occupied' THEN 1 ELSE 0 END) as occupied,
                SUM(CASE WHEN status = 'Reserved' THEN 1 ELSE 0 END) as reserved
            FROM cemetery_lots
            GROUP BY section
            ORDER BY section
        ");
        $sections = $stmt->fetchAll();
        
        // Get filtered lots
        $query = "SELECT cl.*, dr.full_name as deceased_name 
                  FROM cemetery_lots cl 
                  LEFT JOIN deceased_records dr ON cl.id = dr.lot_id 
                  WHERE cl.status = :status";
        
        if ($filterSection) {
            $query .= " AND cl.section = :section";
        }
        
        $query .= " ORDER BY cl.lot_number";
        
        $lotsStmt = $conn->prepare($query);
        $lotsStmt->bindParam(':status', $filterStatus);
        if ($filterSection) {
            $lotsStmt->bindParam(':section', $filterSection);
        }
        $lotsStmt->execute();
        $filteredLots = $lotsStmt->fetchAll();
        
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
  <title>PeacePlot Admin - Lot Availability</title>
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
        <a href="index.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16" /><path d="M4 12h16" /><path d="M4 17h16" /><path d="M8 7v10" /><path d="M16 7v10" /></svg></span><span>Cemetery Lot Management</span></a>
        <a href="lot-availability.php" class="active"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20" /><path d="M2 12h20" /><path d="M4 4l16 16" /></svg></span><span>Lot Availability</span></a>
        <a href="cemetery-map.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2V6z" /><path d="M9 4v14" /><path d="M15 6v14" /></svg></span><span>Cemetery Map</span></a>
        <a href="burial-records.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" /><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" /><path d="M8 6h8" /><path d="M8 10h8" /></svg></span><span>Burial Records</span></a>
        <a href="reports.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18" /><path d="M7 14v4" /><path d="M11 10v8" /><path d="M15 6v12" /><path d="M19 12v6" /></svg></span><span>Reports</span></a>
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
        <h1 class="page-title">Lot Availability Monitoring</h1>
      </div>

      <?php if (isset($error)): ?>
        <div class="card" style="padding:20px; color:#ef4444;">
          Error loading data: <?php echo htmlspecialchars($error); ?>
        </div>
      <?php else: ?>

      <div class="stats-grid">
        <?php if (empty($sections)): ?>
          <div class="card" style="padding:20px; color:#6b7280;">
            No sections found. Add cemetery lots to see statistics.
          </div>
        <?php else: ?>
          <?php foreach ($sections as $section): ?>
            <div class="stat-card">
              <div class="stat-header"><?php echo htmlspecialchars($section['section']); ?></div>
              <div class="stat-row">
                <span class="stat-label">Vacant</span>
                <span class="stat-value stat-vacant"><?php echo $section['vacant']; ?></span>
              </div>
              <div class="stat-row">
                <span class="stat-label">Occupied</span>
                <span class="stat-value stat-occupied"><?php echo $section['occupied']; ?></span>
              </div>
              <div class="stat-row">
                <span class="stat-label">Reserved</span>
                <span class="stat-value stat-reserved"><?php echo $section['reserved']; ?></span>
              </div>
              <div class="stat-total">
                <span class="stat-label">Total</span>
                <span class="stat-value"><?php echo $section['total']; ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <section class="card" style="margin-top:20px">
        <div class="tabs">
          <a href="?status=Vacant<?php echo $filterSection ? '&section=' . urlencode($filterSection) : ''; ?>" 
             class="tab <?php echo $filterStatus === 'Vacant' ? 'active' : ''; ?>">
            Vacant Lots
          </a>
          <a href="?status=Occupied<?php echo $filterSection ? '&section=' . urlencode($filterSection) : ''; ?>" 
             class="tab <?php echo $filterStatus === 'Occupied' ? 'active' : ''; ?>">
            Occupied Lots
          </a>
          <a href="?status=Reserved<?php echo $filterSection ? '&section=' . urlencode($filterSection) : ''; ?>" 
             class="tab <?php echo $filterStatus === 'Reserved' ? 'active' : ''; ?>">
            Reserved Lots
          </a>
        </div>

        <div class="card-head" style="border-top:1px solid var(--border); padding:16px 18px;">
          <div>
            <h2 class="card-title"><?php echo htmlspecialchars($filterStatus); ?> Lots</h2>
            <p class="card-sub">Showing <?php echo count($filteredLots); ?> <?php echo strtolower($filterStatus); ?> lots</p>
          </div>
          <div style="display:flex; gap:10px; align-items:center;">
            <select id="sectionFilter" style="padding:8px 12px; border:1px solid var(--border); border-radius:8px; font-size:14px;">
              <option value="">All Sections</option>
              <?php foreach ($sections as $section): ?>
                <option value="<?php echo htmlspecialchars($section['section']); ?>" 
                        <?php echo $filterSection === $section['section'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($section['section']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th align="left">Lot Number</th>
                <th align="left">Section</th>
                <th align="left">Block</th>
                <th align="left">Position</th>
                <th align="left">Status</th>
                <?php if ($filterStatus === 'Occupied'): ?>
                  <th align="left">Deceased Name</th>
                <?php endif; ?>
                <th align="left">Size (sqm)</th>
                <th align="left">Price</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($filteredLots)): ?>
                <tr>
                  <td colspan="<?php echo $filterStatus === 'Occupied' ? '8' : '7'; ?>" style="text-align:center; color:#6b7280;">
                    No <?php echo strtolower($filterStatus); ?> lots found
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($filteredLots as $lot): ?>
                  <tr>
                    <td><strong><?php echo htmlspecialchars($lot['lot_number']); ?></strong></td>
                    <td><?php echo htmlspecialchars($lot['section']); ?></td>
                    <td><?php echo htmlspecialchars($lot['block'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($lot['position'] ?: '—'); ?></td>
                    <td><span class="badge <?php echo strtolower($lot['status']); ?>"><?php echo htmlspecialchars($lot['status']); ?></span></td>
                    <?php if ($filterStatus === 'Occupied'): ?>
                      <td><?php echo htmlspecialchars($lot['deceased_name'] ?: '—'); ?></td>
                    <?php endif; ?>
                    <td><?php echo $lot['size_sqm'] ? number_format($lot['size_sqm'], 2) : '—'; ?></td>
                    <td><?php echo $lot['price'] ? '₱' . number_format($lot['price'], 2) : '—'; ?></td>
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
    document.getElementById('sectionFilter')?.addEventListener('change', function() {
      const section = this.value;
      const status = '<?php echo $filterStatus; ?>';
      window.location.href = '?status=' + status + (section ? '&section=' + encodeURIComponent(section) : '');
    });
  </script>
  <script src="../assets/js/app.js"></script>
</body>
</html>
