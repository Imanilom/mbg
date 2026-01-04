<?php
// modules/distribusi/scan.php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();
checkRole(['kantor', 'admin']);

$user = getUserData();
$page_title = "Scan QR Code Penerimaan";

// Check if QR data submitted
$distribusi_data = null;
if(isset($_GET['qr']) && !empty($_GET['qr'])) {
    $qr_code = mysqli_real_escape_string($conn, $_GET['qr']);
    
    // Get distribusi by QR code
    $query = "SELECT d.*, k.nama_kantor, u.nama_lengkap as pengirim_name
              FROM distribusi d
              INNER JOIN kantor k ON d.kantor_id = k.id
              INNER JOIN users u ON d.pengirim_id = u.id
              WHERE d.qr_code = '$qr_code' AND d.status = 'dikirim'";
    
    $result = mysqli_query($conn, $query);
    
    if(mysqli_num_rows($result) > 0) {
        $distribusi_data = mysqli_fetch_assoc($result);
        
        // Check akses kantor
        if($user['role'] == 'kantor' && $distribusi_data['kantor_id'] != $user['kantor_id']) {
            $error_message = "QR Code ini bukan untuk kantor Anda";
            $distribusi_data = null;
        } else {
            // Get items
            $query_items = "SELECT dd.*, p.kode_produk, p.nama_produk, s.nama_satuan
                           FROM distribusi_detail dd
                           INNER JOIN produk p ON dd.produk_id = p.id
                           INNER JOIN satuan s ON p.satuan_id = s.id
                           WHERE dd.distribusi_id = '{$distribusi_data['id']}'
                           ORDER BY p.nama_produk";
            
            $items_result = mysqli_query($conn, $query_items);
        }
    } else {
        $error_message = "QR Code tidak valid atau sudah pernah di-scan";
    }
}

include '../../includes/header.php';
?>

<!-- Minimal navbar for mobile -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <i class="fas fa-qrcode"></i> Scan Penerimaan
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="list.php">
                        <i class="fas fa-list"></i> Daftar
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../../logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container-fluid py-3">
    <?php if(!$distribusi_data): ?>
    <!-- Scanner Interface -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
                <i class="fas fa-camera"></i> Scan QR Code
            </h5>
        </div>
        <div class="card-body text-center">
            <?php if(isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?= $error_message ?>
            </div>
            <?php endif; ?>
            
            <div id="scanner-container" class="mb-3">
                <video id="qr-video" class="w-100" style="max-width: 500px; border: 2px solid #007bff; border-radius: 10px;"></video>
            </div>
            
            <div id="scan-status" class="alert alert-info">
                <i class="fas fa-info-circle"></i> Arahkan kamera ke QR Code pada surat jalan
            </div>
            
            <div class="alert alert-warning">
                <small>
                    <i class="fas fa-lightbulb"></i> Tips:
                    <ul class="text-start mb-0">
                        <li>Pastikan QR Code terlihat jelas</li>
                        <li>Hindari pantulan cahaya</li>
                        <li>Jarak ideal 10-20 cm dari kamera</li>
                    </ul>
                </small>
            </div>
            
            <!-- Manual Input Alternative -->
            <button class="btn btn-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#manualInput">
                <i class="fas fa-keyboard"></i> Input Manual
            </button>
            
            <div class="collapse mt-3" id="manualInput">
                <div class="card card-body">
                    <form method="GET">
                        <div class="input-group">
                            <input type="text" class="form-control" name="qr" placeholder="Masukkan kode QR secara manual" required>
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i> Cari
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <!-- Penerimaan Form -->
    <form id="formPenerimaan" method="POST">
        <input type="hidden" name="distribusi_id" value="<?= $distribusi_data['id'] ?>">
        <input type="hidden" name="qr_code" value="<?= $qr_code ?>">
        <input type="hidden" id="latitude" name="latitude">
        <input type="hidden" id="longitude" name="longitude">
        
        <!-- Info Distribusi -->
        <div class="card mb-3">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="fas fa-check-circle"></i> QR Code Valid!
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr>
                        <th width="150">No Surat Jalan</th>
                        <td><?= $distribusi_data['no_surat_jalan'] ?></td>
                    </tr>
                    <tr>
                        <th>Tanggal Kirim</th>
                        <td><?= format_tanggal($distribusi_data['tanggal_kirim']) ?></td>
                    </tr>
                    <tr>
                        <th>Kantor Tujuan</th>
                        <td><?= $distribusi_data['nama_kantor'] ?></td>
                    </tr>
                    <tr>
                        <th>Pengirim</th>
                        <td><?= $distribusi_data['pengirim_name'] ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Penerima Info -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user"></i> Informasi Penerima
                </h5>
            </div>
            <div class="card-body">
                <div class="form-group mb-3">
                    <label>Nama Penerima <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="penerima_name" value="<?= $user['nama_lengkap'] ?>" required>
                </div>
                
                <div class="form-group mb-3">
                    <label>Waktu Penerimaan</label>
                    <input type="text" class="form-control" value="<?= date('d/m/Y H:i:s') ?>" readonly>
                    <input type="hidden" name="tanggal_terima" value="<?= date('Y-m-d H:i:s') ?>">
                </div>
                
                <div class="form-group mb-3">
                    <label>Lokasi GPS</label>
                    <input type="text" class="form-control" id="gps-info" value="Mengambil lokasi..." readonly>
                    <small class="text-muted">Lokasi akan direkam otomatis</small>
                </div>
            </div>
        </div>

        <!-- Detail Barang -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-boxes"></i> Detail Barang
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Produk</th>
                                <th>Qty Kirim</th>
                                <th>Qty Terima</th>
                                <th>Kondisi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            while($item = mysqli_fetch_assoc($items_result)): 
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td>
                                    <strong><?= $item['kode_produk'] ?></strong><br>
                                    <small><?= $item['nama_produk'] ?></small><br>
                                    <small class="text-muted"><?= $item['nama_satuan'] ?></small>
                                    <input type="hidden" name="items[<?= $item['id'] ?>][detail_id]" value="<?= $item['id'] ?>">
                                    <input type="hidden" name="items[<?= $item['id'] ?>][qty_kirim]" value="<?= $item['qty_kirim'] ?>">
                                </td>
                                <td class="text-center">
                                    <strong><?= number_format($item['qty_kirim'], 2) ?></strong>
                                </td>
                                <td>
                                    <input type="number" class="form-control input-qty-terima" 
                                           name="items[<?= $item['id'] ?>][qty_terima]" 
                                           value="<?= $item['qty_kirim'] ?>" 
                                           step="0.01" 
                                           required
                                           data-qty-kirim="<?= $item['qty_kirim'] ?>">
                                </td>
                                <td>
                                    <select class="form-select kondisi-select" name="items[<?= $item['id'] ?>][kondisi_terima]" required>
                                        <option value="lengkap">Lengkap</option>
                                        <option value="kurang">Kurang</option>
                                        <option value="rusak">Rusak</option>
                                        <option value="lebih">Lebih</option>
                                    </select>
                                    <textarea class="form-control mt-2 alasan-selisih d-none" 
                                              name="items[<?= $item['id'] ?>][alasan_selisih]" 
                                              rows="2" 
                                              placeholder="Alasan selisih (wajib diisi)"></textarea>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Upload Foto -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-camera"></i> Foto Barang (Opsional)
                </h5>
            </div>
            <div class="card-body">
                <input type="file" class="form-control" name="foto_barang" accept="image/*" capture="camera">
                <small class="text-muted">Ambil foto barang yang diterima sebagai bukti</small>
            </div>
        </div>

        <!-- Submit -->
        <div class="card">
            <div class="card-body">
                <button type="submit" class="btn btn-success btn-lg w-100" id="btnSubmit">
                    <i class="fas fa-check"></i> Konfirmasi Penerimaan
                </button>
                <a href="scan.php" class="btn btn-secondary btn-lg w-100 mt-2">
                    <i class="fas fa-times"></i> Batal
                </a>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script>
<?php if(!$distribusi_data): ?>
// QR Scanner
const video = document.getElementById('qr-video');
const canvasElement = document.createElement('canvas');
const canvas = canvasElement.getContext('2d');
const scanStatus = document.getElementById('scan-status');

let scanning = true;

// Start camera
navigator.mediaDevices.getUserMedia({ 
    video: { facingMode: 'environment' } // Use back camera
}).then(function(stream) {
    video.srcObject = stream;
    video.setAttribute('playsinline', true);
    video.play();
    requestAnimationFrame(tick);
}).catch(function(err) {
    scanStatus.className = 'alert alert-danger';
    scanStatus.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Tidak dapat mengakses kamera: ' + err.message;
});

function tick() {
    if(!scanning) return;
    
    if(video.readyState === video.HAVE_ENOUGH_DATA) {
        canvasElement.height = video.videoHeight;
        canvasElement.width = video.videoWidth;
        canvas.drawImage(video, 0, 0, canvasElement.width, canvasElement.height);
        
        const imageData = canvas.getImageData(0, 0, canvasElement.width, canvasElement.height);
        const code = jsQR(imageData.data, imageData.width, imageData.height, {
            inversionAttempts: 'dontInvert',
        });
        
        if(code) {
            scanning = false;
            scanStatus.className = 'alert alert-success';
            scanStatus.innerHTML = '<i class="fas fa-check-circle"></i> QR Code terdeteksi! Memproses...';
            
            // Stop camera
            video.srcObject.getTracks().forEach(track => track.stop());
            
            // Redirect with QR data
            window.location.href = 'scan.php?qr=' + encodeURIComponent(code.data);
        }
    }
    
    requestAnimationFrame(tick);
}
<?php else: ?>
// Get GPS location
if(navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function(position) {
        document.getElementById('latitude').value = position.coords.latitude;
        document.getElementById('longitude').value = position.coords.longitude;
        document.getElementById('gps-info').value = 
            position.coords.latitude.toFixed(6) + ', ' + 
            position.coords.longitude.toFixed(6);
    }, function(error) {
        document.getElementById('gps-info').value = 'Lokasi tidak tersedia: ' + error.message;
    });
} else {
    document.getElementById('gps-info').value = 'Browser tidak mendukung geolocation';
}

// Monitor qty terima changes
$(document).on('input', '.input-qty-terima', function() {
    const row = $(this).closest('tr');
    const qtyKirim = parseFloat($(this).data('qty-kirim'));
    const qtyTerima = parseFloat($(this).val());
    const kondisiSelect = row.find('.kondisi-select');
    const alasanField = row.find('.alasan-selisih');
    
    if(qtyTerima < qtyKirim) {
        kondisiSelect.val('kurang');
        alasanField.removeClass('d-none').prop('required', true);
    } else if(qtyTerima > qtyKirim) {
        kondisiSelect.val('lebih');
        alasanField.removeClass('d-none').prop('required', true);
    } else {
        kondisiSelect.val('lengkap');
        alasanField.addClass('d-none').prop('required', false);
    }
});

// Monitor kondisi changes
$(document).on('change', '.kondisi-select', function() {
    const alasanField = $(this).closest('tr').find('.alasan-selisih');
    const value = $(this).val();
    
    if(value === 'kurang' || value === 'rusak' || value === 'lebih') {
        alasanField.removeClass('d-none').prop('required', true);
    } else {
        alasanField.addClass('d-none').prop('required', false);
    }
});

// Form submit
$('#formPenerimaan').submit(function(e) {
    e.preventDefault();
    
    Swal.fire({
        title: 'Konfirmasi Penerimaan?',
        text: 'Data penerimaan akan disimpan dan tidak dapat diubah',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Konfirmasi!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if(result.isConfirmed) {
            const formData = new FormData(this);
            
            $.ajax({
                url: 'process_penerimaan.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    $('#btnSubmit').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
                },
                success: function(res) {
                    if(res.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: res.message,
                            showConfirmButton: false,
                            timer: 2000
                        }).then(() => {
                            window.location.href = 'detail.php?id=<?= $distribusi_data['id'] ?>';
                        });
                    } else {
                        Swal.fire('Gagal!', res.message, 'error');
                        $('#btnSubmit').prop('disabled', false).html('<i class="fas fa-check"></i> Konfirmasi Penerimaan');
                    }
                },
                error: function() {
                    Swal.fire('Error!', 'Terjadi kesalahan sistem', 'error');
                    $('#btnSubmit').prop('disabled', false).html('<i class="fas fa-check"></i> Konfirmasi Penerimaan');
                }
            });
        }
    });
});
<?php endif; ?>
</script>

</body>
</html>