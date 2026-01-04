<?php
// modules/distribusi/riwayat.php
$page_title = 'Riwayat Penerimaan Barang';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';

// Check role
checkLogin();
// Halaman ini khusus untuk role kantor untuk melihat riwayat penerimaan
checkRole(['kantor']);

$user = getUserData();
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0"><?= $page_title ?></h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="../../modules/dashboard/index.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Riwayat Penerimaan</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <!-- Filter -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-filter"></i> Filter Data</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <form id="filterForm" class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Status</label>
                                <select class="form-control" name="status" id="status">
                                    <option value="">Semua Status</option>
                                    <option value="dikirim">Dikirim</option>
                                    <option value="diterima">Diterima</option>
                                    <option value="bermasalah">Bermasalah</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Tanggal Awal</label>
                                <input type="date" class="form-control" name="start_date" id="start_date">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Tanggal Akhir</label>
                                <input type="date" class="form-control" name="end_date" id="end_date">
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Table Data -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tableRiwayat" class="table table-bordered table-striped table-hover">
                            <thead>
                                <tr>
                                    <th width="5%">No</th>
                                    <th>No Surat Jalan</th>
                                    <th>Tanggal Kirim</th>
                                    <th>Pengirim</th>
                                    <th>Status</th>
                                    <th width="15%">Aksi</th>
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
// Helper function for DataTables CSS/JS
$extra_css = '
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css">
';

$extra_js = '
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    var table = $("#tableRiwayat").DataTable({
        "processing": true,
        "serverSide": true,
        "order": [],
        "ajax": {
            "url": "ajax_list.php",
            "type": "POST",
            "data": function(d) {
                d.status = $("#status").val();
                d.start_date = $("#start_date").val();
                d.end_date = $("#end_date").val();
            }
        },
        "columnDefs": [
            { "targets": [0, 5], "orderable": false }
        ]
    });

    // Refresh table on filter change
    $("#status, #start_date, #end_date").change(function() {
        table.ajax.reload();
    });
});
</script>
';

include '../../includes/footer.php'; 
?>
