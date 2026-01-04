<?php
// modules/master/produk_template.php
require_once '../../config/database.php';
require_once '../../helpers/session.php';

checkLogin();

// Set Headers for Excel download
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Template_Import_Produk.xls");
header("Pragma: no-cache");
header("Expires: 0");
?>

<!DOCTYPE html>
<html>
<head>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 5px; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h3>Template Import Produk</h3>
    <p>Petunjuk: Isi data di bawah ini. Kolom Kode Produk akan di-generate otomatis jika kosong. Kolom ID referensi (Jenis, Kategori, Satuan) harus sesuai dengan ID di sistem.</p>
    <table>
        <thead>
            <tr>
                <th>Nama Produk*</th>
                <th>ID Jenis Barang*</th>
                <th>ID Kategori*</th>
                <th>ID Satuan*</th>
                <th>Tipe Item (stok/distribusi/khusus)*</th>
                <th>Harga Estimasi</th>
                <th>Stok Minimum</th>
                <th>Masa Kadaluarsa (Hari)</th>
                <th>Spesifikasi</th>
                <th>Deskripsi</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Contoh Produk 1</td>
                <td>1</td>
                <td>2</td>
                <td>1</td>
                <td>stok</td>
                <td>10000</td>
                <td>10</td>
                <td>365</td>
                <td>Warna Merah</td>
                <td>Deskripsi Produk</td>
            </tr>
        </tbody>
    </table>
    
    <br><br>
    <h4>Referensi ID:</h4>
    <table>
        <tr>
            <td valign="top">
                <b>Jenis Barang</b><br>
                <?php
                $jenis = db_get_all("SELECT id, nama_jenis FROM jenis_barang WHERE status='aktif'");
                foreach($jenis as $j) echo "{$j['id']} - {$j['nama_jenis']}<br>";
                ?>
            </td>
            <td valign="top">
                <b>Kategori</b><br>
                <?php
                $kategori = db_get_all("SELECT id, nama_kategori FROM kategori WHERE status='aktif'");
                foreach($kategori as $k) echo "{$k['id']} - {$k['nama_kategori']}<br>";
                ?>
            </td>
            <td valign="top">
                <b>Satuan</b><br>
                <?php
                $satuan = db_get_all("SELECT id, nama_satuan FROM satuan");
                foreach($satuan as $s) echo "{$s['id']} - {$s['nama_satuan']}<br>";
                ?>
            </td>
        </tr>
    </table>
</body>
</html>
