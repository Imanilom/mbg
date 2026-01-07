<?php
require_once '../../config/database.php';

echo "<h2>Starting Migration: Add Custom Item Support to Menu Master Detail</h2>";

try {
    // 1. Add item_type column
    $result = $conn->query("SHOW COLUMNS FROM `menu_master_detail` LIKE 'item_type'");
    if ($result->num_rows == 0) {
        $sql = "ALTER TABLE `menu_master_detail` ADD COLUMN `item_type` ENUM('product', 'recipe', 'manual') DEFAULT 'product' AFTER `menu_master_id`";
        if ($conn->query($sql) === TRUE) {
            echo "<p>✅ Added column `item_type`.</p>";
            
            // Migrate existing data
            $conn->query("UPDATE menu_master_detail SET item_type = 'recipe' WHERE resep_id IS NOT NULL");
            $conn->query("UPDATE menu_master_detail SET item_type = 'product' WHERE produk_id IS NOT NULL");
            echo "<p>✅ Migrated existing data to use item_type.</p>";
        } else {
            throw new Exception("Error adding item_type: " . $conn->error);
        }
    } else {
        echo "<p>ℹ️ Column `item_type` already exists.</p>";
    }

    // 2. Add custom_name column
    $result = $conn->query("SHOW COLUMNS FROM `menu_master_detail` LIKE 'custom_name'");
    if ($result->num_rows == 0) {
        $sql = "ALTER TABLE `menu_master_detail` ADD COLUMN `custom_name` VARCHAR(255) DEFAULT NULL AFTER `resep_id`";
        if ($conn->query($sql) === TRUE) {
            echo "<p>✅ Added column `custom_name`.</p>";
        } else {
            throw new Exception("Error adding custom_name: " . $conn->error);
        }
    } else {
        echo "<p>ℹ️ Column `custom_name` already exists.</p>";
    }

    echo "<h3>Migration completed successfully!</h3>";
    echo "<p><a href='catalog.php'>Go back to Catalog</a></p>";

} catch (Exception $e) {
    echo "<h3>❌ Error: " . $e->getMessage() . "</h3>";
}
?>
