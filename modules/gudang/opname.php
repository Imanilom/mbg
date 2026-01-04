<?php
// modules/gudang/opname.php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();
checkRole(['admin', 'gudang']);

// Create tables if not exist (Auto-setup for convenience)
if (isset($_GET['setup'])) {
    require_once 'setup_opname.php';
    set_flash('success', 'Tabel berhasil diinisialisasi');
    header("Location: opname.php");
    exit;
}

// Check if table exists
$check_table = db_query("SHOW TABLES LIKE 'stok_opname'");
if (mysqli_num_rows($check_table) == 0) {
    echo '<div class="alert alert-warning">Tabel Stok Opname belum ada. <a href="?setup=1">Klik di sini untuk setup database</a></div>';
    exit;
}

$page_title = 'Stok Opname';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Stok Opname</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="opname_form.php" class="btn btn-primary float-sm-right">
                        <i class="fas fa-plus"></i> Opname Baru
                    </a>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tabel_opname" class="table table-striped table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Nomor</th>
                                    <th>Tanggal</th>
                                    <th>Keterangan</th>
                                    <th>Status</th>
                                    <th>User</th>
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
        var table = $('#tabel_opname').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: 'ajax_opname.php',
                type: 'POST'
            },
            columns: [
                { data: 'nomor_dokumen' },
                { data: 'tanggal' },
                { data: 'keterangan' },
                { 
                    data: 'status',
                    render: function(data) {
                        if(data == 'draft') return '<span class=\"badge bg-secondary\">Draft</span>';
                        return '<span class=\"badge bg-success\">Final</span>';
                    }
                },
                { data: 'user_name' },
                { 
                    data: 'aksi', 
                    orderable: false, 
                    searchable: false 
                }
            ],
            order: [[1, 'desc']]
        });
    });
</script>
";
include '../../includes/footer.php'; 
?>
