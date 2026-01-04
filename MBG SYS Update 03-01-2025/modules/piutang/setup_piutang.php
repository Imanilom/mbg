<?php
// modules/piutang/setup_piutang.php
require_once '../../config/database.php';

$sql = "
CREATE TABLE IF NOT EXISTS `piutang` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `jatuh_tempo` date DEFAULT NULL,
  `tipe_referensi` enum('distribusi') DEFAULT 'distribusi',
  `referensi_id` int(11) NOT NULL,
  `no_referensi` varchar(50) NOT NULL COMMENT 'Nomor Surat Jalan / Dokumen',
  `kantor_id` int(11) DEFAULT NULL,
  `total_piutang` decimal(15,2) DEFAULT 0.00,
  `total_bayar` decimal(15,2) DEFAULT 0.00,
  `sisa_piutang` decimal(15,2) GENERATED ALWAYS AS (`total_piutang` - `total_bayar`) STORED,
  `status` enum('belum_lunas','sebagian','lunas') DEFAULT 'belum_lunas',
  `keterangan` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `kantor_id` (`kantor_id`),
  KEY `referensi` (`tipe_referensi`, `referensi_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pembayaran_piutang` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `piutang_id` int(11) NOT NULL,
  `tanggal_bayar` date NOT NULL,
  `jumlah_bayar` decimal(15,2) NOT NULL,
  `metode_bayar` varchar(50) DEFAULT 'transfer',
  `bukti_bayar` varchar(255) DEFAULT NULL,
  `keterangan` text,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `piutang_id` (`piutang_id`),
  FOREIGN KEY (`piutang_id`) REFERENCES `piutang` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if (db_query($sql)) {
    echo "Tabel Piutang & Pembayaran berhasil dibuat.";
} else {
    echo "Gagal membuat tabel: " . mysqli_error($conn);
}
?>
