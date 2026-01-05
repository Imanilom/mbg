<?php
/**
 * Database Extension untuk Analisis Harga Pasar
 */

// Load database utama
require_once 'database.php';

/**
 * Buat tabel harga_pasar jika belum ada
 */
function create_market_price_tables() {
    global $conn;
    
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
    
    $conn->query($sql);
    
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
    
    $conn->query($sql);
    
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
    
    $conn->query($sql);
    
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
    ORDER BY status_harga, selisih_percent DESC;";
    
    $conn->query($sql);
    
    error_log("✅ Database tables untuk harga pasar berhasil dibuat/dicek");
}

/**
 * Get harga pasar statistics
 */
function get_market_price_stats($year = null, $month = null) {
    global $conn;
    
    if (!$year) $year = date('Y');
    
    $sql = "SELECT 
            COUNT(DISTINCT produk_id) as total_produk,
            COUNT(DISTINCT nama_pasar) as total_pasar,
            AVG(harga_rata_rata) as avg_harga_rata,
            MIN(harga_terendah) as min_harga_global,
            MAX(harga_tertinggi) as max_harga_global,
            MAX(scraped_at) as last_update
        FROM harga_pasar 
        WHERE tahun = ?";
    
    $params = [$year];
    
    if ($month) {
        $sql .= " AND bulan = ?";
        $params[] = $month;
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat('i', count($params)), ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

/**
 * Get produk dengan harga terbaik
 */
function get_best_price_products($limit = 10) {
    global $conn;
    
    $current_year = date('Y');
    $current_month = date('n');
    
    $sql = "SELECT 
            p.nama_produk,
            p.kode_produk,
            s.nama_satuan,
            hp.nama_pasar,
            hp.harga_terendah,
            hp.harga_rata_rata,
            ROUND((p.harga_estimasi - hp.harga_terendah) / p.harga_estimasi * 100, 2) AS diskon_percent,
            hp.scraped_at
        FROM harga_pasar hp
        INNER JOIN produk p ON hp.produk_id = p.id
        INNER JOIN satuan s ON p.satuan_id = s.id
        WHERE hp.tahun = ? AND hp.bulan = ?
            AND hp.harga_terendah < p.harga_estimasi * 0.95
            AND p.harga_estimasi > 0
        ORDER BY diskon_percent DESC
        LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $current_year, $current_month, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    
    return $products;
}

/**
 * Get trend harga untuk produk tertentu
 */
function get_price_trend($produk_id, $months = 6) {
    global $conn;
    
    $sql = "SELECT 
            CONCAT(LPAD(bulan, 2, '0'), '-', tahun) AS periode,
            AVG(harga_terendah) as avg_terendah,
            AVG(harga_tertinggi) as avg_tertinggi,
            AVG(harga_rata_rata) as avg_rata,
            COUNT(DISTINCT nama_pasar) as jumlah_pasar
        FROM harga_pasar 
        WHERE produk_id = ?
            AND (tahun * 100 + bulan) >= ?
        GROUP BY tahun, bulan
        ORDER BY tahun DESC, bulan DESC
        LIMIT ?";
    
    // Calculate start period
    $current_year = date('Y');
    $current_month = date('n');
    $start_period = $current_year * 100 + $current_month - ($months - 1);
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $produk_id, $start_period, $months);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $trends = [];
    while ($row = $result->fetch_assoc()) {
        $trends[] = $row;
    }
    
    return array_reverse($trends); // Oldest first for chart
}

/**
 * Get perbandingan harga antar pasar
 */
function get_market_comparison($produk_id, $year = null, $month = null) {
    global $conn;
    
    if (!$year) $year = date('Y');
    if (!$month) $month = date('n');
    
    $sql = "SELECT 
            nama_pasar,
            harga_terendah,
            harga_tertinggi,
            harga_rata_rata,
            jumlah_hari_terdata,
            scraped_at
        FROM harga_pasar 
        WHERE produk_id = ? AND tahun = ? AND bulan = ?
        ORDER BY harga_rata_rata ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $produk_id, $year, $month);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $markets = [];
    while ($row = $result->fetch_assoc()) {
        $markets[] = $row;
    }
    
    return $markets;
}

/**
 * Get produk yang sering diminta
 */
function get_frequently_requested_products($limit = 10) {
    global $conn;
    
    $sql = "SELECT 
            p.nama_produk,
            p.kode_produk,
            s.nama_satuan,
            COUNT(rd.id) as jumlah_request,
            SUM(rd.qty_request) as total_qty_request,
            AVG(p.harga_estimasi) as harga_estimasi_avg
        FROM request_detail rd
        INNER JOIN produk p ON rd.produk_id = p.id
        INNER JOIN satuan s ON p.satuan_id = s.id
        INNER JOIN request r ON rd.request_id = r.id
        WHERE r.tanggal_request >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
        GROUP BY p.id, p.nama_produk, p.kode_produk, s.nama_satuan
        ORDER BY jumlah_request DESC, total_qty_request DESC
        LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    
    return $products;
}

/**
 * Auto-create tables on first load
 */
// Auto-create tables on first load - DISABLED for production stability
// if ($conn) {
//     create_market_price_tables();
// }
