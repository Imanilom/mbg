<?php
// modules/laporan/laba_rugi.php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();
checkRole(['admin', 'koperasi']);

$bulan_filter = $_GET['bulan'] ?? date('m');
$tahun_filter = $_GET['tahun'] ?? date('Y');

// Query Summary Laba Rugi per Hari
$query_summary = "
    SELECT 
        d.tanggal_kirim,
        COUNT(DISTINCT d.id) as total_transaksi,
        SUM(dd.qty_kirim * dd.harga_jual) as total_omzet,
        SUM(dd.qty_kirim * dd.hpp) as total_hpp,
        SUM(dd.qty_kirim * (dd.harga_jual - dd.hpp)) as total_laba
    FROM distribusi_detail dd
    JOIN distribusi d ON dd.distribusi_id = d.id
    WHERE MONTH(d.tanggal_kirim) = '$bulan_filter' 
    AND YEAR(d.tanggal_kirim) = '$tahun_filter'
    AND d.status != 'batal'
    GROUP BY d.tanggal_kirim
    ORDER BY d.tanggal_kirim DESC
";

$summary = db_get_all($query_summary);

// Calculate Totals for Card
$grand_total_omzet = 0;
$grand_total_hpp = 0;
$grand_total_laba = 0;
foreach($summary as $s) {
    $grand_total_omzet += $s['total_omzet'];
    $grand_total_hpp += $s['total_hpp'];
    $grand_total_laba += $s['total_laba'];
}

$page_title = 'Laporan Laba Rugi';
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/dashboard/index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Laporan Laba Rugi</li>
    </ol>
</nav>

<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Bulan</label>
                        <select name="bulan" class="form-select">
                            <?php
                            $months = [
                                '01'=>'Januari', '02'=>'Februari', '03'=>'Maret', '04'=>'April',
                                '05'=>'Mei', '06'=>'Juni', '07'=>'Juli', '08'=>'Agustus',
                                '09'=>'September', '10'=>'Oktober', '11'=>'November', '12'=>'Desember'
                            ];
                            foreach($months as $k => $v) {
                                $sel = $k == $bulan_filter ? 'selected' : '';
                                echo "<option value='$k' $sel>$v</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Tahun</label>
                        <select name="tahun" class="form-select">
                            <?php
                            for($y = date('Y'); $y >= 2024; $y--) {
                                $sel = $y == $tahun_filter ? 'selected' : '';
                                echo "<option value='$y' $sel>$y</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-2"></i>Filter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 bg-primary-subtle h-100">
            <div class="card-body p-4">
                <div class="small text-primary-emphasis fw-bold mb-1">TOTAL OMZET (JUAL)</div>
                <h3 class="fw-800 text-primary mb-0"><?= format_rupiah($grand_total_omzet) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 bg-danger-subtle h-100">
            <div class="card-body p-4">
                <div class="small text-danger-emphasis fw-bold mb-1">TOTAL HPP (MODAL)</div>
                <h3 class="fw-800 text-danger mb-0"><?= format_rupiah($grand_total_hpp) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 bg-success h-100 text-white">
            <div class="card-body p-4">
                <div class="small text-white-50 fw-bold mb-1">TOTAL LABA KOTOR</div>
                <h3 class="fw-800 mb-0"><?= format_rupiah($grand_total_laba) ?></h3>
                <div class="small mt-2 text-white-50">Margin: <?= ($grand_total_omzet > 0) ? number_format(($grand_total_laba / $grand_total_omzet) * 100, 1) : 0 ?>%</div>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Table -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-800 text-dark"><i class="fas fa-chart-line me-2 text-primary opacity-50"></i> Rincian Harian</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="bg-light">
                    <tr>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="text-center py-3">Transaksi</th>
                        <th class="text-end py-3">Omzet</th>
                        <th class="text-end py-3">HPP</th>
                        <th class="text-end py-3 px-4">Laba</th>
                        <th class="text-center py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($summary)): ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">Belum ada data transaksi bulan ini.</td></tr>
                    <?php else: ?>
                        <?php foreach($summary as $row): ?>
                        <tr>
                            <td class="px-4 fw-bold"><?= format_tanggal($row['tanggal_kirim']) ?></td>
                            <td class="text-center"><?= $row['total_transaksi'] ?></td>
                            <td class="text-end"><?= format_rupiah($row['total_omzet']) ?></td>
                            <td class="text-end text-danger"><?= format_rupiah($row['total_hpp']) ?></td>
                            <td class="text-end px-4 fw-800 text-success"><?= format_rupiah($row['total_laba']) ?></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-primary rounded-circle" 
                                        type="button" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#detail-<?= strtotime($row['tanggal_kirim']) ?>">
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                            </td>
                        </tr>
                        <!-- Collapsible Detail per Item -->
                        <tr class="collapse bg-light" id="detail-<?= strtotime($row['tanggal_kirim']) ?>">
                            <td colspan="6" class="p-3">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body p-0">
                                        <table class="table table-sm mb-0">
                                            <thead class="text-muted small">
                                                <tr>
                                                    <th class="ps-4">No SJ</th>
                                                    <th>Barang</th>
                                                    <th class="text-center">Qty</th>
                                                    <th class="text-end">Jual</th>
                                                    <th class="text-end">HPP</th>
                                                    <th class="text-end pe-4">Laba</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                // Get Item Details for this date
                                                $q_items = "
                                                    SELECT d.no_surat_jalan, p.nama_produk, dd.qty_kirim, dd.harga_jual, dd.hpp,
                                                    (dd.qty_kirim * (dd.harga_jual - dd.hpp)) as item_laba
                                                    FROM distribusi_detail dd
                                                    JOIN distribusi d ON dd.distribusi_id = d.id
                                                    JOIN produk p ON dd.produk_id = p.id
                                                    WHERE d.tanggal_kirim = '{$row['tanggal_kirim']}'
                                                ";
                                                $items = db_get_all($q_items);
                                                foreach($items as $itm):
                                                ?>
                                                <tr>
                                                    <td class="ps-4 small"><?= $itm['no_surat_jalan'] ?></td>
                                                    <td class="small"><?= $itm['nama_produk'] ?></td>
                                                    <td class="text-center small"><?= number_format($itm['qty_kirim'],0) ?></td>
                                                    <td class="text-end small"><?= format_number($itm['harga_jual']) ?></td>
                                                    <td class="text-end small text-muted"><?= format_number($itm['hpp']) ?></td>
                                                    <td class="text-end small fw-bold text-success pe-4"><?= format_number($itm['item_laba']) ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
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

<?php include '../../includes/footer.php'; ?>
