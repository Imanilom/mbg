<?php
// modules/gudang/ajax_stok.php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();

$request = $_REQUEST;

$columns = [
    0 => 'p.kode_produk',
    1 => 'p.nama_produk',
    2 => 'k.nama_kategori',
    3 => 'total_stok',
    4 => 's.nama_satuan',
    5 => 'nilai_aset',
    6 => 'status_stok',
    7 => 'aksi'
];

// Base query aggregation per product
$sql_base = "SELECT 
                p.id, p.kode_produk, p.nama_produk, p.harga_estimasi, p.stok_minimum,
                k.nama_kategori, s.nama_satuan,
                COALESCE(SUM(gs.qty_available), 0) as total_stok,
                (COALESCE(SUM(gs.qty_available), 0) * p.harga_estimasi) as nilai_aset
             FROM produk p
             LEFT JOIN gudang_stok gs ON p.id = gs.produk_id
             LEFT JOIN kategori_produk k ON p.kategori_id = k.id
             LEFT JOIN satuan s ON p.satuan_id = s.id
             WHERE 1=1";

if (!empty($request['kategori'])) {
    $kategori = db_escape($request['kategori']);
    $sql_base .= " AND p.kategori_id = '$kategori'";
}

if (!empty($request['search']['value'])) {
    $search = db_escape($request['search']['value']);
    $sql_base .= " AND (p.nama_produk LIKE '%$search%' OR p.kode_produk LIKE '%$search%')";
}

// Group by product needed before HAVING clause for status filter
$sql_group = " GROUP BY p.id";

// Handle Status Filter (needs HAVING because it uses aggregated data)
$having_clause = "";
if (!empty($request['status'])) {
    if ($request['status'] == 'low') {
        $having_clause = " HAVING total_stok <= p.min_stock";
    } elseif ($request['status'] == 'safe') {
        $having_clause = " HAVING total_stok > p.min_stock";
    }
}

// Count total filtered
$count_query = "SELECT COUNT(*) as total FROM (SELECT p.id " . substr($sql_base, strpos($sql_base, "FROM")) . $sql_group . $having_clause . ") as temp";
$totalData = db_get_row($count_query)['total'];
$totalFiltered = $totalData; // For now assuming simple count

// Ordering
$order = " ORDER BY p.nama_produk ASC";
if (isset($request['order'][0]['column'])) {
    $col_idx = $request['order'][0]['column'];
    $col_dir = $request['order'][0]['dir'];
    if (isset($columns[$col_idx])) {
        // Adjust ordering for calculated columns
        if ($columns[$col_idx] == 'total_stok' || $columns[$col_idx] == 'nilai_aset') {
            $order = " ORDER BY " . $columns[$col_idx] . " " . $col_dir;
        } else {
            $order = " ORDER BY " . $columns[$col_idx] . " " . $col_dir;
        }
    }
}

// Limit
$limit = "";
if ($request['length'] != -1) {
    $limit = " LIMIT " . intval($request['start']) . ", " . intval($request['length']);
}

// Execute Final Query
$query = $sql_base . $sql_group . $having_clause . $order . $limit;
$data_raw = db_get_all($query);

$data = [];
foreach ($data_raw as $row) {
    // Determine Status Badge
    $status_badge = '<span class="badge bg-success">Aman</span>';
    if ($row['total_stok'] <= $row['min_stock']) {
        $status_badge = '<span class="badge bg-warning">Menipis</span>';
    }
    if ($row['total_stok'] == 0) {
        $status_badge = '<span class="badge bg-danger">Habis</span>';
    }

    $nestedData = [];
    $nestedData['kode_produk'] = $row['kode_produk'];
    $nestedData['nama_produk'] = $row['nama_produk'];
    $nestedData['nama_kategori'] = $row['nama_kategori'];
    $nestedData['total_stok'] = number_format($row['total_stok'], 2);
    $nestedData['nama_satuan'] = $row['nama_satuan'];
    $nestedData['nilai_aset'] = format_rupiah($row['nilai_aset']);
    $nestedData['status_stok'] = $status_badge;
    
    $actionBtn = '
    <div class="btn-group">
        <a href="detail.php?id='.$row['id'].'" class="btn btn-sm btn-info" title="Detail Kartu Stok">
            <i class="align-middle" data-feather="file-text"></i>
        </a>
    </div>';
    
    $nestedData['aksi'] = $actionBtn;
    
    $data[] = $nestedData;
}

echo json_encode([
    "draw" => intval($request['draw']),
    "recordsTotal" => intval($totalData), // Should be total count without filter
    "recordsFiltered" => intval($totalFiltered),
    "data" => $data
]);
?>
