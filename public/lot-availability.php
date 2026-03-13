<?php
require_once __DIR__ . '/../config/auth.php';
requireLogin();

$user = getUserInfo();
$userInitials = getInitials($user['full_name']);

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
                SUM(CASE WHEN status = 'Occupied' THEN 1 ELSE 0 END) as occupied
            FROM cemetery_lots
            GROUP BY section
            ORDER BY section
        ");
        $sections = $stmt->fetchAll();
        
        // Pagination parameters
        $itemsPerPage = 20;
        $currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $offset = ($currentPage - 1) * $itemsPerPage;

        // Get total count for filtered lots
        $countQuery = "SELECT COUNT(*) FROM cemetery_lots WHERE status = :status";
        if ($filterSection) {
            $countQuery .= " AND section = :section";
        }
        $countStmt = $conn->prepare($countQuery);
        $countStmt->bindParam(':status', $filterStatus);
        if ($filterSection) {
            $countStmt->bindParam(':section', $filterSection);
        }
        $countStmt->execute();
        $totalItems = $countStmt->fetchColumn();
        $totalPages = ceil($totalItems / $itemsPerPage);

        // Get filtered and paginated lots
        $query = "SELECT cl.*, GROUP_CONCAT(dr.full_name, ', ') as deceased_name 
                  FROM cemetery_lots cl 
                  LEFT JOIN deceased_records dr ON cl.id = dr.lot_id 
                  WHERE cl.status = :status";
        
        if ($filterSection) {
            $query .= " AND cl.section = :section";
        }
        
        $query .= " GROUP BY cl.id ORDER BY LENGTH(cl.lot_number), cl.lot_number LIMIT :limit OFFSET :offset";
        
        $lotsStmt = $conn->prepare($query);
        $lotsStmt->bindParam(':status', $filterStatus);
        if ($filterSection) {
            $lotsStmt->bindParam(':section', $filterSection);
        }
        $lotsStmt->bindParam(':limit', $itemsPerPage, PDO::PARAM_INT);
        $lotsStmt->bindParam(':offset', $offset, PDO::PARAM_INT);
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
  <title>PeacePlot Admin - Lots</title>
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
          </div>
        </div>
        <a href="lot-availability.php" class="active"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20" /><path d="M2 12h20" /><path d="M4 4l16 16" /></svg></span><span>Lots</span></a>
        <a href="cemetery-map.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2V6z" /><path d="M9 4v14" /><path d="M15 6v14" /></svg></span><span>Cemetery Map</span></a>
        <a href="map-editor.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" /><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" /></svg></span><span>Map Editor</span></a>
        <a href="burial-records.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" /><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" /><path d="M8 6h8" /><path d="M8 10h8" /></svg></span><span>Burial Records</span></a>
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
        <h1 class="page-title">Lots Monitoring</h1>
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
        </div>

        <div class="card-head" style="border-top:1px solid var(--border); padding:16px 18px;">
          <div>
            <h2 class="card-title"><?php echo htmlspecialchars($filterStatus); ?> Lots</h2>
            <p class="card-sub">
              Showing <?php echo $totalItems > 0 ? ($offset + 1) : 0; ?>-<?php echo min($offset + $itemsPerPage, $totalItems); ?> of <?php echo $totalItems; ?> <?php echo strtolower($filterStatus); ?> lots
            </p>
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
                <th align="right">Actions</th>
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
                    <td>
                      <div class="actions">
                        <button class="btn-action btn-map" onclick="handleMapRedirect(<?php echo $lot['id']; ?>, '<?php echo htmlspecialchars($lot['lot_number']); ?>')">
                          <span class="icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" fill="currentColor" fill-opacity="0.2"/>
                              <circle cx="12" cy="10" r="3" fill="currentColor"/>
                              <path d="M12 2v20" stroke-width="1" opacity="0.3"/>
                            </svg>
                          </span>
                          <span>View on Map</span>
                        </button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination-wrap" style="padding: 20px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 8px; align-items: center;">
          <?php
            $base_url = "?status=" . urlencode($filterStatus) . ($filterSection ? "&section=" . urlencode($filterSection) : "");
          ?>
          
          <a href="<?php echo $base_url; ?>&page=<?php echo $currentPage - 1; ?>" 
             class="pagination-btn <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>"
             style="text-decoration: none;">Previous</a>
          
          <?php
          $delta = 2;
          for ($i = 1; $i <= $totalPages; $i++):
            if ($i === 1 || $i === $totalPages || ($i >= $currentPage - $delta && $i <= $currentPage + $delta)):
          ?>
            <a href="<?php echo $base_url; ?>&page=<?php echo $i; ?>" 
               class="pagination-btn <?php echo $i === $currentPage ? 'active' : ''; ?>"
               style="text-decoration: none;"><?php echo $i; ?></a>
          <?php elseif ($i === $currentPage - $delta - 1 || $i === $currentPage + $delta + 1): ?>
            <span style="padding: 8px; color: #64748b;">...</span>
          <?php endif; endfor; ?>
          
          <a href="<?php echo $base_url; ?>&page=<?php echo $currentPage + 1; ?>" 
             class="pagination-btn <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>"
             style="text-decoration: none;">Next</a>
        </div>
        <?php endif; ?>
      </section>

      <?php endif; ?>
    </main>
  </div>

  <style>
    .pagination-btn {
      padding: 8px 14px;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      background: white;
      color: #475569;
      font-size: 14px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    .pagination-btn:hover:not(.disabled) {
      background: #f8fafc;
      border-color: #cbd5e1;
    }
    .pagination-btn.active {
      background: #3b82f6;
      color: white;
      border-color: #3b82f6;
    }
    .pagination-btn.disabled {
      opacity: 0.5;
      pointer-events: none;
      cursor: not-allowed;
    }
  </style>

  <script>
    function handleMapRedirect(lotId, lotNumber) {
      // Redirect to cemetery map page with highlighted lot parameter
      window.location.href = `cemetery-map.php?highlight_lot=${lotId}`;
    }
    
    document.getElementById('sectionFilter')?.addEventListener('change', function() {
      const section = this.value;
      const status = '<?php echo $filterStatus; ?>';
      window.location.href = '?status=' + status + (section ? '&section=' + encodeURIComponent(section) : '');
    });
  </script>
  <script src="../assets/js/app.js"></script>
</body>
</html>
