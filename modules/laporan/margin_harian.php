<?php
$page_title = 'Laporan Margin Harian';
require_once '../../includes/header.php';
require_role(['admin']);

require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';
require_once '../../helpers/MarginHelper.php';

// Get current user role
$user_role = $_SESSION['user']['role'] ?? 'guest';

// Default date range (last 30 days)
$tanggal_start = $_GET['tanggal_start'] ?? date('Y-m-d', strtotime('-30 days'));
$tanggal_end = $_GET['tanggal_end'] ?? date('Y-m-d');
?>

<div class="row">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/dashboard/index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/laporan/index.php">Laporan</a></li>
                <li class="breadcrumb-item active">Margin Harian</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Laporan Margin Harian</h5>
                <button class="btn btn-success" onclick="exportExcel()">
                    <i class="fas fa-file-excel me-2"></i>Export Excel
                </button>
            </div>
            <div class="card-body">
                <!-- Filter -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Tanggal Mulai</label>
                        <input type="date" class="form-control" id="tanggal_start" value="<?= $tanggal_start ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tanggal Akhir</label>
                        <input type="date" class="form-control" id="tanggal_end" value="<?= $tanggal_end ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <button class="btn btn-primary d-block" onclick="loadData()">
                            <i class="fas fa-search me-2"></i>Tampilkan
                        </button>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row mb-4" id="summaryCards">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h6 class="card-title">Total Margin</h6>
                                <h3 id="totalMargin">Rp 0</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h6 class="card-title">Rata-rata/Hari</h6>
                                <h3 id="avgMargin">Rp 0</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h6 class="card-title">Jumlah Produk</h6>
                                <h3 id="totalProduk">0</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <h6 class="card-title">Total Transaksi</h6>
                                <h3 id="totalTransaksi">0</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="table-responsive">
                    <table id="tableMargin" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Kode Produk</th>
                                <th>Nama Produk</th>
                                <th>Satuan</th>
                                <th>Qty</th>
                                <th>HPP</th>
                                <th>Harga Jual</th>
                                <th>Margin/Unit</th>
                                <th>Total Margin</th>
                                <th>Transaksi</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <tr>
                                <td colspan="10" class="text-center">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    loadData();
});

function loadData() {
    const start = $('#tanggal_start').val();
    const end = $('#tanggal_end').val();
    
    $.ajax({
        url: '<?= BASE_URL ?>/modules/laporan/margin_harian_ajax.php',
        type: 'GET',
        data: { tanggal_start: start, tanggal_end: end },
        success: function(response) {
            if (response.success) {
                updateSummary(response.summary);
                updateTable(response.data);
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function() {
            Swal.fire('Error', 'Gagal memuat data', 'error');
        }
    });
}

function updateSummary(summary) {
    $('#totalMargin').text(formatRupiah(summary.total_margin));
    $('#avgMargin').text(formatRupiah(summary.avg_margin));
    $('#totalProduk').text(summary.total_produk);
    $('#totalTransaksi').text(summary.total_transaksi);
}

function updateTable(data) {
    let html = '';
    
    if (data.length === 0) {
        html = '<tr><td colspan="10" class="text-center">Tidak ada data</td></tr>';
    } else {
        data.forEach(row => {
            html += `
                <tr>
                    <td>${row.tanggal}</td>
                    <td>${row.kode_produk}</td>
                    <td>${row.nama_produk}</td>
                    <td>${row.nama_satuan}</td>
                    <td>${formatNumber(row.total_qty)}</td>
                    <td>${formatRupiah(row.hpp)}</td>
                    <td>${formatRupiah(row.harga_jual)}</td>
                    <td>${formatRupiah(row.avg_margin_per_unit)}</td>
                    <td class="fw-bold text-success">${formatRupiah(row.total_margin)}</td>
                    <td>${row.jumlah_transaksi}</td>
                </tr>
            `;
        });
    }
    
    $('#tableBody').html(html);
}

function exportExcel() {
    const start = $('#tanggal_start').val();
    const end = $('#tanggal_end').val();
    window.location.href = `<?= BASE_URL ?>/modules/laporan/margin_harian_export.php?tanggal_start=${start}&tanggal_end=${end}`;
}

function formatRupiah(amount) {
    return 'Rp ' + parseFloat(amount).toLocaleString('id-ID');
}

function formatNumber(num) {
    return parseFloat(num).toLocaleString('id-ID');
}
</script>

<?php require_once '../../includes/footer.php'; ?>
