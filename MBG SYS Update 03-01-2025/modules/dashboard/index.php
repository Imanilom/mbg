<?php
$page_title = 'Dashboard';
require_once '../../includes/header.php';

// Load extended database functions
require_once '../../config/database_extended.php';

$user_role = get_user_data('role');
$user_id = get_user_data('id');
$kantor_id = get_user_data('kantor_id');

// Statistics based on role
if ($user_role == 'admin') {
    // Admin: All statistics
    $total_request = db_get_row("SELECT COUNT(*) as total FROM request")['total'];
    $pending_request = db_get_row("SELECT COUNT(*) as total FROM request WHERE status = 'pending'")['total'];
    $total_distribusi = db_get_row("SELECT COUNT(*) as total FROM distribusi")['total'];
    $total_piutang = db_get_row("SELECT SUM(sisa_piutang) as total FROM piutang WHERE status = 'belum_lunas'")['total'] ?? 0;
    
    // Market Price Statistics
    $market_stats = get_market_price_stats(date('Y'), date('n'));
    $total_produk_pasar = $market_stats['total_produk'] ?? 0;
    $total_pasar = $market_stats['total_pasar'] ?? 0;
    $avg_harga_rata = $market_stats['avg_harga_rata'] ?? 0;
    $last_market_update = $market_stats['last_update'] ?? null;
    
    // Grafik data - Distribusi per bulan (6 bulan terakhir)
    $chart_query = "SELECT DATE_FORMAT(tanggal_kirim, '%Y-%m') as bulan, COUNT(*) as total 
                    FROM distribusi 
                    WHERE tanggal_kirim >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    GROUP BY bulan 
                    ORDER BY bulan ASC";
    $chart_data = db_get_all($chart_query);
    
    // Get produk dengan harga terbaik
    $best_price_products = get_best_price_products(5);
    
} elseif ($user_role == 'koperasi') {
    // Koperasi: Request & Pembelanjaan
    $total_request = db_get_row("SELECT COUNT(*) as total FROM request")['total'];
    $pending_request = db_get_row("SELECT COUNT(*) as total FROM request WHERE status = 'pending'")['total'];
    $pembelanjaan_hari_ini = db_get_row("SELECT COUNT(*) as total FROM pembelanjaan WHERE tanggal = CURDATE()")['total'];
    $total_distribusi_dikirim = db_get_row("SELECT COUNT(*) as total FROM distribusi WHERE status = 'dikirim'")['total'];
    
    // Market Price Statistics untuk koperasi
    $market_stats = get_market_price_stats(date('Y'), date('n'));
    $best_price_products = get_best_price_products(5);
    
} elseif ($user_role == 'gudang') {
    // Gudang: Stok
    $total_produk = db_get_row("SELECT COUNT(DISTINCT produk_id) as total FROM gudang_stok")['total'];
    $stok_menipis = db_get_row("SELECT COUNT(*) as total FROM gudang_stok gs 
                                INNER JOIN produk p ON gs.produk_id = p.id 
                                WHERE gs.qty_available <= p.stok_minimum")['total'];
    $akan_expired = db_get_row("SELECT COUNT(*) as total FROM gudang_stok 
                                WHERE tanggal_expired BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")['total'];
    $barang_rusak = db_get_row("SELECT COUNT(*) as total FROM gudang_stok WHERE kondisi = 'rusak'")['total'];
    
    // Produk yang sering diminta
    $frequent_products = get_frequently_requested_products(5);
    
} elseif ($user_role == 'kantor') {
    // Kantor: Request & Distribusi ke kantor sendiri
    $total_request = db_get_row("SELECT COUNT(*) as total FROM request WHERE kantor_id = $kantor_id")['total'];
    $pending_request = db_get_row("SELECT COUNT(*) as total FROM request WHERE kantor_id = $kantor_id AND status = 'pending'")['total'];
    $distribusi_dikirim = db_get_row("SELECT COUNT(*) as total FROM distribusi WHERE kantor_id = $kantor_id AND status = 'dikirim'")['total'];
    $distribusi_diterima = db_get_row("SELECT COUNT(*) as total FROM distribusi WHERE kantor_id = $kantor_id AND status = 'diterima'")['total'];
}

require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';
?>

<!-- Dashboard Content -->
<div class="row mb-5">
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <h2 class="fw-800 mb-1" style="letter-spacing: -1px;">Dashboard Performa</h2>
                <p class="text-muted mb-0">Selamat datang kembali, <strong><?= $user['nama_lengkap'] ?></strong>. Berikut ringkasan hari ini.</p>
            </div>
            <div class="d-none d-md-block">
                <div class="p-2 border rounded-pill px-4 bg-white shadow-sm small fw-600 text-muted">
                    <i class="fas fa-calendar-alt me-2 text-primary"></i> <?= date('d M Y') ?>
                    <?php if ($user_role == 'admin' || $user_role == 'koperasi'): ?>
                    <span class="ms-3">
                        <i class="fas fa-sync-alt me-1 <?= $last_market_update ? 'text-success' : 'text-warning' ?>"></i>
                        <?= $last_market_update ? 'Update Harga: ' . format_datetime($last_market_update, false) : 'Belum ada data harga' ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row gx-4">
    <?php if ($user_role == 'admin'): ?>
    <!-- Admin Statistics -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-0 shadow-sm h-100 position-relative overflow-hidden">
            <div class="card-body p-4">
                <div class="d-flex align-items-center mb-3">
                    <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-xl me-3">
                        <i class="fas fa-clipboard-list fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold text-uppercase" style="letter-spacing: 1px;">Request</div>
                    </div>
                </div>
                <h2 class="fw-800 mb-2"><?= format_number($total_request) ?></h2>
                <div class="progress" style="height: 6px; border-radius: 10px; background: #f1f5f9;">
                    <div class="progress-bar bg-primary" style="width: 75%; border-radius: 10px;"></div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-0 shadow-sm h-100 position-relative overflow-hidden">
            <div class="card-body p-4">
                <div class="d-flex align-items-center mb-3">
                    <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-xl me-3">
                        <i class="fas fa-clock fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold text-uppercase" style="letter-spacing: 1px;">Pending</div>
                    </div>
                </div>
                <h2 class="fw-800 mb-2"><?= format_number($pending_request) ?></h2>
                <div class="small text-warning fw-bold"><i class="fas fa-exclamation-circle me-1"></i> Perlu Tindakan Segera</div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-0 shadow-sm h-100 position-relative overflow-hidden">
            <div class="card-body p-4">
                <div class="d-flex align-items-center mb-3">
                    <div class="p-3 bg-success bg-opacity-10 text-success rounded-xl me-3">
                        <i class="fas fa-shipping-fast fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold text-uppercase" style="letter-spacing: 1px;">Distribusi</div>
                    </div>
                </div>
                <h2 class="fw-800 mb-2"><?= format_number($total_distribusi) ?></h2>
                <div class="small text-success fw-bold"><i class="fas fa-check-circle me-1"></i> Logistik Lancar</div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-0 shadow-sm h-100 position-relative overflow-hidden">
            <div class="card-body p-4">
                <div class="d-flex align-items-center mb-3">
                    <div class="p-3 bg-info bg-opacity-10 text-info rounded-xl me-3">
                        <i class="fas fa-chart-line fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold text-uppercase" style="letter-spacing: 1px;">Harga Pasar</div>
                    </div>
                </div>
                <h2 class="fw-800 mb-2"><?= format_number($total_produk_pasar) ?></h2>
                <div class="small text-info fw-bold">
                    <i class="fas fa-store me-1"></i> <?= $total_pasar ?> Pasar
                    <?php if ($avg_harga_rata): ?>
                    <span class="ms-2">
                        <i class="fas fa-money-bill-wave me-1"></i> Avg: <?= format_rupiah($avg_harga_rata) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif ($user_role == 'koperasi'): ?>
    <!-- Koperasi Statistics -->
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card primary">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Total Request</h6>
                        <h3 class="mb-0"><?= format_number($total_request) ?></h3>
                    </div>
                    <div class="text-primary fs-1">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card warning">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Request Pending</h6>
                        <h3 class="mb-0"><?= format_number($pending_request) ?></h3>
                    </div>
                    <div class="text-warning fs-1">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card success">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Pembelanjaan Hari Ini</h6>
                        <h3 class="mb-0"><?= format_number($pembelanjaan_hari_ini) ?></h3>
                    </div>
                    <div class="text-success fs-1">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card info">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Data Harga Pasar</h6>
                        <h3 class="mb-0"><?= format_number($market_stats['total_produk'] ?? 0) ?></h3>
                    </div>
                    <div class="text-info fs-1">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif ($user_role == 'gudang'): ?>
    <!-- Gudang Statistics -->
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card primary">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Total Produk</h6>
                        <h3 class="mb-0"><?= format_number($total_produk) ?></h3>
                    </div>
                    <div class="text-primary fs-1">
                        <i class="fas fa-boxes"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card warning">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Stok Menipis</h6>
                        <h3 class="mb-0"><?= format_number($stok_menipis) ?></h3>
                    </div>
                    <div class="text-warning fs-1">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card danger">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Akan Expired</h6>
                        <h3 class="mb-0"><?= format_number($akan_expired) ?></h3>
                    </div>
                    <div class="text-danger fs-1">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card secondary">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Barang Rusak</h6>
                        <h3 class="mb-0"><?= format_number($barang_rusak) ?></h3>
                    </div>
                    <div class="text-secondary fs-1">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif ($user_role == 'kantor'): ?>
    <!-- Kantor Statistics -->
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card primary">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Total Request Saya</h6>
                        <h3 class="mb-0"><?= format_number($total_request) ?></h3>
                    </div>
                    <div class="text-primary fs-1">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card warning">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Request Pending</h6>
                        <h3 class="mb-0"><?= format_number($pending_request) ?></h3>
                    </div>
                    <div class="text-warning fs-1">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card info">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Barang Dikirim</h6>
                        <h3 class="mb-0"><?= format_number($distribusi_dikirim) ?></h3>
                    </div>
                    <div class="text-info fs-1">
                        <i class="fas fa-shipping-fast"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card success">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Barang Diterima</h6>
                        <h3 class="mb-0"><?= format_number($distribusi_diterima) ?></h3>
                    </div>
                    <div class="text-success fs-1">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Charts & Market Analysis -->
<?php if ($user_role == 'admin'): ?>
<div class="row mt-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Grafik Distribusi 6 Bulan Terakhir</h5>
                <a href="modules/analisis-harga/" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-chart-bar me-1"></i> Lihat Analisis Lengkap
                </a>
            </div>
            <div class="card-body">
                <canvas id="chartDistribusi"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-tags me-2"></i>Rekomendasi Pembelian</h5>
                <span class="badge bg-success">Harga Terbaik</span>
            </div>
            <div class="card-body">
                <?php if (!empty($best_price_products)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($best_price_products as $product): ?>
                        <div class="list-group-item px-0 border-bottom">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?= htmlspecialchars($product['nama_produk']) ?></h6>
                                    <small class="text-muted">
                                        <?= $product['nama_pasar'] ?> • 
                                        <span class="text-success fw-bold"><?= format_rupiah($product['harga_terendah']) ?></span>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <div class="badge bg-success bg-opacity-10 text-success mb-2">
                                        <i class="fas fa-percentage me-1"></i> <?= number_format($product['diskon_percent'], 1) ?>%
                                    </div>
                                    <div class="small text-muted">
                                        Avg: <?= format_rupiah($product['harga_rata_rata']) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <div class="text-muted mb-3">
                            <i class="fas fa-chart-line fa-3x mb-3"></i>
                            <p>Belum ada data harga pasar</p>
                        </div>
                          <a href="<?= BASE_URL ?>/modules/analisis/scrape.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-sync-alt me-1"></i> Scrape Data Harga
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activities -->
<div class="row mt-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Aktivitas Terbaru</h5>
            </div>
            <div class="card-body">
                <?php
                $activities = db_get_all("SELECT la.*, u.nama_lengkap 
                                          FROM log_activity la 
                                          INNER JOIN users u ON la.user_id = u.id 
                                          ORDER BY la.created_at DESC LIMIT 10");
                ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($activities as $act): ?>
                    <div class="list-group-item px-0">
                        <div class="d-flex w-100 justify-content-between">
                            <small class="fw-bold"><?= $act['nama_lengkap'] ?></small>
                            <small class="text-muted"><?= format_datetime($act['created_at']) ?></small>
                        </div>
                        <small class="text-muted"><?= $act['activity'] ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Statistik Harga Pasar</h5>
                <span class="badge bg-info"><?= date('M Y') ?></span>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small">Total Produk</span>
                        <span class="fw-bold"><?= format_number($total_produk_pasar) ?></span>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar bg-primary" style="width: <?= min($total_produk_pasar * 5, 100) ?>%"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small">Jumlah Pasar</span>
                        <span class="fw-bold"><?= format_number($total_pasar) ?></span>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar bg-info" style="width: <?= min($total_pasar * 20, 100) ?>%"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small">Rata-rata Harga</span>
                        <span class="fw-bold"><?= format_rupiah($avg_harga_rata) ?></span>
                    </div>
                </div>
                
                <div class="mt-4 pt-3 border-top">
                    <div class="d-flex justify-content-between small">
                        <span class="text-muted">Harga Terendah</span>
                        <span class="fw-bold text-success"><?= format_rupiah($market_stats['min_harga_global'] ?? 0) ?></span>
                    </div>
                    <div class="d-flex justify-content-between small mt-2">
                        <span class="text-muted">Harga Tertinggi</span>
                        <span class="fw-bold text-danger"><?= format_rupiah($market_stats['max_harga_global'] ?? 0) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php elseif ($user_role == 'koperasi'): ?>
<!-- Koperasi Dashboard -->
<div class="row mt-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Analisis Harga Pasar</h5>
                <a href="modules/analisis-harga/" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-chart-bar me-1"></i> Detail Analisis
                </a>
            </div>
            <div class="card-body">
                <?php if (!empty($best_price_products)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th>Pasar</th>
                                    <th>Harga Terendah</th>
                                    <th>Diskon</th>
                                    <th>Rata-rata</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($best_price_products as $product): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($product['nama_produk']) ?></div>
                                        <small class="text-muted"><?= $product['kode_produk'] ?></small>
                                    </td>
                                    <td><?= $product['nama_pasar'] ?></td>
                                    <td class="fw-bold text-success"><?= format_rupiah($product['harga_terendah']) ?></td>
                                    <td>
                                        <span class="badge bg-success bg-opacity-10 text-success">
                                            <i class="fas fa-percentage me-1"></i> <?= number_format($product['diskon_percent'], 1) ?>%
                                        </span>
                                    </td>
                                    <td class="text-muted"><?= format_rupiah($product['harga_rata_rata']) ?></td>
                                    <td>
                                        <a href="#" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Buat Request">
                                            <i class="fas fa-cart-plus"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <div class="text-muted mb-3">
                            <i class="fas fa-chart-line fa-3x mb-3"></i>
                            <p>Belum ada data harga pasar untuk analisis</p>
                        </div>
                        <a href="modules/analisis-harga/scrape.php" class="btn btn-primary">
                            <i class="fas fa-sync-alt me-1"></i> Ambil Data Harga Terbaru
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Info Pembelanjaan</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h6><i class="fas fa-lightbulb me-2"></i>Tips Pembelian</h6>
                    <p class="small mb-0">Gunakan data harga pasar untuk menentukan waktu pembelian terbaik.</p>
                </div>
                
                <div class="mt-4">
                    <h6 class="text-muted mb-3">Statistik Harga</h6>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Produk Tersedia</span>
                        <span class="fw-bold"><?= format_number($market_stats['total_produk'] ?? 0) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Pasar Terdata</span>
                        <span class="fw-bold"><?= format_number($market_stats['total_pasar'] ?? 0) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Update Terakhir</span>
                        <span class="fw-bold"><?= $market_stats['last_update'] ? format_datetime($market_stats['last_update'], false) : '-' ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php elseif ($user_role == 'gudang'): ?>
<!-- Gudang Dashboard -->
<div class="row mt-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-boxes me-2"></i>Produk Sering Diminta</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($frequent_products)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th>Jumlah Request</th>
                                    <th>Total Qty</th>
                                    <th>Harga Estimasi</th>
                                    <th>Stok Saat Ini</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($frequent_products as $product): 
                                    $stok_info = db_get_row("SELECT 
                                        SUM(qty_available) as total_stok,
                                        SUM(CASE WHEN kondisi = 'rusak' THEN qty_available ELSE 0 END) as stok_rusak
                                        FROM gudang_stok 
                                        WHERE produk_id = (SELECT id FROM produk WHERE nama_produk = ?)", 
                                        [$product['nama_produk']]);
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($product['nama_produk']) ?></div>
                                        <small class="text-muted"><?= $product['kode_produk'] ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?= $product['jumlah_request'] ?></span>
                                    </td>
                                    <td><?= format_number($product['total_qty_request']) ?> <?= $product['nama_satuan'] ?></td>
                                    <td><?= format_rupiah($product['harga_estimasi_avg']) ?></td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="fw-bold"><?= format_number($stok_info['total_stok'] ?? 0) ?></span>
                                            <?php if ($stok_info['stok_rusak'] > 0): ?>
                                            <small class="text-danger"><?= format_number($stok_info['stok_rusak']) ?> rusak</small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <div class="text-muted">
                            <i class="fas fa-box-open fa-3x mb-3"></i>
                            <p>Belum ada data request produk</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Peringatan Stok</h5>
            </div>
            <div class="card-body">
                <?php
                $warning_stocks = db_get_all("SELECT 
                    p.nama_produk,
                    p.kode_produk,
                    p.stok_minimum,
                    s.nama_satuan,
                    SUM(gs.qty_available) as total_stok
                FROM gudang_stok gs
                INNER JOIN produk p ON gs.produk_id = p.id
                INNER JOIN satuan s ON p.satuan_id = s.id
                WHERE gs.kondisi = 'baik'
                GROUP BY p.id, p.nama_produk, p.kode_produk, p.stok_minimum, s.nama_satuan
                HAVING total_stok <= p.stok_minimum
                ORDER BY total_stok ASC
                LIMIT 5");
                ?>
                
                <?php if (!empty($warning_stocks)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($warning_stocks as $stock): ?>
                        <div class="list-group-item px-0 border-bottom">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1"><?= htmlspecialchars($stock['nama_produk']) ?></h6>
                                    <small class="text-muted">Min: <?= format_number($stock['stok_minimum']) ?> <?= $stock['nama_satuan'] ?></small>
                                </div>
                                <div class="text-end">
                                    <div class="badge bg-warning text-dark">
                                        <?= format_number($stock['total_stok']) ?> <?= $stock['nama_satuan'] ?>
                                    </div>
                                    <div class="small text-muted mt-1"><?= $stock['kode_produk'] ?></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <div class="text-success mb-3">
                            <i class="fas fa-check-circle fa-3x"></i>
                        </div>
                        <p class="mb-0">Stok dalam kondisi baik</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// Chart.js for Admin
if ($user_role == 'admin') {
    $chart_labels = [];
    $chart_values = [];
    
    foreach ($chart_data as $data) {
        $chart_labels[] = date('M Y', strtotime($data['bulan'] . '-01'));
        $chart_values[] = $data['total'];
    }
    
    $extra_js = "
    <script src='https://cdn.jsdelivr.net/npm/chart.js'></script>
    <script>
    // Chart Distribusi
    const ctx = document.getElementById('chartDistribusi');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: " . json_encode($chart_labels) . ",
            datasets: [{
                label: 'Jumlah Distribusi',
                data: " . json_encode($chart_values) . ",
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99, 102, 241, 0.05)',
                borderWidth: 4,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#6366f1',
                pointBorderColor: '#fff',
                pointBorderWidth: 3,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: '#1e293b',
                    padding: 12,
                    titleFont: { size: 14, weight: 'bold' },
                    bodyFont: { size: 13 },
                    displayColors: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: '#f1f5f9',
                        drawBorder: false
                    },
                    ticks: {
                        color: '#94a3b8',
                        font: { size: 11 }
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#94a3b8',
                        font: { size: 11 }
                    }
                }
            }
        }
    });
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle=\"tooltip\"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
    </script>
    ";
}

require_once '../../includes/footer.php';
?>