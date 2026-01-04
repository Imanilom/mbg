<?php
// modules/distribusi/cetak_surat_jalan.php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();

$id = $_GET['id'] ?? 0;
$distribusi = db_get_row("SELECT d.*, k.nama_kantor, k.alamat as alamat_tujuan, u.nama_lengkap as pengirim 
                          FROM distribusi d
                          JOIN kantor k ON d.kantor_id = k.id
                          JOIN users u ON d.pengirim_id = u.id
                          WHERE d.id = " . db_escape($id));

if (!$distribusi) {
    die("Data distribusi tidak ditemukan");
}

$items = db_get_all("SELECT dd.*, p.kode_produk, p.nama_produk, s.nama_satuan
                     FROM distribusi_detail dd
                     JOIN produk p ON dd.produk_id = p.id
                     JOIN satuan s ON p.satuan_id = s.id
                     WHERE dd.distribusi_id = " . db_escape($id));

// Helper for rendering one copy
function render_copy($title, $distribusi, $items) {
    ?>
    <div class="invoice-box">
        <div class="header">
            <div class="title">SURAT JALAN</div>
            <div class="subtitle"><?= $title ?></div>
        </div>

        <div class="meta">
            <table width="100%">
                <tr>
                    <td width="60%" valign="top">
                        <strong>Kepada Yth:</strong><br>
                        <?= strtoupper($distribusi['nama_kantor']) ?><br>
                        <?= $distribusi['alamat_tujuan'] ?>
                    </td>
                    <td width="40%" valign="top" align="right">
                        <table class="meta-table">
                            <tr><td>No SJ</td><td>: <strong><?= $distribusi['no_surat_jalan'] ?></strong></td></tr>
                            <tr><td>Tanggal</td><td>: <?= format_tanggal($distribusi['tanggal_kirim']) ?></td></tr>
                            <tr><td>Pengirim</td><td>: <?= $distribusi['pengirim'] ?></td></tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th width="5%" class="center">No</th>
                    <th width="15%">Kode</th>
                    <th width="40%">Nama Barang</th>
                    <th width="10%" class="center">Qty</th>
                    <th width="10%" class="center">Satuan</th>
                    <th width="20%">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php $no=1; foreach($items as $item): ?>
                <tr>
                    <td class="center"><?= $no++ ?></td>
                    <td><?= $item['kode_produk'] ?></td>
                    <td><?= $item['nama_produk'] ?></td>
                    <td class="center fw-bold"><?= number_format($item['qty_kirim'], 0) ?></td>
                    <td class="center"><?= $item['nama_satuan'] ?></td>
                    <td><?= $item['keterangan'] ?? '-' ?></td>
                </tr>
                <?php endforeach; ?>
                <!-- Fill empty rows if needed -->
                <?php for($i=$no; $i<=10; $i++): ?>
                <tr><td colspan="6">&nbsp;</td></tr>
                <?php endfor; ?>
            </tbody>
        </table>

        <div class="footer">
            <table width="100%">
                <tr>
                    <td width="30%" align="center">
                        Penerima,<br><br><br><br>
                        (.......................)
                    </td>
                    <td width="30%" align="center">
                        Supir/Ekspedisi,<br><br><br><br>
                        (.......................)
                    </td>
                    <td width="40%" align="center">
                        Hormat Kami,<br><br><br><br>
                        ( <?= $distribusi['pengirim'] ?> )
                    </td>
                </tr>
            </table>
        </div>
    </div>
    <?php
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Surat Jalan - <?= $distribusi['no_surat_jalan'] ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11pt; margin: 0; padding: 0; }
        .page { width: 210mm; min-height: 297mm; padding: 10mm; margin: 0 auto; background: white; box-sizing: border-box; }
        .invoice-box { border: 1px solid #000; padding: 10px; margin-bottom: 15mm; position: relative; height: 130mm; }
        
        .header { text-align: center; border-bottom: 2px double #000; padding-bottom: 5px; margin-bottom: 10px; }
        .title { font-size: 18pt; font-weight: bold; letter-spacing: 2px; }
        .subtitle { font-size: 10pt; font-style: italic; }
        
        .meta-table td { padding: 2px 5px; }
        
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; border: 1px solid #000; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 4px 6px; font-size: 10pt; }
        .data-table th { background-color: #eee; text-align: center; }
        .center { text-align: center; }
        .fw-bold { font-weight: bold; }
        
        .footer { margin-top: 20px; }
        
        /* Dashed separator line for 2-ply simulation on one page */
        .separator { border-bottom: 1px dashed #999; margin: 5mm 0; position: relative; }
        .separator::after { content: '✂ Potong disini'; position: absolute; right: 0; top: -10px; font-size: 8pt; color: #666; background: white; padding-left: 5px; }

        @media print {
            body { background: none; -webkit-print-color-adjust: exact; }
            .page { width: 100%; height: auto; padding: 0; margin: 0; }
            .invoice-box { page-break-inside: avoid; height: 48vh; margin-bottom: 2vh; border: 1px solid #000; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="page">
        <?php render_copy("ASLI (Untuk Penerima)", $distribusi, $items); ?>
        
        <div class="separator"></div>
        
        <?php render_copy("COPY (Arsip Pengirim)", $distribusi, $items); ?>
    </div>
</body>
</html>
