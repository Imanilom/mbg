<?php
require_once '../../helpers/ajax.php';
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

ajax_check_unexpected_output();

try {
    checkLogin();
    checkRole(['koperasi', 'admin']);

    $user = getUserData();

    if($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid Request Method");
    }

    $request_id = $_POST['request_id'] ?? 0;
    $action = $_POST['action'] ?? '';
    $keterangan_approval = $_POST['keterangan_approval'] ?? '';

    if(!$request_id) {
        throw new Exception("ID Request tidak valid");
    }

    if(!in_array($action, ['approve', 'reject', 'tolak'])) {
        throw new Exception("Aksi tidak valid");
    }

    // Normalize action reject/tolak
    if($action == 'reject') $action = 'tolak';

    mysqli_begin_transaction($conn);

    // Get current request
    $query_check = "SELECT * FROM request WHERE id = '" . db_escape($request_id) . "'";
    $result_check = mysqli_query($conn, $query_check);
    
    if(mysqli_num_rows($result_check) == 0) {
        throw new Exception("Request tidak ditemukan");
    }
    
    $current_request = mysqli_fetch_assoc($result_check);
    
    if($current_request['status'] != 'pending') {
        throw new Exception("Request tidak dapat diproses karena status bukan pending");
    }

    $status = ($action == 'approve') ? 'diproses' : 'ditolak';
    $approved_at = date('Y-m-d H:i:s');
    
    // Update header
    $query_update = "UPDATE request SET 
                    status = '$status',
                    approved_by = '" . $user['id'] . "',
                    approved_at = '$approved_at',
                    keterangan_approval = '" . db_escape($keterangan_approval) . "'
                    WHERE id = '" . db_escape($request_id) . "'";

    if(!mysqli_query($conn, $query_update)) {
        throw new Exception("Gagal mengupdate status request: " . mysqli_error($conn));
    }

    // Update details (qty_approved) if approved
    if($action == 'approve' && isset($_POST['items']) && is_array($_POST['items'])) {
        foreach($_POST['items'] as $item) {
            $detail_id = $item['detail_id'] ?? 0;
            $qty_approved = $item['qty_approved'] ?? 0;
            
            if(!$detail_id) continue;

            $query_detail = "UPDATE request_detail SET 
                            qty_approved = '" . db_escape($qty_approved) . "'
                            WHERE id = '" . db_escape($detail_id) . "' AND request_id = '" . db_escape($request_id) . "'";
            
            if(!mysqli_query($conn, $query_detail)) {
                throw new Exception("Gagal mengupdate detail request: " . mysqli_error($conn));
            }
        }
    }

    // Log activity
    $log_action = ($action == 'approve') ? "Menyetujui request" : "Menolak request";
    logActivity($conn, $user['id'], "$log_action: " . $current_request['no_request'], 'request', $request_id);

    mysqli_commit($conn);

    echo json_encode([
        'status' => 'success',
        'message' => 'Request berhasil ' . ($action == 'approve' ? 'disetujui' : 'ditolak'),
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
