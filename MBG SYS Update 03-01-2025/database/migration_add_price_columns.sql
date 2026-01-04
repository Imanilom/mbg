-- Migration: Add price columns to produk table
-- Date: 2026-01-03
-- Description: Add harga_beli, harga_jual_1, harga_jual_2 columns for price management

ALTER TABLE `produk` 
ADD COLUMN `harga_beli` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Purchase price from pembelanjaan' AFTER `harga_estimasi`,
ADD COLUMN `harga_jual_1` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Selling price 1 - editable by staf inventori' AFTER `harga_beli`,
ADD COLUMN `harga_jual_2` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Selling price 2 - editable by admin' AFTER `harga_jual_1`;

-- Verify the changes
SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_COMMENT 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'marketlist_mbg' 
  AND TABLE_NAME = 'produk' 
  AND COLUMN_NAME IN ('harga_beli', 'harga_jual_1', 'harga_jual_2');
