<?php
require_once '../../helpers/ajax.php';
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

ajax_check_unexpected_output();

require_login();
require_role(['admin']);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// DataTables Server-Side Processing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($action)) {
    $draw = $_POST['draw'] ?? 1;
    $start = $_POST['start'] ?? 0;
    $length = $_POST['length'] ?? 10;
    $search = $_POST['search']['value'] ?? '';
    $order_column = $_POST['order'][0]['column'] ?? 1;
    $order_dir = $_POST['order'][0]['dir'] ?? 'asc';
    
    $filter_role = $_POST['role'] ?? '';
    $filter_status = $_POST['status'] ?? '';
    
    $columns = ['id', 'username', 'nama_lengkap', 'email', 'role', 'kantor_id', 'status'];
    $order_by = $columns[$order_column] ?? 'username';
    
    // Count total records
    $total_query = "SELECT COUNT(*) as total FROM users";
    $total_data = db_get_row($total_query)['total'];
    
    // Build query with filters
    $where = [];
    if (!empty($search)) {
        $where[] = "(username LIKE '%{$search}%' OR nama_lengkap LIKE '%{$search}%' OR email LIKE '%{$search}%')";
    }
    if (!empty($filter_role)) {
        $where[] = "role = '" . db_escape($filter_role) . "'";
    }
    if (!empty($filter_status)) {
        $where[] = "status = '" . db_escape($filter_status) . "'";
    }
    
    $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Count filtered records
    $filtered_query = "SELECT COUNT(*) as total FROM users {$where_sql}";
    $total_filtered = db_get_row($filtered_query)['total'];
    
    // Get data
    $query = "SELECT u.*, k.nama_kantor 
              FROM users u 
              LEFT JOIN kantor k ON u.kantor_id = k.id 
              {$where_sql}
              ORDER BY {$order_by} {$order_dir} 
              LIMIT {$start}, {$length}";
    
    $users = db_get_all($query);
    
    $data = [];
    $no = $start + 1;
    
    foreach ($users as $user) {
        $role_badge = get_role_badge($user['role']);
        $status_badge = get_status_badge($user['status'], 'aktif');
        
        $aksi = "
            <button class='btn btn-sm btn-warning' onclick='editUser({$user['id']})' title='Edit'>
                <i class='fas fa-edit'></i>
            </button>
            <button class='btn btn-sm btn-" . ($user['status'] == 'aktif' ? 'secondary' : 'success') . "' 
                    onclick='toggleStatus({$user['id']}, \"{$user['status']}\")' 
                    title='" . ($user['status'] == 'aktif' ? 'Nonaktifkan' : 'Aktifkan') . "'>
                <i class='fas fa-power-off'></i>
            </button>
            <button class='btn btn-sm btn-danger' onclick='deleteUser({$user['id']})' title='Hapus'>
                <i class='fas fa-trash'></i>
            </button>
        ";
        
        $data[] = [
            'no' => $no++,
            'username' => $user['username'],
            'nama_lengkap' => $user['nama_lengkap'],
            'email' => $user['email'] ?: '-',
            'role' => "<span class='badge bg-{$role_badge}'>" . get_role_label($user['role']) . "</span>",
            'kantor' => $user['nama_kantor'] ?: '-',
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

// Get single user
if ($action == 'get') {
    $id = intval($_POST['id']);
    $user = db_get_row("SELECT * FROM users WHERE id = {$id}");
    
    if ($user) {
        echo json_encode(['success' => true, 'data' => $user]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']);
    }
    exit;
}

// Save user (insert/update)
if ($action == 'save') {
    $id = intval($_POST['id'] ?? 0);
    $username = clean_input($_POST['username']);
    $password = $_POST['password'] ?? '';
    $nama_lengkap = clean_input($_POST['nama_lengkap']);
    $email = clean_input($_POST['email']);
    $no_hp = clean_input($_POST['no_hp']);
    $role = clean_input($_POST['role']);
    $kantor_id = !empty($_POST['kantor_id']) ? intval($_POST['kantor_id']) : null;
    $status = clean_input($_POST['status']);
    
    // Validation
    if (empty($username) || empty($nama_lengkap) || empty($role)) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
        exit;
    }
    
    // Check duplicate username
    $check_query = "SELECT id FROM users WHERE username = '" . db_escape($username) . "'";
    if ($id > 0) {
        $check_query .= " AND id != {$id}";
    }
    $existing = db_get_row($check_query);
    
    if ($existing) {
        echo json_encode(['success' => false, 'message' => 'Username sudah digunakan']);
        exit;
    }
    
    // Handle foto upload
    $foto = null;
    if (!empty($_FILES['foto']['name'])) {
        $upload = upload_file($_FILES['foto'], 'users', ['jpg', 'jpeg', 'png']);
        if ($upload['success']) {
            $foto = $upload['file_path'];
        }
    }
    
    $data = [
        'username' => $username,
        'nama_lengkap' => $nama_lengkap,
        'email' => $email,
        'no_hp' => $no_hp,
        'role' => $role,
        'kantor_id' => $kantor_id,
        'status' => $status
    ];
    
    if (!empty($password)) {
        $data['password'] = password_hash($password, PASSWORD_DEFAULT);
    }
    
    if ($foto) {
        $data['foto'] = $foto;
    }
    
    if ($id > 0) {
        // Update
        $result = db_update('users', $data, "id = {$id}");
        $message = 'User berhasil diupdate';
    } else {
        // Insert
        if (empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Password harus diisi']);
            exit;
        }
        $result = db_insert('users', $data);
        $message = 'User berhasil ditambahkan';
    }
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data']);
    }
    exit;
}

// Delete user
if ($action == 'delete') {
    $id = intval($_POST['id']);
    
    // Prevent delete own account
    if ($id == get_user_data('id')) {
        echo json_encode(['success' => false, 'message' => 'Tidak dapat menghapus akun sendiri']);
        exit;
    }
    
    $result = db_delete('users', "id = {$id}");
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'User berhasil dihapus']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus user']);
    }
    exit;
}

// Toggle status
if ($action == 'toggle_status') {
    $id = intval($_POST['id']);
    $status = clean_input($_POST['status']);
    
    $result = db_update('users', ['status' => $status], "id = {$id}");
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Status berhasil diubah']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal mengubah status']);
    }
    exit;
}