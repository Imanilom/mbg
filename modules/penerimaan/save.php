<?php
require_once '../../helpers/ajax.php';
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

ajax_check_unexpected_output();

try {
    checkLogin();

    $no_penerimaan = $_POST['no_penerimaan'] ?? '';
    $tanggal_terima = $_POST['tanggal_terima'] ?? '';
    $pembelanjaan_id = !empty($_POST['pembelanjaan_id']) ? $_POST['pembelanjaan_id'] : NULL;
    $supplier_id = !empty($_POST['supplier_id']) ? $_POST['supplier_id'] : NULL;
    $no_surat_jalan = $_POST['no_surat_jalan'] ?? '';
    $kondisi_barang = $_POST['kondisi_barang'] ?? '';
    $keterangan = $_POST['keterangan'] ?? '';
    $user_id = getUserData('id');

    if (empty($no_penerimaan)) throw new Exception("No Penerimaan tidak boleh kosong");
    if (empty($tanggal_terima)) throw new Exception("Tanggal terima tidak boleh kosong");

    mysqli_begin_transaction($conn);

    // 1. Insert Header
    // Status langsung 'masuk_gudang' agar stok bertambah
    $sql = "INSERT INTO penerimaan_barang 
    (no_penerimaan, tanggal_terima, pembelanjaan_id, supplier_id, no_surat_jalan, penerima_id, kondisi_barang, keterangan, status)
    VALUES 
    ('" . db_escape($no_penerimaan) . "', 
     '" . db_escape($tanggal_terima) . "', 
     " . ($pembelanjaan_id ? "'" . db_escape($pembelanjaan_id) . "'" : "NULL") . ", 
     " . ($supplier_id ? "'" . db_escape($supplier_id) . "'" : "NULL") . ", 
     '" . db_escape($no_surat_jalan) . "', 
     '" . db_escape($user_id) . "', 
     '" . db_escape($kondisi_barang) . "', 
     '" . db_escape($keterangan) . "', 
     'masuk_gudang')";
    
    if(!db_query($sql)) throw new Exception("Gagal simpan header: " . mysqli_error($conn));
    $penerimaan_id = mysqli_insert_id($conn);

    // 2. Insert Detail & Update Stock
    if(isset($_POST['produk_id']) && is_array($_POST['produk_id'])) {
        foreach($_POST['produk_id'] as $k => $prod_id) {
            $qty = (float)($_POST['qty_terima'][$k] ?? 0);
            $cond = $_POST['kondisi'][$k] ?? 'baik';
            $ket = $_POST['ket_item'][$k] ?? '';
            
            // Qty ke gudang = qty terima (default)
            $qty_gudang = $qty; 
            
            // 2a. Insert Penerimaan Detail
            $sqlDet = "INSERT INTO penerimaan_detail (penerimaan_id, produk_id, qty_terima, qty_ke_gudang, kondisi, keterangan)
            VALUES ('$penerimaan_id', '" . db_escape($prod_id) . "', '$qty', '$qty_gudang', '" . db_escape($cond) . "', '" . db_escape($ket) . "')";
            
            if(!db_query($sqlDet)) throw new Exception("Gagal simpan detail: " . mysqli_error($conn));

            // 2b. Update Gudang Stok (Auto-Entry)
            if($qty_gudang > 0) {
                // Fetch Harga Beli from Pembelanjaan Detail (if linked)
                $harga_beli = 0;
                if ($pembelanjaan_id) {
                    $row_harga = db_get_row("SELECT harga_satuan FROM pembelanjaan_detail WHERE pembelanjaan_id = '$pembelanjaan_id' AND produk_id = '$prod_id'");
                    if ($row_harga) {
                        $harga_beli = (float)$row_harga['harga_satuan'];
                    }
                }

                // Cari apakah ada stok dengan batch default? Jika tidak buat baru.
                // Disini kita asumsi simple: Batch = Tanggal Terima, Lokasi = 'General'
                $batch_number = date('Ymd', strtotime($tanggal_terima));
                
                // Cek existing row
                $stok_exist = db_get_row("SELECT id, qty_stok, harga_beli FROM gudang_stok WHERE produk_id = '" . db_escape($prod_id) . "' AND batch_number = '$batch_number' AND kondisi = 'baik' AND harga_beli = '$harga_beli' LIMIT 1");
                
                if($stok_exist) {
                     $new_qty = $stok_exist['qty_stok'] + $qty_gudang;
                     // Update juga qty_available (asumsi available = stok - reserved)
                     $sqlUpdStok = "UPDATE gudang_stok SET qty_stok = qty_stok + $qty_gudang, qty_available = qty_available + $qty_gudang WHERE id = '{$stok_exist['id']}'";
                     if(!db_query($sqlUpdStok)) throw new Exception("Gagal update gudang stok");
                     $saldo_akhir = $new_qty;
                } else {
                     $sqlInsStok = "INSERT INTO gudang_stok (produk_id, batch_number, qty_stok, qty_available, qty_reserved, kondisi, lokasi_rak, harga_beli)
                     VALUES ('" . db_escape($prod_id) . "', '$batch_number', '$qty_gudang', '$qty_gudang', 0, 'baik', 'A-01', '$harga_beli')";
                     if(!db_query($sqlInsStok)) throw new Exception("Gagal insert gudang stok: " . mysqli_error($conn));
                     $saldo_akhir = $qty_gudang;
                }

                // 2c. Kartu Stok
                $saldo_awal = $saldo_akhir - $qty_gudang;
                $sqlKartu = "INSERT INTO kartu_stok (produk_id, tanggal, jenis_transaksi, referensi_tipe, referensi_id, qty, saldo_awal, saldo_akhir, keterangan, user_id)
                VALUES ('" . db_escape($prod_id) . "', '$tanggal_terima', 'masuk', 'penerimaan', '$penerimaan_id', '$qty_gudang', '$saldo_awal', '$saldo_akhir', 'Penerimaan No: $no_penerimaan (HPP: $harga_beli)', '$user_id')";
                
                if(!db_query($sqlKartu)) throw new Exception("Gagal catat kartu stok");

                // 2d. Update Harga Beli di Master Produk (Margin 1)
                // Agar di modul Penetapan Harga selalu update dengan harga pembelian terakhir
                if ($harga_beli > 0) {
                    db_query("UPDATE produk SET harga_beli = '$harga_beli' WHERE id = '" . db_escape($prod_id) . "'");
                }
            }
        }
    }

    // 3. Update Status Pembelanjaan
    if($pembelanjaan_id) {
        db_query("UPDATE pembelanjaan SET status='selesai' WHERE id='" . db_escape($pembelanjaan_id) . "'");
    }

    mysqli_commit($conn);
    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    if (isset($conn)) mysqli_rollback($conn);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
