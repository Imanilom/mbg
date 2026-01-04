<?php
// modules/gudang/setup_opname.php
require_once '../../config/database.php';

$sql = "
CREATE TABLE IF NOT EXISTS `stok_opname` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `nomor_dokumen` varchar(50) NOT NULL,
  `keterangan` text,
  `status` enum('draft','final') DEFAULT 'draft',
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `stok_opname_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `opname_id` int(11) NOT NULL,
  `gudang_stok_id` int(11) NOT NULL COMMENT 'Reff to gudang_stok batch',
  `produk_id` int(11) NOT NULL,
  `qty_sistem` decimal(10,2) NOT NULL,
  `qty_fisik` decimal(10,2) NOT NULL,
  `selisih` decimal(10,2) GENERATED ALWAYS AS (`qty_fisik` - `qty_sistem`) STORED,
  `keterangan` text,
  PRIMARY KEY (`id`),
  KEY `opname_id` (`opname_id`),
  KEY `gudang_stok_id` (`gudang_stok_id`),
  FOREIGN KEY (`opname_id`) REFERENCES `stok_opname` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if (db_query($sql)) {
    echo "Tabel Stok Opname berhasil dibuat.";
} else {
    echo "Gagal membuat tabel: " . mysqli_error($conn);
}
?>
