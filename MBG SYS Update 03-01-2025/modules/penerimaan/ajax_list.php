<?php
// modules/penerimaan/ajax_list.php
require_once '../../helpers/ajax.php'; // Include ajax helper for output buffering
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

ajax_check_unexpected_output(); // Clear any previous output

checkLogin();

// Columns
$columns = [
    0 => 'id', // No
    1 => 'no_penerimaan',
    2 => 'tanggal_terima',
    3 => 'nama_supplier',
    4 => 'no_surat_jalan',
    5 => 'nama_lengkap',
    6 => 'status',
    7 => 'id'
];

$sql = "SELECT p.*, s.nama_supplier, u.nama_lengkap 
        FROM penerimaan_barang p 
        LEFT JOIN supplier s ON p.supplier_id = s.id
        LEFT JOIN users u ON p.penerima_id = u.id";

$where = " WHERE 1=1";

// Search
if (!empty($_POST['search']['value'])) {
    $search = db_escape($_POST['search']['value']);
    $where .= " AND (p.no_penerimaan LIKE '%$search%' OR s.nama_supplier LIKE '%$search%' OR p.no_surat_jalan LIKE '%$search%')";
}

// Count Filtered
$query_count = "SELECT COUNT(*) as total FROM penerimaan_barang p LEFT JOIN supplier s ON p.supplier_id = s.id" . $where;
$count_res = db_get_row($query_count);
$total_filtered = $count_res ? $count_res['total'] : 0;

// Order
if (isset($_POST['order'])) {
    $col_idx = $_POST['order'][0]['column'];
    $col = isset($columns[$col_idx]) ? $columns[$col_idx] : 'tanggal_terima';
    $dir = $_POST['order'][0]['dir'];
    $where .= " ORDER BY $col $dir";
} else {
    $where .= " ORDER BY p.tanggal_terima DESC";
}

// Limit
if (isset($_POST['length']) && $_POST['length'] != -1) {
    $start = intval($_POST['start']);
    $len = intval($_POST['length']);
    $where .= " LIMIT $start, $len";
}

$data = db_get_all($sql . $where);
$output = [];
$no = isset($_POST['start']) ? intval($_POST['start']) + 1 : 1;

if ($data) {
    foreach ($data as $row) {
        $status = $row['status'] == 'masuk_gudang' 
            ? '<span class="badge bg-success">Masuk Gudang</span>' 
            : '<span class="badge bg-info">Diterima Koperasi</span>';

        $aksi = '<a href="detail.php?id='.$row['id'].'" class="btn btn-sm btn-info"><i class="fas fa-eye"></i> Detail</a>';

        $output[] = [
            $no++,
            $row['no_penerimaan'],
            format_tanggal($row['tanggal_terima']),
            $row['nama_supplier'] ?? '-',
            $row['no_surat_jalan'] ?? '-',
            $row['nama_lengkap'] ?? '-',
            $status,
            $aksi
        ];
    }
}

// Count Total All
$count_all = db_get_row("SELECT COUNT(*) as total FROM penerimaan_barang");
$total_all = $count_all ? $count_all['total'] : 0;

echo json_encode([
    "draw" => isset($_POST['draw']) ? intval($_POST['draw']) : 0,
    "recordsTotal" => intval($total_all),
    "recordsFiltered" => intval($total_filtered),
    "data" => $output
]);
?>
