<?php
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$records = [];
$availableLots = [];

if ($conn) {
    try {
        $stmt = $conn->query("
            SELECT dr.*, cl.lot_number, cl.section, cl.block 
            FROM deceased_records dr 
            LEFT JOIN cemetery_lots cl ON dr.lot_id = cl.id 
            ORDER BY dr.date_of_burial DESC
        ");
        $records = $stmt->fetchAll();
        
        $lotsStmt = $conn->query("
            SELECT id, lot_number, section, block 
            FROM cemetery_lots 
            ORDER BY lot_number
        ");
        $availableLots = $lotsStmt->fetchAll();
        
    } catch (PDOException $e) {
        $error = $e->getMessage();
        $availableLots = [];
    }
} else {
    $availableLots = [];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>PeacePlot Admin - Burial Records</title>
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
        <a href="burial-records.php" class="active"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" /><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" /><path d="M8 6h8" /><path d="M8 10h8" /></svg></span><span>Burial Records</span></a>
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
        <h1 class="page-title">Burial Records</h1>
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

      <section class="card">
        <div class="card-head" style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
          <div>
            <h2 class="card-title">All Burial Records</h2>
            <p class="card-sub">Manage deceased person records and burial information</p>
          </div>
          <div style="display:flex; gap:10px; align-items:center;">
            <input 
              id="recordSearch" 
              type="text" 
              placeholder="Search records…" 
              style="padding:8px 12px; border:1px solid var(--border); border-radius:8px; font-size:14px; width:240px;">
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
              <?php if (isset($error)): ?>
                <tr>
                  <td colspan="10" style="text-align:center; color:#ef4444;">
                    Error loading data: <?php echo htmlspecialchars($error); ?>
                  </td>
                </tr>
              <?php elseif (empty($records)): ?>
                <tr>
                  <td colspan="10" style="text-align:center; color:#6b7280;">
                    No burial records found. Click "Add New Burial Record" to create one.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($records as $record): ?>
                  <tr data-record-id="<?php echo $record['id']; ?>">
                    <td><?php echo htmlspecialchars($record['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($record['lot_number'] ?: '—'); ?></td>
                    <td>
                      <?php if ($record['layer']): ?>
                        <span style="background: #e3f2fd; color: #1976d2; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                          Layer <?php echo htmlspecialchars($record['layer']); ?>
                        </span>
                      <?php else: ?>
                        —
                      <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($record['section'] ?: '—'); ?></td>
                    <td><?php echo $record['date_of_death'] ? date('M d, Y', strtotime($record['date_of_death'])) : '—'; ?></td>
                    <td><?php echo $record['date_of_burial'] ? date('M d, Y', strtotime($record['date_of_burial'])) : '—'; ?></td>
                    <td><?php echo $record['age'] ?: '—'; ?></td>
                    <td style="max-width: 120px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #6b7280; font-size: 13px;">
                      <?php echo htmlspecialchars($record['deceased_info'] ?: '—'); ?>
                    </td>
                    <td style="max-width: 120px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #6b7280; font-size: 13px;">
                      <?php echo htmlspecialchars($record['remarks'] ?: '—'); ?>
                    </td>
                    <td>
                      <div class="actions">
                        <button class="btn-action btn-edit" data-action="view" data-record-id="<?php echo $record['id']; ?>">
                          <span class="icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                              <circle cx="12" cy="12" r="3" />
                            </svg>
                          </span>
                          <span>View</span>
                        </button>
                        <button class="btn-action btn-edit" data-action="edit" data-record-id="<?php echo $record['id']; ?>">
                          <span class="icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                              <path d="M12 20h9" />
                              <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" />
                            </svg>
                          </span>
                          <span>Edit</span>
                        </button>
                        <button class="btn-action btn-delete" data-action="delete" data-record-id="<?php echo $record['id']; ?>">
                          <span class="icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                              <path d="M3 6h18" />
                              <path d="M8 6V4h8v2" />
                              <path d="M19 6l-1 14H6L5 6" />
                              <path d="M10 11v6" />
                              <path d="M14 11v6" />
                            </svg>
                          </span>
                          <span>Delete</span>
                        </button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
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
