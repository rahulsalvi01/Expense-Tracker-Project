<?php
// config/db.php

$server_name = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? '';
$is_local = (in_array($server_name, ['localhost', '127.0.0.1']) || php_sapi_name() === 'cli' || strpos($server_name, 'localhost') !== false);

if ($is_local) {
    $host = '127.0.0.1';
    $dbname = 'expense_tracker';
    $username = 'root'; 
    $password = '';     
} else {
    $host = 'sql104.infinityfree.com';
    $dbname = 'if0_43011222_fintrack';
    $username = 'if0_43011222'; 
    $password = 'aSfiTAfVSd';     
}

try {
    // Create a new PDO instance
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Set PDO error mode to exception for easier debugging
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set default fetch mode to associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    // Fallback to local if remote failed (e.g. testing local server)
    if (!$is_local) {
        try {
            $pdo = new PDO("mysql:host=127.0.0.1;dbname=expense_tracker;charset=utf8mb4", "root", "");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $fallbackErr) {
            die("Database Connection Failed: " . $e->getMessage());
        }
    } else {
        die("Database Connection Failed: " . $e->getMessage());
    }
}
?>