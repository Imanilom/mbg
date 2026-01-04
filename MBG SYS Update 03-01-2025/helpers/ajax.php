<?php
/**
 * AJAX Helper
 * Centralized JSON header and robust error handling for all AJAX endpoints.
 */

// Force JSON response
if (!headers_sent()) {
    header('Content-Type: application/json');
}

// Silence browser display errors
ini_set('display_errors', 0);
ini_set('html_errors', 0);
error_reporting(E_ALL);

// Start output buffering to catch stray output
if (ob_get_level() === 0) {
    ob_start();
}

/**
 * Global Error Handler for AJAX
 * Converts PHP errors (Notice, Warning, Fatal) into JSON responses.
 */
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Check if error is suppressed by @
    if (!(error_reporting() & $errno)) return false;
    
    // Clear any previous output
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    // Log the error for administrator
    error_log("AJAX System Error [$errno]: $errstr in $errfile on line $errline");
    
    // Return clean JSON
    echo json_encode([
        'status' => 'error', 
        'success' => false,
        'message' => "System Error: $errstr",
        'debug_info' => [
            'type' => $errno,
            'file' => basename($errfile),
            'line' => $errline
        ]
    ]);
    exit;
});

/**
 * Handle unexpected output after initialization
 * Call this after all require_once blocks if you want to ensure clean output.
 */
function ajax_check_unexpected_output() {
    $out = ob_get_contents();
    if (!empty($out)) {
        ob_clean();
        error_log("Hidden output detected in AJAX: " . $out);
    }
}
?>
