<?php
require_once '../../helpers/ajax.php';
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';
require_once '../../helpers/MarginHelper.php';

ajax_check_unexpected_output();

require_login();
require_role(['admin']);

$tanggal_start = $_GET['tanggal_start'] ?? date('Y-m-d', strtotime('-30 days'));
$tanggal_end = $_GET['tanggal_end'] ?? date('Y-m-d');

// Get data
$data = MarginHelper::getDailyMarginSummary($tanggal_start, $tanggal_end);

// Calculate summary
$total_margin = 0;
$total_produk = [];
$total_transaksi = 0;

foreach ($data as $row) {
    $total_margin += floatval($row['total_margin']);
    $total_produk[$row['produk_id']] = true;
    $total_transaksi += intval($row['jumlah_transaksi']);
}

$jumlah_hari = (strtotime($tanggal_end) - strtotime($tanggal_start)) / 86400 + 1;
$avg_margin = $jumlah_hari > 0 ? $total_margin / $jumlah_hari : 0;

$summary = [
    'total_margin' => $total_margin,
    'avg_margin' => $avg_margin,
    'total_produk' => count($total_produk),
    'total_transaksi' => $total_transaksi
];

echo json_encode([
    'success' => true,
    'summary' => $summary,
    'data' => $data
]);
