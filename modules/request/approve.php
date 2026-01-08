<?php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();
checkRole(['koperasi', 'admin']);

$user = getUserData();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get request data
$query = "SELECT r.*, k.nama_kantor, u.nama_lengkap as pembuat
          FROM request r
          INNER JOIN kantor k ON r.kantor_id = k.id
          INNER JOIN users u ON r.user_id = u.id
          WHERE r.id = '$id' AND r.status = 'pending'";

$result = mysqli_query($conn, $query);

if(mysqli_num_rows($result) == 0) {
    header("Location: list.php");
    exit;
}

$request = mysqli_fetch_assoc($result);

// Get detail with stock info
$query_detail = "SELECT rd.*, p.kode_produk, p.nama_produk, s.nama_satuan,
                (SELECT COALESCE(SUM(qty_available), 0) FROM gudang_stok WHERE produk_id = p.id AND kondisi = 'baik') as stok_tersedia
                FROM request_detail rd
                INNER JOIN produk p ON rd.produk_id = p.id
                INNER JOIN satuan s ON p.satuan_id = s.id
                WHERE rd.request_id = '$id'
                ORDER BY p.nama_produk";

$detail_result = mysqli_query($conn, $query_detail);

$page_title = "Approve Request: " . $request['no_request'];
include '../../includes/header.php';
include '../../includes/navbar.php';
include '../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <section class="content">
        <div class="container-fluid">
            <form id="formApprove" method="POST">
                <input type="hidden" name="request_id" value="<?= $id ?>">
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-check-circle"></i> Approve Request</h3>
                    </div>
                    <div class="card-body">
                        <!-- Info request -->
                        <div class="alert alert-info">
                            <strong>Request:</strong> <?= $request['no_request'] ?><br>
                            <strong>Kantor:</strong> <?= $request['nama_kantor'] ?><br>
                            <strong>Pembuat:</strong> <?= $request['pembuat'] ?><br>
                            <strong>Keperluan:</strong> <?= nl2br($request['keperluan']) ?>
                        </div>

                        <!-- Items table -->
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Produk</th>
                                    <th>Qty Request</th>
                                    <th>Stok Tersedia</th>
                                    <th>Qty Approved</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = 1;
                                while($item = mysqli_fetch_assoc($detail_result)): 
                                    $cukup = $item['stok_tersedia'] >= $item['qty_request'];
                                    $badge_class = $cukup ? 'success' : 'danger';
                                    $badge_text = $cukup ? 'Stok Cukup' : 'Stok Tidak Cukup';
                                ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td>
                                        <?= $item['kode_produk'] ?> - <?= $item['nama_produk'] ?><br>
                                        <small class="text-muted"><?= $item['nama_satuan'] ?></small>
                                    </td>
                                    <td><?= number_format($item['qty_request'], 2) ?></td>
                                    <td><?= number_format($item['stok_tersedia'], 2) ?></td>
                                    <td>
                                        <input type="hidden" name="items[<?= $item['id'] ?>][detail_id]" value="<?= $item['id'] ?>">
                                        <input type="number" class="form-control" name="items[<?= $item['id'] ?>][qty_approved]" 
                                               value="<?= $item['qty_request'] ?>" min="0" max="<?= $item['stok_tersedia'] ?>" 
                                               step="0.01" required>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $badge_class ?>"><?= $badge_text ?></span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>

                        <div class="form-group mt-3">
                            <label>Keterangan Approval</label>
                            <textarea class="form-control" name="keterangan_approval" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" name="action" value="approve" class="btn btn-success">
                            <i class="fas fa-check"></i> Approve
                        </button>
                        <button type="submit" name="action" value="reject" class="btn btn-danger">
                            <i class="fas fa-times"></i> Tolak
                        </button>
                        <a href="detail.php?id=<?= $id ?>" class="btn btn-default">
                            <i class="fas fa-arrow-left"></i> Batal
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </section>
</div>

<?php 
ob_start(); 
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$('#formApprove').submit(function(e) {
    e.preventDefault();
    
    const action = $(document.activeElement).val();
    const formData = $(this).serialize() + '&action=' + action;
    
    let title = action === 'approve' ? 'Approve Request?' : 'Tolak Request?';
    let text = action === 'approve' ? 'Request akan diproses' : 'Request akan ditolak';
    
    Swal.fire({
        title: title,
        text: text,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if(result.isConfirmed) {
            $.ajax({
                url: 'process_approve.php',
                type: 'POST',
                data: formData,
                success: function(res) {
                    if(res.status === 'success') {
                        Swal.fire('Berhasil!', res.message, 'success').then(() => {
                            window.location.href = 'detail.php?id=<?= $id ?>';
                        });
                    } else {
                        Swal.fire('Gagal!', res.message, 'error');
                    }
                }
            });
        }
    });
});
</script>
<?php 
$extra_js = ob_get_clean(); 
include '../../includes/footer.php'; 
?>