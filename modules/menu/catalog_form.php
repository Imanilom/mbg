<?php
// Start session and check auth manually before outputting anything
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/MenuCatalogHelper.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Access Control
if (!is_logged_in()) {
    header('Location: ' . BASE_URL . '/modules/auth/login.php');
    exit();
}

$user = get_user_data();
if (!in_array($user['role'], ['admin', 'koperasi'])) {
    set_flash('error', 'Akses ditolak.');
    header('Location: ' . BASE_URL . '/modules/dashboard');
    exit();
}

$catalogHelper = new MenuCatalogHelper();
$is_edit = isset($_GET['id']);
$menu = null;

if ($is_edit) {
    $menu = $catalogHelper->getMenu($_GET['id']);
    if (!$menu) {
        set_flash('error', 'Menu tidak ditemukan.');
        header('Location: catalog.php');
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $data = [
            'nama_menu' => $_POST['nama_menu'],
            'deskripsi' => $_POST['deskripsi'],
            'created_by' => $user['id'],
            'items' => []
        ];
        
        // Process items
        if (!empty($_POST['item_type'])) {
            foreach ($_POST['item_type'] as $index => $type) {
                $item = [
                    'item_type' => $type,
                    'produk_id' => $type == 'product' ? $_POST['produk_id'][$index] : null,
                    'resep_id' => $type == 'recipe' ? $_POST['resep_id'][$index] : null,
                    'manual_nama' => $type == 'manual' ? $_POST['manual_nama'][$index] : null,
                    'qty_needed' => $_POST['qty_needed'][$index],
                    'keterangan' => $_POST['keterangan'][$index] ?? ''
                ];
                $data['items'][] = $item;
            }
        }
        
        if ($is_edit) {
            $catalogHelper->updateMenu($_GET['id'], $data);
            logActivity($conn, $user['id'], "Mengupdate menu catalog", 'menu_master', $_GET['id']);
            set_flash('success', 'Menu catalog berhasil diupdate.');
        } else {
            $menu_id = $catalogHelper->createMenu($data);
            logActivity($conn, $user['id'], "Membuat menu catalog baru", 'menu_master', $menu_id);
            set_flash('success', 'Menu catalog berhasil dibuat.');
        }
        
        header('Location: catalog.php');
        exit();
        
    } catch (Exception $e) {
        set_flash('error', 'Gagal menyimpan menu: ' . $e->getMessage());
    }
}

// Get products and recipes for dropdown
$products = $conn->query("SELECT id, nama_produk, kode_produk FROM produk ORDER BY nama_produk ASC")->fetch_all(MYSQLI_ASSOC);
$recipes = $conn->query("SELECT id, nama_resep FROM resep ORDER BY nama_resep ASC")->fetch_all(MYSQLI_ASSOC);

// NOW include the header and view
$page_title = 'Form Menu Catalog';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../includes/navbar.php';
?>

<style>
    .item-row {
        background: #f8f9fc;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1rem;
        border-left: 4px solid #4e73df;
    }
    .item-row:hover {
        background: #eaecf4;
    }
    .remove-item-btn {
        opacity: 0.7;
        transition: opacity 0.2s;
    }
    .remove-item-btn:hover {
        opacity: 1;
    }
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-<?= $is_edit ? 'edit' : 'plus' ?> me-2"></i>
                <?= $is_edit ? 'Edit' : 'Buat' ?> Menu Catalog
            </h1>
            <p class="mb-0 text-muted">Template menu tanpa tanggal</p>
        </div>
        <a href="catalog.php" class="btn btn-secondary shadow-sm">
            <i class="fas fa-arrow-left me-1"></i> Kembali
        </a>
    </div>

    <!-- Flash Message -->
    <?php show_flash(); ?>

    <!-- Form -->
    <div class="row">
        <div class="col-lg-8">
            <form method="POST" action="" id="catalogForm">
                <!-- Menu Info Card -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 bg-primary text-white">
                        <h6 class="m-0 font-weight-bold">
                            <i class="fas fa-info-circle me-1"></i> Informasi Menu
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nama Menu <span class="text-danger">*</span></label>
                            <input type="text" name="nama_menu" class="form-control" 
                                   value="<?= $menu ? htmlspecialchars($menu['nama_menu']) : '' ?>" 
                                   required placeholder="Contoh: Nasi Goreng Spesial">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Deskripsi</label>
                            <textarea name="deskripsi" class="form-control" rows="3" 
                                      placeholder="Deskripsi singkat tentang menu ini..."><?= $menu ? htmlspecialchars($menu['deskripsi']) : '' ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Items Card -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 bg-success text-white d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold">
                            <i class="fas fa-list me-1"></i> Item Menu (Resep / Produk)
                        </h6>
                        <button type="button" class="btn btn-sm btn-light" onclick="addItem()">
                            <i class="fas fa-plus me-1"></i> Tambah Item
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="itemsContainer">
                            <?php if ($menu && !empty($menu['items'])): ?>
                                <?php foreach ($menu['items'] as $index => $item): ?>
                                <div class="item-row" data-index="<?= $index ?>">
                                    <div class="row align-items-end">
                                        <div class="col-md-3">
                                            <label class="form-label small fw-bold">Tipe</label>
                                            <select name="item_type[]" class="form-select item-type-select" onchange="toggleItemType(this)" required>
                                                <option value="product" <?= ($item['item_type'] ?? '') == 'product' ? 'selected' : '' ?>>Produk</option>
                                                <option value="recipe" <?= ($item['item_type'] ?? '') == 'recipe' ? 'selected' : '' ?>>Resep</option>
                                                <option value="manual" <?= ($item['item_type'] ?? '') == 'manual' ? 'selected' : '' ?>>Manual</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4 product-select" style="<?= ($item['item_type'] ?? '') == 'product' ? '' : 'display:none' ?>">
                                            <label class="form-label small fw-bold">Produk</label>
                                            <select name="produk_id[]" class="form-select">
                                                <option value="">-- Pilih Produk --</option>
                                                <?php foreach ($products as $p): ?>
                                                <option value="<?= $p['id'] ?>" <?= $item['produk_id'] == $p['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($p['nama_produk']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4 recipe-select" style="<?= ($item['item_type'] ?? '') == 'recipe' ? '' : 'display:none' ?>">
                                            <label class="form-label small fw-bold">Resep</label>
                                            <select name="resep_id[]" class="form-select">
                                                <option value="">-- Pilih Resep --</option>
                                                <?php foreach ($recipes as $r): ?>
                                                <option value="<?= $r['id'] ?>" <?= $item['resep_id'] == $r['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($r['nama_resep']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4 manual-input" style="<?= ($item['item_type'] ?? '') == 'manual' ? '' : 'display:none' ?>">
                                            <label class="form-label small fw-bold">Nama Item</label>
                                            <input type="text" name="manual_nama[]" class="form-control" 
                                                   value="<?= htmlspecialchars($item['custom_name'] ?? '') ?>" placeholder="Nama Item / Bahan">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small fw-bold">Qty/Porsi</label>
                                            <input type="number" name="qty_needed[]" class="form-control" 
                                                   value="<?= $item['qty_needed'] ?>" step="0.01" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small fw-bold">Keterangan</label>
                                            <input type="text" name="keterangan[]" class="form-control" 
                                                   value="<?= htmlspecialchars($item['keterangan']) ?>" placeholder="Opsional">
                                        </div>
                                        <div class="col-md-1">
                                            <button type="button" class="btn btn-danger btn-sm remove-item-btn" onclick="removeItem(this)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Catatan:</strong> Qty/Porsi adalah jumlah yang dibutuhkan per 1 porsi. 
                            Saat dijadwalkan, sistem akan mengalikan dengan total porsi yang dibutuhkan.
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="catalog.php" class="btn btn-secondary">
                        <i class="fas fa-times me-1"></i> Batal
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> <?= $is_edit ? 'Update' : 'Simpan' ?> Menu
                    </button>
                </div>
            </form>
        </div>

        <!-- Help Card -->
        <div class="col-lg-4">
            <div class="card shadow mb-4 border-left-info">
                <div class="card-header py-3 bg-info text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-question-circle me-1"></i> Panduan
                    </h6>
                </div>
                <div class="card-body">
                    <h6 class="fw-bold">Cara Menggunakan:</h6>
                    <ol class="small">
                        <li>Isi nama dan deskripsi menu</li>
                        <li>Tambahkan item (produk atau resep)</li>
                        <li>Tentukan qty per porsi untuk setiap item</li>
                        <li>Simpan sebagai template</li>
                    </ol>
                    
                    <hr>
                    
                    <h6 class="fw-bold">Perbedaan Produk vs Resep:</h6>
                    <ul class="small">
                        <li><strong>Produk:</strong> Item tunggal (contoh: Nasi 200g)</li>
                        <li><strong>Resep:</strong> Kombinasi beberapa produk (contoh: Sambal yang terdiri dari cabai, bawang, dll)</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let itemIndex = <?= $menu && !empty($menu['items']) ? count($menu['items']) : 0 ?>;

function addItem() {
    const container = document.getElementById('itemsContainer');
    const itemHtml = `
        <div class="item-row" data-index="${itemIndex}">
            <div class="row align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Tipe</label>
                    <select name="item_type[]" class="form-select item-type-select" onchange="toggleItemType(this)" required>
                        <option value="product">Produk</option>
                        <option value="recipe">Resep</option>
                        <option value="manual">Manual</option>
                    </select>
                </div>
                <div class="col-md-4 product-select">
                    <label class="form-label small fw-bold">Produk</label>
                    <select name="produk_id[]" class="form-select">
                        <option value="">-- Pilih Produk --</option>
                        <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nama_produk']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 recipe-select" style="display:none">
                    <label class="form-label small fw-bold">Resep</label>
                    <select name="resep_id[]" class="form-select">
                        <option value="">-- Pilih Resep --</option>
                        <?php foreach ($recipes as $r): ?>
                        <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nama_resep']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 manual-input" style="display:none">
                    <label class="form-label small fw-bold">Nama Item</label>
                    <input type="text" name="manual_nama[]" class="form-control" placeholder="Nama Item / Bahan">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Qty/Porsi</label>
                    <input type="number" name="qty_needed[]" class="form-control" step="0.01" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Keterangan</label>
                    <input type="text" name="keterangan[]" class="form-control" placeholder="Opsional">
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-danger btn-sm remove-item-btn" onclick="removeItem(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', itemHtml);
    itemIndex++;
}

function removeItem(btn) {
    btn.closest('.item-row').remove();
}

function toggleItemType(select) {
    const row = select.closest('.item-row');
    const productSelect = row.querySelector('.product-select');
    const recipeSelect = row.querySelector('.recipe-select');
    const manualInput = row.querySelector('.manual-input');
    
    // Hide all first
    productSelect.style.display = 'none';
    recipeSelect.style.display = 'none';
    manualInput.style.display = 'none';
    
    if (select.value === 'product') {
        productSelect.style.display = 'block';
    } else if (select.value === 'recipe') {
        recipeSelect.style.display = 'block';
    } else if (select.value === 'manual') {
        manualInput.style.display = 'block';
    }
}

// Add first item if creating new
<?php if (!$is_edit): ?>
if (itemIndex === 0) {
    addItem();
}
<?php endif; ?>
</script>

<?php require_once '../../includes/footer.php'; ?>
