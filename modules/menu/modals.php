<!-- Product Selection Modal -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Pilih Produk</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" id="productSearch" class="form-control mb-3 shadow-sm" placeholder="Cari produk berdasarkan nama atau kode...">
                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Kode</th>
                                <th>Nama Produk</th>
                                <th>Satuan</th>
                                <th width="10%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="productList">
                            <!-- Populated via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recipe Selection Modal -->
<div class="modal fade" id="recipeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Pilih Resep</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" id="recipeSearch" class="form-control mb-3 shadow-sm" placeholder="Cari resep berdasarkan nama atau kode...">
                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Kode</th>
                                <th>Nama Resep</th>
                                <th>Porsi Standar</th>
                                <th width="10%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="recipeList">
                            <!-- Populated via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    renderProductList();
    renderRecipeList();
    
    setupSearch('productSearch', 'productRow');
    setupSearch('recipeSearch', 'recipeRow');
});

function renderProductList() {
    let html = '';
    products.forEach(p => {
        html += `
            <tr class=\"productRow\" data-search=\"${p.kode_produk.toLowerCase()} ${p.nama_produk.toLowerCase()}\">
                <td><small class=\"text-muted\">${p.kode_produk}</small></td>
                <td><strong>${p.nama_produk}</strong></td>
                <td>${p.nama_satuan}</td>
                <td>
                    <button type=\"button\" class=\"btn btn-sm btn-primary\" onclick=\"selectProduct(${p.id}, '${p.nama_produk}', '${p.nama_satuan}')\">Pilih</button>
                </td>
            </tr>
        `;
    });
    document.getElementById('productList').innerHTML = html;
}

function renderRecipeList() {
    let html = '';
    recipes.forEach(r => {
        html += `
            <tr class=\"recipeRow\" data-search=\"${r.kode_resep.toLowerCase()} ${r.nama_resep.toLowerCase()}\">
                <td><small class=\"text-muted\">${r.kode_resep}</small></td>
                <td><strong>${r.nama_resep}</strong></td>
                <td>${r.porsi_standar} Porsi</td>
                <td>
                    <button type=\"button\" class=\"btn btn-sm btn-primary\" onclick=\"selectRecipe(${r.id}, '${r.nama_resep}')\">Pilih</button>
                </td>
            </tr>
        `;
    });
    document.getElementById('recipeList').innerHTML = html;
}

function setupSearch(inputId, rowClass) {
    document.getElementById(inputId).addEventListener('input', function(e) {
        const query = e.target.value.toLowerCase();
        document.querySelectorAll('.' + rowClass).forEach(row => {
            row.style.display = row.dataset.search.includes(query) ? '' : 'none';
        });
    });
}
</script>
