<?php
// modules/gudang/stok.php
$page_title = 'Stok Gudang';
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();
checkRole(['admin', 'gudang', 'koperasi']);

// Get Stock Alerts
$alert_query = "SELECT 
                    COUNT(CASE WHEN qty_available <= stok_minimum THEN 1 END) as low_stock,
                    COUNT(CASE WHEN DATEDIFF(tanggal_expired, CURDATE()) <= 30 AND tanggal_expired >= CURDATE() THEN 1 END) as expiring_soon,
                    COUNT(CASE WHEN tanggal_expired < CURDATE() THEN 1 END) as expired
                FROM gudang_stok gs
                JOIN produk p ON gs.produk_id = p.id
                WHERE gs.qty_available > 0";
$alerts = db_get_row($alert_query);

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Gudang Stok</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="opname.php" class="btn btn-primary float-sm-right">
                        <i class="align-middle" data-feather="clipboard"></i> Stock Opname
                    </a>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">

            <!-- Alerts Section -->
            <div class="row">
                <div class="col-md-4">
                    <div class="card text-white bg-warning mb-3">
                        <div class="card-body">
                            <h5 class="card-title text-white">Stok Menipis</h5>
                            <h2 class="text-white"><?php echo $alerts['low_stock'] ?? 0; ?> Item</h2>
                            <p class="card-text">Item dengan stok di bawah minimum.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-info mb-3">
                        <div class="card-body">
                            <h5 class="card-title text-white">Akan Kadaluarsa</h5>
                            <h2 class="text-white"><?php echo $alerts['expiring_soon'] ?? 0; ?> Batch</h2>
                            <p class="card-text">Kadaluarsa dalam 30 hari.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-danger mb-3">
                        <div class="card-body">
                            <h5 class="card-title text-white">Sudah Kadaluarsa</h5>
                            <h2 class="text-white"><?php echo $alerts['expired'] ?? 0; ?> Batch</h2>
                            <p class="card-text">Batch yang sudah melewati tanggal expired.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Filter Kategori</label>
                            <select id="filter_kategori" class="form-select">
                                <option value="">Semua Kategori</option>
                                <?php
                                $kategori = db_get_all("SELECT * FROM kategori_produk ORDER BY nama_kategori");
                                foreach ($kategori as $k) {
                                    echo "<option value='{$k['id']}'>{$k['nama_kategori']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                            <div class="col-md-3">
                                <label class="form-label">Filter Status Stok</label>
                                <select class="form-select" id="filter_status">
                                    <option value="">Semua</option>
                                    <option value="aman">Aman</option>
                                    <option value="low">Menipis ( < Stok Min)</option>
                                    <option value="empty">Kosong</option>
                                </select>
                            </div>
                    </div>

                    <div class="table-responsive">
                        <table id="tabel_stok" class="table table-striped table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Kode</th>
                                    <th>Nama Produk</th>
                                    <th>Kategori</th>
                                    <th>Total Stok</th>
                                    <th>Satuan</th>
                                    <th>Nilai Aset</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data loaded via Ajax -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<?php 
$extra_js = "
<script>
    $(document).ready(function() {
        var table = $('#tabel_stok').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: 'ajax_stok.php',
                type: 'POST',
                data: function(d) {
                    d.kategori = $('#filter_kategori').val();
                    d.status = $('#filter_status').val();
                }
            },
            columns: [
                { data: 'kode_produk' },
                { data: 'nama_produk' },
                { data: 'nama_kategori' },
                { 
                    data: 'total_stok',
                    render: function(data, type, row) {
                        return '<span class=\"fw-bold\">' + data + '</span>';
                    }
                },
                { data: 'nama_satuan' },
                { data: 'nilai_aset' },
                { data: 'status_stok' },
                { data: 'aksi', orderable: false, searchable: false }
            ],
            order: [[1, 'asc']]
        });

        $('#filter_kategori, #filter_status').change(function() {
            table.draw();
        });
    });
</script>
";
include '../../includes/footer.php'; 
?>
