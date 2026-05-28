<?php
// superadmin/php/api_solicitudes.php

header('Content-Type: application/json');

// Ensure error reporting is clean for JSON responses
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    // Include the main config to get DB connection logic if available,
    // or set it up directly here based on standard project structure.
    $dbHost = getenv('DB_HOST') ?: 'localhost';
    $dbName = getenv('DB_NAME') ?: 'miinvit3_residencial_app';
    $dbUser = getenv('DB_USER') ?: 'root';
    $dbPass = getenv('DB_PASS') ?: '';

    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'POST') {
        // Insert new request
        $nombre_residencial = $_POST['nombre_residencial'] ?? '';
        $nombre_solicitante = $_POST['nombre_solicitante'] ?? '';
        $fecha_instalacion = $_POST['fecha_instalacion'] ?? '';
        $total_casas = isset($_POST['total_casas']) && $_POST['total_casas'] !== '' ? (int)$_POST['total_casas'] : null;
        $casas_habitadas = isset($_POST['casas_habitadas']) && $_POST['casas_habitadas'] !== '' ? (int)$_POST['casas_habitadas'] : null;
        $promedio_autos = $_POST['promedio_autos'] ?? null;
        $tipo_acceso = $_POST['tipo_acceso'] ?? null;
        $ancho_acceso = isset($_POST['ancho_acceso']) && $_POST['ancho_acceso'] !== '' ? (float)$_POST['ancho_acceso'] : null;
        $alto_acceso = isset($_POST['alto_acceso']) && $_POST['alto_acceso'] !== '' ? (float)$_POST['alto_acceso'] : null;
        $tipo_tag = $_POST['tipo_tag'] ?? null;
        $numero_tags = isset($_POST['numero_tags']) && $_POST['numero_tags'] !== '' ? (int)$_POST['numero_tags'] : null;
        $modulos_seleccionados = $_POST['modulos_seleccionados'] ?? null;

        if (empty($nombre_residencial) || empty($nombre_solicitante) || empty($fecha_instalacion)) {
            throw new Exception('Faltan campos obligatorios.');
        }

        $stmt = $conn->prepare("INSERT INTO solicitudes_cotizacion (nombre_residencial, nombre_solicitante, fecha_instalacion, total_casas, casas_habitadas, promedio_autos, tipo_acceso, ancho_acceso, alto_acceso, tipo_tag, numero_tags, modulos_seleccionados) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if (!$stmt) {
             throw new Exception('Prepare failed: ' . $conn->error);
        }

        $stmt->bind_param("sssiissddsis",
            $nombre_residencial,
            $nombre_solicitante,
            $fecha_instalacion,
            $total_casas,
            $casas_habitadas,
            $promedio_autos,
            $tipo_acceso,
            $ancho_acceso,
            $alto_acceso,
            $tipo_tag,
            $numero_tags,
            $modulos_seleccionados
        );

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
        } else {
            throw new Exception('Execute failed: ' . $stmt->error);
        }

        $stmt->close();
    } elseif ($method === 'GET') {
        // Fetch requests
        $query = "SELECT * FROM solicitudes_cotizacion ORDER BY fecha_creacion DESC";
        $result = $conn->query($query);

        if (!$result) {
            throw new Exception('Query failed: ' . $conn->error);
        }

        $solicitudes = [];
        while ($row = $result->fetch_assoc()) {
            // Decode json if needed or keep as string depending on frontend needs
            $row['modulos_seleccionados'] = json_decode($row['modulos_seleccionados'], true);
            $solicitudes[] = $row;
        }

        echo json_encode(['success' => true, 'data' => $solicitudes]);
    } else {
        throw new Exception('Invalid request method.');
    }

    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
