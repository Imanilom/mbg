<!-- Main Content -->
<div id="content">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <button type="button" id="sidebarCollapse" class="btn btn-outline-secondary">
                <i class="fas fa-bars"></i>
            </button>
            
            <span class="navbar-brand ms-3 mb-0 h1"><?= $page_title ?? 'Dashboard' ?></span>
            
            <div class="ms-auto d-flex align-items-center gap-2">
                <!-- Notifications -->
                <div class="dropdown">
                    <button class="btn btn-link text-decoration-none dropdown-toggle p-2 border-0 position-relative" type="button" data-bs-toggle="dropdown" style="color: var(--text-muted);">
                        <i class="fas fa-bell fs-5"></i>
                        <?php
                        $notif_query = "SELECT COUNT(*) as total FROM request WHERE status = 'pending'";
                        $notif = db_get_row($notif_query);
                        $notif_count = $notif['total'] ?? 0;
                        if ($notif_count > 0):
                        ?>
                        <span class="position-absolute top-0 start-50 translate-middle-y badge rounded-pill bg-danger border border-white" style="font-size: 0.6rem; padding: 4px 6px;">
                            <?= $notif_count ?>
                        </span>
                        <?php endif; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-3 p-2" style="min-width: 280px; border-radius: 16px;">
                        <li class="px-3 py-2 fw-bold text-dark">Notifikasi</li>
                        <li><hr class="dropdown-divider opacity-50"></li>
                        <?php if ($notif_count > 0): ?>
                        <li>
                            <a class="dropdown-item rounded-3 p-3 d-flex align-items-start gap-3" href="<?= BASE_URL ?>/modules/request/index.php">
                                <div class="p-2 bg-warning bg-opacity-10 text-warning rounded-circle">
                                    <i class="fas fa-clipboard-list"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold small">Permintaan Baru</div>
                                    <div class="text-muted small"><?= $notif_count ?> request menunggu approval</div>
                                </div>
                            </a>
                        </li>
                        <?php else: ?>
                        <li class="p-4 text-center">
                            <i class="fas fa-bell-slash text-muted fs-2 mb-2 d-block"></i>
                            <div class="text-muted small">Tidak ada notifikasi baru</div>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <div class="vr mx-2 opacity-10" style="height: 24px;"></div>

                <!-- User Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-link text-decoration-none dropdown-toggle d-flex align-items-center p-0 border-0" type="button" data-bs-toggle="dropdown">
                        <div class="user-avatar-wrapper shadow-sm rounded-circle overflow-hidden border-2 border-white" style="width: 42px; height: 42px;">
                            <?php if ($user['foto']): ?>
                            <img src="<?= BASE_URL ?>/assets/uploads/<?= $user['foto'] ?>" 
                                 class="w-100 h-100 object-fit-cover" alt="User">
                            <?php else: ?>
                            <div class="w-100 h-100 d-flex align-items-center justify-content-center bg-primary text-white fw-bold">
                                <?= strtoupper(substr($user['nama_lengkap'], 0, 1)) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-3 p-2" style="min-width: 240px; border-radius: 16px;">
                        <li class="px-3 py-3 border-bottom mb-2">
                            <div class="fw-bold text-dark"><?= $user['nama_lengkap'] ?></div>
                            <div class="text-muted small"><?= get_role_label() ?></div>
                        </li>
                        <li>
                            <a class="dropdown-item rounded-3 py-2 px-3" href="<?= BASE_URL ?>/modules/auth/profile.php">
                                <i class="fas fa-user-circle me-2 text-primary opacity-75"></i> Pengaturan Profil
                            </a>
                        </li>
                        <li><hr class="dropdown-divider opacity-50"></li>
                        <li>
                            <a class="dropdown-item rounded-3 py-2 px-3 text-danger" href="<?= BASE_URL ?>/logout.php" 
                               onclick="return confirm('Yakin ingin logout?')">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <?php show_flash(); ?>