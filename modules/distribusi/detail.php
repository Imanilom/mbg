<?php
// modules/distribusi/detail.php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();

$id = $_GET['id'] ?? 0;
// Utilizes JOINs to get full context
$query = "SELECT d.*, k.nama_kantor, u.nama_lengkap as pengirim, 
          u2.nama_lengkap as penerima_user,
          r.no_request
          FROM distribusi d 
          LEFT JOIN kantor k ON d.kantor_id = k.id 
          LEFT JOIN users u ON d.pengirim_id = u.id 
          LEFT JOIN users u2 ON d.penerima_user_id = u2.id
          LEFT JOIN request r ON d.request_id = r.id
          WHERE d.id = " . db_escape($id);
          
$distribusi = db_get_row($query);

if (!$distribusi) {
    echo "Data distribusi tidak ditemukan.";
    exit;
}

$page_title = 'Detail Distribusi: ' . $distribusi['no_surat_jalan'];
include '../../includes/header.php';
include '../../includes/sidebar.php';
include '../../includes/navbar.php';
?>

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/dashboard/index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="list.php">Distribusi Barang</a></li>
        <li class="breadcrumb-item active"><?= $distribusi['no_surat_jalan'] ?></li>
    </ol>
</nav>

<div class="row">
    <!-- Status & QR Card -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-800 text-dark">
                    <i class="fas fa-info-circle me-2 text-primary opacity-50"></i> Informasi Pengiriman
                </h5>
            </div>
            <div class="card-body p-4">
                <div class="text-center mb-4 p-3 bg-light rounded-4">
                    <?php if(!empty($distribusi['qr_code'])): ?>
                    <img src="<?= BASE_URL ?>/assets/uploads/<?= $distribusi['qr_code'] ?>" 
                         alt="QR Code" class="img-fluid mb-2 shadow-sm rounded-3" style="width: 180px;">
                    <div class="small text-muted fw-bold mt-2">PINDAI UNTUK PENERIMAAN</div>
                    <?php else: ?>
                    <div class="alert alert-warning border-0 rounded-4 x-small">QR Code belum digenerate</div>
                    <?php endif; ?>
                </div>

                <div class="mb-4 text-center">
                    <?= get_status_badge($distribusi['status'], 'distribusi') ?>
                </div>

                <div class="p-3 bg-light rounded-3 mb-4">
                    <div class="row g-0 mb-3">
                        <div class="col-5 small text-muted fw-bold">Tanggal Kirim</div>
                        <div class="col-7 small text-dark fw-800 text-end"><?= format_tanggal($distribusi['tanggal_kirim']) ?></div>
                    </div>
                    <div class="row g-0 mb-3">
                        <div class="col-5 small text-muted fw-bold">Kantor Tujuan</div>
                        <div class="col-7 small text-dark fw-800 text-end"><?= $distribusi['nama_kantor'] ?></div>
                    </div>
                    <div class="row g-0 mb-0">
                        <div class="col-5 small text-muted fw-bold">Pengirim</div>
                        <div class="col-7 small text-dark fw-800 text-end"><?= $distribusi['pengirim'] ?></div>
                    </div>
                </div>

                <button onclick="window.open('cetak_surat_jalan.php?id=<?= $id ?>', '_blank')" class="btn btn-outline-primary w-100 rounded-pill shadow-sm">
                    <i class="fas fa-print me-2"></i> Cetak Surat Jalan (2-Ply)
                </button>
            </div>
        </div>
    </div>

    <!-- Details Column -->
    <div class="col-lg-8">
        <!-- Recipient Info (Only if accepted) -->
        <?php if($distribusi['status'] == 'diterima'): ?>
        <div class="card shadow-sm border-0 mb-4 bg-success-subtle">
            <div class="card-body p-4">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 me-3">
                        <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                            <i class="fas fa-check-double fa-lg"></i>
                        </div>
                    </div>
                    <div>
                        <h6 class="fw-800 text-success mb-1">Barang Telah Diterima</h6>
                        <p class="small text-success-emphasis mb-0">
                            Diterima oleh <strong><?= $distribusi['penerima_name'] ?? '-' ?></strong> pada <?= format_datetime($distribusi['tanggal_terima']) ?>
                        </p>
                    </div>
                    <?php if($distribusi['bukti_terima']): ?>
                    <div class="ms-auto">
                        <a href="<?= BASE_URL ?>/assets/uploads/<?= $distribusi['bukti_terima'] ?>" target="_blank" class="btn btn-success btn-sm rounded-pill shadow-sm px-3">
                            <i class="fas fa-image me-1"></i> Bukti Terima
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Items Table -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-800 text-dark">
                    <i class="fas fa-boxes me-2 text-primary opacity-50"></i> Daftar Barang
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th width="5%" class="text-center py-3 border-0">No</th>
                                <th width="35%" class="py-3 border-0 px-4">Produk</th>
                                <th width="10%" class="text-center py-3 border-0">Satuan</th>
                                <th width="15%" class="text-center py-3 border-0">Qty Kirim</th>
                                <?php if($distribusi['status'] != 'dikirim'): ?>
                                <th width="15%" class="text-center py-3 border-0">Qty Terima</th>
                                <th width="20%" class="text-center py-3 border-0">Selisih</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $items = db_get_all("
                                SELECT dd.*, p.nama_produk, p.kode_produk, s.nama_satuan 
                                FROM distribusi_detail dd
                                JOIN produk p ON dd.produk_id = p.id
                                JOIN satuan s ON p.satuan_id = s.id
                                WHERE dd.distribusi_id = " . db_escape($id)
                            );
                            
                            $no = 1;
                            foreach($items as $item):
                            ?>
                            <tr>
                                <td class="text-center"><?= $no++ ?></td>
                                <td class="px-4">
                                    <div class="fw-bold text-dark mb-0"><?= $item['nama_produk'] ?></div>
                                    <div class="small text-muted"><?= $item['kode_produk'] ?></div>
                                </td>
                                <td class="text-center"><?= $item['nama_satuan'] ?></td>
                                <td class="text-center fw-800 text-primary"><?= format_number($item['qty_kirim']) ?></td>
                                <?php if($distribusi['status'] != 'dikirim'): ?>
                                <td class="text-center fw-bold"><?= format_number($item['qty_terima']) ?></td>
                                <td class="text-center">
                                    <?php 
                                    $selisih = $item['selisih'] ?? 0;
                                    $cls = $selisih == 0 ? 'success' : ($selisih < 0 ? 'danger' : 'warning');
                                    echo "<span class='badge bg-$cls rounded-pill px-3 shadow-sm'>".format_number($selisih)."</span>";
                                    ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if($distribusi['keterangan']): ?>
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-comment-alt me-2 text-primary opacity-50"></i> Keterangan</h6>
            </div>
            <div class="card-body p-4 text-muted small">
                <?= nl2br(clean_input($distribusi['keterangan'])) ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php 
// Print Styles
?>
<style media="print">
  .main-footer, .navbar, .main-sidebar, .btn, .breadcrumb { display: none !important; }
  .content-wrapper { margin-left: 0 !important; }
  .card { box-shadow: none !important; border: 1px solid #ddd !important; }
</style>

<?php include '../../includes/footer.php'; ?>
