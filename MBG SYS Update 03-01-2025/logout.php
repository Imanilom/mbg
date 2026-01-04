<?php
require_once 'config/database.php';
require_once 'helpers/session.php';
require_once 'helpers/functions.php';

// Log activity before logout
if (is_logged_in()) {
    $user_id = get_user_data('id');
    
    $log_data = [
        'user_id' => $user_id,
        'activity' => 'Logout dari sistem',
        'module' => 'auth',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
    ];
    db_insert('log_activity', $log_data);
}

// Logout user
logout_user();

// Redirect to login
header('Location: modules/auth/login.php');
exit();