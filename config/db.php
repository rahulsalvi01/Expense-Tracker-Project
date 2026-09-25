<?php
// config/db.php

$host = 'sql104.infinityfree.com';
$dbname = 'if0_43011222_fintrack';
$username = 'if0_43011222'; 
$password = 'aSfiTAfVSd';     

try {
    // Create a new PDO instance
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Set PDO error mode to exception for easier debugging
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set default fetch mode to associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    // In a production environment, you would log this error instead of displaying it.
    die("Database Connection Failed: " . $e->getMessage());
}
?>