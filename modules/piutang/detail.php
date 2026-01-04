<?php
// modules/piutang/detail.php
$page_title = 'Detail Piutang';
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();
checkRole(['admin', 'koperasi']);

$id = $_GET['id'] ?? 0;
$piutang = db_get_row("SELECT p.*, k.nama_kantor 
                       FROM piutang p
                       LEFT JOIN kantor k ON p.kantor_id = k.id
                       WHERE p.id = " . db_escape($id));

if (!$piutang) {
    set_flash('error', 'Data tidak ditemukan');
    header("Location: list.php");
    exit;
}

// Get Distribution Items (if ref is distribusi)
$items = [];
if ($piutang['tipe_referensi'] == 'distribusi') {
    $items = db_get_all("SELECT dd.*, p.nama_produk, p.kode_produk, u.nama_satuan
                         FROM distribusi_detail dd
                         JOIN produk p ON dd.produk_id = p.id
                         LEFT JOIN satuan u ON p.satuan_id = u.id
                         WHERE dd.distribusi_id = " . db_escape($piutang['referensi_id']));
}

// Get Payment History
$payments = db_get_all("SELECT pp.*, u.nama_lengkap as user_name
                        FROM pembayaran_piutang pp
                        LEFT JOIN users u ON pp.user_id = u.id
                        WHERE pp.piutang_id = " . db_escape($id) . "
                        ORDER BY pp.tanggal_bayar DESC");

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Detail Piutang</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="list.php" class="btn btn-secondary float-sm-right"><i class="fas fa-arrow-left"></i> Kembali</a>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            
            <div class="row">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Info Piutang</h3>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless table-sm">
                                <tr>
                                    <td>No Referensi</td>
                                    <td class="text-end fw-bold"><?php echo $piutang['no_referensi']; ?></td>
                                </tr>
                                <tr>
                                    <td>Tanggal</td>
                                    <td class="text-end"><?php echo format_tanggal($piutang['tanggal']); ?></td>
                                </tr>
                                <tr>
                                    <td>Jatuh Tempo</td>
                                    <td class="text-end text-danger"><?php echo format_tanggal($piutang['jatuh_tempo']); ?></td>
                                </tr>
                                <tr>
                                    <td>Kantor</td>
                                    <td class="text-end"><?php echo $piutang['nama_kantor']; ?></td>
                                </tr>
                                <tr><td colspan="2"><hr></td></tr>
                                <tr>
                                    <td>Total Tagihan</td>
                                    <td class="text-end fw-bold"><?php echo format_rupiah($piutang['total_piutang']); ?></td>
                                </tr>
                                <tr>
                                    <td>Sudah Dibayar</td>
                                    <td class="text-end text-success"><?php echo format_rupiah($piutang['total_bayar']); ?></td>
                                </tr>
                                <tr>
                                    <td><h5>Sisa Tagihan</h5></td>
                                    <td class="text-end"><h5 class="text-danger"><?php echo format_rupiah($piutang['sisa_piutang']); ?></h5></td>
                                </tr>
                                <tr>
                                    <td>Status</td>
                                    <td class="text-end">
                                        <span class="badge bg-<?php echo $piutang['status'] == 'lunas' ? 'success' : ($piutang['status'] == 'sebagian' ? 'warning' : 'danger'); ?>">
                                            <?php echo strtoupper($piutang['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                            
                            <?php if ($piutang['sisa_piutang'] > 0): ?>
                            <div class="d-grid gap-2 mt-3">
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalBayar">
                                    <i class="fas fa-dollar-sign"></i> Bayar Sekarang
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <!-- Items -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Item Distribusi (<?php echo $piutang['no_referensi']; ?>)</h3>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped table-sm">
                                    <thead>
                                        <tr>
                                            <th>Kode</th>
                                            <th>Nama Produk</th>
                                            <th class="text-end">Qty</th>
                                            <th>Satuan</th>
                                            <th>Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td><?php echo $item['kode_produk']; ?></td>
                                            <td><?php echo $item['nama_produk']; ?></td>
                                            <td class="text-end"><?php echo number_format($item['qty_kirim'], 2); ?></td>
                                            <td><?php echo $item['nama_satuan']; ?></td>
                                            <td><?php echo $item['keterangan']; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="alert alert-info mt-2 mb-0 icon-alert">
                                <i class="fas fa-info-circle me-2"></i>
                                <small>Harga item tidak ditampilkan di sini karena diambil dari master data saat transaksi.</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- History Pembayaran -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Riwayat Pembayaran</h3>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Metode</th>
                                            <th>Keterangan</th>
                                            <th>User</th>
                                            <th class="text-end">Jumlah</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($payments): foreach ($payments as $pay): ?>
                                        <tr>
                                            <td><?php echo format_tanggal($pay['tanggal_bayar']); ?></td>
                                            <td><?php echo ucfirst($pay['metode_bayar']); ?></td>
                                            <td><?php echo $pay['keterangan']; ?></td>
                                            <td><?php echo $pay['user_name']; ?></td>
                                            <td class="text-end fw-bold text-success"><?php echo format_rupiah($pay['jumlah_bayar']); ?></td>
                                        </tr>
                                        <?php endforeach; else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">Belum ada pembayaran.</td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<!-- Modal Bayar -->
<div class="modal fade" id="modalBayar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="payment_save.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="piutang_id" value="<?php echo $id; ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Input Pembayaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tanggal Bayar</label>
                        <input type="date" class="form-control" name="tanggal_bayar" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jumlah Bayar (Maks: <?php echo format_rupiah($piutang['sisa_piutang']); ?>)</label>
                        <input type="number" class="form-control" name="jumlah_bayar" max="<?php echo $piutang['sisa_piutang']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Metode Pembayaran</label>
                        <select class="form-select" name="metode_bayar">
                            <option value="transfer">Transfer Bank</option>
                            <option value="tunai">Tunai</option>
                            <option value="giro">Giro/Cek</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <textarea class="form-control" name="keterangan" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Bukti Transfer (Optional)</label>
                        <input type="file" class="form-control" name="bukti_bayar">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Pembayaran</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
