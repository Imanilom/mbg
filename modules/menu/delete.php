<?php
require_once '../../config/database.php';
require_once '../../helpers/MenuHarianHelper.php';

session_start();

// Check access
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin', 'koperasi'])) {
    set_flash('error', 'Akses ditolak.');
    header('Location: ' . BASE_URL . '/modules/dashboard');
    exit();
}

$menu_id = intval($_GET['id'] ?? 0);

if (!$menu_id) {
    set_flash('error', 'Menu tidak ditemukan.');
    header('Location: index.php');
    exit();
}

$menuHelper = new MenuHarianHelper();

// Get menu info for logging
$menu = db_get_row("SELECT * FROM menu_harian WHERE id = ?", [$menu_id]);

if (!$menu) {
    set_flash('error', 'Menu tidak ditemukan.');
    header('Location: index.php');
    exit();
}

// Check if menu can be deleted (only draft status)
if ($menu['status'] !== 'draft') {
    set_flash('error', 'Hanya menu dengan status DRAFT yang dapat dihapus.');
    header('Location: detail.php?id=' . $menu_id);
    exit();
}

// Delete menu
if ($menuHelper->deleteMenu($menu_id)) {
    // Log activity
    db_insert('log_activity', [
        'user_id' => $_SESSION['user']['id'],
        'activity' => "Menghapus menu: {$menu['nama_menu']}",
        'module' => 'menu',
        'reference_id' => $menu_id,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    
    set_flash('success', 'Menu berhasil dihapus.');
} else {
    set_flash('error', 'Gagal menghapus menu.');
}

header('Location: index.php');
exit();
