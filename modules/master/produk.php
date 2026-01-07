<?php
$page_title = 'Manajemen Produk';
require_once '../../includes/header.php';
require_role(['admin', 'koperasi', 'gudang']);

require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';

// Get current user role
$user_role = $_SESSION['user']['role'] ?? 'guest';
?>

<div class="row">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/dashboard/index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Master Produk</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-box me-2"></i>Data Produk</h5>
                <div>
                    <button class="btn btn-info me-2" onclick="importExcel()">
                        <i class="fas fa-file-import me-2"></i>Import Excel
                    </button>
                    <button class="btn btn-success me-2" onclick="exportExcel()">
                        <i class="fas fa-file-excel me-2"></i>Export Excel
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalProduk" onclick="addProduk()">
                        <i class="fas fa-plus me-2"></i>Tambah Produk
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <select class="form-select" id="filterJenis">
                            <option value="">Semua Jenis</option>
                            <?php
                            $jenis = db_get_all("SELECT * FROM jenis_barang WHERE status = 'aktif' ORDER BY nama_jenis");
                            foreach ($jenis as $j) {
                                echo "<option value='{$j['id']}'>{$j['nama_jenis']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="filterKategori">
                            <option value="">Semua Kategori</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="filterStatus">
                            <option value="">Semua Status</option>
                            <option value="persiapan">Persiapan</option>
                            <option value="running">Running</option>
                            <option value="nonaktif">Non-aktif</option>
                        </select>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table id="tableProduk" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th width="5%">No</th>
                                <th>Gambar</th>
                                <th>Kode</th>
                                <th>Nama Produk</th>
                                <th>Jenis</th>
                                <th>Kategori</th>
                                <th>Satuan</th>
                                <th>Supplier</th>
                                <th>Harga Beli</th>
                                <th>Harga Jual 1</th>
                                <th>Harga Jual 2</th>
                                <th>HET (Harga Eceran Tertinggi)</th>
                                <th>Tipe</th>
                                <th>Status</th>
                                <th width="12%">Aksi</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Produk -->
<div class="modal fade" id="modalProduk" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form id="formProduk" enctype="multipart/form-data">
                <input type="hidden" name="id" id="produkId">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalProdukTitle">Tambah Produk</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Kode Produk <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="kode_produk" id="kode_produk" readonly required>
                                        <small class="text-muted">Otomatis generate</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Jenis Barang <span class="text-danger">*</span></label>
                                        <select class="form-select" name="jenis_barang_id" id="jenis_barang_id" required>
                                            <option value="">Pilih Jenis</option>
                                            <?php
                                            $jenis = db_get_all("SELECT * FROM jenis_barang WHERE status = 'aktif' ORDER BY nama_jenis");
                                            foreach ($jenis as $j) {
                                                echo "<option value='{$j['id']}'>{$j['nama_jenis']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Nama Produk <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nama_produk" id="nama_produk" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Kategori <span class="text-danger">*</span></label>
                                        <select class="form-select" name="kategori_id" id="kategori_id" required>
                                            <option value="">Pilih Kategori</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Satuan <span class="text-danger">*</span></label>
                                        <select class="form-select" name="satuan_id" id="satuan_id" required>
                                            <option value="">Pilih Satuan</option>
                                            <?php
                                            $satuan = db_get_all("SELECT * FROM satuan ORDER BY nama_satuan");
                                            foreach ($satuan as $s) {
                                                echo "<option value='{$s['id']}'>{$s['nama_satuan']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Tipe Item <span class="text-danger">*</span></label>
                                        <select class="form-select" name="tipe_item" id="tipe_item" required>
                                            <option value="">Pilih Tipe</option>
                                            <option value="stok">Stok</option>
                                            <option value="distribusi">Distribusi</option>
                                            <option value="khusus">Khusus</option>
                                        </select>
                                        <small class="text-muted">Stok=disimpan, Distribusi=langsung kirim</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Status Produk</label>
                                        <select class="form-select" name="status_produk" id="status_produk">
                                            <option value="persiapan">Persiapan</option>
                                            <option value="running">Running</option>
                                            <option value="nonaktif">Non-aktif</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Harga Estimasi</label>
                                        <input type="text" class="form-control format-rupiah" name="harga_estimasi" id="harga_estimasi">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Stok Minimum</label>
                                        <input type="number" class="form-control" name="stok_minimum" id="stok_minimum" value="0">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Masa Kadaluarsa (Hari)</label>
                                        <input type="number" class="form-control" name="masa_kadaluarsa_hari" id="masa_kadaluarsa_hari">
                                        <small class="text-muted">Untuk bahan baku</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Supplier Utama</label>
                                        <select class="form-select" name="supplier_id" id="supplier_id">
                                            <option value="">Pilih Supplier</option>
                                            <?php
                                            $suppliers = db_get_all("SELECT * FROM supplier WHERE status = 'aktif' ORDER BY nama_supplier ASC");
                                            foreach ($suppliers as $sup) {
                                                echo "<option value='{$sup['id']}'>{$sup['nama_supplier']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($user_role == 'admin'): ?>
                            <hr>
                            <h6 class="mb-3"><i class="fas fa-dollar-sign me-2"></i>Penetapan Harga</h6>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Harga Beli</label>
                                        <input type="text" class="form-control format-rupiah" name="harga_beli" id="harga_beli">
                                        <small class="text-muted">Harga pembelian</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Harga Jual 1</label>
                                        <input type="text" class="form-control format-rupiah" name="harga_jual_1" id="harga_jual_1">
                                        <small class="text-muted">Diinput staf inventori</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Harga Jual 2</label>
                                        <input type="text" class="form-control format-rupiah" name="harga_jual_2" id="harga_jual_2">
                                        <small class="text-muted">Diinput admin</small>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label class="form-label">Spesifikasi</label>
                                <textarea class="form-control" name="spesifikasi" id="spesifikasi" rows="2"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Deskripsi</label>
                                <textarea class="form-control" name="deskripsi" id="deskripsi" rows="3"></textarea>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Gambar Produk</label>
                                <input type="file" class="form-control" name="gambar" id="gambar" accept="image/*">
                                <div id="previewGambar" class="mt-3 text-center">
                                    <img src="<?= BASE_URL ?>/assets/img/no-image.png" class="img-thumbnail" width="200" id="imgPreview">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Barcode</label>
                                <input type="text" class="form-control" name="barcode" id="barcode" readonly>
                                <button type="button" class="btn btn-sm btn-secondary mt-2" onclick="generateBarcode()">
                                    <i class="fas fa-barcode me-1"></i>Generate Barcode
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Import Excel -->
<div class="modal fade" id="modalImport" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formImport" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Import Data Produk</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">File Excel (.xlsx)</label>
                        <input type="file" class="form-control" name="file_excel" accept=".xlsx" required>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <a href="<?= BASE_URL ?>/modules/master/produk_template.php" class="alert-link">Download Template Excel</a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload me-2"></i>Upload & Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extra_js = "
<script src='https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js'></script>
<script src='https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
<script>
let table;

$(document).ready(function() {
    table = $('#tableProduk').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '" . BASE_URL . "/modules/master/produk_ajax.php',
            type: 'POST',
            data: function(d) {
                d.jenis = $('#filterJenis').val();
                d.kategori = $('#filterKategori').val();
                d.status = $('#filterStatus').val();
            }
        },
        columns: [
            { data: 'no', orderable: false },
            { data: 'gambar', orderable: false },
            { data: 'kode_produk' },
            { data: 'nama_produk' },
            { data: 'jenis' },
            { data: 'kategori' },
            { data: 'satuan' },
            { data: 'supplier' },
            { data: 'harga_beli' },
            { data: 'harga_jual_1' },
            { data: 'harga_jual_2' },
            { data: 'harga_jual_3' },
            { data: 'tipe_item' },
            { data: 'status' },
            { data: 'aksi', orderable: false }
        ],
        order: [[2, 'asc']]
    });
    
    // Filters
    $('#filterJenis, #filterKategori, #filterStatus').on('change', function() {
        table.ajax.reload();
    });
    
    // Load kategori when jenis changes
    $('#filterJenis, #jenis_barang_id').on('change', function() {
        let jenisId = $(this).val();
        let targetSelect = $(this).attr('id') == 'filterJenis' ? '#filterKategori' : '#kategori_id';
        
        if (jenisId) {
            $.ajax({
                url: '" . BASE_URL . "/modules/master/produk_ajax.php',
                type: 'POST',
                data: { action: 'get_kategori', jenis_id: jenisId },
                success: function(response) {
                    let res = response;
                    let options = '<option value=\"\">Pilih Kategori</option>';
                    res.forEach(function(item) {
                        options += '<option value=\"' + item.id + '\">' + item.nama_kategori + '</option>';
                    });
                    $(targetSelect).html(options);
                }
            });
        }
        
        // Generate kode when jenis changes in form
        if ($(this).attr('id') == 'jenis_barang_id') {
            $.ajax({
                url: '" . BASE_URL . "/modules/master/produk_ajax.php',
                type: 'POST',
                data: { action: 'generate_code', jenis_id: jenisId },
                success: function(response) {
                    let res = response;
                    $('#kode_produk').val(res.code);
                }
            });
        }
    });
    
    // Preview gambar
    $('#gambar').on('change', function() {
        let file = this.files[0];
        if (file) {
            let reader = new FileReader();
            reader.onload = function(e) {
                $('#imgPreview').attr('src', e.target.result);
            }
            reader.readAsDataURL(file);
        }
    });
    
    // Submit form
    $('#formProduk').on('submit', function(e) {
        e.preventDefault();
        
        let formData = new FormData(this);
        formData.append('action', 'save');
        
        // Convert rupiah to number
        let harga = $('#harga_estimasi').val().replace(/[^0-9]/g, '');
        formData.set('harga_estimasi', harga);
        
        // Convert price fields to number (if fields exist)
        if ($('#harga_beli').length) {
            let hargaBeli = $('#harga_beli').val().replace(/[^0-9]/g, '');
            let hargaJual1 = $('#harga_jual_1').val().replace(/[^0-9]/g, '');
            let hargaJual2 = $('#harga_jual_2').val().replace(/[^0-9]/g, '');
            formData.set('harga_beli', hargaBeli);
            formData.set('harga_jual_1', hargaJual1);
            formData.set('harga_jual_2', hargaJual2);
        }
        
        $.ajax({
            url: '" . BASE_URL . "/modules/master/produk_ajax.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                let res = response;
                if (res.success) {
                    $('#modalProduk').modal('hide');
                    table.ajax.reload();
                    Swal.fire('Berhasil!', res.message, 'success');
                } else {
                    Swal.fire('Error!', res.message, 'error');
                }
            }
        });
    });
    
    // Import form
    $('#formImport').on('submit', function(e) {
        e.preventDefault();
        
        let formData = new FormData(this);
        formData.append('action', 'import');
        
        Swal.fire({
            title: 'Mengimport data...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        $.ajax({
            url: '" . BASE_URL . "/modules/master/produk_ajax.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                let res = response;
                Swal.close();
                
                if (res.success) {
                    $('#modalImport').modal('hide');
                    table.ajax.reload();
                    Swal.fire('Berhasil!', res.message, 'success');
                } else {
                    Swal.fire('Error!', res.message, 'error');
                }
            }
        });
    });
});

function addProduk() {
    $('#modalProdukTitle').text('Tambah Produk');
    $('#formProduk')[0].reset();
    $('#produkId').val('');
    $('#imgPreview').attr('src', '" . BASE_URL . "/assets/img/no-image.png');
    $('#kategori_id').html('<option value=\"\">Pilih Kategori</option>');
    $('#supplier_id').val('');
    $('#barcode').val('');
}

function editProduk(id) {
    $.ajax({
        url: '" . BASE_URL . "/modules/master/produk_ajax.php',
        type: 'POST',
        data: { action: 'get', id: id },
        success: function(response) {
            let res = response;
            if (res.success) {
                let data = res.data;
                $('#modalProdukTitle').text('Edit Produk');
                $('#produkId').val(data.id);
                $('#kode_produk').val(data.kode_produk);
                $('#nama_produk').val(data.nama_produk);
                $('#jenis_barang_id').val(data.jenis_barang_id).trigger('change');
                
                // Wait for kategori to load
                setTimeout(function() {
                    $('#kategori_id').val(data.kategori_id);
                }, 500);
                
                $('#satuan_id').val(data.satuan_id);
                $('#tipe_item').val(data.tipe_item);
                $('#status_produk').val(data.status_produk);
                $('#harga_estimasi').val(formatRupiah(data.harga_estimasi));
                $('#stok_minimum').val(data.stok_minimum);
                $('#masa_kadaluarsa_hari').val(data.masa_kadaluarsa_hari);
                $('#spesifikasi').val(data.spesifikasi);
                $('#deskripsi').val(data.deskripsi);
                $('#supplier_id').val(data.supplier_id);
                $('#barcode').val(data.barcode);
                
                // Populate price fields if they exist
                if ($('#harga_beli').length) {
                    $('#harga_beli').val(formatRupiah(data.harga_beli));
                    $('#harga_jual_1').val(formatRupiah(data.harga_jual_1));
                    $('#harga_jual_2').val(formatRupiah(data.harga_jual_2));
                }
                
                if (data.gambar) {
                    $('#imgPreview').attr('src', '" . BASE_URL . "/assets/uploads/' + data.gambar);
                }
                
                $('#modalProduk').modal('show');
            }
        }
    });
}

function deleteProduk(id) {
    Swal.fire({
        title: 'Hapus Produk?',
        text: 'Data produk akan dihapus permanen!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '" . BASE_URL . "/modules/master/produk_ajax.php',
                type: 'POST',
                data: { action: 'delete', id: id },
                success: function(response) {
                    let res = response;
                    if (res.success) {
                        table.ajax.reload();
                        Swal.fire('Terhapus!', res.message, 'success');
                    } else {
                        Swal.fire('Error!', res.message, 'error');
                    }
                }
            });
        }
    });
}

function generateBarcode() {
    let barcode = 'BR' + Date.now();
    $('#barcode').val(barcode);
}

function importExcel() {
    $('#modalImport').modal('show');
}

function exportExcel() {
    window.open('" . BASE_URL . "/modules/master/produk_export.php', '_blank');
}

function viewDetail(id) {
    window.location.href = '" . BASE_URL . "/modules/master/produk_detail.php?id=' + id;
}
</script>
";

$extra_css = "
<link rel='stylesheet' href='https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css'>
";

require_once '../../includes/footer.php';
?>