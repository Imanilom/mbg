<?php
// modules/pembelanjaan/ajax_list.php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();

// Columns to be used
$columns = [
    0 => 'id',
    1 => 'no_pembelanjaan',
    2 => 'tanggal',
    3 => 'nama_supplier',
    4 => 'periode_type',
    5 => 'total_belanja',
    6 => 'status',
    7 => 'id'
];

// Base query
$sql = "SELECT p.*, s.nama_supplier 
        FROM pembelanjaan p 
        LEFT JOIN supplier s ON p.supplier_id = s.id";

// Filtering
$where = " WHERE 1=1";

if (!empty($_POST['periode_type'])) {
    $where .= " AND p.periode_type = '" . db_escape($_POST['periode_type']) . "'";
}

if (!empty($_POST['supplier_id'])) {
    $where .= " AND p.supplier_id = '" . db_escape($_POST['supplier_id']) . "'";
}

if (!empty($_POST['start_date'])) {
    $where .= " AND p.tanggal >= '" . db_escape($_POST['start_date']) . "'";
}

if (!empty($_POST['end_date'])) {
    $where .= " AND p.tanggal <= '" . db_escape($_POST['end_date']) . "'";
}

// Search
if (!empty($_POST['search']['value'])) {
    $search = db_escape($_POST['search']['value']);
    $where .= " AND (p.no_pembelanjaan LIKE '%$search%' OR s.nama_supplier LIKE '%$search%' OR p.periode_value LIKE '%$search%')";
}

// Count total filtered
$query_count = "SELECT COUNT(*) as total FROM pembelanjaan p LEFT JOIN supplier s ON p.supplier_id = s.id" . $where;
$total_filtered = db_get_row($query_count)['total'];

// Order
if (isset($_POST['order'])) {
    $order_col = $columns[$_POST['order'][0]['column']];
    $order_dir = $_POST['order'][0]['dir'];
    $where .= " ORDER BY $order_col $order_dir";
} else {
    $where .= " ORDER BY p.tanggal DESC";
}

// Limit
if (isset($_POST['length']) && $_POST['length'] != -1) {
    $start = $_POST['start'];
    $length = $_POST['length'];
    $where .= " LIMIT $start, $length";
}

$data_query = $sql . $where;
$result = db_get_all($data_query);

$data = [];
$no = $_POST['start'] + 1;

foreach ($result as $row) {
    // Status Badge
    $status_badge = $row['status'] == 'selesai' 
        ? '<span class="badge bg-success">Selesai</span>' 
        : '<span class="badge bg-warning">Draft</span>';

    // Action Buttons
    $aksi = '
    <div class="btn-group">
        <a href="detail.php?id='.$row['id'].'" class="btn btn-info btn-xs" title="Detail">
            <i class="fas fa-eye"></i>
        </a>
        <a href="edit.php?id='.$row['id'].'" class="btn btn-warning btn-xs" title="Edit">
            <i class="fas fa-edit"></i>
        </a>
        <button type="button" onclick="deleteData('.$row['id'].')" class="btn btn-danger btn-xs" title="Hapus">
            <i class="fas fa-trash"></i>
        </button>
    </div>';

    $nestedData = [];
    $nestedData[] = $no++;
    $nestedData[] = $row['no_pembelanjaan'];
    $nestedData[] = format_tanggal($row['tanggal']);
    $nestedData[] = $row['nama_supplier'] ?? '-';
    $nestedData[] = ucfirst($row['periode_type']) . ' <small class="d-block text-muted">'.$row['periode_value'].'</small>';
    $nestedData[] = format_rupiah($row['total_belanja']);
    $nestedData[] = $status_badge;
    $nestedData[] = $aksi;

    $data[] = $nestedData;
}

// Total records without filter
$total_all = db_get_row("SELECT COUNT(*) as total FROM pembelanjaan")['total'];

$json_data = [
    "draw" => intval($_POST['draw']),
    "recordsTotal" => intval($total_all),
    "recordsFiltered" => intval($total_filtered),
    "data" => $data
];

echo json_encode($json_data);
