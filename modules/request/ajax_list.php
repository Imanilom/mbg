<?php
// modules/request/ajax_list.php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();
$user = getUserData();

// Build query
$where = ["1=1"];

if(!empty($_POST['status'])) {
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $where[] = "r.status = '$status'";
}

if(!empty($_POST['tanggal_dari'])) {
    $tanggal_dari = mysqli_real_escape_string($conn, $_POST['tanggal_dari']);
    $where[] = "r.tanggal_request >= '$tanggal_dari'";
}

if(!empty($_POST['tanggal_sampai'])) {
    $tanggal_sampai = mysqli_real_escape_string($conn, $_POST['tanggal_sampai']);
    $where[] = "r.tanggal_request <= '$tanggal_sampai'";
}

if(!empty($_POST['kantor'])) {
    $kantor = mysqli_real_escape_string($conn, $_POST['kantor']);
    $where[] = "r.kantor_id = '$kantor'";
}

// Filter by role
if($user['role'] == 'kantor') {
    $where[] = "r.kantor_id = '{$user['kantor_id']}'";
}

$where_clause = implode(" AND ", $where);

$query = "SELECT r.*, k.nama_kantor, u.nama_lengkap as pembuat,
          (SELECT COUNT(*) FROM request_detail WHERE request_id = r.id) as jumlah_item
          FROM request r
          INNER JOIN kantor k ON r.kantor_id = k.id
          INNER JOIN users u ON r.user_id = u.id
          WHERE $where_clause
          ORDER BY r.created_at DESC";

$result = mysqli_query($conn, $query);

if(mysqli_num_rows($result) == 0) {
    echo '<div class="alert alert-info">Tidak ada data request</div>';
    exit;
}

// Status badge colors
function getStatusBadge($status) {
    $badges = [
        'pending' => 'warning',
        'diproses' => 'info',
        'selesai' => 'success',
        'ditolak' => 'danger'
    ];
    $color = $badges[$status] ?? 'secondary';
    $text = ucfirst($status);
    return "<span class='badge badge-{$color}'>{$text}</span>";
}

echo '<div class="row">';

while($row = mysqli_fetch_assoc($result)) {
    $status_badge = getStatusBadge($row['status']);
    $tanggal_request = format_tanggal($row['tanggal_request']);
    $tanggal_butuh = $row['tanggal_butuh'] ? format_tanggal($row['tanggal_butuh']) : '-';
    
    // Tentukan warna card berdasarkan status
    $card_color = [
        'pending' => 'border-warning',
        'diproses' => 'border-info',
        'selesai' => 'border-success',
        'ditolak' => 'border-danger'
    ];
    $border = $card_color[$row['status']] ?? '';
    
    echo '<div class="col-md-6 col-lg-4">';
    echo '<div class="card ' . $border . ' mb-3">';
    echo '<div class="card-header">';
    echo '<h5 class="card-title mb-0">';
    echo '<i class="fas fa-file-alt"></i> ' . $row['no_request'];
    echo '<span class="float-right">' . $status_badge . '</span>';
    echo '</h5>';
    echo '</div>';
    echo '<div class="card-body">';
    echo '<p class="mb-1"><strong>Kantor:</strong> ' . $row['nama_kantor'] . '</p>';
    echo '<p class="mb-1"><strong>Pembuat:</strong> ' . $row['pembuat'] . '</p>';
    echo '<p class="mb-1"><strong>Tanggal Request:</strong> ' . $tanggal_request . '</p>';
    echo '<p class="mb-1"><strong>Tanggal Butuh:</strong> ' . $tanggal_butuh . '</p>';
    echo '<p class="mb-1"><strong>Jumlah Item:</strong> ' . $row['jumlah_item'] . '</p>';
    
    if($row['keperluan']) {
        echo '<p class="mb-1"><strong>Keperluan:</strong><br>' . nl2br(htmlspecialchars($row['keperluan'])) . '</p>';
    }
    
    echo '</div>';
    echo '<div class="card-footer">';
    
    // Action buttons berdasarkan role dan status
    echo '<a href="detail.php?id=' . $row['id'] . '" class="btn btn-sm btn-info">';
    echo '<i class="fas fa-eye"></i> Detail';
    echo '</a>';
    
    // Kantor hanya bisa edit jika status pending
    if($user['role'] == 'kantor' && $row['status'] == 'pending') {
        echo ' <a href="edit.php?id=' . $row['id'] . '" class="btn btn-sm btn-warning">';
        echo '<i class="fas fa-edit"></i> Edit';
        echo '</a>';
        
        echo ' <button class="btn btn-sm btn-danger btn-delete" data-id="' . $row['id'] . '" data-no="' . $row['no_request'] . '">';
        echo '<i class="fas fa-trash"></i> Hapus';
        echo '</button>';
    }
    
    // Koperasi bisa approve jika status pending
    if(($user['role'] == 'koperasi' || $user['role'] == 'admin') && $row['status'] == 'pending') {
        echo ' <a href="approve.php?id=' . $row['id'] . '" class="btn btn-sm btn-success">';
        echo '<i class="fas fa-check"></i> Proses';
        echo '</a>';
    }
    
    // Koperasi bisa buat distribusi jika status diproses
    if(($user['role'] == 'koperasi' || $user['role'] == 'admin') && $row['status'] == 'diproses') {
        echo ' <a href="../distribusi/add.php?request_id=' . $row['id'] . '" class="btn btn-sm btn-primary">';
        echo '<i class="fas fa-truck"></i> Distribusi';
        echo '</a>';
    }
    
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

echo '</div>';
?>