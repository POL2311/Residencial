<?php
require_once __DIR__ . '/app_security.php';

$DB_HOST = 'localhost';
$DB_NAME = 'residencial_app4';
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
    app_log_exception($e, 'db-connect');
    app_abort(
        500,
        'No pudimos iniciar el sistema',
        'La conexión con la base de datos no está disponible en este momento. Inténtalo más tarde.'
    );
}

app_ensure_session();
