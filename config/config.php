<?php

$DB_HOST = 'localhost';
$DB_NAME = 'caroli93_residencial_app';
$DB_USER = 'caroli93_root';
$DB_PASS = 'residencial_app123456';

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
