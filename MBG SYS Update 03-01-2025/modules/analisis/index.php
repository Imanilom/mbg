<?php
$page_title = 'Analisis Harga Pasar';
require_once '../../includes/header.php';
require_once '../../config/database_extended.php';

// Check access
if (!in_array($user['role'], ['admin', 'koperasi'])) {
    set_flash_message('error', 'Akses ditolak.');
    header('Location: ' . base_url('modules/dashboard'));
    exit();
}

// Get filter parameters
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('n');
$produk_id = $_GET['produk_id'] ?? null;

// Get statistics
$market_stats = get_market_price_stats($year, $month);

// Get produk list for filter
$produk_list = db_get_all("SELECT id, kode_produk, nama_produk FROM produk ORDER BY nama_produk");

require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800"><?= $page_title ?></h1>
            <p class="mb-0">Analisis harga pasar untuk menentukan strategi pembelian terbaik</p>
        </div>
        <div>
            <a href="scrape.php" class="btn btn-primary">
                <i class="fas fa-sync-alt me-1"></i> Scrape Data Baru
            </a>
            <button class="btn btn-outline-secondary ms-2" data-bs-toggle="modal" data-bs-target="#filterModal">
                <i class="fas fa-filter me-1"></i> Filter
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Produk Tersedia</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= format_number($market_stats['total_produk'] ?? 0) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-box fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Jumlah Pasar</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= format_number($market_stats['total_pasar'] ?? 0) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-store fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Rata-rata Harga</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= format_rupiah($market_stats['avg_harga_rata'] ?? 0) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Update Terakhir</div>
                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                <?= $market_stats['last_update'] ? format_datetime($market_stats['last_update'], false) : '-' ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="row">
        <!-- Price Comparison Table -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Perbandingan Harga Pasar</h6>
                    <div class="small">Periode: <?= date('F Y', mktime(0, 0, 0, $month, 1, $year)) ?></div>
                </div>
                <div class="card-body">
                    <?php
                    $prices = db_get_all("
                        SELECT 
                            p.nama_produk,
                            p.kode_produk,
                            s.nama_satuan,
                            hp.nama_pasar,
                            hp.harga_terendah,
                            hp.harga_tertinggi,
                            hp.harga_rata_rata,
                            hp.jumlah_hari_terdata,
                            (hp.harga_tertinggi - hp.harga_terendah) as selisih,
                            hp.scraped_at
                        FROM harga_pasar hp
                        INNER JOIN produk p ON hp.produk_id = p.id
                        INNER JOIN satuan s ON p.satuan_id = s.id
                        WHERE hp.tahun = ? AND hp.bulan = ?
                        ORDER BY p.nama_produk, hp.harga_rata_rata ASC
                    ", [$year, $month]);
                    ?>
                    
                    <?php if (!empty($prices)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" id="priceTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Produk</th>
                                        <th>Pasar</th>
                                        <th>Terendah</th>
                                        <th>Tertinggi</th>
                                        <th>Rata-rata</th>
                                        <th>Selisih</th>
                                        <th>Update</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($prices as $price): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($price['nama_produk']) ?></div>
                                            <small class="text-muted"><?= $price['kode_produk'] ?></small>
                                        </td>
                                        <td><?= $price['nama_pasar'] ?></td>
                                        <td class="text-success fw-bold"><?= format_rupiah($price['harga_terendah']) ?></td>
                                        <td class="text-danger fw-bold"><?= format_rupiah($price['harga_tertinggi']) ?></td>
                                        <td class="fw-bold"><?= format_rupiah($price['harga_rata_rata']) ?></td>
                                        <td>
                                            <span class="badge <?= $price['selisih'] > 0 ? 'bg-warning text-dark' : 'bg-secondary' ?>">
                                                <?= format_rupiah($price['selisih']) ?>
                                            </span>
                                        </td>
                                        <td class="small"><?= format_datetime($price['scraped_at'], false) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-chart-line fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">Belum ada data untuk periode ini</h5>
                            <p class="text-muted">Scrape data harga pasar terlebih dahulu</p>
                            <a href="scrape.php" class="btn btn-primary">
                                <i class="fas fa-sync-alt me-1"></i> Scrape Data
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recommendations & Filter -->
        <div class="col-lg-4">
            <!-- Best Prices -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-tags me-1"></i>Rekomendasi Pembelian
                    </h6>
                </div>
                <div class="card-body">
                    <?php
                    $recommendations = db_get_all("
                        SELECT * FROM v_rekomendasi_pembelian 
                        WHERE status_harga = 'DIBAWAH_HARGA'
                        ORDER BY selisih_percent DESC 
                        LIMIT 5
                    ");
                    ?>
                    
                    <?php if (!empty($recommendations)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recommendations as $rec): ?>
                            <div class="list-group-item px-0 border-bottom">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1 fw-bold"><?= htmlspecialchars($rec['nama_produk']) ?></h6>
                                        <small class="text-muted">
                                            <?= $rec['nama_pasar'] ?> • <?= $rec['nama_satuan'] ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <div class="badge bg-success text-white mb-2">
                                            <?= number_format($rec['selisih_percent'], 1) ?>%
                                        </div>
                                        <div class="fw-bold text-success">
                                            <?= format_rupiah($rec['harga_pasar']) ?>
                                        </div>
                                        <small class="text-muted d-block">
                                            vs <?= format_rupiah($rec['harga_sistem']) ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-percentage fa-3x text-muted mb-3"></i>
                            <p class="text-muted mb-0">Belum ada rekomendasi pembelian</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Price Trend -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-line me-1"></i>Trend Harga
                    </h6>
                </div>
                <div class="card-body">
                    <?php if ($produk_id): 
                        $trends = get_price_trend($produk_id, 6);
                        if (!empty($trends)):
                    ?>
                        <canvas id="trendChart" height="200"></canvas>
                        <script>
                        const trendCtx = document.getElementById('trendChart');
                        new Chart(trendCtx, {
                            type: 'line',
                            data: {
                                labels: <?= json_encode(array_column($trends, 'periode')) ?>,
                                datasets: [{
                                    label: 'Harga Rata-rata',
                                    data: <?= json_encode(array_column($trends, 'avg_rata')) ?>,
                                    borderColor: '#4e73df',
                                    backgroundColor: 'rgba(78, 115, 223, 0.05)',
                                    borderWidth: 2,
                                    fill: true,
                                    tension: 0.4
                                }]
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    legend: {
                                        display: false
                                    }
                                }
                            }
                        });
                        </script>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <p class="text-muted">Belum ada data trend untuk produk ini</p>
                        </div>
                    <?php endif; else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-chart-area fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Pilih produk untuk melihat trend harga</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Modal -->
<div class="modal fade" id="filterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="GET" action="">
                <div class="modal-header">
                    <h5 class="modal-title">Filter Analisis Harga</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tahun</label>
                        <select class="form-select" name="year">
                            <?php for ($y = 2023; $y <= date('Y'); $y++): ?>
                            <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Bulan</label>
                        <select class="form-select" name="month">
                            <?php
                            $months = [
                                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                            ];
                            foreach ($months as $num => $name): ?>
                            <option value="<?= $num ?>" <?= $num == $month ? 'selected' : '' ?>>
                                <?= $name ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Produk</label>
                        <select class="form-select" name="produk_id">
                            <option value="">Semua Produk</option>
                            <?php foreach ($produk_list as $produk): ?>
                            <option value="<?= $produk['id'] ?>" <?= $produk['id'] == $produk_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($produk['nama_produk']) ?> (<?= $produk['kode_produk'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Terapkan Filter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extra_js = "
<script src='https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js'></script>
<script>
$(document).ready(function() {
    $('#priceTable').DataTable({
        pageLength: 25,
        order: [[3, 'asc']],
        language: {
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_ data',
            info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
            paginate: {
                first: 'Pertama',
                last: 'Terakhir',
                next: 'Selanjutnya',
                previous: 'Sebelumnya'
            }
        }
    });
});
</script>
";

require_once '../../includes/footer.php';
?>