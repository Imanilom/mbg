<?php
require_once '../../helpers/ajax.php';
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

ajax_check_unexpected_output();
require_login();
require_role(['admin']);

// DataTables Server-Side
$draw = $_POST['draw'] ?? 1;
$start = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 10;
$search = $_POST['search']['value'] ?? '';

$where = [];
if (!empty($search)) {
    $where[] = "(p.kode_produk LIKE '%{$search}%' OR p.nama_produk LIKE '%{$search}%' OR sup.nama_supplier LIKE '%{$search}%')";
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total records
$total_data = db_get_row("SELECT COUNT(DISTINCT p.id) as total FROM produk p")['total'];

// Get filtered data using view
$query = "SELECT * FROM v_supplier_termurah {$where_sql} LIMIT {$start}, {$length}";
$results = db_get_all($query);

// Count filtered
$count_query = "SELECT COUNT(*) as total FROM v_supplier_termurah {$where_sql}";
$total_filtered = db_get_row($count_query)['total'];

$data = [];
foreach ($results as $row) {
    $data[] = [
        'kode_produk' => $row['kode_produk'],
        'nama_produk' => $row['nama_produk'],
        'nama_supplier' => $row['nama_supplier'] ?? '-',
        'harga_termurah' => format_rupiah($row['harga_termurah']),
        'harga_rata_rata' => format_rupiah($row['harga_rata_rata']),
        'jumlah_pembelian' => $row['jumlah_pembelian'],
        'pembelian_terakhir' => $row['pembelian_terakhir'] ? format_tanggal($row['pembelian_terakhir']) : '-'
    ];
}

echo json_encode([
    'draw' => intval($draw),
    'recordsTotal' => $total_data,
    'recordsFiltered' => $total_filtered,
    'data' => $data
]);
