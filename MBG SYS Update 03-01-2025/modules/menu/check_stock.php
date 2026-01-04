<?php
/**
 * AJAX Endpoint: Check Stock Availability
 * Returns stock status and purchase recommendations
 */

require_once '../../config/database.php';
require_once '../../helpers/MenuHarianHelper.php';

header('Content-Type: application/json');

// Start session for user check
session_start();

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

$produk_id = intval($input['produk_id'] ?? 0);
$resep_id = intval($input['resep_id'] ?? 0);
$qty_needed = floatval($input['qty_needed'] ?? 0); // Portions if resep_id is set
$tanggal_menu = $input['tanggal_menu'] ?? date('Y-m-d');

if ((!$produk_id && !$resep_id) || !$qty_needed) {
    echo json_encode(['error' => 'Invalid parameters']);
    exit();
}

try {
    $menuHelper = new MenuHarianHelper();
    
    if ($resep_id) {
        // Recipe decomposition
        $ingredients = $menuHelper->getRecipeIngredients($resep_id, $qty_needed);
        $results = [];
        $overall_status = 'sufficient';
        
        foreach ($ingredients as $ing) {
            $allocation = $menuHelper->calculateStockAllocation($ing['produk_id'], $ing['calculated_qty'], $tanggal_menu);
            
            // Get product name
            $p = db_get_row("SELECT nama_produk, (SELECT nama_satuan FROM satuan WHERE id = produk.satuan_id) as nama_satuan FROM produk WHERE id = ?", [$ing['produk_id']]);
            
            $results[] = [
                'produk_id' => $ing['produk_id'],
                'nama_produk' => $p['nama_produk'],
                'satuan' => $p['nama_satuan'],
                'qty_needed' => $ing['calculated_qty'],
                'warehouse_stock' => $allocation['warehouse_stock'],
                'stock_status' => $allocation['stock_status'],
                'market_recommendation' => $allocation['market_recommendation'] ? json_decode($allocation['market_recommendation'], true) : null
            ];
            
            // Update overall status
            if ($allocation['stock_status'] == 'insufficient') {
                $overall_status = 'insufficient';
            } elseif ($allocation['stock_status'] == 'partial' && $overall_status == 'sufficient') {
                $overall_status = 'partial';
            }
        }
        
        echo json_encode([
            'success' => true,
            'is_recipe' => true,
            'overall_status' => $overall_status,
            'ingredients' => $results
        ]);
        
    } else {
        // Get product info
        $product = db_get_row("SELECT p.*, s.nama_satuan 
                               FROM produk p 
                               INNER JOIN satuan s ON p.satuan_id = s.id 
                               WHERE p.id = ?", [$produk_id]);
        
        if (!$product) {
            echo json_encode(['error' => 'Product not found']);
            exit();
        }
        
        // Calculate stock allocation
        $allocation = $menuHelper->calculateStockAllocation($produk_id, $qty_needed, $tanggal_menu);
        
        // Prepare response
        $response = [
            'success' => true,
            'is_recipe' => false,
            'produk_id' => $produk_id,
            'nama_produk' => $product['nama_produk'],
            'satuan' => $product['nama_satuan'],
            'qty_needed' => $qty_needed,
            'warehouse_stock' => $allocation['warehouse_stock'],
            'qty_from_warehouse' => $allocation['qty_from_warehouse'],
            'qty_to_purchase' => $allocation['qty_to_purchase'],
            'stock_status' => $allocation['stock_status'],
            'market_recommendation' => null
        ];
        
        // Add market recommendation if available
        if ($allocation['market_recommendation']) {
            $response['market_recommendation'] = json_decode($allocation['market_recommendation'], true);
        }
        
        echo json_encode($response);
    }
    
} catch (Exception $e) {
    error_log("Stock check error: " . $e->getMessage());
    echo json_encode(['error' => 'Error checking stock: ' . $e->getMessage()]);
}
