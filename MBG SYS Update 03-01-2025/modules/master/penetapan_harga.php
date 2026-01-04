<?php
$page_title = 'Penetapan Harga';
require_once '../../includes/header.php';
require_role(['admin', 'gudang']);

require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';

// Get current user role
$current_role = $_SESSION['user']['role'];
?>

<div class="row">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/dashboard/index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Penetapan Harga</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-tags me-2"></i>Penetapan Harga Produk</h5>
                <div>
                    <button class="btn btn-success" onclick="exportExcel()">
                        <i class="fas fa-file-excel me-2"></i>Export Excel
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Keterangan:</strong>
                    <ul class="mb-0 mt-2">
                        <li><strong>Harga Beli:</strong> Harga pembelian terakhir dari modul pembelanjaan</li>
                        <li><strong>Harga Jual 1:</strong> Dapat diinput oleh Staf Inventori</li>
                        <li><strong>Harga Jual 2:</strong> Dapat diinput oleh Admin</li>
                        <li><strong>Harga Jual 3:</strong> Harga dari hasil scraping pasar (read-only)</li>
                    </ul>
                </div>

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
                </div>
                
                <div class="table-responsive">
                    <table id="tableHarga" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th width="5%">No</th>
                                <th>Kode</th>
                                <th>Nama Produk</th>
                                <th>Jenis</th>
                                <th>Kategori</th>
                                <th>Satuan</th>
                                <th>Harga Beli</th>
                                <th>Harga Jual 1</th>
                                <th>Harga Jual 2</th>
                                <th>Harga Jual 3<br><small>(Scraping)</small></th>
                                <th width="8%">Aksi</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Edit Harga -->
<div class="modal fade" id="modalHarga" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formHarga">
                <input type="hidden" name="id" id="produkId">
                <div class="modal-header">
                    <h5 class="modal-title">Penetapan Harga</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Kode Produk</label>
                            <p id="viewKode" class="form-control-plaintext"></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nama Produk</label>
                            <p id="viewNama" class="form-control-plaintext"></p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Jenis</label>
                            <p id="viewJenis" class="form-control-plaintext"></p>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Kategori</label>
                            <p id="viewKategori" class="form-control-plaintext"></p>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Satuan</label>
                            <p id="viewSatuan" class="form-control-plaintext"></p>
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Harga Beli <small class="text-muted">(dari pembelanjaan)</small></label>
                                <input type="text" class="form-control" id="viewHargaBeli" readonly>
                                <small class="text-muted">Otomatis dari pembelanjaan terakhir</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Harga Jual 3 <small class="text-muted">(dari scraping)</small></label>
                                <input type="text" class="form-control" id="viewHargaScraping" readonly>
                                <small class="text-muted" id="scrapingInfo"></small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Harga Jual 1 
                                    <?php if ($current_role == 'gudang'): ?>
                                        <span class="badge bg-success">Editable</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Read-only</span>
                                    <?php endif; ?>
                                </label>
                                <input type="text" class="form-control format-rupiah" name="harga_jual_1" id="harga_jual_1" 
                                    <?= $current_role != 'gudang' ? 'readonly' : '' ?>>
                                <small class="text-muted">Diinput oleh Staf Inventori</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Harga Jual 2 
                                    <?php if ($current_role == 'admin'): ?>
                                        <span class="badge bg-success">Editable</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Read-only</span>
                                    <?php endif; ?>
                                </label>
                                <input type="text" class="form-control format-rupiah" name="harga_jual_2" id="harga_jual_2" 
                                    <?= $current_role != 'admin' ? 'readonly' : '' ?>>
                                <small class="text-muted">Diinput oleh Admin</small>
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

<?php
$extra_js = "
<script src='https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js'></script>
<script src='https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
<script>
let table;

$(document).ready(function() {
    table = $('#tableHarga').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '" . BASE_URL . "/modules/master/penetapan_harga_ajax.php',
            type: 'POST',
            data: function(d) {
                d.jenis = $('#filterJenis').val();
                d.kategori = $('#filterKategori').val();
            }
        },
        columns: [
            { data: 'no', orderable: false },
            { data: 'kode_produk' },
            { data: 'nama_produk' },
            { data: 'jenis' },
            { data: 'kategori' },
            { data: 'satuan' },
            { data: 'harga_beli' },
            { data: 'harga_jual_1' },
            { data: 'harga_jual_2' },
            { data: 'harga_jual_3' },
            { data: 'aksi', orderable: false }
        ],
        order: [[1, 'asc']]
    });
    
    // Filters
    $('#filterJenis, #filterKategori').on('change', function() {
        table.ajax.reload();
    });
    
    // Load kategori when jenis changes
    $('#filterJenis').on('change', function() {
        let jenisId = $(this).val();
        
        if (jenisId) {
            $.ajax({
                url: '" . BASE_URL . "/modules/master/produk_ajax.php',
                type: 'POST',
                data: { action: 'get_kategori', jenis_id: jenisId },
                success: function(response) {
                    let options = '<option value=\"\">Semua Kategori</option>';
                    response.forEach(function(item) {
                        options += '<option value=\"' + item.id + '\">' + item.nama_kategori + '</option>';
                    });
                    $('#filterKategori').html(options);
                }
            });
        } else {
            $('#filterKategori').html('<option value=\"\">Semua Kategori</option>');
        }
    });
    
    // Submit form
    $('#formHarga').on('submit', function(e) {
        e.preventDefault();
        
        let formData = new FormData(this);
        formData.append('action', 'save');
        
        // Convert rupiah to number
        let harga1 = $('#harga_jual_1').val().replace(/[^0-9]/g, '');
        let harga2 = $('#harga_jual_2').val().replace(/[^0-9]/g, '');
        formData.set('harga_jual_1', harga1);
        formData.set('harga_jual_2', harga2);
        
        $.ajax({
            url: '" . BASE_URL . "/modules/master/penetapan_harga_ajax.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#modalHarga').modal('hide');
                    table.ajax.reload();
                    Swal.fire('Berhasil!', response.message, 'success');
                } else {
                    Swal.fire('Error!', response.message, 'error');
                }
            }
        });
    });
});

function editHarga(id) {
    $.ajax({
        url: '" . BASE_URL . "/modules/master/penetapan_harga_ajax.php',
        type: 'POST',
        data: { action: 'get', id: id },
        success: function(response) {
            if (response.success) {
                let data = response.data;
                
                $('#produkId').val(data.id);
                $('#viewKode').text(data.kode_produk);
                $('#viewNama').text(data.nama_produk);
                $('#viewJenis').text(data.nama_jenis);
                $('#viewKategori').text(data.nama_kategori);
                $('#viewSatuan').text(data.nama_satuan);
                
                $('#viewHargaBeli').val(formatRupiah(data.harga_beli));
                $('#harga_jual_1').val(formatRupiah(data.harga_jual_1));
                $('#harga_jual_2').val(formatRupiah(data.harga_jual_2));
                
                // Display scraped price
                if (data.harga_scraping) {
                    $('#viewHargaScraping').val(formatRupiah(data.harga_scraping));
                    $('#scrapingInfo').text('Pasar: ' + data.pasar_scraping + ' (Update: ' + data.scraping_date + ')');
                } else {
                    $('#viewHargaScraping').val('Tidak ada data');
                    $('#scrapingInfo').text('Belum ada data scraping');
                }
                
                $('#modalHarga').modal('show');
            }
        }
    });
}

function exportExcel() {
    window.open('" . BASE_URL . "/modules/master/penetapan_harga_export.php', '_blank');
}
</script>
";

$extra_css = "
<link rel='stylesheet' href='https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css'>
";

require_once '../../includes/footer.php';
?>
