<?php
require_once '../../helpers/ajax.php';
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

ajax_check_unexpected_output();

checkLogin();
$user = getUserData();

// Build query
$where = ["1=1"];

// Status filter
if(!empty($_POST['status'])) {
    $status = db_escape($_POST['status']);
    $where[] = "d.status = '$status'";
}

// Date filter (tanggal_kirim)
if(!empty($_POST['start_date'])) {
    $start_date = db_escape($_POST['start_date']);
    $where[] = "d.tanggal_kirim >= '$start_date'";
}

if(!empty($_POST['end_date'])) {
    $end_date = db_escape($_POST['end_date']);
    $where[] = "d.tanggal_kirim <= '$end_date'";
}

// Kantor filter
if(!empty($_POST['kantor_id'])) {
    $kantor_id = db_escape($_POST['kantor_id']);
    $where[] = "d.kantor_id = '$kantor_id'";
}

// Role-based filtering
if($user['role'] == 'kantor') {
    // Kantor can only see distributions sent to them
    $where[] = "d.kantor_id = '{$user['kantor_id']}'";
}

$where_clause = implode(" AND ", $where);

// Columns for sorting
$columns = [
    0 => 'd.id',
    1 => 'd.no_surat_jalan',
    2 => 'd.tanggal_kirim',
    3 => 'k.nama_kantor',
    4 => 'u.nama_lengkap',
    5 => 'd.status'
];

// Order
$order_by = "d.created_at DESC";
if(isset($_POST['order'])) {
    $col_index = $_POST['order'][0]['column'];
    $direction = $_POST['order'][0]['dir'];
    if(isset($columns[$col_index])) {
        $order_by = $columns[$col_index] . " " . $direction;
    }
}

// Pagination
$limit = "";
if(isset($_POST['length']) && $_POST['length'] != -1) {
    $start = (int)$_POST['start'];
    $length = (int)$_POST['length'];
    $limit = "LIMIT $start, $length";
}

// Main Query
$query = "SELECT d.*, k.nama_kantor, u.nama_lengkap as pengirim
          FROM distribusi d
          INNER JOIN kantor k ON d.kantor_id = k.id
          INNER JOIN users u ON d.pengirim_id = u.id
          WHERE $where_clause
          ORDER BY $order_by
          $limit";

$result = mysqli_query($conn, $query);

// Count total filtered
$query_filtered = "SELECT COUNT(*) as total 
                   FROM distribusi d
                   INNER JOIN kantor k ON d.kantor_id = k.id
                   INNER JOIN users u ON d.pengirim_id = u.id
                   WHERE $where_clause";
$filtered_res = db_get_row($query_filtered);
$total_filtered = $filtered_res['total'];

// Count total all
$query_all = "SELECT COUNT(*) as total FROM distribusi d";
if($user['role'] == 'kantor') {
    $query_all .= " WHERE d.kantor_id = '{$user['kantor_id']}'";
}
$all_res = db_get_row($query_all);
$total_all = $all_res['total'];

$data = [];
$no = $_POST['start'] + 1;

while($row = mysqli_fetch_assoc($result)) {
    $nestedData = [];
    
    $nestedData[] = $no++;
    
    // No Surat Jalan
    $link = '<a href="detail.php?id=' . $row['id'] . '"><strong>' . $row['no_surat_jalan'] . '</strong></a>';
    $nestedData[] = $link;
    
    // Tanggal Kirim
    $nestedData[] = format_tanggal($row['tanggal_kirim']);
    
    // Kantor Tujuan / Pengirim (Depends on view logic, but request asked for both in list.php columns)
    // list.php cols: No, No SJ, Tgl Kirim, Kantor Tujuan, Pengirim, Status, Aksi
    $nestedData[] = $row['nama_kantor'];
    $nestedData[] = $row['pengirim'];
    
    // Status
    $nestedData[] = get_status_badge($row['status'], 'distribusi');
    
    // Aksi
    $aksi = '<div class="btn-group">';
    $aksi .= '<a href="detail.php?id=' . $row['id'] . '" class="btn btn-sm btn-info" title="Detail"><i class="fas fa-eye"></i></a>';
    
    // Print button
    $aksi .= '<a href="print.php?id=' . $row['id'] . '" target="_blank" class="btn btn-sm btn-default" title="Print"><i class="fas fa-print"></i></a>';
    
    // Delete only for admin/koperasi/gudang if status is dikirim (or whatever logic applies)
    if(in_array($user['role'], ['admin', 'koperasi', 'gudang'])) {
        $aksi .= '<button type="button" class="btn btn-sm btn-danger" onclick="deleteData(' . $row['id'] . ')" title="Hapus"><i class="fas fa-trash"></i></button>';
    }
    
    $aksi .= '</div>';
    
    $nestedData[] = $aksi;
    
    $data[] = $nestedData;
}

$json_data = [
    "draw" => intval($_POST['draw']),
    "recordsTotal" => intval($total_all),
    "recordsFiltered" => intval($total_filtered),
    "data" => $data
];

echo json_encode($json_data);
?>
