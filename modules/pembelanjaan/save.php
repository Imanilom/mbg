<?php
require_once '../../helpers/ajax.php';
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

ajax_check_unexpected_output();

try {
    checkLogin();
    checkRole(['admin', 'koperasi']);

    $action = $_POST['action'] ?? '';

    if ($action !== 'save_pembelanjaan') {
        throw new Exception("Invalid Action");
    }

    $no_pembelanjaan = $_POST['no_pembelanjaan'] ?? '';
    $tanggal = $_POST['tanggal'] ?? '';
    $periode_type = $_POST['periode_type'] ?? '';
    $periode_value = $_POST['periode_value'] ?? '';
    $supplier_id = !empty($_POST['supplier_id']) ? $_POST['supplier_id'] : NULL;
    $keterangan = $_POST['keterangan'] ?? '';
    $total_belanja = $_POST['total_belanja'] ?? 0;
    $user_id = getUserData('id');

    if (empty($no_pembelanjaan)) throw new Exception("No Pembelanjaan tidak boleh kosong");
    if (empty($tanggal)) throw new Exception("Tanggal tidak boleh kosong");

    // Validasi No Pembelanjaan Unik (Added quotes)
    $check = db_get_row("SELECT id FROM pembelanjaan WHERE no_pembelanjaan = '" . db_escape($no_pembelanjaan) . "'");
    if ($check) {
        throw new Exception("No Pembelanjaan sudah ada");
    }

    // Upload Bukti (Optional)
    $bukti_belanja = null;
    if (!empty($_FILES['bukti_belanja']['name'])) {
        $upload = upload_file($_FILES['bukti_belanja'], 'pembelanjaan');
        if ($upload['success']) {
            $bukti_belanja = $upload['file_name'];
        } else {
            throw new Exception($upload['message']);
        }
    }

    // Start Transaction
    mysqli_begin_transaction($conn);

    // Insert Header (Added quotes to all string values)
    $sqlHeader = "INSERT INTO pembelanjaan 
        (no_pembelanjaan, tanggal, periode_type, periode_value, supplier_id, total_belanja, bukti_belanja, keterangan, user_id, status)
        VALUES 
        ('" . db_escape($no_pembelanjaan) . "', 
         '" . db_escape($tanggal) . "', 
         '" . db_escape($periode_type) . "', 
         '" . db_escape($periode_value) . "', 
         " . ($supplier_id ? "'" . db_escape($supplier_id) . "'" : "NULL") . ", 
         " . (float)$total_belanja . ", 
         " . ($bukti_belanja ? "'" . db_escape($bukti_belanja) . "'" : "NULL") . ", 
         '" . db_escape($keterangan) . "', 
         '" . db_escape($user_id) . "', 
         'draft')";
    
    if (!db_query($sqlHeader)) {
        throw new Exception("Gagal simpan header pembelanjaan: " . mysqli_error($conn));
    }
    
    $pembelanjaan_id = mysqli_insert_id($conn);

    // Insert Details
    if (isset($_POST['produk_id']) && is_array($_POST['produk_id'])) {
        foreach ($_POST['produk_id'] as $key => $prod_id) {
            if(empty($prod_id)) continue;

            $qty = (float)($_POST['qty'][$key] ?? 0);
            $harga = (float)($_POST['harga_satuan'][$key] ?? 0);

            // Added quotes to produk_id just in case
            $sqlDetail = "INSERT INTO pembelanjaan_detail 
                (pembelanjaan_id, produk_id, qty, harga_satuan)
                VALUES 
                ('$pembelanjaan_id', '" . db_escape($prod_id) . "', '$qty', '$harga')";
            
            if (!db_query($sqlDetail)) {
                 throw new Exception("Gagal simpan detail item");
            }
        }
    }

    mysqli_commit($conn);
    echo json_encode(['status' => 'success', 'id' => $pembelanjaan_id]);

} catch (Exception $e) {
    if (isset($conn)) mysqli_rollback($conn);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
