<?php
// modules/distribusi/list.php
$page_title = 'Data Distribusi';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';

// Check role
checkLogin();
// Semua role bisa lihat log distribusi tapi dengan filter berbeda
// Admin/Koperasi/Gudang: Semua
// Kantor: Hanya miliknya

$user = getUserData();
?>

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/dashboard/index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Distribusi Barang</li>
    </ol>
</nav>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-800 text-dark mb-0"><?= $page_title ?></h4>
                <p class="text-muted small mb-0">Kelola pengiriman barang antar kantor</p>
            </div>
            <div class="d-flex gap-2">
                <?php if(in_array($user['role'], ['admin', 'koperasi', 'gudang'])): ?>
                <a href="add.php" class="btn btn-primary shadow-sm px-4">
                    <i class="fas fa-plus me-2"></i> Distribusi Baru
                </a>
                <?php endif; ?>
                
                <?php if($user['role'] == 'kantor'): ?>
                <a href="scan.php" class="btn btn-warning shadow-sm px-4">
                    <i class="fas fa-qrcode me-2"></i> Scan Penerimaan
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Filter Card -->
    <div class="col-12 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-filter me-2 text-primary opacity-50"></i> Filter Data</h6>
            </div>
            <div class="card-body p-4">
                <form id="filterForm" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Status</label>
                        <select class="form-select border-0 bg-light" name="status" id="status">
                            <option value="">Semua Status</option>
                            <option value="dikirim">Dikirim</option>
                            <option value="diterima">Diterima</option>
                            <option value="bermasalah">Bermasalah</option>
                        </select>
                    </div>
                    <?php if(in_array($user['role'], ['admin', 'koperasi', 'gudang'])): ?>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Kantor Tujuan</label>
                        <select class="form-select border-0 bg-light" name="kantor_id" id="kantor_id">
                            <option value="">Semua Kantor</option>
                            <?php
                            $kantor = db_get_all("SELECT id, nama_kantor FROM kantor WHERE status='aktif' ORDER BY nama_kantor");
                            foreach($kantor as $k) {
                                echo "<option value='{$k['id']}'>{$k['nama_kantor']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Tanggal Awal</label>
                        <input type="date" class="form-control border-0 bg-light" name="start_date" id="start_date">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Tanggal Akhir</label>
                        <input type="date" class="form-control border-0 bg-light" name="end_date" id="end_date">
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Table Data -->
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="tableDistribusi" class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th width="5%" class="py-3 border-0 px-4 text-center">No</th>
                                <th class="py-3 border-0">No Surat Jalan</th>
                                <th class="py-3 border-0">Tanggal Kirim</th>
                                <th class="py-3 border-0">Kantor Tujuan</th>
                                <th class="py-3 border-0">Pengirim</th>
                                <th class="py-3 border-0">Status</th>
                                <th width="15%" class="py-3 border-0 px-4 text-center">Aksi</th>
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    var table = $("#tableDistribusi").DataTable({
        "processing": true,
        "serverSide": true,
        "order": [],
        "ajax": {
            "url": "ajax_list.php",
            "type": "POST",
            "data": function(d) {
                d.status = $("#status").val();
                d.kantor_id = $("#kantor_id").val();
                d.start_date = $("#start_date").val();
                d.end_date = $("#end_date").val();
            }
        },
        "columnDefs": [
            { "targets": [0, 6], "orderable": false }
        ]
    });

    // Refresh table on filter change
    $("#status, #kantor_id, #start_date, #end_date").change(function() {
        table.ajax.reload();
    });
});

function deleteData(id) {
    Swal.fire({
        title: "Apakah anda yakin?",
        text: "Data yang dihapus tidak dapat dikembalikan!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Ya, Hapus!",
        cancelButtonText: "Batal"
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: "delete.php",
                type: "POST",
                data: {id: id},
                success: function(res) {
                    if(res.status == "success") {
                        Swal.fire("Berhasil!", res.message, "success");
                        $("#tableDistribusi").DataTable().ajax.reload();
                    } else {
                        Swal.fire("Gagal!", res.message, "error");
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
