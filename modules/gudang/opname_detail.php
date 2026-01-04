<?php
// modules/gudang/opname_detail.php
$page_title = 'Detail Stok Opname';
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();
checkRole(['admin', 'gudang']);

$id = $_GET['id'] ?? 0;
$opname = db_get_row("SELECT so.*, u.nama_lengkap as user_name 
                      FROM stok_opname so
                      LEFT JOIN users u ON so.user_id = u.id
                      WHERE so.id = " . db_escape($id));

if (!$opname) {
    set_flash('error', 'Data tidak ditemukan.');
    header("Location: opname.php");
    exit;
}

$details = db_get_all("SELECT sod.*, p.nama_produk, p.kode_produk, gs.batch_number, u.nama_satuan
                       FROM stok_opname_detail sod
                       JOIN produk p ON sod.produk_id = p.id
                       LEFT JOIN satuan u ON p.satuan_id = u.id
                       LEFT JOIN gudang_stok gs ON sod.gudang_stok_id = gs.id
                       WHERE sod.opname_id = " . db_escape($id));

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Detail Stok Opname</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="opname.php" class="btn btn-secondary float-sm-right"><i class="fas fa-arrow-left"></i> Kembali</a>
                    <?php if ($opname['status'] == 'final'): ?>
                    <button onclick="window.print()" class="btn btn-outline-primary ms-2"><i class="fas fa-print"></i> Cetak</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Informasi Opname</h3>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <table class="table table-borderless table-sm">
                                <tr>
                                    <td style="width: 120px;">Nomor Dokumen</td>
                                    <td>: <strong><?php echo $opname['nomor_dokumen']; ?></strong></td>
                                </tr>
                                <tr>
                                    <td>Tanggal</td>
                                    <td>: <?php echo format_tanggal($opname['tanggal']); ?></td>
                                </tr>
                                <tr>
                                    <td>Status</td>
                                    <td>: <span class="badge bg-<?php echo $opname['status'] == 'final' ? 'success' : 'secondary'; ?>"><?php echo strtoupper($opname['status']); ?></span></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless table-sm">
                                <tr>
                                    <td style="width: 120px;">Dibuat Oleh</td>
                                    <td>: <?php echo $opname['user_name']; ?></td>
                                </tr>
                                <tr>
                                    <td>Keterangan</td>
                                    <td>: <?php echo $opname['keterangan'] ?: '-'; ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered text-sm">
                            <thead>
                                <tr>
                                    <th>Kode</th>
                                    <th>Nama Produk</th>
                                    <th>Batch</th>
                                    <th class="text-end">Qty Sistem</th>
                                    <th class="text-end">Qty Fisik</th>
                                    <th class="text-end">Selisih</th>
                                    <th>Keterangan Item</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_items = 0;
                                $total_selisih = 0;
                                foreach ($details as $row): 
                                    $total_items++;
                                    $total_selisih += $row['selisih'];
                                    $diff_class = $row['selisih'] != 0 ? ($row['selisih'] < 0 ? 'text-danger fw-bold' : 'text-success fw-bold') : 'text-muted';
                                ?>
                                <tr>
                                    <td><?php echo $row['kode_produk']; ?></td>
                                    <td><?php echo $row['nama_produk']; ?> <small class="text-muted">(<?php echo $row['nama_satuan']; ?>)</small></td>
                                    <td><?php echo $row['batch_number'] ?: '-'; ?></td>
                                    <td class="text-end"><?php echo number_format($row['qty_sistem'], 2); ?></td>
                                    <td class="text-end"><?php echo number_format($row['qty_fisik'], 2); ?></td>
                                    <td class="text-end <?php echo $diff_class; ?>"><?php echo number_format($row['selisih'], 2); ?></td>
                                    <td><?php echo $row['keterangan']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-active">
                                    <td colspan="5" class="text-end fw-bold">Total Selisih</td>
                                    <td class="text-end fw-bold"><?php echo number_format($total_selisih, 2); ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<?php include '../../includes/footer.php'; ?>
