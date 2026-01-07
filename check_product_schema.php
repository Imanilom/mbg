<?php
require_once 'config/database.php';

function check_table($table) {
    global $conn;
    $res = $conn->query("SHOW COLUMNS FROM $table");
    if ($res) {
        echo "Table '$table' exists. Columns:\n";
        while ($row = $res->fetch_assoc()) {
            echo "- " . $row['Field'] . "\n";
        }
    } else {
        echo "Table '$table' does NOT exist.\n";
    }
    echo "\n";
}

check_table('produk');
check_table('jenis_barang');
?>
