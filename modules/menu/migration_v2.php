<?php
require_once '../../config/database.php';

echo "<h2>Starting Menu System Refactoring Migration...</h2>";

try {
    // 1. Create menu_master table
    $sql = "CREATE TABLE IF NOT EXISTS `menu_master` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `nama_menu` varchar(200) NOT NULL,
      `deskripsi` text DEFAULT NULL,
      `created_by` int(11) NOT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    if ($conn->query($sql) === TRUE) {
        echo "<p>✅ Table `menu_master` created/checked successfully.</p>";
    } else {
        throw new Exception("Error creating table menu_master: " . $conn->error);
    }

    // 2. Create menu_master_detail table
    $sql = "CREATE TABLE IF NOT EXISTS `menu_master_detail` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `menu_master_id` int(11) NOT NULL,
      `produk_id` int(11) DEFAULT NULL,
      `resep_id` int(11) DEFAULT NULL,
      `qty_needed` decimal(10,4) NOT NULL COMMENT 'Base quantity (usually per 1 portion)',
      `keterangan` text DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `menu_master_id` (`menu_master_id`),
      KEY `produk_id` (`produk_id`),
      KEY `resep_id` (`resep_id`),
      CONSTRAINT `menu_master_detail_ibfk_1` FOREIGN KEY (`menu_master_id`) REFERENCES `menu_master` (`id`) ON DELETE CASCADE,
      CONSTRAINT `menu_master_detail_ibfk_2` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`),
      CONSTRAINT `menu_master_detail_ibfk_3` FOREIGN KEY (`resep_id`) REFERENCES `resep` (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    if ($conn->query($sql) === TRUE) {
        echo "<p>✅ Table `menu_master_detail` created/checked successfully.</p>";
    } else {
        throw new Exception("Error creating table menu_master_detail: " . $conn->error);
    }

    // 3. Add menu_master_id column to menu_harian if not exists
    $result = $conn->query("SHOW COLUMNS FROM `menu_harian` LIKE 'menu_master_id'");
    if ($result->num_rows == 0) {
        $sql = "ALTER TABLE `menu_harian` ADD COLUMN `menu_master_id` int(11) DEFAULT NULL AFTER `no_menu`";
        if ($conn->query($sql) === TRUE) {
            echo "<p>✅ Column `menu_master_id` added to `menu_harian`.</p>";
            // Add foreign key
             $conn->query("ALTER TABLE `menu_harian` ADD CONSTRAINT `menu_harian_ibfk_master` FOREIGN KEY (`menu_master_id`) REFERENCES `menu_master` (`id`) ON DELETE SET NULL");
        } else {
            throw new Exception("Error adding column to menu_harian: " . $conn->error);
        }
    } else {
        echo "<p>ℹ️ Column `menu_master_id` already exists in `menu_harian`.</p>";
    }

    echo "<h3>Migration completed successfully!</h3>";
    echo "<p><a href='index.php'>Go back to Menu</a></p>";

} catch (Exception $e) {
    echo "<h3>❌ Error: " . $e->getMessage() . "</h3>";
}
?>
