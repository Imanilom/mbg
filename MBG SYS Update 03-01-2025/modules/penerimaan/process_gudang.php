<?php
// modules/penerimaan/process_gudang.php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();

$id = $_POST['id'] ?? 0;
$user_id = getUserData('id');

mysqli_begin_transaction($conn);

try {
    $penerimaan = db_get_row("SELECT * FROM penerimaan_barang WHERE id='$id' FOR UPDATE");
    
    if($penerimaan['status'] != 'diterima_koperasi') {
        throw new Exception("Status invalid");
    }

    $items = db_get_all("SELECT * FROM penerimaan_detail WHERE penerimaan_id='$id'");
    
    foreach($items as $item) {
        $produk_id = $item['produk_id'];
        $qty = $item['qty_ke_gudang'];
        
        // 1. Update/Insert Gudang Stok
        // Cek apakah produk sudah ada di stok (assume single batch/location for now for simplicity, or insert new row)
        // For simplicity: Check by produk_id + expired date if applicable.
        // Let's just insert new row for every receipt to track batches accurately? 
        // Or aggregate? The SQL schema `gudang_stok` has `id` PK. 
        // Let's insert new row as "Batch Baru"
        
        $sqlStok = "INSERT INTO gudang_stok (produk_id, qty_stok, qty_available, kondisi) 
                    VALUES ('$produk_id', '$qty', '$qty', '{$item['kondisi']}')";
        db_query($sqlStok);
        
        // 2. Insert Kartu Stok
        // Get current total stok for balance calculation (approximate)
        $total_stok_query = db_get_row("SELECT SUM(qty_stok) as total FROM gudang_stok WHERE produk_id='$produk_id'");
        $saldo_akhir = ($total_stok_query['total'] ?? 0); 
        $saldo_awal = $saldo_akhir - $qty;

        $sqlKartu = "INSERT INTO kartu_stok 
                    (produk_id, tanggal, jenis_transaksi, referensi_tipe, referensi_id, qty, saldo_awal, saldo_akhir, user_id)
                    VALUES 
                    ('$produk_id', NOW(), 'masuk', 'penerimaan', '$id', '$qty', '$saldo_awal', '$saldo_akhir', '$user_id')";
        db_query($sqlKartu);
    }

    // Update Status
    db_query("UPDATE penerimaan_barang SET status='masuk_gudang' WHERE id='$id'");

    mysqli_commit($conn);
    
    // Redirect
    header("Location: detail.php?id=$id&status=success");

} catch(Exception $e) {
    mysqli_rollback($conn);
    die("Error: " . $e->getMessage());
}
?>
