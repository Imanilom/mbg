<?php
/**
 * Check scraping status
 */

// START SESSION (untuk logging)
session_start();

$lock_file = __DIR__ . '/scraping.lock';

header('Content-Type: application/json');

if (!file_exists($lock_file)) {
    echo json_encode(['status' => 'completed']);
    exit();
}

// Check lock file age
$lock_time = filemtime($lock_file);
if (time() - $lock_time > 600) { // 10 minutes timeout
    @unlink($lock_file);
    
    // Log timeout
    if (isset($_SESSION['user']['id'])) {
        require_once __DIR__ . '/../../config/database.php';
        try {
            $log_data = [
                'user_id' => $_SESSION['user']['id'],
                'activity' => 'Scraping timeout (lebih dari 10 menit)',
                'module' => 'scraping',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
            ];
            db_insert('log_activity', $log_data);
        } catch (Exception $e) {
            error_log("Failed to log timeout: " . $e->getMessage());
        }
    }
    
    echo json_encode(['status' => 'timeout', 'message' => 'Proses timeout setelah 10 menit']);
    exit();
}

// Check lock content
$lock_content = @file_get_contents($lock_file);
if ($lock_content) {
    $lock_data = @json_decode($lock_content, true);
    if ($lock_data) {
        $start_time = strtotime($lock_data['started_at']);
        $elapsed = time() - $start_time;
        
        echo json_encode([
            'status' => 'running', 
            'lock_time' => date('Y-m-d H:i:s', $lock_time),
            'elapsed' => $elapsed . ' seconds',
            'started_by' => $lock_data['user_name'] ?? 'Unknown',
            'progress' => min(floor($elapsed / 3), 95) . '%' // Simulasi progress
        ]);
        exit();
    }
}

echo json_encode(['status' => 'running', 'lock_time' => date('Y-m-d H:i:s', $lock_time)]);