<?php
$page_title = 'Detail Menu Catalog';
require_once '../../includes/header.php';
require_once '../../helpers/MenuCatalogHelper.php';
require_once '../../helpers/functions.php';

// Check access
if (!in_array($user['role'], ['admin', 'koperasi'])) {
    set_flash('error', 'Akses ditolak.');
    header('Location: ' . BASE_URL . '/modules/dashboard');
    exit();
}

if (!isset($_GET['id'])) {
    set_flash('error', 'ID menu tidak ditemukan.');
    header('Location: catalog.php');
    exit();
}

$catalogHelper = new MenuCatalogHelper();
$menu = $catalogHelper->getMenu($_GET['id']);

if (!$menu) {
    set_flash('error', 'Menu tidak ditemukan.');
    header('Location: catalog.php');
    exit();
}

require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-book-open me-2"></i><?= htmlspecialchars($menu['nama_menu']) ?>
            </h1>
            <p class="mb-0 text-muted">Detail Menu Catalog</p>
        </div>
        <div class="d-flex gap-2">
            <a href="catalog.php" class="btn btn-secondary shadow-sm">
                <i class="fas fa-arrow-left me-1"></i> Kembali
            </a>
            <a href="catalog_form.php?id=<?= $menu['id'] ?>" class="btn btn-warning text-white shadow-sm">
                <i class="fas fa-edit me-1"></i> Edit
            </a>
            <a href="planner_schedule.php?menu_id=<?= $menu['id'] ?>" class="btn btn-success shadow-sm">
                <i class="fas fa-calendar-plus me-1"></i> Jadwalkan
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Menu Info -->
        <div class="col-lg-4">
            <div class="card shadow mb-4 border-left-primary">
                <div class="card-header py-3 bg-primary text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-info-circle me-1"></i> Informasi Menu
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="small text-muted">Nama Menu</label>
                        <h5 class="fw-bold"><?= htmlspecialchars($menu['nama_menu']) ?></h5>
                    </div>
                    
                    <div class="mb-3">
                        <label class="small text-muted">Deskripsi</label>
                        <p><?= htmlspecialchars($menu['deskripsi'] ?: '-') ?></p>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-2">
                        <i class="fas fa-list text-primary me-2"></i>
                        <strong><?= count($menu['items']) ?></strong> Item
                    </div>
                    
                    <div class="mb-2">
                        <i class="fas fa-user text-info me-2"></i>
                        Dibuat oleh: <strong><?= htmlspecialchars($menu['created_by']) ?></strong>
                    </div>
                    
                    <div class="mb-2">
                        <i class="fas fa-clock text-success me-2"></i>
                        <?= format_tanggal($menu['created_at']) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Items List -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-success text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-list me-1"></i> Daftar Item Menu
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($menu['items'])): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <p>Belum ada item dalam menu ini</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th width="5%">#</th>
                                    <th width="15%">Tipe</th>
                                    <th width="35%">Nama</th>
                                    <th width="15%" class="text-end">Qty/Porsi</th>
                                    <th width="10%">Satuan</th>
                                    <th width="20%">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($menu['items'] as $index => $item): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td>
                                        <?php if ($item['produk_id']): ?>
                                            <span class="badge bg-primary">
                                                <i class="fas fa-box me-1"></i> Produk
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-info">
                                                <i class="fas fa-utensils me-1"></i> Resep
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($item['produk_id']): ?>
                                            <strong><?= htmlspecialchars($item['nama_produk']) ?></strong>
                                            <br><small class="text-muted"><?= htmlspecialchars($item['kode_produk']) ?></small>
                                        <?php else: ?>
                                            <strong><?= htmlspecialchars($item['nama_resep']) ?></strong>
                                            <br><small class="text-muted">Resep komposit</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <strong><?= format_number($item['qty_needed']) ?></strong>
                                    </td>
                                    <td>
                                        <?= $item['nama_satuan'] ? htmlspecialchars($item['nama_satuan']) : '-' ?>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?= htmlspecialchars($item['keterangan'] ?: '-') ?></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle me-1"></i>
                        <strong>Catatan:</strong> Qty/Porsi adalah jumlah per 1 porsi. 
                        Saat menu ini dijadwalkan, sistem akan mengalikan dengan total porsi yang dibutuhkan.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
