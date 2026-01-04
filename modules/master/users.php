<?php
$page_title = 'Manajemen User';
require_once '../../includes/header.php';
require_role(['admin']);

require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';
?>

<div class="row">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/dashboard/index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Master User</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-users me-2"></i>Data User</h5>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalUser" onclick="addUser()">
                    <i class="fas fa-plus me-2"></i>Tambah User
                </button>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <select class="form-select" id="filterRole">
                            <option value="">Semua Role</option>
                            <option value="admin">Administrator</option>
                            <option value="koperasi">Staff Koperasi</option>
                            <option value="gudang">Staff Gudang</option>
                            <option value="kantor">Staff Kantor</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="filterStatus">
                            <option value="">Semua Status</option>
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Non-aktif</option>
                        </select>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table id="tableUsers" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th width="5%">No</th>
                                <th>Username</th>
                                <th>Nama Lengkap</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Kantor</th>
                                <th>Status</th>
                                <th width="15%">Aksi</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal User -->
<div class="modal fade" id="modalUser" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formUser" enctype="multipart/form-data">
                <input type="hidden" name="id" id="userId">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalUserTitle">Tambah User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="username" id="username" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Password <span class="text-danger" id="passRequired">*</span></label>
                                <input type="password" class="form-control" name="password" id="password">
                                <small class="text-muted">Kosongkan jika tidak ingin mengubah password</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_lengkap" id="nama_lengkap" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" id="email">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">No HP</label>
                                <input type="text" class="form-control format-number" name="no_hp" id="no_hp">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Role <span class="text-danger">*</span></label>
                                <select class="form-select" name="role" id="role" required>
                                    <option value="">Pilih Role</option>
                                    <option value="admin">Administrator</option>
                                    <option value="koperasi">Staff Koperasi</option>
                                    <option value="gudang">Staff Gudang</option>
                                    <option value="kantor">Staff Kantor</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6" id="divKantor" style="display:none;">
                            <div class="mb-3">
                                <label class="form-label">Kantor <span class="text-danger">*</span></label>
                                <select class="form-select" name="kantor_id" id="kantor_id">
                                    <option value="">Pilih Kantor</option>
                                    <?php
                                    $kantors = db_get_all("SELECT id, nama_kantor FROM kantor WHERE status = 'aktif' ORDER BY nama_kantor");
                                    foreach ($kantors as $k) {
                                        echo "<option value='{$k['id']}'>{$k['nama_kantor']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" id="status">
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Non-aktif</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Foto Profile</label>
                        <input type="file" class="form-control" name="foto" id="foto" accept="image/*">
                        <div id="previewFoto" class="mt-2"></div>
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
    // Initialize DataTable
    table = $('#tableUsers').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '" . BASE_URL . "/modules/master/users_ajax.php',
            type: 'POST',
            data: function(d) {
                d.role = $('#filterRole').val();
                d.status = $('#filterStatus').val();
            }
        },
        columns: [
            { data: 'no', orderable: false },
            { data: 'username' },
            { data: 'nama_lengkap' },
            { data: 'email' },
            { data: 'role' },
            { data: 'kantor' },
            { data: 'status' },
            { data: 'aksi', orderable: false }
        ],
        order: [[1, 'asc']]
    });
    
    // Filter
    $('#filterRole, #filterStatus').on('change', function() {
        table.ajax.reload();
    });
    
    // Role change - show/hide kantor
    $('#role').on('change', function() {
        if ($(this).val() == 'kantor') {
            $('#divKantor').show();
            $('#kantor_id').prop('required', true);
        } else {
            $('#divKantor').hide();
            $('#kantor_id').prop('required', false);
        }
    });
    
    // Preview foto
    $('#foto').on('change', function() {
        let file = this.files[0];
        if (file) {
            let reader = new FileReader();
            reader.onload = function(e) {
                $('#previewFoto').html('<img src=\"' + e.target.result + '\" class=\"img-thumbnail\" width=\"150\">');
            }
            reader.readAsDataURL(file);
        }
    });
    
    // Submit form
    $('#formUser').on('submit', function(e) {
        e.preventDefault();
        
        let formData = new FormData(this);
        formData.append('action', 'save');
        
        $.ajax({
            url: '" . BASE_URL . "/modules/master/users_ajax.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                let res = response;
                if (res.success) {
                    $('#modalUser').modal('hide');
                    table.ajax.reload();
                    Swal.fire('Berhasil!', res.message, 'success');
                } else {
                    Swal.fire('Error!', res.message, 'error');
                }
            }
        });
    });
});

function addUser() {
    $('#modalUserTitle').text('Tambah User');
    $('#formUser')[0].reset();
    $('#userId').val('');
    $('#previewFoto').html('');
    $('#passRequired').show();
    $('#password').prop('required', true);
    $('#divKantor').hide();
}

function editUser(id) {
    $.ajax({
        url: '" . BASE_URL . "/modules/master/users_ajax.php',
        type: 'POST',
        data: { action: 'get', id: id },
        success: function(response) {
            let res = response;
            if (res.success) {
                $('#modalUserTitle').text('Edit User');
                $('#userId').val(res.data.id);
                $('#username').val(res.data.username);
                $('#nama_lengkap').val(res.data.nama_lengkap);
                $('#email').val(res.data.email);
                $('#no_hp').val(res.data.no_hp);
                $('#role').val(res.data.role).trigger('change');
                $('#kantor_id').val(res.data.kantor_id);
                $('#status').val(res.data.status);
                
                $('#passRequired').hide();
                $('#password').prop('required', false);
                
                if (res.data.foto) {
                    $('#previewFoto').html('<img src=\"" . BASE_URL . "/assets/uploads/' + res.data.foto + '\" class=\"img-thumbnail\" width=\"150\">');
                }
                
                $('#modalUser').modal('show');
            }
        }
    });
}

function deleteUser(id) {
    Swal.fire({
        title: 'Hapus User?',
        text: 'Data user akan dihapus permanen!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '" . BASE_URL . "/modules/master/users_ajax.php',
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

function toggleStatus(id, status) {
    let newStatus = status == 'aktif' ? 'nonaktif' : 'aktif';
    
    $.ajax({
        url: '" . BASE_URL . "/modules/master/users_ajax.php',
        type: 'POST',
        data: { action: 'toggle_status', id: id, status: newStatus },
        success: function(response) {
            let res = response;
            if (res.success) {
                table.ajax.reload();
                Swal.fire('Berhasil!', res.message, 'success');
            }
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