<?php
/**
 * Scraping Worker - Runs the actual scraping process
 */

// START SESSION TERLEBIH DAHULU
session_start();

// Check access
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'] ?? '', ['admin', 'koperasi'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Akses ditolak. Silakan login terlebih dahulu.']);
    exit();
}

// Set longer timeout
set_time_limit(300); // 5 minutes
ini_set('max_execution_time', 300);
ignore_user_abort(true);

// Include database BEFORE getting parameters
require_once __DIR__ . '/../../config/database.php';

// Get parameters
$month = isset($_POST['month']) ? intval($_POST['month']) : date('n');
$year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');

// Validate parameters
if ($month < 1 || $month > 12) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Bulan tidak valid']);
    exit();
}

if ($year < 2020 || $year > date('Y')) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Tahun tidak valid']);
    exit();
}

// Simpan info user ke lock file
$lock_file = __DIR__ . '/scraping.lock';
$lock_data = [
    'started_at' => date('c'),
    'user_id' => $_SESSION['user']['id'] ?? null,
    'user_name' => $_SESSION['user']['nama_lengkap'] ?? 'Unknown',
    'pid' => getmypid(),
    'month' => $month,
    'year' => $year
];
file_put_contents($lock_file, json_encode($lock_data));

// Log start activity
if (isset($_SESSION['user']['id'])) {
    try {
        $log_data = [
            'user_id' => $_SESSION['user']['id'],
            'activity' => "Memulai scraping data harga pasar untuk {$month}-{$year}",
            'module' => 'scraping',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ];
        db_insert('log_activity', $log_data);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

// Return immediate response untuk AJAX
header('Content-Type: application/json');
ob_start();
echo json_encode(['success' => true, 'message' => 'Scraping dimulai', 'lock_created' => true]);
$size = ob_get_length();
header("Content-Length: $size");
ob_end_flush();
flush();

// Close connection to browser
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// ================================================
// PROSES SCRAPING DI BACKGROUND
// ================================================
try {
    // Include scraper class
    require_once __DIR__ . '/MarketPriceScraper.php';
    
    // Create scraper instance
    $scraper = new MarketPriceScraper();
    
    // Run scraping
    error_log("Starting scraping for {$month}-{$year}");
    $result = $scraper->runScheduledJob($month, $year);
    
    error_log("Scraping completed: " . json_encode($result));
    
    // Log completion activity
    if (isset($_SESSION['user']['id'])) {
        try {
            $log_data = [
                'user_id' => $_SESSION['user']['id'],
                'activity' => "Scraping selesai: {$result['successful_markets']}/{$result['total_markets']} pasar, {$result['total_commodities']} komoditas",
                'module' => 'scraping',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
            ];
            db_insert('log_activity', $log_data);
        } catch (Exception $e) {
            error_log("Failed to log completion: " . $e->getMessage());
        }
    }
    
} catch (Exception $e) {
    error_log("Scraping error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Log error to database
    if (isset($_SESSION['user']['id'])) {
        try {
            $log_data = [
                'user_id' => $_SESSION['user']['id'],
                'activity' => "Scraping error: " . $e->getMessage(),
                'module' => 'scraping',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
            ];
            db_insert('log_activity', $log_data);
        } catch (Exception $e2) {
            error_log("Failed to log error: " . $e2->getMessage());
        }
    }
}

// Remove lock file
if (file_exists($lock_file)) {
    unlink($lock_file);
    error_log("Lock file removed");
}