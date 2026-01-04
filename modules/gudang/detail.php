<?php
// modules/gudang/detail.php
$page_title = 'Kartu Stok Detail';
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();
checkRole(['admin', 'gudang', 'koperasi']);

$id = $_GET['id'] ?? 0;
// Get Product Info
$produk = db_get_row("SELECT p.*, k.nama_kategori, s.nama_satuan 
                      FROM produk p
                      LEFT JOIN kategori_produk k ON p.kategori_id = k.id
                      LEFT JOIN satuan s ON p.satuan_id = s.id
                      WHERE p.id = " . db_escape($id));

if (!$produk) {
    set_flash('error', 'Produk tidak ditemukan');
    header("Location: stok.php");
    exit;
}

// Get Stock Batches (Current Stock)
$batches = db_get_all("SELECT * FROM gudang_stok WHERE produk_id = '$id' AND qty_available > 0 ORDER BY tanggal_expired ASC");

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Kartu Stok: <?php echo $produk['nama_produk']; ?></h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="stok.php" class="btn btn-secondary float-sm-right"><i class="fas fa-arrow-left"></i> Kembali</a>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">

            <div class="row">
                <div class="col-md-4">
                    <div class="card card-primary card-outline">
                        <div class="card-body box-profile">
                            <h3 class="profile-username text-center"><?php echo $produk['kode_produk']; ?></h3>
                            <p class="text-muted text-center"><?php echo $produk['nama_produk']; ?></p>

                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <div class="mb-2"><strong>Kategori:</strong> <br><?php echo $produk['nama_kategori']; ?></div>
                                    <div class="mb-2"><strong>Satuan:</strong> <br><?php echo $produk['nama_satuan']; ?></div>
                                    <div class="mb-2"><strong>Min. Stok:</strong> <br><?php echo $produk['stok_minimum']; ?></div>
                                    <div class="mb-2"><strong>Harga Estimasi:</strong> <br><?php echo format_rupiah($produk['harga_estimasi']); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <!-- Batches -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Stok per Batch</h3>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Batch Number</th>
                                        <th>Expired</th>
                                        <th>Lokasi Rak</th>
                                        <th class="text-end">Qty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_batch_stok = 0;
                                    if ($batches): foreach ($batches as $batch): 
                                        $total_batch_stok += $batch['qty_available'];
                                    ?>
                                    <tr>
                                        <td><?php echo $batch['batch_number']; ?></td>
                                        <td>
                                            <?php 
                                            // Expire Alert
                                            $days_left = (strtotime($batch['tanggal_expired']) - time()) / (60 * 60 * 24);
                                            $class = 'text-success';
                                            if ($days_left < 0) $class = 'text-danger fw-bold';
                                            elseif ($days_left < 30) $class = 'text-warning fw-bold';
                                            echo "<span class='$class'>" . format_tanggal($batch['tanggal_expired']) . "</span>";
                                            ?>
                                        </td>
                                        <td><?php echo $batch['lokasi_rak']; ?></td>
                                        <td class="text-end fw-bold"><?php echo number_format($batch['qty_available'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; else: ?>
                                    <tr><td colspan="4" class="text-center">Stok Kosong</td></tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="3" class="text-end">Total</th>
                                        <th class="text-end"><?php echo number_format($total_batch_stok, 2); ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Kartu Stok History -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Kartu Stok (100 Transaksi Terakhir)</h3>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Tipe</th>
                                            <th>No Ref</th>
                                            <th>Ket</th>
                                            <th class="text-end">Masuk</th>
                                            <th class="text-end">Keluar</th>
                                            <th class="text-end">Saldo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Fetch history from kartu_stok
                                        $history = db_get_all("SELECT * FROM kartu_stok 
                                                               WHERE produk_id = '$id' 
                                                               ORDER BY created_at DESC LIMIT 100");
                                        
                                        if ($history): foreach ($history as $h):
                                            $badge = $h['jenis_transaksi'] == 'masuk' ? '<span class="badge bg-success">Masuk</span>' : '<span class="badge bg-danger">Keluar</span>';
                                        ?>
                                        <tr>
                                            <td><?php echo format_tanggal($h['tanggal']); ?></td>
                                            <td><?php echo $badge; ?></td>
                                            <td><?php echo $h['referensi']; ?></td>
                                            <td><?php echo $h['keterangan']; ?></td>
                                            <td class="text-end text-success"><?php echo $h['jenis_transaksi'] == 'masuk' ? number_format($h['jumlah'], 2) : '-'; ?></td>
                                            <td class="text-end text-danger"><?php echo $h['jenis_transaksi'] == 'keluar' ? number_format($h['jumlah'], 2) : '-'; ?></td>
                                            <td class="text-end fw-bold"><?php echo number_format($h['stok_akhir'], 2); ?></td>
                                        </tr>
                                        <?php endforeach; else: ?>
                                        <tr><td colspan="7" class="text-center">Belum ada riwayat transaksi.</td></tr>
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

<?php include '../../includes/footer.php'; ?>
