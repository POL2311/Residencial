<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/api_helpers.php';
require_once __DIR__ . '/../../../config/residencial_helpers.php';

require_login();

$user = current_user();
$userId = (int)($user['id'] ?? 0);

try {
    $stmt = $pdo->prepare("
        SELECT residencial_id
        FROM usuarios_residenciales
        WHERE user_id = ?
        ORDER BY es_principal DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $residencialId = (int)$stmt->fetchColumn();

    if (!$residencialId) {
        json_out(false, ['error' => 'No tienes un residencial asignado.']);
    }

    $stmtBanners = $pdo->prepare("
        SELECT
            id,
            titulo,
            subtitulo,
            imagen_url,
            categoria,
            link_url,
            orden
        FROM home_banners_residenciales
        WHERE residencial_id = :rid
          AND activo = 1
        ORDER BY orden ASC, id DESC
    ");
    $stmtBanners->execute(['rid' => $residencialId]);
    $banners = $stmtBanners->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmtServicios = $pdo->prepare("
        SELECT
            id,
            nombre,
            descripcion,
            imagen_url,
            telefono,
            whatsapp,
            link_url,
            categoria,
            orden
        FROM home_servicios_residenciales
        WHERE residencial_id = :rid
          AND activo = 1
        ORDER BY orden ASC, id DESC
    ");
    $stmtServicios->execute(['rid' => $residencialId]);
    $servicios = $stmtServicios->fetchAll(PDO::FETCH_ASSOC) ?: [];

    json_out(true, [
        'banners' => $banners,
        'servicios' => $servicios,
    ]);
} catch (Throwable $e) {
    json_out(false, ['error' => 'Error interno: ' . $e->getMessage()]);
}