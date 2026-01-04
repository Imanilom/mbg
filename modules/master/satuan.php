<?php
$page_title = 'Manajemen Satuan';
require_once '../../includes/header.php';
require_role(['admin', 'koperasi', 'gudang']);

require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';

// Handle CRUD
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'save') {
        $id = intval($_POST['id'] ?? 0);
        $nama_satuan = clean_input($_POST['nama_satuan']);
        $keterangan = clean_input($_POST['keterangan']);
        
        $data = [
            'nama_satuan' => $nama_satuan,
            'keterangan' => $keterangan
        ];
        
        if ($id > 0) {
            db_update('satuan', $data, "id = {$id}");
            set_flash('success', 'Satuan berhasil diupdate');
        } else {
            db_insert('satuan', $data);
            set_flash('success', 'Satuan berhasil ditambahkan');
        }
        
        header('Location: satuan.php');
        exit;
    }
    
    if ($action == 'delete') {
        $id = intval($_POST['id']);
        
        // Check if used
        $check = db_get_row("SELECT COUNT(*) as total FROM produk WHERE satuan_id = {$id}");
        if ($check['total'] > 0) {
            set_flash('error', 'Satuan tidak dapat dihapus karena masih digunakan');
        } else {
            db_delete('satuan', "id = {$id}");
            set_flash('success', 'Satuan berhasil dihapus');
        }
        
        header('Location: satuan.php');
        exit;
    }
}

// Get data
$satuan_list = db_get_all("SELECT * FROM satuan ORDER BY nama_satuan ASC");
?>

<div class="row">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/dashboard/index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Master Satuan</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0" id="formTitle">Tambah Satuan</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="formSatuan">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" id="satuanId">
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Satuan <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_satuan" id="nama_satuan" required>
                        <small class="text-muted">Contoh: PCS, BOX, KG, LITER</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <textarea class="form-control" name="keterangan" id="keterangan" rows="3"></textarea>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Simpan
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="resetForm()">
                            <i class="fas fa-times me-2"></i>Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Data Satuan</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th width="5%">No</th>
                                <th>Nama Satuan</th>
                                <th>Keterangan</th>
                                <th width="15%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            foreach ($satuan_list as $s): 
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><strong><?= $s['nama_satuan'] ?></strong></td>
                                <td><?= $s['keterangan'] ?: '-' ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick="editSatuan(<?= $s['id'] ?>, '<?= addslashes($s['nama_satuan']) ?>', '<?= addslashes($s['keterangan']) ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Yakin hapus satuan ini?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($satuan_list)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">Belum ada data satuan</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function editSatuan(id, nama, keterangan) {
    $('#formTitle').text('Edit Satuan');
    $('#satuanId').val(id);
    $('#nama_satuan').val(nama);
    $('#keterangan').val(keterangan);
    $('#nama_satuan').focus();
}

function resetForm() {
    $('#formTitle').text('Tambah Satuan');
    $('#formSatuan')[0].reset();
    $('#satuanId').val('');
}
</script>

<?php
require_once '../../includes/footer.php';
?>