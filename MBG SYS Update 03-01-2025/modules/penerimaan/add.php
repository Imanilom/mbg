<?php
// modules/penerimaan/add.php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();

$pembelanjaan_id = $_GET['pembelanjaan_id'] ?? '';
$pembelanjaan = null;
$detail_items = [];

if($pembelanjaan_id) {
    $pembelanjaan = db_get_row("SELECT * FROM pembelanjaan WHERE id = ".db_escape($pembelanjaan_id));
    if($pembelanjaan) {
        $detail_items = db_get_all("
            SELECT pd.*, p.nama_produk, p.kode_produk, s.nama_satuan 
            FROM pembelanjaan_detail pd
            JOIN produk p ON pd.produk_id = p.id
            JOIN satuan s ON p.satuan_id = s.id
            WHERE pd.pembelanjaan_id = $pembelanjaan_id
        ");
    }
}

$page_title = 'Input Penerimaan Barang';
$no_terima = generate_number('TRM', 'penerimaan_barang', 'no_penerimaan');

include '../../includes/header.php';
include '../../includes/navbar.php';
include '../../includes/sidebar.php';
?>

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/dashboard/index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="list.php">Penerimaan Barang</a></li>
        <li class="breadcrumb-item active">Tambah</li>
    </ol>
</nav>

<form id="formPenerimaan" method="POST">
    <div class="row">
        <!-- Header Card -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-800 text-dark">
                        <i class="fas fa-file-import me-2 text-primary opacity-50"></i> Header Penerimaan
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">No Penerimaan</label>
                        <input type="text" class="form-control bg-light border-0 fw-bold" name="no_penerimaan" value="<?= $no_terima ?>" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Tanggal Terima <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="tanggal_terima" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Ref. Pembelanjaan (Optional)</label>
                        <div class="input-group">
                            <input type="text" class="form-control bg-light border-0 small" id="pembelanjaan_txt" value="<?= $pembelanjaan ? $pembelanjaan['no_pembelanjaan'] : '' ?>" readonly placeholder="Pilih Pembelanjaan...">
                            <input type="hidden" name="pembelanjaan_id" value="<?= $pembelanjaan_id ?>">
                            <input type="hidden" name="supplier_id" value="<?= $pembelanjaan['supplier_id'] ?? '' ?>">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalRef">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">No Surat Jalan / Nota Supplier</label>
                        <input type="text" class="form-control" name="no_surat_jalan" required placeholder="Masukkan nomor referensi...">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Kondisi Umum</label>
                        <select class="form-select" name="kondisi_barang">
                            <option value="baik">Sesuai & Baik</option>
                            <option value="kurang">Kurang (Jumlah Tidak Sesuai)</option>
                            <option value="rusak">Rusak (Ada Kerusakan)</option>
                        </select>
                    </div>

                    <div class="mb-0">
                        <label class="form-label small fw-bold text-muted">Keterangan</label>
                        <textarea class="form-control" name="keterangan" rows="3" placeholder="Catatan penerimaan..."><?= $pembelanjaan['keterangan'] ?? '' ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Item Card -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-800 text-dark">
                        <i class="fas fa-boxes me-2 text-primary opacity-50"></i> Detail Item Diterima
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="tableItems">
                            <thead class="bg-light">
                                <tr>
                                    <th width="40%" class="py-3 border-0 px-4">Produk</th>
                                    <th width="15%" class="text-center py-3 border-0">Qty Beli</th>
                                    <th width="15%" class="text-center py-3 border-0">Qty Terima</th>
                                    <th width="20%" class="py-3 border-0">Kondisi</th>
                                    <th width="10%" class="text-center py-3 border-0"></th>
                                </tr>
                            </thead>
                            <tbody id="itemsContainer">
                                <?php if($detail_items): foreach($detail_items as $item): ?>
                                <tr>
                                    <td class="align-middle px-4">
                                        <div class="fw-bold text-dark mb-0"><?= $item['nama_produk'] ?></div>
                                        <div class="small text-muted"><?= $item['kode_produk'] ?></div>
                                        <input type="hidden" name="produk_id[]" value="<?= $item['produk_id'] ?>">
                                    </td>
                                    <td class="text-center align-middle">
                                        <span class="badge bg-light text-dark border px-3"><?= floatval($item['qty']) ?></span>
                                    </td>
                                    <td class="align-middle">
                                        <input type="number" step="0.01" class="form-control form-control-sm text-center fw-bold" name="qty_terima[]" value="<?= floatval($item['qty']) ?>" required>
                                    </td>
                                    <td class="align-middle">
                                        <select class="form-select form-select-sm" name="kondisi[]">
                                            <option value="baik">Baik</option>
                                            <option value="rusak">Rusak</option>
                                        </select>
                                    </td>
                                    <td class="text-center align-middle">
                                        <button type="button" class="btn btn-link text-muted p-0" data-bs-toggle="collapse" data-bs-target="#ket-<?= $item['produk_id'] ?>">
                                            <i class="fas fa-comment-dots"></i>
                                        </button>
                                        <button type="button" class="btn btn-link text-danger p-0 ms-2" onclick="$(this).closest('tr').remove()">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr class="collapse" id="ket-<?= $item['produk_id'] ?>">
                                    <td colspan="5" class="bg-light border-0 py-2 px-4 shadow-inner text-end">
                                        <input type="text" class="form-control form-control-sm bg-white d-inline-block w-75" name="ket_item[]" placeholder="Catatan item ini...">
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr id="emptyMsg">
                                    <td colspan="5" class="py-5 text-center text-muted">
                                        <div class="mb-2"><i class="fas fa-search fa-2x opacity-20"></i></div>
                                        Silahkan pilih referensi pembelanjaan terlebih dahulu
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white p-4 border-top">
                    <div class="d-flex gap-2 justify-content-end">
                        <a href="list.php" class="btn btn-outline-secondary px-4">Batal</a>
                        <button type="submit" class="btn btn-primary px-4 shadow-sm">
                            <i class="fas fa-save me-2"></i> Simpan Penerimaan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Modal Ref -->
<div class="modal fade" id="modalRef" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-white border-bottom-0 py-3">
                <h5 class="modal-title fw-800 text-dark">Pilih Referensi Pembelanjaan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="tableRef">
                        <thead class="bg-light">
                            <tr>
                                <th class="py-3 border-0 px-4">No Pembelanjaan</th>
                                <th class="py-3 border-0">Tanggal</th>
                                <th class="py-3 border-0">Supplier</th>
                                <th class="py-3 border-0 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $pending = db_get_all("SELECT p.*, s.nama_supplier FROM pembelanjaan p LEFT JOIN supplier s ON p.supplier_id=s.id WHERE p.status != 'selesai' ORDER BY p.tanggal DESC");
                            foreach($pending as $p) {
                                echo "<tr>
                                    <td class='align-middle px-4 fw-bold text-primary'>{$p['no_pembelanjaan']}</td>
                                    <td class='align-middle'>" . date('d/m/Y', strtotime($p['tanggal'])) . "</td>
                                    <td class='align-middle fw-600'>{$p['nama_supplier']}</td>
                                    <td class='align-middle text-center'>
                                        <a href='add.php?pembelanjaan_id={$p['id']}' class='btn btn-primary btn-sm rounded-pill px-3 shadow-sm'>Pilih</a>
                                    </td>
                                </tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
$extra_js = '
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function(){
    $("#formPenerimaan").submit(function(e){
        e.preventDefault();
        
        const form = $(this);
        const submitBtn = form.find("button[type=submit]");
        
        $.ajax({
            url: "save.php",
            type: "POST",
            data: form.serialize(),
            beforeSend: function() {
                submitBtn.prop("disabled", true).html("<i class=\'fas fa-spinner fa-spin me-2\'></i> Menyimpan...");
            },
            success: function(resp){
                if(resp.status=="success"){
                    Swal.fire({
                        icon: "success",
                        title: "Berhasil!",
                        text: "Data penerimaan berhasil disimpan",
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        window.location.href="list.php";
                    });
                } else {
                    Swal.fire("Gagal!", resp.message, "error");
                    submitBtn.prop("disabled", false).html("<i class=\'fas fa-save me-2\'></i> Simpan Penerimaan");
                }
            },
            error: function() {
                Swal.fire("Error!", "Terjadi kesalahan koneksi server", "error");
                submitBtn.prop("disabled", false).html("<i class=\'fas fa-save me-2\'></i> Simpan Penerimaan");
            }
        });
    });
});
</script>';
include '../../includes/footer.php'; 
?>
