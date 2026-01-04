<?php
// modules/piutang/ajax_piutang.php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();

$request = $_REQUEST;

$columns = [
    0 => 'no_referensi',
    1 => 'tanggal',
    2 => 'jatuh_tempo',
    3 => 'k.nama_kantor',
    4 => 'total_piutang',
    5 => 'sisa_piutang',
    6 => 'status',
    7 => 'aksi'
];

$sql = "SELECT p.*, k.nama_kantor 
        FROM piutang p
        LEFT JOIN kantor k ON p.kantor_id = k.id
        WHERE 1=1";

if (!empty($request['search']['value'])) {
    $search = db_escape($request['search']['value']);
    $sql .= " AND (p.no_referensi LIKE '%$search%' OR k.nama_kantor LIKE '%$search%')";
}

// Count
$count_query = "SELECT COUNT(*) as total FROM piutang p WHERE 1=1";
$totalData = db_get_row($count_query)['total'];
$totalFiltered = $totalData;

// Order
$order = " ORDER BY p.tanggal DESC";
if (isset($request['order'][0]['column'])) {
    $col_idx = $request['order'][0]['column'];
    $col_dir = $request['order'][0]['dir'];
    if (isset($columns[$col_idx])) {
        $order = " ORDER BY " . $columns[$col_idx] . " " . $col_dir;
    }
}

// Limit
$limit = "";
if ($request['length'] != -1) {
    $limit = " LIMIT " . intval($request['start']) . ", " . intval($request['length']);
}

$query = $sql . $order . $limit;
$data_raw = db_get_all($query);

$data = [];
foreach ($data_raw as $row) {
    $status_badge = '<span class="badge bg-danger">Belum Lunas</span>';
    if ($row['status'] == 'lunas') $status_badge = '<span class="badge bg-success">Lunas</span>';
    if ($row['status'] == 'sebagian') $status_badge = '<span class="badge bg-warning">Sebagian</span>';

    $nestedData = [];
    $nestedData['no_referensi'] = $row['no_referensi'];
    $nestedData['tanggal'] = format_tanggal($row['tanggal']);
    $nestedData['jatuh_tempo'] = format_tanggal($row['jatuh_tempo']);
    $nestedData['nama_kantor'] = $row['nama_kantor'];
    $nestedData['total_piutang'] = format_rupiah($row['total_piutang']);
    $nestedData['sisa_piutang'] = format_rupiah($row['sisa_piutang']);
    $nestedData['status'] = $status_badge;
    
    $nestedData['aksi'] = '<a href="detail.php?id='.$row['id'].'" class="btn btn-sm btn-primary">Detail / Bayar</a>';
    
    $data[] = $nestedData;
}

echo json_encode([
    "draw" => intval($request['draw']),
    "recordsTotal" => intval($totalData),
    "recordsFiltered" => intval($totalFiltered),
    "data" => $data
]);
?>
