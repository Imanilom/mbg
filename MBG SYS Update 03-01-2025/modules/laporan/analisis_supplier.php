<?php
$page_title = 'Analisis Supplier';
require_once '../../includes/header.php';
require_role(['admin']);

require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';
?>

<div class="row">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/dashboard/index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/laporan/index.php">Laporan</a></li>
                <li class="breadcrumb-item active">Analisis Supplier</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-truck me-2"></i>Analisis Supplier Termurah</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Tabel ini menampilkan supplier dengan harga termurah untuk setiap produk berdasarkan riwayat pembelian.
                </div>

                <div class="table-responsive">
                    <table id="tableSupplier" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Kode Produk</th>
                                <th>Nama Produk</th>
                                <th>Supplier Termurah</th>
                                <th>Harga Termurah</th>
                                <th>Harga Rata-rata</th>
                                <th>Jumlah Pembelian</th>
                                <th>Pembelian Terakhir</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#tableSupplier').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '<?= BASE_URL ?>/modules/laporan/analisis_supplier_ajax.php',
            type: 'POST'
        },
        columns: [
            { data: 'kode_produk' },
            { data: 'nama_produk' },
            { data: 'nama_supplier' },
            { data: 'harga_termurah' },
            { data: 'harga_rata_rata' },
            { data: 'jumlah_pembelian' },
            { data: 'pembelian_terakhir' }
        ],
        order: [[0, 'asc']]
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
