<?php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';
require_once '../../vendor/autoload.php';

require_login();
require_role(['admin', 'gudang']);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set document properties
$spreadsheet->getProperties()
    ->setCreator("MBG System")
    ->setTitle("Data Harga Produk")
    ->setSubject("Export Harga Produk")
    ->setDescription("Data harga produk dari sistem MBG");

// Header styling
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
];

// Set headers
$headers = ['No', 'Kode Produk', 'Nama Produk', 'Jenis', 'Kategori', 'Satuan', 
            'Harga Beli', 'Harga Jual 1', 'Harga Jual 2', 'Harga Jual 3 (Scraping)', 'Pasar'];
$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '1', $header);
    $sheet->getStyle($col . '1')->applyFromArray($headerStyle);
    $col++;
}

// Get data
$query = "SELECT p.*, jb.nama_jenis, k.nama_kategori, s.nama_satuan 
          FROM produk p 
          INNER JOIN jenis_barang jb ON p.jenis_barang_id = jb.id 
          INNER JOIN kategori k ON p.kategori_id = k.id 
          INNER JOIN satuan s ON p.satuan_id = s.id 
          ORDER BY p.kode_produk ASC";

$produks = db_get_all($query);

$row = 2;
$no = 1;

foreach ($produks as $p) {
    // Get scraped price
    $scraped = db_get_row("
        SELECT hp.harga_terendah, hp.nama_pasar 
        FROM harga_pasar hp 
        WHERE hp.produk_id = {$p['id']} 
        ORDER BY hp.tahun DESC, hp.bulan DESC 
        LIMIT 1
    ");
    
    $sheet->setCellValue('A' . $row, $no++);
    $sheet->setCellValue('B' . $row, $p['kode_produk']);
    $sheet->setCellValue('C' . $row, $p['nama_produk']);
    $sheet->setCellValue('D' . $row, $p['nama_jenis']);
    $sheet->setCellValue('E' . $row, $p['nama_kategori']);
    $sheet->setCellValue('F' . $row, $p['nama_satuan']);
    $sheet->setCellValue('G' . $row, $p['harga_beli']);
    $sheet->setCellValue('H' . $row, $p['harga_jual_1']);
    $sheet->setCellValue('I' . $row, $p['harga_jual_2']);
    $sheet->setCellValue('J' . $row, $scraped ? $scraped['harga_terendah'] : 0);
    $sheet->setCellValue('K' . $row, $scraped ? $scraped['nama_pasar'] : '-');
    
    // Format currency columns
    $sheet->getStyle('G' . $row . ':J' . $row)->getNumberFormat()
        ->setFormatCode('#,##0');
    
    $row++;
}

// Auto-size columns
foreach (range('A', 'K') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Add borders to all data
$sheet->getStyle('A1:K' . ($row - 1))->applyFromArray([
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
]);

// Output file
$filename = 'Harga_Produk_' . date('Y-m-d_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
