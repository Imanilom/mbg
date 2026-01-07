<?php
/**
 * Menu Catalog Helper Class
 * Handles Master Menu operations (definitions without dates)
 */

require_once __DIR__ . '/functions.php';

class MenuCatalogHelper {
    
    private $db;
    
    public function __construct() {
        global $conn;
        $this->db = $conn;
    }
    
    /**
     * Get all master menus
     */
    public function getAllMenus() {
        $sql = "SELECT mm.*, u.nama_lengkap as created_by_name,
                (SELECT COUNT(*) FROM menu_master_detail WHERE menu_master_id = mm.id) as total_items
                FROM menu_master mm
                LEFT JOIN users u ON mm.created_by = u.id
                ORDER BY mm.nama_menu ASC";
        
        $result = $this->db->query($sql);
        
        $menus = [];
        while ($row = $result->fetch_assoc()) {
            $menus[] = $row;
        }
        return $menus;
    }

    /**
     * Get single menu with details
     */
    public function getMenu($id) {
        // Header
        $sql = "SELECT * FROM menu_master WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $menu = $result->fetch_assoc();
        $stmt->close();
        
        if (!$menu) return null;
        
        // Details
        $sql = "SELECT mmd.*, 
                    p.nama_produk, p.kode_produk, s.nama_satuan,
                    r.nama_resep
                FROM menu_master_detail mmd
                LEFT JOIN produk p ON mmd.produk_id = p.id
                LEFT JOIN satuan s ON p.satuan_id = s.id
                LEFT JOIN resep r ON mmd.resep_id = r.id
                WHERE mmd.menu_master_id = ?";
                
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $details = [];
        while ($row = $result->fetch_assoc()) {
            $details[] = $row;
        }
        $stmt->close();
        
        $menu['items'] = $details;
        return $menu;
    }
    
    /**
     * Create new master menu
     */
    public function createMenu($data) {
        try {
            $this->db->begin_transaction();
            
            $sql = "INSERT INTO menu_master (nama_menu, deskripsi, created_by) VALUES (?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("ssi", $data['nama_menu'], $data['deskripsi'], $data['created_by']);
            
            if (!$stmt->execute()) {
                throw new Exception("Gagal simpan header menu");
            }
            $menu_id = $stmt->insert_id;
            $stmt->close();
            
            // Insert Items
            if (!empty($data['items'])) {
                $this->insertItems($menu_id, $data['items']);
            }
            
            $this->db->commit();
            return $menu_id;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Update master menu
     */
    public function updateMenu($id, $data) {
        try {
            $this->db->begin_transaction();
            
            $sql = "UPDATE menu_master SET nama_menu = ?, deskripsi = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("ssi", $data['nama_menu'], $data['deskripsi'], $id);
            
            if (!$stmt->execute()) {
                throw new Exception("Gagal update header menu");
            }
            $stmt->close();
            
            // Delete old items and insert new ones (simplest approach)
            $this->db->query("DELETE FROM menu_master_detail WHERE menu_master_id = " . intval($id));
            
            if (!empty($data['items'])) {
                $this->insertItems($id, $data['items']);
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Insert Items Helper
     */
    private function insertItems($menu_id, $items) {
        $sql = "INSERT INTO menu_master_detail (menu_master_id, produk_id, resep_id, qty_needed, keterangan, item_type, custom_name) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        
        foreach ($items as $item) {
            $produk_id = !empty($item['produk_id']) ? intval($item['produk_id']) : null;
            $resep_id = !empty($item['resep_id']) ? intval($item['resep_id']) : null;
            $qty = floatval($item['qty_needed']); 
            $keterangan = $item['keterangan'] ?? '';
            
            // Determine item type if not explicitly sent (backward compatibility)
            $item_type = $item['item_type'] ?? 'product'; // Default to product
            if ($resep_id) $item_type = 'recipe';
            
            $custom_name = $item['manual_nama'] ?? null;
            
            if ($item_type === 'manual' && empty($custom_name)) {
                // Skip invalid manual items? Or defaulting?
                 // Let's assume validation happens in frontend/controller
            }

            $stmt->bind_param("iiidsss", $menu_id, $produk_id, $resep_id, $qty, $keterangan, $item_type, $custom_name);
            $stmt->execute();
        }
        $stmt->close();
    }
    
    public function deleteMenu($id) {
        // Constraint cascade should handle details. 
        // Check if used in menu_harian? Maybe prevent delete if used.
        // For now, allow delete (menu_harian has ON DELETE SET NULL)
        
        $sql = "DELETE FROM menu_master WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
}
