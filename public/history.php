<?php
require_once __DIR__ . '/../config/auth.php';
requireLogin();

$user = getUserInfo();
$userInitials = getInitials($user['full_name']);

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$logs = [];
$archivedLogs = [];
$showArchived = isset($_GET['view']) && $_GET['view'] === 'archived';

if ($conn) {
    try {
        // Ensure column exists (migration helper)
        $conn->exec("ALTER TABLE activity_logs ADD COLUMN is_archived BOOLEAN DEFAULT 0");
    } catch (PDOException $e) {
        // Ignore if already exists
    }

    try {
        $archivedCondition = $showArchived ? "al.is_archived = 1" : "al.is_archived = 0";
        // Fetch activity logs, excluding login actions
        $stmt = $conn->query("
            SELECT al.*, u.full_name as user_name 
            FROM activity_logs al 
            LEFT JOIN users u ON al.user_id = u.id 
            WHERE al.action != 'LOGIN' AND $archivedCondition
            ORDER BY al.created_at DESC
        ");
        $logs = $stmt->fetchAll();
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
  <title>PeacePlot Admin - System History</title>
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
        <a href="map-editor.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" /><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" /></svg></span><span>Map Editor</span></a>
        <a href="burial-records.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" /><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" /><path d="M8 6h8" /><path d="M8 10h8" /></svg></span><span>Burial Records</span></a>
        <a href="reports.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18" /><path d="M7 14v4" /><path d="M11 10v8" /><path d="M15 6v12" /><path d="M19 12v6" /></svg></span><span>Reports</span></a>
        <a href="history.php" class="active"><span class="icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span><span>History</span></a>
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
        <h1 class="page-title">System Activity History</h1>
        <div style="display:flex; gap:10px;">
          <a href="history.php?view=<?php echo $showArchived ? 'active' : 'archived'; ?>" class="btn-secondary" style="display:flex; align-items:center; gap:8px; text-decoration:none;">
            <span class="icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 8v13H3V8"></path>
                <path d="M1 3h22v5H1z"></path>
                <path d="M10 12h4"></path>
              </svg>
            </span>
            <span><?php echo $showArchived ? 'View Active Logs' : 'View Archived Logs'; ?></span>
          </a>
        </div>
      </div>

      <section class="card">
        <div class="card-head" style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
          <div>
            <h2 class="card-title"><?php echo $showArchived ? 'Archived System Activity' : 'System Activity Log'; ?></h2>
            <p class="card-sub"><?php echo $showArchived ? 'History logs that have been moved to archive.' : 'A comprehensive log of all changes made in the system, from newest to oldest.'; ?></p>
          </div>
          <div style="display:flex; gap:10px; align-items:center;">
            <div style="display:flex; align-items:center; gap:8px; background:#fff; padding:8px 15px; border:2px solid #e2e8f0; border-radius:12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
              <label for="startDate" style="font-size:13px; font-weight:600; color:#64748b;">From:</label>
              <input type="date" id="startDate" style="border:none; outline:none; font-size:14px; color:#1e293b;">
              <div style="width:1px; height:20px; background:#e2e8f0; margin:0 5px;"></div>
              <label for="endDate" style="font-size:13px; font-weight:600; color:#64748b;">To:</label>
              <input type="date" id="endDate" style="border:none; outline:none; font-size:14px; color:#1e293b;">
            </div>
            <input 
              id="historySearch" 
              type="text" 
              placeholder="🔍 Search activity…" 
              style="padding:12px 20px; border:2px solid #e2e8f0; border-radius:12px; font-size:16px; width:300px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06); transition: all 0.2s ease; outline: none;"
              onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59, 130, 246, 0.3), 0 4px 6px -1px rgba(0,0,0,0.1)';"
              onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06)';">
          </div>
        </div>

        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th align="left">Date & Time</th>
                <th align="left">User</th>
                <th align="left">Action</th>
                <th align="left">Description</th>
                <th align="right">Actions</th>
              </tr>
            </thead>
            <tbody id="historyTableBody">
              <?php if (isset($error)): ?>
                <tr>
                  <td colspan="5" style="text-align:center; color:#ef4444;">
                    Error loading data: <?php echo htmlspecialchars($error); ?>
                  </td>
                </tr>
              <?php elseif (empty($logs)): ?>
                <tr>
                  <td colspan="5" style="text-align:center; color:#6b7280;">
                    No system activity found.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($logs as $log): ?>
                  <tr class="history-row" 
                      data-id="<?php echo $log['id']; ?>"
                      data-user="<?php echo strtolower(htmlspecialchars($log['user_name'] ?: 'System')); ?>"
                      data-action="<?php echo strtolower(htmlspecialchars($log['action'])); ?>"
                      data-desc="<?php echo strtolower(htmlspecialchars($log['description'])); ?>"
                      data-date="<?php echo date('Y-m-d', strtotime($log['created_at'])); ?>">
                    <td style="white-space: nowrap;"><span style="font-weight: 500; color: #1f2937;"><?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?></span></td>
                    <td><strong style="color: #4b5563;"><?php echo htmlspecialchars($log['user_name'] ?: 'System'); ?></strong></td>
                    <td>
                      <?php 
                        $badgeClass = 'badge-info';
                        if (strpos($log['action'], 'DELETE') !== false) $badgeClass = 'badge-danger';
                        if (strpos($log['action'], 'ADD') !== false) $badgeClass = 'badge-success';
                        if (strpos($log['action'], 'UPDATE') !== false) $badgeClass = 'badge-warning';
                      ?>
                      <span class="badge <?php echo $badgeClass; ?>" style="padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase;">
                        <?php echo htmlspecialchars($log['action']); ?>
                      </span>
                    </td>
                    <td style="color: #4b5563;"><?php echo htmlspecialchars($log['description']); ?></td>
                    <td align="right">
                      <button class="btn-action archive-single-btn" 
                              data-id="<?php echo $log['id']; ?>" 
                              data-action-type="<?php echo $showArchived ? 'restore' : 'archive'; ?>"
                              title="<?php echo $showArchived ? 'Restore to Active' : 'Move to Archive'; ?>">
                        <span class="icon">
                          <?php if ($showArchived): ?>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                              <polyline points="23 4 23 10 17 10"></polyline>
                              <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                            </svg>
                          <?php else: ?>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                              <polyline points="21 8 21 21 3 21 3 8"></polyline>
                              <rect x="1" y="3" width="22" height="5"></rect>
                            </svg>
                          <?php endif; ?>
                        </span>
                      </button>
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

  <style>
    .badge-success { background: #dcfce7; color: #166534; }
    .badge-danger { background: #fee2e2; color: #991b1b; }
    .badge-warning { background: #fef9c3; color: #854d0e; }
    .badge-info { background: #e0f2fe; color: #075985; }
  </style>

  <script src="../assets/js/app.js"></script>
  <script>
    // Search and filter functionality
    document.addEventListener('DOMContentLoaded', () => {
      const searchInput = document.getElementById('historySearch');
      const startDateInput = document.getElementById('startDate');
      const endDateInput = document.getElementById('endDate');
      const rows = document.querySelectorAll('.history-row');

      function filterTable() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        const startDate = startDateInput.value;
        const endDate = endDateInput.value;

        rows.forEach(row => {
          const user = row.getAttribute('data-user');
          const action = row.getAttribute('data-action');
          const desc = row.getAttribute('data-desc');
          const rowDate = row.getAttribute('data-date');

          const matchesSearch = user.includes(searchTerm) || 
                               action.includes(searchTerm) || 
                               desc.includes(searchTerm);
          
          let matchesDate = true;
          if (startDate && rowDate < startDate) matchesDate = false;
          if (endDate && rowDate > endDate) matchesDate = false;

          if (matchesSearch && matchesDate) {
            row.style.display = '';
          } else {
            row.style.display = 'none';
          }
        });
      }

      if (searchInput) searchInput.addEventListener('input', filterTable);
      if (startDateInput) startDateInput.addEventListener('change', filterTable);
      if (endDateInput) endDateInput.addEventListener('change', filterTable);

      // Handle single log archiving/restoring
      document.querySelectorAll('.archive-single-btn').forEach(btn => {
        btn.addEventListener('click', async (e) => {
          const logId = btn.getAttribute('data-id');
          const actionType = btn.getAttribute('data-action-type');
          const isArchive = actionType === 'archive';
          
          if (!confirm(`Are you sure you want to ${isArchive ? 'archive' : 'restore'} this activity log?`)) {
            return;
          }
          
          try {
            const response = await fetch('../api/archive_history.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ 
                action: isArchive ? 'archive_single' : 'restore_single', 
                id: logId 
              })
            });
            const result = await response.json();
            
            if (result.success) {
              // Smoothly remove the row
              const row = btn.closest('tr');
              row.style.transition = 'all 0.3s ease';
              row.style.opacity = '0';
              row.style.transform = 'translateX(20px)';
              setTimeout(() => {
                row.remove();
                // If no more rows, show the empty message
                if (document.querySelectorAll('.history-row').length === 0) {
                  window.location.reload();
                }
              }, 300);
            } else {
              alert('Error: ' + result.message);
            }
          } catch (error) {
            console.error('Error:', error);
            alert(`Failed to ${actionType} log.`);
          }
        });
      });
    });
  </script>
</body>
</html>
