<?php
// modules/piutang/list.php
$page_title = 'Piutang Distribusi';
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();
checkRole(['admin', 'koperasi', 'kantor']); // Kantor might view their own debt? assuming admin/koperasi manage it.

// Setup check
if (isset($_GET['setup'])) {
    require_once 'setup_piutang.php';
    set_flash('success', 'Database Piutang berhasil diinisialisasi');
    header("Location: list.php");
    exit;
}

$check = db_query("SHOW TABLES LIKE 'piutang'");
if (mysqli_num_rows($check) == 0) {
    echo '<div class="alert alert-warning">Tabel Piutang belum ada. <a href="?setup=1">Klik di sini untuk setup</a></div>';
    exit;
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Piutang Distribusi</h1>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">

            <!-- Summary Cards -->
            <?php
            $summary = db_get_row("SELECT 
                                    SUM(sisa_piutang) as total_outstanding,
                                    COUNT(CASE WHEN status='belum_lunas' THEN 1 END) as count_belum_lunas
                                   FROM piutang");
            ?>
            <div class="row">
                <div class="col-md-6">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <h5 class="card-title text-white">Total Piutang Belum Lunas</h5>
                            <h2 class="text-white"><?php echo format_rupiah($summary['total_outstanding'] ?? 0); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h5 class="card-title text-white">Jumlah Invoice Terbuka</h5>
                            <h2 class="text-white"><?php echo $summary['count_belum_lunas'] ?? 0; ?></h2>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tabel_piutang" class="table table-striped table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>No Referensi</th>
                                    <th>Tanggal</th>
                                    <th>Jatuh Tempo</th>
                                    <th>Kantor / Koperasi</th>
                                    <th>Total Piutang</th>
                                    <th>Sisa Piutang</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Ajax -->
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
        var table = $('#tabel_piutang').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: 'ajax_piutang.php',
                type: 'POST'
            },
            columns: [
                { data: 'no_referensi' },
                { data: 'tanggal' },
                { data: 'jatuh_tempo' },
                { data: 'nama_kantor' },
                { data: 'total_piutang' },
                { data: 'sisa_piutang' },
                { data: 'status' },
                { data: 'aksi', orderable: false }
            ],
            order: [[1, 'desc']]
        });
    });
</script>
";
include '../../includes/footer.php'; 
?>
