<?php
require_once '../../helpers/ajax.php';
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

ajax_check_unexpected_output();

checkLogin();

$query = "SELECT gs.id, gs.produk_id, gs.qty_available, gs.batch_number, 
                 p.kode_produk, p.nama_produk, 
                 CONCAT(p.kode_produk, ' - ', p.nama_produk) as produk_label 
          FROM gudang_stok gs
          JOIN produk p ON gs.produk_id = p.id
          WHERE gs.qty_stok > 0
          ORDER BY p.nama_produk ASC, gs.tanggal_expired ASC";

$data = db_get_all($query);

echo json_encode($data);
?>
