<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include constants if not already included
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../helpers/constants.php';
}

// Include database connection and functions
if (!isset($conn)) {
    require_once __DIR__ . '/../config/database.php';
}

// Include session helper
require_once __DIR__ . '/../helpers/session.php';

// Check if user is logged in
$is_logged_in = is_logged_in();

// Get user data
if ($is_logged_in) {
    $user = get_user_data();
    
    // Ensure user array exists in session for compatibility
    if (!isset($_SESSION['user'])) {
        $_SESSION['user'] = $user;
    }
} else {
    $user = null;
    
    // If trying to access protected page, redirect to login
    // Except for login page itself
    $current_page = basename($_SERVER['PHP_SELF']);
    $public_pages = ['login.php', 'register.php', 'forgot-password.php'];
    
    if (!in_array($current_page, $public_pages) && strpos($_SERVER['REQUEST_URI'], '/auth/') === false) {
        header('Location: ' . base_url('modules/auth/login.php'));
        exit();
    }
}

// Set default page title if not set
if (!isset($page_title)) {
    $page_title = 'Marketlist MBG';
}

// Get flash message if any
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Dashboard' ?> - Marketlist MBG</title>
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Di dalam <head> section dari header.php -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom CSS -->
    <style>
        :root {
            --sidebar-width: 280px;
            --navbar-height: 80px;
            --sidebar-margin: 20px;
            
            /* Premium Refined Palette */
            --primary: #6366f1;
            --primary-light: #e0e7ff;
            --secondary: #94a3b8;
            --bg-main: #f1f5f9;
            --surface: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 24px;
        }
        
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-main);
            color: var(--text-main);
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }
        
        .wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh;
            padding: var(--sidebar-margin);
            gap: var(--sidebar-margin);
            position: relative;
        }
        
        #sidebar {
            width: var(--sidebar-width);
            background: #1e293b;
            color: #fff;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: var(--radius-lg);
            position: fixed;
            left: var(--sidebar-margin);
            top: var(--sidebar-margin);
            height: calc(100vh - (var(--sidebar-margin) * 2));
            z-index: 1050;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
        }
        
        #sidebar.active {
            transform: translateX(calc(-1 * (var(--sidebar-width) + (var(--sidebar-margin) * 2))));
        }
        
        #content {
            width: 100%;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            margin-left: calc(var(--sidebar-width) + var(--sidebar-margin));
            flex-grow: 1;
            min-width: 0;
        }
        
        #content.active {
            margin-left: 0;
        }

        /* Mobile Overlay */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1040;
            backdrop-filter: blur(2px);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        @media (max-width: 991.98px) {
            .sidebar-overlay.active {
                display: block;
                opacity: 1;
            }

            .wrapper {
                padding: 1rem;
            }

            #sidebar {
                left: 0;
                top: 0;
                height: 100vh;
                border-radius: 0;
                margin: 0;
                transform: translateX(-100%);
            }

            #sidebar.active {
                transform: translateX(0);
            }

            #content {
                margin-left: 0 !important;
            }
        }
        
        .navbar-custom {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: var(--radius-md);
            padding: 0 1.5rem;
            height: var(--navbar-height);
            margin-bottom: var(--sidebar-margin);
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
        }
        
        .content-wrapper {
            padding: 0;
        }
        
        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .sidebar-header h3 {
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: #fff;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .sidebar-menu {
            padding: 1.5rem 0.75rem;
        }
        
        .sidebar-menu .menu-item {
            margin-bottom: 4px;
        }
        
        .sidebar-menu .menu-item a {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            color: #94a3b8;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.2s;
            font-weight: 500;
            font-size: 0.925rem;
        }
        
        .sidebar-menu .menu-item a:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.05);
            transform: translateX(4px);
        }
        
        .sidebar-menu .menu-item a.active {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.3);
        }
        
        .sidebar-menu .menu-item a i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }
        
        .card {
            border: none;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            background: #fff;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden;
            margin-bottom: var(--sidebar-margin);
        }
        
        .card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        .card-header {
            background: transparent;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #f1f5f9;
            font-weight: 700;
            color: var(--text-main);
            font-size: 1.1rem;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .btn {
            border-radius: 12px;
            padding: 10px 24px;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--primary);
            border: none;
            box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.2);
        }
        
        .btn-primary:hover {
            background: #4f46e5;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.3);
        }
        
        .table {
            --bs-table-bg: transparent;
        }
        
        .table thead th {
            padding: 1rem 1.5rem;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: #f8fafc;
            border: none;
        }
        
        .table-responsive {
            border-radius: var(--radius-md);
            background: #fff;
        }
        
        .table.table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(248, 250, 252, 0.5);
        }
        
        .table.table-hover tbody tr:hover {
            background-color: rgba(99, 102, 241, 0.02);
        }
        
        /* Breadcrumbs Premium */
        .breadcrumb {
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(4px);
            padding: 12px 20px;
            border-radius: var(--radius-md);
            border: 1px solid rgba(226, 232, 240, 0.5);
            display: inline-flex;
            margin-bottom: 2rem;
        }
        
        .breadcrumb-item a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .breadcrumb-item.active {
            color: var(--text-muted);
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .breadcrumb-item + .breadcrumb-item::before {
            content: "\f105";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            padding: 0 12px;
            color: #cbd5e1;
            font-size: 0.75rem;
        }

        /* Modal Overhaul */
        .modal-content {
            border-radius: var(--radius-xl);
            border: none;
            box-shadow: var(--shadow-lg);
        }
        
        .modal-header {
            padding: 2rem;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid #f1f5f9;
        }
        
        .modal-title {
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.5px;
        }

        .table tbody td {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            color: var(--text-main);
            font-size: 0.95rem;
        }
        
        .badge {
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.75rem;
        }
        
        .form-control, .form-select {
            border-radius: 12px;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            transition: all 0.2s;
        }
        
        .form-control:focus, .form-select:focus {
            background: #fff;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }
        
        label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-main);
            font-size: 0.9rem;
        }
        
        @media (max-width: 991px) {
            .wrapper { padding: 10px; }
            #sidebar {
                transform: translateX(calc(-1 * (var(--sidebar-width) + 40px)));
                height: calc(100vh - 20px);
            }
            #sidebar.active { transform: translateX(0); }
            #content { margin-left: 0; }
        }
    </style>
    
    <?php if (isset($extra_css)): ?>
        <?= $extra_css ?>
    <?php endif; ?>
</head>
<body>
    <div class="wrapper">