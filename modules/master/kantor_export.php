<?php
require_once '../../config/database.php';
require_once '../../helpers/session.php';
require_once '../../helpers/functions.php';

require_login();
require_role(['admin']);

// Query data
$query = "SELECT * FROM kantor ORDER BY kode_kantor ASC";
$kantors = db_get_all($query);

// Set headers untuk download Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="Data_Kantor_' . date('YmdHis') . '.xls"');
header('Cache-Control: max-age=0');

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Export Data Kantor</title>
</head>
<body>
    <table border="1">
        <thead>
            <tr>
                <th>No</th>
                <th>Kode Kantor</th>
                <th>Nama Kantor</th>
                <th>Alamat</th>
                <th>No Telepon</th>
                <th>PIC Name</th>
                <th>PIC Phone</th>
                <th>Status</th>
                <th>Tanggal Dibuat</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            foreach ($kantors as $k): 
            ?>
            <tr>
                <td><?= $no++ ?></td>
                <td><?= $k['kode_kantor'] ?></td>
                <td><?= $k['nama_kantor'] ?></td>
                <td><?= $k['alamat'] ?></td>
                <td><?= $k['no_telp'] ?></td>
                <td><?= $k['pic_name'] ?></td>
                <td><?= $k['pic_phone'] ?></td>
                <td><?= strtoupper($k['status']) ?></td>
                <td><?= $k['created_at'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>