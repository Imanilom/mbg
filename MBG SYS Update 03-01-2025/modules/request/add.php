<?php
// modules/request/add.php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();
checkRole(['kantor', 'admin']);

$user = getUserData();
$page_title = "Tambah Permintaan Barang";

// Generate nomor request otomatis
$no_request = generate_number('REQ', 'request', 'no_request');

$extra_css = '
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
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
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/dashboard/index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="list.php">Request Barang</a></li>
        <li class="breadcrumb-item active">Tambah</li>
    </ol>
</nav>

<form id="formRequest" method="POST">
    <div class="row">
        <!-- Header Card -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-800 text-dark">
                        <i class="fas fa-info-circle me-2 text-primary opacity-50"></i> Informasi Request
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">No Request</label>
                        <input type="text" class="form-control bg-light border-0 fw-bold" name="no_request" value="<?= $no_request ?>" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Tanggal Request <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="tanggal_request" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Kantor <span class="text-danger">*</span></label>
                        <select class="form-select" name="kantor_id" required <?= $user['role'] == 'kantor' ? 'readonly' : '' ?>>
                            <?php
                            if($user['role'] == 'kantor') {
                                $kantor_query = db_get_all("SELECT id, nama_kantor FROM kantor WHERE id = '{$user['kantor_id']}'");
                            } else {
                                $kantor_query = db_get_all("SELECT id, nama_kantor FROM kantor WHERE status='aktif' ORDER BY nama_kantor");
                            }
                            foreach($kantor_query as $k) {
                                $selected = ($user['role'] == 'kantor' && $k['id'] == $user['kantor_id']) ? 'selected' : '';
                                echo "<option value='{$k['id']}' {$selected}>{$k['nama_kantor']}</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Tanggal Dibutuhkan</label>
                        <input type="date" class="form-control" name="tanggal_butuh" min="<?= date('Y-m-d') ?>">
                    </div>

                    <div class="mb-0">
                        <label class="form-label small fw-bold text-muted">Keperluan <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="keperluan" rows="4" required placeholder="Tuliskan keperluan..."></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Items Card -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-800 text-dark">
                        <i class="fas fa-boxes me-2 text-primary opacity-50"></i> Item Barang
                    </h5>
                    <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" id="btnAddItem">
                        <i class="fas fa-plus me-1"></i> Tambah Baris
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="tableItems">
                            <thead class="bg-light">
                                <tr>
                                    <th width="5%" class="text-center py-3 border-0">No</th>
                                    <th width="40%" class="py-3 border-0">Produk</th>
                                    <th width="15%" class="text-center py-3 border-0">Qty</th>
                                    <th width="35%" class="py-3 border-0">Keterangan</th>
                                    <th width="5%" class="text-center py-3 border-0"></th>
                                </tr>
                            </thead>
                            <tbody id="itemsContainer">
                                <!-- Items will be added here -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white p-4 border-top">
                    <div class="d-flex gap-2 justify-content-end">
                        <a href="list.php" class="btn btn-outline-secondary px-4">Batal</a>
                        <button type="submit" class="btn btn-primary px-4" name="action" value="submit">
                            <i class="fas fa-paper-plane me-2"></i> Submit Request
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include '../../includes/footer.php'; ?>

<script>
let itemIndex = 0;

$(document).ready(function() {
    // Add first item
    addItem();

    // Add item button
    $('#btnAddItem').click(function() {
        addItem();
    });

    // Remove item
    $(document).on('click', '.btn-remove-item', function() {
        if($('#itemsContainer tr').length > 1) {
            $(this).closest('tr').remove();
            updateRowNumbers();
        } else {
            Swal.fire('Peringatan', 'Minimal harus ada 1 item', 'warning');
        }
    });

    // Change produk, update satuan
    $(document).on('change', '.select-produk', function() {
        const row = $(this).closest('tr');
        const produkId = $(this).val();
        
        if(produkId) {
            $.ajax({
                url: 'get_produk_info.php',
                type: 'POST',
                data: { produk_id: produkId },
                dataType: 'json',
                success: function(response) {
                    if(response.status === 'success') {
                        row.find('.satuan-text').text(response.data.satuan);
                    }
                }
            });
        } else {
            row.find('.satuan-text').text('-');
        }
    });

    // Form submit
    $('#formRequest').submit(function(e) {
        e.preventDefault();
        
        // Validasi minimal 1 item
        if($('#itemsContainer tr').length === 0) {
            Swal.fire('Peringatan', 'Minimal harus ada 1 item', 'warning');
            return false;
        }

        // Validasi setiap item harus terisi
        let valid = true;
        $('#itemsContainer tr').each(function() {
            const produk = $(this).find('.select-produk').val();
            const qty = $(this).find('.input-qty').val();
            
            if(!produk || !qty || qty <= 0) {
                valid = false;
                return false;
            }
        });

        if(!valid) {
            Swal.fire('Peringatan', 'Pastikan semua item terisi dengan benar', 'warning');
            return false;
        }

        // Submit form
        const formData = $(this).serialize();
        
        $.ajax({
            url: 'save.php',
            type: 'POST',
            data: formData,
            beforeSend: function() {
                Swal.fire({
                    title: 'Menyimpan...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
            },
            success: function(res) {
                if(res.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: res.message,
                        showConfirmButton: false,
                        timer: 1500
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
    
    const html = `
        <tr>
            <td class="text-center row-number">${itemIndex}</td>
            <td>
                <select class="form-control select-produk" name="items[${itemIndex}][produk_id]" required>
                    <option value="">Pilih Produk...</option>
                    <?php
                    $produk_query = mysqli_query($conn, "
                        SELECT p.id, p.nama_produk, p.kode_produk, jb.nama_jenis
                        FROM produk p
                        INNER JOIN jenis_barang jb ON p.jenis_barang_id = jb.id
                        WHERE p.status_produk = 'running'
                        ORDER BY jb.nama_jenis, p.nama_produk
                    ");
                    $current_jenis = '';
                    while($p = mysqli_fetch_assoc($produk_query)) {
                        if($current_jenis != $p['nama_jenis']) {
                            if($current_jenis != '') echo '</optgroup>';
                            echo '<optgroup label="' . $p['nama_jenis'] . '">';
                            $current_jenis = $p['nama_jenis'];
                        }
                        echo '<option value="' . $p['id'] . '">' . $p['kode_produk'] . ' - ' . $p['nama_produk'] . '</option>';
                    }
                    if($current_jenis != '') echo '</optgroup>';
                    ?>
                </select>
            </td>
            <td class="satuan-text text-center">-</td>
            <td>
                <input type="number" class="form-control input-qty" name="items[${itemIndex}][qty]" min="1" step="0.01" required>
            </td>
            <td>
                <input type="text" class="form-control" name="items[${itemIndex}][keterangan]" placeholder="Keterangan (opsional)">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-danger btn-sm btn-remove-item">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `;
    
    $('#itemsContainer').append(html);
    
    // Initialize select2 for new row
    $('#itemsContainer tr:last-child .select-produk').select2({
        placeholder: 'Pilih Produk...',
        allowClear: true,
        width: '100%',
        theme: 'bootstrap-5'
    });
}

function updateRowNumbers() {
    $('#itemsContainer tr').each(function(index) {
        $(this).find('.row-number').text(index + 1);
    });
}
</script>

