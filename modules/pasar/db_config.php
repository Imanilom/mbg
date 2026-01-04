<?php
/**
 * Konfigurasi Database untuk Marketlist MBG
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'marketlist_mbg');

// Create tables if not exist
function createMarketPriceTables($db) {
    // Table untuk harga pasar
    $sql = "CREATE TABLE IF NOT EXISTS `harga_pasar` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `produk_id` int(11) NOT NULL,
        `pasar_id` int(11) NOT NULL,
        `nama_pasar` varchar(100) NOT NULL,
        `bulan` int(2) NOT NULL,
        `tahun` int(4) NOT NULL,
        `harga_terendah` decimal(15,2) NOT NULL,
        `harga_tertinggi` decimal(15,2) NOT NULL,
        `harga_rata_rata` decimal(15,2) NOT NULL,
        `jumlah_hari_terdata` int(3) DEFAULT 0,
        `data_harian_json` text COMMENT 'JSON data harga harian',
        `scraped_at` datetime NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_pasar_produk_periode` (`produk_id`, `pasar_id`, `bulan`, `tahun`),
        KEY `produk_id` (`produk_id`),
        KEY `pasar_id` (`pasar_id`),
        KEY `periode` (`tahun`, `bulan`),
        FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $db->query($sql);
    
    // Table untuk log scraping
    $sql = "CREATE TABLE IF NOT EXISTS `scraping_log` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `bulan` int(2) NOT NULL,
        `tahun` int(4) NOT NULL,
        `jumlah_pasar` int(11) DEFAULT 0,
        `jumlah_komoditas` int(11) DEFAULT 0,
        `status` enum('success','partial','failed') DEFAULT 'success',
        `error_message` text,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `periode` (`tahun`, `bulan`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $db->query($sql);
    
    // View untuk analisis harga pasar
    $sql = "CREATE OR REPLACE VIEW `v_analisis_harga_pasar` AS
    SELECT 
        hp.id,
        p.kode_produk,
        p.nama_produk,
        hp.nama_pasar,
        CONCAT(LPAD(hp.bulan, 2, '0'), '-', hp.tahun) AS periode,
        hp.harga_terendah,
        hp.harga_tertinggi,
        hp.harga_rata_rata,
        hp.jumlah_hari_terdata,
        (hp.harga_tertinggi - hp.harga_terendah) AS selisih_harga,
        ROUND(((hp.harga_tertinggi - hp.harga_terendah) / hp.harga_terendah * 100), 2) AS volatilitas_percent,
        s.nama_satuan,
        hp.scraped_at,
        hp.created_at
    FROM harga_pasar hp
    INNER JOIN produk p ON hp.produk_id = p.id
    INNER JOIN satuan s ON p.satuan_id = s.id
    ORDER BY hp.tahun DESC, hp.bulan DESC, p.nama_produk, hp.nama_pasar;";
    
    $db->query($sql);
    
    // View untuk perbandingan harga antar pasar
    $sql = "CREATE OR REPLACE VIEW `v_perbandingan_harga_antar_pasar` AS
    SELECT 
        p.nama_produk,
        s.nama_satuan,
        hp.bulan,
        hp.tahun,
        GROUP_CONCAT(DISTINCT hp.nama_pasar ORDER BY hp.nama_pasar) AS daftar_pasar,
        COUNT(DISTINCT hp.nama_pasar) AS jumlah_pasar,
        MIN(hp.harga_terendah) AS harga_terendah_global,
        MAX(hp.harga_tertinggi) AS harga_tertinggi_global,
        ROUND(AVG(hp.harga_rata_rata), 2) AS harga_rata_rata_global,
        ROUND(STD(hp.harga_rata_rata), 2) AS standar_deviasi
    FROM harga_pasar hp
    INNER JOIN produk p ON hp.produk_id = p.id
    INNER JOIN satuan s ON p.satuan_id = s.id
    GROUP BY p.nama_produk, s.nama_satuan, hp.bulan, hp.tahun
    HAVING COUNT(DISTINCT hp.nama_pasar) > 1
    ORDER BY hp.tahun DESC, hp.bulan DESC, p.nama_produk;";
    
    $db->query($sql);
    
    // View untuk rekomendasi pembelian
    $sql = "CREATE OR REPLACE VIEW `v_rekomendasi_pembelian` AS
    SELECT 
        p.nama_produk,
        p.kode_produk,
        s.nama_satuan,
        hp.nama_pasar,
        hp.harga_terendah AS harga_pasar,
        p.harga_estimasi AS harga_sistem,
        CASE 
            WHEN hp.harga_terendah < p.harga_estimasi * 0.9 THEN 'DIBAWAH_HARGA'
            WHEN hp.harga_terendah > p.harga_estimasi * 1.1 THEN 'DIATAS_HARGA'
            ELSE 'NORMAL'
        END AS status_harga,
        ROUND(ABS(hp.harga_terendah - p.harga_estimasi) / p.harga_estimasi * 100, 2) AS selisih_percent,
        CONCAT(LPAD(hp.bulan, 2, '0'), '-', hp.tahun) AS periode_harga,
        hp.scraped_at AS update_terakhir
    FROM harga_pasar hp
    INNER JOIN produk p ON hp.produk_id = p.id
    INNER JOIN satuan s ON p.satuan_id = s.id
    WHERE hp.tahun = YEAR(CURDATE()) 
        AND hp.bulan = MONTH(CURDATE())
    ORDER BY status_harga, selisih_percent DESC;";
    
    $db->query($sql);
    
    error_log("✅ Database tables untuk harga pasar berhasil dibuat/dicek");
}

// Initialize database tables when needed
try {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$db->connect_error) {
        createMarketPriceTables($db);
        $db->close();
    }
} catch (Exception $e) {
    error_log("Database initialization error: " . $e->getMessage());
}