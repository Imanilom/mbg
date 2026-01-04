<?php
require_once '../../helpers/ajax.php';
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

ajax_check_unexpected_output();
$qr_lib_path = '../../vendor/phpqrcode/qrlib.php';
$has_qr_lib = file_exists(__DIR__ . '/' . $qr_lib_path);

if ($has_qr_lib) {
    require_once $qr_lib_path;
}

// Clear any accidental output from include
ob_clean();

try {
    checkLogin();
    checkRole(['koperasi', 'admin']);

    $user = getUserData();

    if($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid Request Method");
    }

    mysqli_begin_transaction($conn);
    
    // Validate inputs
    $no_surat_jalan = $_POST['no_surat_jalan'] ?? '';
    $tanggal_kirim = $_POST['tanggal_kirim'] ?? '';
    $kantor_id = $_POST['kantor_id'] ?? '';
    $pengirim_id = $_POST['pengirim_id'] ?? '';
    $keterangan = $_POST['keterangan'] ?? '';
    $request_id = !empty($_POST['request_id']) ? (int)$_POST['request_id'] : NULL;
    
    if (empty($no_surat_jalan)) throw new Exception("No Surat Jalan tidak boleh kosong");
    if (empty($tanggal_kirim)) throw new Exception("Tanggal kirim tidak boleh kosong");
    if (empty($kantor_id)) throw new Exception("Kantor tujuan harus dipilih");

    // Generate unique QR code
    $qr_code = 'QR-' . time() . '-' . rand(1000, 9999);
    
    // Prepare QR data JSON
    $qr_data_array = [
        'qr_code' => $qr_code,
        'no_surat_jalan' => $no_surat_jalan,
        'kantor_id' => $kantor_id,
        'tanggal_kirim' => $tanggal_kirim,
        'items' => []
    ];
    
    // Validate items
    if(!isset($_POST['items']) || !is_array($_POST['items']) || count($_POST['items']) == 0) {
        throw new Exception("Detail barang tidak boleh kosong");
    }
    
    // Check stok untuk setiap item
    foreach($_POST['items'] as $item) {
        if(empty($item['produk_id'])) continue;
        $produk_id = (int)$item['produk_id'];
        $qty_kirim = (float)$item['qty_kirim'];
        
        // Check stok tersedia
        $query_stok = "SELECT COALESCE(SUM(qty_available), 0) as stok_tersedia 
                        FROM gudang_stok 
                        WHERE produk_id = '$produk_id' AND kondisi = 'baik'";
        $result_stok = mysqli_query($conn, $query_stok);
        $stok_data = mysqli_fetch_assoc($result_stok);
        
        if($qty_kirim > ($stok_data['stok_tersedia'] ?? 0)) {
            // Get produk name for error message
            $query_produk = "SELECT nama_produk FROM produk WHERE id = '$produk_id'";
            $result_produk = mysqli_query($conn, $query_produk);
            $produk_data = mysqli_fetch_assoc($result_produk);
            $nama_p = $produk_data['nama_produk'] ?? "ID $produk_id";
            
            throw new Exception("Stok tidak cukup untuk produk: $nama_p. Tersedia: " . ($stok_data['stok_tersedia'] ?? 0) . ", Diminta: $qty_kirim");
        }
        
        // Add to QR data
        $qr_data_array['items'][] = [
            'produk_id' => $produk_id,
            'qty' => $qty_kirim
        ];
    }
    
    $qr_data_json = json_encode($qr_data_array);
    
    // Insert distribusi header
    $query_header = "INSERT INTO distribusi (
        no_surat_jalan, qr_code, qr_data, tanggal_kirim, request_id, 
        kantor_id, pengirim_id, keterangan, status
    ) VALUES (
        '" . db_escape($no_surat_jalan) . "', '$qr_code', '" . db_escape($qr_data_json) . "', '$tanggal_kirim', 
        " . ($request_id ? "'$request_id'" : "NULL") . ", 
        '$kantor_id', '$pengirim_id', '" . db_escape($keterangan) . "', 'dikirim'
    )";
    
    if(!mysqli_query($conn, $query_header)) {
        throw new Exception("Gagal menyimpan distribusi: " . mysqli_error($conn));
    }
    
    $distribusi_id = mysqli_insert_id($conn);
    
    // Insert detail items
    foreach($_POST['items'] as $item) {
        if(empty($item['produk_id'])) continue;
        $produk_id = (int)$item['produk_id'];
        $qty_request = isset($item['qty_request']) ? (float)$item['qty_request'] : 0;
        $qty_kirim = (float)$item['qty_kirim'];
        $keterangan_item = $item['keterangan'] ?? '';
        
        // 1. Reserve Stock & Calculate FIFO HPP
        // We do this first to get the HPP value
        $hpp_satuan = reserveStock_local($conn, $produk_id, $qty_kirim);
        
        // 2. Get Sell Price Snapshot (use harga_jual_2 for margin calculation)
        $prod_info = db_get_row("SELECT harga_jual_2 FROM produk WHERE id = '$produk_id'");
        $harga_jual = (float)($prod_info['harga_jual_2'] ?? 0);
        
        // 3. Calculate margin
        $margin_per_unit = $harga_jual - $hpp_satuan;

        $query_detail = "INSERT INTO distribusi_detail (
            distribusi_id, produk_id, qty_request, qty_kirim, keterangan, hpp, harga_jual, margin_per_unit
        ) VALUES (
            '$distribusi_id', '$produk_id', '$qty_request', '$qty_kirim', '" . db_escape($keterangan_item) . "', '$hpp_satuan', '$harga_jual', '$margin_per_unit'
        )";
        
        if(!mysqli_query($conn, $query_detail)) {
            throw new Exception("Gagal menyimpan detail distribusi: " . mysqli_error($conn));
        }
        
        // Catat kartu stok as reserved
        $query_kartu = "INSERT INTO kartu_stok (
            produk_id, tanggal, jenis_transaksi, referensi_tipe, referensi_id, 
            qty, saldo_awal, saldo_akhir, keterangan, user_id
        ) SELECT 
            '$produk_id',
            NOW(),
            'keluar',
            'distribusi_reserved',
            '$distribusi_id',
            '$qty_kirim',
            COALESCE((SELECT saldo_akhir FROM kartu_stok WHERE produk_id = '$produk_id' ORDER BY id DESC LIMIT 1), 0),
            COALESCE((SELECT saldo_akhir FROM kartu_stok WHERE produk_id = '$produk_id' ORDER BY id DESC LIMIT 1), 0),
            '" . db_escape("Reserved untuk distribusi $no_surat_jalan") . "',
            '{$user['id']}'
        ";
        
        mysqli_query($conn, $query_kartu);
    }
    
    if($request_id) {
        db_query("UPDATE request SET status = 'selesai' WHERE id = '$request_id'");
    }
    
    // Generate QR
    $qr_dir = '../../assets/uploads/qr/';
    if(!is_dir($qr_dir)) mkdir($qr_dir, 0777, true);
    
    $qr_filename = 'qr_' . $distribusi_id . '_' . time() . '.png';
    $qr_path = $qr_dir . $qr_filename;
    
    if ($has_qr_lib) {
        QRcode::png($qr_code, $qr_path, QR_ECLEVEL_L, 5, 2);
    }
    
    db_query("UPDATE distribusi SET qr_code = '$qr_code' WHERE id = '$distribusi_id'");
    logActivity($conn, $user['id'], "Membuat distribusi baru: $no_surat_jalan", 'distribusi', $distribusi_id);
    
    // Piutang Logic
    $total_value = 0;
    foreach($_POST['items'] as $item) {
        $p_id = (int)$item['produk_id'];
        $q_k = (float)$item['qty_kirim'];
        $d_p = db_get_row("SELECT harga_estimasi FROM produk WHERE id = '$p_id'");
        $total_value += ($q_k * (float)($d_p['harga_estimasi'] ?? 0));
    }

    if ($total_value > 0) {
        $jatuh_tempo = date('Y-m-d', strtotime($tanggal_kirim . ' + 30 days'));
        $query_piutang = "INSERT INTO piutang (tanggal, jatuh_tempo, tipe_referensi, referensi_id, no_referensi, kantor_id, total_piutang, total_bayar, status, keterangan) 
                        VALUES ('$tanggal_kirim', '$jatuh_tempo', 'distribusi', '$distribusi_id', '$no_surat_jalan', '$kantor_id', '$total_value', 0, 'belum_lunas', " . db_escape("Piutang Distribusi $no_surat_jalan") . ")";
        db_query($query_piutang);
    }
    
    // Update margin summary for this date
    require_once '../../helpers/MarginHelper.php';
    MarginHelper::updateDailyMarginSummary(date('Y-m-d', strtotime($tanggal_kirim)));
    
    mysqli_commit($conn);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Distribusi berhasil dibuat',
        'distribusi_id' => $distribusi_id,
        'qr_code' => $qr_code,
        'qr_image' => $qr_filename
    ]);
    
} catch(Exception $e) {
    if (isset($conn)) mysqli_rollback($conn);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

function reserveStock_local($conn, $produk_id, $qty) {
    $rows = db_get_all("SELECT id, qty_available, harga_beli FROM gudang_stok WHERE produk_id = '$produk_id' AND kondisi = 'baik' AND qty_available > 0 ORDER BY tanggal_expired ASC, batch_number ASC");
    $rem = $qty;
    $total_cogs = 0;
    
    foreach($rows as $stok) {
        if($rem <= 0) break;
        $take = min($rem, $stok['qty_available']);
        
        $total_cogs += ($take * (float)$stok['harga_beli']);
        
        db_query("UPDATE gudang_stok SET qty_reserved = qty_reserved + $take WHERE id = '{$stok['id']}'");
        $rem -= $take;
    }
    if($rem > 0) throw new Exception("Stok tidak mencukupi untuk ID: $produk_id");
    
    // Return unit HPP
    return ($qty > 0) ? ($total_cogs / $qty) : 0;
}
?>
