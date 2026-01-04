<?php
/**
 * Cancel scraping process
 */

// START SESSION
session_start();

// Set JSON header
header('Content-Type: application/json');

// Check access
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'] ?? '', ['admin', 'koperasi'])) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit();
}

$lock_file = __DIR__ . '/scraping.lock';

if (file_exists($lock_file)) {
    // Log cancellation
    if (isset($_SESSION['user']['id'])) {
        require_once __DIR__ . '/../../config/database.php';
        try {
            $log_data = [
                'user_id' => $_SESSION['user']['id'],
                'activity' => 'Membatalkan proses scraping',
                'module' => 'scraping',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
            ];
            db_insert('log_activity', $log_data);
        } catch (Exception $e) {
            error_log("Failed to log cancellation: " . $e->getMessage());
        }
    }
    
    unlink($lock_file);
    
    echo json_encode(['success' => true, 'message' => 'Scraping dibatalkan']);
} else {
    echo json_encode(['success' => false, 'message' => 'Tidak ada proses scraping yang berjalan']);
}