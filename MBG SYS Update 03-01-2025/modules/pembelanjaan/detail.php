<?php
// modules/pembelanjaan/detail.php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();

$id = $_GET['id'] ?? 0;
// Detail query
$sql = "SELECT p.*, s.nama_supplier, u.nama_lengkap 
        FROM pembelanjaan p 
        LEFT JOIN supplier s ON p.supplier_id = s.id 
        LEFT JOIN users u ON p.user_id = u.id 
        WHERE p.id = " . db_escape($id);
$data = db_get_row($sql);

if (!$data) {
    die("Data tidak ditemukan");
}

$page_title = 'Detail Pembelanjaan: ' . $data['no_pembelanjaan'];
include '../../includes/header.php';
include '../../includes/navbar.php';
include '../../includes/sidebar.php';
?>

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/dashboard/index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="list.php">Data Pembelanjaan</a></li>
        <li class="breadcrumb-item active">Detail</li>
    </ol>
</nav>

<div class="row">
    <div class="col-lg-8">
        <!-- Main Detail Card -->
        <div class="card shadow-sm border-0 mb-4 text-break">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-800 text-dark">
                    <i class="fas fa-receipt me-2 text-primary opacity-50"></i> Informasi Pembelanjaan
                </h5>
                <div class="d-flex align-items-center gap-3">
                    <span class="small text-muted fw-bold"><?= format_tanggal($data['tanggal']) ?></span>
                    <?= get_status_badge($data['status'], 'request') ?>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-1" style="letter-spacing: 0.5px;">No Pembelanjaan</label>
                        <div class="fw-bold h5 mb-0 text-primary"><?= $data['no_pembelanjaan'] ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-1" style="letter-spacing: 0.5px;">Periode</label>
                        <div class="fw-bold mb-0 text-dark">
                            <span class="badge bg-soft-info text-info rounded-pill px-2 py-1 small"><?= ucfirst($data['periode_type']) ?></span>
                            <?= $data['periode_value'] ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-1" style="letter-spacing: 0.5px;">Supplier</label>
                        <div class="fw-bold mb-0 text-dark">
                            <i class="fas fa-store me-1 text-muted"></i>
                            <?= $data['nama_supplier'] ?? 'Non-Supplier (Pasar/Umum)' ?>
                        </div>
                        <?php if($data['supplier_id']): 
                            $supp_addr = db_get_row("SELECT alamat, no_telp FROM supplier WHERE id=".$data['supplier_id']);
                        ?>
                        <div class="small text-muted mt-1 ps-4">
                            <?= $supp_addr['alamat'] ?><br>
                            <i class="fas fa-phone-alt me-1 small"></i> <?= $supp_addr['no_telp'] ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-1" style="letter-spacing: 0.5px;">Dicatat Oleh</label>
                        <div class="fw-bold mb-0 text-dark">
                            <i class="fas fa-user-edit me-1 text-muted"></i>
                            <?= $data['nama_lengkap'] ?>
                        </div>
                    </div>
                </div>

                <div class="mb-0">
                    <label class="form-label small fw-bold text-muted text-uppercase mb-2" style="letter-spacing: 0.5px;">Keterangan</label>
                    <div class="p-3 bg-light rounded-3 text-dark small border-0 shadow-none" style="min-height: 80px;">
                        <?= $data['keterangan'] ? nl2br(clean_input($data['keterangan'])) : '<span class="text-muted italic">Tidak ada keterangan</span>' ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Items Table Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-800 text-dark">
                    <i class="fas fa-shopping-basket me-2 text-primary opacity-50"></i> Rincian Barang
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th width="5%" class="text-center py-3 border-0">No</th>
                                <th class="py-3 border-0">Produk</th>
                                <th width="15%" class="text-center py-3 border-0">Qty</th>
                                <th width="20%" class="text-end py-3 border-0 text-nowrap">Harga Satuan</th>
                                <th width="20%" class="text-end py-3 border-0 text-nowrap">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $items = db_get_all("
                                SELECT pd.*, p.nama_produk, p.kode_produk, s.nama_satuan 
                                FROM pembelanjaan_detail pd 
                                JOIN produk p ON pd.produk_id = p.id 
                                JOIN satuan s ON p.satuan_id = s.id 
                                WHERE pd.pembelanjaan_id = $id
                            ");
                            $no = 1;
                            foreach($items as $item):
                            ?>
                            <tr>
                                <td class="text-center"><?= $no++ ?></td>
                                <td>
                                    <span class="small text-muted d-block"><?= $item['kode_produk'] ?></span>
                                    <span class="fw-bold text-dark"><?= $item['nama_produk'] ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold text-dark"><?= floatval($item['qty']) ?></span>
                                    <span class="small text-muted"><?= $item['nama_satuan'] ?></span>
                                </td>
                                <td class="text-end text-nowrap"><?= format_rupiah($item['harga_satuan']) ?></td>
                                <td class="text-end text-nowrap fw-bold text-dark"><?= format_rupiah($item['subtotal']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-white">
                            <tr>
                                <td colspan="4" class="text-end py-4 border-0">
                                    <h5 class="mb-0 text-muted small fw-bold text-uppercase">Total Keseluruhan</h5>
                                </td>
                                <td class="text-end py-4 border-0">
                                    <h4 class="mb-0 fw-800 text-primary"><?= format_rupiah($data['total_belanja']) ?></h4>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white p-4 border-top d-flex justify-content-between align-items-center">
                <a href="list.php" class="btn btn-outline-secondary px-4">
                    <i class="fas fa-arrow-left me-2"></i> Kembali
                </a>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary px-4" onclick="window.print()">
                        <i class="fas fa-print me-2"></i> Print
                    </button>
                    <?php if($data['status'] != 'selesai'): ?>
                    <a href="../penerimaan/add.php?pembelanjaan_id=<?= $id ?>" class="btn btn-primary px-4 shadow-sm">
                        <i class="fas fa-box-open me-2"></i> Proses Penerimaan
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Attachments Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-800 text-dark">
                    <i class="fas fa-paperclip me-2 text-primary opacity-50"></i> Lampiran & Bukti
                </h5>
            </div>
            <div class="card-body p-4">
                <?php if($data['bukti_belanja']): ?>
                <div class="rounded-4 overflow-hidden shadow-sm mb-3">
                    <?php 
                    $file_ext = strtolower(pathinfo($data['bukti_belanja'], PATHINFO_EXTENSION));
                    if(in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                    <img src="<?= BASE_URL ?>/assets/uploads/pembelanjaan/<?= $data['bukti_belanja'] ?>" class="img-fluid" alt="Bukti Belanja">
                    <?php else: ?>
                    <div class="bg-light p-5 text-center">
                        <i class="fas fa-file-pdf fa-4x text-danger opacity-50 mb-3"></i>
                        <div class="small fw-bold text-muted">Dokumen PDF</div>
                    </div>
                    <?php endif; ?>
                </div>
                <a href="<?= BASE_URL ?>/assets/uploads/pembelanjaan/<?= $data['bukti_belanja'] ?>" target="_blank" class="btn btn-light w-100 rounded-pill fw-bold text-muted border">
                    <i class="fas fa-expand me-2"></i> Lihat Full Version
                </a>
                <?php else: ?>
                <div class="text-center py-5">
                    <div class="p-4 bg-light rounded-circle d-inline-block mb-3">
                        <i class="fas fa-image fa-2x text-muted opacity-25"></i>
                    </div>
                    <p class="text-muted small mb-0">Tidak ada lampiran bukti belanja.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Info Card -->
        <div class="card shadow-sm border-0 bg-primary text-white overflow-hidden">
            <div class="card-body p-4 position-relative">
                <i class="fas fa-quote-right position-absolute opacity-10" style="right: -10px; bottom: -10px; font-size: 8rem;"></i>
                <h6 class="text-white-50 fw-bold text-uppercase small mb-3" style="letter-spacing: 1px;">Status Terakhir</h6>
                <div class="h4 fw-800 mb-4">
                    <?= $data['status'] == 'selesai' ? 'Sudah Diterima Gudang' : 'Menunggu Penerimaan' ?>
                </div>
                <div class="small text-white-50">
                    <?= $data['status'] == 'selesai' ? 'Semua produk dari belanjaan ini telah berhasil diverifikasi dan masuk ke stok gudang.' : 'Belanjaan telah dicatat, barang perlu diverifikasi oleh petugas gudang untuk update stok.' ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
