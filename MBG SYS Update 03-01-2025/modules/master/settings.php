<?php
// modules/master/settings.php
$page_title = 'Pengaturan Sistem';
require_once '../../helpers/constants.php';
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

require_login();
require_role(['admin']); // Only admin can access settings

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_settings'])) {
    
    $app_name = clean_input($_POST['app_name']);
    $company_name = clean_input($_POST['company_name']);
    $company_address = clean_input($_POST['company_address']);
    $company_phone = clean_input($_POST['company_phone']);
    
    // Check if table 'settings' exists
    $check_table = db_query("SHOW TABLES LIKE 'settings'");
    if (mysqli_num_rows($check_table) == 0) {
        db_query("CREATE TABLE `settings` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `setting_key` varchar(50) NOT NULL,
            `setting_value` text,
            PRIMARY KEY (`id`),
            UNIQUE KEY `setting_key` (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // Function to update or insert setting
    function update_setting($key, $value) {
        $check = db_get_row("SELECT id FROM settings WHERE setting_key = '$key'");
        if ($check) {
            db_update('settings', ['setting_value' => $value], "setting_key = '$key'");
        } else {
            db_insert('settings', ['setting_key' => $key, 'setting_value' => $value]);
        }
    }

    update_setting('app_name', $app_name);
    update_setting('company_name', $company_name);
    update_setting('company_address', $company_address);
    update_setting('company_phone', $company_phone);

    set_flash('success', 'Pengaturan berhasil disimpan');
    header("Location: settings.php");
    exit;
}

// Get current settings
$check_table_exist = db_query("SHOW TABLES LIKE 'settings'");
$settings = [];
if (mysqli_num_rows($check_table_exist) > 0) {
    $rows = db_get_all("SELECT * FROM settings");
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Defaults
$val_app_name = $settings['app_name'] ?? 'MBG System';
$val_company_name = $settings['company_name'] ?? 'Baraja Coffee';
$val_company_addr = $settings['company_address'] ?? 'Jl. Example No. 123';
$val_company_phone = $settings['company_phone'] ?? '08123456789';

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/dashboard/index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Pengaturan</li>
    </ol>
</nav>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-cog me-2 text-primary"></i> Pengaturan Umum Aplikasi</h5>
            </div>
            <div class="card-body p-4">
                
                <?php show_flash(); ?>

                <form method="POST" action="">
                    <input type="hidden" name="save_settings" value="1">
                    
                    <h6 class="text-uppercase text-muted fw-bold mb-3" style="font-size: 0.75rem; letter-spacing: 1px;">Identitas Aplikasi</h6>
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Aplikasi</label>
                        <input type="text" class="form-control" name="app_name" value="<?= $val_app_name ?>" required>
                    </div>

                    <hr class="my-4 border-light">

                    <h6 class="text-uppercase text-muted fw-bold mb-3" style="font-size: 0.75rem; letter-spacing: 1px;">Info Perusahaan (Untuk Kop Surat/Laporan)</h6>

                    <div class="mb-3">
                        <label class="form-label">Nama Perusahaan / Instansi</label>
                        <input type="text" class="form-control" name="company_name" value="<?= $val_company_name ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Alamat Lengkap</label>
                        <textarea class="form-control" name="company_address" rows="3"><?= $val_company_addr ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nomor Telepon / Kontak</label>
                        <input type="text" class="form-control" name="company_phone" value="<?= $val_company_phone ?>">
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-2"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
