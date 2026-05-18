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
require_once __DIR__ . '/../../../config/comunicados_helpers.php';
require_once __DIR__ . '/_operational_bootstrap.php';

require_login();
admin_module_required('home', 'El inicio no está habilitado para este cliente.');

$user = current_user();
$userId = (int)($user['id'] ?? 0);

try {
    comunicados_schema_ensure($pdo);

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

    // 1) Preferimos comunicados publicados reales para alimentar el carrusel.
    $banners = [];
    if (tableExists($pdo, 'comunicados_residenciales')) {
        $stmtCom = $pdo->prepare("
            SELECT
                id,
                titulo,
                mensaje,
                imagen_url,
                tipo,
                prioridad,
                fecha_publicacion,
                fecha_expiracion
            FROM comunicados_residenciales
            WHERE residencial_id = :rid
              AND estado = 'publicado'
              AND fecha_publicacion <= CURDATE()
              AND (fecha_expiracion IS NULL OR fecha_expiracion >= CURDATE())
            ORDER BY
              CASE prioridad
                WHEN 'alta' THEN 3
                WHEN 'media' THEN 2
                ELSE 1
              END DESC,
              fecha_publicacion DESC,
              id DESC
            LIMIT 5
        ");
        $stmtCom->execute(['rid' => $residencialId]);
        $comRows = $stmtCom->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($comRows as $row) {
            $raw = (string)($row['mensaje'] ?? '');
            $raw = preg_replace('/\\s+/u', ' ', trim($raw)) ?: '';
            $sub = function_exists('mb_substr') ? mb_substr($raw, 0, 110) : substr($raw, 0, 110);
            if (strlen($raw) > 110) {
                $sub .= '…';
            }
            $banners[] = [
                'id' => (int)($row['id'] ?? 0),
                'titulo' => (string)($row['titulo'] ?? ''),
                'subtitulo' => $sub,
                'imagen_url' => (string)($row['imagen_url'] ?? ''),
                'categoria' => (string)($row['tipo'] ?? 'general'),
                'link_url' => '#comunicados',
                'orden' => 0,
            ];
        }
    }

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
    $serviciosResidenciales = $stmtServicios->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $serviciosGlobales = [];
    if (tableExists($pdo, 'home_servicios_globales')) {
        $stmtGlobales = $pdo->query("
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
            FROM home_servicios_globales
            WHERE activo = 1
            ORDER BY orden ASC, id DESC
        ");
        $serviciosGlobales = $stmtGlobales->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    $servicios = [];
    foreach ($serviciosGlobales as $item) {
        $item['origen'] = 'global';
        $servicios[] = $item;
    }
    foreach ($serviciosResidenciales as $item) {
        $item['origen'] = 'residencial';
        $servicios[] = $item;
    }

    json_out(true, [
        'banners' => $banners,
        'servicios' => $servicios,
    ]);
} catch (Throwable $e) {
    app_json_exception($e, 'No pudimos cargar el inicio del residencial.');
}
