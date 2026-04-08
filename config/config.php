<?php

$DB_HOST = 'localhost';
$DB_NAME = 'residencial_app2'; 
$DB_USER = 'root';     
$DB_PASS = '';       

#$DB_HOST = 'localhost';
#$DB_NAME = 'miinvit3_residencial_app'; 
#$DB_USER = 'miinvit3_adminmafious';     
#$DB_PASS = 'Madafaka985*mg';       
try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("Error de conexi車n a la base de datos: " . $e->getMessage());
}

session_start();
