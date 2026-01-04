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
    
    $where = [];
    if (!empty($search)) {
        $where[] = "(kode_resep LIKE '%{$search}%' OR nama_resep LIKE '%{$search}%')";
    }
    
    $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $total_data = db_get_row("SELECT COUNT(*) as total FROM resep")['total'];
    $total_filtered = db_get_row("SELECT COUNT(*) as total FROM resep {$where_sql}")['total'];
    
    $query = "SELECT * FROM resep {$where_sql} 
              ORDER BY kode_resep ASC 
              LIMIT {$start}, {$length}";
    
    $reseps = db_get_all($query);
    
    $data = [];
    $no = $start + 1;
    
    foreach ($reseps as $r) {
        $status_badge = $r['status'] == 'aktif' 
            ? '<span class="badge bg-success">Aktif</span>' 
            : '<span class="badge bg-danger">Non-aktif</span>';
        
        $aksi = "
            <button class='btn btn-sm btn-info' onclick='editResep({$r['id']})' title='Edit'>
                <i class='fas fa-edit'></i>
            </button>
            <button class='btn btn-sm btn-danger' onclick='deleteResep({$r['id']})' title='Hapus'>
                <i class='fas fa-trash'></i>
            </button>
        ";
        
        $data[] = [
            'no' => $no++,
            'kode_resep' => $r['kode_resep'],
            'nama_resep' => $r['nama_resep'],
            'porsi_standar' => $r['porsi_standar'],
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
    $prefix = 'RSP';
    $last = db_get_row("SELECT kode_resep FROM resep WHERE kode_resep LIKE '{$prefix}-%' ORDER BY id DESC LIMIT 1");
    
    if ($last) {
        $num = intval(substr($last['kode_resep'], strlen($prefix) + 1)) + 1;
    } else {
        $num = 1;
    }
    
    $code = $prefix . '-' . str_pad($num, 3, '0', STR_PAD_LEFT);
    echo json_encode(['code' => $code]);
    exit;
}

// Get single resep with details
if ($action == 'get') {
    $id = intval($_POST['id']);
    $resep = db_get_row("SELECT * FROM resep WHERE id = {$id}");
    
    if ($resep) {
        $details = db_get_all("SELECT rd.*, p.nama_produk, s.nama_satuan 
                               FROM resep_detail rd 
                               JOIN produk p ON rd.produk_id = p.id 
                               JOIN satuan s ON p.satuan_id = s.id 
                               WHERE rd.resep_id = {$id}");
        $resep['details'] = $details;
        echo json_encode(['success' => true, 'data' => $resep]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Resep tidak ditemukan']);
    }
    exit;
}

// Save
if ($action == 'save') {
    $id = intval($_POST['id'] ?? 0);
    $kode_resep = clean_input($_POST['kode_resep']);
    $nama_resep = clean_input($_POST['nama_resep']);
    $deskripsi = clean_input($_POST['deskripsi'] ?? '');
    $porsi_standar = intval($_POST['porsi_standar'] ?? 1);
    $status = clean_input($_POST['status'] ?? 'aktif');
    
    if (empty($kode_resep) || empty($nama_resep)) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
        exit;
    }
    
    $data = [
        'kode_resep' => $kode_resep,
        'nama_resep' => $nama_resep,
        'deskripsi' => $deskripsi,
        'porsi_standar' => $porsi_standar,
        'status' => $status
    ];
    
    mysqli_begin_transaction($conn);
    try {
        if ($id > 0) {
            db_update('resep', $data, "id = {$id}");
            $resep_id = $id;
            // Clear old details
            db_delete('resep_detail', "resep_id = {$resep_id}");
        } else {
            $resep_id = db_insert('resep', $data);
        }
        
        if (!$resep_id) throw new Exception("Gagal menyimpan resep header");
        
        // Save details
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                if (empty($item['produk_id'])) continue;
                
                $detail_data = [
                    'resep_id' => $resep_id,
                    'produk_id' => intval($item['produk_id']),
                    'gramasi' => floatval($item['gramasi'] ?? 0),
                    'keterangan' => clean_input($item['keterangan'] ?? '')
                ];
                db_insert('resep_detail', $detail_data);
            }
        }
        
        mysqli_commit($conn);
        echo json_encode(['success' => true, 'message' => 'Resep berhasil disimpan']);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Delete
if ($action == 'delete') {
    $id = intval($_POST['id']);
    
    // Check if used in menu
    $check = db_get_row("SELECT COUNT(*) as total FROM menu_harian_detail WHERE resep_id = {$id}");
    if ($check['total'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Resep tidak dapat dihapus karena sudah digunakan di menu']);
        exit;
    }
    
    $result = db_delete('resep', "id = {$id}");
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Resep berhasil dihapus']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus resep']);
    }
    exit;
}
?>
