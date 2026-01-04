<?php
/**
 * Konfigurasi untuk Market Scraper
 */

return [
    // URL target
    'base_url' => 'http://kepokmas.cirebonkab.go.id/statistik-wilayah',
    
    // User agent untuk request
    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    
    // Delay antar request (dalam mikrodetik)
    'request_delay' => 800000,
    
    // Timeout cURL (dalam detik)
    'timeout' => 30,
    
    // Range ID pasar yang akan discrape
    'market_id_range' => [3, 21],
    
    // Path untuk menyimpan hasil
    'storage_path' => __DIR__ . '/data/',
    
    // Nama bulan dalam Bahasa Indonesia
    'month_names' => [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ],
    
    // Database configuration (jika menggunakan database)
    'database' => [
        'host' => 'localhost',
        'username' => 'root',
        'password' => '',
        'database' => 'market_data',
        'table' => 'prices'
    ]
];