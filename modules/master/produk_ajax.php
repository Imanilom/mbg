<?php
require_once '../../helpers/ajax.php';
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

ajax_check_unexpected_output();

require_login();
require_role(['admin', 'koperasi', 'gudang']);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// DataTables Server-Side
if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($action)) {
    $draw = $_POST['draw'] ?? 1;
    $start = $_POST['start'] ?? 0;
    $length = $_POST['length'] ?? 10;
    $search = $_POST['search']['value'] ?? '';
    
    $filter_jenis = $_POST['jenis'] ?? '';
    $filter_kategori = $_POST['kategori'] ?? '';
    $filter_status = $_POST['status'] ?? '';
    
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
    if (!empty($filter_status)) {
        $where[] = "p.status_produk = '" . db_escape($filter_status) . "'";
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
        $gambar = $p['gambar'] 
            ? "<img src='" . BASE_URL . "/assets/uploads/{$p['gambar']}' class='img-thumbnail' width='50'>" 
            : "<img src='" . BASE_URL . "/assets/img/no-image.png' class='img-thumbnail' width='50'>";
        
        $tipe_badges = [
            'stok' => '<span class="badge bg-primary">Stok</span>',
            'distribusi' => '<span class="badge bg-info">Distribusi</span>',
            'khusus' => '<span class="badge bg-warning">Khusus</span>'
        ];
        
        $status_badges = [
            'persiapan' => '<span class="badge bg-secondary">Persiapan</span>',
            'running' => '<span class="badge bg-success">Running</span>',
            'nonaktif' => '<span class="badge bg-danger">Non-aktif</span>'
        ];
        
        $aksi = "
            <button class='btn btn-sm btn-info' onclick='viewDetail({$p['id']})' title='Detail'>
                <i class='fas fa-eye'></i>
            </button>
            <button class='btn btn-sm btn-warning' onclick='editProduk({$p['id']})' title='Edit'>
                <i class='fas fa-edit'></i>
            </button>
            <button class='btn btn-sm btn-danger' onclick='deleteProduk({$p['id']})' title='Hapus'>
                <i class='fas fa-trash'></i>
            </button>
        ";
        
        
        // Get scraped price (latest from harga_pasar)
        $scraped = db_get_row("
            SELECT hp.harga_terendah 
            FROM harga_pasar hp 
            WHERE hp.produk_id = {$p['id']} 
            ORDER BY hp.tahun DESC, hp.bulan DESC 
            LIMIT 1
        ");
        
        $data[] = [
            'no' => $no++,
            'gambar' => $gambar,
            'kode_produk' => $p['kode_produk'],
            'nama_produk' => $p['nama_produk'],
            'jenis' => $p['nama_jenis'],
            'kategori' => $p['nama_kategori'],
            'satuan' => $p['nama_satuan'],
            'harga_beli' => format_rupiah($p['harga_beli']),
            'harga_jual_1' => format_rupiah($p['harga_jual_1']),
            'harga_jual_2' => format_rupiah($p['harga_jual_2']),
            'harga_jual_3' => $scraped ? format_rupiah($scraped['harga_terendah']) : '-',
            'tipe_item' => $tipe_badges[$p['tipe_item']] ?? $p['tipe_item'],
            'status' => $status_badges[$p['status_produk']] ?? $p['status_produk'],
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

// Get kategori by jenis
if ($action == 'get_kategori') {
    $jenis_id = intval($_POST['jenis_id']);
    $kategori = db_get_all("SELECT id, nama_kategori FROM kategori WHERE jenis_barang_id = {$jenis_id} AND status = 'aktif' ORDER BY nama_kategori");
    echo json_encode($kategori);
    exit;
}

// Generate code
if ($action == 'generate_code') {
    $jenis_id = intval($_POST['jenis_id']);
    $jenis = db_get_row("SELECT kode_jenis FROM jenis_barang WHERE id = {$jenis_id}");
    
    if ($jenis) {
        $prefix = $jenis['kode_jenis'];
        $last = db_get_row("SELECT kode_produk FROM produk WHERE kode_produk LIKE '{$prefix}-%' ORDER BY id DESC LIMIT 1");
        
        if ($last) {
            $num = intval(substr($last['kode_produk'], strlen($prefix) + 1)) + 1;
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

// Get single
if ($action == 'get') {
    $id = intval($_POST['id']);
    $produk = db_get_row("SELECT * FROM produk WHERE id = {$id}");
    
    if ($produk) {
        echo json_encode(['success' => true, 'data' => $produk]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Produk tidak ditemukan']);
    }
    exit;
}

// Save
if ($action == 'save') {
    $id = intval($_POST['id'] ?? 0);
    $kode_produk = clean_input($_POST['kode_produk']);
    $nama_produk = clean_input($_POST['nama_produk']);
    $jenis_barang_id = intval($_POST['jenis_barang_id']);
    $kategori_id = intval($_POST['kategori_id']);
    $satuan_id = intval($_POST['satuan_id']);
    $tipe_item = clean_input($_POST['tipe_item']);
    $status_produk = clean_input($_POST['status_produk']);
    $harga_estimasi = floatval($_POST['harga_estimasi'] ?? 0);
    $stok_minimum = intval($_POST['stok_minimum'] ?? 0);
    $masa_kadaluarsa_hari = !empty($_POST['masa_kadaluarsa_hari']) ? intval($_POST['masa_kadaluarsa_hari']) : null;
    $spesifikasi = clean_input($_POST['spesifikasi']);
    $deskripsi = clean_input($_POST['deskripsi']);
    $barcode = clean_input($_POST['barcode']);
    
    if (empty($kode_produk) || empty($nama_produk)) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
        exit;
    }
    
    // Check duplicate code
    $check_query = "SELECT id FROM produk WHERE kode_produk = '" . db_escape($kode_produk) . "'";
    if ($id > 0) {
        $check_query .= " AND id != {$id}";
    }
    $existing = db_get_row($check_query);
    
    if ($existing) {
        echo json_encode(['success' => false, 'message' => 'Kode produk sudah digunakan']);
        exit;
    }
    
    // Handle gambar upload
    $gambar = null;
    if (!empty($_FILES['gambar']['name'])) {
        $upload = upload_file($_FILES['gambar'], 'produk', ['jpg', 'jpeg', 'png']);
        if ($upload['success']) {
            $gambar = $upload['file_path'];
        }
    }
    
    $data = [
        'kode_produk' => $kode_produk,
        'nama_produk' => $nama_produk,
        'jenis_barang_id' => $jenis_barang_id,
        'kategori_id' => $kategori_id,
        'satuan_id' => $satuan_id,
        'tipe_item' => $tipe_item,
        'status_produk' => $status_produk,
        'harga_estimasi' => $harga_estimasi,
        'stok_minimum' => $stok_minimum,
        'masa_kadaluarsa_hari' => $masa_kadaluarsa_hari,
        'spesifikasi' => $spesifikasi,
        'deskripsi' => $deskripsi,
        'barcode' => $barcode
    ];
    
    // Add price fields if submitted (admin only)
    if (isset($_POST['harga_beli'])) {
        $data['harga_beli'] = floatval($_POST['harga_beli'] ?? 0);
    }
    if (isset($_POST['harga_jual_1'])) {
        $data['harga_jual_1'] = floatval($_POST['harga_jual_1'] ?? 0);
    }
    if (isset($_POST['harga_jual_2'])) {
        $data['harga_jual_2'] = floatval($_POST['harga_jual_2'] ?? 0);
    }

    
    if ($gambar) {
        $data['gambar'] = $gambar;
    }
    
    if ($id > 0) {
        $result = db_update('produk', $data, "id = {$id}");
        $message = 'Produk berhasil diupdate';
    } else {
        $result = db_insert('produk', $data);
        $message = 'Produk berhasil ditambahkan';
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
    $check_stok = db_get_row("SELECT COUNT(*) as total FROM gudang_stok WHERE produk_id = {$id}");
    if ($check_stok['total'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Produk tidak dapat dihapus karena ada data stok']);
        exit;
    }
    
    $result = db_delete('produk', "id = {$id}");
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Produk berhasil dihapus']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus produk']);
    }
    exit;
}

// Import Excel
if ($action == 'import') {
    require_once '../../vendor/autoload.php'; // PhpSpreadsheet
    
    if (empty($_FILES['file_excel']['name'])) {
        echo json_encode(['success' => false, 'message' => 'File tidak ditemukan']);
        exit;
    }
    
    $file = $_FILES['file_excel']['tmp_name'];
    
    try {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        
        $imported = 0;
        $failed = 0;
        
        // Skip header row
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            
            // Map columns: Kode, Nama, Jenis ID, Kategori ID, Satuan ID, Tipe, Status, Harga, Stok Min
            $data = [
                'kode_produk' => $row[0],
                'nama_produk' => $row[1],
                'jenis_barang_id' => intval($row[2]),
                'kategori_id' => intval($row[3]),
                'satuan_id' => intval($row[4]),
                'tipe_item' => $row[5],
                'status_produk' => $row[6],
                'harga_estimasi' => floatval($row[7]),
                'stok_minimum' => intval($row[8])
            ];
            
            if (db_insert('produk', $data)) {
                $imported++;
            } else {
                $failed++;
            }
        }
        
        echo json_encode([
            'success' => true, 
            'message' => "Berhasil import {$imported} produk, gagal {$failed}"
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    
    exit;
}