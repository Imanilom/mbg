-- Migration: Add margin calculation system
-- Date: 2026-01-03
-- Description: Add margin columns to distribusi_detail and create margin_summary table

-- 1. Add margin columns to distribusi_detail (hpp and harga_jual already exist)
ALTER TABLE `distribusi_detail`
ADD COLUMN IF NOT EXISTS `margin_per_unit` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Margin per unit (harga_jual - hpp)' AFTER `harga_jual`,
ADD COLUMN IF NOT EXISTS `total_margin` DECIMAL(15,2) GENERATED ALWAYS AS (`qty_kirim` * `margin_per_unit`) STORED COMMENT 'Total margin for this item' AFTER `margin_per_unit`;

-- 2. Create margin_summary table for daily aggregation
CREATE TABLE IF NOT EXISTS `margin_summary` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `tanggal` DATE NOT NULL,
  `produk_id` INT(11) NOT NULL,
  `total_qty` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `total_margin` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `avg_margin_per_unit` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `jumlah_transaksi` INT(11) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_daily_product` (`tanggal`, `produk_id`),
  KEY `idx_tanggal` (`tanggal`),
  KEY `idx_produk` (`produk_id`),
  CONSTRAINT `fk_margin_summary_produk` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create view for daily margin analysis
CREATE OR REPLACE VIEW v_margin_harian AS
SELECT 
    ms.tanggal,
    ms.produk_id,
    p.kode_produk,
    p.nama_produk,
    s.nama_satuan,
    ms.total_qty,
    ms.total_margin,
    ms.avg_margin_per_unit,
    ms.jumlah_transaksi,
    p.harga_beli as hpp,
    p.harga_jual_2 as harga_jual
FROM margin_summary ms
INNER JOIN produk p ON ms.produk_id = p.id
INNER JOIN satuan s ON p.satuan_id = s.id
ORDER BY ms.tanggal DESC, ms.total_margin DESC;

-- 4. Create view for supplier analysis
CREATE OR REPLACE VIEW v_supplier_termurah AS
SELECT 
    p.id as produk_id,
    p.kode_produk,
    p.nama_produk,
    pb.supplier_id,
    sup.nama_supplier,
    MIN(pd.harga_satuan) as harga_termurah,
    AVG(pd.harga_satuan) as harga_rata_rata,
    COUNT(DISTINCT pb.id) as jumlah_pembelian,
    MAX(pb.tanggal) as pembelian_terakhir
FROM produk p
LEFT JOIN pembelanjaan_detail pd ON p.id = pd.produk_id
LEFT JOIN pembelanjaan pb ON pd.pembelanjaan_id = pb.id
LEFT JOIN supplier sup ON pb.supplier_id = sup.id
WHERE pb.status = 'selesai'
GROUP BY p.id, pb.supplier_id, sup.nama_supplier
ORDER BY p.nama_produk, harga_termurah ASC;

-- 5. Create view for monthly profit summary
CREATE OR REPLACE VIEW v_margin_bulanan AS
SELECT 
    YEAR(ms.tanggal) as tahun,
    MONTH(ms.tanggal) as bulan,
    DATE_FORMAT(ms.tanggal, '%Y-%m') as periode,
    SUM(ms.total_margin) as total_margin_bulan,
    COUNT(DISTINCT ms.tanggal) as jumlah_hari,
    COUNT(DISTINCT ms.produk_id) as jumlah_produk,
    SUM(ms.jumlah_transaksi) as total_transaksi,
    AVG(ms.total_margin) as avg_margin_per_hari
FROM margin_summary ms
GROUP BY YEAR(ms.tanggal), MONTH(ms.tanggal)
ORDER BY tahun DESC, bulan DESC;

-- Verify the changes
SELECT 'Migration completed successfully' as status;
