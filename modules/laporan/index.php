<?php
// modules/laporan/index.php
$page_title = 'Pusat Laporan';
require_once '../../helpers/constants.php';
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();
checkRole(['admin', 'koperasi', 'gudang', 'kantor']); // Access varies by report type? Let's check permissions inside or allow all to see menu.

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';
?>

<div class="content-wrapper">
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/dashboard/index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Pusat Laporan</li>
    </ol>
</nav>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-800 text-dark">
                    <i class="fas fa-file-invoice me-2 text-primary opacity-50"></i> Konfigurasi Laporan
                </h5>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-4">Pilih kategori dan tentukan parameter laporan yang ingin Anda cetak atau ekspor.</p>
                
                <style>
                    #reportTabs button {
                        cursor: pointer !important;
                        pointer-events: auto !important;
                        user-select: none;
                    }
                </style>
                
                <script>
                // Define switchTab function BEFORE buttons
                function switchTab(tabId) {
                    // Remove active from all buttons
                    document.querySelectorAll('#reportTabs button').forEach(btn => {
                        btn.classList.remove('active');
                        btn.setAttribute('aria-selected', 'false');
                    });
                    
                    // Remove active from all panes
                    document.querySelectorAll('.tab-pane').forEach(pane => {
                        pane.classList.remove('show', 'active');
                    });
                    
                    // Add active to clicked button
                    const clickedBtn = document.getElementById(tabId + '-tab');
                    if (clickedBtn) {
                        clickedBtn.classList.add('active');
                        clickedBtn.setAttribute('aria-selected', 'true');
                    }
                    
                    // Show target pane
                    const targetPane = document.getElementById(tabId);
                    if (targetPane) {
                        targetPane.classList.add('show', 'active');
                    }
                }
                </script>
                            
                            <ul class="nav nav-pills mb-4 px-3 border-0 bg-light rounded-pill p-1" id="reportTabs" role="tablist" style="width: fit-content;">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active rounded-pill px-4 fw-bold" id="transaksi-tab" data-bs-toggle="pill" data-bs-target="#transaksi" type="button" role="tab" aria-controls="transaksi" aria-selected="true" onclick="switchTab('transaksi')">
                                        <i class="fas fa-exchange-alt me-2"></i>Transaksi
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link rounded-pill px-4 fw-bold" id="stok-tab" data-bs-toggle="pill" data-bs-target="#stok" type="button" role="tab" aria-controls="stok" aria-selected="false" onclick="switchTab('stok')">
                                        <i class="fas fa-boxes me-2"></i>Gudang & Stok
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link rounded-pill px-4 fw-bold" id="keuangan-tab" data-bs-toggle="pill" data-bs-target="#keuangan" type="button" role="tab" aria-controls="keuangan" aria-selected="false" onclick="switchTab('keuangan')">
                                        <i class="fas fa-hand-holding-usd me-2"></i>Keuangan
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content" id="reportTabsContent">
                                
                                <!-- Tab Transaksi (Request, Belanja, Terima, Distribusi) -->
                                <div class="tab-pane fade show active" id="transaksi" role="tabpanel" aria-labelledby="transaksi-tab" tabindex="0">
                                    <form action="cetak.php" method="GET" target="_blank" class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Jenis Transaksi</label>
                                            <select class="form-select" name="jenis" required>
                                                <option value="request">Request Barang</option>
                                                <option value="pembelanjaan">Pembelanjaan</option>
                                                <option value="penerimaan">Penerimaan Barang</option>
                                                <option value="distribusi">Distribusi</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Dari Tanggal</label>
                                            <input type="date" class="form-control" name="start_date" value="<?php echo date('Y-m-01'); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Sampai Tanggal</label>
                                            <input type="date" class="form-control" name="end_date" value="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                        <div class="col-md-2 d-flex align-items-end">
                                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-print"></i> Cetak</button>
                                        </div>
                                        
                                        <div class="col-12 mt-3">
                                            <label class="form-label">Filter Status (Opsional)</label>
                                            <select class="form-select" name="status">
                                                <option value="">Semua Status</option>
                                                <option value="draft">Draft / Persiapan</option>
                                                <option value="pending">Pending</option>
                                                <option value="approved,disetujui">Disetujui</option>
                                                <option value="rejected,ditolak">Ditolak</option>
                                                <option value="selesai,diterima_koperasi">Selesai / Diterima</option>
                                            </select>
                                        </div>
                                    </form>
                                </div>

                                <!-- Tab Stok -->
                                <div class="tab-pane fade" id="stok" role="tabpanel" aria-labelledby="stok-tab" tabindex="0">
                                    <form action="cetak.php" method="GET" target="_blank" class="row g-3">
                                        <input type="hidden" name="jenis" value="stok">
                                        <div class="col-md-4">
                                            <label class="form-label">Tipe Laporan Stok</label>
                                            <select class="form-select" name="sub_jenis">
                                                <option value="persediaan">Posisi Stok Saat Ini</option>
                                                <option value="kartu_stok">Kartu Stok (Riwayat)</option>
                                                <option value="expired">Monitoring Expired</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Kategori Produk</label>
                                            <select class="form-select" name="kategori_id">
                                                <option value="">Semua Kategori</option>
                                                <?php
                                                $kategori = db_get_all("SELECT * FROM kategori_produk ORDER BY nama_kategori");
                                                foreach ($kategori as $k) {
                                                    echo "<option value='{$k['id']}'>{$k['nama_kategori']}</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4 d-flex align-items-end">
                                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-print"></i> Cetak</button>
                                        </div>
                                        
                                        <div class="col-12 mt-3 text-muted">
                                            <small>* Untuk laporan Kartu Stok, harap pilih produk spesifik di halaman Gudang > Detail. Laporan ini menampilkan agregat stok.</small>
                                        </div>
                                    </form>
                                </div>

                                <!-- Tab Keuangan (Piutang) -->
                                <div class="tab-pane fade" id="keuangan" role="tabpanel" aria-labelledby="keuangan-tab" tabindex="0">
                                    <form action="cetak.php" method="GET" target="_blank" class="row g-3">
                                        <input type="hidden" name="jenis" value="piutang">
                                        <div class="col-md-4">
                                            <label class="form-label">Status Piutang</label>
                                            <select class="form-select" name="status">
                                                <option value="">Semua</option>
                                                <option value="belum_lunas">Belum Lunas</option>
                                                <option value="sebagian">Bayar Sebagian</option>
                                                <option value="lunas">Lunas</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Tgl Jatuh Tempo Dari</label>
                                            <input type="date" class="form-control" name="start_date">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Sampai</label>
                                            <input type="date" class="form-control" name="end_date">
                                        </div>
                                        <div class="col-md-2 d-flex align-items-end">
                                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-print"></i> Cetak</button>
                                        </div>
                                    </form>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<?php include '../../includes/footer.php'; ?>
