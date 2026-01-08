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
    
    $id = $_POST['id'] ?? 0;
    if(!$id) throw new Exception("ID Request tidak valid");

    // Cek status request, hanya boleh edit jika pending
    $current_check = db_get_row("SELECT * FROM request WHERE id = " . db_escape($id));
    if(!$current_check) throw new Exception("Request tidak ditemukan");
    if($current_check['status'] != 'pending') throw new Exception("Request tidak dapat diedit karena status bukan pending");
    
    // Validasi kepemilikan jika kantor
    if($user['role'] == 'kantor' && $current_check['kantor_id'] != $user['kantor_id']) {
        throw new Exception("Anda tidak memiliki akses ke request ini");
    }

    // Update header
    $tanggal_request = $_POST['tanggal_request'] ?? '';
    // $kantor_id = $_POST['kantor_id'] ?? ''; // Kantor tidak bisa diubah saat edit biasanya, atau bisa? Di UI add.php kantor select disabled untuk role kantor.
    // Jika admin, mungkin bisa ubah kantor. Tapi amannya kita ambil dari POST jika ada, atau keep existing.
    // Tapi karena logic add.php role kantor readonly, logic update sebaiknya menghormati itu.
    // Namun untuk simplifikasi dan keamanan data relasi, biasanya kantor tidak diubah saat edit request, tapi bolehlah jika admin.
    
    $kantor_id = isset($_POST['kantor_id']) ? $_POST['kantor_id'] : $current_check['kantor_id'];
    
    $keperluan = $_POST['keperluan'] ?? '';
    $tanggal_butuh = !empty($_POST['tanggal_butuh']) ? $_POST['tanggal_butuh'] : NULL;
    
    $query_header = "UPDATE request SET 
                    tanggal_request = '" . db_escape($tanggal_request) . "',
                    kantor_id = '" . db_escape($kantor_id) . "',
                    keperluan = '" . db_escape($keperluan) . "',
                    tanggal_butuh = " . ($tanggal_butuh ? "'" . db_escape($tanggal_butuh) . "'" : "NULL") . "
                    WHERE id = '" . db_escape($id) . "'";
    
    if(!mysqli_query($conn, $query_header)) {
        throw new Exception("Gagal mengupdate request: " . mysqli_error($conn));
    }
    
    // Update detail: Delete all and re-insert
    $query_delete = "DELETE FROM request_detail WHERE request_id = " . db_escape($id);
    if(!mysqli_query($conn, $query_delete)) {
        throw new Exception("Gagal mereset item request: " . mysqli_error($conn));
    }
    
    if(isset($_POST['items']) && is_array($_POST['items'])) {
        foreach($_POST['items'] as $item) {
            $produk_id = $item['produk_id'] ?? 0;
            $qty_request = $item['qty'] ?? 0;
            $keterangan = $item['keterangan'] ?? '';
            
            if (empty($produk_id)) continue;

            $query_detail = "INSERT INTO request_detail (request_id, produk_id, qty_request, keterangan)
                            VALUES ('$id', '" . db_escape($produk_id) . "', '" . db_escape($qty_request) . "', '" . db_escape($keterangan) . "')";
            
            if(!mysqli_query($conn, $query_detail)) {
                throw new Exception("Gagal menyimpan detail request: " . mysqli_error($conn));
            }
        }
    }
    
    // Log activity
    logActivity($conn, $user['id'], "Mengedit request: " . $current_check['no_request'], 'request', $id);
    
    mysqli_commit($conn);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Request berhasil diperbarui',
        'request_id' => $id
    ]);
    
} catch(Throwable $e) {
    if (isset($conn)) mysqli_rollback($conn);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
