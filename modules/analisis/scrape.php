<?php
$page_title = 'Scrape Data Harga Pasar';
require_once '../../includes/header.php';

// Check access
if (!in_array($user['role'], ['admin', 'koperasi'])) {
    set_flash_message('error', 'Akses ditolak.');
    header('Location: ' . base_url('modules/dashboard'));
    exit();
}

// Check if scraping is in progress
$lock_file = __DIR__ . '/scraping.lock';
$is_scraping = file_exists($lock_file);

// Get scraping history
$scraping_logs = db_get_all("SELECT * FROM scraping_log ORDER BY created_at DESC LIMIT 10");

require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800"><?= $page_title ?></h1>
            <p class="mb-0">Ambil data harga terbaru dari Kepokmas Cirebon</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Kembali
            </a>
        </div>
    </div>

    <!-- Scraping Status -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Status Scraping</h6>
                    <div class="badge <?= $is_scraping ? 'bg-warning text-dark' : 'bg-success' ?>">
                        <?= $is_scraping ? 'Sedang Berjalan' : 'Siap' ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($is_scraping): ?>
                        <div class="alert alert-warning">
                            <h6 class="alert-heading">
                                <i class="fas fa-sync-alt fa-spin me-2"></i>Scraping sedang berlangsung
                            </h6>
                            <p class="mb-0">Proses pengambilan data sedang berjalan. Silakan tunggu hingga selesai.</p>
                        </div>
                        
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="text-muted">Mengambil data harga pasar...</p>
                            
                            <div class="mt-4">
                                <button id="checkStatus" class="btn btn-primary">
                                    <i class="fas fa-sync me-1"></i> Cek Status
                                </button>
                                <button id="cancelScraping" class="btn btn-danger">
                                    <i class="fas fa-times me-1"></i> Batalkan
                                </button>
                            </div>
                        </div>
                        
                        <script>
                        // Wait for DOM to be ready
                        document.addEventListener('DOMContentLoaded', function() {
                            var checkInterval;
                            var checkStatusBtn = document.getElementById('checkStatus');
                            var cancelBtn = document.getElementById('cancelScraping');
                            
                            // Auto-check every 10 seconds
                            function startAutoCheck() {
                                checkInterval = setInterval(checkScrapingStatus, 10000);
                            }
                            
                            // Manual check
                            checkStatusBtn.addEventListener('click', checkScrapingStatus);
                            
                            // Cancel scraping
                            cancelBtn.addEventListener('click', function() {
                                if (confirm('Batalkan proses scraping?')) {
                                    fetch('cancel_scraping.php', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/x-www-form-urlencoded',
                                        }
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            alert('Scraping dibatalkan');
                                            location.reload();
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error:', error);
                                        alert('Terjadi error saat membatalkan scraping');
                                    });
                                }
                            });
                            
                            // Check scraping status
                            function checkScrapingStatus() {
                                fetch('check_status.php')
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.status === 'completed' || data.status === 'timeout') {
                                            clearInterval(checkInterval);
                                            alert('Scraping selesai');
                                            location.reload();
                                        } else if (data.status === 'error') {
                                            clearInterval(checkInterval);
                                            alert('Terjadi error: ' + data.message);
                                            location.reload();
                                        }
                                        // If still running, continue
                                    })
                                    .catch(error => {
                                        console.error('Error checking status:', error);
                                    });
                            }
                            
                            // Start auto-check
                            startAutoCheck();
                        });
                        </script>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i>Informasi Scraping</h6>
                            <p class="mb-0">Proses scraping akan mengambil data harga dari 7 pasar di Cirebon untuk bulan berjalan.</p>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card bg-light mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title"><i class="fas fa-list me-2"></i>Pasar yang Akan Discrape</h6>
                                        <ul class="mb-0">
                                            <li>Pasar Sumber (ID: 3)</li>
                                            <li>Pasar Pasalaran (ID: 16)</li>
                                            <li>Pasar Jamblang (ID: 17)</li>
                                            <li>Pasar Palimanan (ID: 18)</li>
                                            <li>Pasar Cipeujeuh (ID: 19)</li>
                                            <li>Pasar Babakan (ID: 20)</li>
                                            <li>Pasar Ciledug (ID: 21)</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title"><i class="fas fa-clock me-2"></i>Estimasi Waktu</h6>
                                        <p class="mb-2">Estimasi waktu scraping: <strong>2-3 menit</strong></p>
                                        <p class="mb-0">Setiap pasar akan diambil datanya dengan delay untuk menghindari blocking.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center py-4">
                            <form action="run_scraping.php" method="POST" id="scrapeForm">
                                <div class="row justify-content-center mb-4">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Bulan</label>
                                            <select class="form-select" name="month" required>
                                                <?php
                                                $months = [
                                                    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                                                    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                                                    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                                                ];
                                                $current_month = date('n');
                                                foreach ($months as $num => $name): ?>
                                                <option value="<?= $num ?>" <?= $num == $current_month ? 'selected' : '' ?>>
                                                    <?= $name ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Tahun</label>
                                            <input type="number" class="form-control" name="year" 
                                                   value="<?= date('Y') ?>" min="2023" max="<?= date('Y') ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-play me-2"></i> Mulai Scraping
                                </button>
                            </form>
                        </div>
                        
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var scrapeForm = document.getElementById('scrapeForm');
                            
                            scrapeForm.addEventListener('submit', function(e) {
                                e.preventDefault();
                                
                                if (confirm('Mulai proses scraping?')) {
                                    // Show loading
                                    var submitBtn = this.querySelector('button[type="submit"]');
                                    var originalText = submitBtn.innerHTML;
                                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Memulai...';
                                    submitBtn.disabled = true;
                                    
                                    // Create lock file first
                                    fetch('create_lock.php', {
                                        method: 'POST'
                                    })
                                    .then(response => response.json())
                                    .then(lockData => {
                                        if (lockData.success) {
                                            // Submit the form
                                            var formData = new FormData(this);
                                            return fetch('run_scraping.php', {
                                                method: 'POST',
                                                body: formData
                                            });
                                        } else {
                                            throw new Error('Gagal membuat lock file');
                                        }
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            alert('Scraping berhasil dimulai! Halaman akan direfresh.');
                                            location.reload();
                                        } else {
                                            alert('Error: ' + (data.error || 'Gagal memulai scraping'));
                                            location.reload();
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error:', error);
                                        alert('Terjadi error saat memulai scraping');
                                        location.reload();
                                    })
                                    .finally(() => {
                                        // Reset button
                                        submitBtn.innerHTML = originalText;
                                        submitBtn.disabled = false;
                                    });
                                }
                            });
                        });
                        </script>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Scraping History -->
        <div class="col-lg-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-history me-1"></i>Riwayat Scraping
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($scraping_logs)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($scraping_logs as $log): ?>
                            <div class="list-group-item px-0 border-bottom">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">
                                            <?= str_pad($log['bulan'], 2, '0', STR_PAD_LEFT) ?>-<?= $log['tahun'] ?>
                                        </h6>
                                        <small class="text-muted">
                                            <?= format_number($log['jumlah_pasar']) ?> pasar • 
                                            <?= format_number($log['jumlah_komoditas']) ?> komoditas
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <div class="badge <?= $log['status'] == 'success' ? 'bg-success' : ($log['status'] == 'partial' ? 'bg-warning text-dark' : 'bg-danger') ?>">
                                            <?= strtoupper($log['status']) ?>
                                        </div>
                                        <div class="small text-muted mt-1">
                                            <?= format_datetime($log['created_at'], false) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-history fa-3x text-muted mb-3"></i>
                            <p class="text-muted mb-0">Belum ada riwayat scraping</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// Jika Anda ingin tetap menggunakan jQuery, pastikan sudah dimuat di header
// Alternatif: tambahkan di sini jika belum ada
if (!isset($extra_js)) {
    $extra_js = '';
}

// Pilihan 1: Gunakan native JavaScript (direkomendasikan)
// $extra_js .= ''; // Native JS sudah digunakan di atas

// Pilihan 2: Atau jika ingin jQuery, tambahkan CDN
// $extra_js .= '<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>';

require_once '../../includes/footer.php'; 
?>