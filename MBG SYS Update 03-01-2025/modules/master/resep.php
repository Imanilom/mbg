<?php
$page_title = 'Manajemen Resep';
require_once '../../includes/header.php';
require_role(['admin', 'koperasi', 'gudang']);

require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';

// Get products for ingredient selection
$products = db_get_all("SELECT p.*, s.nama_satuan FROM produk p INNER JOIN satuan s ON p.satuan_id = s.id WHERE p.status_produk = 'running' ORDER BY p.nama_produk");
?>

<div class="row">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/dashboard/index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Master Resep</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-utensils me-2"></i>Data Resep</h5>
                <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalResep" onclick="addResep()">
                    <i class="fas fa-plus me-2"></i>Tambah Resep
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tableResep" class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="5%">No</th>
                                <th>Kode Resep</th>
                                <th>Nama Resep</th>
                                <th>Porsi Standar</th>
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

<!-- Modal Resep -->
<div class="modal fade" id="modalResep" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <form id="formResep">
                <input type="hidden" name="id" id="resepId">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalResepTitle">Tambah Resep</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Kode Resep</label>
                            <input type="text" class="form-control bg-light" name="kode_resep" id="kode_resep" readonly placeholder="Otomatis">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Nama Resep <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama_resep" id="nama_resep" required placeholder="Contoh: Nasi Goreng Spesial">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Porsi Standar <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="porsi_standar" id="porsi_standar" value="1" min="1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Status</label>
                            <select class="form-select" name="status" id="status">
                                <option value="aktif">Aktif</option>
                                <option value="nonaktif">Non-aktif</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Deskripsi</label>
                            <textarea class="form-control" name="deskripsi" id="deskripsi" rows="2"></textarea>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0"><i class="fas fa-list me-2"></i>Bahan-bahan (Gramasi per Porsi Standar)</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addIngredientRow()">
                            <i class="fas fa-plus me-1"></i>Tambah Bahan
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th width="40%">Produk</th>
                                    <th width="15%">Qty per Porsi</th>
                                    <th width="10%">Satuan</th>
                                    <th>Keterangan</th>
                                    <th width="5%"></th>
                                </tr>
                            </thead>
                            <tbody id="ingredientList">
                                <!-- Row template will be added here -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fas fa-save me-2"></i>Simpan Resep
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
let ingredientIndex = 0;
const products = " . json_encode($products) . ";

$(document).ready(function() {
    table = $('#tableResep').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '" . BASE_URL . "/modules/master/resep_ajax.php',
            type: 'POST'
        },
        columns: [
            { data: 'no' },
            { data: 'kode_resep' },
            { data: 'nama_resep' },
            { data: 'porsi_standar' },
            { data: 'status' },
            { data: 'aksi', orderable: false }
        ]
    });

    $('#formResep').on('submit', function(e) {
        e.preventDefault();
        let formData = new FormData(this);
        formData.append('action', 'save');
        
        $.ajax({
            url: '" . BASE_URL . "/modules/master/resep_ajax.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                let res = JSON.parse(response);
                if (res.success) {
                    $('#modalResep').modal('hide');
                    table.ajax.reload();
                    Swal.fire('Berhasil!', res.message, 'success');
                } else {
                    Swal.fire('Error!', res.message, 'error');
                }
            }
        });
    });
});

function addResep() {
    $('#modalResepTitle').text('Tambah Resep');
    $('#formResep')[0].reset();
    $('#resepId').val('');
    $('#ingredientList').empty();
    ingredientIndex = 0;
    
    // Auto generate code
    $.get('" . BASE_URL . "/modules/master/resep_ajax.php?action=generate_code', function(res) {
        let data = JSON.parse(res);
        $('#kode_resep').val(data.code);
    });

    // Add one empty row
    addIngredientRow();
}

function addIngredientRow(data = null) {
    let options = '<option value=\"\">Pilih Produk</option>';
    products.forEach(p => {
        let selected = data && data.produk_id == p.id ? 'selected' : '';
        options += `<option value=\"${p.id}\" data-satuan=\"${p.nama_satuan}\" ${selected}>${p.nama_produk}</option>`;
    });

    let row = `
        <tr id=\"row_${ingredientIndex}\">
            <td>
                <select name=\"items[${ingredientIndex}][produk_id]\" class=\"form-select form-select-sm select-product\" required onchange=\"updateSatuan(${ingredientIndex})\">
                    ${options}
                </select>
            </td>
            <td>
                <input type=\"number\" name=\"items[${ingredientIndex}][gramasi]\" class=\"form-control form-control-sm text-end\" value=\"${data ? data.gramasi : '0'}\" step=\"0.0001\" min=\"0.0001\" required>
            </td>
            <td>
                <span class=\"satuan-label\">${data ? data.nama_satuan : '-'}</span>
            </td>
            <td>
                <input type=\"text\" name=\"items[${ingredientIndex}][keterangan]\" class=\"form-control form-control-sm\" value=\"${data ? data.keterangan || '' : ''}\" placeholder=\"Opsional\">
            </td>
            <td class=\"text-center\">
                <button type=\"button\" class=\"btn btn-sm btn-link text-danger p-0\" onclick=\"removeRow(${ingredientIndex})\">
                    <i class=\"fas fa-times-circle\"></i>
                </button>
            </td>
        </tr>
    `;
    $('#ingredientList').append(row);
    ingredientIndex++;
}

function removeRow(index) {
    if ($('#ingredientList tr').length > 1) {
        $(`#row_${index}`).remove();
    }
}

function updateSatuan(index) {
    let select = $(`#row_${index} .select-product`);
    let satuan = select.find(':selected').data('satuan') || '-';
    $(`#row_${index} .satuan-label`).text(satuan);
}

function editResep(id) {
    $.post('" . BASE_URL . "/modules/master/resep_ajax.php', { action: 'get', id: id }, function(response) {
        let res = JSON.parse(response);
        if (res.success) {
            let data = res.data;
            $('#modalResepTitle').text('Edit Resep');
            $('#resepId').val(data.id);
            $('#kode_resep').val(data.kode_resep);
            $('#nama_resep').val(data.nama_resep);
            $('#porsi_standar').val(data.porsi_standar);
            $('#status').val(data.status);
            $('#deskripsi').val(data.deskripsi);
            
            $('#ingredientList').empty();
            ingredientIndex = 0;
            if (data.details && data.details.length > 0) {
                data.details.forEach(detail => {
                    addIngredientRow(detail);
                });
            } else {
                addIngredientRow();
            }
            
            $('#modalResep').modal('show');
        }
    });
}

function deleteResep(id) {
    Swal.fire({
        title: 'Hapus Resep?',
        text: 'Data resep akan dihapus permanen!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('" . BASE_URL . "/modules/master/resep_ajax.php', { action: 'delete', id: id }, function(response) {
                let res = JSON.parse(response);
                if (res.success) {
                    table.ajax.reload();
                    Swal.fire('Terhapus!', res.message, 'success');
                } else {
                    Swal.fire('Error!', res.message, 'error');
                }
            });
        }
    });
}
</script>
";

$extra_css = "
<link rel='stylesheet' href='https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css'>
<style>
    .select-product { width: 100% !important; }
    .modal-xl { max-width: 1000px; }
</style>
";

require_once '../../includes/footer.php';
?>
