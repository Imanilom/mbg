<?php
session_start();
require_once '../../config/database.php';
require_once '../../helpers/functions.php';
require_once '../../helpers/MenuHarianHelper.php';

// Check access
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin', 'koperasi'])) {
    $_SESSION['flash_type'] = 'error';
    $_SESSION['flash_message'] = 'Akses ditolak.';
    header('Location: ' . BASE_URL . '/modules/dashboard');
    exit();
}

$user = $_SESSION['user'];
$menuHelper = new MenuHarianHelper();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_planner') {
    try {
        $data = [
            'days' => [],
            'created_by' => $user['id']
        ];
        
        if (!empty($_POST['days'])) {
            foreach ($_POST['days'] as $day) {
                if (!empty($day['tanggal_menu']) && !empty($day['items'])) {
                    $dayData = [
                        'tanggal_menu' => $day['tanggal_menu'],
                        'nama_menu' => !empty($day['nama_menu']) ? $day['nama_menu'] : 'Menu ' . $day['tanggal_menu'],
                        'deskripsi' => $day['deskripsi'] ?? '',
                        'total_porsi' => intval($day['total_porsi'] ?? 1),
                        'kantor_id' => !empty($day['kantor_id']) ? intval($day['kantor_id']) : null,
                        'items' => []
                    ];
                    
                    foreach ($day['items'] as $item) {
                        if ((!empty($item['produk_id']) || !empty($item['resep_id'])) && !empty($item['qty_needed'])) {
                            $entry = [
                                'qty_needed' => floatval($item['qty_needed']),
                                'keterangan' => $item['keterangan'] ?? ''
                            ];
                            if (!empty($item['resep_id'])) {
                                $entry['resep_id'] = intval($item['resep_id']);
                            } else {
                                $entry['produk_id'] = intval($item['produk_id']);
                            }
                            $dayData['items'][] = $entry;
                        }
                    }
                    
                    if (!empty($dayData['items'])) {
                        $data['days'][] = $dayData;
                    }
                }
            }
        }
        
        if (empty($data['days'])) {
            throw new Exception('Minimal harus ada 1 hari perencanaan dengan item menu');
        }
        
        $menu_ids = $menuHelper->createMenu($data);
        
        if ($menu_ids) {
            // Log activity
            logActivity($conn, $user['id'], "Membuat perencanaan menu bulk (" . count($data['days']) . " hari)", 'menu', is_array($menu_ids) ? $menu_ids[0] : $menu_ids);
            
            $_SESSION['flash_type'] = 'success';
            $_SESSION['flash_message'] = 'Perencanaan menu berhasil disimpan!';
            header('Location: index.php');
            exit();
        } else {
            throw new Exception('Gagal menyimpan perencanaan menu');
        }
        
    } catch (Exception $e) {
        $_SESSION['flash_type'] = 'error';
        $_SESSION['flash_message'] = $e->getMessage();
    }
}

// Get products and recipes for selection
$products = db_get_all("SELECT p.*, s.nama_satuan, k.nama_kategori 
                        FROM produk p
                        INNER JOIN satuan s ON p.satuan_id = s.id
                        INNER JOIN kategori k ON p.kategori_id = k.id
                        WHERE p.status_produk = 'running'
                        ORDER BY p.nama_produk");

$recipes = db_get_all("SELECT * FROM resep WHERE status = 'aktif' ORDER BY nama_resep");
$offices = db_get_all("SELECT * FROM kantor WHERE status = 'aktif' ORDER BY nama_kantor");

$page_title = 'Planner Menu Harian';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';
?>

<style>
    .day-card { transition: all 0.3s ease; border-left: 5px solid #4e73df; }
    .day-card:hover { transform: translateY(-2px); box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important; }
    .item-row { border-bottom: 1px solid #e3e6f0; padding: 10px 0; }
    .item-row:last-child { border-bottom: none; }
    .bg-light-blue { background-color: #f8f9fc; }
    .sticky-planner-header { position: sticky; top: 0; z-index: 100; background: rgba(255,255,255,0.9); backdrop-filter: blur(5px); }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 sticky-planner-header py-2">
        <div>
            <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-calendar-alt me-2 text-primary"></i>Planner Menu Harian</h1>
            <p class="mb-0 text-muted">Rencanakan menu berbeda untuk setiap harinya dalam satu langkah</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary" onclick="addDayBlock()">
                <i class="fas fa-calendar-plus me-1"></i> Tambah Hari
            </button>
            <button type="submit" form="plannerForm" class="btn btn-primary shadow-sm">
                <i class="fas fa-save me-1"></i> Simpan Semua Perencanaan
            </button>
            <a href="index.php" class="btn btn-secondary text-white">
                <i class="fas fa-times me-1"></i> Batal
            </a>
        </div>
    </div>

    <?php show_flash(); ?>

    <form id="plannerForm" method="POST">
        <input type="hidden" name="action" value="save_planner">
        <div id="dayContainer">
            <!-- Day blocks will be added here -->
        </div>
    </form>
</div>

<!-- Modals -->
<?php include 'modals.php'; ?>

<script>
let dayIndex = 0;
let productModal;
let recipeModal;
let currentDayIndex = null;
let currentItemIndex = null;

const products = <?= json_encode($products) ?>;
const recipes = <?= json_encode($recipes) ?>;
const offices = <?= json_encode($offices) ?>;

document.addEventListener('DOMContentLoaded', function() {
    productModal = new bootstrap.Modal(document.getElementById('productModal'));
    recipeModal = new bootstrap.Modal(document.getElementById('recipeModal'));
    
    // Add initial day
    addDayBlock();
});

function addDayBlock() {
    const id = dayIndex++;
    let officeOptions = '<option value=\"\">Semua Kantor</option>';
    offices.forEach(o => {
        officeOptions += `<option value=\"${o.id}\">${o.nama_kantor}</option>`;
    });

    const html = `
        <div class=\"card shadow-sm mb-4 day-card\" id=\"day_block_${id}\">
            <div class=\"card-header bg-white d-flex justify-content-between align-items-center\">
                <h6 class=\"m-0 font-weight-bold text-primary\"><i class=\"fas fa-day me-2\"></i>Blok Perencanaan #${id + 1}</h6>
                <button type=\"button\" class=\"btn btn-sm btn-outline-danger border-0\" onclick=\"removeDayBlock(${id})\">
                    <i class=\"fas fa-trash me-1\"></i> Hapus Hari
                </button>
            </div>
            <div class=\"card-body\">
                <div class=\"row g-3\">
                    <div class=\"col-md-3\">
                        <label class=\"form-label small fw-bold\">Tanggal Menu <span class=\"text-danger\">*</span></label>
                        <input type=\"date\" name=\"days[${id}][tanggal_menu]\" class=\"form-control form-control-sm\" value=\"<?= date('Y-m-d') ?>\" required onchange=\"updateDayStock(${id})\">
                    </div>
                    <div class=\"col-md-3\">
                        <label class=\"form-label small fw-bold\">Jumlah Porsi <span class=\"text-danger\">*</span></label>
                        <input type=\"number\" name=\"days[${id}][total_porsi]\" id=\"total_porsi_${id}\" class=\"form-control form-control-sm\" min=\"1\" value=\"1\" required onchange=\"updateDayStock(${id})\">
                    </div>
                    <div class=\"col-md-3\">
                        <label class=\"form-label small fw-bold\">Kantor Tujuan</label>
                        <select name=\"days[${id}][kantor_id]\" class=\"form-select form-select-sm\">
                            ${officeOptions}
                        </select>
                    </div>
                    <div class=\"col-md-3\">
                        <label class=\"form-label small fw-bold\">Nama Menu</label>
                        <input type=\"text\" name=\"days[${id}][nama_menu]\" class=\"form-control form-control-sm\" placeholder=\"Contoh: Menu Senin Sehat\">
                    </div>
                    <div class=\"col-12\">
                        <textarea name=\"days[${id}][deskripsi]\" class=\"form-control form-control-sm\" rows=\"1\" placeholder=\"Deskripsi singkat (opsional)\"></textarea>
                    </div>
                </div>

                <div class=\"mt-4 border-top pt-3\">
                    <div class=\"d-flex justify-content-between align-items-center mb-2\">
                        <span class=\"small fw-bold text-uppercase text-muted\"><i class=\"fas fa-utensils me-1\"></i>Daftar Menu / Bahan</span>
                        <div class=\"btn-group\">
                            <button type=\"button\" class=\"btn btn-xs btn-outline-primary\" onclick=\"addItemToDay(${id}, 'resep')\">+ Resep</button>
                            <button type=\"button\" class=\"btn btn-xs btn-outline-secondary\" onclick=\"addItemToDay(${id}, 'produk')\">+ Produk</button>
                        </div>
                    </div>
                    <div id=\"items_container_${id}\" class=\"bg-light-blue p-2 rounded\">
                        <!-- Items will be added here -->
                    </div>
                </div>

                <div id=\"day_summary_${id}\" class=\"mt-3\" style=\"display:none;\">
                    <!-- Overall stock summary for this day will be shown here -->
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('dayContainer').insertAdjacentHTML('beforeend', html);
    addItemToDay(id, 'resep');
}

function removeDayBlock(id) {
    if (document.querySelectorAll('.day-card').length > 1) {
        document.getElementById(`day_block_${id}`).remove();
    } else {
        alert('Minimal harus ada 1 hari perencanaan');
    }
}

let itemIndexes = {};

function addItemToDay(dayId, type) {
    if (!itemIndexes[dayId]) itemIndexes[dayId] = 0;
    const itemId = itemIndexes[dayId]++;
    const isRecipe = type === 'resep';

    const html = `
        <div class=\"item-row row g-2 align-items-center\" id=\"item_row_${dayId}_${itemId}\" data-type=\"${type}\" data-item-index=\"${itemId}\">
            <div class=\"col-md-5\">
                <div class=\"input-group input-group-sm\">
                    <span class=\"input-group-text bg-white\"><i class=\"fas ${isRecipe ? 'fa-utensils text-primary' : 'fa-box text-secondary'}\"></i></span>
                    <input type=\"text\" class=\"form-control bg-white cursor-pointer\" id=\"name_${dayId}_${itemId}\" placeholder=\"Pilih ${type}...\" readonly onclick=\"openSelectionModal(${dayId}, ${itemId}, '${type}')\">
                    <input type=\"hidden\" name=\"days[${dayId}][items][${itemId}][${isRecipe ? 'resep_id' : 'produk_id'}]\" id=\"id_${dayId}_${itemId}\">
                    <button class=\"btn btn-outline-primary\" type=\"button\" onclick=\"openSelectionModal(${dayId}, ${itemId}, '${type}')\"><i class=\"fas fa-search\"></i></button>
                </div>
            </div>
            <div class=\"col-md-2\">
                <div class=\"input-group input-group-sm\">
                    <input type=\"number\" name=\"days[${dayId}][items][${itemId}][qty_needed]\" id=\"qty_${dayId}_${itemId}\" class=\"form-control text-end\" value=\"1\" min=\"0.001\" step=\"0.001\" onchange=\"checkItemStock(${dayId}, ${itemId})\">
                    <span class=\"input-group-text\" id=\"unit_${dayId}_${itemId}\" style=\"font-size: 0.7rem;\">${isRecipe ? 'Porsi' : 'Qty/Porsi'}</span>
                </div>
            </div>
            <div class=\"col-md-4\">
                <div id=\"stock_status_${dayId}_${itemId}\" class=\"small\">
                    <span class=\"text-muted\">Belum dipilih</span>
                </div>
            </div>
            <div class=\"col-md-1 text-end\">
                <button type=\"button\" class=\"btn btn-link btn-sm text-danger\" onclick=\"removeItemFromDay(${dayId}, ${itemId})\"><i class=\"fas fa-times\"></i></button>
            </div>
            <div class=\"col-12\">
                <div id=\"detailed_info_${dayId}_${itemId}\" style=\"display:none; font-size: 0.7rem;\" class=\"mt-1 ps-4\"></div>
            </div>
        </div>
    `;
    
    document.getElementById(`items_container_${dayId}`).insertAdjacentHTML('beforeend', html);
}

function removeItemFromDay(dayId, itemId) {
    document.getElementById(`item_row_${dayId}_${itemId}`).remove();
    updateDayStock(dayId);
}

function openSelectionModal(dayId, itemId, type) {
    currentDayIndex = dayId;
    currentItemIndex = itemId;
    if (type === 'resep') {
        recipeModal.show();
    } else {
        productModal.show();
    }
}

function selectProduct(id, name, unit) {
    const d = currentDayIndex, i = currentItemIndex;
    document.getElementById(`id_${d}_${i}`).value = id;
    document.getElementById(`name_${d}_${i}`).value = name;
    document.getElementById(`unit_${d}_${i}`).innerText = unit;
    productModal.hide();
    checkItemStock(d, i);
}

function selectRecipe(id, name) {
    const d = currentDayIndex, i = currentItemIndex;
    document.getElementById(`id_${d}_${i}`).value = id;
    document.getElementById(`name_${d}_${i}`).value = name;
    document.getElementById(`unit_${d}_${i}`).innerText = 'Porsi';
    
    // Auto sync portions from day header if empty
    const portions = document.getElementById(`total_porsi_${d}`).value;
    document.getElementById(`qty_${d}_${i}`).value = portions;
    
    recipeModal.hide();
    checkItemStock(d, i);
}

function updateDayStock(dayId) {
    // Collect all items in this day and re-check
    const rows = document.querySelectorAll(`#items_container_${dayId} .item-row`);
    rows.forEach(row => {
        const itemId = row.getAttribute('data-item-index');
        checkItemStock(dayId, itemId);
    });
}

function checkItemStock(dayId, itemId) {
    const row = document.getElementById(`item_row_${dayId}_${itemId}`);
    const type = row.getAttribute('data-type');
    const targetId = document.getElementById(`id_${dayId}_${itemId}`).value;
    const qty = document.getElementById(`qty_${dayId}_${itemId}`).value;
    const date = document.querySelector(`[name=\"days[${dayId}][tanggal_menu]\"]`).value;
    
    if (!targetId || !qty || !date) return;
    
    const statusDiv = document.getElementById(`stock_status_${dayId}_${itemId}`);
    const infoDiv = document.getElementById(`detailed_info_${dayId}_${itemId}`);
    
    statusDiv.innerHTML = '<i class=\"fas fa-spinner fa-spin\"></i>';
    
    const totalPorsi = parseFloat(document.getElementById(`total_porsi_${dayId}`).value) || 1;
    
    // For direct products, we treat input as Qty Per Portion, so we multiply by Total Portions
    // For recipes, input is usually just the number of portions (which should equal total portions, or specific amount)
    
    if (type === 'resep') {
        body.resep_id = targetId;
        // user input for recipe is "Portions"
        // typically this equals total_porsi, but user can override. 
        // We pass it as is.
    } else {
        body.produk_id = targetId;
        // user input for product is "Qty Per Portion"
        // we must multiply by total portions for the stock check
        body.qty_needed = parseFloat(qty) * totalPorsi;
    }
    
    fetch('check_stock.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(body)
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) throw new Error(data.error);
        
        if (data.is_recipe) {
            renderRecipeStock(statusDiv, infoDiv, data);
        } else {
            renderProductStock(statusDiv, infoDiv, data);
        }
    })
    .catch(err => {
        statusDiv.innerHTML = `<span class=\"text-danger\">${err.message}</span>`;
    });
}

function renderProductStock(statusDiv, infoDiv, data) {
    const statusIdx = data.stock_status;
    const badge = statusIdx === 'sufficient' ? 'bg-success' : (statusIdx === 'partial' ? 'bg-warning text-dark' : 'bg-danger');
    const label = statusIdx === 'sufficient' ? 'Cukup' : (statusIdx === 'partial' ? 'Sebagian' : 'Habis');
    
    statusDiv.innerHTML = `<span class=\"badge ${badge}\">${label}</span> <span class=\"text-muted\">Stok: ${data.warehouse_stock} ${data.satuan}</span>`;
    
    if (data.qty_to_purchase > 0 && data.market_recommendation) {
        infoDiv.innerHTML = `<i class=\"fas fa-shopping-cart text-warning me-1\"></i> Beli ${data.qty_to_purchase} ${data.satuan} di ${data.market_recommendation.nama_pasar} (@ Rp ${formatNumber(data.market_recommendation.harga_terendah)})`;
        infoDiv.style.display = 'block';
    } else {
        infoDiv.style.display = 'none';
    }
}

function renderRecipeStock(statusDiv, infoDiv, data) {
    const statusIdx = data.overall_status;
    const badge = statusIdx === 'sufficient' ? 'bg-success' : (statusIdx === 'partial' ? 'bg-warning text-dark' : 'bg-danger');
    const label = statusIdx === 'sufficient' ? 'Lengkap' : (statusIdx === 'partial' ? 'Perlu Beli' : 'Stok Kosong');
    
    statusDiv.innerHTML = `<span class=\"badge ${badge}\">${label}</span> <span class=\"text-muted\">${data.ingredients.length} Bahan</span>`;
    
    let rows = '<table class=\"table table-sm table-striped m-0 text-muted\" style=\"font-size: 0.75rem;\"><thead><tr><th>Bahan</th><th class=\"text-center\">Rumus (Gram x Porsi)</th><th class=\"text-end\">Total Butuh</th><th class=\"text-end\">Stok Gudang</th><th class=\"text-center\">Status</th></tr></thead><tbody>';
    data.ingredients.forEach(ing => {
        const color = ing.stock_status === 'sufficient' ? 'text-success' : (ing.stock_status === 'partial' ? 'text-warning' : 'text-danger');
        const icon = ing.stock_status === 'sufficient' ? '<i class=\"fas fa-check-circle\"></i>' : (ing.stock_status === 'partial' ? '<i class=\"fas fa-exclamation-circle\"></i>' : '<i class=\"fas fa-times-circle\"></i>');
        
        // Calculate raw grammage per portion to show the math
        const gramPerPortion = ing.qty_needed / data.qty_needed; // This might be affected by float precision, so rely on backend data if possible, or display as is
        // Actually, let's use the explicit math: Gramasi x Total Porsi
        // wait, ing.qty_needed IS standard portion based. 
        // Let's improve the display:
        
        rows += `<tr>
            <td class=\"fw-bold ${color}\">${ing.nama_produk}</td>
            <td class=\"text-center\">${parseFloat(gramPerPortion).toLocaleString('id-ID')} ${ing.satuan} x ${data.qty_needed}</td>
            <td class=\"text-end font-weight-bold\">${parseFloat(ing.qty_needed).toLocaleString('id-ID')} ${ing.satuan}</td>
            <td class=\"text-end\">${parseFloat(ing.warehouse_stock).toLocaleString('id-ID')} ${ing.satuan}</td>
            <td class=\"text-center ${color}\">${icon}</td>
        </tr>`;
        
        // Show purchase recommendation in a subtrow if needed
        if (ing.stock_status !== 'sufficient' && ing.market_recommendation) {
             rows += `<tr>
                <td colspan=\"5\" class=\"text-end small fst-italic text-warning bg-light ps-4\">
                    <i class=\"fas fa-shopping-cart me-1\"></i> Beli ${parseFloat(ing.qty_needed - ing.warehouse_stock).toLocaleString('id-ID')} ${ing.satuan} 
                    di ${ing.market_recommendation.nama_pasar} (@ Rp ${formatNumber(ing.market_recommendation.harga_terendah)})
                </td>
             </tr>`;
        }
    });
    rows += '</tbody></table>';
    
    infoDiv.innerHTML = rows;
    infoDiv.style.display = 'block';
}

function formatNumber(num) {
    return new Intl.NumberFormat('id-ID').format(num);
}
</script>

<?php require_once '../../includes/footer.php'; ?>
