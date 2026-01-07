<?php
// Load dependencies first (before any output)
require_once '../../config/database.php';
require_once '../../helpers/MenuHarianHelper.php';
require_once '../../helpers/menu_helpers.php';
require_once '../../helpers/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get user from session
$user = $_SESSION['user'] ?? null;
if (!$user) {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

// Handle actions BEFORE any output
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'delete' && isset($_POST['id'])) {
        $menuHelper = new MenuHarianHelper();
        if ($menuHelper->deleteMenu($_POST['id'])) {
            // Log activity
            logActivity($conn, $user['id'], "Menghapus menu planner", 'menu', $_POST['id']);
            $_SESSION['flash_type'] = 'success';
            $_SESSION['flash_message'] = 'Menu berhasil dihapus.';
        } else {
            $_SESSION['flash_type'] = 'error';
            $_SESSION['flash_message'] = 'Gagal menghapus menu. Pastikan status masih draft.';
        }
        header('Location: index.php');
        exit();
    }
}

// Check access
if (!in_array($user['role'], ['admin', 'koperasi'])) {
    $_SESSION['flash_type'] = 'error';
    $_SESSION['flash_message'] = 'Akses ditolak.';
    header('Location: ' . BASE_URL . '/modules/dashboard');
    exit();
}

// Get filter parameters
$filter_status = $_GET['status'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

// Build query
$sql = "SELECT * FROM v_menu_summary WHERE 1=1";
$params = [];
$types = '';

if ($filter_status) {
    $sql .= " AND status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

if ($filter_date_from) {
    $sql .= " AND tanggal_menu >= ?";
    $params[] = $filter_date_from;
    $types .= 's';
}

if ($filter_date_to) {
    $sql .= " AND tanggal_menu <= ?";
    $params[] = $filter_date_to;
    $types .= 's';
}

$sql .= " ORDER BY tanggal_menu DESC, created_at DESC";

// Execute query
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

$menus = [];
while ($row = $result->fetch_assoc()) {
    $menus[] = $row;
}

// NOW include header (after all redirects and processing)
$page_title = 'Menu Harian';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-utensils me-2"></i>Menu Harian
            </h1>
            <p class="mb-0 text-muted">Kelola menu harian dengan tampilan kartu yang informatif</p>
        </div>
        <div class="d-flex gap-2">
            <a href="catalog.php" class="btn btn-info text-white shadow-sm">
                <i class="fas fa-book-open me-1"></i> Menu Catalog
            </a>
            <a href="planner.php" class="btn btn-success text-white shadow-sm">
                <i class="fas fa-calendar-alt me-1"></i> Planner View
            </a>
            <a href="create.php" class="btn btn-primary shadow-sm">
                <i class="fas fa-plus me-1"></i> Buat Menu Baru
            </a>
        </div>
    </div>

    <!-- Flash Message -->
    <?php show_flash(); ?>

    <!-- Filter Card -->
    <div class="card shadow mb-4 border-left-primary">
        <div class="card-body">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-uppercase">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua Status</option>
                        <option value="draft" <?= $filter_status == 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="approved" <?= $filter_status == 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="processing" <?= $filter_status == 'processing' ? 'selected' : '' ?>>Processing</option>
                        <option value="completed" <?= $filter_status == 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= $filter_status == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-uppercase">Dari Tanggal</label>
                    <input type="date" name="date_from" class="form-control" value="<?= $filter_date_from ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-uppercase">Sampai Tanggal</label>
                    <input type="date" name="date_to" class="form-control" value="<?= $filter_date_to ?>">
                </div>
                <div class="col-md-3">
                    <div class="d-grid gap-2 d-md-block">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i> Filter
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-redo me-1"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Menu Cards -->
    <div class="row">
        <?php if (empty($menus)): ?>
        <div class="col-12 text-center py-5">
            <div class="text-gray-300 mb-3">
                <i class="fas fa-utensils fa-4x"></i>
            </div>
            <h5>Belum ada menu harian</h5>
            <p class="text-muted">Buat menu baru atau ubah filter pencarian Anda</p>
        </div>
        <?php else: ?>
            <?php foreach ($menus as $menu): ?>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-<?= get_status_color($menu['status']) ?> shadow h-100 py-2 card-menu">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-<?= get_status_color($menu['status']) ?> text-uppercase mb-1">
                                    <?= format_tanggal($menu['tanggal_menu']) ?>
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800 mb-2">
                                    <?= htmlspecialchars($menu['nama_menu']) ?>
                                </div>
                                <div class="mb-2">
                                    <span class="badge bg-light text-dark border">
                                        <i class="fas fa-building me-1"></i> <?= htmlspecialchars($menu['nama_kantor'] ?? 'Semua Kantor') ?>
                                    </span>
                                </div>
                                <p class="text-muted small mb-2 text-truncate" style="max-width: 250px;">
                                    <?= htmlspecialchars($menu['deskripsi'] ?? '-') ?>
                                </p>
                                
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div class="small">
                                        <i class="fas fa-users text-info me-1"></i> <strong><?= format_number($menu['total_porsi']) ?></strong> Porsi
                                    </div>
                                    <div class="small">
                                         <?= get_menu_status_badge($menu['status']) ?>
                                    </div>
                                </div>
                                
                                <hr class="my-2">
                                
                                <div class="d-flex justify-content-between align-items-center small text-muted">
                                    <div>
                                        <i class="fas fa-box me-1"></i> <?= $menu['total_items'] ?> Item
                                    </div>
                                    <div class="<?= $menu['items_to_purchase'] > 0 ? 'text-warning fw-bold' : 'text-success' ?>">
                                        <?php if ($menu['items_to_purchase'] > 0): ?>
                                            <i class="fas fa-shopping-cart me-1"></i> Beli
                                        <?php else: ?>
                                            <i class="fas fa-check me-1"></i> Stok OK
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Hover Actions Overlay -->
                        <div class="card-actions mt-3 pt-2 border-top d-flex justify-content-between">
                            <a href="detail.php?id=<?= $menu['id'] ?>" class="btn btn-sm btn-info text-white flex-grow-1 me-1">
                                <i class="fas fa-eye"></i> Detail
                            </a>
                            <?php if ($menu['status'] == 'draft'): ?>
                                <a href="edit.php?id=<?= $menu['id'] ?>" class="btn btn-sm btn-warning text-white flex-grow-1 me-1">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <button type="button" class="btn btn-sm btn-danger flex-grow-1" onclick="confirmDelete(<?= $menu['id'] ?>)">
                                    <i class="fas fa-trash"></i> Hapus
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<form id="deleteForm" action="index.php" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
function confirmDelete(id) {
    if (confirm('Apakah Anda yakin ingin menghapus menu ini?')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php
function get_status_color($status) {
    $colors = [
        'draft' => 'secondary',
        'approved' => 'primary',
        'processing' => 'warning',
        'completed' => 'success',
        'cancelled' => 'danger'
    ];
    return $colors[$status] ?? 'secondary';
}

if (!function_exists('get_menu_status_badge')) {
    function get_menu_status_badge($status) {
        $badges = [
            'draft' => '<span class="badge bg-secondary">DRAFT</span>',
            'approved' => '<span class="badge bg-primary">APPROVED</span>',
            'processing' => '<span class="badge bg-warning text-dark">PROCESSING</span>',
            'completed' => '<span class="badge bg-success">COMPLETED</span>',
            'cancelled' => '<span class="badge bg-danger">CANCELLED</span>'
        ];
        return $badges[$status] ?? '<span class="badge bg-light text-dark">' . strtoupper($status) . '</span>';
    }
}

require_once '../../includes/footer.php';
?>
