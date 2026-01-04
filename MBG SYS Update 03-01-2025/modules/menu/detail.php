<?php
session_start();
require_once '../../config/database.php';
require_once '../../helpers/functions.php';
require_once '../../helpers/MenuHarianHelper.php';

// Check access
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$menuHelper = new MenuHarianHelper();

// Get menu header
$menu = db_get_row("SELECT mh.*, k.nama_kantor, u.nama_lengkap as created_by_name, 
                    ua.nama_lengkap as approved_by_name
                    FROM menu_harian mh 
                    LEFT JOIN kantor k ON mh.kantor_id = k.id 
                    LEFT JOIN users u ON mh.created_by = u.id
                    LEFT JOIN users ua ON mh.approved_by = ua.id
                    WHERE mh.id = ?", [$id]);

if (!$menu) {
    $_SESSION['flash_type'] = 'error';
    $_SESSION['flash_message'] = 'Menu tidak ditemukan.';
    header('Location: index.php');
    exit();
}

// Get menu details
$details = db_get_all("SELECT mhd.*, p.nama_produk, s.nama_satuan, 
                       r.nama_resep, r.kode_resep
                       FROM menu_harian_detail mhd
                       LEFT JOIN produk p ON mhd.produk_id = p.id
                       LEFT JOIN satuan s ON p.satuan_id = s.id
                       LEFT JOIN resep r ON mhd.resep_id = r.id
                       WHERE mhd.menu_id = ?", [$id]);

// Calculate real-time stock
foreach ($details as &$item) {
    $allocation = $menuHelper->calculateStockAllocation($item['produk_id'], $item['qty_needed'], $menu['tanggal_menu']);
    $item['current_warehouse_stock'] = $allocation['warehouse_stock'];
    $item['current_stock_status'] = $allocation['stock_status'];
    $item['current_market_recommendation'] = $allocation['market_recommendation'] ? json_decode($allocation['market_recommendation'], true) : null;
}
unset($item);

$page_title = 'Detail Menu Harian: ' . $menu['no_menu'];
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Detail Menu</h1>
            <p class="mb-0 text-muted">Informasi lengkap dan status stok</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Kembali
            </a>
            <?php if ($menu['status'] == 'draft'): ?>
                <a href="edit.php?id=<?= $menu['id'] ?>" class="btn btn-warning">
                    <i class="fas fa-edit me-1"></i> Edit
                </a>
                <button type="button" class="btn btn-danger" onclick="confirmDelete(<?= $menu['id'] ?>)">
                    <i class="fas fa-trash me-1"></i> Hapus
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Info Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Informasi Menu</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <td style="width: 150px;">No Menu</td>
                            <td>: <strong><?= $menu['no_menu'] ?></strong></td>
                        </tr>
                        <tr>
                            <td>Nama Menu</td>
                            <td>: <?= htmlspecialchars($menu['nama_menu']) ?></td>
                        </tr>
                        <tr>
                            <td>Tanggal</td>
                            <td>: <?= format_tanggal($menu['tanggal_menu']) ?></td>
                        </tr>
                        <tr>
                            <td>Total Porsi</td>
                            <td>: <?= format_number($menu['total_porsi']) ?> Porsi</td>
                        </tr>
                        <tr>
                            <td>Deskripsi</td>
                            <td>: <?= !empty($menu['deskripsi']) ? nl2br(htmlspecialchars($menu['deskripsi'])) : '-' ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <td style="width: 150px;">Kantor</td>
                            <td>: <?= $menu['nama_kantor'] ?? 'Semua Kantor' ?></td>
                        </tr>
                        <tr>
                            <td>Status</td>
                            <td>: <?= get_status_badge($menu['status'], 'menu') ?></td>
                        </tr>
                        <tr>
                            <td>Dibuat Oleh</td>
                            <td>: <?= $menu['created_by_name'] ?> <br> <small class="text-muted"><?= format_datetime($menu['created_at']) ?></small></td>
                        </tr>
                        <?php if ($menu['approved_by']): ?>
                        <tr>
                            <td>Disetujui Oleh</td>
                            <td>: <?= $menu['approved_by_name'] ?> <br> <small class="text-muted"><?= format_datetime($menu['approved_at']) ?></small></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Items Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Item / Bahan Baku</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Item / Bahan</th>
                            <th>Total Butuh</th>
                            <th>Stok Gudang (Saat Ini)</th>
                            <th>Status Stok</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($details as $index => $item): ?>
                            <tr>
                                <td class="text-center"><?= $index + 1 ?></td>
                                <td>
                                    <strong><?= $item['nama_produk'] ?></strong><br>
                                    <?php if ($item['resep_id']): ?>
                                        <small class="text-primary"><i class="fas fa-utensils me-1"></i>Dari Resep: <?= $item['nama_resep'] ?></small>
                                    <?php else: ?>
                                        <small class="text-secondary"><i class="fas fa-box me-1"></i>Produk Langsung</small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-bold">
                                    <?= format_number($item['qty_needed'], 2) ?> <?= $item['nama_satuan'] ?>
                                </td>
                                <td class="text-end">
                                    <?= format_number($item['current_warehouse_stock'], 2) ?> <?= $item['nama_satuan'] ?>
                                </td>
                                <td class="text-center">
                                    <?php 
                                    $status = $item['current_stock_status'];
                                    $badge = $status == 'sufficient' ? 'bg-success' : ($status == 'partial' ? 'bg-warning text-dark' : 'bg-danger');
                                    $label = $status == 'sufficient' ? 'Cukup' : ($status == 'partial' ? 'Kurang' : 'Kosong');
                                    ?>
                                    <span class="badge <?= $badge ?>"><?= $label ?></span>
                                    
                                    <?php if ($status != 'sufficient' && $item['current_market_recommendation']): ?>
                                        <div class="mt-2 small text-start p-2 bg-light rounded text-warning border border-warning">
                                            <?php $rec = $item['current_market_recommendation']; ?>
                                            <i class="fas fa-shopping-cart me-1"></i>
                                            Beli <strong><?= format_number($item['qty_needed'] - $item['current_warehouse_stock'], 2) ?> <?= $item['nama_satuan'] ?></strong>
                                            di <?= $rec['nama_pasar'] ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= $item['keterangan'] ?></td>
                            </tr>
                        <?php endforeach; ?>
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
</script>

<?php require_once '../../includes/footer.php'; ?>
