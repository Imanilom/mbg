<?php
require_once 'helpers/constants.php';
require_once 'config/database.php';
require_once 'helpers/session.php';
require_once 'helpers/functions.php';

// Check if already logged in
if (is_logged_in()) {
    header('Location: modules/dashboard/index.php');
    exit();
}

// Redirect to login
header('Location: modules/auth/login.php');
exit();