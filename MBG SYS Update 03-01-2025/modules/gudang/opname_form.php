<?php
// modules/gudang/opname_form.php
$page_title = 'Form Opname';
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

checkLogin();
checkRole(['admin', 'gudang']);

$id = $_GET['id'] ?? null;
$opname = null;
$opname_details = [];

if ($id) {
    $opname = db_get_row("SELECT * FROM stok_opname WHERE id = " . db_escape($id));
    if (!$opname) {
        set_flash('error', 'Data tidak ditemukan.');
        header("Location: opname.php");
        exit;
    }
    if ($opname['status'] == 'final') {
        set_flash('warning', 'Dokumen sudah final dan tidak dapat diedit.');
        header("Location: opname_detail.php?id=$id");
        exit;
    }
    
    // Get saved details
    $opname_details = db_get_all("SELECT sod.*, p.nama_produk, p.kode_produk, gs.batch_number 
                                  FROM stok_opname_detail sod
                                  JOIN produk p ON sod.produk_id = p.id
                                  LEFT JOIN gudang_stok gs ON sod.gudang_stok_id = gs.id
                                  WHERE sod.opname_id = $id");
} else {
    // New Opname
    $opname = [
        'id' => '',
        'tanggal' => date('Y-m-d'),
        'nomor_dokumen' => generate_number('SO', 'stok_opname', 'nomor_dokumen'),
        'keterangan' => ''
    ];
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0"><?php echo $id ? 'Edit' : 'Buat'; ?> Stok Opname</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="opname.php" class="btn btn-secondary float-sm-right"><i class="fas fa-arrow-left"></i> Kembali</a>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Informasi Opname</h3>
                </div>
                <div class="card-body">
                    <form action="opname_process.php" method="POST" id="formOpname">
                        <input type="hidden" name="opname_id" value="<?php echo $opname['id']; ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nomor Dokumen</label>
                                <input type="text" class="form-control" name="nomor_dokumen" value="<?php echo $opname['nomor_dokumen']; ?>" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tanggal</label>
                                <input type="date" class="form-control" name="tanggal" value="<?php echo $opname['tanggal']; ?>" required>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Keterangan</label>
                                <textarea class="form-control" name="keterangan" rows="2"><?php echo $opname['keterangan']; ?></textarea>
                            </div>
                        </div>

                        <hr>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4>Detail Item</h4>
                            <!-- Button to load current stock -->
                            <button type="button" class="btn btn-info" id="btnLoadStock">
                                <i class="fas fa-sync"></i> Muat Stok Saat Ini
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered" id="tblDetail">
                                <thead>
                                    <tr>
                                        <th>Produk / Batch</th>
                                        <th width="150">Qty Sistem</th>
                                        <th width="150">Qty Fisik</th>
                                        <th width="150">Selisih</th>
                                        <th>Keterangan</th>
                                        <th width="50"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($opname_details): foreach($opname_details as $item): ?>
                                    <tr>
                                        <td>
                                            <input type="hidden" name="gudang_stok_id[]" value="<?php echo $item['gudang_stok_id']; ?>">
                                            <input type="hidden" name="produk_id[]" value="<?php echo $item['produk_id']; ?>">
                                            <strong><?php echo $item['kode_produk'] . ' - ' . $item['nama_produk']; ?></strong><br>
                                            <small class="text-muted">Batch: <?php echo $item['batch_number']; ?></small>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" class="form-control qty_sistem" name="qty_sistem[]" value="<?php echo $item['qty_sistem']; ?>" readonly>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" class="form-control qty_fisik" name="qty_fisik[]" value="<?php echo $item['qty_fisik']; ?>" required>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" class="form-control selisih" value="<?php echo $item['selisih']; ?>" readonly>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control" name="item_keterangan[]" value="<?php echo $item['keterangan']; ?>">
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-danger btn-sm btn-remove"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="6" class="text-center">
                                            Klik "Muat Stok Saat Ini" untuk mengisi tabel dengan data stok yang ada di gudang.
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="mt-4">
                            <button type="submit" name="action" value="draft" class="btn btn-secondary me-2">Simpan Draft</button>
                            <button type="button" class="btn btn-primary" id="btnFinalize">Finalisasi Opname</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </section>
</div>

<?php 
$extra_js = "
<script>
    $(document).ready(function() {
        // Function to load stock via AJAX
        $('#btnLoadStock').click(function() {
            if(!confirm('Ini akan menimpa data di tabel detail. Lanjutkan?')) return;
            
            $.ajax({
                url: 'ajax_get_current_stock.php',
                type: 'GET',
                success: function(data) {
                    try {
                        var html = '';
                        
                        $.each(data, function(index, item) {
                            html += `
                            <tr>
                                <td>
                                    <input type=\"hidden\" name=\"gudang_stok_id[]\" value=\"`+item.id+`\">
                                    <input type=\"hidden\" name=\"produk_id[]\" value=\"`+item.produk_id+`\">
                                    <strong>`+item.produk_label+`</strong><br>
                                    <small class=\"text-muted\">Batch: `+(item.batch_number || '-')+`</small>
                                </td>
                                <td>
                                    <input type=\"number\" step=\"0.01\" class=\"form-control qty_sistem\" name=\"qty_sistem[]\" value=\"`+item.qty_available+`\" readonly>
                                </td>
                                <td>
                                    <input type=\"number\" step=\"0.01\" class=\"form-control qty_fisik\" name=\"qty_fisik[]\" value=\"`+item.qty_available+`\" required>
                                </td>
                                <td>
                                    <input type=\"number\" step=\"0.01\" class=\"form-control selisih\" value=\"0\" readonly>
                                </td>
                                <td>
                                    <input type=\"text\" class=\"form-control\" name=\"item_keterangan[]\" placeholder=\"Keterangan...\">
                                </td>
                                <td>
                                    <button type=\"button\" class=\"btn btn-danger btn-sm btn-remove\"><i class=\"fas fa-trash\"></i></button>
                                </td>
                            </tr>
                            `;
                        });
                        
                        $('#tblDetail tbody').html(html);
                    } catch(e) {
                         alert('Error processing response: ' + e);
                    }
                },
                error: function(xhr) {
                    alert('Gagal memuat stok: ' + xhr.statusText);
                }
            });
        });

        // Calculate Difference
        $(document).on('input', '.qty_fisik', function() {
            var row = $(this).closest('tr');
            var sistem = parseFloat(row.find('.qty_sistem').val()) || 0;
            var fisik = parseFloat($(this).val()) || 0;
            var selisih = fisik - sistem;
            row.find('.selisih').val(selisih.toFixed(2));
            
            if(selisih != 0) {
                row.find('.selisih').addClass('text-danger fw-bold').removeClass('text-success');
            } else {
                row.find('.selisih').addClass('text-success').removeClass('text-danger fw-bold');
            }
        });

        // Remove Row
        $(document).on('click', '.btn-remove', function() {
            $(this).closest('tr').remove();
        });

        // Normalize Finalize Button
        $('#btnFinalize').click(function() {
            if(confirm('Apakah Anda yakin ingin menyelesaikan opname ini? Stok akan disesuaikan secara otomatis.')) {
                $('<input>').attr({
                    type: 'hidden',
                    name: 'action',
                    value: 'final'
                }).appendTo('#formOpname');
                $('#formOpname').submit();
            }
        });
    });
</script>
";
include '../../includes/footer.php'; 
?>
