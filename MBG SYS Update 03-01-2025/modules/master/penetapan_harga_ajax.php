<?php
require_once '../../helpers/ajax.php';
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

ajax_check_unexpected_output();

require_login();
require_role(['admin', 'gudang']);

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$current_role = $_SESSION['user']['role'];

// DataTables Server-Side
if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($action)) {
    $draw = $_POST['draw'] ?? 1;
    $start = $_POST['start'] ?? 0;
    $length = $_POST['length'] ?? 10;
    $search = $_POST['search']['value'] ?? '';
    
    $filter_jenis = $_POST['jenis'] ?? '';
    $filter_kategori = $_POST['kategori'] ?? '';
    
    $where = [];
    if (!empty($search)) {
        $where[] = "(p.kode_produk LIKE '%{$search}%' OR p.nama_produk LIKE '%{$search}%')";
    }
    if (!empty($filter_jenis)) {
        $where[] = "p.jenis_barang_id = " . intval($filter_jenis);
    }
    if (!empty($filter_kategori)) {
        $where[] = "p.kategori_id = " . intval($filter_kategori);
    }
    
    $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $total_data = db_get_row("SELECT COUNT(*) as total FROM produk")['total'];
    $total_filtered = db_get_row("SELECT COUNT(*) as total FROM produk p {$where_sql}")['total'];
    
    $query = "SELECT p.*, jb.nama_jenis, k.nama_kategori, s.nama_satuan 
              FROM produk p 
              INNER JOIN jenis_barang jb ON p.jenis_barang_id = jb.id 
              INNER JOIN kategori k ON p.kategori_id = k.id 
              INNER JOIN satuan s ON p.satuan_id = s.id 
              {$where_sql} 
              ORDER BY p.kode_produk ASC 
              LIMIT {$start}, {$length}";
    
    $produks = db_get_all($query);
    
    $data = [];
    $no = $start + 1;
    
    foreach ($produks as $p) {
        // Get scraped price (latest from harga_pasar)
        $scraped = db_get_row("
            SELECT hp.harga_terendah, hp.nama_pasar, hp.scraped_at 
            FROM harga_pasar hp 
            WHERE hp.produk_id = {$p['id']} 
            ORDER BY hp.tahun DESC, hp.bulan DESC 
            LIMIT 1
        ");
        
        $harga_scraping = $scraped ? format_rupiah($scraped['harga_terendah']) : '-';
        
        $aksi = "
            <button class='btn btn-sm btn-primary' onclick='editHarga({$p['id']})' title='Set Harga'>
                <i class='fas fa-edit'></i>
            </button>
        ";
        
        $data[] = [
            'no' => $no++,
            'kode_produk' => $p['kode_produk'],
            'nama_produk' => $p['nama_produk'],
            'jenis' => $p['nama_jenis'],
            'kategori' => $p['nama_kategori'],
            'satuan' => $p['nama_satuan'],
            'harga_beli' => format_rupiah($p['harga_beli']),
            'harga_jual_1' => format_rupiah($p['harga_jual_1']),
            'harga_jual_2' => format_rupiah($p['harga_jual_2']),
            'harga_jual_3' => $harga_scraping,
            'aksi' => $aksi
        ];
    }
    
    echo json_encode([
        'draw' => intval($draw),
        'recordsTotal' => $total_data,
        'recordsFiltered' => $total_filtered,
        'data' => $data
    ]);
    exit;
}

// Get single product with prices
if ($action == 'get') {
    $id = intval($_POST['id']);
    
    $produk = db_get_row("
        SELECT p.*, jb.nama_jenis, k.nama_kategori, s.nama_satuan 
        FROM produk p 
        INNER JOIN jenis_barang jb ON p.jenis_barang_id = jb.id 
        INNER JOIN kategori k ON p.kategori_id = k.id 
        INNER JOIN satuan s ON p.satuan_id = s.id 
        WHERE p.id = {$id}
    ");
    
    if ($produk) {
        // Get latest scraped price
        $scraped = db_get_row("
            SELECT hp.harga_terendah, hp.nama_pasar, hp.scraped_at 
            FROM harga_pasar hp 
            WHERE hp.produk_id = {$id} 
            ORDER BY hp.tahun DESC, hp.bulan DESC 
            LIMIT 1
        ");
        
        $produk['harga_scraping'] = $scraped ? $scraped['harga_terendah'] : 0;
        $produk['pasar_scraping'] = $scraped ? $scraped['nama_pasar'] : '-';
        $produk['scraping_date'] = $scraped ? date('d/m/Y H:i', strtotime($scraped['scraped_at'])) : '-';
        
        echo json_encode(['success' => true, 'data' => $produk]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Produk tidak ditemukan']);
    }
    exit;
}

// Save prices
if ($action == 'save') {
    $id = intval($_POST['id'] ?? 0);
    $harga_jual_1 = floatval($_POST['harga_jual_1'] ?? 0);
    $harga_jual_2 = floatval($_POST['harga_jual_2'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID produk tidak valid']);
        exit;
    }
    
    // Check permissions
    $data = [];
    
    if ($current_role == 'gudang') {
        // Staf inventori can only edit harga_jual_1
        $data['harga_jual_1'] = $harga_jual_1;
    } elseif ($current_role == 'admin') {
        // Admin can edit both
        $data['harga_jual_1'] = $harga_jual_1;
        $data['harga_jual_2'] = $harga_jual_2;
    }
    
    if (empty($data)) {
        echo json_encode(['success' => false, 'message' => 'Tidak ada harga yang dapat diupdate']);
        exit;
    }
    
    $result = db_update('produk', $data, "id = {$id}");
    
    if ($result) {
        // Log activity
        $produk = db_get_row("SELECT nama_produk FROM produk WHERE id = {$id}");
        if ($produk) {
            log_activity("Update harga produk: {$produk['nama_produk']}", 'harga', $id);
        }
        
        echo json_encode(['success' => true, 'message' => 'Harga berhasil diupdate']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal mengupdate harga']);
    }
    exit;
}
