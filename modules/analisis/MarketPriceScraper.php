<?php
/**
 * Market Price Scraper Class
 */
class MarketPriceScraper {
    
    private $db;
    private $baseUrl = 'http://kepokmas.cirebonkab.go.id/statistik-wilayah';
    private $monthNames = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    public function __construct() {
        global $conn;
        $this->db = $conn;
    }
    
    /**
     * Run scraping job
     */
    public function runScheduledJob($month = null, $year = null) {
        if (!$month || !$year) {
            $month = date('n');
            $year = date('Y');
        }
        
        $market_ids = [3, 16, 17, 18, 19, 20, 21];
        $total_inserted = 0;
        $successful_markets = 0;
        
        foreach ($market_ids as $pasar_id) {
            try {
                $market_data = $this->fetchMarketData($pasar_id, $month, $year);
                if ($market_data && !empty($market_data['commodities'])) {
                    $inserted = $this->insertMarketDataToDB($market_data);
                    $total_inserted += $inserted;
                    $successful_markets++;
                    
                    error_log("✅ Success for Pasar ID {$pasar_id}: {$inserted} commodities");
                }
                
                // Delay between requests
                sleep(1);
                
            } catch (Exception $e) {
                error_log("❌ Error for Pasar ID {$pasar_id}: " . $e->getMessage());
            }
        }
        
        // Save scraping log
        $this->saveScrapingLog($month, $year, $successful_markets, $total_inserted);
        
        return [
            'total_markets' => count($market_ids),
            'successful_markets' => $successful_markets,
            'total_commodities' => $total_inserted,
            'month' => $month,
            'year' => $year
        ];
    }
    
    /**
     * Fetch market data
     */
    private function fetchMarketData($pasar_id, $month, $year) {
        $month_year = sprintf('%02d-%d', $month, $year);
        $url = "{$this->baseUrl}?pasar={$pasar_id}&bulan={$month_year}";
        
        $html = $this->curlGet($url);
        if (!$html) {
            return null;
        }
        
        return $this->parseMarketData($html, $pasar_id, $month, $year);
    }
    
    /**
     * Parse HTML data
     */
    private function parseMarketData($html, $pasar_id, $month, $year) {
        try {
            // Extract market name
            preg_match('/Statistik Pasar ([^<]+) Pada/', $html, $matches);
            $market_name = isset($matches[1]) ? trim($matches[1]) : "Pasar_{$pasar_id}";
            $market_name = preg_replace('/^Pasar\s+/', '', $market_name);
            
            // Find table
            if (!preg_match('/<table[^>]*class="[^"]*table-pasar[^"]*"[^>]*>.*?<\/table>/s', $html, $table_match)) {
                return null;
            }
            
            $table_html = $table_match[0];
            preg_match_all('/<tr[^>]*>.*?<\/tr>/s', $table_html, $row_matches);
            
            $commodities = [];
            foreach ($row_matches[0] as $index => $row_html) {
                if ($index < 2) continue; // Skip header rows
                
                preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $row_html, $cell_matches);
                $cells = array_map(function($cell) {
                    $text = strip_tags($cell);
                    $text = str_replace('&nbsp;', '', $text);
                    return trim($text);
                }, $cell_matches[1]);
                
                if (count($cells) >= 36) {
                    $commodity = $this->parseCommodityRow($cells, $index - 1);
                    if ($commodity) {
                        $commodities[] = $commodity;
                    }
                }
            }
            
            return [
                'market_id' => $pasar_id,
                'market_name' => $market_name,
                'month' => $month,
                'year' => $year,
                'commodities' => $commodities,
                'scraped_at' => date('c')
            ];
            
        } catch (Exception $e) {
            error_log("Parse error for Pasar ID {$pasar_id}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Parse commodity row
     */
    private function parseCommodityRow($cells, $rowIndex) {
        $commodity_name = trim($cells[1]);
        if (empty($commodity_name)) {
            return null;
        }
        
        $valid_prices = [];
        for ($day = 1; $day <= 31; $day++) {
            $cell_index = 1 + $day;
            if (isset($cells[$cell_index])) {
                $price_text = trim($cells[$cell_index]);
                if ($price_text !== '' && $price_text !== '&nbsp;') {
                    $clean_price = $this->cleanPrice($price_text);
                    if ($clean_price > 0) {
                        $valid_prices[] = $clean_price;
                    }
                }
            }
        }
        
        if (empty($valid_prices)) {
            return null;
        }
        
        $lowest = isset($cells[33]) ? $this->cleanPrice($cells[33]) : min($valid_prices);
        $highest = isset($cells[34]) ? $this->cleanPrice($cells[34]) : max($valid_prices);
        $average = isset($cells[35]) ? $this->cleanPrice($cells[35], true) : (array_sum($valid_prices) / count($valid_prices));
        
        return [
            'name' => $commodity_name,
            'daily_prices' => $valid_prices,
            'lowest_price' => $lowest,
            'highest_price' => $highest,
            'average_price' => $average,
            'price_count' => count($valid_prices)
        ];
    }
    
    /**
     * Clean price text
     */
    private function cleanPrice($price_text, $is_average = false) {
        $clean = str_replace('.', '', $price_text);
        $clean = str_replace(',', '.', $clean);
        $clean = preg_replace('/[^0-9.]/', '', $clean);
        return (float)$clean;
    }
    
    /**
     * Insert to database
     */
    private function insertMarketDataToDB($market_data) {
        $inserted = 0;
        
        try {
            $this->db->begin_transaction();
            
            foreach ($market_data['commodities'] as $commodity) {
                $produk_id = $this->findOrCreateProduct($commodity['name']);
                
                $sql = "INSERT INTO harga_pasar (
                    produk_id, pasar_id, nama_pasar, bulan, tahun,
                    harga_terendah, harga_tertinggi, harga_rata_rata,
                    jumlah_hari_terdata, scraped_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    harga_terendah = VALUES(harga_terendah),
                    harga_tertinggi = VALUES(harga_tertinggi),
                    harga_rata_rata = VALUES(harga_rata_rata),
                    jumlah_hari_terdata = VALUES(jumlah_hari_terdata),
                    scraped_at = VALUES(scraped_at)";
                
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param(
                    "iissidddis",
                    $produk_id,
                    $market_data['market_id'],
                    $market_data['market_name'],
                    $market_data['month'],
                    $market_data['year'],
                    $commodity['lowest_price'],
                    $commodity['highest_price'],
                    $commodity['average_price'],
                    $commodity['price_count'],
                    $market_data['scraped_at']
                );
                
                $stmt->execute();
                $stmt->close();
                $inserted++;
            }
            
            $this->db->commit();
            return $inserted;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("DB insert error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Find or create product
     */
    private function findOrCreateProduct($commodity_name) {
        // Find existing product
        $stmt = $this->db->prepare("SELECT id FROM produk WHERE LOWER(nama_produk) = LOWER(?) LIMIT 1");
        $stmt->bind_param("s", $commodity_name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row['id'];
        }
        $stmt->close();
        
        // Create new product
        $kode_produk = "PASAR-" . strtoupper(substr(preg_replace('/[^A-Z]/i', '', $commodity_name), 0, 3)) . "-" . substr(md5($commodity_name), 0, 4);
        
        $sql = "INSERT INTO produk (
            kode_produk, nama_produk, jenis_barang_id, kategori_id, satuan_id,
            tipe_item, status_produk, harga_estimasi, deskripsi
        ) VALUES (?, ?, 1, 1, 4, 'stok', 'running', 0.00, ?)";
        
        $description = "Komoditas pasar: " . $commodity_name;
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("sss", $kode_produk, $commodity_name, $description);
        $stmt->execute();
        $product_id = $stmt->insert_id;
        $stmt->close();
        
        return $product_id;
    }
    
    /**
     * cURL request
     */
    private function curlGet($url) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        
        if ($http_code != 200) {
            return null;
        }
        
        return $response;
    }
    
    /**
     * Save scraping log
     */
    private function saveScrapingLog($month, $year, $markets, $commodities) {
        $status = ($markets > 0) ? 'success' : 'failed';
        if ($markets > 0 && $markets < 7) {
            $status = 'partial';
        }
        
        $sql = "INSERT INTO scraping_log (bulan, tahun, jumlah_pasar, jumlah_komoditas, status) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("iiiss", $month, $year, $markets, $commodities, $status);
        $stmt->execute();
        $stmt->close();
    }
}