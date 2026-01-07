<?php
require_once '../../config/database.php';

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Starting Migration: Add supplier_id to produk table</h2>";

// 1. Add column supplier_id if not exists
$check_col = $conn->query("SHOW COLUMNS FROM produk LIKE 'supplier_id'");
if ($check_col->num_rows == 0) {
    $sql = "ALTER TABLE produk ADD COLUMN supplier_id INT(11) DEFAULT NULL AFTER kategori_id";
    if ($conn->query($sql) === TRUE) {
        echo "<div style='color:green'>[SUCCESS] Added column supplier_id to produk table.</div><br>";
    } else {
        echo "<div style='color:red'>[ERROR] Error adding column: " . $conn->error . "</div><br>";
    }
} else {
    echo "<div style='color:orange'>[INFO] Column supplier_id already exists.</div><br>";
}

// 2. Add Foreign Key constraint
// Check if constraint exists
$check_fk = $conn->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'produk' AND CONSTRAINT_NAME = 'fk_produk_supplier'");
if ($check_fk->num_rows == 0) {
    $sql = "ALTER TABLE produk ADD CONSTRAINT fk_produk_supplier FOREIGN KEY (supplier_id) REFERENCES supplier(id) ON DELETE SET NULL";
    if ($conn->query($sql) === TRUE) {
        echo "<div style='color:green'>[SUCCESS] Added foreign key constraint fk_produk_supplier.</div><br>";
    } else {
        echo "<div style='color:red'>[ERROR] Error adding constraint: " . $conn->error . "</div><br>";
    }
} else {
    echo "<div style='color:orange'>[INFO] Constraint fk_produk_supplier already exists.</div><br>";
}

echo "<h3>Migration Completed!</h3>";
echo "<a href='http://localhost/mbg/modules/menu/shopping_list.php'>Go back to Shopping List</a>";
?>
