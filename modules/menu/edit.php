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

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user = $_SESSION['user'];
$menuHelper = new MenuHarianHelper();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_menu') {
    try {
        $data = [
            'tanggal_menu' => $_POST['tanggal_menu'],
            'nama_menu' => $_POST['nama_menu'],
            'deskripsi' => $_POST['deskripsi'],
            'total_porsi' => intval($_POST['total_porsi']),
            'kantor_id' => !empty($_POST['kantor_id']) ? intval($_POST['kantor_id']) : null,
            'updated_by' => $user['id'],
            'items' => []
        ];

        if (!empty($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
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
                    $data['items'][] = $entry;
                }
            }
        }

        if ($menuHelper->updateMenu($id, $data)) {
            // Log activity
            logActivity($conn, $user['id'], "Mengupdate menu planner", 'menu', $id);
            
            $_SESSION['flash_type'] = 'success';
            $_SESSION['flash_message'] = 'Menu berhasil diupdate!';
            header('Location: index.php');
            exit();
        } else {
            throw new Exception('Gagal mengupdate menu');
        }

    } catch (Exception $e) {
        $_SESSION['flash_type'] = 'error';
        $_SESSION['flash_message'] = $e->getMessage();
    }
}

// Get existing data
$menu = $menuHelper->getMenuWithDetails($id);

if (!$menu) {
    $_SESSION['flash_type'] = 'error';
    $_SESSION['flash_message'] = 'Menu tidak ditemukan.';
    header('Location: index.php');
    exit();
}

if ($menu['status'] !== 'draft') {
    $_SESSION['flash_type'] = 'error';
    $_SESSION['flash_message'] = 'Hanya menu status Draft yang dapat diedit.';
    header('Location: index.php');
    exit();
}

// Prepare existing items for JS
$existingItems = [];
foreach ($menu['details'] as $detail) {
    // Reverse calculate per-portion quantity
    // Using loose precision for display
    $qty_per_portion = $detail['qty_needed'] / $menu['total_porsi'];
    
    // We strictly convert existing items to PRODUCTS (not recipes) to avoid double decomposition
    // unless you want to re-construct recipes which is hard.
    // So we use produk_id, name, and the calculated per-portion qty.
    // We treat them as if user added them manually as products.
    
    $existingItems[] = [
        'type' => 'produk', // Force product type for existing stored ingredients
        'id' => $detail['produk_id'],
        'name' => $detail['nama_produk'],
        'qty' => $qty_per_portion,
        'unit' => $detail['nama_satuan'] ?? 'Unit',
        'keterangan' => $detail['keterangan'] ?? '',
        'original_resep' => $detail['nama_resep'] ?? '' // Just for info if needed
    ];
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

$page_title = 'Edit Menu: ' . $menu['no_menu'];
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Edit Menu</h1>
            <p class="mb-0 text-muted">Edit perencanaan menu</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Kembali
            </a>
        </div>
    </div>

    <?php show_flash(); ?>

    <form id="editForm" method="POST">
        <input type="hidden" name="action" value="update_menu">
        
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Informasi Menu</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Tanggal Menu <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal_menu" id="tanggal_menu" class="form-control" value="<?= $menu['tanggal_menu'] ?>" required onchange="updateStockAll()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Jumlah Porsi <span class="text-danger">*</span></label>
                        <input type="number" name="total_porsi" id="total_porsi" class="form-control" min="1" value="<?= $menu['total_porsi'] ?>" required onchange="updateStockAll()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Kantor Tujuan</label>
                        <select name="kantor_id" class="form-select">
                            <option value="">Semua Kantor</option>
                            <?php foreach ($offices as $office): ?>
                                <option value="<?= $office['id'] ?>" <?= $menu['kantor_id'] == $office['id'] ? 'selected' : '' ?>>
                                    <?= $office['nama_kantor'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Nama Menu</label>
                        <input type="text" name="nama_menu" class="form-control" value="<?= htmlspecialchars($menu['nama_menu']) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">Deskripsi</label>
                        <textarea name="deskripsi" class="form-control" rows="2"><?= htmlspecialchars($menu['deskripsi']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Item / Bahan Baku</h6>
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-primary" onclick="addItem('resep')">
                        <i class="fas fa-plus me-1"></i> Tambah Resep
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addItem('produk')">
                        <i class="fas fa-plus me-1"></i> Tambah Produk
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div id="items_container">
                    <!-- Items inserted via JS -->
                </div>
            </div>
            <div class="card-footer bg-light">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i> Update Menu
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Modals -->
<?php include 'modals.php'; ?>

<script>
let itemIndex = 0;
let productModal;
let recipeModal;
let currentItemIndex = null;

const products = <?= json_encode($products) ?>;
const recipes = <?= json_encode($recipes) ?>;
const existingItems = <?= json_encode($existingItems) ?>;

document.addEventListener('DOMContentLoaded', function() {
    productModal = new bootstrap.Modal(document.getElementById('productModal'));
    recipeModal = new bootstrap.Modal(document.getElementById('recipeModal'));
    
    // Load existing items
    if (existingItems && existingItems.length > 0) {
        existingItems.forEach(item => {
            addItem(item.type, item);
        });
    } else {
        // Add one empty row if none
        addItem('resep');
    }
});

function addItem(type, data = null) {
    const id = itemIndex++;
    const isRecipe = type === 'resep';
    
    const valId = data ? data.id : '';
    const valName = data ? data.name : '';
    const valQty = data ? data.qty : 1;
    const valUnit = data ? (data.unit || '-') : (isRecipe ? 'Porsi' : '-');
    const labelUnit = isRecipe ? 'Porsi' : 'Qty/Porsi';
    
    // Note: If loading existing item (data != null), it is ALWAYS treated as 'produk' logic-wise correctly by backend if we send produk_id
    // But UI might show "Resep" if we set type=resep.
    // Our PHP logic converted all existing DB items to 'produk' type for safety.
    // So 'type' here will likely be 'produk' for all existing items.
    
    const html = `
        <div class="item-row row g-2 align-items-center border-bottom py-2" id="item_row_${id}" data-type="${type}">
            <div class="col-md-5">
                <label class="small text-muted d-block d-md-none">Item</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="fas ${isRecipe ? 'fa-utensils text-primary' : 'fa-box text-secondary'}"></i></span>
                    <input type="text" class="form-control bg-white cursor-pointer" id="name_${id}" 
                           placeholder="Pilih ${type}..." readonly onclick="openSelectionModal(${id}, '${type}')" value="${valName}">
                    <input type="hidden" name="items[${id}][${isRecipe ? 'resep_id' : 'produk_id'}]" id="id_${id}" value="${valId}">
                    <button class="btn btn-outline-primary" type="button" onclick="openSelectionModal(${id}, '${type}')"><i class="fas fa-search"></i></button>
                </div>
                ${data && data.original_resep ? `<small class="text-xs text-muted ms-1">Dari Resep: ${data.original_resep}</small>` : ''}
            </div>
            <div class="col-md-2">
                <label class="small text-muted d-block d-md-none">Jumlah</label>
                <div class="input-group input-group-sm">
                    <input type="number" name="items[${id}][qty_needed]" id="qty_${id}" 
                           class="form-control text-end" value="${valQty}" min="0.001" step="0.001" onchange="checkItemStock(${id})">
                    <span class="input-group-text" id="unit_${id}" style="font-size: 0.7rem;">${labelUnit}</span>
                </div>
            </div>
            <div class="col-md-4">
                <label class="small text-muted d-block d-md-none">Status Stok</label>
                <div id="stock_status_${id}" class="small">
                    <span class="text-muted">Belum dicek</span>
                </div>
            </div>
            <div class="col-md-1 text-end">
                <button type="button" class="btn btn-link btn-sm text-danger" onclick="removeItem(${id})"><i class="fas fa-times"></i></button>
            </div>
            <div class="col-12">
                <div id="detailed_info_${id}" style="display:none; font-size: 0.7rem;" class="mt-1 ps-4"></div>
            </div>
        </div>
    `;
    
    document.getElementById('items_container').insertAdjacentHTML('beforeend', html);
    
    if (valId) {
        checkItemStock(id);
    }
}

function removeItem(id) {
    document.getElementById(`item_row_${id}`).remove();
    updateStockAll();
}

function updateStockAll() {
    const rows = document.querySelectorAll('.item-row');
    rows.forEach(row => {
        const id = row.id.split('_')[2];
        checkItemStock(id);
    });
}

function openSelectionModal(id, type) {
    currentItemIndex = id;
    if (type === 'resep') {
        recipeModal.show();
    } else {
        productModal.show();
    }
}

function selectProduct(id, name, unit) {
    const i = currentItemIndex;
    document.getElementById(`id_${i}`).value = id;
    document.getElementById(`name_${i}`).value = name;
    // document.getElementById(`unit_${i}`).innerText = 'Qty/Porsi'; // Keep static
    productModal.hide();
    checkItemStock(i);
}

function selectRecipe(id, name) {
    const i = currentItemIndex;
    document.getElementById(`id_${i}`).value = id;
    document.getElementById(`name_${i}`).value = name;
    
    // Auto sync portions
    const portions = document.getElementById('total_porsi').value;
    document.getElementById(`qty_${i}`).value = portions;
    
    recipeModal.hide();
    checkItemStock(i);
}

function checkItemStock(itemId) {
    const row = document.getElementById(`item_row_${itemId}`);
    if (!row) return; // Removed row
    
    const type = row.getAttribute('data-type');
    const targetId = document.getElementById(`id_${itemId}`).value;
    const qty = document.getElementById(`qty_${itemId}`).value;
    const date = document.getElementById('tanggal_menu').value;
    
    if (!targetId || !qty || !date) return;
    
    const statusDiv = document.getElementById(`stock_status_${itemId}`);
    const infoDiv = document.getElementById(`detailed_info_${itemId}`);
    
    statusDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    const totalPorsi = parseFloat(document.getElementById('total_porsi').value) || 1;
    const body = { qty_needed: qty, tanggal_menu: date };
    
    if (type === 'resep') {
        body.resep_id = targetId;
    } else {
        body.produk_id = targetId;
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
        statusDiv.innerHTML = `<span class="text-danger">${err.message}</span>`;
    });
}

// Reuse render functions from create.php (copy-pasted for independence)
function renderProductStock(statusDiv, infoDiv, data) {
    const statusIdx = data.stock_status;
    const badge = statusIdx === 'sufficient' ? 'bg-success' : (statusIdx === 'partial' ? 'bg-warning text-dark' : 'bg-danger');
    const label = statusIdx === 'sufficient' ? 'Cukup' : (statusIdx === 'partial' ? 'Sebagian' : 'Habis');
    
    statusDiv.innerHTML = `<span class="badge ${badge}">${label}</span> <span class="text-muted">Stok: ${formatNumber(data.warehouse_stock)} ${data.satuan}</span>`;
    
    if (data.qty_to_purchase > 0 && data.market_recommendation) {
        infoDiv.innerHTML = `<i class="fas fa-shopping-cart text-warning me-1"></i> Beli ${formatNumber(data.qty_to_purchase)} ${data.satuan} di ${data.market_recommendation.nama_pasar} (@ Rp ${formatNumber(data.market_recommendation.harga_terendah)})`;
        infoDiv.style.display = 'block';
    } else {
        infoDiv.style.display = 'none';
    }
}

function renderRecipeStock(statusDiv, infoDiv, data) {
    const statusIdx = data.overall_status;
    const badge = statusIdx === 'sufficient' ? 'bg-success' : (statusIdx === 'partial' ? 'bg-warning text-dark' : 'bg-danger');
    const label = statusIdx === 'sufficient' ? 'Lengkap' : (statusIdx === 'partial' ? 'Perlu Beli' : 'Stok Kosong');
    
    statusDiv.innerHTML = `<span class="badge ${badge}">${label}</span> <span class="text-muted">${data.ingredients.length} Bahan</span>`;
    
    let rows = '<table class="table table-sm table-striped m-0 text-muted" style="font-size: 0.75rem;"><thead><tr><th>Bahan</th><th class="text-center">Rumus (Gram x Porsi)</th><th class="text-end">Total Butuh</th><th class="text-end">Stok Gudang</th><th class="text-center">Status</th></tr></thead><tbody>';
    data.ingredients.forEach(ing => {
        const color = ing.stock_status === 'sufficient' ? 'text-success' : (ing.stock_status === 'partial' ? 'text-warning' : 'text-danger');
        const icon = ing.stock_status === 'sufficient' ? '<i class="fas fa-check-circle"></i>' : (ing.stock_status === 'partial' ? '<i class="fas fa-exclamation-circle"></i>' : '<i class="fas fa-times-circle"></i>');
        const gramPerPortion = ing.qty_needed / data.qty_needed;
        
        rows += `<tr>
            <td class="fw-bold ${color}">${ing.nama_produk}</td>
            <td class="text-center">${formatNumber(gramPerPortion)} ${ing.satuan} x ${formatNumber(data.qty_needed)}</td>
            <td class="text-end font-weight-bold">${formatNumber(ing.qty_needed)} ${ing.satuan}</td>
            <td class="text-end">${formatNumber(ing.warehouse_stock)} ${ing.satuan}</td>
            <td class="text-center ${color}">${icon}</td>
        </tr>`;
        
        if (ing.stock_status !== 'sufficient' && ing.market_recommendation) {
             rows += `<tr>
                <td colspan="5" class="text-end small fst-italic text-warning bg-light ps-4">
                    <i class="fas fa-shopping-cart me-1"></i> Beli ${formatNumber(ing.qty_needed - ing.warehouse_stock)} ${ing.satuan} 
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
