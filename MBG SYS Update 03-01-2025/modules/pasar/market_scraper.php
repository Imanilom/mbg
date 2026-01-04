<?php
/**
 * Worker PHP untuk scraping data harga pasar Kepokmas dan insert ke database Marketlist MBG
 * 
 * @package MarketPriceScraper
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

// Load database configuration
require_once 'db_config.php';

class MarketPriceScraper {
    
    private $db;
    private $baseUrl = 'http://kepokmas.cirebonkab.go.id/statistik-wilayah';
    private $monthNames = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    /**
     * Constructor - Initialize database connection
     */
    public function __construct() {
        try {
            $this->db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($this->db->connect_error) {
                throw new Exception("Connection failed: " . $this->db->connect_error);
            }
            $this->db->set_charset("utf8mb4");
        } catch (Exception $e) {
            error_log("Database Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Main handler untuk request
     */
    public function handleRequest() {
        try {
            // Ambil parameter dari GET
            $pasar = isset($_GET['pasar']) ? (int)$_GET['pasar'] : null;
            $month = isset($_GET['month']) ? str_pad($_GET['month'], 2, '0', STR_PAD_LEFT) : null;
            $year = isset($_GET['year']) ? $_GET['year'] : null;
            
            // Jika tidak ada parameter bulan, gunakan bulan sekarang
            if (!$month || !$year) {
                $today = new DateTime();
                $month = $today->format('m');
                $year = $today->format('Y');
            }
            
            $currentMonthYear = "{$month}-{$year}";
            
            // Tentukan ID pasar yang akan di-scrape
            $marketIds = [];
            if ($pasar) {
                $marketIds = [$pasar];
            } else {
                // Semua ID pasar yang valid dari Kepokmas
                $validMarketIds = [3, 16, 17, 18, 19, 20, 21];
                foreach ($validMarketIds as $id) {
                    $marketIds[] = $id;
                }
            }
            
            $results = [];
            $totalInserted = 0;
            
            foreach ($marketIds as $pasarId) {
                try {
                    $marketData = $this->fetchMarketData($pasarId, $currentMonthYear);
                    if ($marketData) {
                        $results[] = $marketData;
                        
                        // Insert ke database
                        $inserted = $this->insertMarketDataToDB($marketData);
                        $totalInserted += $inserted;
                        
                        error_log("✅ Berhasil mengambil dan insert data untuk Pasar ID {$pasarId} - {$inserted} komoditas");
                    }
                    
                    // Delay antar request
                    usleep(800000); // 800ms
                    
                } catch (Exception $e) {
                    error_log("❌ Error pada Pasar ID {$pasarId}: " . $e->getMessage());
                    continue;
                }
            }
            
            // Return response JSON
            echo json_encode([
                'success' => true,
                'timestamp' => date('c'),
                'month_year' => $currentMonthYear,
                'total_markets_scanned' => count($marketIds),
                'total_markets_found' => count($results),
                'total_commodities_inserted' => $totalInserted,
                'markets' => array_map(function($market) {
                    return [
                        'market_id' => $market['market_id'],
                        'market_name' => $market['market_name'],
                        'total_commodities' => $market['total_commodities']
                    ];
                }, $results)
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => date('c')
            ], JSON_PRETTY_PRINT);
        }
    }
    
    /**
     * Ambil data untuk satu pasar
     */
    private function fetchMarketData($pasarId, $monthYear) {
        $url = "{$this->baseUrl}?pasar={$pasarId}&bulan={$monthYear}";
        
        // Fetch HTML menggunakan cURL
        $html = $this->curlGet($url);
        
        if (!$html) {
            return null;
        }
        
        // Parse data dari HTML
        return $this->parseMarketData($html, $pasarId, $monthYear);
    }
    
    /**
     * Fetch HTML menggunakan cURL
     */
    private function curlGet($url) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_ENCODING => 'gzip, deflate',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
                'Cache-Control: no-cache',
                'Connection: keep-alive'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            error_log("cURL Error: " . curl_error($ch));
            curl_close($ch);
            return null;
        }
        
        curl_close($ch);
        
        // Check jika 404 atau error
        if ($httpCode == 404) {
            error_log("⏭️ Pasar ID {$pasarId} tidak ditemukan (404)");
            return null;
        } elseif ($httpCode != 200) {
            error_log("⚠️ HTTP Error {$httpCode} untuk Pasar ID {$pasarId}");
            return null;
        }
        
        return $response;
    }
    
    /**
     * Parse data dari HTML
     */
    private function parseMarketData($html, $pasarId, $monthYear) {
        try {
            // Ekstrak nama pasar dari HTML
            $marketName = $this->extractMarketName($html, $pasarId);
            
            // Parse bulan dan tahun
            list($month, $year) = explode('-', $monthYear);
            $monthName = $this->monthNames[(int)$month - 1];
            
            // Cari tabel data
            $tableHtml = $this->extractTable($html);
            if (!$tableHtml) {
                error_log("📊 Tabel tidak ditemukan untuk Pasar ID {$pasarId}");
                return null;
            }
            
            // Parse baris tabel
            $rows = $this->extractTableRows($tableHtml);
            $commodities = $this->parseCommodities($rows);
            
            return [
                'market_id' => $pasarId,
                'market_name' => $marketName,
                'full_name' => "Pasar {$marketName}",
                'period' => "{$monthName} {$year}",
                'month' => (int)$month,
                'year' => (int)$year,
                'total_commodities' => count($commodities),
                'commodities' => $commodities,
                'data_url' => "{$this->baseUrl}?pasar={$pasarId}&bulan={$monthYear}",
                'scraped_at' => date('c')
            ];
            
        } catch (Exception $e) {
            error_log("❌ Error parsing data untuk Pasar ID {$pasarId}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Ekstrak nama pasar dari HTML
     */
    private function extractMarketName($html, $pasarId) {
        $pattern = '/Statistik Pasar ([^<]+) Pada/';
        if (preg_match($pattern, $html, $matches)) {
            $name = trim($matches[1]);
            // Hapus kata "Pasar " jika ada di awal
            $name = preg_replace('/^Pasar\s+/', '', $name);
            return $name;
        }
        
        // Fallback: coba dari dropdown select
        $pattern = '/<option value="' . $pasarId . '" [^>]*>([^<]+)<\/option>/';
        if (preg_match($pattern, $html, $matches)) {
            return trim($matches[1]);
        }
        
        return "Pasar_{$pasarId}";
    }
    
    /**
     * Ekstrak tabel dari HTML
     */
    private function extractTable($html) {
        // Cari tabel dengan class yang spesifik
        $pattern = '/<table[^>]*class="[^"]*table-pasar[^"]*"[^>]*>.*?<\/table>/s';
        
        if (preg_match($pattern, $html, $matches)) {
            return $matches[0];
        }
        
        return null;
    }
    
    /**
     * Ekstrak baris dari tabel
     */
    private function extractTableRows($tableHtml) {
        $rows = [];
        
        // Cari semua baris <tr>
        $pattern = '/<tr[^>]*>.*?<\/tr>/s';
        preg_match_all($pattern, $tableHtml, $matches);
        
        foreach ($matches[0] as $rowHtml) {
            $rows[] = $rowHtml;
        }
        
        return $rows;
    }
    
    /**
     * Parse komoditas dari baris tabel
     */
    private function parseCommodities($rows) {
        $commodities = [];
        
        foreach ($rows as $index => $rowHtml) {
            // Skip header rows (2 baris pertama)
            if ($index < 2) continue;
            
            $cells = $this->extractRowCells($rowHtml);
            
            // Minimal perlu ada: no, nama, dan beberapa harga
            if (count($cells) >= 36) {
                $commodity = $this->parseCommodityRow($cells, $index - 1);
                if ($commodity) {
                    $commodities[] = $commodity;
                }
            }
        }
        
        return $commodities;
    }
    
    /**
     * Parse satu baris komoditas
     */
    private function parseCommodityRow($cells, $rowIndex) {
        $rowNumber = (int)$cells[0] ?: ($rowIndex + 1);
        $commodityName = trim($cells[1]);
        
        // Skip jika nama komoditas kosong
        if (empty($commodityName)) {
            return null;
        }
        
        // Parse harga harian (kolom 2-32)
        $dailyPrices = [];
        $validPrices = [];
        
        for ($day = 1; $day <= 31; $day++) {
            $cellIndex = 1 + $day; // cells[2] = hari ke-1
            
            if (isset($cells[$cellIndex])) {
                $priceText = trim($cells[$cellIndex]);
                
                // Skip sel kosong
                if ($priceText !== '' && $priceText !== '&nbsp;') {
                    $cleanPrice = $this->cleanPrice($priceText);
                    
                    if ($cleanPrice > 0) {
                        $dailyPrices[] = [
                            'day' => $day,
                            'price' => $cleanPrice
                        ];
                        $validPrices[] = $cleanPrice;
                    }
                }
            }
        }
        
        // Skip jika tidak ada harga valid
        if (empty($validPrices)) {
            return null;
        }
        
        // Parse statistik dari kolom terakhir
        $lowest = $this->parseStatistic($cells, 33);
        $highest = $this->parseStatistic($cells, 34);
        $average = $this->parseStatistic($cells, 35, true);
        
        return [
            'no' => $rowNumber,
            'name' => $commodityName,
            'daily_prices' => $dailyPrices,
            'price_count' => count($validPrices),
            'lowest_price' => $lowest ?: min($validPrices),
            'highest_price' => $highest ?: max($validPrices),
            'average_price' => $average ?: (array_sum($validPrices) / count($validPrices))
        ];
    }
    
    /**
     * Ekstrak sel dari baris
     */
    private function extractRowCells($rowHtml) {
        $cells = [];
        
        // Cari semua <td> dalam baris
        $pattern = '/<td[^>]*>(.*?)<\/td>/s';
        preg_match_all($pattern, $rowHtml, $matches);
        
        foreach ($matches[1] as $cellContent) {
            // Bersihkan HTML tags
            $text = strip_tags($cellContent);
            $text = str_replace('&nbsp;', '', $text);
            $text = trim($text);
            $cells[] = $text;
        }
        
        return $cells;
    }
    
    /**
     * Clean harga text
     */
    private function cleanPrice($priceText) {
        // Hapus titik pemisah ribuan
        $clean = str_replace('.', '', $priceText);
        // Ganti koma dengan titik untuk desimal
        $clean = str_replace(',', '.', $clean);
        // Hapus karakter non-numerik kecuali titik
        $clean = preg_replace('/[^0-9.]/', '', $clean);
        
        return (float)$clean;
    }
    
    /**
     * Parse statistik dari sel
     */
    private function parseStatistic($cells, $index, $isAverage = false) {
        if (isset($cells[$index])) {
            $value = $this->cleanPrice($cells[$index]);
            return $value > 0 ? $value : null;
        }
        return null;
    }
    
    /**
     * Insert data pasar ke database Marketlist MBG
     */
    private function insertMarketDataToDB($marketData) {
        $inserted = 0;
        
        try {
            // Mulai transaction
            $this->db->begin_transaction();
            
            // Insert atau update data pasar ke tabel khusus harga pasar
            foreach ($marketData['commodities'] as $commodity) {
                $success = $this->insertCommodityPrice($marketData, $commodity);
                if ($success) {
                    $inserted++;
                }
            }
            
            // Commit transaction
            $this->db->commit();
            
            return $inserted;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error inserting market data: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Insert harga komoditas ke tabel khusus
     */
    private function insertCommodityPrice($marketData, $commodity) {
        try {
            // Normalize nama komoditas
            $normalizedName = $this->normalizeCommodityName($commodity['name']);
            
            // Cari atau buat produk di database
            $produkId = $this->findOrCreateProduct($normalizedName);
            
            // Insert data harga ke tabel harga_pasar
            $sql = "INSERT INTO harga_pasar (
                produk_id,
                pasar_id,
                nama_pasar,
                bulan,
                tahun,
                harga_terendah,
                harga_tertinggi,
                harga_rata_rata,
                jumlah_hari_terdata,
                data_harian_json,
                scraped_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                harga_terendah = VALUES(harga_terendah),
                harga_tertinggi = VALUES(harga_tertinggi),
                harga_rata_rata = VALUES(harga_rata_rata),
                jumlah_hari_terdata = VALUES(jumlah_hari_terdata),
                data_harian_json = VALUES(data_harian_json),
                scraped_at = VALUES(scraped_at),
                updated_at = CURRENT_TIMESTAMP";
            
            $stmt = $this->db->prepare($sql);
            
            // Konversi data harian ke JSON
            $dailyPricesJson = json_encode($commodity['daily_prices'], JSON_UNESCAPED_UNICODE);
            
            $stmt->bind_param(
                "iissidddiss",
                $produkId,
                $marketData['market_id'],
                $marketData['market_name'],
                $marketData['month'],
                $marketData['year'],
                $commodity['lowest_price'],
                $commodity['highest_price'],
                $commodity['average_price'],
                $commodity['price_count'],
                $dailyPricesJson,
                $marketData['scraped_at']
            );
            
            $stmt->execute();
            $stmt->close();
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error inserting commodity price: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Normalize nama komoditas untuk konsistensi
     */
    private function normalizeCommodityName($name) {
        // Standardize nama-nama umum
        $normalizations = [
            '/^Minyak\s+Goreng\s+Curah$/i' => 'Minyak Goreng Curah',
            '/^Minyak\s+Goreng\s+Bimoli$/i' => 'Minyak Goreng Bimoli',
            '/^Minyak\s+Goreng\s+Tropical$/i' => 'Minyak Goreng Tropical',
            '/^Gula\s+Pasir\s+lokal$/i' => 'Gula Pasir Lokal',
            '/^Daging\s+Sapi\s+Murni$/i' => 'Daging Sapi Murni',
            '/^Daging\s+Ayam\s+Broiler$/i' => 'Daging Ayam Broiler',
            '/^Daging\s+Ayam\s+Kampung$/i' => 'Daging Ayam Kampung',
            '/^Telur\s+Ayam\s+Broiler$/i' => 'Telur Ayam Broiler',
            '/^Telur\s+Ayam\s+Kampung$/i' => 'Telur Ayam Kampung',
            '/^Susu\s+Kental\s+Manis\s+Bendera\s+Putih$/i' => 'Susu Kental Manis Bendera Putih',
            '/^Susu\s+Kental\s+Manis\s+Indomilk$/i' => 'Susu Kental Manis Indomilk',
            '/^Garam\s+Beryodium$/i' => 'Garam Beryodium',
            '/^Kacang\s+Hijau$/i' => 'Kacang Hijau',
            '/^Kacang\s+Tanah\/Suuk$/i' => 'Kacang Tanah',
            '/^Teri\s+Medan\s+Kering$/i' => 'Teri Medan Kering',
            '/^Ikan\s+Kembung$/i' => 'Ikan Kembung',
            '/^Tepung\s+Terigu\s+Segi\s+Tiga\s+Biru$/i' => 'Tepung Terigu Segitiga Biru',
            '/^Cabe\s+Merah\s+Keriting$/i' => 'Cabe Merah Keriting',
            '/^Cabe\s+Merah\s+Biasa$/i' => 'Cabe Merah Biasa',
            '/^Cabe\s+Rawit\s+Merah$/i' => 'Cabe Rawit Merah',
            '/^Cabe\s+Rawit\s+Hijau$/i' => 'Cabe Rawit Hijau',
            '/^Indomie\s+Kari\s+Ayam$/i' => 'Indomie Kari Ayam',
            '/^Gula\s+Merah$/i' => 'Gula Merah',
            '/^Gula\s+Batu$/i' => 'Gula Batu',
            '/^Telur\s+Bebek\s+Mentah$/i' => 'Telur Bebek Mentah',
            '/^Telur\s+Bebek\s+Asin$/i' => 'Telur Bebek Asin',
            '/^Ikan\s+Asin\s+Tiga\s+Wajan$/i' => 'Ikan Asin Tiga Wajan',
            '/^Ikan\s+Bandeng$/i' => 'Ikan Bandeng',
            '/^Beras\s+Medium$/i' => 'Beras Medium',
            '/^Beras\s+Premium\s+I$/i' => 'Beras Premium I',
        ];
        
        foreach ($normalizations as $pattern => $replacement) {
            if (preg_match($pattern, $name)) {
                return $replacement;
            }
        }
        
        // Default: trim dan kapitalisasi kata pertama
        return ucwords(strtolower(trim($name)));
    }
    
    /**
     * Cari atau buat produk di database
     */
    private function findOrCreateProduct($commodityName) {
        // Cek apakah produk sudah ada
        $sql = "SELECT id FROM produk WHERE LOWER(nama_produk) = LOWER(?) LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $commodityName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row['id'];
        }
        $stmt->close();
        
        // Jika tidak ada, buat produk baru di kategori bahan baku
        $kodeProduk = $this->generateProductCode($commodityName);
        $jenisBarangId = 1; // ID untuk "Bahan Baku"
        $kategoriId = 1;    // ID untuk "Bahan Kimia" (default)
        $satuanId = 4;      // ID untuk "KG" (default)
        
        // Tentukan kategori berdasarkan nama komoditas
        if (preg_match('/(minyak|goreng)/i', $commodityName)) {
            $kategoriId = 1; // Bahan Kimia
            $satuanId = 5;   // Liter
        } elseif (preg_match('/(daging|ayam|sapi|telur|ikan)/i', $commodityName)) {
            $kategoriId = 4; // Bahan Tekstil? Sesuaikan dengan kebutuhan
            $satuanId = 8;   // Unit
        } elseif (preg_match('/(gula|garam|tepung|beras)/i', $commodityName)) {
            $kategoriId = 1; // Bahan Kimia
            $satuanId = 4;   // KG
        } elseif (preg_match('/(cabe|bawang|tomat|sayur)/i', $commodityName)) {
            $kategoriId = 4; // Bahan Tekstil? Sesuaikan dengan kebutuhan
            $satuanId = 4;   // KG
        } elseif (preg_match('/(indomie|mie)/i', $commodityName)) {
            $kategoriId = 2; // Bahan Plastik
            $satuanId = 1;   // PCS
        }
        
        $sql = "INSERT INTO produk (
            kode_produk,
            nama_produk,
            jenis_barang_id,
            kategori_id,
            satuan_id,
            tipe_item,
            status_produk,
            harga_estimasi,
            deskripsi,
            created_at
        ) VALUES (?, ?, ?, ?, ?, 'stok', 'running', 0.00, ?, CURRENT_TIMESTAMP)";
        
        $stmt = $this->db->prepare($sql);
        $description = "Komoditas pasar: " . $commodityName;
        $stmt->bind_param(
            "siiiis",
            $kodeProduk,
            $commodityName,
            $jenisBarangId,
            $kategoriId,
            $satuanId,
            $description
        );
        
        $stmt->execute();
        $productId = $stmt->insert_id;
        $stmt->close();
        
        return $productId;
    }
    
    /**
     * Generate kode produk unik
     */
    private function generateProductCode($commodityName) {
        $prefix = "PASAR-";
        $cleanedName = preg_replace('/[^A-Z]/i', '', substr($commodityName, 0, 3));
        $random = strtoupper(substr(md5($commodityName . time()), 0, 4));
        return $prefix . $cleanedName . "-" . $random;
    }
    
    /**
     * Fungsi untuk scheduled job (bisa dijadwal via cron)
     */
    public function runScheduledJob($month = null, $year = null) {
        if (!$month || !$year) {
            $today = new DateTime();
            $month = $today->format('m');
            $year = $today->format('Y');
        }
        
        error_log("🚀 Menjalankan scheduled scraping untuk {$month}-{$year}");
        
        $allResults = [];
        $totalInserted = 0;
        $validMarketIds = [3, 16, 17, 18, 19, 20, 21];
        
        foreach ($validMarketIds as $pasarId) {
            try {
                $marketData = $this->fetchMarketData($pasarId, "{$month}-{$year}");
                if ($marketData) {
                    $allResults[] = $marketData;
                    
                    // Insert ke database
                    $inserted = $this->insertMarketDataToDB($marketData);
                    $totalInserted += $inserted;
                }
                
                usleep(800000); // Delay 800ms
                
            } catch (Exception $e) {
                error_log("Error pada Pasar ID {$pasarId}: " . $e->getMessage());
            }
        }
        
        // Simpan log ke database
        $this->saveScrapingLog($month, $year, count($allResults), $totalInserted);
        
        error_log("✅ Scheduled job selesai: " . count($allResults) . " pasar, {$totalInserted} komoditas");
        
        return [
            'total_markets' => count($allResults),
            'total_commodities' => $totalInserted,
            'month' => $month,
            'year' => $year
        ];
    }
    
    /**
     * Simpan log scraping ke database
     */
    private function saveScrapingLog($month, $year, $totalMarkets, $totalCommodities) {
        try {
            $sql = "INSERT INTO scraping_log (
                bulan,
                tahun,
                jumlah_pasar,
                jumlah_komoditas,
                status,
                created_at
            ) VALUES (?, ?, ?, ?, 'success', CURRENT_TIMESTAMP)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("iiii", $month, $year, $totalMarkets, $totalCommodities);
            $stmt->execute();
            $stmt->close();
            
        } catch (Exception $e) {
            error_log("Error saving scraping log: " . $e->getMessage());
        }
    }
    
    /**
     * Get harga pasar terbaru untuk produk tertentu
     */
    public function getLatestMarketPrices($produkId = null) {
        $results = [];
        
        if ($produkId) {
            $sql = "SELECT 
                    hp.*,
                    p.nama_produk,
                    p.kode_produk,
                    s.nama_satuan
                FROM harga_pasar hp
                INNER JOIN produk p ON hp.produk_id = p.id
                INNER JOIN satuan s ON p.satuan_id = s.id
                WHERE hp.produk_id = ?
                ORDER BY hp.tahun DESC, hp.bulan DESC, hp.scraped_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("i", $produkId);
        } else {
            $sql = "SELECT 
                    hp.*,
                    p.nama_produk,
                    p.kode_produk,
                    s.nama_satuan
                FROM harga_pasar hp
                INNER JOIN produk p ON hp.produk_id = p.id
                INNER JOIN satuan s ON p.satuan_id = s.id
                ORDER BY hp.tahun DESC, hp.bulan DESC, hp.scraped_at DESC
                LIMIT 100";
            
            $stmt = $this->db->prepare($sql);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
        
        $stmt->close();
        return $results;
    }
    
    /**
     * Get statistik harga per produk
     */
    public function getPriceStatistics($year = null) {
        if (!$year) {
            $year = date('Y');
        }
        
        $sql = "SELECT 
                p.id AS produk_id,
                p.nama_produk,
                p.kode_produk,
                s.nama_satuan,
                COUNT(DISTINCT hp.nama_pasar) AS jumlah_pasar,
                AVG(hp.harga_rata_rata) AS rata_rata_harga,
                MIN(hp.harga_terendah) AS harga_terendah_terkecil,
                MAX(hp.harga_tertinggi) AS harga_tertinggi_terbesar,
                MAX(hp.scraped_at) AS update_terakhir
            FROM harga_pasar hp
            INNER JOIN produk p ON hp.produk_id = p.id
            INNER JOIN satuan s ON p.satuan_id = s.id
            WHERE hp.tahun = ?
            GROUP BY p.id, p.nama_produk, p.kode_produk, s.nama_satuan
            ORDER BY p.nama_produk";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $year);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $statistics = [];
        while ($row = $result->fetch_assoc()) {
            $statistics[] = $row;
        }
        
        $stmt->close();
        return $statistics;
    }
    
    public function __destruct() {
        if ($this->db) {
            $this->db->close();
        }
    }
}

// Jalankan scraper
try {
    $scraper = new MarketPriceScraper();
    
    // Cek jika ini scheduled job (CLI)
    if (php_sapi_name() === 'cli') {
        // Ambil parameter dari command line
        $options = getopt("m:y:p:");
        $month = $options['m'] ?? null;
        $year = $options['y'] ?? null;
        $pasar = $options['p'] ?? null;
        
        if ($pasar) {
            $_GET['pasar'] = $pasar;
        }
        if ($month && $year) {
            $_GET['month'] = $month;
            $_GET['year'] = $year;
        }
        
        $scraper->runScheduledJob($month, $year);
    } else {
        $scraper->handleRequest();
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('c')
    ], JSON_PRETTY_PRINT);
}