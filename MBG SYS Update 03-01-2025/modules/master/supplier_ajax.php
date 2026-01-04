<?php
require_once '../../helpers/ajax.php';
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

ajax_check_unexpected_output();

require_login();
require_role(['admin', 'koperasi']);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// DataTables Server-Side
if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($action)) {
    $draw = $_POST['draw'] ?? 1;
    $start = $_POST['start'] ?? 0;
    $length = $_POST['length'] ?? 10;
    $search = $_POST['search']['value'] ?? '';
    
    $where = [];
    if (!empty($search)) {
        $where[] = "(kode_supplier LIKE '%{$search}%' OR nama_supplier LIKE '%{$search}%' OR alamat LIKE '%{$search}%')";
    }
    
    $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $total_data = db_get_row("SELECT COUNT(*) as total FROM supplier")['total'];
    $total_filtered = db_get_row("SELECT COUNT(*) as total FROM supplier {$where_sql}")['total'];
    
    $query = "SELECT * FROM supplier {$where_sql} ORDER BY kode_supplier ASC LIMIT {$start}, {$length}";
    $suppliers = db_get_all($query);
    
    $data = [];
    $no = $start + 1;
    
    foreach ($suppliers as $s) {
        $status_badge = get_status_badge($s['status'], 'aktif');
        $pic = $s['pic_name'] ? $s['pic_name'] . '<br><small>' . $s['pic_phone'] . '</small>' : '-';
        
        $aksi = "
            <button class='btn btn-sm btn-warning' onclick='editSupplier({$s['id']})'>
                <i class='fas fa-edit'></i>
            </button>
            <button class='btn btn-sm btn-danger' onclick='deleteSupplier({$s['id']})'>
                <i class='fas fa-trash'></i>
            </button>
        ";
        
        $data[] = [
            'no' => $no++,
            'kode_supplier' => $s['kode_supplier'],
            'nama_supplier' => $s['nama_supplier'],
            'alamat' => $s['alamat'] ?: '-',
            'no_telp' => $s['no_telp'] ?: '-',
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
    $last = db_get_row("SELECT kode_supplier FROM supplier ORDER BY id DESC LIMIT 1");
    
    if ($last) {
        $num = intval(substr($last['kode_supplier'], 4)) + 1;
    } else {
        $num = 1;
    }
    
    $code = 'SUP-' . str_pad($num, 3, '0', STR_PAD_LEFT);
    
    echo json_encode(['code' => $code]);
    exit;
}

// Get single
if ($action == 'get') {
    $id = intval($_POST['id']);
    $supplier = db_get_row("SELECT * FROM supplier WHERE id = {$id}");
    
    if ($supplier) {
        echo json_encode(['success' => true, 'data' => $supplier]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Supplier tidak ditemukan']);
    }
    exit;
}

// Save
if ($action == 'save') {
    $id = intval($_POST['id'] ?? 0);
    $kode_supplier = clean_input($_POST['kode_supplier']);
    $nama_supplier = clean_input($_POST['nama_supplier']);
    $alamat = clean_input($_POST['alamat']);
    $no_telp = clean_input($_POST['no_telp']);
    $email = clean_input($_POST['email']);
    $pic_name = clean_input($_POST['pic_name']);
    $pic_phone = clean_input($_POST['pic_phone']);
    $keterangan = clean_input($_POST['keterangan']);
    $status = clean_input($_POST['status']);
    
    if (empty($kode_supplier) || empty($nama_supplier)) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
        exit;
    }
    
    $data = [
        'kode_supplier' => $kode_supplier,
        'nama_supplier' => $nama_supplier,
        'alamat' => $alamat,
        'no_telp' => $no_telp,
        'email' => $email,
        'pic_name' => $pic_name,
        'pic_phone' => $pic_phone,
        'keterangan' => $keterangan,
        'status' => $status
    ];
    
    if ($id > 0) {
        $result = db_update('supplier', $data, "id = {$id}");
        $message = 'Supplier berhasil diupdate';
    } else {
        $result = db_insert('supplier', $data);
        $message = 'Supplier berhasil ditambahkan';
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
    $check = db_get_row("SELECT COUNT(*) as total FROM pembelanjaan WHERE supplier_id = {$id}");
    if ($check['total'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Supplier tidak dapat dihapus karena masih digunakan']);
        exit;
    }
    
    $result = db_delete('supplier', "id = {$id}");
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Supplier berhasil dihapus']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus supplier']);
    }
    exit;
}