<?php
// modules/pembelanjaan/add.php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';
require_once '../../helpers/MenuHarianHelper.php';

checkLogin();
checkRole(['admin', 'koperasi']);

$action = 'simpan';
$page_title = 'Tambah Pembelanjaan';
$no_belanja = generate_number('BLJ', 'pembelanjaan', 'no_pembelanjaan');

// Check for menu_id to prefill items
$prefill_items = [];
$menu_keterangan = '';
if (isset($_GET['menu_id'])) {
    $menuHelper = new MenuHarianHelper();
    $menu_id = intval($_GET['menu_id']);
    $menu = $menuHelper->getMenuWithDetails($menu_id);
    
    if ($menu) {
        $menu_keterangan = "Pembelanjaan untuk Menu: " . $menu['nama_menu'] . " (" . format_tanggal($menu['tanggal_menu']) . ")";
        
        foreach ($menu['details'] as $item) {
            if ($item['qty_to_purchase'] > 0) {
                // Get market recommendation for lowest price
                $rec = $menuHelper->getMarketRecommendation($item['produk_id'], $menu['tanggal_menu']);
                $price = $rec ? $rec['harga_terendah'] : 0;
                
                $prefill_items[] = [
                    'produk_id' => $item['produk_id'],
                    'qty' => $item['qty_to_purchase'],
                    'harga' => $price,
                    'market_name' => $rec ? $rec['nama_pasar'] : ''
                ];
            }
        }
    }
}

include '../../includes/header.php';
// Echo CSS manually since header variable support is unconfirmed
echo '<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />';
include '../../includes/navbar.php';
include '../../includes/sidebar.php';
?>

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/modules/dashboard/index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="list.php">Data Pembelanjaan</a></li>
        <li class="breadcrumb-item active">Tambah</li>
    </ol>
</nav>

<form id="formBelanja" enctype="multipart/form-data">
    <div class="row">
        <!-- Header Card -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-800 text-dark">
                        <i class="fas fa-info-circle me-2 text-primary opacity-50"></i> Informasi Belanja
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">No Pembelanjaan</label>
                        <input type="text" class="form-control bg-light border-0 fw-bold" name="no_pembelanjaan" value="<?= $no_belanja ?>" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Tanggal <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Supplier</label>
                        <select class="form-select select2" name="supplier_id">
                            <option value="">-- Pilih Supplier (Optional) --</option>
                            <?php
                            $suppliers = db_get_all("SELECT * FROM supplier WHERE status='aktif' ORDER BY nama_supplier");
                            foreach($suppliers as $s) {
                                echo "<option value='{$s['id']}'>{$s['nama_supplier']}</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">Periode Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="periode_type" id="periode_type" required>
                                <option value="harian">Harian</option>
                                <option value="mingguan">Mingguan</option>
                                <option value="bulanan">Bulanan</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">Periode Value</label>
                            <input type="text" class="form-control bg-light border-0" name="periode_value" id="periode_value" placeholder="Auto" readonly>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Bukti Belanja</label>
                        <input type="file" class="form-control" name="bukti_belanja" accept="image/*,.pdf">
                    </div>

                    <div class="mb-0">
                        <label class="form-label small fw-bold text-muted">Keterangan</label>
                        <textarea class="form-control" name="keterangan" rows="3" placeholder="Tambahkan catatan..."><?= isset($menu_keterangan) ? $menu_keterangan : '' ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Items Card -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-800 text-dark">
                        <i class="fas fa-shopping-basket me-2 text-primary opacity-50"></i> Item Belanja
                    </h5>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-success btn-sm rounded-pill px-3" id="btn-browse-request">
                            <i class="fas fa-file-import me-1"></i> Ambil dari Request
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" id="btn-add-item">
                            <i class="fas fa-plus me-1"></i> Tambah Item
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="table-detail">
                            <thead class="bg-light">
                                <tr>
                                    <th width="5%" class="text-center py-3 border-0">No</th>
                                    <th width="40%" class="py-3 border-0">Produk</th>
                                    <th width="20%" class="py-3 border-0">Harga Satuan</th>
                                    <th width="10%" class="text-center py-3 border-0">Qty</th>
                                    <th width="20%" class="py-3 border-0 text-end">Subtotal</th>
                                    <th width="5%" class="text-center py-3 border-0"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Rows will be added by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white p-4 border-top">
                    <div class="row align-items-center">
                        <div class="col-8">
                            <h5 class="mb-0 text-muted small fw-bold text-uppercase">Total Keseluruhan</h5>
                            <input type="text" class="form-control-plaintext h4 fw-800 text-primary mb-0 p-0" id="total_grand" readonly value="Rp 0">
                            <input type="hidden" name="total_belanja" id="grand_total_val" value="0">
                        </div>
                        <div class="col-4 d-flex gap-2 justify-content-end">
                            <a href="list.php" class="btn btn-outline-secondary px-4">Batal</a>
                            <button type="submit" class="btn btn-primary px-4 shadow-sm">
                                <i class="fas fa-save me-2"></i> Simpan
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Template Row for JS (Inside Form to ensure DOM presence) -->
    <template id="row-template">
        <tr>
            <td class="row-no">1</td>
            <td>
                <select class="form-control select-produk" name="produk_id[]" required onchange="produkChanged(this)">
                    <option value="">Pilih Produk</option>
                    <?php
                    $produk = db_get_all("SELECT p.*, s.nama_satuan FROM produk p LEFT JOIN satuan s ON p.satuan_id = s.id WHERE p.status_produk='running' ORDER BY p.nama_produk");
                    foreach($produk as $p) {
                        echo "<option value='{$p['id']}' data-satuan='{$p['nama_satuan']}' data-harga='{$p['harga_estimasi']}'>{$p['nama_produk']} ({$p['kode_produk']})</option>";
                    }
                    ?>
                </select>
            </td>
            <td>
                <input type="text" class="form-control input-harga format-rupiah" name="harga_satuan_view[]" onkeyup="cals(this)" placeholder="0">
                <input type="hidden" class="input-harga-val" name="harga_satuan[]">
            </td>
            <td>
                <input type="number" step="0.01" class="form-control input-qty" name="qty[]" onkeyup="cals(this)" onchange="cals(this)" value="1" min="0.1" required>
            </td>
            <td>
                <input type="text" class="form-control input-satuan" readonly>
            </td>
            <td>
                <input type="text" class="form-control input-subtotal" readonly>
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    </template>
</form>

<?php
$json_items = json_encode($prefill_items ?? []);

$extra_js = '
</script>

<!-- Modal Browse Request -->
<div class="modal fade" id="modalRequest" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Ambil dari Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">No Request</th>
                                <th>Tanggal</th>
                                <th>Kantor</th>
                                <th>Status</th>
                                <th>Keperluan</th>
                                <th class="text-end pe-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="listRequestContainer">
                            <!-- Loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
                <div id="loadingRequest" class="text-center py-4 d-none">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
                <div id="emptyRequest" class="text-center py-4 d-none">
                    <p class="text-muted mb-0">Tidak ada request yang siap diproses</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    var prefillItems = ' . $json_items . ';

    $(document).ready(function() {
        if ($.fn.select2) {
            $(".select2").select2();
        }
        updatePeriodeValue();
        
        // Handle prefill or default row
        if (prefillItems && prefillItems.length > 0) {
            prefillItems.forEach(function(item) {
                addRowWithData(item);
            });
        } else {
            // Add one initial row if no prefill
            addRow();
        }

        $("#periode_type, input[name=tanggal]").change(function() {
            updatePeriodeValue();
        });

        $("#btn-add-item").click(function() {
            addRow();
        });
        
        // Open Modal Request
        $("#btn-browse-request").click(function() {
            $("#modalRequest").modal("show");
            loadPendingRequests();
        });

        $("#formBelanja").submit(function(e) {
            e.preventDefault();
            
            // Validate minimum 1 item
            if($("#table-detail tbody tr").length == 0) {
                 Swal.fire("Error", "Minimal harus ada 1 barang belanjaan", "error");
                 return;
            }

            var formData = new FormData(this);
            formData.append("action", "save_pembelanjaan");

            $.ajax({
                url: "save.php",
                type: "POST",
                data: formData,
                contentType: false,
                processData: false,
                success: function(res) {
                    if(res.status == "success") {
                        Swal.fire({
                            title: "Berhasil!",
                            text: "Pembelanjaan tersimpan",
                            icon: "success"
                        }).then(() => {
                            window.location.href = "detail.php?id=" + res.id;
                        });
                    } else {
                        Swal.fire("Gagal", res.message, "error");
                    }
                },
                error: function() {
                    Swal.fire("Error", "Gagal menghubungi server", "error");
                }
            });
        });
    });

    function loadPendingRequests() {
        $("#listRequestContainer").empty();
        $("#loadingRequest").removeClass("d-none");
        $("#emptyRequest").addClass("d-none");

        $.ajax({
            url: "get_pending_requests.php",
            type: "GET",
            dataType: "json",
            success: function(res) {
                $("#loadingRequest").addClass("d-none");
                if(res.status == "success" && res.data.length > 0) {
                    res.data.forEach(function(req) {
                        var html = `
                            <tr>
                                <td class="ps-4 fw-bold text-primary">${req.no_request}</td>
                                <td>${req.tanggal_request}</td>
                                <td>${req.nama_kantor}</td>
                                <td><span class="badge ${req.status == \'diproses\' ? \'bg-primary\' : \'bg-warning\'}">${req.status.toUpperCase()}</span></td>
                                <td>${req.keperluan}</td>
                                <td class="text-end pe-4">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="importRequest(\'${req.id}\')">
                                        <i class="fas fa-plus me-1"></i> Pilih
                                    </button>
                                </td>
                            </tr>
                        `;
                        $("#listRequestContainer").append(html);
                    });
                } else {
                    $("#emptyRequest").removeClass("d-none");
                }
            },
            error: function() {
                $("#loadingRequest").addClass("d-none");
                $("#emptyRequest").removeClass("d-none").find("p").text("Gagal memuat data");
            }
        });
    }

    function importRequest(requestId) {
        Swal.fire({
            title: \'Memproses...\',
            didOpen: () => { Swal.showLoading() }
        });

        $.ajax({
            url: "get_request_items.php",
            type: "POST",
            data: { request_id: requestId },
            dataType: "json",
            success: function(res) {
                Swal.close();
                if(res.status == "success") {
                    $("#modalRequest").modal("hide");
                    
                    // Clear empty initial row if exists and is empty
                    var rows = $("#table-detail tbody tr");
                    if(rows.length === 1) {
                         var firstRowVal = rows.first().find(".select-produk").val();
                         if(!firstRowVal) rows.first().remove();
                    }

                    res.data.forEach(function(item) {
                        var data = {
                            produk_id: item.produk_id,
                            qty: item.qty_request,
                            harga: item.harga_terakhir || 0
                        };
                        addRowWithData(data);
                    });
                    
                    const Toast = Swal.mixin({
                        toast: true,
                        position: \'top-end\',
                        showConfirmButton: false,
                        timer: 3000
                    });
                    Toast.fire({
                        icon: \'success\',
                        title: res.data.length + \' item ditambahkan\'
                    });
                } else {
                    Swal.fire("Gagal", res.message, "error");
                }
            },
            error: function() {
                Swal.close();
                Swal.fire("Error", "Gagal mengambil detail request", "error");
            }
        });
    }

    function updatePeriodeValue() {
        var type = $("#periode_type").val();
        var dateVal = $("input[name=tanggal]").val();
        var result = "";
        
        if(!dateVal) return;

        var d = new Date(dateVal);
        var year = d.getFullYear();
        var month = ("0" + (d.getMonth() + 1)).slice(-2);
        
        if(type == "harian") {
            result = dateVal;
        } else if(type == "bulanan") {
            result = year + "-" + month;
        } else if(type == "mingguan") {
            // Simple logic for week number
            var onejan = new Date(d.getFullYear(), 0, 1);
            var week = Math.ceil((((d.getTime() - onejan.getTime()) / 86400000) + onejan.getDay() + 1) / 7);
            result = "Week-" + week + "-" + year;
        }
        
        $("#periode_value").val(result);
    }

    function addRow() {
        var template = document.getElementById("row-template");
        if (!template) {
            console.error("Template not found!");
            return;
        }
        var clone = template.content.cloneNode(true);
        $("#table-detail tbody").append(clone);
        reorderRows();
    }

    function addRowWithData(data) {
        var template = document.getElementById("row-template");
        if (!template) {
            console.error("Template not found for data row!");
            return;
        }
        var clone = template.content.cloneNode(true);
        $("#table-detail tbody").append(clone);
        
        var row = $("#table-detail tbody tr").last();
        
        // Set values
        var select = row.find(".select-produk");
        select.val(data.produk_id);
        
        // Trigger default population first
        produkChanged(select[0]);
        
        // Override with our data (qty request)
        row.find(".input-qty").val(data.qty);
        
        if (data.harga > 0) {
            row.find(".input-harga-val").val(data.harga);
            row.find(".input-harga").val(formatRupiahJS(data.harga));
            
            // Add market info if available
            if (data.market_name) {
                // Determine where to show market name.
                // We could add a hidden note or something in the future.
            }
        }
        
        // Recalculate subtotal
        cals(select[0]);
        
        reorderRows();
    }

    function removeRow(btn) {
        $(btn).closest("tr").remove();
        reorderRows();
        calculateGrandTotal();
    }

    function reorderRows() {
        var no = 1;
        $("#table-detail tbody tr").each(function() {
            $(this).find(".row-no").text(no++);
        });
    }

    function produkChanged(select) {
        var option = $(select).find("option:selected");
        var satuan = option.data("satuan");
        var harga = option.data("harga");
        
        var row = $(select).closest("tr");
        row.find(".input-satuan").val(satuan);
        
        // Set Default Harga if empty
        if(row.find(".input-harga").val() == "") {
             row.find(".input-harga").val(formatRupiahJS(harga));
             row.find(".input-harga-val").val(harga);
         }
        cals(select);
    }

    function cals(el) {
        var row = $(el).closest("tr");
        var qty = parseFloat(row.find(".input-qty").val()) || 0;
        
        // Get harga raw
        var hargaStr = row.find(".input-harga").val().replace(/[^0-9]/g, "");
        var harga = parseFloat(hargaStr) || 0;
        row.find(".input-harga-val").val(harga);
        row.find(".input-harga").val(formatRupiahJS(harga));

        var subtotal = qty * harga;
        row.find(".input-subtotal").val(formatRupiahJS(subtotal));
        
        calculateGrandTotal();
    }

    function calculateGrandTotal() {
        var total = 0;
        $(".input-subtotal").each(function() {
            var val = $(this).val().replace(/[^0-9]/g, "");
            total += parseFloat(val) || 0;
        });
        
        $("#total_grand").val(formatRupiahJS(total));
        $("#grand_total_val").val(total);
    }

    function formatRupiahJS(angka) {
        var number_string = angka.toString(),
            sisa    = number_string.length % 3,
            rupiah  = number_string.substr(0, sisa),
            ribuan  = number_string.substr(sisa).match(/\d{3}/g);
            
        if (ribuan) {
            separator = sisa ? "." : "";
            rupiah += separator + ribuan.join(".");
        }
        return "Rp " + rupiah;
    }
</script>
';

include '../../includes/footer.php';
?>
