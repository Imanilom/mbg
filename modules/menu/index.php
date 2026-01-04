<?php
$page_title = 'Menu Harian';
require_once '../../includes/header.php';
require_once '../../helpers/MenuHarianHelper.php';
require_once '../../helpers/menu_helpers.php';

// Handle actions
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
    set_flash('error', 'Akses ditolak.');
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
            <p class="mb-0 text-muted">Kelola menu harian dengan pengecekan stok otomatis</p>
        </div>
        <div>
            <a href="create.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Buat Menu Baru
            </a>
        </div>
    </div>

    <!-- Flash Message -->
    <?php show_flash(); ?>

    <!-- Filter Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-filter me-1"></i>Filter
            </h6>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
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
                    <label class="form-label">Tanggal Dari</label>
                    <input type="date" name="date_from" class="form-control" value="<?= $filter_date_from ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tanggal Sampai</label>
                    <input type="date" name="date_to" class="form-control" value="<?= $filter_date_to ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-1"></i>Filter
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-redo me-1"></i>Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Menu List Card -->
    <div class="card shadow">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list me-1"></i>Daftar Menu Harian
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-striped" id="menuTable">
                    <thead>
                        <tr>
                            <th>No Menu</th>
                            <th>Tanggal</th>
                            <th>Nama Menu</th>
                            <th>Kantor</th>
                            <th>Porsi</th>
                            <th>Total Porsi</th>
                            <th>Status Stok</th>
                            <th>Status</th>
                            <th>Dibuat Oleh</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($menus)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted mb-0">Belum ada menu harian</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($menus as $menu): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($menu['no_menu']) ?></strong>
                            </td>
                            <td><?= format_tanggal($menu['tanggal_menu']) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($menu['nama_menu']) ?></strong>
                            </td>
                            <td><?= htmlspecialchars($menu['nama_kantor'] ?? 'Semua Kantor') ?></td>
                            <td>
                                <span class="badge bg-info"><?= format_number($menu['total_porsi']) ?> porsi</span>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?= $menu['total_items'] ?> items<br>
                                    <span class="text-success"><?= $menu['items_from_warehouse'] ?> dari gudang</span><br>
                                    <span class="text-warning"><?= $menu['items_to_purchase'] ?> perlu beli</span>
                                </small>
                            </td>
                            <td>
                                <?php if ($menu['items_to_purchase'] > 0): ?>
                                    <span class="badge bg-warning text-dark">
                                        <i class="fas fa-shopping-cart me-1"></i>Perlu Beli
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check me-1"></i>Stok Cukup
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?= get_status_badge($menu['status'], 'menu') ?></td>
                            <td>
                                <small>
                                    <?= htmlspecialchars($menu['created_by_name']) ?><br>
                                    <span class="text-muted"><?= format_datetime($menu['created_at']) ?></span>
                                </small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="detail.php?id=<?= $menu['id'] ?>" 
                                       class="btn btn-info" title="Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($menu['status'] == 'draft'): ?>
                                    <a href="edit.php?id=<?= $menu['id'] ?>" 
                                       class="btn btn-warning" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" 
                                            class="btn btn-danger" 
                                            onclick="confirmDelete(<?= $menu['id'] ?>)" 
                                            title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
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

// Add status badge helper for menu
function getMenuStatusBadge(status) {
    const badges = {
        'draft': '<span class="badge bg-secondary">DRAFT</span>',
        'approved': '<span class="badge bg-primary">APPROVED</span>',
        'processing': '<span class="badge bg-warning text-dark">PROCESSING</span>',
        'completed': '<span class="badge bg-success">COMPLETED</span>',
        'cancelled': '<span class="badge bg-danger">CANCELLED</span>'
    };
    return badges[status] || '<span class="badge bg-light text-dark">' + status.toUpperCase() + '</span>';
}
</script>

<?php
// Add menu status badge to constants
if (!function_exists('get_menu_status_badge')) {
    function get_menu_status_badge($status) {
        $badges = [
            'draft' => '<span class="badge bg-secondary rounded-pill px-3">DRAFT</span>',
            'approved' => '<span class="badge bg-primary rounded-pill px-3">APPROVED</span>',
            'processing' => '<span class="badge bg-warning text-dark rounded-pill px-3">PROCESSING</span>',
            'completed' => '<span class="badge bg-success rounded-pill px-3">COMPLETED</span>',
            'cancelled' => '<span class="badge bg-danger rounded-pill px-3">CANCELLED</span>'
        ];
        return $badges[$status] ?? '<span class="badge bg-light text-dark rounded-pill px-3">' . strtoupper($status) . '</span>';
    }
}

require_once '../../includes/footer.php';
?>
