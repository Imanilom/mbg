<?php
$page_title = 'Laporan Margin Bulanan';
require_once '../../includes/header.php';
require_role(['admin']);

require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';
require_once '../../helpers/MarginHelper.php';

// Default to current month
$bulan = $_GET['bulan'] ?? date('n');
$tahun = $_GET['tahun'] ?? date('Y');

// Get monthly profit
$profit = MarginHelper::getMonthlyProfit($bulan, $tahun);
$top_products = MarginHelper::getTopProductsByMargin($bulan, $tahun, 10);
$highest_day = MarginHelper::getHighestMarginDay($bulan, $tahun);

$bulan_names = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
?>

<div class="row">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/dashboard/index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/laporan/index.php">Laporan</a></li>
                <li class="breadcrumb-item active">Margin Bulanan</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Laporan Laba Bulanan</h5>
            </div>
            <div class="card-body">
                <!-- Filter -->
                <form method="GET" class="row mb-4">
                    <div class="col-md-3">
                        <label class="form-label">Bulan</label>
                        <select name="bulan" class="form-select">
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>" <?= $i == $bulan ? 'selected' : '' ?>><?= $bulan_names[$i] ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tahun</label>
                        <select name="tahun" class="form-select">
                            <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                                <option value="<?= $y ?>" <?= $y == $tahun ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary d-block">
                            <i class="fas fa-search me-2"></i>Tampilkan
                        </button>
                    </div>
                </form>

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h6 class="card-title">Total Laba Bulan Ini</h6>
                                <h3><?= format_rupiah($profit['total_margin']) ?></h3>
                                <small><?= $bulan_names[$bulan] ?> <?= $tahun ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h6 class="card-title">Rata-rata/Hari</h6>
                                <h3><?= format_rupiah($profit['avg_margin_per_hari']) ?></h3>
                                <small><?= $profit['jumlah_hari'] ?> hari transaksi</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h6 class="card-title">Jumlah Produk</h6>
                                <h3><?= $profit['jumlah_produk'] ?></h3>
                                <small>produk terjual</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <h6 class="card-title">Total Transaksi</h6>
                                <h3><?= $profit['total_transaksi'] ?></h3>
                                <small>distribusi</small>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($highest_day): ?>
                <div class="alert alert-info">
                    <i class="fas fa-trophy me-2"></i>
                    <strong>Hari Terbaik:</strong> 
                    <?= format_tanggal($highest_day['tanggal']) ?> 
                    dengan margin <?= format_rupiah($highest_day['total_margin_hari']) ?>
                    (<?= $highest_day['jumlah_produk'] ?> produk, <?= $highest_day['total_transaksi'] ?> transaksi)
                </div>
                <?php endif; ?>

                <!-- Top Products -->
                <h6 class="mt-4 mb-3">Top 10 Produk Berdasarkan Margin</h6>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Kode</th>
                                <th>Nama Produk</th>
                                <th>Total Qty</th>
                                <th>Avg Margin/Unit</th>
                                <th>Total Margin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_products)): ?>
                                <tr><td colspan="6" class="text-center">Tidak ada data</td></tr>
                            <?php else: ?>
                                <?php $rank = 1; foreach ($top_products as $prod): ?>
                                    <tr>
                                        <td><?= $rank++ ?></td>
                                        <td><?= $prod['kode_produk'] ?></td>
                                        <td><?= $prod['nama_produk'] ?></td>
                                        <td><?= format_number($prod['total_qty'], 2) ?> <?= $prod['nama_satuan'] ?></td>
                                        <td><?= format_rupiah($prod['avg_margin_per_unit']) ?></td>
                                        <td class="fw-bold text-success"><?= format_rupiah($prod['total_margin']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
