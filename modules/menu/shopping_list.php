<?php
$page_title = 'Daftar Belanja';
require_once '../../includes/header.php';
require_once '../../helpers/MenuHarianHelper.php';
require_once '../../helpers/functions.php';

// Inputs
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d', strtotime('+1 week'));

// Calculate
$menuHelper = new MenuHarianHelper();
$items = $menuHelper->calculateShoppingList($start_date, $end_date);

// Get menus in range for context
$menus = $menuHelper->getMenusByDateRange($start_date, $end_date);
$menu_count = 0;
foreach ($menus as $date => $day_menus) {
    $menu_count += count($day_menus);
}

require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';
?>

<div class="container-fluid py-4">
    <div class="d-print-none mb-4">
        <a href="planner.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Kembali ke Planner
        </a>
        <button onclick="window.print()" class="btn btn-primary float-end">
            <i class="fas fa-print me-1"></i> Cetak Daftar Belanja
        </button>
    </div>

    <div class="card shadow">
        <div class="card-header py-3 bg-white">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4 class="m-0 font-weight-bold text-primary">Daftar Belanja Menu Harian</h4>
                    <p class="mb-0 text-muted">
                        Periode: <strong><?= format_tanggal($start_date) ?></strong> s/d <strong><?= format_tanggal($end_date) ?></strong>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <span class="badge bg-info p-2">
                        <i class="fas fa-utensils me-1"></i> <?= $menu_count ?> Menu Terjadwal
                    </span>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($items)): ?>
                <div class="text-center py-5">
                    <div class="text-gray-300 mb-3">
                        <i class="fas fa-shopping-basket fa-4x"></i>
                    </div>
                    <h5>Tidak ada item yang perlu dibelanjakan</h5>
                    <p class="text-muted">Mungkin belum ada menu yang dijadwalkan atau semua stok gudang mencukupi.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" width="100%" cellspacing="0">
                        <thead class="table-dark">
                            <tr>
                                <th>Nama Bahan / Produk</th>
                                <th class="text-end">Total Kebutuhan</th>
                                <th class="text-end">Stok Gudang</th>
                                <th class="text-end">Harus Beli</th>
                                <th>Rekomendasi Pasar</th>
                                <th width="15%">Supplier / Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_estimates = 0;
                            foreach ($items as $item): 
                                $row_class = $item['qty_to_purchase'] > 0 ? '' : 'text-muted bg-light';
                                $purchase_est = 0;
                                if (isset($item['market_recommendation'])) {
                                    $purchase_est = $item['qty_to_purchase'] * $item['market_recommendation']['harga_terendah'];
                                    $total_estimates += $purchase_est;
                                }
                                
                                // Get supplier info for this product
                                $supplier_query = "SELECT s.* FROM supplier s 
                                                  INNER JOIN produk p ON p.supplier_id = s.id 
                                                  WHERE p.id = ? AND s.status = 'aktif' LIMIT 1";
                                $stmt = $conn->prepare($supplier_query);
                                $stmt->bind_param("i", $item['produk_id']);
                                $stmt->execute();
                                $supplier_result = $stmt->get_result();
                                $supplier = $supplier_result->fetch_assoc();
                                $stmt->close();
                            ?>
                            <tr class="<?= $row_class ?>">
                                <td class="fw-bold"><?= htmlspecialchars($item['nama_produk']) ?></td>
                                <td class="text-end">
                                    <?= format_number($item['total_qty_needed']) ?> <?= $item['satuan'] ?>
                                </td>
                                <td class="text-end">
                                    <?= format_number($item['warehouse_stock']) ?> <?= $item['satuan'] ?>
                                </td>
                                <td class="text-end <?= $item['qty_to_purchase'] > 0 ? 'fw-bold text-danger' : 'text-success' ?>">
                                    <?= format_number($item['qty_to_purchase']) ?> <?= $item['satuan'] ?>
                                </td>
                                <td>
                                    <?php if ($item['qty_to_purchase'] > 0): ?>
                                        <?php if (isset($item['market_recommendation'])): ?>
                                            <div class="small">
                                                <i class="fas fa-store text-primary me-1"></i>
                                                <strong><?= htmlspecialchars($item['market_recommendation']['nama_pasar']) ?></strong><br>
                                                <span class="text-muted">
                                                    @ Rp <?= format_number($item['market_recommendation']['harga_terendah']) ?>
                                                    (Total: Rp <?= format_number($purchase_est) ?>)
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small fs-italic">Belum ada data harga pasar</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-success"><i class="fas fa-check"></i> Stok Cukup</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($item['qty_to_purchase'] > 0): ?>
                                        <?php if ($supplier && !empty($supplier['pic_phone'])): ?>
                                            <div class="small mb-2">
                                                <i class="fas fa-truck text-info me-1"></i>
                                                <strong><?= htmlspecialchars($supplier['nama_supplier']) ?></strong><br>
                                                <span class="text-muted">
                                                    PIC: <?= htmlspecialchars($supplier['pic_name'] ?? '-') ?>
                                                </span>
                                            </div>
                                            <?php
                                            // Format WhatsApp message
                                            $phone = preg_replace('/[^0-9]/', '', $supplier['pic_phone']);
                                            if (substr($phone, 0, 1) == '0') {
                                                $phone = '62' . substr($phone, 1);
                                            }
                                            
                                            $message = "Halo, saya dari MBG System.\n\n";
                                            $message .= "Saya ingin memesan:\n";
                                            $message .= "📦 *" . $item['nama_produk'] . "*\n";
                                            $message .= "📊 Jumlah: *" . format_number($item['qty_to_purchase']) . " " . $item['satuan'] . "*\n";
                                            $message .= "📅 Untuk periode: " . format_tanggal($start_date) . " - " . format_tanggal($end_date) . "\n\n";
                                            $message .= "Mohon informasi ketersediaan dan harganya. Terima kasih!";
                                            
                                            $wa_link = "https://wa.me/" . $phone . "?text=" . urlencode($message);
                                            ?>
                                            <a href="<?= $wa_link ?>" target="_blank" class="btn btn-success btn-sm w-100 d-print-none">
                                                <i class="fab fa-whatsapp me-1"></i> Pesan via WA
                                            </a>
                                        <?php elseif ($supplier): ?>
                                            <div class="small text-muted">
                                                <i class="fas fa-truck me-1"></i>
                                                <?= htmlspecialchars($supplier['nama_supplier']) ?><br>
                                                <span class="text-danger small">No WA tidak tersedia</span>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">
                                                <i class="fas fa-exclamation-triangle me-1"></i>
                                                Supplier belum diset
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php if ($total_estimates > 0): ?>
                        <tfoot>
                            <tr class="table-active">
                                <td colspan="5" class="text-end fw-bold">Estimasi Total Belanja:</td>
                                <td class="fw-bold text-primary">Rp <?= format_number($total_estimates) ?></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
// Helper if format_tanggal is not available in included files
if (!function_exists('format_tanggal')) {
    function format_tanggal($date) {
        return date('d M Y', strtotime($date));
    }
}
if (!function_exists('format_number')) {
    function format_number($number) {
        return number_format($number, 0, ',', '.');
    }
}

require_once '../../includes/footer.php'; 
?>
