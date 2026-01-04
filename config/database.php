<?php
/**
 * Database Configuration
 * Koneksi ke database MySQL menggunakan MySQLi
 */

// Database credentials - only define if not already defined
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', '');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'marketlist_mbg');
}

// Create connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Set charset to utf8mb4
if (!mysqli_set_charset($conn, "utf8mb4")) {
    die("Error setting charset utf8mb4: " . mysqli_error($conn));
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');

/**
 * Function untuk execute query dengan prepared statement
 */
function db_prepare($query, $params = []) {
    global $conn;
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        error_log("Database Prepare Error: " . mysqli_error($conn));
        return false;
    }
    
    if (!empty($params)) {
        $types = '';
        $values = [];
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_double($param)) {
                $types .= 'd';
            } elseif (is_string($param)) {
                $types .= 's';
            } else {
                $types .= 'b';
            }
            $values[] = $param;
        }
        mysqli_stmt_bind_param($stmt, $types, ...$values);
    }
    
    if (!mysqli_stmt_execute($stmt)) {
        error_log("Database Execute Error: " . mysqli_stmt_error($stmt));
        return false;
    }
    
    return $stmt;
}

/**
 * Function untuk execute query (mendukung prepared statement jika ada params)
 */
function db_query($query, $params = []) {
    global $conn;
    
    if (empty($params)) {
        $result = mysqli_query($conn, $query);
        if (!$result) {
            error_log("Database Error: " . mysqli_error($conn));
            return false;
        }
        return $result;
    }
    
    $stmt = db_prepare($query, $params);
    if ($stmt) {
        $result = mysqli_stmt_get_result($stmt);
        if ($result === false) { // For INSERT/UPDATE/DELETE
            return true;
        }
        return $result;
    }
    return false;
}

/**
 * Function untuk escape string
 */
function db_escape($string) {
    global $conn;
    return mysqli_real_escape_string($conn, $string);
}

/**
 * Function untuk get single row
 */
function db_get_row($query, $params = []) {
    $result = db_query($query, $params);
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    return null;
}

/**
 * Function untuk get all rows
 */
function db_get_all($query, $params = []) {
    $result = db_query($query, $params);
    $data = [];
    
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
    }
    
    return $data;
}

/**
 * Function untuk insert data menggunakan prepared statement
 */
function db_insert($table, $data) {
    global $conn;
    
    $fields = array_keys($data);
    $placeholders = array_fill(0, count($fields), '?');
    $values = array_values($data);
    
    $sql = "INSERT INTO `{$table}` (`" . implode('`, `', $fields) . "`) 
            VALUES (" . implode(', ', $placeholders) . ")";
    
    $stmt = db_prepare($sql, $values);
    if ($stmt) {
        return mysqli_insert_id($conn);
    }
    
    return false;
}

/**
 * Function untuk update data menggunakan prepared statement
 */
function db_update($table, $data, $where, $where_params = []) {
    global $conn;
    
    $set = [];
    $values = [];
    foreach ($data as $field => $value) {
        $set[] = "`{$field}` = ?";
        $values[] = $value;
    }
    
    $all_params = array_merge($values, $where_params);
    
    $sql = "UPDATE `{$table}` SET " . implode(', ', $set) . " WHERE {$where}";
    
    return db_query($sql, $all_params);
}

/**
 * Function untuk delete data menggunakan prepared statement
 */
function db_delete($table, $where, $params = []) {
    $sql = "DELETE FROM `{$table}` WHERE {$where}";
    return db_query($sql, $params);
}
