<?php
require_once '../../config/database.php';

echo "Running Migration V4 - Manual Item Support...\n";

try {
    // Add item_type column
    $sql = "ALTER TABLE menu_master_detail 
            ADD COLUMN item_type ENUM('product', 'recipe', 'manual') DEFAULT 'product' AFTER menu_master_id,
            ADD COLUMN manual_nama VARCHAR(255) NULL AFTER keterangan";
    
    // Check if column exists first to avoid error
    $check = $conn->query("SHOW COLUMNS FROM menu_master_detail LIKE 'item_type'");
    if ($check->num_rows == 0) {
        if ($conn->query($sql)) {
            echo "SUCCESS: Added item_type and manual_nama columns.\n";
        } else {
            throw new Exception("Error adding columns: " . $conn->error);
        }
    } else {
        echo "SKIPPING: Columns already exist.\n";
    }

    // Since we are adding item_type, we should populate it for existing rows
    // existing rows have produk_id or resep_id. 
    // If resep_id is NOT NULL, it's 'recipe'. Else 'product'.
    
    $update_recipe = "UPDATE menu_master_detail SET item_type = 'recipe' WHERE resep_id IS NOT NULL";
    if ($conn->query($update_recipe)) {
        echo "SUCCESS: Updated existing recipe items.\n";
    }

    $update_product = "UPDATE menu_master_detail SET item_type = 'product' WHERE produk_id IS NOT NULL";
    if ($conn->query($update_product)) {
         echo "SUCCESS: Updated existing product items.\n";
    }

    echo "Migration V4 Completed Successfully.\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
