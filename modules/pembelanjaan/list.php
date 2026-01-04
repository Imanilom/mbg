<?php
// modules/pembelanjaan/list.php
$page_title = 'Data Pembelanjaan';
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();
checkRole(['admin', 'koperasi']);

include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/dashboard/index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Data Pembelanjaan</li>
    </ol>
</nav>

<!-- Summary Stats -->
<div class="row gx-4 mb-2">
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-0 shadow-sm h-100 position-relative overflow-hidden">
            <div class="card-body p-4">
                <div class="d-flex align-items-center mb-3">
                    <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-xl me-3">
                        <i class="fas fa-shopping-cart fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold text-uppercase" style="letter-spacing: 1px;">Belanja Hari Ini</div>
                    </div>
                </div>
                <?php
                $today = date('Y-m-d');
                $sql_today = "SELECT COUNT(*) as total FROM pembelanjaan WHERE tanggal = '$today'";
                $row_today = db_get_row($sql_today);
                ?>
                <h2 class="fw-800 mb-0"><?= format_number($row_today['total'] ?? 0) ?></h2>
                <div class="small text-muted mt-2">Transaksi pada hari ini</div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-0 shadow-sm h-100 position-relative overflow-hidden">
            <div class="card-body p-4">
                <div class="d-flex align-items-center mb-3">
                    <div class="p-3 bg-success bg-opacity-10 text-success rounded-xl me-3">
                        <i class="fas fa-money-bill-wave fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold text-uppercase" style="letter-spacing: 1px;">Total Bulan Ini</div>
                    </div>
                </div>
                <?php
                $month = date('Y-m');
                $sql_month = "SELECT SUM(total_belanja) as total FROM pembelanjaan WHERE DATE_FORMAT(tanggal, '%Y-%m') = '$month'";
                $row_month = db_get_row($sql_month);
                ?>
                <h2 class="fw-800 mb-0"><?= format_rupiah($row_month['total'] ?? 0) ?></h2>
                <div class="small text-success fw-bold mt-2"><i class="fas fa-calendar-alt me-1"></i> Periode <?= date('F Y') ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <!-- Filter Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-800 text-dark">
                    <i class="fas fa-filter me-2 text-primary opacity-50"></i> Filter Data
                </h5>
                <a href="add.php" class="btn btn-primary shadow-sm">
                    <i class="fas fa-plus me-2"></i> Pembelanjaan Baru
                </a>
            </div>
            <div class="card-body p-4">
                <form id="filterForm" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Periode</label>
                        <select class="form-select" name="periode_type" id="periode_type">
                            <option value="">Semua</option>
                            <option value="harian">Harian</option>
                            <option value="mingguan">Mingguan</option>
                            <option value="bulanan">Bulanan</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Supplier</label>
                        <select class="form-select" name="supplier_id" id="supplier_id">
                            <option value="">Semua Supplier</option>
                            <?php
                            $supp = db_get_all("SELECT id, nama_supplier FROM supplier WHERE status='aktif' ORDER BY nama_supplier");
                            foreach($supp as $s) {
                                echo "<option value='{$s['id']}'>{$s['nama_supplier']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Tanggal Awal</label>
                        <input type="date" class="form-control" name="start_date" id="start_date">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Tanggal Akhir</label>
                        <input type="date" class="form-control" name="end_date" id="end_date">
                    </div>
                </form>
            </div>
        </div>

        <!-- Table Data -->
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive p-4">
                    <table id="tablePembelanjaan" class="table table-hover w-100">
                        <thead>
                            <tr>
                                <th width="5%">No</th>
                                <th>No Pembelanjaan</th>
                                <th>Tanggal</th>
                                <th>Supplier</th>
                                <th>Periode</th>
                                <th>Total Belanja</th>
                                <th>Status</th>
                                <th width="15%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Data via Ajax -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// Ajax Handler for this page embedded for simplicity or separate file
// Creating a separate ajax list file is better practice, but check plan.
// Plan implies creating list.php. I will create ajax_list.php as well implicitly or inline it. 
// I'll assume ajax_list.php is needed. I'll create it in next step.

echo '
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css">
';

$extra_js = '
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    var table = $("#tablePembelanjaan").DataTable({
        "processing": true,
        "serverSide": true,
        "order": [[2, "desc"]],
        "ajax": {
            "url": "ajax_list.php",
            "type": "POST",
            "data": function(d) {
                d.periode_type = $("#periode_type").val();
                d.supplier_id = $("#supplier_id").val();
                d.start_date = $("#start_date").val();
                d.end_date = $("#end_date").val();
            }
        },
        "columnDefs": [
            { "targets": [0, 7], "orderable": false }
        ]
    });

    // Refresh table filters
    $("#periode_type, #supplier_id, #start_date, #end_date").change(function() {
        table.ajax.reload();
    });
});

function deleteData(id) {
    Swal.fire({
        title: "Hapus Pembelanjaan?",
        text: "Data yang dihapus permanen!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        confirmButtonText: "Ya, Hapus!",
        cancelButtonText: "Batal"
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: "delete.php",
                type: "POST",
                data: {id: id},
                success: function(response) {
                    try {
                        var res = JSON.parse(response);
                        if(res.status === "success") {
                            Swal.fire("Berhasil", res.message, "success");
                            $("#tablePembelanjaan").DataTable().ajax.reload();
                        } else {
                            Swal.fire("Gagal", res.message, "error");
                        }
                    } catch(e) {
                         Swal.fire("Error", "Gagal response server", "error");
                    }
                }
            });
        }
    });
}
</script>
';

include '../../includes/footer.php'; 
?>
