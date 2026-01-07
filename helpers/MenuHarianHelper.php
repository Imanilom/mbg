<?php
/**
 * Menu Harian Helper Class
 * Handles daily menu operations including stock checking and purchase recommendations
 */

require_once __DIR__ . '/functions.php';

class MenuHarianHelper {
    
    private $db;
    
    public function __construct() {
        global $conn;
        $this->db = $conn;
    }
    
    /**
     * Generate menu number
     */
    public function generateMenuNumber() {
        return generate_number('MENU', 'menu_harian', 'no_menu');
    }
    
    /**
     * Check warehouse stock for a product
     */
    public function checkWarehouseStock($produk_id) {
        $sql = "SELECT 
                    COALESCE(SUM(qty_available), 0) as total_available
                FROM gudang_stok 
                WHERE produk_id = ? AND kondisi = 'baik'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $produk_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return floatval($row['total_available']);
    }
    
    /**
     * Get market price recommendation for a product
     */
    public function getMarketRecommendation($produk_id, $tanggal_menu) {
        $month = date('n', strtotime($tanggal_menu));
        $year = date('Y', strtotime($tanggal_menu));
        
        $sql = "SELECT 
                    pasar_id,
                    nama_pasar,
                    harga_terendah,
                    harga_rata_rata,
                    scraped_at
                FROM harga_pasar
                WHERE produk_id = ? 
                    AND bulan = ? 
                    AND tahun = ?
                ORDER BY harga_terendah ASC
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("iii", $produk_id, $month, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $recommendation = $result->fetch_assoc();
            $stmt->close();
            return $recommendation;
        }
        
        $stmt->close();
        return null;
    }
    
    /**
     * Calculate stock allocation for menu item
     */
    public function calculateStockAllocation($produk_id, $qty_needed, $tanggal_menu) {
        $warehouse_stock = floatval($this->checkWarehouseStock($produk_id));
        $qty_needed = floatval($qty_needed);
        
        $allocation = [
            'warehouse_stock' => $warehouse_stock,
            'qty_from_warehouse' => 0,
            'qty_to_purchase' => 0,
            'stock_status' => 'insufficient',
            'market_recommendation' => null
        ];
        
        // Use epsilon for float comparison to handle precision issues
        $epsilon = 0.00001;
        
        if ($warehouse_stock >= ($qty_needed - $epsilon)) {
            // Sufficient stock in warehouse
            $allocation['qty_from_warehouse'] = $qty_needed;
            $allocation['qty_to_purchase'] = 0;
            $allocation['stock_status'] = 'sufficient';
        } elseif ($warehouse_stock > $epsilon) {
            // Partial stock
            $allocation['qty_from_warehouse'] = $warehouse_stock;
            $allocation['qty_to_purchase'] = $qty_needed - $warehouse_stock;
            $allocation['stock_status'] = 'partial';
        } else {
            // No stock, need to purchase all
            $allocation['qty_from_warehouse'] = 0;
            $allocation['qty_to_purchase'] = $qty_needed;
            $allocation['stock_status'] = 'insufficient';
        }
        
        // Get market recommendation if purchase needed
        if ($allocation['qty_to_purchase'] > 0) {
            $recommendation = $this->getMarketRecommendation($produk_id, $tanggal_menu);
            if ($recommendation) {
                $allocation['market_recommendation'] = json_encode($recommendation);
            }
        }
        
        return $allocation;
    }
    
    /**
     * Reserve warehouse stock for menu item
     */
    public function reserveWarehouseStock($produk_id, $qty_to_reserve) {
        // Update gudang_stok to reserve quantity
        $sql = "UPDATE gudang_stok 
                SET qty_reserved = qty_reserved + ?
                WHERE produk_id = ? 
                    AND kondisi = 'baik'
                    AND qty_available >= ?
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("did", $qty_to_reserve, $produk_id, $qty_to_reserve);
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    }
    
    /**
     * Release warehouse stock reservation
     */
    public function releaseWarehouseStock($produk_id, $qty_to_release) {
        $sql = "UPDATE gudang_stok 
                SET qty_reserved = GREATEST(0, qty_reserved - ?)
                WHERE produk_id = ? AND kondisi = 'baik'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("di", $qty_to_release, $produk_id);
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    }
    
    /**
     * Get menu with details
     */
    public function getMenuWithDetails($menu_id) {
        // Get menu header
        $sql = "SELECT mh.*, 
                    u1.nama_lengkap as created_by_name,
                    u2.nama_lengkap as approved_by_name,
                    k.nama_kantor
                FROM menu_harian mh
                LEFT JOIN users u1 ON mh.created_by = u1.id
                LEFT JOIN users u2 ON mh.approved_by = u2.id
                LEFT JOIN kantor k ON mh.kantor_id = k.id
                WHERE mh.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $menu_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $menu = $result->fetch_assoc();
        $stmt->close();
        
        if (!$menu) {
            return null;
        }
        
        // Get menu details with stock info
        $sql = "SELECT * FROM v_menu_stock_check WHERE menu_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $menu_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $details = [];
        while ($row = $result->fetch_assoc()) {
            $details[] = $row;
        }
        $stmt->close();
        
        $menu['details'] = $details;
        return $menu;
    }
    
    /**
     * Get recipe ingredients with calculated quantities
     */
    public function getRecipeIngredients($resep_id, $total_porsi) {
        $sql = "SELECT rd.*, r.porsi_standar 
                FROM resep_detail rd
                JOIN resep r ON rd.resep_id = r.id
                WHERE rd.resep_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $resep_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $ingredients = [];
        while ($row = $result->fetch_assoc()) {
            // formula: (gramasi / porsi_standar) * total_porsi
            $qty_needed = ($row['gramasi'] / $row['porsi_standar']) * $total_porsi;
            $row['calculated_qty'] = $qty_needed;
            $ingredients[] = $row;
        }
        $stmt->close();
        
        return $ingredients;
    }

    /**
     * Create menus (supports multiple days with different menus)
     */
    public function createMenu($data) {
        try {
            $this->db->begin_transaction();
            
            // Handle new Planner format (array of 'days')
            if (isset($data['days']) && is_array($data['days'])) {
                $created_menu_ids = [];
                foreach ($data['days'] as $day) {
                    $created_menu_ids[] = $this->saveSingleMenu($day, $data['created_by']);
                }
                $this->db->commit();
                return $created_menu_ids;
            } 
            
            // Handle legacy format (one menu for multiple dates)
            $dates = is_array($data['tanggal_menu']) ? $data['tanggal_menu'] : [$data['tanggal_menu']];
            $created_menu_ids = [];

            foreach ($dates as $tanggal) {
                $dayData = $data;
                $dayData['tanggal_menu'] = $tanggal;
                $created_menu_ids[] = $this->saveSingleMenu($dayData, $data['created_by']);
            }
            
            $this->db->commit();
            return count($created_menu_ids) == 1 ? $created_menu_ids[0] : $created_menu_ids;
            
        } catch (Exception $e) {
            if ($this->db->connect_errno == 0 && $this->db->ping()) {
                $this->db->rollback();
            }
            error_log("Error creating menu: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Helper to save a single menu record and its details
     */
    private function saveSingleMenu($day, $created_by) {
        $no_menu = $this->generateMenuNumber();
        
        $sql = "INSERT INTO menu_harian (
                    no_menu, tanggal_menu, nama_menu, deskripsi, 
                    total_porsi, kantor_id, created_by, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'draft')";
        
        $stmt = $this->db->prepare($sql);
        $kantor_id = !empty($day['kantor_id']) ? intval($day['kantor_id']) : null;
        
        $stmt->bind_param("ssssiis", 
            $no_menu,
            $day['tanggal_menu'],
            $day['nama_menu'],
            $day['deskripsi'],
            $day['total_porsi'],
            $kantor_id,
            $created_by
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Gagal simpan header menu untuk tanggal: " . $day['tanggal_menu']);
        }
        $menu_id = $stmt->insert_id;
        $stmt->close();
        
        // Insert menu details
        if (!empty($day['items'])) {
            foreach ($day['items'] as $item) {
                if (!empty($item['resep_id'])) {
                    // Decompose recipe into products
                    $ingredients = $this->getRecipeIngredients($item['resep_id'], $day['total_porsi']);
                    foreach ($ingredients as $ing) {
                        $this->addMenuItem($menu_id, [
                            'produk_id' => $ing['produk_id'],
                            'qty_needed' => $ing['calculated_qty'],
                            'resep_id' => $item['resep_id'],
                            'keterangan' => $item['keterangan'] ?? ''
                        ], $day['tanggal_menu']);
                    }
                } else {
                    // Direct product
                    // User input (qty_needed) is strictly treated as "Quantity Per Portion" for consistency with UI.
                    // We must calculate Total Quantity = Qty Per Portion * Total Portions
                    $qty_per_portion = floatval($item['qty_needed']);
                    $total_qty_needed = $qty_per_portion * $day['total_porsi'];
                    
                    // We save the calculated TOTAL needed
                    $item['qty_needed'] = $total_qty_needed;
                    
                    $this->addMenuItem($menu_id, $item, $day['tanggal_menu']);
                }
            }
        }
        return $menu_id;
    }
    
    /**
     * Add item to menu
     */
    public function addMenuItem($menu_id, $item, $tanggal_menu) {
        $resep_id = $item['resep_id'] ?? null;
        
        // Calculate stock allocation
        $allocation = $this->calculateStockAllocation(
            $item['produk_id'], 
            $item['qty_needed'],
            $tanggal_menu
        );
        
        // Insert menu detail (added resep_id)
        $sql = "INSERT INTO menu_harian_detail (
                    menu_id, produk_id, resep_id, qty_needed, qty_from_warehouse, 
                    qty_to_purchase, market_recommendation, keterangan
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("iiidddss",
            $menu_id,
            $item['produk_id'],
            $resep_id,
            $item['qty_needed'],
            $allocation['qty_from_warehouse'],
            $allocation['qty_to_purchase'],
            $allocation['market_recommendation'],
            $item['keterangan']
        );
        
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    }
    
    /**
     * Approve menu and reserve stock
     */
    public function approveMenu($menu_id, $approved_by) {
        try {
            $this->db->begin_transaction();
            
            // Get menu details
            $sql = "SELECT produk_id, qty_from_warehouse 
                    FROM menu_harian_detail 
                    WHERE menu_id = ? AND qty_from_warehouse > 0";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("i", $menu_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            // Reserve stock for each item
            while ($row = $result->fetch_assoc()) {
                $this->reserveWarehouseStock(
                    $row['produk_id'], 
                    $row['qty_from_warehouse']
                );
                
                // Update detail to mark as reserved
                $update_sql = "UPDATE menu_harian_detail 
                              SET warehouse_reserved = 1 
                              WHERE menu_id = ? AND produk_id = ?";
                $update_stmt = $this->db->prepare($update_sql);
                $update_stmt->bind_param("ii", $menu_id, $row['produk_id']);
                $update_stmt->execute();
                $update_stmt->close();
            }
            $stmt->close();
            
            // Update menu status
            $sql = "UPDATE menu_harian 
                    SET status = 'approved', 
                        approved_by = ?, 
                        approved_at = NOW() 
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("ii", $approved_by, $menu_id);
            $stmt->execute();
            $stmt->close();
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error approving menu: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update menu header and details
     */
    public function updateMenu($menu_id, $data) {
        try {
            $this->db->begin_transaction();
            
            // 1. Update Header
            $sql = "UPDATE menu_harian 
                    SET tanggal_menu = ?, 
                        nama_menu = ?, 
                        deskripsi = ?, 
                        total_porsi = ?, 
                        kantor_id = ?,
                        updated_by = ?,
                        updated_at = NOW()
                    WHERE id = ? AND status = 'draft'";
            
            $stmt = $this->db->prepare($sql);
            $kantor_id = !empty($data['kantor_id']) ? intval($data['kantor_id']) : null;
            
            $stmt->bind_param("sssiisi", 
                $data['tanggal_menu'], 
                $data['nama_menu'], 
                $data['deskripsi'], 
                $data['total_porsi'], 
                $kantor_id,
                $data['updated_by'],
                $menu_id
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Gagal update menu header");
            }
            $stmt->close();
            
            // 2. Delete existing details (simplest way to handle updates)
            // Note: This only works because we are in draft mode and no stock is reserved yet
            $sql = "DELETE FROM menu_harian_detail WHERE menu_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("i", $menu_id);
            $stmt->execute();
            $stmt->close();
            
            // 3. Insert new details
            if (!empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    if (!empty($item['resep_id'])) {
                        // Decompose recipe
                        $ingredients = $this->getRecipeIngredients($item['resep_id'], $data['total_porsi']);
                        foreach ($ingredients as $ing) {
                            $this->addMenuItem($menu_id, [
                                'produk_id' => $ing['produk_id'],
                                'qty_needed' => $ing['calculated_qty'],
                                'resep_id' => $item['resep_id'],
                                'keterangan' => $item['keterangan'] ?? ''
                            ], $data['tanggal_menu']);
                        }
                    } else {
                        // Product (Per-Portion Logic)
                        $qty_per_portion = floatval($item['qty_needed']);
                        $total_qty_needed = $qty_per_portion * $data['total_porsi'];
                        
                        $item['qty_needed'] = $total_qty_needed;
                        $this->addMenuItem($menu_id, $item, $data['tanggal_menu']);
                    }
                }
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error updating menu: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete menu (only if draft)
     */
    public function deleteMenu($menu_id) {
        // Check if menu is draft
        $sql = "SELECT status FROM menu_harian WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $menu_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $menu = $result->fetch_assoc();
        $stmt->close();
        
        if ($menu['status'] !== 'draft') {
            return false; // Can only delete draft menus
        }
        
        // Delete menu (cascade will delete details)
        $sql = "DELETE FROM menu_harian WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $menu_id);
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    }
    /**
     * Get menus within a date range
     */
    public function getMenusByDateRange($start, $end) {
        $sql = "SELECT m.*, k.nama_kantor,
                (SELECT COUNT(*) FROM menu_harian_detail WHERE menu_id = m.id) as total_items
                FROM menu_harian m
                LEFT JOIN kantor k ON m.kantor_id = k.id
                WHERE m.tanggal_menu BETWEEN ? AND ?
                ORDER BY m.tanggal_menu ASC";
                
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ss", $start, $end);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $menus = [];
        while ($row = $result->fetch_assoc()) {
            // Group by date
            $date = $row['tanggal_menu'];
            if (!isset($menus[$date])) {
                $menus[$date] = [];
            }
            $menus[$date][] = $row;
        }
        $stmt->close();
        
        return $menus;
    }

    /**
     * Calculate shopping list for a date range
     */
    public function calculateShoppingList($start, $end) {
        // Aggregate all items from menus in the range
        // We only care about Approved or Draft menus (probably mostly Approved for final shopping, but Draft for planning)
        // Let's include all non-cancelled menus
        
        $sql = "SELECT 
                    p.id as produk_id,
                    p.nama_produk,
                    s.nama_satuan,
                    SUM(mhd.qty_needed) as total_qty_needed
                FROM menu_harian m
                JOIN menu_harian_detail mhd ON m.id = mhd.menu_id
                JOIN produk p ON mhd.produk_id = p.id
                JOIN satuan s ON p.satuan_id = s.id
                WHERE m.tanggal_menu BETWEEN ? AND ?
                AND m.status != 'cancelled'
                GROUP BY p.id, p.nama_produk, s.nama_satuan
                ORDER BY p.nama_produk";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ss", $start, $end);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $shopping_list = [];
        while ($row = $result->fetch_assoc()) {
            $produk_id = $row['produk_id'];
            $warehouse_stock = $this->checkWarehouseStock($produk_id);
            
            $item = [
                'produk_id' => $produk_id,
                'nama_produk' => $row['nama_produk'],
                'satuan' => $row['nama_satuan'],
                'total_qty_needed' => floatval($row['total_qty_needed']),
                'warehouse_stock' => $warehouse_stock,
                'qty_to_purchase' => max(0, floatval($row['total_qty_needed']) - $warehouse_stock)
            ];
            
            // Get market recommendation if purchase needed
            if ($item['qty_to_purchase'] > 0) {
                // Use start date for market price reference (or current date)
                $market_rec = $this->getMarketRecommendation($produk_id, $start);
                $item['market_recommendation'] = $market_rec;
            }
            
            $shopping_list[] = $item;
        }
        $stmt->close();
        
        return $shopping_list;
    }

    /**
     * Create menu from master catalog
     */
    public function createMenuFromMaster($menu_master_id, $tanggal_menu, $kantor_id, $total_porsi, $created_by) {
        try {
            $this->db->begin_transaction();
            
            // Get master menu
            $sql = "SELECT * FROM menu_master WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("i", $menu_master_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $master = $result->fetch_assoc();
            $stmt->close();
            
            if (!$master) {
                throw new Exception("Menu master tidak ditemukan");
            }
            
            // Generate menu number
            $no_menu = $this->generateMenuNumber();
            
            // Create menu_harian record
            $sql = "INSERT INTO menu_harian (
                        no_menu, menu_master_id, tanggal_menu, nama_menu, deskripsi, 
                        total_porsi, kantor_id, created_by, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft')";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("sisssiis", 
                $no_menu,
                $menu_master_id,
                $tanggal_menu,
                $master['nama_menu'],
                $master['deskripsi'],
                $total_porsi,
                $kantor_id,
                $created_by
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Gagal membuat menu harian");
            }
            $menu_id = $stmt->insert_id;
            $stmt->close();
            
            // Get master menu items
            $sql = "SELECT * FROM menu_master_detail WHERE menu_master_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("i", $menu_master_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($item = $result->fetch_assoc()) {
                if (!empty($item['resep_id'])) {
                    // Recipe: decompose into products
                    $ingredients = $this->getRecipeIngredients($item['resep_id'], $total_porsi);
                    foreach ($ingredients as $ing) {
                        $this->addMenuItem($menu_id, [
                            'produk_id' => $ing['produk_id'],
                            'qty_needed' => $ing['calculated_qty'],
                            'resep_id' => $item['resep_id'],
                            'keterangan' => $item['keterangan'] ?? ''
                        ], $tanggal_menu);
                    }
                } else {
                    // Product: scale quantity by total portions
                    $qty_per_portion = floatval($item['qty_needed']);
                    $total_qty_needed = $qty_per_portion * $total_porsi;
                    
                    $this->addMenuItem($menu_id, [
                        'produk_id' => $item['produk_id'],
                        'qty_needed' => $total_qty_needed,
                        'resep_id' => null,
                        'keterangan' => $item['keterangan'] ?? ''
                    ], $tanggal_menu);
                }
            }
            $stmt->close();
            
            $this->db->commit();
            return $menu_id;
            
        } catch (Exception $e) {
            if ($this->db->connect_errno == 0 && $this->db->ping()) {
                $this->db->rollback();
            }
            error_log("Error creating menu from master: " . $e->getMessage());
            throw $e;
        }
    }
}
