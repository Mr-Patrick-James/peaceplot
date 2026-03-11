<?php
require_once __DIR__ . '/../config/auth.php';
requireLogin();

$user = getUserInfo();
$userInitials = getInitials($user['full_name']);
$isAdmin = ($user['role'] === 'admin');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/logger.php';

$database = new Database();
$conn = $database->getConnection();

$error = '';
$success = '';

// Handle actions
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Change Password
    if (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match';
        } else {
            // Verify current password
            $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = :id");
            $stmt->bindParam(':id', $user['id']);
            $stmt->execute();
            $userData = $stmt->fetch();
            
            if ($userData && $userData['password_hash'] === $currentPassword) {
                $updateStmt = $conn->prepare("UPDATE users SET password_hash = :password WHERE id = :id");
                $updateStmt->bindParam(':password', $newPassword);
                $updateStmt->bindParam(':id', $user['id']);
                if ($updateStmt->execute()) {
                    logActivity($conn, 'CHANGE_PASSWORD', 'users', $user['id'], "User changed their password");
                    $success = 'Password updated successfully';
                } else {
                    $error = 'Failed to update password';
                }
            } else {
                $error = 'Incorrect current password';
            }
        }
    }
    
    // 2. Add New User (Admin Only)
    if (isset($_POST['add_user']) && $isAdmin) {
        $newUsername = $_POST['username'] ?? '';
        $newFullName = $_POST['full_name'] ?? '';
        $newEmail = $_POST['email'] ?? '';
        $newPassword = $_POST['password'] ?? '';
        $newRole = $_POST['role'] ?? 'staff';
        
        if ($newUsername && $newPassword) {
            $stmt = $conn->prepare("INSERT INTO users (username, password_hash, full_name, email, role) VALUES (:username, :password, :full_name, :email, :role)");
            $stmt->bindParam(':username', $newUsername);
            $stmt->bindParam(':password', $newPassword);
            $stmt->bindParam(':full_name', $newFullName);
            $stmt->bindParam(':email', $newEmail);
            $stmt->bindParam(':role', $newRole);
            
            if ($stmt->execute()) {
                $newUserId = $conn->lastInsertId();
                logActivity($conn, 'ADD_USER', 'users', $newUserId, "Admin added new user: $newUsername ($newRole)");
                $success = 'User account created successfully';
            } else {
                $error = 'Failed to add user. Username might already exist.';
            }
        }
    }

    // 3. Delete User (Admin Only)
    if (isset($_POST['delete_user']) && $isAdmin) {
        $userIdToDelete = $_POST['user_id'] ?? '';
        if ($userIdToDelete == $user['id']) {
            $error = "You cannot delete your own account";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = :id");
            $stmt->bindParam(':id', $userIdToDelete);
            if ($stmt->execute()) {
                logActivity($conn, 'DELETE_USER', 'users', $userIdToDelete, "Admin deleted user ID: $userIdToDelete");
                $success = 'User account removed successfully';
            } else {
                $error = 'Failed to delete user';
            }
        }
    }
}

// 4. Database Export (Admin Only)
if ($action === 'export_db' && $isAdmin) {
    $dbPath = __DIR__ . '/../database/peaceplot.db';
    if (file_exists($dbPath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/x-sqlite3');
        header('Content-Disposition: attachment; filename="peaceplot_backup_' . date('Y-m-d_His') . '.db"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($dbPath));
        readfile($dbPath);
        exit;
    } else {
        $error = 'Database file not found';
    }
}

// Fetch all users for Admin tab
$allUsers = [];
if ($conn && $isAdmin) {
    $stmt = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
    $allUsers = $stmt->fetchAll();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>PeacePlot Admin - System Settings</title>
  <link rel="stylesheet" href="../assets/css/styles.css" />
  <style>
    :root {
        --settings-bg: #f5f7fb;
        --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        --input-focus: #3b82f6;
        --tab-active: #2f6df6;
        --tab-inactive: #64748b;
        --danger: #ef4444;
        --success: #10b981;
    }

    .settings-container {
        max-width: 1000px;
        margin: 0 auto;
        padding: 20px;
    }

    .modern-card {
        background: white;
        border-radius: 20px;
        box-shadow: var(--card-shadow);
        overflow: hidden;
        border: 1px solid #e2e8f0;
    }

    .settings-header {
        padding: 30px 40px;
        border-bottom: 1px solid #f1f5f9;
    }

    .settings-title {
        font-size: 24px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 25px;
    }

    /* Tabs Styling */
    .tabs-nav {
        display: flex;
        gap: 30px;
        border-bottom: 1px solid #f1f5f9;
        margin-bottom: 0;
    }

    .tab-link {
        padding: 12px 0;
        font-size: 15px;
        font-weight: 600;
        color: var(--tab-inactive);
        text-decoration: none;
        position: relative;
        transition: all 0.2s;
        cursor: pointer;
        background: none;
        border: none;
        outline: none;
    }

    .tab-link:hover {
        color: #1e293b;
    }

    .tab-link.active {
        color: var(--tab-active);
    }

    .tab-link.active::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 0;
        right: 0;
        height: 2px;
        background: var(--tab-active);
    }

    /* Content Area */
    .settings-content {
        padding: 40px;
    }

    .tab-panel {
        display: none;
        animation: fadeIn 0.3s ease;
    }

    .tab-panel.active {
        display: block;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(5px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Form Styling */
    .form-section {
        margin-bottom: 40px;
    }

    .section-label {
        font-size: 14px;
        font-weight: 600;
        color: #64748b;
        margin-bottom: 12px;
        display: block;
    }

    .modern-input-group {
        margin-bottom: 25px;
    }

    .modern-input {
        width: 100%;
        padding: 14px 18px;
        border: 1.5px solid #e2e8f0;
        border-radius: 12px;
        font-size: 15px;
        color: #1e293b;
        background: #fcfdfe;
        transition: all 0.2s;
        outline: none;
    }

    .modern-input:focus {
        border-color: var(--input-focus);
        background: white;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    }

    .modern-input:disabled {
        background: #f8fafc;
        color: #94a3b8;
        cursor: not-allowed;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    /* Button Styling */
    .modern-btn {
        padding: 12px 24px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-primary-modern {
        background: #2f6df6;
        color: white;
    }

    .btn-primary-modern:hover {
        background: #1e4fd6;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(47, 109, 246, 0.2);
    }

    .btn-danger-modern {
        background: #fee2e2;
        color: #ef4444;
    }

    .btn-danger-modern:hover {
        background: #fecaca;
        color: #dc2626;
    }

    .btn-secondary-modern {
        background: #f1f5f9;
        color: #475569;
    }

    .btn-secondary-modern:hover {
        background: #e2e8f0;
    }

    /* Team List */
    .team-grid {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .user-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 15px 20px;
        background: #f8fafc;
        border-radius: 15px;
        border: 1px solid #f1f5f9;
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .user-avatar-sm {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        background: var(--primary);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 14px;
    }

    .user-details h4 {
        margin: 0;
        font-size: 15px;
        color: #1e293b;
    }

    .user-details p {
        margin: 0;
        font-size: 13px;
        color: #64748b;
    }

    .badge-modern {
        padding: 4px 10px;
        border-radius: 8px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }

    /* Modal-like dialogs */
    .modern-dialog {
        background: #1c2128;
        color: white;
        border-radius: 16px;
        padding: 30px;
        max-width: 400px;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 1000;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        display: none;
    }

    .dialog-title {
        font-size: 20px;
        font-weight: 700;
        margin-bottom: 15px;
    }

    .dialog-text {
        color: #94a3b8;
        font-size: 14px;
        line-height: 1.6;
        margin-bottom: 25px;
    }

    .dialog-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
    }

    /* Sidebar Footer Clickable State */
    .sidebar-footer .user.active {
        background: rgba(255,255,255,0.1);
    }

    /* Alerts */
    .status-alert {
        padding: 16px 20px;
        border-radius: 12px;
        margin-bottom: 30px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 14px;
        font-weight: 500;
    }

    .status-alert.error { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
    .status-alert.success { background: #ecfdf5; color: #047857; border: 1px solid #d1fae5; }
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
        <a href="index.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16" /><path d="M4 12h16" /><path d="M4 17h16" /><path d="M8 7v10" /><path d="M16 7v10" /></svg></span><span>Lot Management</span></a>
        <a href="lot-availability.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20" /><path d="M2 12h20" /><path d="M4 4l16 16" /></svg></span><span>Lots</span></a>
        <a href="cemetery-map.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2V6z" /><path d="M9 4v14" /><path d="M15 6v14" /></svg></span><span>Cemetery Map</span></a>
        <a href="map-editor.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" /><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" /></svg></span><span>Map Editor</span></a>
        <a href="burial-records.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" /><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" /><path d="M8 6h8" /><path d="M8 10h8" /></svg></span><span>Burial Records</span></a>
        <a href="reports.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18" /><path d="M7 14v4" /><path d="M11 10v8" /><path d="M15 6v12" /><path d="M19 12v6" /></svg></span><span>Reports</span></a>
        <a href="history.php"><span class="icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span><span>History</span></a>
      </nav>

      <div class="sidebar-footer">
        <div class="user active" onclick="window.location.href='settings.php'" style="cursor:pointer; transition: background 0.2s ease; border-radius: 12px; padding: 10px; margin-bottom: 10px;">
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
      <div class="settings-container">
        <div class="modern-card">
          <div class="settings-header">
            <h1 class="settings-title">System Settings</h1>
            <div class="tabs-nav">
              <button class="tab-link active" onclick="switchTab('profile')">My Profile</button>
              <?php if ($isAdmin): ?>
                <button class="tab-link" onclick="switchTab('admin')">Admin Management</button>
                <button class="tab-link" onclick="switchTab('database')">Database</button>
              <?php endif; ?>
            </div>
          </div>

          <div class="settings-content">
            <?php if ($error): ?>
              <div class="status-alert error">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?php echo htmlspecialchars($error); ?>
              </div>
            <?php endif; ?>

            <?php if ($success): ?>
              <div class="status-alert success">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <?php echo htmlspecialchars($success); ?>
              </div>
            <?php endif; ?>

            <!-- Profile Tab -->
            <div id="profile-tab" class="tab-panel active">
              <div class="form-section">
                <span class="section-label">PERSONAL INFORMATION</span>
                <div class="form-row">
                  <div class="modern-input-group">
                    <label class="section-label">Full Name</label>
                    <input type="text" class="modern-input" value="<?php echo htmlspecialchars($user['full_name']); ?>" disabled>
                  </div>
                  <div class="modern-input-group">
                    <label class="section-label">Email address</label>
                    <input type="email" class="modern-input" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                  </div>
                </div>
              </div>

              <div class="form-section" style="margin-bottom: 0;">
                <span class="section-label">SECURITY</span>
                <form method="POST">
                  <div class="modern-input-group">
                    <label class="section-label">Current password</label>
                    <input type="password" name="current_password" class="modern-input" placeholder="••••••••" required>
                  </div>
                  <div class="form-row">
                    <div class="modern-input-group">
                      <label class="section-label">New password</label>
                      <input type="password" name="new_password" class="modern-input" placeholder="Minimum 8 characters" required>
                    </div>
                    <div class="modern-input-group">
                      <label class="section-label">Confirm new password</label>
                      <input type="password" name="confirm_password" class="modern-input" placeholder="Re-type new password" required>
                    </div>
                  </div>
                  <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 10px;">
                    <button type="submit" name="change_password" class="modern-btn btn-primary-modern">Update Password</button>
                  </div>
                </form>
              </div>
            </div>

            <?php if ($isAdmin): ?>
              <!-- Admin Tab -->
              <div id="admin-tab" class="tab-panel">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                  <span class="section-label" style="margin-bottom: 0;">ADMINISTRATORS & STAFF</span>
                  <button class="modern-btn btn-primary-modern" onclick="showAddUserModal()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="14" y2="12"/></svg>
                    Add User
                  </button>
                </div>

                <div class="team-grid">
                  <?php foreach ($allUsers as $u): ?>
                    <div class="user-row">
                      <div class="user-info">
                        <div class="user-avatar-sm"><?php echo getInitials($u['full_name']); ?></div>
                        <div class="user-details">
                          <h4><?php echo htmlspecialchars($u['full_name']); ?></h4>
                          <p><?php echo htmlspecialchars($u['email'] ?: $u['username']); ?></p>
                        </div>
                      </div>
                      <div style="display: flex; align-items: center; gap: 20px;">
                        <span class="badge-modern role-<?php echo $u['role']; ?>"><?php echo $u['role']; ?></span>
                        <div style="text-align: right; min-width: 120px;">
                          <div style="font-size: 12px; color: #64748b;">Last Login</div>
                          <div style="font-size: 13px; font-weight: 500; color: #1e293b;"><?php echo $u['last_login'] ? date('M d, Y', strtotime($u['last_login'])) : 'Never'; ?></div>
                        </div>
                        <?php if ($u['id'] != $user['id']): ?>
                          <button class="modern-btn btn-danger-modern" style="padding: 10px;" onclick="confirmDeleteUser(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['full_name']); ?>')">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                          </button>
                        <?php else: ?>
                          <div style="width: 38px;"></div>
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>

              <!-- Database Tab -->
              <div id="database-tab" class="tab-panel">
                <div class="form-section">
                  <span class="section-label">DATABASE MAINTENANCE</span>
                  <div style="background: #f8fafc; border-radius: 16px; padding: 30px; border: 1px dashed #cbd5e1; text-align: center;">
                    <div style="width: 60px; height: 60px; background: #e0f2fe; color: #0284c7; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                      <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                    </div>
                    <h3 style="margin: 0 0 10px; font-size: 18px; color: #1e293b;">Full System Backup</h3>
                    <p style="color: #64748b; font-size: 14px; max-width: 400px; margin: 0 auto 25px;">
                      Download the complete PeacePlot database file containing all lot records, burial records, and system history.
                    </p>
                    <a href="settings.php?action=export_db" class="modern-btn btn-primary-modern" style="text-decoration: none;">
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                      Download Database (.db)
                    </a>
                  </div>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Delete User Dialog (Black style like image) -->
  <div id="delete-dialog" class="modern-dialog">
    <div style="width: 48px; height: 48px; background: #2d333b; color: white; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 20px;">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
    </div>
    <div class="dialog-title">Are you sure?</div>
    <div class="dialog-text">
      You are going to permanently delete <strong id="delete-user-name" style="color: white;"></strong>'s account. This action cannot be undone.
    </div>
    <div class="dialog-actions">
      <button class="modern-btn btn-secondary-modern" style="background: #2d333b; color: #94a3b8;" onclick="closeDialog('delete-dialog')">Cancel</button>
      <form method="POST" style="margin: 0;">
        <input type="hidden" name="user_id" id="delete-user-id">
        <button type="submit" name="delete_user" class="modern-btn btn-primary-modern" style="background: #2f6df6;">Delete Account</button>
      </form>
    </div>
  </div>

  <!-- Add User Modal (Standard styling) -->
  <div id="add-user-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.4); z-index: 999; align-items: center; justify-content: center;">
    <div class="modern-card" style="width: 100%; max-width: 500px;">
      <div class="settings-header" style="padding: 25px 35px;">
        <h2 style="font-size: 20px; margin: 0;">Create New User Account</h2>
      </div>
      <form method="POST" style="padding: 35px;">
        <div class="modern-input-group">
          <label class="section-label">Full Name</label>
          <input type="text" name="full_name" class="modern-input" required>
        </div>
        <div class="modern-input-group">
          <label class="section-label">Username</label>
          <input type="text" name="username" class="modern-input" required>
        </div>
        <div class="modern-input-group">
          <label class="section-label">Email address</label>
          <input type="email" name="email" class="modern-input">
        </div>
        <div class="form-row">
          <div class="modern-input-group">
            <label class="section-label">Initial password</label>
            <input type="password" name="password" class="modern-input" required>
          </div>
          <div class="modern-input-group">
            <label class="section-label">Role</label>
            <select name="role" class="modern-input">
              <option value="staff">Staff Member</option>
              <option value="admin">Administrator</option>
              <option value="viewer">Viewer Only</option>
            </select>
          </div>
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 10px;">
          <button type="button" class="modern-btn btn-secondary-modern" onclick="closeModal('add-user-modal')">Cancel</button>
          <button type="submit" name="add_user" class="modern-btn btn-primary-modern">Create Account</button>
        </div>
      </form>
    </div>
  </div>

  <div id="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.4); z-index: 998;" onclick="closeAllModals()"></div>

  <script>
    function switchTab(tabId) {
        // Update nav
        document.querySelectorAll('.tab-link').forEach(link => {
            link.classList.remove('active');
            if (link.textContent.toLowerCase().includes(tabId)) {
                link.classList.add('active');
            }
        });
        
        // Update panels
        document.querySelectorAll('.tab-panel').forEach(panel => {
            panel.classList.remove('active');
        });
        document.getElementById(tabId + '-tab').classList.add('active');
        
        // Save to URL for persistence on refresh
        const url = new URL(window.location);
        url.searchParams.set('tab', tabId);
        window.history.pushState({}, '', url);
    }

    // Check URL for active tab on load
    window.onload = function() {
        const urlParams = new URLSearchParams(window.location.search);
        const tab = urlParams.get('tab');
        if (tab && document.getElementById(tab + '-tab')) {
            switchTab(tab);
        }
    };

    function showAddUserModal() {
        document.getElementById('add-user-modal').style.display = 'flex';
        document.getElementById('modal-overlay').style.display = 'block';
    }

    function confirmDeleteUser(id, name) {
        document.getElementById('delete-user-id').value = id;
        document.getElementById('delete-user-name').textContent = name;
        document.getElementById('delete-dialog').style.display = 'block';
        document.getElementById('modal-overlay').style.display = 'block';
    }

    function closeDialog(id) {
        document.getElementById(id).style.display = 'none';
        document.getElementById('modal-overlay').style.display = 'none';
    }

    function closeModal(id) {
        document.getElementById(id).style.display = 'none';
        document.getElementById('modal-overlay').style.display = 'none';
    }

    function closeAllModals() {
        document.getElementById('add-user-modal').style.display = 'none';
        document.getElementById('delete-dialog').style.display = 'none';
        document.getElementById('modal-overlay').style.display = 'none';
    }
  </script>
</body>
</html>
