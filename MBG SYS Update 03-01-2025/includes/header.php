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
    
    <!-- Toastify CSS for Toast Notifications -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    
    <!-- Di dalam <head> section dari header.php -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom CSS -->
    <style>
        :root {
            --sidebar-width: 280px;
            --navbar-height: 80px;
            --sidebar-margin: 20px;
            
            /* Premium Light Blue Palette */
            --primary: #60a5fa;
            --primary-dark: #3b82f6;
            --primary-light: #dbeafe;
            --primary-lighter: #eff6ff;
            --secondary: #94a3b8;
            --accent: #38bdf8;
            
            --bg-main: #f8fafc;
            --bg-light: #ffffff;
            --surface: #ffffff;
            --surface-elevated: #fafbfc;
            
            --text-main: #1e293b;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
            
            --success: #22c55e;
            --warning: #fbbf24;
            --danger: #f87171;
            --info: #60a5fa;
            
            --border-light: #e2e8f0;
            --border-lighter: #f1f5f9;
            
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.03);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.03);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.05), 0 4px 6px -4px rgb(0 0 0 / 0.03);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.05), 0 8px 10px -6px rgb(0 0 0 / 0.03);
            
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
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            color: var(--text-main);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: var(--radius-lg);
            position: fixed;
            left: var(--sidebar-margin);
            top: var(--sidebar-margin);
            height: calc(100vh - (var(--sidebar-margin) * 2));
            z-index: 1050;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-light);
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
            color: var(--text-main);
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
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.2s;
            font-weight: 500;
            font-size: 0.925rem;
        }
        
        .sidebar-menu .menu-item a:hover {
            color: var(--primary-dark);
            background: var(--primary-lighter);
        }
        
        .sidebar-menu .menu-item a.active {
            background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%);
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(96, 165, 250, 0.2);
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
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
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
            background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%);
            border: none;
            box-shadow: 0 2px 8px rgba(96, 165, 250, 0.25);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            box-shadow: 0 4px 12px rgba(96, 165, 250, 0.3);
        }
        
        .table {
            --bs-table-bg: transparent;
            margin-bottom: 0;
        }
        
        .table thead th {
            padding: 0.875rem 1rem;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: var(--primary-lighter);
            border-bottom: 2px solid var(--primary-light);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .table tbody td {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid var(--border-lighter);
            vertical-align: middle;
            color: var(--text-main);
            font-size: 0.875rem;
        }
        
        .table-responsive {
            border-radius: var(--radius-md);
            background: #fff;
            box-shadow: var(--shadow-sm);
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Mobile responsive table */
        @media (max-width: 768px) {
            .table-responsive {
                border-radius: var(--radius-md);
                margin: 0 -0.5rem;
            }
            
            .table thead th,
            .table tbody td {
                padding: 0.625rem 0.75rem;
                font-size: 0.8125rem;
            }
            
            .table thead th {
                font-size: 0.6875rem;
            }
        }
        
        .table.table-striped tbody tr:nth-of-type(odd) {
            background-color: var(--bg-main);
        }
        
        .table.table-hover tbody tr:hover {
            background-color: var(--primary-lighter);
        }
        
        /* Breadcrumbs Premium with Icons */
        .breadcrumb {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(8px);
            padding: 10px 18px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-light);
            display: inline-flex;
            align-items: center;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
        }
        
        .breadcrumb-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .breadcrumb-item i {
            font-size: 0.8rem;
            opacity: 0.7;
        }
        
        .breadcrumb-item a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.8125rem;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .breadcrumb-item a:hover {
            color: var(--primary-dark);
        }
        
        .breadcrumb-item.active {
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.8125rem;
        }
        
        .breadcrumb-item + .breadcrumb-item::before {
            content: "\f105";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            padding: 0 10px;
            color: var(--text-muted);
            font-size: 0.7rem;
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
        
        /* Premium Enhancements */
        .form-control:focus, .form-select:focus {
            background: #fff;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            outline: none;
        }
        
        /* Glassmorphism Utility */
        .glass {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        /* Premium Shadows */
        .shadow-premium {
            box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        }
        
        /* Smooth Transitions */
        * {
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* Badge Enhancements */
        .badge.bg-primary {
            background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%) !important;
            box-shadow: 0 2px 4px rgba(96, 165, 250, 0.2);
        }
        
        .badge.bg-success {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%) !important;
            box-shadow: 0 2px 4px rgba(34, 197, 94, 0.2);
        }
        
        .badge.bg-warning {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%) !important;
            box-shadow: 0 2px 4px rgba(251, 191, 36, 0.2);
        }
        
        .badge.bg-danger {
            background: linear-gradient(135deg, #f87171 0%, #ef4444 100%) !important;
            box-shadow: 0 2px 4px rgba(248, 113, 113, 0.2);
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