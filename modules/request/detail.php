<?php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();
$user = getUserData();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get request data
$query = "SELECT r.*, k.nama_kantor, k.kode_kantor, u.nama_lengkap as pembuat,
          ua.nama_lengkap as approver_name
          FROM request r
          INNER JOIN kantor k ON r.kantor_id = k.id
          INNER JOIN users u ON r.user_id = u.id
          LEFT JOIN users ua ON r.approved_by = ua.id
          WHERE r.id = '$id'";

$result = mysqli_query($conn, $query);

if(mysqli_num_rows($result) == 0) {
    header("Location: list.php");
    exit;
}

$request = mysqli_fetch_assoc($result);

// Check access
if($user['role'] == 'kantor' && $request['kantor_id'] != $user['kantor_id']) {
    header("Location: list.php");
    exit;
}

// Get detail items
$query_detail = "SELECT rd.*, p.kode_produk, p.nama_produk, s.nama_satuan as product_satuan
                FROM request_detail rd
                LEFT JOIN produk p ON rd.produk_id = p.id
                LEFT JOIN satuan s ON p.satuan_id = s.id
                WHERE rd.request_id = '$id'
                ORDER BY CASE WHEN rd.item_type = 'manual' THEN rd.custom_name ELSE p.nama_produk END";

$detail_result = mysqli_query($conn, $query_detail);

$page_title = "Detail Request: " . $request['no_request'];
include '../../includes/header.php';
include '../../includes/navbar.php';
include '../../includes/sidebar.php';
?>

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/dashboard/index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="list.php">Request Barang</a></li>
        <li class="breadcrumb-item active">Detail</li>
    </ol>
</nav>

<div class="row">
    <div class="col-lg-8">
        <!-- Main Info Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-800 text-dark">
                    <i class="fas fa-info-circle me-2 text-primary opacity-50"></i> Informasi Request
                </h5>
                <?= get_status_badge($request['status'], 'request') ?>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-1" style="letter-spacing: 0.5px;">No Request</label>
                        <div class="fw-bold h5 mb-0"><?= $request['no_request'] ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-1" style="letter-spacing: 0.5px;">Kantor</label>
                        <div class="fw-bold h6 mb-0"><?= $request['kode_kantor'] ?> - <?= $request['nama_kantor'] ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-1" style="letter-spacing: 0.5px;">Pembuat</label>
                        <div class="fw-bold mb-0 text-dark"><?= $request['pembuat'] ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-1" style="letter-spacing: 0.5px;">Tanggal Request</label>
                        <div class="fw-bold mb-0 text-dark"><?= format_tanggal($request['tanggal_request']) ?></div>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-1" style="letter-spacing: 0.5px;">Keperluan</label>
                        <div class="p-3 bg-light rounded-3 text-dark border-0"><?= nl2br(htmlspecialchars($request['keperluan'])) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Items Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-800 text-dark">
                    <i class="fas fa-boxes me-2 text-primary opacity-50"></i> Detail Barang
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th width="5%" class="text-center py-3 border-0">No</th>
                                <th class="py-3 border-0">Produk</th>
                                <th width="15%" class="text-center py-3 border-0">Qty Req</th>
                                <th width="15%" class="text-center py-3 border-0">Qty Appr</th>
                                <th width="15%" class="py-3 border-0">Satuan</th>
                                <th width="20%" class="py-3 border-0">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            mysqli_data_seek($detail_result, 0);
                            while($item = mysqli_fetch_assoc($detail_result)): 
                                $item_type = trim($item['item_type']);
                            ?>
                            <tr>
                                <td class="text-center"><?= $no++ ?></td>
                                 <td class="fw-bold text-dark">
                                    <?php if ($item_type === 'manual'): ?>
                                        <span class="badge bg-warning text-dark mb-1">Manual</span><br>
                                        <?= htmlspecialchars($item['custom_name']) ?>
                                    <?php else: ?>
                                        <span class="small text-muted d-block"><?= htmlspecialchars($item['kode_produk']) ?></span>
                                        <?= htmlspecialchars($item['nama_produk']) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?= number_format($item['qty_request'], 2) ?></td>
                                <td class="text-center">
                                    <span class="<?= $item['qty_approved'] ? 'fw-bold text-primary' : 'text-muted' ?>">
                                        <?= $item['qty_approved'] ? number_format($item['qty_approved'], 2) : '-' ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($item_type === 'manual' ? $item['satuan'] : $item['product_satuan']) ?></td>
                                <td class="small"><?= htmlspecialchars($item['keterangan']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white p-4 border-top d-flex gap-2 justify-content-between">
                <div>
                    <a href="list.php" class="btn btn-outline-secondary px-4">
                        <i class="fas fa-arrow-left me-2"></i> Kembali
                    </a>
                </div>
                <div class="d-flex gap-2">
                    <button onclick="window.print()" class="btn btn-outline-primary px-4">
                        <i class="fas fa-print me-2"></i> Print
                    </button>
                    
                    <?php if($request['status'] == 'pending' && ($user['role'] == 'koperasi' || $user['role'] == 'admin')): ?>
                    <a href="approve.php?id=<?= $id ?>" class="btn btn-success px-4 shadow-sm">
                        <i class="fas fa-check me-2"></i> Proses Request
                    </a>
                    <?php endif; ?>
                    
                    <?php if($request['status'] == 'diproses' && ($user['role'] == 'koperasi' || $user['role'] == 'admin')): ?>
                    <a href="../distribusi/add.php?request_id=<?= $id ?>" class="btn btn-primary px-4 shadow-sm">
                        <i class="fas fa-truck me-2"></i> Buat Distribusi
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Timeline Column -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 mb-4 h-100">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-800 text-dark">
                    <i class="fas fa-history me-2 text-primary opacity-50"></i> History & Status
                </h5>
            </div>
            <div class="card-body p-4">
                <div class="position-relative ps-4 border-start border-2 border-light py-2">
                    <!-- Created -->
                    <div class="mb-5 position-relative">
                        <div class="position-absolute translate-middle-x" style="left: -33px; top: 0;">
                            <div class="p-2 bg-primary text-white rounded-circle shadow-sm" style="width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-plus" style="font-size: 10px;"></i>
                            </div>
                        </div>
                        <div class="small fw-bold text-uppercase text-muted" style="letter-spacing: 0.5px;"><?= format_datetime($request['created_at']) ?></div>
                        <div class="fw-bold text-dark mt-1">Request Diajukan</div>
                        <div class="small text-muted mt-1">Oleh: <span class="text-primary fw-bold"><?= $request['pembuat'] ?></span></div>
                    </div>

                    <?php if($request['status'] != 'pending'): ?>
                    <!-- Approval/Rejection -->
                    <div class="mb-5 position-relative">
                        <div class="position-absolute translate-middle-x" style="left: -33px; top: 0;">
                            <div class="p-2 <?= $request['status'] == 'ditolak' ? 'bg-danger' : 'bg-success' ?> text-white rounded-circle shadow-sm" style="width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas <?= $request['status'] == 'ditolak' ? 'fa-times' : 'fa-check' ?>" style="font-size: 10px;"></i>
                            </div>
                        </div>
                        <div class="small fw-bold text-uppercase text-muted" style="letter-spacing: 0.5px;"><?= format_datetime($request['approved_at']) ?></div>
                        <div class="fw-bold text-dark mt-1"><?= $request['status'] == 'ditolak' ? 'Request Ditolak' : 'Request Disetujui' ?></div>
                        <div class="small text-muted mt-1">Oleh: <span class="text-primary fw-bold"><?= $request['approver_name'] ?></span></div>
                        <?php if($request['keterangan_approval']): ?>
                        <div class="small p-2 bg-light rounded mt-2 border-start border-3 border-<?= $request['status'] == 'ditolak' ? 'danger' : 'success' ?>">
                            "<?= $request['keterangan_approval'] ?>"
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Current State -->
                    <div class="position-relative">
                        <div class="position-absolute translate-middle-x" style="left: -33px; top: 0;">
                            <div class="p-2 bg-secondary text-white rounded-circle shadow-sm opacity-50" style="width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-clock" style="font-size: 10px;"></i>
                            </div>
                        </div>
                        <div class="small fw-bold text-uppercase text-muted" style="letter-spacing: 0.5px;">Current Status</div>
                        <div class="fw-bold text-dark mt-1">Menunggu Tindakan</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
