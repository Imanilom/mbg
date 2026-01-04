<?php
// modules/penerimaan/list.php
$page_title = 'Data Penerimaan Barang';
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();
checkRole(['admin', 'koperasi', 'gudang']);

include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/dashboard/index.php">Dashboard</a></li>
        <li class="breadcrumb-item active text-dark">Penerimaan Barang</li>
    </ol>
</nav>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-800 text-dark mb-0"><?= $page_title ?></h4>
                <p class="text-muted small mb-0">Log penerimaan barang dari supplier</p>
            </div>
            <a href="add.php" class="btn btn-primary shadow-sm px-4">
                <i class="fas fa-plus me-2"></i> Penerimaan Baru
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="tablePenerimaan" class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th width="5%" class="py-3 border-0 px-4 text-center">No</th>
                                <th class="py-3 border-0">No Penerimaan</th>
                                <th class="py-3 border-0">Tanggal</th>
                                <th class="py-3 border-0">Supplier</th>
                                <th class="py-3 border-0 text-center">No Surat Jalan</th>
                                <th class="py-3 border-0">Penerima</th>
                                <th class="py-3 border-0">Status</th>
                                <th width="15%" class="py-3 border-0 px-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Ajax Load -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
echo '
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
    $("#tablePenerimaan").DataTable({
        "processing": true,
        "serverSide": true,
        "responsive": true,
        "order": [[2, "desc"]],
        "ajax": {
            "url": "ajax_list.php",
            "type": "POST"
        },
        "columnDefs": [
            { "targets": [0, 4, 7], "className": "text-center" },
            { "targets": [0, 7], "orderable": false }
        ],
        "language": {
            "search": "",
            "searchPlaceholder": "Cari data...",
            "lengthMenu": "_MENU_",
            "paginate": {
                "previous": "<i class=\'fas fa-chevron-left\'></i>",
                "next": "<i class=\'fas fa-chevron-right\'></i>"
            }
        },
        "drawCallback": function() {
            $(".dataTables_filter input").addClass("form-control border-0 bg-light-subtle shadow-none");
            $(".dataTables_length select").addClass("form-select border-0 bg-light-subtle shadow-none");
        }
    });
});
</script>
';
include '../../includes/footer.php'; 
?>
