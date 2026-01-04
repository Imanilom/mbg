<?php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

require_login();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// DataTables Server-Side
if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($action)) {
    $draw = $_POST['draw'] ?? 1;
    $start = $_POST['start'] ?? 0;
    $length = $_POST['length'] ?? 10;
    $search = $_POST['search']['value'] ?? '';
    $filter_jenis = $_POST['jenis'] ?? '';
    
    $where = [];
    if (!empty($search)) {
        $where[] = "(k.kode_kategori LIKE '%{$search}%' OR k.nama_kategori LIKE '%{$search}%')";
    }
    if (!empty($filter_jenis)) {
        $where[] = "k.jenis_barang_id = " . intval($filter_jenis);
    }
    
    $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $total_data = db_get_row("SELECT COUNT(*) as total FROM kategori")['total'];
    $total_filtered = db_get_row("SELECT COUNT(*) as total FROM kategori k {$where_sql}")['total'];
    
    $query = "SELECT k.*, jb.nama_jenis, p.nama_kategori as parent_name
              FROM kategori k
              INNER JOIN jenis_barang jb ON k.jenis_barang_id = jb.id
              LEFT JOIN kategori p ON k.parent_id = p.id
              {$where_sql}
              ORDER BY k.kode_kategori ASC
              LIMIT {$start}, {$length}";
    
    $kategoris = db_get_all($query);
    
    $data = [];
    $no = $start + 1;
    
    foreach ($kategoris as $k) {
        $status_badge = get_status_badge($k['status'], 'aktif');
        $parent = $k['parent_name'] ?: '<em class="text-muted">Root</em>';
        
        $aksi = "
            <button class='btn btn-sm btn-warning' onclick='editKategori({$k['id']})'>
                <i class='fas fa-edit'></i>
            </button>
            <button class='btn btn-sm btn-danger' onclick='deleteKategori({$k['id']})'>
                <i class='fas fa-trash'></i>
            </button>
        ";
        
        $data[] = [
            'no' => $no++,
            'kode_kategori' => $k['kode_kategori'],
            'nama_kategori' => $k['nama_kategori'],
            'jenis' => $k['nama_jenis'],
            'parent' => $parent,
            'status' => $status_badge,
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

// Generate code
if ($action == 'generate_code') {
    $jenis_id = intval($_POST['jenis_id']);
    $jenis = db_get_row("SELECT kode_jenis FROM jenis_barang WHERE id = {$jenis_id}");
    
    if ($jenis) {
        $prefix = $jenis['kode_jenis'];
        $last = db_get_row("SELECT kode_kategori FROM kategori WHERE kode_kategori LIKE '{$prefix}-%' ORDER BY id DESC LIMIT 1");
        
        if ($last) {
            $num = intval(substr($last['kode_kategori'], strlen($prefix) + 1)) + 1;
        } else {
            $num = 1;
        }
        
        $code = $prefix . '-' . str_pad($num, 3, '0', STR_PAD_LEFT);
        echo json_encode(['code' => $code]);
    } else {
        echo json_encode(['code' => '']);
    }
    exit;
}

// Get parents
if ($action == 'get_parents') {
    $jenis_id = intval($_POST['jenis_id']);
    $parents = db_get_all("SELECT id, nama_kategori FROM kategori WHERE jenis_barang_id = {$jenis_id} AND parent_id IS NULL AND status = 'aktif' ORDER BY nama_kategori");
    echo json_encode($parents);
    exit;
}

// Get single
if ($action == 'get') {
    $id = intval($_POST['id']);
    $kategori = db_get_row("SELECT * FROM kategori WHERE id = {$id}");
    
    if ($kategori) {
        echo json_encode(['success' => true, 'data' => $kategori]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Kategori tidak ditemukan']);
    }
    exit;
}

// Save
if ($action == 'save') {
    $id = intval($_POST['id'] ?? 0);
    $kode_kategori = clean_input($_POST['kode_kategori']);
    $nama_kategori = clean_input($_POST['nama_kategori']);
    $jenis_barang_id = intval($_POST['jenis_barang_id']);
    $parent_id = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
    $deskripsi = clean_input($_POST['deskripsi']);
    $status = clean_input($_POST['status']);
    
    if (empty($kode_kategori) || empty($nama_kategori)) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
        exit;
    }
    
    $data = [
        'kode_kategori' => $kode_kategori,
        'nama_kategori' => $nama_kategori,
        'jenis_barang_id' => $jenis_barang_id,
        'parent_id' => $parent_id,
        'deskripsi' => $deskripsi,
        'status' => $status
    ];
    
    if ($id > 0) {
        $result = db_update('kategori', $data, "id = {$id}");
        $message = 'Kategori berhasil diupdate';
    } else {
        $result = db_insert('kategori', $data);
        $message = 'Kategori berhasil ditambahkan';
    }
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data']);
    }
    exit;
}

// Delete
if ($action == 'delete') {
    $id = intval($_POST['id']);
    
    // Check if used
    $check = db_get_row("SELECT COUNT(*) as total FROM produk WHERE kategori_id = {$id}");
    if ($check['total'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Kategori tidak dapat dihapus karena masih digunakan']);
        exit;
    }
    
    $result = db_delete('kategori', "id = {$id}");
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Kategori berhasil dihapus']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus kategori']);
    }
    exit;
}