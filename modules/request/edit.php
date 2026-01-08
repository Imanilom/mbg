<?php
// modules/request/edit.php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();
checkRole(['kantor', 'admin']);

$user = getUserData();
$id = $_GET['id'] ?? 0;

if(!$id) {
    header('Location: list.php');
    exit;
}

// Get request data
$request = db_get_row("SELECT * FROM request WHERE id = " . db_escape($id));
if(!$request) {
    header('Location: list.php');
    exit;
}

// Cek status, hanya pending yang bisa edit
if($request['status'] != 'pending') {
    $_SESSION['flash_error'] = "Request dengan status {$request['status']} tidak dapat diedit.";
    header('Location: detail.php?id=' . $id);
    exit;
}

// Validasi kepemilikan
if($user['role'] == 'kantor' && $request['kantor_id'] != $user['kantor_id']) {
    header('Location: list.php');
    exit;
}

$page_title = "Edit Permintaan Barang";

$extra_css = '
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<style>
    .card-header-premium {
        background: linear-gradient(to right, rgba(96, 165, 250, 0.1), rgba(255, 255, 255, 0));
        border-bottom: 1px solid var(--border-lighter);
        padding: 1.5rem;
    }
    .form-group-icon {
        position: relative;
    }
    .form-group-icon i {
        position: absolute;
        top: 50%;
        left: 1rem;
        transform: translateY(-50%);
        color: var(--text-muted);
        z-index: 4;
    }
    .form-group-icon .form-control, .form-group-icon .form-select {
        padding-left: 2.5rem;
    }
    .table-custom th {
        background-color: var(--surface-elevated);
        color: var(--text-secondary);
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        border-bottom: 2px solid var(--border-light);
    }
</style>
';

$extra_js = '
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
';

include '../../includes/header.php';
include '../../includes/navbar.php';
include '../../includes/sidebar.php';
?>

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb border-0 shadow-sm bg-white">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/dashboard/index.php"><i class="fas fa-home"></i> Dashboard</a></li>
        <li class="breadcrumb-item"><a href="list.php">Request Barang</a></li>
        <li class="breadcrumb-item active">Edit</li>
    </ol>
</nav>

<form id="formEditRequest" method="POST">
    <input type="hidden" name="id" value="<?= $id ?>">
    <div class="row g-4">
        <!-- Header Card -->
        <div class="col-lg-4">
            <div class="card shadow-premium border-0 h-100">
                <div class="card-header-premium">
                    <h5 class="mb-0 fw-bold text-dark d-flex align-items-center">
                        <span class="icon-wrapper bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="fas fa-info-circle fa-lg"></i>
                        </span>
                        Informasi Request
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-uppercase text-muted ls-1">No Request</label>
                        <div class="form-group-icon">
                            <i class="fas fa-hashtag"></i>
                            <input type="text" class="form-control bg-light fw-bold text-dark" value="<?= $request['no_request'] ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-uppercase text-muted ls-1">Tanggal Request <span class="text-danger">*</span></label>
                        <div class="form-group-icon">
                            <input type="date" class="form-control ps-3" name="tanggal_request" value="<?= $request['tanggal_request'] ?>" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-uppercase text-muted ls-1">Kantor <span class="text-danger">*</span></label>
                        <div class="form-group-icon">
                            <i class="fas fa-building"></i>
                            <select class="form-select" name="kantor_id" required <?= $user['role'] == 'kantor' ? 'readonly' : '' ?>>
                                <?php
                                if($user['role'] == 'kantor') {
                                    $kantor_query = db_get_all("SELECT id, nama_kantor FROM kantor WHERE id = '{$user['kantor_id']}'");
                                } else {
                                    $kantor_query = db_get_all("SELECT id, nama_kantor FROM kantor WHERE status='aktif' ORDER BY nama_kantor");
                                }
                                foreach($kantor_query as $k) {
                                    $selected = ($k['id'] == $request['kantor_id']) ? 'selected' : '';
                                    echo "<option value='{$k['id']}' {$selected}>{$k['nama_kantor']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-uppercase text-muted ls-1">Tanggal Dibutuhkan</label>
                        <div class="form-group-icon">
                            <input type="date" class="form-control ps-3" name="tanggal_butuh" value="<?= $request['tanggal_butuh'] ?>" min="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label small fw-bold text-uppercase text-muted ls-1">Keperluan <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="keperluan" rows="4" required placeholder="Jelaskan kebutuhan barang ini..."><?= htmlspecialchars($request['keperluan']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Items Card -->
        <div class="col-lg-8">
            <div class="card shadow-premium border-0">
                <div class="card-header-premium d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-dark d-flex align-items-center">
                        <span class="icon-wrapper bg-success bg-opacity-10 text-success rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="fas fa-boxes fa-lg"></i>
                        </span>
                        Item Barang
                    </h5>
                    <button type="button" class="btn btn-primary btn-sm rounded-pill px-4" id="btnAddItem">
                        <i class="fas fa-plus me-2"></i> Tambah Baris
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle table-custom" id="tableItems">
                            <thead>
                                <tr>
                                    <th width="5%" class="text-center py-3 ps-4">No</th>
                                    <th width="40%" class="py-3">Produk</th>
                                    <th width="15%" class="text-center py-3">Qty</th>
                                    <th width="35%" class="py-3">Keterangan</th>
                                    <th width="5%" class="text-center py-3 pe-4"></th>
                                </tr>
                            </thead>
                            <tbody id="itemsContainer">
                                <?php
                                $details = db_get_all("SELECT rd.*, p.nama_produk, p.kode_produk, p.satuan 
                                                      FROM request_detail rd 
                                                      JOIN produk p ON rd.produk_id = p.id 
                                                      WHERE rd.request_id = " . db_escape($id));
                                $idx = 0;
                                foreach($details as $item):
                                    $idx++;
                                ?>
                                <tr>
                                    <td class="text-center row-number fw-bold text-muted ps-4"><?= $idx ?></td>
                                    <td>
                                        <select class="form-control select-produk" name="items[<?= $idx ?>][produk_id]" required>
                                            <option value="">Pilih Produk...</option>
                                            <?php
                                            $produk_query = mysqli_query($conn, "SELECT p.id, p.nama_produk, p.kode_produk, jb.nama_jenis FROM produk p INNER JOIN jenis_barang jb ON p.jenis_barang_id = jb.id WHERE p.status_produk = 'running' ORDER BY jb.nama_jenis, p.nama_produk");
                                            $current_jenis = '';
                                            while($p = mysqli_fetch_assoc($produk_query)) {
                                                if($current_jenis != $p['nama_jenis']) {
                                                    if($current_jenis != '') echo '</optgroup>';
                                                    echo '<optgroup label="' . addslashes($p['nama_jenis']) . '">';
                                                    $current_jenis = $p['nama_jenis'];
                                                }
                                                $selected = ($p['id'] == $item['produk_id']) ? 'selected' : '';
                                                echo '<option value="' . $p['id'] . '" ' . $selected . '>' . addslashes($p['kode_produk']) . ' - ' . addslashes($p['nama_produk']) . '</option>';
                                            }
                                            if($current_jenis != '') echo '</optgroup>';
                                            ?>
                                        </select>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border satuan-text px-2 py-1"><?= $item['satuan'] ?></span>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control input-qty text-center" name="items[<?= $idx ?>][qty]" value="<?= $item['qty_request'] ?>" min="1" step="0.01" required>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" name="items[<?= $idx ?>][keterangan]" value="<?= htmlspecialchars($item['keterangan']) ?>" placeholder="Keterangan item...">
                                    </td>
                                    <td class="text-center pe-4">
                                        <button type="button" class="btn btn-outline-danger btn-sm btn-remove-item rounded-circle p-2">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white p-4 border-top">
                    <div class="d-flex gap-3 justify-content-end">
                        <a href="detail.php?id=<?= $id ?>" class="btn btn-light text-muted fw-bold px-4 border">
                            <i class="fas fa-times me-2"></i> Batal
                        </a>
                        <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm">
                            <i class="fas fa-save me-2"></i> Simpan Perubahan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include '../../includes/footer.php'; ?>

<script>
let itemIndex = <?= $idx ?>;

$(document).ready(function() {
    // Initialize Select2 for existing rows
    $('.select-produk').select2({
        theme: 'bootstrap-5',
        width: '100%'
    });

    $('#btnAddItem').click(function() {
        addItem();
    });

    $(document).on('click', '.btn-remove-item', function() {
        if($('#itemsContainer tr').length > 1) {
            $(this).closest('tr').remove();
            updateRowNumbers();
        } else {
            Swal.fire('Peringatan', 'Minimal harus ada 1 item', 'warning');
        }
    });

    $(document).on('change', '.select-produk', function() {
        const row = $(this).closest('tr');
        const produkId = $(this).val();
        if(produkId) {
            $.ajax({
                url: 'get_produk_info.php',
                type: 'POST',
                data: { produk_id: produkId },
                dataType: 'json',
                success: function(res) {
                    if(res.status === 'success') {
                        row.find('.satuan-text').text(res.data.satuan);
                        row.find('.input-qty').focus();
                    }
                }
            });
        }
    });

    $('#formEditRequest').submit(function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        
        $.ajax({
            url: 'update.php',
            type: 'POST',
            data: formData,
            beforeSend: function() {
                Swal.fire({
                    title: 'Memperbarui...',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });
            },
            success: function(res) {
                if(typeof res === 'string') {
                    try { res = JSON.parse(res); } catch(e) {}
                }
                
                if(res.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: res.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = 'detail.php?id=' + res.request_id;
                    });
                } else {
                    Swal.fire('Gagal!', res.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error!', 'Terjadi kesalahan sistem', 'error');
            }
        });
    });
});

function addItem() {
    itemIndex++;
    
    // Identical options logic as in add.php (but we could optimize this later)
    const options = `<?php
    $produk_query = mysqli_query($conn, "SELECT p.id, p.nama_produk, p.kode_produk, jb.nama_jenis FROM produk p INNER JOIN jenis_barang jb ON p.jenis_barang_id = jb.id WHERE p.status_produk = 'running' ORDER BY jb.nama_jenis, p.nama_produk");
    $current_jenis = '';
    while($p = mysqli_fetch_assoc($produk_query)) {
        if($current_jenis != $p['nama_jenis']) {
            if($current_jenis != '') echo '</optgroup>';
            echo '<optgroup label="' . addslashes($p['nama_jenis']) . '">';
            $current_jenis = $p['nama_jenis'];
        }
        echo '<option value="' . $p['id'] . '">' . addslashes($p['kode_produk']) . ' - ' . addslashes($p['nama_produk']) . '</option>';
    }
    if($current_jenis != '') echo '</optgroup>';
    ?>`;

    const html = `
        <tr>
            <td class="text-center row-number fw-bold text-muted ps-4">${$('#itemsContainer tr').length + 1}</td>
            <td>
                <select class="form-control select-produk" name="items[${itemIndex}][produk_id]" required>
                    <option value="">Pilih Produk...</option>
                    ${options}
                </select>
            </td>
            <td class="text-center">
                <span class="badge bg-light text-dark border satuan-text px-2 py-1">-</span>
            </td>
            <td>
                <input type="number" class="form-control input-qty text-center" name="items[${itemIndex}][qty]" min="1" step="0.01" required placeholder="0">
            </td>
            <td>
                <input type="text" class="form-control" name="items[${itemIndex}][keterangan]" placeholder="Keterangan item...">
            </td>
            <td class="text-center pe-4">
                <button type="button" class="btn btn-outline-danger btn-sm btn-remove-item rounded-circle p-2">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </td>
        </tr>
    `;
    
    $('#itemsContainer').append(html);
    $('#itemsContainer tr:last-child .select-produk').select2({
        theme: 'bootstrap-5',
        width: '100%',
        dropdownParent: $('#itemsContainer tr:last-child td:nth-child(2)')
    });
}

function updateRowNumbers() {
    $('#itemsContainer tr').each(function(index) {
        $(this).find('.row-number').text(index + 1);
    });
}
</script>
