<?php
/**
 * Create scraping lock file
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

// Check if already running
if (file_exists($lock_file)) {
    // Check if stale (older than 10 minutes)
    $lock_time = filemtime($lock_file);
    if (time() - $lock_time > 600) {
        unlink($lock_file);
    } else {
        echo json_encode(['success' => false, 'error' => 'Scraping sudah berjalan']);
        exit();
    }
}

$data = [
    'started_at' => date('c'),
    'user_id' => $_SESSION['user']['id'] ?? null,
    'user_name' => $_SESSION['user']['nama_lengkap'] ?? 'Unknown',
    'pid' => getmypid()
];

file_put_contents($lock_file, json_encode($data));

echo json_encode(['success' => true, 'lock_created' => true]);