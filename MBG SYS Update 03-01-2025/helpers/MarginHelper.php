<?php
/**
 * Margin Helper
 * Helper functions for margin calculation and analysis
 */

class MarginHelper {
    
    /**
     * Calculate margin for a product distribution
     * @param int $produk_id Product ID
     * @param float $qty Quantity
     * @param float|null $harga_jual Override selling price (optional)
     * @return array ['hpp', 'harga_jual', 'margin_per_unit', 'total_margin']
     */
    public static function calculateMargin($produk_id, $qty, $harga_jual = null) {
        // Get product prices
        $produk = db_get_row("SELECT harga_beli as hpp, harga_jual_2 as harga_jual FROM produk WHERE id = " . intval($produk_id));
        
        if (!$produk) {
            return [
                'hpp' => 0,
                'harga_jual' => 0,
                'margin_per_unit' => 0,
                'total_margin' => 0
            ];
        }
        
        $hpp = floatval($produk['hpp']);
        $selling_price = $harga_jual !== null ? floatval($harga_jual) : floatval($produk['harga_jual']);
        $margin_per_unit = $selling_price - $hpp;
        $total_margin = $margin_per_unit * floatval($qty);
        
        return [
            'hpp' => $hpp,
            'harga_jual' => $selling_price,
            'margin_per_unit' => $margin_per_unit,
            'total_margin' => $total_margin
        ];
    }
    
    /**
     * Update daily margin summary
     * Aggregates margin data for a specific date
     * @param string $tanggal Date in Y-m-d format
     * @return bool Success status
     */
    public static function updateDailyMarginSummary($tanggal) {
        // Get all distribution details for the date
        $query = "
            SELECT 
                dd.produk_id,
                SUM(dd.qty_kirim) as total_qty,
                SUM(dd.total_margin) as total_margin,
                AVG(dd.margin_per_unit) as avg_margin_per_unit,
                COUNT(DISTINCT dd.distribusi_id) as jumlah_transaksi
            FROM distribusi_detail dd
            INNER JOIN distribusi d ON dd.distribusi_id = d.id
            WHERE DATE(d.tanggal_kirim) = '" . db_escape($tanggal) . "'
            AND dd.margin_per_unit IS NOT NULL
            GROUP BY dd.produk_id
        ";
        
        $results = db_get_all($query);
        
        foreach ($results as $row) {
            // Insert or update margin_summary
            $check = db_get_row("
                SELECT id FROM margin_summary 
                WHERE tanggal = '" . db_escape($tanggal) . "' 
                AND produk_id = " . intval($row['produk_id'])
            );
            
            $data = [
                'tanggal' => $tanggal,
                'produk_id' => $row['produk_id'],
                'total_qty' => $row['total_qty'],
                'total_margin' => $row['total_margin'],
                'avg_margin_per_unit' => $row['avg_margin_per_unit'],
                'jumlah_transaksi' => $row['jumlah_transaksi']
            ];
            
            if ($check) {
                db_update('margin_summary', $data, "id = " . $check['id']);
            } else {
                db_insert('margin_summary', $data);
            }
        }
        
        return true;
    }
    
    /**
     * Get monthly profit
     * @param int $bulan Month (1-12)
     * @param int $tahun Year
     * @return array ['total_margin', 'jumlah_hari', 'jumlah_produk', 'total_transaksi', 'avg_margin_per_hari']
     */
    public static function getMonthlyProfit($bulan, $tahun) {
        $query = "
            SELECT 
                SUM(total_margin) as total_margin,
                COUNT(DISTINCT tanggal) as jumlah_hari,
                COUNT(DISTINCT produk_id) as jumlah_produk,
                SUM(jumlah_transaksi) as total_transaksi,
                AVG(total_margin) as avg_margin_per_hari
            FROM margin_summary
            WHERE YEAR(tanggal) = " . intval($tahun) . "
            AND MONTH(tanggal) = " . intval($bulan);
        
        $result = db_get_row($query);
        
        return [
            'total_margin' => floatval($result['total_margin'] ?? 0),
            'jumlah_hari' => intval($result['jumlah_hari'] ?? 0),
            'jumlah_produk' => intval($result['jumlah_produk'] ?? 0),
            'total_transaksi' => intval($result['total_transaksi'] ?? 0),
            'avg_margin_per_hari' => floatval($result['avg_margin_per_hari'] ?? 0)
        ];
    }
    
    /**
     * Get cheapest supplier for a product
     * @param int $produk_id Product ID
     * @return array|null Supplier info with cheapest price
     */
    public static function getCheapestSupplier($produk_id) {
        $query = "
            SELECT 
                pb.supplier_id,
                sup.nama_supplier,
                MIN(pd.harga_satuan) as harga_termurah,
                AVG(pd.harga_satuan) as harga_rata_rata,
                COUNT(DISTINCT pb.id) as jumlah_pembelian,
                MAX(pb.tanggal) as pembelian_terakhir
            FROM pembelanjaan_detail pd
            INNER JOIN pembelanjaan pb ON pd.pembelanjaan_id = pb.id
            LEFT JOIN supplier sup ON pb.supplier_id = sup.id
            WHERE pd.produk_id = " . intval($produk_id) . "
            AND pb.status = 'selesai'
            GROUP BY pb.supplier_id, sup.nama_supplier
            ORDER BY harga_termurah ASC
            LIMIT 1
        ";
        
        return db_get_row($query);
    }
    
    /**
     * Get highest margin day in a month
     * @param int $bulan Month (1-12)
     * @param int $tahun Year
     * @return array|null Day with highest margin
     */
    public static function getHighestMarginDay($bulan, $tahun) {
        $query = "
            SELECT 
                tanggal,
                SUM(total_margin) as total_margin_hari,
                COUNT(DISTINCT produk_id) as jumlah_produk,
                SUM(jumlah_transaksi) as total_transaksi
            FROM margin_summary
            WHERE YEAR(tanggal) = " . intval($tahun) . "
            AND MONTH(tanggal) = " . intval($bulan) . "
            GROUP BY tanggal
            ORDER BY total_margin_hari DESC
            LIMIT 1
        ";
        
        return db_get_row($query);
    }
    
    /**
     * Get daily margin summary
     * @param string $tanggal_start Start date
     * @param string $tanggal_end End date
     * @return array List of daily margins
     */
    public static function getDailyMarginSummary($tanggal_start, $tanggal_end) {
        $query = "
            SELECT * FROM v_margin_harian
            WHERE tanggal BETWEEN '" . db_escape($tanggal_start) . "' 
            AND '" . db_escape($tanggal_end) . "'
            ORDER BY tanggal DESC, total_margin DESC
        ";
        
        return db_get_all($query);
    }
    
    /**
     * Get top products by margin
     * @param int $bulan Month (1-12)
     * @param int $tahun Year
     * @param int $limit Limit results
     * @return array Top products
     */
    public static function getTopProductsByMargin($bulan, $tahun, $limit = 10) {
        $query = "
            SELECT 
                produk_id,
                SUM(total_margin) as total_margin,
                SUM(total_qty) as total_qty,
                AVG(avg_margin_per_unit) as avg_margin_per_unit
            FROM margin_summary
            WHERE YEAR(tanggal) = " . intval($tahun) . "
            AND MONTH(tanggal) = " . intval($bulan) . "
            GROUP BY produk_id
            ORDER BY total_margin DESC
            LIMIT " . intval($limit);
        
        $results = db_get_all($query);
        
        // Enrich with product details
        foreach ($results as &$row) {
            $produk = db_get_row("
                SELECT p.kode_produk, p.nama_produk, s.nama_satuan
                FROM produk p
                INNER JOIN satuan s ON p.satuan_id = s.id
                WHERE p.id = " . $row['produk_id']
            );
            
            $row['kode_produk'] = $produk['kode_produk'] ?? '';
            $row['nama_produk'] = $produk['nama_produk'] ?? '';
            $row['nama_satuan'] = $produk['nama_satuan'] ?? '';
        }
        
        return $results;
    }
}
