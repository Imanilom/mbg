<?php
/**
 * File untuk dijalankan via CRON job
 * Contoh: php /path/to/run_scraper_cron.php -m 12 -y 2025
 */

require_once 'market_price_scraper.php';
require_once 'db_config.php';

// Set unlimited execution time for cron job
set_time_limit(0);

// Parse command line arguments
$options = getopt("m:y:p:");

$scraper = new MarketPriceScraper();

$month = $options['m'] ?? date('m');
$year = $options['y'] ?? date('Y');
$pasar = $options['p'] ?? null;

if ($pasar) {
    echo "Running scraper for Pasar ID: {$pasar} for {$month}-{$year}\n";
    
    // Run for specific market
    $_GET['pasar'] = $pasar;
    $_GET['month'] = $month;
    $_GET['year'] = $year;
    
    $scraper->handleRequest();
} else {
    echo "Running scheduled scraper for all markets for {$month}-{$year}\n";
    
    // Run for all markets
    $result = $scraper->runScheduledJob($month, $year);
    
    echo "Scraping completed!\n";
    echo "Total markets: {$result['total_markets']}\n";
    echo "Total commodities inserted: {$result['total_commodities']}\n";
    echo "Period: {$result['month']}-{$result['year']}\n";
    echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
}