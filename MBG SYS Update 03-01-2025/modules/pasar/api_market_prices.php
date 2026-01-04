<?php
/**
 * API Endpoint untuk akses data harga pasar
 */

require_once 'market_price_scraper.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

$action = $_GET['action'] ?? '';

try {
    $scraper = new MarketPriceScraper();
    
    switch ($action) {
        case 'scrape':
            // API untuk scraping manual
            $scraper->handleRequest();
            break;
            
        case 'get_prices':
            // Get harga pasar untuk produk tertentu
            $produkId = $_GET['produk_id'] ?? null;
            $year = $_GET['year'] ?? date('Y');
            $month = $_GET['month'] ?? null;
            
            $prices = $scraper->getLatestMarketPrices($produkId);
            echo json_encode([
                'success' => true,
                'data' => $prices,
                'count' => count($prices)
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
            
        case 'get_statistics':
            // Get statistik harga
            $year = $_GET['year'] ?? date('Y');
            $statistics = $scraper->getPriceStatistics($year);
            
            echo json_encode([
                'success' => true,
                'year' => $year,
                'data' => $statistics,
                'count' => count($statistics)
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
            
        case 'get_pasar_list':
            // Get daftar pasar yang tersedia
            $sql = "SELECT DISTINCT pasar_id, nama_pasar FROM harga_pasar ORDER BY nama_pasar";
            $stmt = $scraper->db->prepare($sql);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $pasarList = [];
            while ($row = $result->fetch_assoc()) {
                $pasarList[] = $row;
            }
            
            echo json_encode([
                'success' => true,
                'data' => $pasarList,
                'count' => count($pasarList)
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
            
        case 'get_produk_list':
            // Get daftar produk dengan harga pasar
            $sql = "SELECT DISTINCT 
                    p.id,
                    p.kode_produk,
                    p.nama_produk,
                    s.nama_satuan,
                    COUNT(hp.id) AS jumlah_data_harga
                FROM harga_pasar hp
                INNER JOIN produk p ON hp.produk_id = p.id
                INNER JOIN satuan s ON p.satuan_id = s.id
                GROUP BY p.id, p.kode_produk, p.nama_produk, s.nama_satuan
                ORDER BY p.nama_produk";
            
            $stmt = $scraper->db->prepare($sql);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $produkList = [];
            while ($row = $result->fetch_assoc()) {
                $produkList[] = $row;
            }
            
            echo json_encode([
                'success' => true,
                'data' => $produkList,
                'count' => count($produkList)
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
            
        case 'get_price_trend':
            // Get trend harga untuk produk tertentu
            $produkId = $_GET['produk_id'] ?? null;
            $pasarId = $_GET['pasar_id'] ?? null;
            $year = $_GET['year'] ?? date('Y');
            
            if (!$produkId) {
                throw new Exception("Produk ID diperlukan");
            }
            
            $sql = "SELECT 
                    CONCAT(LPAD(bulan, 2, '0'), '-', tahun) AS periode,
                    harga_terendah,
                    harga_tertinggi,
                    harga_rata_rata,
                    nama_pasar,
                    scraped_at
                FROM harga_pasar 
                WHERE produk_id = ? 
                    AND tahun = ?";
            
            $params = [$produkId, $year];
            
            if ($pasarId) {
                $sql .= " AND pasar_id = ?";
                $params[] = $pasarId;
            }
            
            $sql .= " ORDER BY tahun, bulan";
            
            $stmt = $scraper->db->prepare($sql);
            $stmt->bind_param(str_repeat('i', count($params)), ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $trendData = [];
            while ($row = $result->fetch_assoc()) {
                $trendData[] = $row;
            }
            
            echo json_encode([
                'success' => true,
                'produk_id' => $produkId,
                'year' => $year,
                'data' => $trendData
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'error' => 'Action tidak valid',
                'available_actions' => [
                    'scrape' => 'Scrape data harga pasar',
                    'get_prices' => 'Get daftar harga',
                    'get_statistics' => 'Get statistik harga',
                    'get_pasar_list' => 'Get daftar pasar',
                    'get_produk_list' => 'Get daftar produk',
                    'get_price_trend' => 'Get trend harga'
                ]
            ], JSON_PRETTY_PRINT);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}