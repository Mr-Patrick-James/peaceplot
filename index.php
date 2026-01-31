<?php
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: public/dashboard.php');
    exit;
}

require_once __DIR__ . '/config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        $database = new Database();
        $conn = $database->getConnection();
        
        if ($conn) {
            try {
                $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username AND is_active = 1");
                $stmt->bindParam(':username', $username);
                $stmt->execute();
                $user = $stmt->fetch();
                
                // Note: In production, use password_hash() and password_verify()
                // For now, simple comparison (as per seed data)
                if ($user && $user['password_hash'] === $password) {
                    // Set session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Update last login
                    $updateStmt = $conn->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = :id");
                    $updateStmt->bindParam(':id', $user['id']);
                    $updateStmt->execute();
                    
                    // Redirect to dashboard
                    header('Location: public/dashboard.php');
                    exit;
                } else {
                    $error = 'Invalid username or password';
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        } else {
            $error = 'Database connection failed';
        }
    } else {
        $error = 'Please enter both username and password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PeacePlot - Login</title>
    <style>
        :root {
            --primary: #2f6df6;
            --primary-dark: #1e4fd6;
            --text: #1c2736;
            --muted: #6b7a90;
            --border: #e4edf6;
            --page: #eef3f9;
            --error: #ef4444;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 420px;
            padding: 40px;
            animation: slideUp 0.5s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--primary), #764ba2);
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }
        
        .logo-icon svg {
            width: 36px;
            height: 36px;
            stroke: white;
        }
        
        .logo-title {
            font-size: 28px;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 6px;
        }
        
        .logo-subtitle {
            font-size: 14px;
            color: var(--muted);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 14px;
            color: var(--text);
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.2s;
            font-family: inherit;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(47, 109, 246, 0.1);
        }
        
        .error-message {
            background: #fee;
            color: var(--error);
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid var(--error);
        }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
        }
        
        .btn-login:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(47, 109, 246, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .login-footer {
            margin-top: 24px;
            text-align: center;
            font-size: 13px;
            color: var(--muted);
        }
        
        .default-credentials {
            margin-top: 20px;
            padding: 16px;
            background: #f0f9ff;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }
        
        .default-credentials-title {
            font-weight: 600;
            color: var(--text);
            margin-bottom: 8px;
            font-size: 13px;
        }
        
        .default-credentials-info {
            font-size: 13px;
            color: var(--muted);
            line-height: 1.6;
        }
        
        .default-credentials-info strong {
            color: var(--text);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <div class="logo-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                    <circle cx="12" cy="10" r="3" />
                </svg>
            </div>
            <div class="logo-title">PeacePlot</div>
            <div class="logo-subtitle">Cemetery Management System</div>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    placeholder="Enter your username"
                    value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                    required
                    autofocus
                >
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    placeholder="Enter your password"
                    required
                >
            </div>
            
            <button type="submit" class="btn-login">Sign In</button>
        </form>
        
        <div class="default-credentials">
            <div class="default-credentials-title">Default Login Credentials</div>
            <div class="default-credentials-info">
                <strong>Username:</strong> admin<br>
                <strong>Password:</strong> admin123
            </div>
        </div>
        
        <div class="login-footer">
            &copy; <?php echo date('Y'); ?> PeacePlot. All rights reserved.
        </div>
    </div>
</body>
</html>
