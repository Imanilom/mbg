<?php
// modules/gudang/ajax_opname.php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();

$request = $_REQUEST;

$columns = [
    0 => 'nomor_dokumen',
    1 => 'tanggal',
    2 => 'keterangan',
    3 => 'status',
    4 => 'u.nama_lengkap',
    5 => 'aksi'
];

$sql = "SELECT so.*, u.nama_lengkap as user_name 
        FROM stok_opname so
        LEFT JOIN users u ON so.user_id = u.id
        WHERE 1=1";

if (!empty($request['search']['value'])) {
    $search = db_escape($request['search']['value']);
    $sql .= " AND (so.nomor_dokumen LIKE '%$search%' OR so.keterangan LIKE '%$search%')";
}

// Count
$count_query = "SELECT COUNT(*) as total FROM stok_opname so WHERE 1=1"; // Simplified for speed
$totalData = db_get_row($count_query)['total'];
$totalFiltered = $totalData; // Assuming filter count

// Order
$order = " ORDER BY so.tanggal DESC, so.id DESC";
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
    $nestedData = [];
    $nestedData['nomor_dokumen'] = $row['nomor_dokumen'];
    $nestedData['tanggal'] = format_tanggal($row['tanggal']);
    $nestedData['keterangan'] = $row['keterangan'];
    $nestedData['status'] = $row['status'];
    $nestedData['user_name'] = $row['user_name'];
    
    $btn = '<a href="opname_detail.php?id='.$row['id'].'" class="btn btn-sm btn-info">Detail</a>';
    if ($row['status'] == 'draft') {
        $btn = '<a href="opname_form.php?id='.$row['id'].'" class="btn btn-sm btn-warning">Edit</a>';
    }
    
    $nestedData['aksi'] = $btn;
    $data[] = $nestedData;
}

echo json_encode([
    "draw" => intval($request['draw']),
    "recordsTotal" => intval($totalData),
    "recordsFiltered" => intval($totalFiltered), // Should calculate properly
    "data" => $data
]);
?>
