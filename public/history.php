<?php
require_once __DIR__ . '/../config/auth.php';
requireLogin();

$user = getUserInfo();
$userInitials = getInitials($user['full_name']);

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$records = [];
if ($conn) {
    try {
        // Fetch all deceased records ordered by their date of burial (most recent first)
        $stmt = $conn->query("
            SELECT dr.*, cl.lot_number, cl.section, cl.block 
            FROM deceased_records dr 
            LEFT JOIN cemetery_lots cl ON dr.lot_id = cl.id 
            ORDER BY dr.date_of_burial DESC, dr.created_at DESC
        ");
        $records = $stmt->fetchAll();
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
  <title>PeacePlot Admin - Burial History</title>
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
        <h1 class="page-title">Burial Additions History</h1>
      </div>

      <section class="card">
        <div class="card-head" style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
          <div>
            <h2 class="card-title">Chronological Burial Additions</h2>
            <p class="card-sub">A log of all deceased records added to the system, from newest to oldest.</p>
          </div>
          <div style="display:flex; gap:10px; align-items:center;">
            <input 
              id="historySearch" 
              type="text" 
              placeholder="🔍 Search history…" 
              style="padding:12px 20px; border:2px solid #e2e8f0; border-radius:12px; font-size:16px; width:380px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06); transition: all 0.2s ease; outline: none;"
              onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59, 130, 246, 0.3), 0 4px 6px -1px rgba(0,0,0,0.1)';"
              onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06)';">
          </div>
        </div>

        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th align="left">Date of Burial</th>
                <th align="left">Full Name</th>
                <th align="left">Lot Number</th>
                <th align="left">Layer</th>
                <th align="left">Section</th>
                <th align="left">Relationship</th>
                <th align="left">Notes</th>
              </tr>
            </thead>
            <tbody id="historyTableBody">
              <?php if (isset($error)): ?>
                <tr>
                  <td colspan="7" style="text-align:center; color:#ef4444;">
                    Error loading data: <?php echo htmlspecialchars($error); ?>
                  </td>
                </tr>
              <?php elseif (empty($records)): ?>
                <tr>
                  <td colspan="7" style="text-align:center; color:#6b7280;">
                    No burial records found.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($records as $record): ?>
                  <tr class="history-row" 
                      data-name="<?php echo strtolower(htmlspecialchars($record['full_name'])); ?>"
                      data-lot="<?php echo strtolower(htmlspecialchars($record['lot_number'] ?: '')); ?>"
                      data-section="<?php echo strtolower(htmlspecialchars($record['section'] ?: '')); ?>"
                      data-dateadded="<?php echo strtolower($record['date_of_burial'] ? date('M d, Y', strtotime($record['date_of_burial'])) : 'Unknown Date'); ?>">
                    <td><span style="font-weight: 500; color: #1f2937;"><?php echo $record['date_of_burial'] ? date('M d, Y', strtotime($record['date_of_burial'])) : 'Unknown Date'; ?></span></td>
                    <td><strong style="color: #3b82f6;"><?php echo htmlspecialchars($record['full_name']); ?></strong></td>
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
                    <td style="color: #4b5563; font-style: italic; font-size: 13px;"><?php echo htmlspecialchars($record['remarks'] ?: '—'); ?></td>
                    <td style="max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #6b7280; font-size: 13px;">
                      <?php echo htmlspecialchars($record['deceased_info'] ?: '—'); ?>
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
  <script>
    // Search functionality
    document.addEventListener('DOMContentLoaded', () => {
      const searchInput = document.getElementById('historySearch');
      if (searchInput) {
        searchInput.addEventListener('input', (e) => {
          const searchTerm = e.target.value.toLowerCase().trim();
          const rows = document.querySelectorAll('.history-row');
          
          rows.forEach(row => {
            const name = row.getAttribute('data-name');
            const lot = row.getAttribute('data-lot');
            const section = row.getAttribute('data-section');
            const dateAdded = row.getAttribute('data-dateadded');
            
            if (name.includes(searchTerm) || 
                lot.includes(searchTerm) || 
                section.includes(searchTerm) ||
                dateAdded.includes(searchTerm)) {
              row.style.display = '';
            } else {
              row.style.display = 'none';
            }
          });
        });
      }
    });
  </script>
</body>
</html>
