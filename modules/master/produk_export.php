<?php
// modules/master/produk_export.php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();

// Set Headers for Excel download
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Data_Produk_" . date('Y-m-d') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// Get Data
$query = "SELECT p.*, j.nama_jenis, k.nama_kategori, s.nama_satuan 
          FROM produk p 
          LEFT JOIN jenis_barang j ON p.jenis_barang_id = j.id 
          LEFT JOIN kategori k ON p.kategori_id = k.id 
          LEFT JOIN satuan s ON p.satuan_id = s.id 
          ORDER BY p.nama_produk ASC";
$data = db_get_all($query);
?>

<!DOCTYPE html>
<html>
<head>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 5px; }
        th { background-color: #f2f2f2; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <h3>Data Produk Marketlist MBG</h3>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Kode Produk</th>
                <th>Nama Produk</th>
                <th>Jenis Barang</th>
                <th>Kategori</th>
                <th>Satuan</th>
                <th>Tipe Item</th>
                <th>Status</th>
                <th>Harga Estimasi</th>
                <th>Stok Minimum</th>
                <th>Masa Kadaluarsa (Hari)</th>
                <th>Spesifikasi</th>
                <th>Deskripsi</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; foreach($data as $row): ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td><?= $row['kode_produk'] ?></td>
                <td><?= $row['nama_produk'] ?></td>
                <td><?= $row['nama_jenis'] ?></td>
                <td><?= $row['nama_kategori'] ?></td>
                <td><?= $row['nama_satuan'] ?></td>
                <td><?= ucfirst($row['tipe_item']) ?></td>
                <td><?= ucfirst($row['status_produk']) ?></td>
                <td class="text-right"><?= $row['harga_estimasi'] ?></td>
                <td class="text-center"><?= $row['stok_minimum'] ?></td>
                <td class="text-center"><?= $row['masa_kadaluarsa_hari'] ?></td>
                <td><?= $row['spesifikasi'] ?></td>
                <td><?= $row['deskripsi'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
