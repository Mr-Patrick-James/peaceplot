<?php
require_once __DIR__ . '/../config/auth.php';
requireLogin();

$user = getUserInfo();
$userInitials = getInitials($user['full_name']);

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

$blocks = [];
if ($db) {
    try {
        $stmt = $db->query("SELECT * FROM blocks ORDER BY name ASC");
        $blocks = $stmt->fetchAll();
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
  <title>PeacePlot Admin - Block Management</title>
  <link rel="stylesheet" href="../assets/css/styles.css" />
  <style>
    /* Modal styles are already in styles.css, but we'll add some specific overrides for Block Management if needed */
    .modal-content {
      animation: modalSlideIn 0.3s ease;
    }
    
    @keyframes modalSlideIn {
      from { transform: translateY(-20px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }

    /* Form specific styles */
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #475569; font-size: 14px; }
    .form-group input, .form-group textarea { 
      width: 100%; 
      padding: 12px 16px; 
      border: 1px solid #e2e8f0; 
      border-radius: 10px; 
      font-size: 15px; 
      transition: all 0.2s; 
      outline: none;
      background: #fcfdfe;
    }
    .form-group input:focus, .form-group textarea:focus { 
      border-color: #3b82f6; 
      background: #fff;
      box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); 
    }

    /* Dropdown Styles */
    .dropdown-content { display: none; padding-left: 20px; }
    .dropdown.active .dropdown-content { display: block; }
    .dropdown-toggle { display: flex; align-items: center; justify-content: space-between; width: 100%; }
    .arrow { transition: transform 0.2s; }
    .dropdown.active .arrow { transform: rotate(180deg); }
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
            <a href="index.php"><span>Manage Lots</span></a>
            <a href="sections.php"><span>Manage Sections</span></a>
            <a href="blocks.php" class="active"><span>Manage Blocks</span></a>
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
              <path d="M8 6h8" /><path d="M8 10h8" />
            </svg>
          </span>
          <span>Burial Records</span>
        </a>

        <a href="reports.php">
          <span class="icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 3v18h18" /><path d="M7 14v4" /><path d="M11 10v8" /><path d="M15 6v12" /><path d="M19 12v6" />
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
      <div class="page-header">
        <h1 class="page-title">Block Management</h1>
        <button class="btn-primary" onclick="openAddModal()">
          <span class="icon" style="color:#fff">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 5v14" />
              <path d="M5 12h14" />
            </svg>
          </span>
          <span>Add New Block</span>
        </button>
      </div>

      <section class="card">
        <div class="card-head">
          <div>
            <h2 class="card-title">Cemetery Blocks</h2>
            <p class="card-sub">Manage and categorize cemetery lots by blocks</p>
          </div>
        </div>

        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th align="left">Block Name</th>
                <th align="left">Description</th>
                <th align="left">Created At</th>
                <th align="right">Actions</th>
              </tr>
            </thead>
            <tbody id="blocksTableBody">
              <?php foreach ($blocks as $block): ?>
                <tr>
                  <td><strong><?php echo htmlspecialchars($block['name'] ?? ''); ?></strong></td>
                  <td><?php echo htmlspecialchars($block['description'] ?? ''); ?></td>
                  <td><?php echo date('M d, Y', strtotime($block['created_at'])); ?></td>
                  <td align="right">
                    <div style="display: flex; justify-content: flex-end; gap: 8px;">
                      <button class="btn-action btn-edit" onclick='openEditModal(<?php echo $block['id']; ?>, <?php echo json_encode($block['name']); ?>, <?php echo json_encode($block['description']); ?>)'>
                        <span class="icon">
                          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 20h9" />
                            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" />
                          </svg>
                        </span>
                        <span>Edit</span>
                      </button>
                      <button class="btn-action btn-delete" onclick="deleteBlock(<?php echo $block['id']; ?>)">
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
              <?php if (empty($blocks)): ?>
                <tr>
                  <td colspan="4" style="text-align: center; padding: 60px; color: #64748b;">
                    <div style="margin-bottom: 12px; opacity: 0.5;">
                      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/><path d="M8 7v10"/><path d="M16 7v10"/></svg>
                    </div>
                    No blocks found. Add your first block to get started!
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>

  <!-- Add/Edit Modal -->
  <div id="blockModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 id="modalTitle" class="modal-title">Add New Block</h2>
        <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
      </div>
      <form id="blockForm">
        <div class="modal-body">
          <input type="hidden" id="blockId" name="id">
          <div class="form-group">
            <label for="name">Block Name</label>
            <input type="text" id="name" name="name" required placeholder="e.g. Block 1">
          </div>
          <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4" placeholder="Brief description of the block..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
          <button type="submit" class="btn-primary">Save Block</button>
        </div>
      </form>
    </div>
  </div>

  <script src="../assets/js/api.js"></script>
  <script src="../assets/js/blocks.js"></script>
</body>
</html>