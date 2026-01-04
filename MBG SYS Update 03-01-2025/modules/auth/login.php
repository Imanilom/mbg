<?php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

// Redirect jika sudah login
if (is_logged_in()) {
    header('Location: ../dashboard/index.php');
    exit();
}

// Process login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = clean_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($username) || empty($password)) {
        set_flash('error', 'Username dan password harus diisi');
    } else {
        // Query user
        $query = "SELECT * FROM users 
                  WHERE username = ? 
                  AND status = 'aktif' LIMIT 1";
        
        $user = db_get_row($query, [$username]);
        
        if ($user) {
            $is_valid = false;
            // Check modern password hash first
            if (password_verify($password, $user['password'])) {
                $is_valid = true;
            } 
            // Fallback to legacy MD5 for migration
            elseif (md5($password) === $user['password']) {
                $is_valid = true;
                // Auto-upgrade to modern hash
                $new_hash = password_hash($password, PASSWORD_DEFAULT);
                db_update('users', ['password' => $new_hash], "id = ?", [$user['id']]);
            }

            if ($is_valid) {
                // Set session
                set_user_session($user);
                
                // Remember me
                if ($remember) {
                    setcookie('remember_user', $username, time() + (86400 * 30), '/');
                }
                
                // Log activity
                $log_data = [
                    'user_id' => $user['id'],
                    'activity' => 'Login ke sistem',
                    'module' => 'auth',
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
                ];
                db_insert('log_activity', $log_data);
                
                // Redirect ke dashboard
                header('Location: ../dashboard/index.php');
                exit();
            } else {
                set_flash('error', 'Username atau password salah');
            }
        } else {
            set_flash('error', 'Username atau password salah');
        }
    }
}

// Auto-fill username dari cookie
$remembered_user = $_COOKIE['remember_user'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Marketlist MBG</title>
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #6366f1;
            --bg-main: #f8fafc;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            max-width: 420px;
            width: 100%;
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(226, 232, 240, 0.8);
            overflow: hidden;
        }
        .login-header {
            padding: 48px 40px 32px;
            text-align: center;
        }
        .login-logo {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(96, 165, 250, 0.15);
            border: 2px solid var(--primary-light);
        }
        .login-header h3 {
            font-weight: 800;
            letter-spacing: -0.5px;
            color: #0f172a;
            margin-bottom: 8px;
        }
        .login-body { padding: 0 40px 48px; }
        .form-label { font-weight: 600; font-size: 0.875rem; color: #475569; margin-bottom: 8px; }
        .form-control {
            border-radius: 12px; padding: 12px 16px; border: 1px solid #e2e8f0;
            background: #f8fafc; transition: all 0.2s;
        }
        .form-control:focus {
            background: #fff; border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }
        .btn-login {
            background: var(--primary); color: white; border: none; padding: 14px;
            border-radius: 12px; font-weight: 700; transition: all 0.2s;
            box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.2);
            margin-top: 20px;
        }
        .btn-login:hover {
            background: #4f46e5; transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.3);
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <div class="login-logo"><img src="<?= BASE_URL ?>/assets/img/mbg.png" alt="MBG Logo" style="width: 48px; height: 48px; object-fit: contain;"></div>
            <h3>MBG System</h3>
            <p class="text-muted small">Silakan masuk ke akun Anda</p>
        </div>
        <div class="login-body">
            <?php show_flash(); ?>
            <form method="POST">
                <div class="mb-4">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($remembered_user) ?>" placeholder="Username" required autofocus>
                </div>
                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
                <div class="mb-4 form-check">
                    <input type="checkbox" name="remember" class="form-check-input" id="remember">
                    <label class="form-check-label small text-muted" for="remember">Ingat saya</label>
                </div>
                <button type="submit" class="btn btn-primary btn-login w-100">
                    Masuk ke Sistem <i class="fas fa-arrow-right ms-2"></i>
                </button>
            </form>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>