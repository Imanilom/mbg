<?php
$current_page = basename($_SERVER['PHP_SELF']);
$user_role = get_user_data('role');

// Function untuk check active menu
function is_active($page) {
    global $current_page;
    return ($current_page == $page) ? 'active' : '';
}
?>
<!-- Sidebar -->
<nav id="sidebar">
    <div class="sidebar-header">
        <h3>
            <div class="p-2 bg-primary rounded-3 d-inline-flex align-items-center justify-content-center me-1" style="width: 38px; height: 38px;">
                <i class="fas fa-layer-group text-white fs-5"></i>
            </div>
            MBG SYSTEM
        </h3>
        <div class="mt-3 px-2 py-1 bg-white bg-opacity-10 rounded-pill d-inline-block" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 700; color: #94a3b8;">
            <?= get_role_label() ?> Panel
        </div>
    </div>
    
    <div class="sidebar-menu">
        <!-- Dashboard -->
        <div class="menu-item">
            <a href="<?= BASE_URL ?>/modules/dashboard/index.php" class="<?= is_active('index.php') ?>">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
        </div>
        
        <?php if (in_array($user_role, ['admin', 'koperasi', 'gudang'])): ?>
        <!-- Master Data (Admin, Koperasi, Gudang) -->
        <div class="menu-item">
            <a href="#masterSubmenu" data-bs-toggle="collapse" class="collapsed">
                <i class="fas fa-database"></i>
                <span>Master Data</span>
            </a>
            <div class="collapse" id="masterSubmenu">
                <ul class="list-unstyled ps-4">
                    <?php if ($user_role == 'admin'): ?>
                    <li><a href="<?= BASE_URL ?>/modules/master/users.php" class="<?= is_active('users.php') ?>">
                        <i class="fas fa-users"></i> User
                    </a></li>
                    <li><a href="<?= BASE_URL ?>/modules/master/kantor.php" class="<?= is_active('kantor.php') ?>">
                        <i class="fas fa-building"></i> Kantor
                    </a></li>
                    <?php endif; ?>
                    
                    <li><a href="<?= BASE_URL ?>/modules/master/produk.php" class="<?= is_active('produk.php') ?>">
                        <i class="fas fa-box"></i> Produk
                    </a></li>
                    <li><a href="<?= BASE_URL ?>/modules/master/penetapan_harga.php" class="<?= is_active('penetapan_harga.php') ?>">
                        <i class="fas fa-dollar-sign"></i> Penetapan Harga
                    </a></li>
                    <li><a href="<?= BASE_URL ?>/modules/master/kategori.php" class="<?= is_active('kategori.php') ?>">
                        <i class="fas fa-tags"></i> Kategori
                    </a></li>
                    <li><a href="<?= BASE_URL ?>/modules/master/supplier.php" class="<?= is_active('supplier.php') ?>">
                        <i class="fas fa-truck"></i> Supplier
                    </a></li>
                    <li><a href="<?= BASE_URL ?>/modules/master/resep.php" class="<?= is_active('resep.php') ?>">
                        <i class="fas fa-utensils"></i> Resep
                    </a></li>
                </ul>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Request (Semua role) -->
        <div class="menu-item">
            <a href="<?= BASE_URL ?>/modules/request/index.php" class="<?= is_active('index.php') ?>">
                <i class="fas fa-clipboard-list"></i>
                <span>Request Barang</span>
            </a>
        </div>
        
        <?php if (in_array($user_role, ['admin', 'koperasi'])): ?>
        <!-- Pembelanjaan (Admin, Koperasi) -->
        <div class="menu-item">
            <a href="<?= BASE_URL ?>/modules/pembelanjaan/index.php">
                <i class="fas fa-shopping-cart"></i>
                <span>Pembelanjaan</span>
            </a>
        </div>
        
        <!-- Penerimaan (Admin, Koperasi) -->
        <div class="menu-item">
            <a href="<?= BASE_URL ?>/modules/penerimaan/index.php">
                <i class="fas fa-dolly"></i>
                <span>Penerimaan Barang</span>
            </a>
        </div>
        
        <!-- Distribusi (Admin, Koperasi) -->
        <div class="menu-item">
            <a href="<?= BASE_URL ?>/modules/distribusi/index.php">
                <i class="fas fa-shipping-fast"></i>
                <span>Distribusi</span>
            </a>
        </div>
        
        <!-- Piutang (Admin, Koperasi) -->
        <div class="menu-item">
            <a href="<?= BASE_URL ?>/modules/piutang/index.php">
                <i class="fas fa-money-bill-wave"></i>
                <span>Piutang</span>
            </a>
        </div>
        <?php endif; ?>
        
        <?php if (in_array($user_role, ['admin', 'gudang'])): ?>
        <!-- Gudang (Admin, Gudang) -->
        <div class="menu-item">
            <a href="#gudangSubmenu" data-bs-toggle="collapse" class="collapsed">
                <i class="fas fa-warehouse"></i>
                <span>Gudang</span>
            </a>
            <div class="collapse" id="gudangSubmenu">
                <ul class="list-unstyled ps-4">
                    <li><a href="<?= BASE_URL ?>/modules/gudang/stok.php">
                        <i class="fas fa-boxes"></i> Stok Barang
                    </a></li>
                    <li><a href="<?= BASE_URL ?>/modules/gudang/opname.php">
                        <i class="fas fa-clipboard-check"></i> Stok Opname
                    </a></li>
                </ul>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($user_role == 'kantor'): ?>
        <!-- Scan QR (Kantor) -->
        <div class="menu-item">
            <a href="<?= BASE_URL ?>/modules/distribusi/scan.php">
                <i class="fas fa-qrcode"></i>
                <span>Scan QR Penerimaan</span>
            </a>
        </div>
        
        <!-- Riwayat Penerimaan (Kantor) -->
        <div class="menu-item">
            <a href="<?= BASE_URL ?>/modules/distribusi/riwayat.php">
                <i class="fas fa-history"></i>
                <span>Riwayat Penerimaan</span>
            </a>
        </div>
        <?php endif; ?>
        
        <?php if (in_array($user_role, ['admin'])): ?>
        <!-- Laporan (Admin) -->
        <!-- Laporan (Admin) -->
        <div class="menu-item">
            <a href="#laporanSubmenu" data-bs-toggle="collapse" class="collapsed">
                <i class="fas fa-chart-line"></i>
                <span>Laporan</span>
            </a>
            <div class="collapse" id="laporanSubmenu">
                <ul class="list-unstyled ps-4">
                    <li><a href="<?= BASE_URL ?>/modules/laporan/index.php" class="<?= is_active('index.php') ?>">
                        <i class="fas fa-tachometer-alt"></i> Dashboard Laporan
                    </a></li>
                    <li><a href="<?= BASE_URL ?>/modules/laporan/margin_harian.php" class="<?= is_active('margin_harian.php') ?>">
                        <i class="fas fa-calendar-day"></i> Margin Harian
                    </a></li>
                    <li><a href="<?= BASE_URL ?>/modules/laporan/margin_bulanan.php" class="<?= is_active('margin_bulanan.php') ?>">
                        <i class="fas fa-calendar-alt"></i> Margin Bulanan
                    </a></li>
                    <li><a href="<?= BASE_URL ?>/modules/laporan/analisis_supplier.php" class="<?= is_active('analisis_supplier.php') ?>">
                        <i class="fas fa-truck"></i> Analisis Supplier
                    </a></li>
                </ul>
            </div>
        </div>
        
        <!-- Settings (Admin) -->
        <div class="menu-item">
            <a href="<?= BASE_URL ?>/modules/master/settings.php">
                <i class="fas fa-cog"></i>
                <span>Pengaturan</span>
            </a>
        </div>
        <?php endif; ?>
        
        <hr style="border-color: rgba(255,255,255,0.1);">
        
        <!-- Profile -->
        <div class="menu-item">
            <a href="<?= BASE_URL ?>/modules/auth/profile.php">
                <i class="fas fa-user-circle"></i>
                <span>Profile</span>
            </a>
        </div>
        
        <!-- Logout -->
        <div class="menu-item">
            <a href="<?= BASE_URL ?>/logout.php" onclick="return confirm('Yakin ingin logout?')">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</nav>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
