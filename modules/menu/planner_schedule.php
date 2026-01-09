<?php
// Load config and database first (before any output)
require_once '../../config/database.php';
require_once '../../helpers/MenuCatalogHelper.php';
require_once '../../helpers/MenuHarianHelper.php';

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

// Check access
if (!in_array($user['role'], ['admin', 'koperasi'])) {
    $_SESSION['flash_type'] = 'error';
    $_SESSION['flash_message'] = 'Akses ditolak.';
    header('Location: ' . BASE_URL . '/modules/dashboard');
    exit();
}

$catalogHelper = new MenuCatalogHelper();
$menuHelper = new MenuHarianHelper();

// Handle form submission BEFORE any output
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $menu_master_id = $_POST['menu_master_id'];
        $tanggal_menu = $_POST['tanggal_menu'];
        $kantor_id = !empty($_POST['kantor_id']) ? $_POST['kantor_id'] : null;
        $total_porsi = $_POST['total_porsi'];
        
        // Create menu from master
        $menu_id = $menuHelper->createMenuFromMaster(
            $menu_master_id,
            $tanggal_menu,
            $kantor_id,
            $total_porsi,
            $user['id']
        );
        
        require_once '../../helpers/functions.php';
        logActivity($conn, $user['id'], "Menjadwalkan menu dari catalog", 'menu_harian', $menu_id);
        $_SESSION['flash_type'] = 'success';
        $_SESSION['flash_message'] = 'Menu berhasil dijadwalkan!';
        header('Location: planner.php');
        exit();
        
    } catch (Exception $e) {
        $_SESSION['flash_type'] = 'error';
        $_SESSION['flash_message'] = 'Gagal menjadwalkan menu: ' . $e->getMessage();
    }
}

// Get menu catalog if ID provided
$menu_catalog = null;
if (isset($_GET['menu_id'])) {
    $menu_catalog = $catalogHelper->getMenu($_GET['menu_id']);
    if (!$menu_catalog) {
        $_SESSION['flash_type'] = 'error';
        $_SESSION['flash_message'] = 'Menu catalog tidak ditemukan.';
        header('Location: catalog.php');
        exit();
    }
}

// Get all menu catalogs for dropdown
$all_menus = $catalogHelper->getAllMenus();

// Get offices for dropdown
$offices = $conn->query("SELECT id, nama_kantor FROM kantor ORDER BY nama_kantor ASC")->fetch_all(MYSQLI_ASSOC);

// NOW include header (after all redirects are done)
$page_title = 'Jadwalkan Menu';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-calendar-plus me-2"></i>Jadwalkan Menu
            </h1>
            <p class="mb-0 text-muted">Pilih menu dari catalog dan tentukan jadwal</p>
        </div>
        <div class="d-flex gap-2">
            <a href="catalog.php" class="btn btn-secondary shadow-sm">
                <i class="fas fa-arrow-left me-1"></i> Ke Catalog
            </a>
            <a href="planner.php" class="btn btn-info text-white shadow-sm">
                <i class="fas fa-calendar me-1"></i> Ke Planner
            </a>
        </div>
    </div>

    <!-- Flash Message -->
    <?php show_flash(); ?>

    <div class="row">
        <!-- Form -->
        <div class="col-lg-8">
            <form method="POST" action="" id="scheduleForm">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 bg-primary text-white">
                        <h6 class="m-0 font-weight-bold">
                            <i class="fas fa-calendar-check me-1"></i> Informasi Jadwal
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Pilih Menu <span class="text-danger">*</span></label>
                                <select name="menu_master_id" id="menuSelect" class="form-select" required onchange="loadMenuPreview(this.value)">
                                    <option value="">-- Pilih Menu dari Catalog --</option>
                                    <?php foreach ($all_menus as $m): ?>
                                    <option value="<?= $m['id'] ?>" <?= $menu_catalog && $menu_catalog['id'] == $m['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($m['nama_menu']) ?> (<?= $m['total_items'] ?> item)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Tanggal Menu <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal_menu" class="form-control" 
                                       value="<?= $_GET['date'] ?? date('Y-m-d') ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Kantor</label>
                                <select name="kantor_id" class="form-select">
                                    <option value="">Semua Kantor</option>
                                    <?php foreach ($offices as $office): ?>
                                    <option value="<?= $office['id'] ?>">
                                        <?= htmlspecialchars($office['nama_kantor']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Total Porsi <span class="text-danger">*</span></label>
                                <input type="number" name="total_porsi" class="form-control" 
                                       value="50" min="1" required placeholder="Contoh: 50">
                                <small class="text-muted">Jumlah porsi yang dibutuhkan</small>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-1"></i>
                            Sistem akan otomatis menghitung kebutuhan bahan berdasarkan total porsi yang Anda masukkan.
                        </div>
                    </div>
                </div>

                <!-- Menu Preview -->
                <div id="menuPreview" style="<?= $menu_catalog ? '' : 'display:none' ?>">
                    <?php if ($menu_catalog): ?>
                    <div class="card shadow mb-4 border-left-success">
                        <div class="card-header py-3 bg-success text-white">
                            <h6 class="m-0 font-weight-bold">
                                <i class="fas fa-eye me-1"></i> Preview Menu
                            </h6>
                        </div>
                        <div class="card-body">
                            <h5 class="fw-bold mb-2"><?= htmlspecialchars($menu_catalog['nama_menu']) ?></h5>
                            <p class="text-muted mb-3"><?= htmlspecialchars($menu_catalog['deskripsi'] ?: 'Tidak ada deskripsi') ?></p>
                            
                            <h6 class="fw-bold mb-2">Item Menu (<?= count($menu_catalog['items']) ?>):</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Nama</th>
                                            <th>Tipe</th>
                                            <th class="text-end">Gramasi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($menu_catalog['items'] as $idx => $item): ?>
                                        <tr>
                                            <td><?= $idx + 1 ?></td>
                                             <td>
                                                <?= htmlspecialchars($item['item_type'] === 'manual' ? $item['custom_name'] : ($item['produk_id'] ? $item['nama_produk'] : $item['nama_resep'])) ?>
                                             </td>
                                             <td>
                                                <?php if ($item['item_type'] === 'product' || $item['produk_id']): ?>
                                                    <span class="badge bg-primary">Produk</span>
                                                <?php elseif ($item['item_type'] === 'recipe' || $item['resep_id']): ?>
                                                    <span class="badge bg-info">Resep</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">Manual</span>
                                                <?php endif; ?>
                                             </td>
                                             <td class="text-end">
                                                <?= format_number($item['qty_needed']) ?> <?= htmlspecialchars($item['item_type'] === 'manual' ? $item['keterangan'] : $item['nama_satuan']) ?>
                                             </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Submit -->
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="planner.php" class="btn btn-secondary">
                        <i class="fas fa-times me-1"></i> Batal
                    </a>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-calendar-check me-1"></i> Jadwalkan Menu
                    </button>
                </div>
            </form>
        </div>

        <!-- Help -->
        <div class="col-lg-4">
            <div class="card shadow mb-4 border-left-info">
                <div class="card-header py-3 bg-info text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-question-circle me-1"></i> Cara Menggunakan
                    </h6>
                </div>
                <div class="card-body">
                    <ol class="small">
                        <li>Pilih menu dari catalog</li>
                        <li>Tentukan tanggal menu akan disajikan</li>
                        <li>Pilih kantor (opsional)</li>
                        <li>Masukkan total porsi yang dibutuhkan</li>
                        <li>Klik "Jadwalkan Menu"</li>
                    </ol>
                    
                    <hr>
                    
                    <h6 class="fw-bold">Keuntungan:</h6>
                    <ul class="small">
                        <li>Tidak perlu input ulang item menu</li>
                        <li>Kalkulasi otomatis berdasarkan porsi</li>
                        <li>Konsisten dengan template catalog</li>
                        <li>Hemat waktu perencanaan</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function loadMenuPreview(menuId) {
    if (!menuId) {
        document.getElementById('menuPreview').style.display = 'none';
        return;
    }
    
    // Reload page with menu_id parameter to show preview
    window.location.href = 'planner_schedule.php?menu_id=' + menuId;
}
</script>

<?php require_once '../../includes/footer.php'; ?>
