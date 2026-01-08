<?php
// modules/pembelanjaan/get_pending_requests.php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();
checkRole(['admin', 'koperasi']);

// Fetch requests with status 'diproses' (approved)
// We assume 'diproses' means approved and ready for purchase/distribution
$query = "SELECT r.id, r.no_request, r.tanggal_request, r.keperluan, r.status, k.nama_kantor 
          FROM request r
          INNER JOIN kantor k ON r.kantor_id = k.id
          WHERE r.status IN ('diproses', 'pending')
          ORDER BY r.tanggal_request ASC, r.id ASC";

$requests = db_get_all($query);

header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'data' => $requests
]);
?>
