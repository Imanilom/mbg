-- ================================================
-- DAILY MENU MANAGEMENT SYSTEM - DATABASE SCHEMA
-- ================================================

USE `marketlist_mbg`;

-- ================================================
-- TABLE: menu_harian (Daily Menu Header)
-- ================================================
CREATE TABLE IF NOT EXISTS `menu_harian` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `no_menu` varchar(50) NOT NULL,
  `tanggal_menu` date NOT NULL,
  `nama_menu` varchar(200) NOT NULL,
  `deskripsi` text,
  `total_porsi` int(11) DEFAULT 0 COMMENT 'Total portions to be prepared',
  `kantor_id` int(11) DEFAULT NULL COMMENT 'Target office, NULL = all offices',
  `status` enum('draft','approved','processing','completed','cancelled') DEFAULT 'draft',
  `created_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `notes` text COMMENT 'Additional notes',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `no_menu` (`no_menu`),
  KEY `tanggal_menu` (`tanggal_menu`),
  KEY `status` (`status`),
  KEY `kantor_id` (`kantor_id`),
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  FOREIGN KEY (`kantor_id`) REFERENCES `kantor` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- TABLE: menu_harian_detail (Menu Items)
-- ================================================
CREATE TABLE IF NOT EXISTS `menu_harian_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `menu_id` int(11) NOT NULL,
  `produk_id` int(11) NOT NULL,
  `qty_needed` decimal(10,2) NOT NULL COMMENT 'Total quantity needed',
  `qty_from_warehouse` decimal(10,2) DEFAULT 0.00 COMMENT 'Quantity from warehouse stock',
  `qty_to_purchase` decimal(10,2) DEFAULT 0.00 COMMENT 'Quantity to buy from market',
  `warehouse_reserved` tinyint(1) DEFAULT 0 COMMENT '1 if warehouse stock reserved',
  `market_recommendation` text COMMENT 'JSON: {pasar_id, nama_pasar, harga}',
  `pembelanjaan_id` int(11) DEFAULT NULL COMMENT 'Link to purchase record',
  `purchase_status` enum('pending','purchased','received') DEFAULT 'pending',
  `distribution_status` enum('pending','distributed','completed') DEFAULT 'pending',
  `keterangan` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `menu_id` (`menu_id`),
  KEY `produk_id` (`produk_id`),
  KEY `pembelanjaan_id` (`pembelanjaan_id`),
  FOREIGN KEY (`menu_id`) REFERENCES `menu_harian` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`),
  FOREIGN KEY (`pembelanjaan_id`) REFERENCES `pembelanjaan` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- VIEW: v_menu_stock_check
-- Shows menu items with warehouse stock availability
-- ================================================
CREATE OR REPLACE VIEW `v_menu_stock_check` AS
SELECT 
    mh.id AS menu_id,
    mh.no_menu,
    mh.tanggal_menu,
    mh.nama_menu,
    mh.status AS menu_status,
    mhd.id AS detail_id,
    p.id AS produk_id,
    p.kode_produk,
    p.nama_produk,
    s.nama_satuan,
    mhd.qty_needed,
    COALESCE(SUM(gs.qty_available), 0) AS warehouse_stock,
    mhd.qty_from_warehouse,
    mhd.qty_to_purchase,
    CASE 
        WHEN COALESCE(SUM(gs.qty_available), 0) >= mhd.qty_needed THEN 'sufficient'
        WHEN COALESCE(SUM(gs.qty_available), 0) > 0 THEN 'partial'
        ELSE 'insufficient'
    END AS stock_status,
    mhd.warehouse_reserved,
    mhd.purchase_status,
    mhd.distribution_status
FROM menu_harian mh
INNER JOIN menu_harian_detail mhd ON mh.id = mhd.menu_id
INNER JOIN produk p ON mhd.produk_id = p.id
INNER JOIN satuan s ON p.satuan_id = s.id
LEFT JOIN gudang_stok gs ON p.id = gs.produk_id AND gs.kondisi = 'baik'
GROUP BY mh.id, mhd.id, p.id, s.id
ORDER BY mh.tanggal_menu DESC, p.nama_produk;

-- ================================================
-- VIEW: v_menu_purchase_recommendation
-- Shows items needing purchase with market price recommendations
-- ================================================
CREATE OR REPLACE VIEW `v_menu_purchase_recommendation` AS
SELECT 
    mh.id AS menu_id,
    mh.no_menu,
    mh.tanggal_menu,
    mh.nama_menu,
    mhd.id AS detail_id,
    p.id AS produk_id,
    p.kode_produk,
    p.nama_produk,
    s.nama_satuan,
    mhd.qty_to_purchase,
    mhd.market_recommendation,
    hp.nama_pasar AS recommended_market,
    hp.harga_terendah AS recommended_price,
    (mhd.qty_to_purchase * hp.harga_terendah) AS estimated_cost,
    hp.bulan,
    hp.tahun,
    hp.scraped_at AS price_updated_at
FROM menu_harian mh
INNER JOIN menu_harian_detail mhd ON mh.id = mhd.menu_id
INNER JOIN produk p ON mhd.produk_id = p.id
INNER JOIN satuan s ON p.satuan_id = s.id
LEFT JOIN harga_pasar hp ON p.id = hp.produk_id 
    AND hp.bulan = MONTH(mh.tanggal_menu)
    AND hp.tahun = YEAR(mh.tanggal_menu)
    AND hp.harga_terendah = (
        SELECT MIN(hp2.harga_terendah)
        FROM harga_pasar hp2
        WHERE hp2.produk_id = p.id
        AND hp2.bulan = MONTH(mh.tanggal_menu)
        AND hp2.tahun = YEAR(mh.tanggal_menu)
    )
WHERE mhd.qty_to_purchase > 0
ORDER BY mh.tanggal_menu DESC, estimated_cost DESC;

-- ================================================
-- VIEW: v_menu_summary
-- Summary of all menus with statistics
-- ================================================
CREATE OR REPLACE VIEW `v_menu_summary` AS
SELECT 
    mh.id,
    mh.no_menu,
    mh.tanggal_menu,
    mh.nama_menu,
    mh.total_porsi,
    k.nama_kantor,
    mh.status,
    u1.nama_lengkap AS created_by_name,
    u2.nama_lengkap AS approved_by_name,
    COUNT(mhd.id) AS total_items,
    SUM(CASE WHEN mhd.qty_to_purchase > 0 THEN 1 ELSE 0 END) AS items_to_purchase,
    SUM(CASE WHEN mhd.warehouse_reserved = 1 THEN 1 ELSE 0 END) AS items_from_warehouse,
    SUM(CASE WHEN mhd.purchase_status = 'purchased' THEN 1 ELSE 0 END) AS items_purchased,
    SUM(CASE WHEN mhd.distribution_status = 'completed' THEN 1 ELSE 0 END) AS items_distributed,
    mh.created_at,
    mh.approved_at
FROM menu_harian mh
LEFT JOIN kantor k ON mh.kantor_id = k.id
LEFT JOIN users u1 ON mh.created_by = u1.id
LEFT JOIN users u2 ON mh.approved_by = u2.id
LEFT JOIN menu_harian_detail mhd ON mh.id = mhd.menu_id
GROUP BY mh.id
ORDER BY mh.tanggal_menu DESC;

-- ================================================
-- INSERT SAMPLE DATA (Optional - for testing)
-- ================================================

-- Add format for menu number in settings
INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_group`, `description`) 
VALUES ('format_no_menu', 'MENU/[TAHUN]/[BULAN]/[NOMOR]', 'format', 'Format Nomor Menu Harian')
ON DUPLICATE KEY UPDATE setting_value = 'MENU/[TAHUN]/[BULAN]/[NOMOR]';

-- ================================================
-- END OF SCHEMA
-- ================================================
