<?php
/**
 * Session Helper
 * Fungsi-fungsi untuk mengelola session dan autentikasi user
 */

/**
 * Start session jika belum dimulai
 */
function init_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Cek apakah konstanta BASE_URL sudah didefinisikan
 * Jika belum, definisikan dengan nilai default
 */
function ensure_base_url() {
    if (!defined('BASE_URL')) {
        // Deteksi base URL secara otomatis
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Deteksi apakah ada folder project dalam path
        $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
        $base_path = '';
        
        if (strpos($script_name, '/mbg/') !== false) {
            $base_path = '/mbg';
        }
        
        $base_url = rtrim($protocol . $host . $base_path, '/');
        define('BASE_URL', $base_url);
    }
}

/**
 * Fungsi base_url untuk menghasilkan URL lengkap
 */
function base_url($path = '') {
    ensure_base_url();
    $path = ltrim($path, '/');
    return $path ? BASE_URL . '/' . $path : BASE_URL;
}

/**
 * Cek apakah user sudah login
 */
function is_logged_in() {
    init_session();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Detect if request is AJAX
 */
function is_ajax() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Redirect ke login jika belum login
 * Jika AJAX, return JSON error
 */
function require_login() {
    if (!is_logged_in()) {
        if (is_ajax()) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'session_expired']);
            exit();
        }
        header('Location: ' . base_url('modules/auth/login.php'));
        exit();
    }
}

/**
 * Cek role user
 */
function check_role($allowed_roles = []) {
    init_session();
    
    if (!is_logged_in()) {
        return false;
    }
    
    if (empty($allowed_roles)) {
        return true;
    }
    
    $user_role = $_SESSION['user_role'] ?? '';
    
    if (is_array($allowed_roles)) {
        return in_array($user_role, $allowed_roles);
    }
    
    return $user_role === $allowed_roles;
}

/**
 * Require specific role atau redirect
 */
function require_role($allowed_roles = []) {
    require_login();
    
    if (!check_role($allowed_roles)) {
        header('Location: ' . base_url('modules/dashboard/index.php'));
        exit();
    }
}

/**
 * Get user data dari session
 */
function get_user_data($key = null) {
    init_session();
    
    if (!is_logged_in()) {
        return null;
    }
    
    if ($key === null) {
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'nama_lengkap' => $_SESSION['nama_lengkap'] ?? null,
            'email' => $_SESSION['email'] ?? null,
            'role' => $_SESSION['user_role'] ?? null,
            'kantor_id' => $_SESSION['kantor_id'] ?? null,
            'foto' => $_SESSION['foto'] ?? null
        ];
    }
    
    $mapping = [
        'id' => 'user_id',
        'role' => 'user_role'
    ];
    
    $session_key = $mapping[$key] ?? $key;
    
    return $_SESSION[$session_key] ?? null;
}

/**
 * Set user session setelah login
 */
function set_user_session($user_data) {
    init_session();
    
    $_SESSION['user_id'] = $user_data['id'];
    $_SESSION['username'] = $user_data['username'];
    $_SESSION['nama_lengkap'] = $user_data['nama_lengkap'];
    $_SESSION['email'] = $user_data['email'];
    $_SESSION['user_role'] = $user_data['role'];
    $_SESSION['kantor_id'] = $user_data['kantor_id'];
    $_SESSION['foto'] = $user_data['foto'];
    $_SESSION['login_time'] = time();
    
    // Juga simpan sebagai array untuk konsistensi
    $_SESSION['user'] = [
        'id' => $user_data['id'],
        'username' => $user_data['username'],
        'nama_lengkap' => $user_data['nama_lengkap'],
        'email' => $user_data['email'],
        'role' => $user_data['role'],
        'kantor_id' => $user_data['kantor_id'],
        'foto' => $user_data['foto']
    ];
}

/**
 * Destroy session dan logout
 */
function logout_user() {
    init_session();
    
    // Unset all session variables
    $_SESSION = [];
    
    // Destroy session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Destroy session
    session_destroy();
}

/**
 * Get user role label
 */
function get_role_label($role = null) {
    if ($role === null) {
        $role = get_user_data('role');
    }
    
    $labels = [
        'admin' => 'Administrator',
        'koperasi' => 'Staff Koperasi',
        'gudang' => 'Staff Gudang',
        'kantor' => 'Staff Kantor'
    ];
    
    return $labels[$role] ?? $role;
}

/**
 * Get user role badge class
 */
function get_role_badge($role = null) {
    if ($role === null) {
        $role = get_user_data('role');
    }
    
    $badges = [
        'admin' => 'danger',
        'koperasi' => 'primary',
        'gudang' => 'success',
        'kantor' => 'info'
    ];
    
    return $badges[$role] ?? 'secondary';
}

/**
 * Set flash message
 */
function set_flash_message($type, $message) {
    init_session();
    
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Show flash message
 */
function show_flash_message() {
    init_session();
    
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        $type = $flash['type'];
        $message = $flash['message'];
        
        $alert_class = 'alert-info';
        $icon = 'info-circle';
        
        switch ($type) {
            case 'success':
                $alert_class = 'alert-success';
                $icon = 'check-circle';
                break;
            case 'error':
                $alert_class = 'alert-danger';
                $icon = 'exclamation-circle';
                break;
            case 'warning':
                $alert_class = 'alert-warning';
                $icon = 'exclamation-triangle';
                break;
        }
        
        echo '<div class="alert ' . $alert_class . ' alert-dismissible fade show" role="alert">
                <i class="fas fa-' . $icon . ' me-2"></i>
                ' . htmlspecialchars($message) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
        
        unset($_SESSION['flash_message']);
    }
}

/**
 * Compatibility Wrappers
 * Untuk mendukung kode lama/style camelCase
 */
function checkLogin() {
    require_login();
}

function checkRole($allowed_roles = []) {
    require_role($allowed_roles);
}

function getUserData($key = null) {
    return get_user_data($key);
}


