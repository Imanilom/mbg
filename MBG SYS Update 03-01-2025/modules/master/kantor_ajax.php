<?php
require_once '../../helpers/ajax.php';
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

ajax_check_unexpected_output();

require_login();
require_role(['admin']);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// DataTables Server-Side
if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($action)) {
    $draw = $_POST['draw'] ?? 1;
    $start = $_POST['start'] ?? 0;
    $length = $_POST['length'] ?? 10;
    $search = $_POST['search']['value'] ?? '';
    
    $where = [];
    if (!empty($search)) {
        $where[] = "(kode_kantor LIKE '%{$search}%' OR nama_kantor LIKE '%{$search}%' OR alamat LIKE '%{$search}%')";
    }
    
    $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $total_data = db_get_row("SELECT COUNT(*) as total FROM kantor")['total'];
    $total_filtered = db_get_row("SELECT COUNT(*) as total FROM kantor {$where_sql}")['total'];
    
    $query = "SELECT * FROM kantor {$where_sql} ORDER BY kode_kantor ASC LIMIT {$start}, {$length}";
    $kantors = db_get_all($query);
    
    $data = [];
    $no = $start + 1;
    
    foreach ($kantors as $k) {
        $status_badge = get_status_badge($k['status'], 'aktif');
        $pic = $k['pic_name'] ? $k['pic_name'] . '<br><small>' . $k['pic_phone'] . '</small>' : '-';
        
        $aksi = "
            <button class='btn btn-sm btn-warning' onclick='editKantor({$k['id']})' title='Edit'>
                <i class='fas fa-edit'></i>
            </button>
            <button class='btn btn-sm btn-danger' onclick='deleteKantor({$k['id']})' title='Hapus'>
                <i class='fas fa-trash'></i>
            </button>
        ";
        
        $data[] = [
            'no' => $no++,
            'kode_kantor' => $k['kode_kantor'],
            'nama_kantor' => $k['nama_kantor'],
            'alamat' => $k['alamat'] ?: '-',
            'no_telp' => $k['no_telp'] ?: '-',
            'pic' => $pic,
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
    $last = db_get_row("SELECT kode_kantor FROM kantor ORDER BY id DESC LIMIT 1");
    
    if ($last) {
        $num = intval(substr($last['kode_kantor'], 3)) + 1;
    } else {
        $num = 1;
    }
    
    $code = 'KTR' . str_pad($num, 3, '0', STR_PAD_LEFT);
    
    echo json_encode(['code' => $code]);
    exit;
}

// Get single
if ($action == 'get') {
    $id = intval($_POST['id']);
    $kantor = db_get_row("SELECT * FROM kantor WHERE id = {$id}");
    
    if ($kantor) {
        echo json_encode(['success' => true, 'data' => $kantor]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Kantor tidak ditemukan']);
    }
    exit;
}

// Save
if ($action == 'save') {
    $id = intval($_POST['id'] ?? 0);
    $kode_kantor = clean_input($_POST['kode_kantor']);
    $nama_kantor = clean_input($_POST['nama_kantor']);
    $alamat = clean_input($_POST['alamat']);
    $no_telp = clean_input($_POST['no_telp']);
    $pic_name = clean_input($_POST['pic_name']);
    $pic_phone = clean_input($_POST['pic_phone']);
    $status = clean_input($_POST['status']);
    
    if (empty($kode_kantor) || empty($nama_kantor)) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
        exit;
    }
    
    // Check duplicate code
    $check_query = "SELECT id FROM kantor WHERE kode_kantor = '" . db_escape($kode_kantor) . "'";
    if ($id > 0) {
        $check_query .= " AND id != {$id}";
    }
    $existing = db_get_row($check_query);
    
    if ($existing) {
        echo json_encode(['success' => false, 'message' => 'Kode kantor sudah digunakan']);
        exit;
    }
    
    $data = [
        'kode_kantor' => $kode_kantor,
        'nama_kantor' => $nama_kantor,
        'alamat' => $alamat,
        'no_telp' => $no_telp,
        'pic_name' => $pic_name,
        'pic_phone' => $pic_phone,
        'status' => $status
    ];
    
    if ($id > 0) {
        $result = db_update('kantor', $data, "id = {$id}");
        $message = 'Kantor berhasil diupdate';
    } else {
        $result = db_insert('kantor', $data);
        $message = 'Kantor berhasil ditambahkan';
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
    $check = db_get_row("SELECT COUNT(*) as total FROM users WHERE kantor_id = {$id}");
    if ($check['total'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Kantor tidak dapat dihapus karena masih digunakan']);
        exit;
    }
    
    $result = db_delete('kantor', "id = {$id}");
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Kantor berhasil dihapus']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus kantor']);
    }
    exit;
}