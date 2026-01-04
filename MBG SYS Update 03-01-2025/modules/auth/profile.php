<?php
// modules/auth/profile.php
require_once '../../helpers/constants.php';
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

require_login();

$page_title = 'Profile Saya';
$user_id = get_user_data('id');

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $nama_lengkap = clean_input($_POST['nama_lengkap']);
    $email = clean_input($_POST['email']);
    
    $data = [
        'nama_lengkap' => $nama_lengkap,
        'email' => $email
    ];
    
    if (db_update('users', $data, "id = $user_id")) {
        set_flash('success', 'Profile berhasil diupdate');
        header("Location: profile.php");
        exit;
    } else {
        set_flash('error', 'Gagal update profile');
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $user = db_get_row("SELECT password FROM users WHERE id = $user_id");
    
    if (!password_verify($old_password, $user['password'])) {
        set_flash('error', 'Password lama tidak sesuai');
    } elseif ($new_password !== $confirm_password) {
        set_flash('error', 'Konfirmasi password tidak cocok');
    } elseif (strlen($new_password) < 6) {
        set_flash('error', 'Password minimal 6 karakter');
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        if (db_update('users', ['password' => $hashed], "id = $user_id")) {
            set_flash('success', 'Password berhasil diubah');
            header("Location: profile.php");
            exit;
        } else {
            set_flash('error', 'Gagal mengubah password');
        }
    }
}

// Get user data
$user = db_get_row("SELECT * FROM users WHERE id = $user_id");

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/dashboard/index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Profile</li>
    </ol>
</nav>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-body text-center p-4">
                <div class="mb-3">
                    <?php if ($user['foto']): ?>
                        <img src="<?= BASE_URL ?>/assets/uploads/<?= $user['foto'] ?>" 
                             class="rounded-circle" width="120" height="120" style="object-fit: cover;">
                    <?php else: ?>
                        <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center" 
                             style="width: 120px; height: 120px; font-size: 3rem; font-weight: bold;">
                            <?= strtoupper(substr($user['nama_lengkap'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <h5 class="mb-1 fw-bold"><?= $user['nama_lengkap'] ?></h5>
                <p class="text-muted small mb-3"><?= get_role_label() ?></p>
                <div class="d-grid gap-2">
                    <span class="badge bg-primary bg-opacity-10 text-primary py-2">
                        <i class="fas fa-envelope me-2"></i><?= $user['email'] ?>
                    </span>
                    <span class="badge bg-success bg-opacity-10 text-success py-2">
                        <i class="fas fa-user me-2"></i><?= $user['username'] ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <?php show_flash(); ?>
        
        <!-- Update Profile -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold"><i class="fas fa-user-edit me-2 text-primary"></i> Edit Profile</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" value="<?= $user['username'] ?>" disabled>
                        <small class="text-muted">Username tidak dapat diubah</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_lengkap" 
                               value="<?= $user['nama_lengkap'] ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" 
                               value="<?= $user['email'] ?>">
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-2"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Change Password -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold"><i class="fas fa-lock me-2 text-warning"></i> Ubah Password</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="">
                    <input type="hidden" name="change_password" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label">Password Lama <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="old_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password Baru <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="new_password" required>
                        <small class="text-muted">Minimal 6 karakter</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Konfirmasi Password Baru <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="confirm_password" required>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-warning px-4">
                            <i class="fas fa-key me-2"></i> Ubah Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
