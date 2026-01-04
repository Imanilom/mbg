<?php
require_once '../../helpers/ajax.php';
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

ajax_check_unexpected_output();

checkLogin();
checkRole(['kantor', 'admin']);

$user = getUserData();

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    mysqli_begin_transaction($conn);
    
    try {
        $distribusi_id = (int)$_POST['distribusi_id'];
        $qr_code = mysqli_real_escape_string($conn, $_POST['qr_code']);
        $penerima_name = mysqli_real_escape_string($conn, $_POST['penerima_name']);
        $tanggal_terima = mysqli_real_escape_string($conn, $_POST['tanggal_terima']);
        $latitude = mysqli_real_escape_string($conn, $_POST['latitude']);
        $longitude = mysqli_real_escape_string($conn, $_POST['longitude']);
        
        // Validate distribusi exists and status is 'dikirim'
        $query_check = "SELECT * FROM distribusi WHERE id = '$distribusi_id' AND status = 'dikirim'";
        $result_check = mysqli_query($conn, $query_check);
        
        if(mysqli_num_rows($result_check) == 0) {
            throw new Exception("Distribusi tidak valid atau sudah pernah diterima");
        }
        
        $distribusi = mysqli_fetch_assoc($result_check);
        
        // Check if user's kantor matches
        if($user['role'] == 'kantor' && $distribusi['kantor_id'] != $user['kantor_id']) {
            throw new Exception("Anda tidak memiliki akses untuk menerima distribusi ini");
        }
        
        // Handle foto upload
        $foto_barang = null;
        if(isset($_FILES['foto_barang']) && $_FILES['foto_barang']['error'] == 0) {
            $foto_dir = '../../assets/uploads/distribusi/';
            if(!is_dir($foto_dir)) {
                mkdir($foto_dir, 0777, true);
            }
            
            $foto_ext = pathinfo($_FILES['foto_barang']['name'], PATHINFO_EXTENSION);
            $foto_filename = 'foto_' . $distribusi_id . '_' . time() . '.' . $foto_ext;
            $foto_path = $foto_dir . $foto_filename;
            
            if(move_uploaded_file($_FILES['foto_barang']['tmp_name'], $foto_path)) {
                $foto_barang = $foto_filename;
            }
        }
        
        // Update distribusi header
        $query_update_header = "UPDATE distribusi SET 
            penerima_name = '$penerima_name',
            penerima_user_id = '{$user['id']}',
            tanggal_terima = '$tanggal_terima',
            scan_latitude = '$latitude',
            scan_longitude = '$longitude',
            status = 'diterima',
            foto_barang = " . ($foto_barang ? "'$foto_barang'" : "NULL") . "
            WHERE id = '$distribusi_id'";
        
        if(!mysqli_query($conn, $query_update_header)) {
            throw new Exception("Gagal update distribusi: " . mysqli_error($conn));
        }
        
        // Process each item
        $has_problem = false;
        
        if(!isset($_POST['items']) || !is_array($_POST['items'])) {
            throw new Exception("Detail barang tidak ditemukan");
        }
        
        foreach($_POST['items'] as $detail_id => $item) {
            $qty_terima = (float)$item['qty_terima'];
            $qty_kirim = (float)$item['qty_kirim'];
            $kondisi_terima = mysqli_real_escape_string($conn, $item['kondisi_terima']);
            $alasan_selisih = mysqli_real_escape_string($conn, $item['alasan_selisih']);
            
            // Update distribusi_detail
            $query_update_detail = "UPDATE distribusi_detail SET 
                qty_terima = '$qty_terima',
                kondisi_terima = '$kondisi_terima',
                alasan_selisih = '$alasan_selisih'
                WHERE id = '$detail_id'";
            
            if(!mysqli_query($conn, $query_update_detail)) {
                throw new Exception("Gagal update detail distribusi: " . mysqli_error($conn));
            }
            
            // Get produk_id
            $query_produk = "SELECT produk_id FROM distribusi_detail WHERE id = '$detail_id'";
            $result_produk = mysqli_query($conn, $query_produk);
            $produk_data = mysqli_fetch_assoc($result_produk);
            $produk_id = $produk_data['produk_id'];
            
            // Release reserved stock dan kurangi stok sesuai qty_kirim
            releaseAndReduceStock($conn, $produk_id, $qty_kirim);
            
            // Catat kartu stok keluar
            $saldo_sekarang = getCurrentStock($conn, $produk_id);
            
            $query_kartu = "INSERT INTO kartu_stok (
                produk_id, tanggal, jenis_transaksi, referensi_tipe, referensi_id,
                qty, saldo_awal, saldo_akhir, keterangan, user_id
            ) VALUES (
                '$produk_id',
                '$tanggal_terima',
                'keluar',
                'distribusi',
                '$distribusi_id',
                '$qty_kirim',
                '$saldo_sekarang',
                " . ($saldo_sekarang - $qty_kirim) . ",
                'Distribusi ke kantor - SJ: {$distribusi['no_surat_jalan']}',
                '{$user['id']}'
            )";
            
            if(!mysqli_query($conn, $query_kartu)) {
                throw new Exception("Gagal catat kartu stok: " . mysqli_error($conn));
            }
            
            // Check if there's problem (selisih)
            if($kondisi_terima != 'lengkap') {
                $has_problem = true;
            }
        }
        
        // Update status jika ada masalah
        if($has_problem) {
            $query_update_status = "UPDATE distribusi SET status = 'bermasalah' WHERE id = '$distribusi_id'";
            mysqli_query($conn, $query_update_status);
        }
        
        // Log scan QR
        $device_info = $_SERVER['HTTP_USER_AGENT'];
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        $query_log_scan = "INSERT INTO log_scan_qr (
            qr_code, distribusi_id, user_id, scan_time, scan_status,
            device_info, ip_address, latitude, longitude, keterangan
        ) VALUES (
            '$qr_code', '$distribusi_id', '{$user['id']}', '$tanggal_terima', 'success',
            '$device_info', '$ip_address', '$latitude', '$longitude', 'Penerimaan barang berhasil'
        )";
        
        mysqli_query($conn, $query_log_scan);
        
        // Log activity
        logActivity($conn, $user['id'], "Menerima distribusi: {$distribusi['no_surat_jalan']}", 'distribusi', $distribusi_id);
        
        mysqli_commit($conn);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Penerimaan barang berhasil dicatat',
            'distribusi_id' => $distribusi_id
        ]);
        
    } catch(Exception $e) {
        mysqli_rollback($conn);
        
        // Log failed scan
        if(isset($qr_code) && isset($distribusi_id)) {
            $device_info = $_SERVER['HTTP_USER_AGENT'];
            $ip_address = $_SERVER['REMOTE_ADDR'];
            
            $query_log_failed = "INSERT INTO log_scan_qr (
                qr_code, distribusi_id, user_id, scan_time, scan_status,
                device_info, ip_address, keterangan
            ) VALUES (
                '$qr_code', '$distribusi_id', '{$user['id']}', NOW(), 'failed',
                '$device_info', '$ip_address', '{$e->getMessage()}'
            )";
            
            mysqli_query($conn, $query_log_failed);
        }
        
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}

// Function to release reserved stock and reduce actual stock
function releaseAndReduceStock($conn, $produk_id, $qty) {
    // Get reserved stock FIFO
    $query_stok = "SELECT id, qty_reserved, qty_stok
                   FROM gudang_stok 
                   WHERE produk_id = '$produk_id' AND qty_reserved > 0
                   ORDER BY tanggal_expired ASC, batch_number ASC";
    
    $result_stok = mysqli_query($conn, $query_stok);
    
    $remaining_qty = $qty;
    
    while($stok = mysqli_fetch_assoc($result_stok)) {
        if($remaining_qty <= 0) break;
        
        $qty_to_release = min($remaining_qty, $stok['qty_reserved']);
        
        // Release reserved dan kurangi stok
        $new_qty_stok = $stok['qty_stok'] - $qty_to_release;
        $new_qty_reserved = $stok['qty_reserved'] - $qty_to_release;
        
        $query_update = "UPDATE gudang_stok 
                        SET qty_stok = '$new_qty_stok',
                            qty_reserved = '$new_qty_reserved'
                        WHERE id = '{$stok['id']}'";
        
        if(!mysqli_query($conn, $query_update)) {
            throw new Exception("Gagal update stok: " . mysqli_error($conn));
        }
        
        $remaining_qty -= $qty_to_release;
    }
    
    if($remaining_qty > 0) {
        throw new Exception("Reserved stock tidak mencukupi untuk produk ID: $produk_id");
    }
}

// Function to get current stock
function getCurrentStock($conn, $produk_id) {
    $query = "SELECT COALESCE(SUM(qty_stok), 0) as total_stok 
              FROM gudang_stok 
              WHERE produk_id = '$produk_id'";
    
    $result = mysqli_query($conn, $query);
    $data = mysqli_fetch_assoc($result);
    
    return $data['total_stok'];
}
?>