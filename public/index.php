<?php
require_once __DIR__ . '/../config/auth.php';
requireLogin();

$user = getUserInfo();
$userInitials = getInitials($user['full_name']);

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$lots = [];
if ($conn) {
    try {
        $stmt = $conn->query("
            SELECT cl.*, dr.full_name as deceased_name 
            FROM cemetery_lots cl 
            LEFT JOIN deceased_records dr ON cl.id = dr.lot_id 
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
</head>
<body>
  <div class="app">
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

        <a href="index.php" class="active">
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
        </a>

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
          <span class="icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
              <path d="M16 17l5-5-5-5" />
              <path d="M21 12H9" />
            </svg>
          </span>
          <span>Logout</span>
        </a>
      </div>
    </aside>

    <main class="main">
      <div class="page-header">
        <h1 class="page-title">Lot Management</h1>
        <button class="btn-primary" data-action="add">
          <span class="icon" style="color:#fff">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 5v14" />
              <path d="M5 12h14" />
            </svg>
          </span>
          <span>Add New Cemetery Lot</span>
        </button>
      </div>

      <section class="card">
        <div class="card-head" style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
          <div>
            <h2 class="card-title">All Cemetery Lots</h2>
            <p class="card-sub">Manage and update cemetery lot information</p>
          </div>
          <div style="display:flex; gap:10px; align-items:center;">
            <input 
              id="lotSearch" 
              type="text" 
              placeholder="Search lots…" 
              style="padding:8px 12px; border:1px solid var(--border); border-radius:8px; font-size:14px; width:240px;">
          </div>
        </div>

        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th align="left">Lot Number</th>
                <th align="left">Section</th>
                <th align="left">Position</th>
                <th align="left">Lot Status</th>
                <th align="left">Deceased Name</th>
                <th align="right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (isset($error)): ?>
                <tr>
                  <td colspan="6" style="text-align:center; color:#ef4444;">
                    Error loading data: <?php echo htmlspecialchars($error); ?>
                  </td>
                </tr>
              <?php elseif (empty($lots)): ?>
                <tr>
                  <td colspan="6" style="text-align:center; color:#6b7280;">
                    No cemetery lots found. Click "Add New Cemetery Lot" to create one.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($lots as $lot): ?>
                  <tr data-lot-id="<?php echo $lot['id']; ?>">
                    <td><?php echo htmlspecialchars($lot['lot_number']); ?></td>
                    <td><?php echo htmlspecialchars($lot['section']); ?></td>
                    <td><?php echo htmlspecialchars($lot['block'] ?: $lot['position'] ?: '—'); ?></td>
                    <td><span class="badge <?php echo strtolower($lot['status']); ?>"><?php echo htmlspecialchars($lot['status']); ?></span></td>
                    <td class="<?php echo $lot['deceased_name'] ? '' : 'muted'; ?>">
                      <?php echo $lot['deceased_name'] ? htmlspecialchars($lot['deceased_name']) : '—'; ?>
                    </td>
                    <td>
                      <div class="actions">
                        <button class="btn-action btn-edit" data-action="edit" data-lot-id="<?php echo $lot['id']; ?>">
                          <span class="icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                              <path d="M12 20h9" />
                              <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" />
                            </svg>
                          </span>
                          <span>Edit</span>
                        </button>
                        <button class="btn-action btn-delete" data-action="delete" data-lot-id="<?php echo $lot['id']; ?>">
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

  <script src="../assets/js/app.js"></script>
  <script src="../assets/js/api.js"></script>
  <script src="../assets/js/cemetery-lots.js"></script>
</body>
</html>
