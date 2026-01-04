<?php
/**
 * Application Constants
 */

// Base URL Configuration
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Auto-detect project folder
$script_name = $_SERVER['SCRIPT_NAME'] ?? '';
$base_path = '';

if (strpos($script_name, '/mbg/') !== false) {
    $base_path = '/mbg';
}

// Define BASE_URL
define('BASE_URL', rtrim($protocol . $host . $base_path, '/'));

// Application paths
define('BASE_PATH', dirname(__DIR__));

// Database configuration - only define if not already defined
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', '');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'marketlist_mbg');
}

// Application Paths
define('APP_PATH', dirname(dirname(__FILE__)));
define('ROOT_PATH', dirname(APP_PATH));
define('UPLOAD_PATH', APP_PATH . '/uploads');

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Session configuration - only set if session is not active
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
    ini_set('session.cookie_samesite', 'Lax');
}

// Debug mode (set to false in production)
define('DEBUG_MODE', true);

// Version
define('APP_VERSION', '1.0.0');


if (!function_exists('generate_number')) {
    /**
     * Generate nomor otomatis berdasarkan format
     * Format: PREFIX/[TAHUN]/[BULAN]/[NOMOR]
     */
    function generate_number($prefix, $table, $field) {
        $year = date('Y');
        $month = date('m');
        
        $query = "SELECT {$field} FROM {$table} 
                  WHERE {$field} LIKE '{$prefix}/{$year}/{$month}/%' 
                  ORDER BY id DESC LIMIT 1";
        
        $result = db_get_row($query);
        
        if ($result) {
            // Extract nomor terakhir
            $last_number = $result[$field];
            $parts = explode('/', $last_number);
            $number = intval($parts[3] ?? 0) + 1;
        } else {
            $number = 1;
        }
        
        return sprintf('%s/%s/%s/%03d', $prefix, $year, $month, $number);
    }
}

if (!function_exists('format_tanggal')) {
    /**
     * Format tanggal ke format Indonesia
     */
    function format_tanggal($date, $format = 'd F Y') {
        if (empty($date) || $date == '0000-00-00') {
            return '-';
        }
        
        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        
        $timestamp = strtotime($date);
        $d = date('d', $timestamp);
        $m = date('n', $timestamp);
        $y = date('Y', $timestamp);
        
        if ($format == 'd F Y') {
            return $d . ' ' . $bulan[$m] . ' ' . $y;
        } elseif ($format == 'd M Y') {
            return $d . ' ' . substr($bulan[$m], 0, 3) . ' ' . $y;
        } else {
            return date($format, $timestamp);
        }
    }
}

if (!function_exists('format_datetime')) {
    /**
     * Format datetime ke format Indonesia
     */
    function format_datetime($datetime) {
        if (empty($datetime) || $datetime == '0000-00-00 00:00:00') {
            return '-';
        }
        
        return format_tanggal($datetime) . ' ' . date('H:i', strtotime($datetime));
    }
}

if (!function_exists('format_rupiah')) {
    /**
     * Format number ke Rupiah
     */
    function format_rupiah($amount) {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}

if (!function_exists('format_number')) {
    /**
     * Format number biasa
     */
    function format_number($number, $decimals = 0) {
        return number_format($number, $decimals, ',', '.');
    }
}

if (!function_exists('clean_input')) {
    /**
     * Sanitize input untuk mencegah XSS
     */
    function clean_input($data) {
        if (is_array($data)) {
            return array_map('clean_input', $data);
        }
        
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        
        return $data;
    }
}

if (!function_exists('upload_file')) {
    /**
     * Upload file dengan validasi
     */
    function upload_file($file, $folder = 'uploads', $allowed_types = ['jpg', 'jpeg', 'png', 'pdf']) {
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return ['success' => false, 'message' => 'Tidak ada file yang diupload'];
        }
        
        $upload_dir = __DIR__ . '/../assets/uploads/' . $folder . '/';
        
        // Create directory if not exists
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_name = $file['name'];
        $file_size = $file['size'];
        $file_tmp = $file['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Validate extension
        if (!in_array($file_ext, $allowed_types)) {
            return ['success' => false, 'message' => 'Tipe file tidak diizinkan'];
        }
        
        // Validate MIME type
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file_tmp);
            finfo_close($finfo);
            
            $allowed_mimes = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'pdf' => 'application/pdf'
            ];
            
            $expected_mime = $allowed_mimes[$file_ext] ?? null;
            if ($expected_mime && $mime !== $expected_mime) {
                return ['success' => false, 'message' => 'Konten file tidak sesuai dengan eksistensi'];
            }
        }
        
        // Validate size (max 5MB)
        if ($file_size > 5 * 1024 * 1024) {
            return ['success' => false, 'message' => 'Ukuran file terlalu besar (max 5MB)'];
        }
        
        // Generate unique filename
        $new_file_name = time() . '_' . uniqid() . '.' . $file_ext;
        $upload_path = $upload_dir . $new_file_name;
        
        // Move file
        if (move_uploaded_file($file_tmp, $upload_path)) {
            return [
                'success' => true,
                'file_name' => $new_file_name,
                'file_path' => $folder . '/' . $new_file_name
            ];
        }
        
        return ['success' => false, 'message' => 'Gagal mengupload file'];
    }
}

if (!function_exists('generate_qr_code')) {
    /**
     * Generate QR Code
     * Menggunakan library phpqrcode
     */
    function generate_qr_code($data, $filename = null) {
        require_once __DIR__ . '/../vendor/phpqrcode/qrlib.php';
        
        $qr_dir = __DIR__ . '/../assets/uploads/qrcode/';
        
        if (!is_dir($qr_dir)) {
            mkdir($qr_dir, 0755, true);
        }
        
        if ($filename === null) {
            $filename = 'qr_' . time() . '_' . uniqid() . '.png';
        }
        
        $file_path = $qr_dir . $filename;
        
        // Generate QR Code
        // Parameters: data, filename, error correction level, size, margin
        QRcode::png($data, $file_path, QR_ECLEVEL_L, 10, 2);
        
        return 'qrcode/' . $filename;
    }
}

if (!function_exists('set_flash')) {
    /**
     * Alert/Notification Session
     */
    function set_flash($type, $message) {
        init_session();
        $_SESSION['flash_type'] = $type;
        $_SESSION['flash_message'] = $message;
    }
}

if (!function_exists('get_flash')) {
    function get_flash() {
        init_session();
        
        if (isset($_SESSION['flash_message'])) {
            $flash = [
                'type' => $_SESSION['flash_type'] ?? 'info',
                'message' => $_SESSION['flash_message']
            ];
            
            unset($_SESSION['flash_type']);
            unset($_SESSION['flash_message']);
            
            return $flash;
        }
        
        return null;
    }
}

if (!function_exists('show_flash')) {
    function show_flash() {
        $flash = get_flash();
        
        if ($flash) {
            $alert_class = [
                'success' => 'alert-success',
                'error' => 'alert-danger',
                'warning' => 'alert-warning',
                'info' => 'alert-info'
            ];
            
            $icon = [
                'success' => 'check-circle',
                'error' => 'exclamation-circle',
                'warning' => 'exclamation-triangle',
                'info' => 'info-circle'
            ];
            
            $class = $alert_class[$flash['type']] ?? 'alert-info';
            $ic = $icon[$flash['type']] ?? 'info-circle';
            
            echo '<div class="alert ' . $class . ' alert-dismissible fade show" role="alert">';
            echo '<i class="fas fa-' . $ic . ' me-2"></i>';
            echo clean_input($flash['message']);
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            echo '</div>';
        }
    }
}

if (!function_exists('get_status_badge')) {
    /**
     * Get status badge
     */
    function get_status_badge($status, $type = 'request') {
        $badges = [
            'request' => [
                'pending' => '<span class="badge bg-warning-subtle text-warning rounded-pill px-3 shadow-none border-0">PENDING</span>',
                'diproses' => '<span class="badge bg-info-subtle text-info rounded-pill px-3 shadow-none border-0">DIPROSES</span>',
                'selesai' => '<span class="badge bg-success-subtle text-success rounded-pill px-3 shadow-none border-0">SELESAI</span>',
                'ditolak' => '<span class="badge bg-danger-subtle text-danger rounded-pill px-3 shadow-none border-0">DITOLAK</span>'
            ],
            'distribusi' => [
                'dikirim' => '<span class="badge bg-primary-subtle text-primary rounded-pill px-3 shadow-none border-0 ls-1">DIKIRIM</span>',
                'diterima' => '<span class="badge bg-success-subtle text-success rounded-pill px-3 shadow-none border-0 ls-1">DITERIMA</span>',
                'bermasalah' => '<span class="badge bg-danger-subtle text-danger rounded-pill px-3 shadow-none border-0 ls-1">BERMASALAH</span>'
            ],
            'piutang' => [
                'belum_lunas' => '<span class="badge bg-warning-subtle text-warning rounded-pill px-3 shadow-none border-0">BELUM LUNAS</span>',
                'lunas' => '<span class="badge bg-success-subtle text-success rounded-pill px-3 shadow-none border-0">LUNAS</span>'
            ],
            'aktif' => [
                'aktif' => '<span class="badge bg-success-subtle text-success rounded-pill px-3 shadow-none border-0">AKTIF</span>',
                'nonaktif' => '<span class="badge bg-secondary-subtle text-secondary rounded-pill px-3 shadow-none border-0">NON-AKTIF</span>'
            ],
            'pembelanjaan' => [
                'pending' => '<span class="badge bg-warning-subtle text-warning rounded-pill px-3 shadow-none border-0">PENDING</span>',
                'selesai' => '<span class="badge bg-success-subtle text-success rounded-pill px-3 shadow-none border-0">SELESAI</span>',
                'batal' => '<span class="badge bg-danger-subtle text-danger rounded-pill px-3 shadow-none border-0">BATAL</span>'
            ],
            'menu' => [
                'draft' => '<span class="badge bg-secondary rounded-pill px-3">DRAFT</span>',
                'approved' => '<span class="badge bg-primary rounded-pill px-3">APPROVED</span>',
                'processing' => '<span class="badge bg-warning text-dark rounded-pill px-3">PROCESSING</span>',
                'completed' => '<span class="badge bg-success rounded-pill px-3">COMPLETED</span>',
                'cancelled' => '<span class="badge bg-danger rounded-pill px-3">CANCELLED</span>'
            ]
        ];
        
        return $badges[$type][$status] ?? '<span class="badge bg-light text-dark rounded-pill px-3 shadow-none border-0">' . strtoupper($status) . '</span>';
    }
}

if (!function_exists('generate_pagination')) {
    /**
     * Pagination helper
     */
    function generate_pagination($total_records, $per_page, $current_page, $base_url) {
        $total_pages = ceil($total_records / $per_page);
        
        if ($total_pages <= 1) {
            return '';
        }
        
        $html = '<nav><ul class="pagination justify-content-center">';
        
        // Previous
        if ($current_page > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . '&page=' . ($current_page - 1) . '">Previous</a></li>';
        }
        
        // Pages
        for ($i = 1; $i <= $total_pages; $i++) {
            $active = ($i == $current_page) ? 'active' : '';
            $html .= '<li class="page-item ' . $active . '"><a class="page-link" href="' . $base_url . '&page=' . $i . '">' . $i . '</a></li>';
        }
        
        // Next
        if ($current_page < $total_pages) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . '&page=' . ($current_page + 1) . '">Next</a></li>';
        }
        
        $html .= '</ul></nav>';
        
        return $html;
    }
}

if (!function_exists('logActivity')) {
    /**
     * Log activity user
     */
    function logActivity($conn, $user_id, $description, $module, $ref_id = 0) {
        if (empty($user_id)) return false;
        
        $user_id = mysqli_real_escape_string($conn, $user_id);
        $description = mysqli_real_escape_string($conn, $description);
        $module = mysqli_real_escape_string($conn, $module);
        $ref_id = (int) $ref_id;
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT'] ?? '-');
        
        $query = "INSERT INTO logs (user_id, description, module, ref_id, ip_address, user_agent, created_at) 
                  VALUES ('$user_id', '$description', '$module', '$ref_id', '$ip_address', '$user_agent', NOW())";
                  
        return mysqli_query($conn, $query);
    }
}
?>