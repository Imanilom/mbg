-- ================================================
-- RECIPE MANAGEMENT SYSTEM - DATABASE SCHEMA
-- ================================================

USE `marketlist_mbg`;

-- ================================================
-- TABLE: resep (Recipe Header)
-- ================================================
CREATE TABLE IF NOT EXISTS `resep` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode_resep` varchar(50) NOT NULL,
  `nama_resep` varchar(200) NOT NULL,
  `deskripsi` text,
  `porsi_standar` int(11) DEFAULT 1 COMMENT 'Standard portion for this recipe',
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_resep` (`kode_resep`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- TABLE: resep_detail (Recipe Ingredients)
-- ================================================
CREATE TABLE IF NOT EXISTS `resep_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `resep_id` int(11) NOT NULL,
  `produk_id` int(11) NOT NULL,
  `gramasi` decimal(10,4) NOT NULL COMMENT 'Quantity per standard portion',
  `keterangan` text,
  PRIMARY KEY (`id`),
  KEY `resep_id` (`resep_id`),
  KEY `produk_id` (`produk_id`),
  FOREIGN KEY (`resep_id`) REFERENCES `resep` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- Update menu_harian_detail to include resep_id
-- ================================================
ALTER TABLE `menu_harian_detail` 
ADD COLUMN `resep_id` int(11) DEFAULT NULL AFTER `produk_id`,
ADD FOREIGN KEY (`resep_id`) REFERENCES `resep` (`id`);
