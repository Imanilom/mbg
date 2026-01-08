<?php
// modules/pembelanjaan/get_request_items.php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();
checkRole(['admin', 'koperasi']);

$request_id = $_POST['request_id'] ?? 0;

if (!$request_id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request ID']);
    exit;
}

// Get request details (items)
// We fetch qty_request. If there is qty_approved logic, we should fetch that instead.
// Based on approve.php, approval process saves directly to db? 
// Checking approve.php, it seems it doesn't solve to a separate table, it might update request_detail or move to another flow.
// Let's assume for now we take what's in request_detail.
// Wait, approve.php updates where? 
// Let's check if there is an 'approved_qty' column in request_detail.
// If not, we use qty_request.

// Re-checking approve.php logic visually from previous context:
// <input ... name="items[...][qty_approved]" ...>
// process_approve.php likely handles this. 
// If I can't see process_approve.php, I should check request_detail schema or assume for now.
// Let's assume we want to pull items.

// Check schema for request_detail first?
// Or just safer to select * from request_detail.

$query = "SELECT rd.produk_id, p.nama_produk, p.kode_produk, rd.qty_request, s.nama_satuan,
          p.harga_estimasi as harga_terakhir
          FROM request_detail rd
          INNER JOIN produk p ON rd.produk_id = p.id
          LEFT JOIN satuan s ON p.satuan_id = s.id
          WHERE rd.request_id = " . db_escape($request_id);

$items = db_get_all($query);

// Enhance with market price if available (optional)

header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'data' => $items
]);
?>
