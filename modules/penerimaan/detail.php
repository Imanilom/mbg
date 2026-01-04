<?php
// modules/penerimaan/detail.php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();

$id = $_GET['id'] ?? 0;
$data = db_get_row("SELECT p.*, s.nama_supplier, u.nama_lengkap 
                    FROM penerimaan_barang p 
                    LEFT JOIN supplier s ON p.supplier_id=s.id 
                    LEFT JOIN users u ON p.penerima_id=u.id 
                    WHERE p.id=".db_escape($id));

if(!$data) die("Not Found");

$page_title = "Detail Penerimaan: " . $data['no_penerimaan'];
include '../../includes/header.php';
include '../../includes/navbar.php';
include '../../includes/sidebar.php';
?>

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/dashboard/index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="list.php">Penerimaan Barang</a></li>
        <li class="breadcrumb-item active"><?= $data['no_penerimaan'] ?></li>
    </ol>
</nav>

<div class="row">
    <!-- Info Column -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-800 text-dark">
                    <i class="fas fa-info-circle me-2 text-primary opacity-50"></i> Info Penerimaan
                </h5>
            </div>
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <div class="badge bg-primary rounded-pill px-4 py-2 shadow-sm text-uppercase ls-1" style="font-size: 0.75rem;">
                        <?= $data['status'] ?>
                    </div>
                </div>

                <div class="p-3 bg-light rounded-3 mb-4">
                    <div class="row g-0 mb-3 border-bottom pb-2 border-white">
                        <div class="col-5 small text-muted fw-bold">No Penerimaan</div>
                        <div class="col-7 small text-dark fw-800 text-end"><?= $data['no_penerimaan'] ?></div>
                    </div>
                    <div class="row g-0 mb-3 border-bottom pb-2 border-white">
                        <div class="col-5 small text-muted fw-bold">Tanggal</div>
                        <div class="col-7 small text-dark fw-800 text-end"><?= format_tanggal($data['tanggal_terima']) ?></div>
                    </div>
                    <div class="row g-0 mb-3 border-bottom pb-2 border-white">
                        <div class="col-5 small text-muted fw-bold">Supplier</div>
                        <div class="col-7 small text-dark fw-800 text-end"><?= $data['nama_supplier'] ?></div>
                    </div>
                    <div class="row g-0 mb-3 border-bottom pb-2 border-white">
                        <div class="col-5 small text-muted fw-bold">No Surat Jalan</div>
                        <div class="col-7 small text-dark fw-800 text-end"><?= $data['no_surat_jalan'] ?></div>
                    </div>
                    <div class="row g-0 mb-0">
                        <div class="col-5 small text-muted fw-bold">Penerima</div>
                        <div class="col-7 small text-dark fw-800 text-end"><?= $data['nama_lengkap'] ?></div>
                    </div>
                </div>

                <?php if($data['keterangan']): ?>
                <div class="mb-0">
                    <label class="form-label small fw-bold text-muted text-uppercase mb-1">Keterangan</label>
                    <div class="p-3 bg-light rounded-3 small text-muted">
                        <?= nl2br(clean_input($data['keterangan'])) ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Items Column -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-800 text-dark">
                    <i class="fas fa-boxes me-2 text-primary opacity-50"></i> Item Barang Diterima
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th width="45%" class="py-3 border-0 px-4">Produk</th>
                                <th width="15%" class="text-center py-3 border-0">Qty</th>
                                <th width="15%" class="text-center py-3 border-0">Kondisi</th>
                                <th width="25%" class="py-3 border-0">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $items = db_get_all("SELECT pd.*, p.nama_produk, p.kode_produk, s.nama_satuan FROM penerimaan_detail pd JOIN produk p ON pd.produk_id=p.id JOIN satuan s ON p.satuan_id=s.id WHERE pd.penerimaan_id=$id");
                            foreach($items as $item):
                            ?>
                            <tr>
                                <td class="px-4">
                                    <div class="fw-bold text-dark mb-0"><?= $item['nama_produk'] ?></div>
                                    <div class="small text-muted"><?= $item['kode_produk'] ?></div>
                                </td>
                                <td class="text-center">
                                    <span class="fw-800 text-primary"><?= floatval($item['qty_terima']) ?></span>
                                    <span class="small text-muted ms-1"><?= $item['nama_satuan'] ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $item['kondisi'] == 'baik' ? 'success' : 'danger' ?>-subtle text-<?= $item['kondisi'] == 'baik' ? 'success' : 'danger' ?> rounded-pill px-3 shadow-none border-0">
                                        <?= strtoupper($item['kondisi']) ?>
                                    </span>
                                </td>
                                <td class="small text-muted"><?= $item['keterangan'] ?: '-' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white p-4 border-top">
                <div class="d-flex justify-content-end align-items-center">
                    <?php if($data['status'] == 'diterima_koperasi'): ?>
                    <form method="POST" action="process_gudang.php" id="formGudang">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <button type="button" class="btn btn-primary px-5 shadow-sm rounded-pill" onclick="confirmGudang()">
                            <i class="fas fa-warehouse me-2"></i> Masukkan ke Gudang
                        </button>
                    </form>
                    <?php else: ?>
                    <div class="text-success fw-bold d-flex align-items-center">
                        <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 24px; height: 24px;">
                            <i class="fas fa-check" style="font-size: 10px;"></i>
                        </div>
                        Barang Sudah Masuk Gudang
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function confirmGudang() {
    Swal.fire({
        title: 'Konfirmasi Gudang',
        text: 'Apakah Anda yakin ingin memasukkan barang ini ke gudang? Stok produk akan otomatis bertambah.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Ya, Masukkan Sekarang!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('formGudang').submit();
        }
    });
}
</script>

<?php include '../../includes/footer.php'; ?>
