<?php
// modules/piutang/payment_save.php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();
checkRole(['admin', 'koperasi']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: list.php");
    exit;
}

$start_trans = db_query("START TRANSACTION");

try {
    $piutang_id = $_POST['piutang_id'];
    $tanggal_bayar = $_POST['tanggal_bayar'];
    $jumlah_bayar = floatval($_POST['jumlah_bayar']);
    $metode_bayar = $_POST['metode_bayar'];
    $keterangan = $_POST['keterangan'];
    $user_id = getUserData('id');

    // Get Current Piutang Data
    $piutang = db_get_row("SELECT * FROM piutang WHERE id = " . db_escape($piutang_id) . " FOR UPDATE");
    
    if (!$piutang) {
        throw new Exception("Data piutang tidak ditemukan.");
    }
    
    if ($jumlah_bayar <= 0) {
        throw new Exception("Jumlah bayar harus lebih dari 0.");
    }
    
    if ($jumlah_bayar > $piutang['sisa_piutang']) {
        throw new Exception("Jumlah bayar melebihi sisa tagihan.");
    }

    // Handle File Upload
    $bukti_bayar = '';
    if (isset($_FILES['bukti_bayar']) && $_FILES['bukti_bayar']['error'] == 0) {
        $bukti_bayar = upload_file($_FILES['bukti_bayar'], 'pembayaran');
    }

    // Insert Payment
    $data_payment = [
        'piutang_id' => $piutang_id,
        'tanggal_bayar' => $tanggal_bayar,
        'jumlah_bayar' => $jumlah_bayar,
        'metode_bayar' => $metode_bayar,
        'bukti_bayar' => $bukti_bayar,
        'keterangan' => $keterangan,
        'user_id' => $user_id
    ];
    
    db_insert('pembayaran_piutang', $data_payment);

    // Update Piutang Status
    $new_paid = $piutang['total_bayar'] + $jumlah_bayar;
    $new_status = 'belum_lunas';
    // Small float epsilon check needed? Using standard comparison for now
    if ($new_paid >= $piutang['total_piutang'] - 0.01) {
        $new_status = 'lunas';
    } elseif ($new_paid > 0) {
        $new_status = 'sebagian';
    }

    $sql_update = "UPDATE piutang SET 
                   total_bayar = '$new_paid', 
                   status = '$new_status' 
                   WHERE id = '$piutang_id'";
                   
    if (!db_query($sql_update)) {
        throw new Exception("Gagal update status piutang.");
    }

    db_query("COMMIT");
    set_flash('success', 'Pembayaran berhasil disimpan.');
    header("Location: detail.php?id=$piutang_id");

} catch (Exception $e) {
    db_query("ROLLBACK");
    set_flash('error', $e->getMessage());
    header("Location: detail.php?id=" . ($_POST['piutang_id'] ?? ''));
}
?>
