<?php
require_once '../../helpers/ajax.php';
require_once '../../config/database.php';
require_once '../../helpers/session.php';

ajax_check_unexpected_output();

try {
    checkLogin();

    if(!isset($_POST['produk_id'])) {
        throw new Exception("Product ID is missing");
    }

    $produk_id = mysqli_real_escape_string($conn, $_POST['produk_id']);
    
    $query = "SELECT p.*, s.nama_satuan, 
              (SELECT COALESCE(SUM(qty_available), 0) FROM gudang_stok WHERE produk_id = p.id AND kondisi = 'baik') as stok_tersedia
              FROM produk p
              INNER JOIN satuan s ON p.satuan_id = s.id
              WHERE p.id = '$produk_id'";
    
    $result = mysqli_query($conn, $query);
    $produk = mysqli_fetch_assoc($result);
    
    if(!$produk) {
        throw new Exception("Product not found");
    }

    echo json_encode([
        'status' => 'success',
        'data' => [
            'satuan' => $produk['nama_satuan'],
            'stok' => $produk['stok_tersedia'],
            'harga_estimasi' => (float)($produk['harga_estimasi'] ?? 0)
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>