<?php
// modules/distribusi/add.php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();
checkRole(['koperasi', 'admin']);

$user = getUserData();
$page_title = "Buat Distribusi";

// Generate nomor surat jalan otomatis
$no_surat_jalan = generate_number('DST', 'distribusi', 'no_surat_jalan');

$extra_css = '
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
';

$extra_js = '
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
';

// Get request_id if from request
$request_id = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;
$request_data = null;

if($request_id > 0) {
    // Load request data
    $query_request = "SELECT r.*, k.nama_kantor 
                      FROM request r
                      INNER JOIN kantor k ON r.kantor_id = k.id
                      WHERE r.id = '$request_id' AND r.status = 'diproses'";
    
    $result_request = mysqli_query($conn, $query_request);
    
    if(mysqli_num_rows($result_request) > 0) {
        $request_data = mysqli_fetch_assoc($result_request);
        
        // Get approved items dengan stok check
        $query_items = "SELECT rd.*, p.id as produk_id, p.kode_produk, p.nama_produk, s.nama_satuan,
                        (SELECT COALESCE(SUM(qty_available), 0) FROM gudang_stok WHERE produk_id = p.id AND kondisi = 'baik') as stok_tersedia
                        FROM request_detail rd
                        INNER JOIN produk p ON rd.produk_id = p.id
                        INNER JOIN satuan s ON p.satuan_id = s.id
                        WHERE rd.request_id = '$request_id' AND rd.qty_approved > 0
                        ORDER BY p.nama_produk";
        
        $result_items = mysqli_query($conn, $query_items);
    }
}

include '../../includes/header.php';
include '../../includes/navbar.php';
include '../../includes/sidebar.php';
?>

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/dashboard/index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="list.php">Distribusi Barang</a></li>
        <li class="breadcrumb-item active">Tambah</li>
    </ol>
</nav>

<form id="formDistribusi" method="POST">
    <div class="row">
        <!-- Header Card -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-800 text-dark">
                        <i class="fas fa-info-circle me-2 text-primary opacity-50"></i> Informasi Distribusi
                    </h5>
                </div>
                <div class="card-body p-4">
                    <?php if($request_data): ?>
                    <div class="alert alert-info border-0 shadow-none bg-light p-3 mb-4 rounded-3 text-dark small">
                        <div class="d-flex align-items-center mb-1">
                            <i class="fas fa-link me-2 text-primary"></i>
                            <span class="fw-bold">Terkait Request:</span>
                        </div>
                        <div class="ps-4">
                            <div class="mb-1"><?= $request_data['no_request'] ?></div>
                            <div class="fw-bold text-muted"><?= $request_data['nama_kantor'] ?></div>
                        </div>
                    </div>
                    <input type="hidden" name="request_id" value="<?= $request_id ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">No Surat Jalan</label>
                        <input type="text" class="form-control bg-light border-0 fw-bold" name="no_surat_jalan" value="<?= $no_surat_jalan ?>" readonly>
                        <div class="form-text x-small">QR Code akan di-generate otomatis</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Tanggal Kirim <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="tanggal_kirim" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Kantor Tujuan <span class="text-danger">*</span></label>
                        <select class="form-select select2" name="kantor_id" id="kantor_id" required <?= $request_data ? 'readonly' : '' ?>>
                            <option value="">Pilih Kantor...</option>
                            <?php
                            $kantor_query = mysqli_query($conn, "SELECT id, kode_kantor, nama_kantor FROM kantor WHERE status='aktif' ORDER BY nama_kantor");
                            while($k = mysqli_fetch_assoc($kantor_query)) {
                                $selected = ($request_data && $k['id'] == $request_data['kantor_id']) ? 'selected' : '';
                                echo "<option value='{$k['id']}' {$selected}>{$k['kode_kantor']} - {$k['nama_kantor']}</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Pengirim</label>
                        <input type="text" class="form-control bg-light border-0" value="<?= $user['nama_lengkap'] ?>" readonly>
                        <input type="hidden" name="pengirim_id" value="<?= $user['id'] ?>">
                    </div>

                    <div class="mb-0">
                        <label class="form-label small fw-bold text-muted">Keterangan</label>
                        <textarea class="form-control" name="keterangan" rows="3" placeholder="Keterangan tambahan (opsional)"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Items Card -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-800 text-dark">
                        <i class="fas fa-boxes me-2 text-primary opacity-50"></i> Item Barang yang Dikirim
                    </h5>
                    <?php if(!$request_data): ?>
                    <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" id="btnAddItem">
                        <i class="fas fa-plus me-1"></i> Tambah Item
                    </button>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="tableItems">
                            <thead class="bg-light">
                                <tr>
                                    <th width="5%" class="text-center py-3 border-0">No</th>
                                    <th width="35%" class="py-3 border-0">Produk</th>
                                    <th width="10%" class="text-center py-3 border-0">Satuan</th>
                                    <?php if($request_data): ?>
                                    <th width="10%" class="text-center py-3 border-0">Req</th>
                                    <?php endif; ?>
                                    <th width="15%" class="text-center py-3 border-0">Stok</th>
                                    <th width="15%" class="py-3 border-0">Qty Kirim</th>
                                    <th width="10%" class="text-center py-3 border-0"></th>
                                </tr>
                            </thead>
                            <tbody id="itemsContainer">
                                <?php if($request_data && isset($result_items)): 
                                    $no = 1;
                                    while($item = mysqli_fetch_assoc($result_items)):
                                        $stok_cukup = $item['stok_tersedia'] >= $item['qty_approved'];
                                        $max_qty = min($item['qty_approved'], $item['stok_tersedia']);
                                ?>
                                <tr data-produk-id="<?= $item['produk_id'] ?>">
                                    <td class="text-center align-middle"><?= $no++ ?></td>
                                    <td class="align-middle">
                                        <div class="fw-bold text-dark mb-0"><?= $item['nama_produk'] ?></div>
                                        <div class="small text-muted"><?= $item['kode_produk'] ?></div>
                                        <input type="hidden" name="items[<?= $item['produk_id'] ?>][produk_id]" value="<?= $item['produk_id'] ?>">
                                        <input type="hidden" name="items[<?= $item['produk_id'] ?>][qty_request]" value="<?= $item['qty_approved'] ?>">
                                    </td>
                                    <td class="text-center align-middle"><?= $item['nama_satuan'] ?></td>
                                    <?php if($request_data): ?>
                                    <td class="text-center align-middle fw-bold"><?= number_format($item['qty_approved'], 2) ?></td>
                                    <?php endif; ?>
                                    <td class="text-center align-middle">
                                        <div class="fw-bold <?= $stok_cukup ? 'text-success' : 'text-danger' ?>">
                                            <?= number_format($item['stok_tersedia'], 2) ?>
                                        </div>
                                        <?php if(!$stok_cukup): ?>
                                        <span class="badge bg-danger x-small">Kurang!</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="align-middle">
                                        <input type="number" class="form-control form-control-sm input-qty-kirim" 
                                               name="items[<?= $item['produk_id'] ?>][qty_kirim]" 
                                               value="<?= $max_qty ?>" 
                                               max="<?= $max_qty ?>" 
                                               min="0" 
                                               step="0.01" 
                                               required>
                                    </td>
                                    <td class="text-center align-middle">
                                        <button type="button" class="btn btn-link text-muted p-0" data-bs-toggle="collapse" data-bs-target="#ket-<?= $item['produk_id'] ?>">
                                            <i class="fas fa-comment-dots"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr class="collapse" id="ket-<?= $item['produk_id'] ?>">
                                    <td colspan="<?= $request_data ? 7 : 6 ?>" class="bg-light border-0 py-2 px-4 shadow-inner">
                                        <input type="text" class="form-control form-control-sm bg-white" name="items[<?= $item['produk_id'] ?>][keterangan]" placeholder="Tambahkan keterangan untuk item ini...">
                                    </td>
                                </tr>
                                <?php 
                                    endwhile;
                                endif; 
                                ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if(!$request_data): ?>
                    <div class="p-4 text-center border-top bg-light">
                        <div class="d-inline-block px-3 py-2 bg-white rounded-pill shadow-sm small text-muted">
                            <i class="fas fa-info-circle me-1 text-primary"></i> 
                            Distribusi manual tanpa request. Tambahkan item menggunakan tombol di atas.
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-white p-4 border-top">
                    <div class="d-flex gap-2 justify-content-end">
                        <a href="list.php" class="btn btn-outline-secondary px-4">Batal</a>
                        <button type="submit" class="btn btn-primary px-4 shadow-sm" id="btnSubmit">
                            <i class="fas fa-paper-plane me-2"></i> Buat Distribusi & QR Code
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
    // Real-time stok check when produk changed
    $(document).on('change', '.select-produk', function() {
        const row = $(this).closest('tr');
        const produkId = $(this).val();
        
        if(produkId) {
            $.ajax({
                url: '../request/get_produk_info.php',
                type: 'POST',
                data: { produk_id: produkId },
                dataType: 'json',
                success: function(response) {
                    if(response.status === 'success') {
                        row.find('.satuan-text').text(response.data.satuan);
                        row.find('.stok-info').html(
                            parseFloat(response.data.stok).toFixed(2) + 
                            (response.data.stok > 0 ? ' <span class="badge badge-success">Tersedia</span>' : ' <span class="badge badge-danger">Habis</span>')
                        );
                        row.find('.input-qty-kirim').attr('max', response.data.stok);
                    }
                }
            });
        }
    });

    // Validate qty_kirim tidak melebihi stok
    $(document).on('input', '.input-qty-kirim', function() {
        const max = parseFloat($(this).attr('max'));
        const val = parseFloat($(this).val());
        
        if(val > max) {
            $(this).val(max);
            Swal.fire('Peringatan', 'Qty kirim tidak boleh melebihi stok tersedia', 'warning');
        }
    });

    // Add item for manual distribution
    $('#btnAddItem').click(function() {
        addItem();
    });

    // Remove item
    $(document).on('click', '.btn-remove-item', function() {
        if($('#itemsContainer tr').not('.collapse').length > 1) {
            const row = $(this).closest('tr');
            // Find target collapse
            const target = row.find('[data-bs-toggle="collapse"]').attr('data-bs-target');
            if(target) $(target).remove();
            
            row.remove();
            updateRowNumbers();
        } else {
            Swal.fire('Peringatan', 'Minimal harus ada 1 item', 'warning');
        }
    });

    // Form submit
    $('#formDistribusi').submit(function(e) {
        e.preventDefault();
        
        // Validasi minimal 1 item
        if($('#itemsContainer tr').length === 0) {
            Swal.fire('Peringatan', 'Minimal harus ada 1 item', 'warning');
            return false;
        }

        // Validasi qty kirim
        let valid = true;
        let errorMsg = '';
        
        $('#itemsContainer tr').not('.collapse').each(function() {
            const input = $(this).find('.input-qty-kirim');
            // Safety check if input exists
            if(input.length === 0) return true; 

            const qty_kirim = parseFloat(input.val());
            const maxAttr = input.attr('max');
            const max = parseFloat(maxAttr);
            
            if(isNaN(qty_kirim) || qty_kirim <= 0) {
                valid = false;
                errorMsg = 'Qty kirim harus lebih dari 0';
                return false;
            }
            
            if(!isNaN(max) && qty_kirim > max) {
                valid = false;
                errorMsg = 'Qty kirim ('+qty_kirim+') melebihi stok tersedia ('+max+')';
                return false;
            }
        });

        if(!valid) {
            Swal.fire('Peringatan', errorMsg, 'warning');
            return false;
        }

        // Konfirmasi
        Swal.fire({
            title: 'Buat Distribusi?',
            text: 'QR Code akan di-generate otomatis',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Buat!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if(result.isConfirmed) {
                submitForm();
            }
        });
    });
});

function submitForm() {
    const formData = $('#formDistribusi').serialize();
    
    $.ajax({
        url: 'save.php',
        type: 'POST',
        data: formData,
        beforeSend: function() {
            $('#btnSubmit').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
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
                    // Open print in new tab
                    window.open('cetak_surat_jalan.php?id=' + res.distribusi_id, '_blank');
                    window.location.href = 'detail.php?id=' + res.distribusi_id;
                });
            } else {
                Swal.fire('Gagal!', res.message, 'error');
                $('#btnSubmit').prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Buat Distribusi & Generate QR Code');
            }
        },
        error: function() {
            Swal.fire('Error!', 'Terjadi kesalahan sistem', 'error');
            $('#btnSubmit').prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Buat Distribusi & Generate QR Code');
        }
    });
}

function addItem() {
    itemIndex++;
    
    const html = `
        <tr>
            <td class="text-center align-middle row-number">${itemIndex}</td>
            <td class="align-middle">
                <select class="form-select select-produk" name="items[${itemIndex}][produk_id]" required>
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
                    ?>
                </select>
                <input type="hidden" name="items[${itemIndex}][qty_request]" value="0">
            </td>
            <td class="satuan-text text-center align-middle">-</td>
            <td class="stok-info text-center align-middle font-monospace">-</td>
            <td class="align-middle">
                <input type="number" class="form-control form-control-sm input-qty-kirim" name="items[${itemIndex}][qty_kirim]" min="0.01" step="0.01" required>
            </td>
            <td class="text-center align-middle">
                <div class="d-flex gap-1 justify-content-center">
                    <button type="button" class="btn btn-link text-muted p-0" data-bs-toggle="collapse" data-bs-target="#ket-new-${itemIndex}">
                        <i class="fas fa-comment-dots"></i>
                    </button>
                    <button type="button" class="btn btn-link text-danger p-0 btn-remove-item">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
        <tr class="collapse" id="ket-new-${itemIndex}">
            <td colspan="6" class="bg-light border-0 py-2 px-4 shadow-inner">
                <input type="text" class="form-control form-control-sm bg-white" name="items[${itemIndex}][keterangan]" placeholder="Tambahkan keterangan untuk item ini...">
            </td>
        </tr>
    `;
    
    $('#itemsContainer').append(html);
    
    // Initialize select2
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

</body>
</html>