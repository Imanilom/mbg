<?php
// modules/gudang/opname_process.php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();
checkRole(['admin', 'gudang']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: opname.php");
    exit;
}

$start_trans = db_query("START TRANSACTION");

try {
    $opname_id = $_POST['opname_id'] ?? '';
    $action = $_POST['action'] ?? 'draft';
    $nomor_dokumen = $_POST['nomor_dokumen'] ?? '';
    $tanggal = $_POST['tanggal'];
    $keterangan = $_POST['keterangan'];
    $user_id = getUserData('id');

    // Headers
    if (empty($opname_id)) {
        // Create New
        $data_insert = [
            'tanggal' => $tanggal,
            'nomor_dokumen' => generate_number('SO', 'stok_opname', 'nomor_dokumen'),
            'keterangan' => $keterangan,
            'status' => $action,
            'user_id' => $user_id
        ];
        $opname_id = db_insert('stok_opname', $data_insert);
    } else {
        // Update Existing
        $data_update = [
            'tanggal' => date('Y-m-d', strtotime($tanggal)), 
            'keterangan' => db_escape($keterangan),
            'status' => $action
        ];
        
        // Manual update query because db_update wrapper might be limited
        $sql_update = "UPDATE stok_opname SET 
                       tanggal = '{$data_update['tanggal']}', 
                       keterangan = '{$data_update['keterangan']}', 
                       status = '{$data_update['status']}' 
                       WHERE id = '$opname_id'";
        db_query($sql_update);
        
        // Delete old details to replace them
        db_query("DELETE FROM stok_opname_detail WHERE opname_id = '$opname_id'");
    }

    // Process Details
    $gudang_stok_ids = $_POST['gudang_stok_id'] ?? [];
    $produk_ids = $_POST['produk_id'] ?? [];
    $qty_sistems = $_POST['qty_sistem'] ?? [];
    $qty_fisiks = $_POST['qty_fisik'] ?? [];
    $item_keterangans = $_POST['item_keterangan'] ?? [];

    for ($i = 0; $i < count($gudang_stok_ids); $i++) {
        $gs_id = $gudang_stok_ids[$i];
        $p_id = $produk_ids[$i];
        $q_sys = $qty_sistems[$i];
        $q_fisik = $qty_fisiks[$i];
        $desc = $item_keterangans[$i];
        $diff = $q_fisik - $q_sys;

        // Save Detail
        $detail_data = [
            'opname_id' => $opname_id,
            'gudang_stok_id' => $gs_id,
            'produk_id' => $p_id,
            'qty_sistem' => $q_sys,
            'qty_fisik' => $q_fisik,
            'keterangan' => $desc
        ];
        db_insert('stok_opname_detail', $detail_data);

        // If Final, Update Stock
        if ($action == 'final' && $diff != 0) {
            // Update Gudang Stok
            // Note: We adjust qty_stok. Since qty_available is generated from qty_stok - qty_reserved, 
            // updating qty_stok is correct assuming reserved doesn't change here.
            
            // However, we need to be careful. The Opname "Physical Qty" should replace the "System Qty".
            // So we update qty_stok so that (new_qty_stok - qty_reserved) = qty_fisik.
            // Simplified: new_qty_stok = qty_fisik + qty_reserved
            // Or simpler: just add the difference to qty_stok.
            // new_qty_stok = old_qty_stok + diff
            
            $sql_gs = "UPDATE gudang_stok SET qty_stok = qty_stok + ($diff) WHERE id = '$gs_id'";
            if (!db_query($sql_gs)) {
                throw new Exception("Gagal update gudang stok ID $gs_id");
            }

            // Insert Kartu Stok
            // If diff > 0 (Surplus) -> Masuk
            // If diff < 0 (Deficit) -> Keluar
            // Actually 'opname' or 'adjustment' type handles signed value usually, 
            // but our kartu stok has 'masuk'/'keluar' enum.
            // If we use 'opname' enum, we should store absolute qty? 
            // Let's check kartu_stok table definition again.
            // It has 'jenis_transaksi' enum('masuk','keluar','opname','adjustment').
            // And 'qty' decimal.
            
            // Standard approach:
            // Opname/Adjustment record the DELTA.
            // But we can also be explicit using Masuk/Keluar if we want.
            // Let's use 'opname' type.
            
            // Need current balance (saldo_akhir prev)
            $last_stock_card = db_get_row("SELECT saldo_akhir FROM kartu_stok WHERE produk_id = '$p_id' ORDER BY id DESC LIMIT 1");
            $saldo_awal = $last_stock_card['saldo_akhir'] ?? 0; // Total stock of product
            
            // Wait, this saldo_awal is "Product Level" aggregate.
            // Opname is adjusting "Batch Level".
            // But Kartu Stok is usually product level.
            // If we adjust one batch, the product total changes by 'diff'.
            
            $saldo_akhir = $saldo_awal + $diff;
            
            $ks_data = [
                'produk_id' => $p_id,
                'tanggal' => date('Y-m-d H:i:s'),
                'jenis_transaksi' => 'opname',
                'referensi_tipe' => 'stok_opname',
                'referensi_id' => $opname_id,
                'qty' => $diff, // Can be negative
                'saldo_awal' => $saldo_awal,
                'saldo_akhir' => $saldo_akhir,
                'keterangan' => "Opname #$opname_id: $desc",
                'user_id' => $user_id
            ];
            
            db_insert('kartu_stok', $ks_data);
        }
    }

    db_query("COMMIT");
    set_flash('success', 'Stok Opname berhasil disimpan ' . ($action == 'final' ? 'dan stok diperbarui' : '(Draft)'));
    header("Location: opname.php");

} catch (Exception $e) {
    db_query("ROLLBACK");
    set_flash('error', 'Gagal menyimpan: ' . $e->getMessage());
    header("Location: opname_form.php?id=" . $opname_id);
}

?>
