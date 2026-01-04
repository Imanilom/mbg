<?php
$page_title = 'Manajemen Supplier';
require_once '../../includes/header.php';
require_role(['admin', 'koperasi']);

require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';
?>

<div class="row">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/dashboard/index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Master Supplier</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-truck me-2"></i>Data Supplier</h5>
                <div>
                    <button class="btn btn-success me-2" onclick="exportExcel()">
                        <i class="fas fa-file-excel me-2"></i>Export Excel
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalSupplier" onclick="addSupplier()">
                        <i class="fas fa-plus me-2"></i>Tambah Supplier
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tableSupplier" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th width="5%">No</th>
                                <th>Kode</th>
                                <th>Nama Supplier</th>
                                <th>Alamat</th>
                                <th>No Telp</th>
                                <th>PIC</th>
                                <th>Status</th>
                                <th width="12%">Aksi</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Supplier -->
<div class="modal fade" id="modalSupplier" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formSupplier">
                <input type="hidden" name="id" id="supplierId">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalSupplierTitle">Tambah Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Kode Supplier <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="kode_supplier" id="kode_supplier" readonly required>
                                <small class="text-muted">Otomatis generate</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" id="status">
                                    <option value="aktif">Aktif</option>
                                    <option value="nonaktif">Non-aktif</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Supplier <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_supplier" id="nama_supplier" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Alamat</label>
                        <textarea class="form-control" name="alamat" id="alamat" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">No Telepon</label>
                                <input type="text" class="form-control format-number" name="no_telp" id="no_telp">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" id="email">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Nama PIC</label>
                                <input type="text" class="form-control" name="pic_name" id="pic_name">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">No Telepon PIC</label>
                                <input type="text" class="form-control format-number" name="pic_phone" id="pic_phone">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <textarea class="form-control" name="keterangan" id="keterangan" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extra_js = "
<script src='https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js'></script>
<script src='https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
<script>
let table;

$(document).ready(function() {
    table = $('#tableSupplier').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '" . BASE_URL . "/modules/master/supplier_ajax.php',
            type: 'POST'
        },
        columns: [
            { data: 'no', orderable: false },
            { data: 'kode_supplier' },
            { data: 'nama_supplier' },
            { data: 'alamat' },
            { data: 'no_telp' },
            { data: 'pic' },
            { data: 'status' },
            { data: 'aksi', orderable: false }
        ],
        order: [[1, 'asc']]
    });
    
    $('#formSupplier').on('submit', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: '" . BASE_URL . "/modules/master/supplier_ajax.php',
            type: 'POST',
            data: $(this).serialize() + '&action=save',
            success: function(response) {
                let res = response;
                if (res.success) {
                    $('#modalSupplier').modal('hide');
                    table.ajax.reload();
                    Swal.fire('Berhasil!', res.message, 'success');
                } else {
                    Swal.fire('Error!', res.message, 'error');
                }
            }
        });
    });
});

function addSupplier() {
    $('#modalSupplierTitle').text('Tambah Supplier');
    $('#formSupplier')[0].reset();
    $('#supplierId').val('');
    
    // Generate code
    $.ajax({
        url: '" . BASE_URL . "/modules/master/supplier_ajax.php',
        type: 'POST',
        data: { action: 'generate_code' },
        success: function(response) {
            let res = response;
            $('#kode_supplier').val(res.code);
        }
    });
}

function editSupplier(id) {
    $.ajax({
        url: '" . BASE_URL . "/modules/master/supplier_ajax.php',
        type: 'POST',
        data: { action: 'get', id: id },
        success: function(response) {
            let res = response;
            if (res.success) {
                $('#modalSupplierTitle').text('Edit Supplier');
                $('#supplierId').val(res.data.id);
                $('#kode_supplier').val(res.data.kode_supplier);
                $('#nama_supplier').val(res.data.nama_supplier);
                $('#alamat').val(res.data.alamat);
                $('#no_telp').val(res.data.no_telp);
                $('#email').val(res.data.email);
                $('#pic_name').val(res.data.pic_name);
                $('#pic_phone').val(res.data.pic_phone);
                $('#keterangan').val(res.data.keterangan);
                $('#status').val(res.data.status);
                
                $('#modalSupplier').modal('show');
            }
        }
    });
}

function deleteSupplier(id) {
    Swal.fire({
        title: 'Hapus Supplier?',
        text: 'Data supplier akan dihapus permanen!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '" . BASE_URL . "/modules/master/supplier_ajax.php',
                type: 'POST',
                data: { action: 'delete', id: id },
                success: function(response) {
                    let res = response;
                    if (res.success) {
                        table.ajax.reload();
                        Swal.fire('Terhapus!', res.message, 'success');
                    } else {
                        Swal.fire('Error!', res.message, 'error');
                    }
                }
            });
        }
    });
}

function exportExcel() {
    window.open('" . BASE_URL . "/modules/master/supplier_export.php', '_blank');
}
</script>
";

$extra_css = "
<link rel='stylesheet' href='https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css'>
";

require_once '../../includes/footer.php';
?>