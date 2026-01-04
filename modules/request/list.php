<?php
// modules/request/list.php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

// Cek login
checkLogin();

// Get user data
$user = getUserData();

$page_title = "Daftar Permintaan Barang";
include '../../includes/header.php';
include '../../includes/navbar.php';
include '../../includes/sidebar.php';
?>

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/dashboard/index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Request Barang</li>
    </ol>
</nav>

<div class="row">
    <div class="col-12">
        <!-- Filter Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-800 text-dark">
                    <i class="fas fa-filter me-2 text-primary opacity-50"></i> Filter Data
                </h5>
            </div>
            <div class="card-body p-4">
                <form id="filterForm">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted">Status</label>
                            <select class="form-select" id="filter_status">
                                <option value="">Semua Status</option>
                                <option value="pending">Pending</option>
                                <option value="diproses">Diproses</option>
                                <option value="selesai">Selesai</option>
                                <option value="ditolak">Ditolak</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted">Tanggal Dari</label>
                            <input type="date" class="form-control" id="filter_tanggal_dari">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted">Tanggal Sampai</label>
                            <input type="date" class="form-control" id="filter_tanggal_sampai">
                        </div>
                        <?php if($user['role'] != 'kantor'): ?>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted">Kantor</label>
                            <select class="form-select" id="filter_kantor">
                                <option value="">Semua Kantor</option>
                                <?php
                                $kantor_query = db_get_all("SELECT id, nama_kantor FROM kantor WHERE status='aktif' ORDER BY nama_kantor");
                                foreach($kantor_query as $k) {
                                    echo "<option value='{$k['id']}'>{$k['nama_kantor']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="mt-4 pt-3 border-top d-flex gap-2">
                        <button type="button" class="btn btn-primary px-4" id="btnFilter">
                            <i class="fas fa-search me-2"></i> Terapkan Filter
                        </button>
                        <button type="button" class="btn btn-outline-secondary px-4" id="btnReset">
                            <i class="fas fa-sync me-2"></i> Reset
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Data Card -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-800 text-dark">
                    <i class="fas fa-list me-2 text-primary opacity-50"></i> Daftar Permintaan
                </h5>
                <?php if($user['role'] == 'kantor' || $user['role'] == 'admin'): ?>
                <a href="add.php" class="btn btn-primary shadow-sm">
                    <i class="fas fa-plus me-2"></i> Tambah Request
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <div id="requestList" class="p-4"></div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    // Load data pertama kali
    loadRequestData();

    // Filter button
    $('#btnFilter').click(function() {
        loadRequestData();
    });

    // Reset button
    $('#btnReset').click(function() {
        $('#filterForm')[0].reset();
        loadRequestData();
    });

    // Function load data request
    function loadRequestData() {
        const filters = {
            status: $('#filter_status').val(),
            tanggal_dari: $('#filter_tanggal_dari').val(),
            tanggal_sampai: $('#filter_tanggal_sampai').val(),
            kantor: $('#filter_kantor').val()
        };

        $.ajax({
            url: 'ajax_list.php',
            type: 'POST',
            data: filters,
            beforeSend: function() {
                $('#requestList').html('<div class="text-center"><i class="fas fa-spinner fa-spin fa-3x"></i><p>Loading...</p></div>');
            },
            success: function(response) {
                $('#requestList').html(response);
            },
            error: function() {
                $('#requestList').html('<div class="alert alert-danger">Gagal memuat data</div>');
            }
        });
    }

    // Delete request
    $(document).on('click', '.btn-delete', function() {
        const id = $(this).data('id');
        const no_request = $(this).data('no');

        Swal.fire({
            title: 'Hapus Request?',
            text: `Yakin ingin menghapus request ${no_request}?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'delete.php',
                    type: 'POST',
                    data: { id: id },
                    success: function(res) {
                        if(res.status === 'success') {
                            Swal.fire('Berhasil!', res.message, 'success');
                            loadRequestData();
                        } else {
                            Swal.fire('Gagal!', res.message, 'error');
                        }
                    }
                });
            }
        });
    });
});
</script>

