<?php
$page_title = 'Manajemen Kategori';
require_once '../../includes/header.php';
require_role(['admin', 'koperasi', 'gudang']);

require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';
?>

<div class="row">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/dashboard/index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Master Kategori</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-tags me-2"></i>Data Kategori</h5>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalKategori" onclick="addKategori()">
                    <i class="fas fa-plus me-2"></i>Tambah Kategori
                </button>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <select class="form-select" id="filterJenis">
                            <option value="">Semua Jenis Barang</option>
                            <?php
                            $jenis = db_get_all("SELECT * FROM jenis_barang WHERE status = 'aktif' ORDER BY nama_jenis");
                            foreach ($jenis as $j) {
                                echo "<option value='{$j['id']}'>{$j['nama_jenis']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table id="tableKategori" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th width="5%">No</th>
                                <th>Kode</th>
                                <th>Nama Kategori</th>
                                <th>Jenis Barang</th>
                                <th>Parent Kategori</th>
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

<!-- Modal Kategori -->
<div class="modal fade" id="modalKategori" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formKategori">
                <input type="hidden" name="id" id="kategoriId">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalKategoriTitle">Tambah Kategori</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Kode Kategori <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="kode_kategori" id="kode_kategori" readonly required>
                                <small class="text-muted">Otomatis generate</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Jenis Barang <span class="text-danger">*</span></label>
                                <select class="form-select" name="jenis_barang_id" id="jenis_barang_id" required>
                                    <option value="">Pilih Jenis</option>
                                    <?php
                                    foreach ($jenis as $j) {
                                        echo "<option value='{$j['id']}'>{$j['nama_jenis']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_kategori" id="nama_kategori" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Parent Kategori</label>
                        <select class="form-select" name="parent_id" id="parent_id">
                            <option value="">Tidak Ada (Root)</option>
                        </select>
                        <small class="text-muted">Opsional, untuk membuat sub-kategori</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea class="form-control" name="deskripsi" id="deskripsi" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" id="status">
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Non-aktif</option>
                        </select>
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
    table = $('#tableKategori').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '" . BASE_URL . "/modules/master/kategori_ajax.php',
            type: 'POST',
            data: function(d) {
                d.jenis = $('#filterJenis').val();
            }
        },
        columns: [
            { data: 'no', orderable: false },
            { data: 'kode_kategori' },
            { data: 'nama_kategori' },
            { data: 'jenis' },
            { data: 'parent' },
            { data: 'status' },
            { data: 'aksi', orderable: false }
        ],
        order: [[1, 'asc']]
    });
    
    $('#filterJenis').on('change', function() {
        table.ajax.reload();
    });
    
    // Load parent kategori when jenis changes
    $('#jenis_barang_id').on('change', function() {
        let jenisId = $(this).val();
        
        if (jenisId) {
            // Generate code
            $.ajax({
                url: '" . BASE_URL . "/modules/master/kategori_ajax.php',
                type: 'POST',
                data: { action: 'generate_code', jenis_id: jenisId },
                success: function(response) {
                    let res = response;
                    $('#kode_kategori').val(res.code);
                }
            });
            
            // Load parent options
            $.ajax({
                url: '" . BASE_URL . "/modules/master/kategori_ajax.php',
                type: 'POST',
                data: { action: 'get_parents', jenis_id: jenisId },
                success: function(response) {
                    let res = response;
                    let options = '<option value=\"\">Tidak Ada (Root)</option>';
                    res.forEach(function(item) {
                        options += '<option value=\"' + item.id + '\">' + item.nama_kategori + '</option>';
                    });
                    $('#parent_id').html(options);
                }
            });
        }
    });
    
    $('#formKategori').on('submit', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: '" . BASE_URL . "/modules/master/kategori_ajax.php',
            type: 'POST',
            data: $(this).serialize() + '&action=save',
            success: function(response) {
                let res = response;
                if (res.success) {
                    $('#modalKategori').modal('hide');
                    table.ajax.reload();
                    Swal.fire('Berhasil!', res.message, 'success');
                } else {
                    Swal.fire('Error!', res.message, 'error');
                }
            }
        });
    });
});

function addKategori() {
    $('#modalKategoriTitle').text('Tambah Kategori');
    $('#formKategori')[0].reset();
    $('#kategoriId').val('');
    $('#parent_id').html('<option value=\"\">Tidak Ada (Root)</option>');
}

function editKategori(id) {
    $.ajax({
        url: '" . BASE_URL . "/modules/master/kategori_ajax.php',
        type: 'POST',
        data: { action: 'get', id: id },
        success: function(response) {
            let res = response;
            if (res.success) {
                $('#modalKategoriTitle').text('Edit Kategori');
                $('#kategoriId').val(res.data.id);
                $('#kode_kategori').val(res.data.kode_kategori);
                $('#nama_kategori').val(res.data.nama_kategori);
                $('#jenis_barang_id').val(res.data.jenis_barang_id).trigger('change');
                $('#deskripsi').val(res.data.deskripsi);
                $('#status').val(res.data.status);
                
                setTimeout(function() {
                    $('#parent_id').val(res.data.parent_id);
                }, 500);
                
                $('#modalKategori').modal('show');
            }
        }
    });
}

function deleteKategori(id) {
    Swal.fire({
        title: 'Hapus Kategori?',
        text: 'Data kategori akan dihapus permanen!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '" . BASE_URL . "/modules/master/kategori_ajax.php',
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
</script>
";

$extra_css = "
<link rel='stylesheet' href='https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css'>
";

require_once '../../includes/footer.php';
?>