<?php
// Start session and check auth manually before outputting anything
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/MenuCatalogHelper.php';
require_once '../../helpers/functions.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Access Control
if (!is_logged_in()) {
    header('Location: ' . BASE_URL . '/modules/auth/login.php');
    exit();
}

$user = get_user_data();
if (!in_array($user['role'], ['admin', 'koperasi'])) {
    set_flash('error', 'Akses ditolak.');
    header('Location: ' . BASE_URL . '/modules/dashboard');
    exit();
}

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $catalogHelper = new MenuCatalogHelper();
    if ($catalogHelper->deleteMenu($_POST['id'])) {
        logActivity($conn, $user['id'], "Menghapus menu catalog", 'menu_master', $_POST['id']);
        $_SESSION['flash_type'] = 'success';
        $_SESSION['flash_message'] = 'Menu catalog berhasil dihapus.';
    } else {
        $_SESSION['flash_type'] = 'error';
        $_SESSION['flash_message'] = 'Gagal menghapus menu catalog.';
    }
    header('Location: catalog.php');
    exit();
}

$catalogHelper = new MenuCatalogHelper();
$menus = $catalogHelper->getAllMenus();

$page_title = 'Menu Catalog';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';
?>

<style>
    .menu-catalog-card {
        transition: all 0.3s ease;
        border-radius: 10px;
        overflow: hidden;
    }
    .menu-catalog-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.15) !important;
    }
    .menu-catalog-card .card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 1.5rem;
    }
    .menu-catalog-card .card-body {
        padding: 1.5rem;
    }
    .item-count-badge {
        background: rgba(255,255,255,0.2);
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.9rem;
    }
    .action-buttons {
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    .menu-catalog-card:hover .action-buttons {
        opacity: 1;
    }
    .empty-state {
        padding: 4rem 2rem;
        text-align: center;
    }
    .empty-state i {
        font-size: 5rem;
        color: #e3e6f0;
        margin-bottom: 1rem;
    }
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-book-open me-2"></i>Menu Catalog
            </h1>
            <p class="mb-0 text-muted">Kelola template menu master tanpa tanggal</p>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-secondary shadow-sm">
                <i class="fas fa-arrow-left me-1"></i> Kembali
            </a>
            <a href="catalog_form.php" class="btn btn-primary shadow-sm">
                <i class="fas fa-plus me-1"></i> Buat Menu Baru
            </a>
        </div>
    </div>

    <!-- Flash Message -->
    <?php show_flash(); ?>

    <!-- Menu Cards Grid -->
    <div class="row">
        <?php if (empty($menus)): ?>
        <div class="col-12">
            <div class="card shadow">
                <div class="card-body empty-state">
                    <i class="fas fa-book-open"></i>
                    <h5>Belum ada menu catalog</h5>
                    <p class="text-muted mb-3">Buat template menu master untuk memudahkan perencanaan menu harian</p>
                    <a href="catalog_form.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Buat Menu Pertama
                    </a>
                </div>
            </div>
        </div>
        <?php else: ?>
            <?php foreach ($menus as $menu): ?>
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card menu-catalog-card shadow h-100">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h5 class="mb-2 fw-bold">
                                    <?= htmlspecialchars($menu['nama_menu']) ?>
                                </h5>
                                <div class="item-count-badge">
                                    <i class="fas fa-list me-1"></i>
                                    <?= $menu['total_items'] ?> Item
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3" style="min-height: 60px;">
                            <?= htmlspecialchars($menu['deskripsi'] ?: 'Tidak ada deskripsi') ?>
                        </p>
                        
                        <hr>
                        
                        <div class="small text-muted mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span><i class="fas fa-user me-1"></i> Dibuat oleh:</span>
                                <span class="fw-bold"><?= htmlspecialchars($menu['created_by_name']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-clock me-1"></i> Tanggal:</span>
                                <span class="fw-bold"><?= format_tanggal($menu['created_at']) ?></span>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="d-flex gap-2 action-buttons">
                            <a href="catalog_detail.php?id=<?= $menu['id'] ?>" class="btn btn-sm btn-info text-white flex-grow-1">
                                <i class="fas fa-eye"></i> Detail
                            </a>
                            <a href="catalog_form.php?id=<?= $menu['id'] ?>" class="btn btn-sm btn-warning text-white flex-grow-1">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <button type="button" class="btn btn-sm btn-danger flex-grow-1" onclick="confirmDelete(<?= $menu['id'] ?>)">
                                <i class="fas fa-trash"></i> Hapus
                            </button>
                        </div>
                        
                        <!-- Always visible button -->
                        <div class="d-grid mt-2">
                            <a href="planner_schedule.php?menu_id=<?= $menu['id'] ?>" class="btn btn-success">
                                <i class="fas fa-calendar-plus me-1"></i> Jadwalkan Menu Ini
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" action="catalog.php" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
function confirmDelete(id) {
    if (confirm('Apakah Anda yakin ingin menghapus menu catalog ini?\n\nPerhatian: Menu yang sudah dijadwalkan akan tetap ada, hanya referensi ke catalog yang akan dihapus.')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>
