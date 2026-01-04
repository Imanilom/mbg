<?php
// modules/laporan/cetak.php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();

$jenis = $_GET['jenis'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$status = $_GET['status'] ?? '';
$sub_jenis = $_GET['sub_jenis'] ?? '';
$kategori_id = $_GET['kategori_id'] ?? '';

// Basic Validation
if (empty($jenis)) die("Jenis laporan tidak valid.");

// --- Helper for Title ---
$title = "Laporan " . ucfirst($jenis);
$subtitle = "";
if ($start_date && $end_date) {
    $subtitle = "Periode: " . format_tanggal($start_date) . " s/d " . format_tanggal($end_date);
}

// --- Data Fetching Logic ---
$data = [];
$columns = [];
$totals = [];

switch ($jenis) {
    case 'request':
        $query = "SELECT r.*, k.nama_kantor, u.nama_lengkap as pemohon 
                  FROM request r
                  LEFT JOIN kantor k ON r.kantor_id = k.id
                  LEFT JOIN users u ON r.user_id = u.id
                  WHERE 1=1";
        if ($start_date) $query .= " AND DATE(r.created_at) >= '$start_date'";
        if ($end_date) $query .= " AND DATE(r.created_at) <= '$end_date'";
        if ($status) {
            $statuses = explode(',', $status);
            $status_list = "'" . implode("','", $statuses) . "'";
            $query .= " AND r.status IN ($status_list)";
        }
        $query .= " ORDER BY r.created_at DESC";
        $data = db_get_all($query);
        $columns = ['Nomor', 'Tanggal', 'Kantor', 'Pemohon', 'Prioritas', 'Status'];
        break;

    case 'pembelanjaan':
        $query = "SELECT p.*, s.nama_supplier 
                  FROM pembelanjaan p
                  LEFT JOIN supplier s ON p.supplier_id = s.id
                  WHERE 1=1";
        if ($start_date) $query .= " AND p.tanggal >= '$start_date'";
        if ($end_date) $query .= " AND p.tanggal <= '$end_date'";
        $query .= " ORDER BY p.tanggal DESC";
        $data = db_get_all($query);
        $columns = ['Nomor', 'Tanggal', 'Supplier', 'Periode', 'Total', 'Status'];
        break;

    case 'distribusi':
        $query = "SELECT d.*, k.nama_kantor 
                  FROM distribusi d
                  LEFT JOIN kantor k ON d.kantor_id = k.id
                  WHERE 1=1";
        if ($start_date) $query .= " AND d.tanggal_kirim >= '$start_date'";
        if ($end_date) $query .= " AND d.tanggal_kirim <= '$end_date'";
        if ($status) $query .= " AND d.status = '$status'";
        $query .= " ORDER BY d.tanggal_kirim DESC";
        $data = db_get_all($query);
        $columns = ['No Surat Jalan', 'Tanggal Kirim', 'Tujuan', 'Status'];
        break;
        
    case 'penerimaan':
        $query = "SELECT pb.*, p.nomor_pembelanjaan, s.nama_supplier
                  FROM penerimaan_barang pb
                  LEFT JOIN pembelanjaan p ON pb.pembelanjaan_id = p.id
                  LEFT JOIN supplier s ON p.supplier_id = s.id
                  WHERE 1=1";
        if ($start_date) $query .= " AND pb.tanggal_terima >= '$start_date'";
        if ($end_date) $query .= " AND pb.tanggal_terima <= '$end_date'";
        $query .= " ORDER BY pb.tanggal_terima DESC";
        $data = db_get_all($query);
        $columns = ['No Penerimaan', 'Tanggal', 'Dari PO', 'Supplier', 'Penerima', 'Status'];
        break;

    case 'stok':
        if ($sub_jenis == 'persediaan' || empty($sub_jenis)) {
            $query = "SELECT p.kode_produk, p.nama_produk, k.nama_kategori, s.nama_satuan,
                             COALESCE(SUM(gs.qty_available), 0) as stok_akhir,
                             p.harga_estimasi,
                             (COALESCE(SUM(gs.qty_available), 0) * p.harga_estimasi) as nilai_aset
                      FROM produk p
                      LEFT JOIN gudang_stok gs ON p.id = gs.produk_id
                      LEFT JOIN kategori_produk k ON p.kategori_id = k.id
                      LEFT JOIN satuan s ON p.satuan_id = s.id
                      WHERE 1=1";
            if ($kategori_id) $query .= " AND p.kategori_id = '$kategori_id'";
            $query .= " GROUP BY p.id ORDER BY p.nama_produk ASC";
            $data = db_get_all($query);
            $columns = ['Kode', 'Nama Produk', 'Kategori', 'Satuan', 'Stok', 'Harga Estimasi', 'Nilai Aset'];
            $title = "Laporan Posisi Stok";
        } elseif ($sub_jenis == 'expired') {
            $query = "SELECT p.kode_produk, p.nama_produk, gs.batch_number, gs.tanggal_expired, gs.qty_available, gs.lokasi_rak
                      FROM gudang_stok gs
                      JOIN produk p ON gs.produk_id = p.id
                      WHERE gs.qty_available > 0 AND gs.tanggal_expired IS NOT NULL";
            $query .= " ORDER BY gs.tanggal_expired ASC"; // Expiring soon first
            $data = db_get_all($query);
            $columns = ['Kode', 'Nama Produk', 'Batch', 'Expired', 'Sisa Stok', 'Lokasi'];
            $title = "Laporan Monitoring Expired";
        }
        break;

    case 'piutang':
        $query = "SELECT p.*, k.nama_kantor 
                  FROM piutang p
                  LEFT JOIN kantor k ON p.kantor_id = k.id
                  WHERE 1=1";
        if ($start_date) $query .= " AND p.jatuh_tempo >= '$start_date'";
        if ($end_date) $query .= " AND p.jatuh_tempo <= '$end_date'";
        if ($status) $query .= " AND p.status = '$status'";
        $query .= " ORDER BY p.tanggal DESC";
        $data = db_get_all($query);
        $columns = ['No Ref', 'Tanggal', 'Jatuh Tempo', 'Kantor', 'Total', 'Dibayar', 'Sisa', 'Status'];
        break;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Laporan</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h2 { margin: 0; }
        .header p { margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #333; padding: 5px; }
        th { background-color: #f2f2f2; }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
        .footer { margin-top: 30px; text-align: right; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="window.print()">Cetak</button>
        <button onclick="window.close()">Tutup</button>
    </div>

    <div class="header">
        <h2>MARKETLIST MBG</h2>
        <h3><?php echo $title; ?></h3>
        <p><?php echo $subtitle; ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%">No</th>
                <?php foreach ($columns as $col): ?>
                <th><?php echo $col; ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            if ($data):
                foreach ($data as $row): 
            ?>
            <tr>
                <td class="text-center"><?php echo $no++; ?></td>
                <?php 
                // Manual Rendering based on Type
                if ($jenis == 'request') {
                    echo "<td>{$row['nomor_request']}</td>";
                    echo "<td>" . format_tanggal($row['created_at']) . "</td>";
                    echo "<td>{$row['nama_kantor']}</td>";
                    echo "<td>{$row['pemohon']}</td>";
                    echo "<td>{$row['prioritas']}</td>";
                    echo "<td>{$row['status']}</td>";
                } elseif ($jenis == 'pembelanjaan') {
                    echo "<td>{$row['no_pembelanjaan']}</td>";
                    echo "<td>" . format_tanggal($row['tanggal']) . "</td>";
                    echo "<td>{$row['nama_supplier']}</td>";
                    echo "<td>" . ucfirst($row['periode_type']) . "</td>";
                    echo "<td class='text-end'>" . format_rupiah($row['total_belanja']) . "</td>";
                    echo "<td>{$row['status']}</td>";
                } elseif ($jenis == 'distribusi') {
                    echo "<td>{$row['no_surat_jalan']}</td>";
                    echo "<td>" . format_tanggal($row['tanggal_kirim']) . "</td>";
                    echo "<td>{$row['nama_kantor']}</td>";
                    echo "<td>{$row['status']}</td>";
                } elseif ($jenis == 'penerimaan') {
                    echo "<td>{$row['no_penerimaan']}</td>";
                    echo "<td>" . format_tanggal($row['tanggal_terima']) . "</td>";
                    echo "<td>{$row['no_pembelanjaan']}</td>";
                    echo "<td>{$row['nama_supplier']}</td>";
                    echo "<td>-</td>";
                    echo "<td>{$row['status']}</td>";
                } elseif ($jenis == 'stok' && $sub_jenis == 'persediaan') {
                    echo "<td>{$row['kode_produk']}</td>";
                    echo "<td>{$row['nama_produk']}</td>";
                    echo "<td>{$row['nama_kategori']}</td>";
                    echo "<td>{$row['nama_satuan']}</td>";
                    echo "<td class='text-end'>" . number_format($row['stok_akhir'], 2) . "</td>";
                    echo "<td class='text-end'>" . format_rupiah($row['harga_estimasi']) . "</td>";
                    echo "<td class='text-end'>" . format_rupiah($row['nilai_aset']) . "</td>";
                } elseif ($jenis == 'stok' && $sub_jenis == 'expired') {
                    echo "<td>{$row['kode_produk']}</td>";
                    echo "<td>{$row['nama_produk']}</td>";
                    echo "<td>{$row['batch_number']}</td>";
                    echo "<td>" . format_tanggal($row['tanggal_expired']) . "</td>";
                    echo "<td class='text-end'>" . number_format($row['qty_available'], 2) . "</td>";
                    echo "<td>{$row['lokasi_rak']}</td>";
                } elseif ($jenis == 'piutang') {
                    echo "<td>{$row['no_referensi']}</td>";
                    echo "<td>" . format_tanggal($row['tanggal']) . "</td>";
                    echo "<td>" . format_tanggal($row['jatuh_tempo']) . "</td>";
                    echo "<td>{$row['nama_kantor']}</td>";
                    echo "<td class='text-end'>" . format_rupiah($row['total_piutang']) . "</td>";
                    echo "<td class='text-end'>" . format_rupiah($row['total_bayar']) . "</td>";
                    echo "<td class='text-end'>" . format_rupiah($row['sisa_piutang']) . "</td>";
                    echo "<td>{$row['status']}</td>";
                }
                ?>
            </tr>
            <?php endforeach; else: ?>
            <tr>
                <td colspan="<?php echo count($columns) + 1; ?>" class="text-center">Tidak ada data untuk periode ini.</td>
            </tr>
            <?php endif; ?>
        </tbody>
        <?php if ($jenis == 'pembelanjaan' || ($jenis == 'stok' && $sub_jenis == 'persediaan') || $jenis == 'piutang'): ?>
        <tfoot>
            <tr>
                <td colspan="<?php echo count($columns); ?>" class="text-end"><strong>Total</strong></td>
                <td class="text-end"><strong>
                    <?php 
                    $total = 0;
                    foreach ($data as $d) {
                        if ($jenis == 'pembelanjaan') $total += $d['total_belanja'];
                        if ($jenis == 'stok') $total += $d['nilai_aset'];
                        if ($jenis == 'piutang') $total += $d['sisa_piutang']; // Sisa is relevant usually
                    }
                    echo format_rupiah($total);
                    ?>
                </strong></td>
                <?php if ($jenis == 'piutang'): ?>
                <td></td>
                <?php endif; ?>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>

    <div class="footer">
        <p>Dicetak oleh: <?php echo getUserData('nama_lengkap'); ?></p>
        <p>Tanggal: <?php echo date('d-m-Y H:i'); ?></p>
    </div>
</body>
</html>
