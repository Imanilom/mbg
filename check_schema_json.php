<?php
require_once 'config/database.php';
header('Content-Type: application/json');

$columns = [];
$res = $conn->query("SHOW COLUMNS FROM produk");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
}
echo json_encode($columns);
?>
