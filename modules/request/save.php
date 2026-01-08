<?php
require_once '../../helpers/ajax.php';
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

ajax_check_unexpected_output();

try {
    checkLogin();
    checkRole(['kantor', 'admin']);

    $user = getUserData();

    if($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid Request Method");
    }

    mysqli_begin_transaction($conn);
    
    // Insert header
    $no_request = $_POST['no_request'] ?? '';
    $tanggal_request = $_POST['tanggal_request'] ?? '';
    $kantor_id = $_POST['kantor_id'] ?? '';
    $keperluan = $_POST['keperluan'] ?? '';
    $tanggal_butuh = !empty($_POST['tanggal_butuh']) ? $_POST['tanggal_butuh'] : NULL;
    $user_id = $user['id'];
    $action = $_POST['action'] ?? 'draft'; // submit or draft
    $status = ($action == 'submit') ? 'pending' : 'draft';
    
    if (empty($no_request)) throw new Exception("No Request tidak boleh kosong");

    $query_header = "INSERT INTO request (no_request, tanggal_request, kantor_id, user_id, keperluan, tanggal_butuh, status)
                    VALUES ('" . db_escape($no_request) . "', '" . db_escape($tanggal_request) . "', '" . db_escape($kantor_id) . "', '" . db_escape($user_id) . "', '" . db_escape($keperluan) . "', " . ($tanggal_butuh ? "'" . db_escape($tanggal_butuh) . "'" : "NULL") . ", '$status')";
    
    if(!mysqli_query($conn, $query_header)) {
        throw new Exception("Gagal menyimpan request: " . mysqli_error($conn));
    }
    
    $request_id = mysqli_insert_id($conn);
    
    // Insert detail
    if(isset($_POST['items']) && is_array($_POST['items'])) {
        foreach($_POST['items'] as $item) {
            $produk_id = $item['produk_id'] ?? 0;
            $qty_request = $item['qty'] ?? 0;
            $keterangan = $item['keterangan'] ?? '';
            
            if (empty($produk_id)) continue;

            $query_detail = "INSERT INTO request_detail (request_id, produk_id, qty_request, keterangan)
                            VALUES ('$request_id', '" . db_escape($produk_id) . "', '" . db_escape($qty_request) . "', '" . db_escape($keterangan) . "')";
            
            if(!mysqli_query($conn, $query_detail)) {
                throw new Exception("Gagal menyimpan detail request: " . mysqli_error($conn));
            }
        }
    }
    
    // Log activity
    logActivity($conn, $user['id'], "Membuat request baru: $no_request", 'request', $request_id);
    
    mysqli_commit($conn);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Request berhasil disimpan',
        'request_id' => $request_id
    ]);
    
} catch(Throwable $e) {
    if (isset($conn)) mysqli_rollback($conn);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
