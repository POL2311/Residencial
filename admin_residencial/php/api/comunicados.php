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
require_once __DIR__ . '/../../../config/service_profile.php';

require_login();
require_role(['admin_residencial']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    app_require_write_guard();
}

$user = current_user();
$adminId = (int)($user['id'] ?? 0);
$residencialId = require_residencial_id($pdo, $adminId);
service_profile_api_require_module($pdo, $residencialId, 'admin_residencial', 'comunicados', 'Los comunicados no están habilitados para este cliente.');

function normalize_comunicado(array $r): array {
    return [
        'id' => (int)($r['id'] ?? 0),
        'titulo' => (string)($r['titulo'] ?? ''),
        'mensaje' => (string)($r['mensaje'] ?? ''),
        'tipo' => (string)($r['tipo'] ?? 'general'),
        'prioridad' => (string)($r['prioridad'] ?? 'media'),
        'fecha_publicacion' => (string)($r['fecha_publicacion'] ?? ''),
        'fecha_expiracion' => (string)($r['fecha_expiracion'] ?? ''),
        'estado' => (string)($r['estado'] ?? 'publicado'),
    ];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $stmt = $pdo->prepare("
            SELECT
                id,
                titulo,
                mensaje,
                tipo,
                prioridad,
                fecha_publicacion,
                fecha_expiracion,
                estado
            FROM comunicados_residenciales
            WHERE residencial_id = :rid
              AND estado != 'archivado'
            ORDER BY fecha_publicacion DESC, id DESC
        ");
        $stmt->execute(['rid' => $residencialId]);

        $comunicados = array_map(
            'normalize_comunicado',
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        );

        json_out(true, ['comunicados' => $comunicados]);
    }

    if ($method === 'POST') {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'archive') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                json_out(false, ['error' => 'ID inválido.']);
            }

            $stmt = $pdo->prepare("
                UPDATE comunicados_residenciales
                SET estado = 'archivado'
                WHERE id = :id
                  AND residencial_id = :rid
            ");
            $stmt->execute([
                'id' => $id,
                'rid' => $residencialId,
            ]);

            if ($stmt->rowCount() === 0) {
                json_out(false, ['error' => 'No se pudo archivar el comunicado.']);
            }

            json_out(true, ['message' => 'Comunicado archivado.']);
        }

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $titulo = clean_str($_POST['titulo'] ?? '');
        $mensaje = clean_str($_POST['mensaje'] ?? '');
        $tipo = clean_str($_POST['tipo'] ?? 'general');
        $prioridad = clean_str($_POST['prioridad'] ?? 'media');
        $fechaPub = clean_str($_POST['fecha_publicacion'] ?? '') ?: date('Y-m-d');
        $fechaExp = clean_str($_POST['fecha_expiracion'] ?? '');
        $estado = clean_str($_POST['estado'] ?? 'publicado');

        if ($titulo === '' || $mensaje === '') {
            json_out(false, ['error' => 'Título y mensaje son requeridos.']);
        }

        if (!in_array($tipo, ['general', 'mantenimiento', 'seguridad', 'pagos'], true)) {
            json_out(false, ['error' => 'Tipo inválido.']);
        }

        if (!in_array($prioridad, ['baja', 'media', 'alta'], true)) {
            json_out(false, ['error' => 'Prioridad inválida.']);
        }

        if (!in_array($estado, ['publicado', 'borrador'], true)) {
            json_out(false, ['error' => 'Estado inválido.']);
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaPub)) {
            json_out(false, ['error' => 'Fecha de publicación inválida.']);
        }

        if ($fechaExp !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaExp)) {
            json_out(false, ['error' => 'Fecha de expiración inválida.']);
        }

        if ($fechaExp !== '' && $fechaExp < $fechaPub) {
            json_out(false, ['error' => 'La fecha de expiración no puede ser menor a la de publicación.']);
        }

        if ($id > 0) {
            $stmtUp = $pdo->prepare("
                UPDATE comunicados_residenciales
                SET titulo = :titulo,
                    mensaje = :mensaje,
                    tipo = :tipo,
                    prioridad = :prioridad,
                    fecha_publicacion = :fecha_pub,
                    fecha_expiracion = :fecha_exp,
                    estado = :estado,
                    actualizado_por = :userId
                WHERE id = :id
                  AND residencial_id = :rid
            ");
            $stmtUp->execute([
                'titulo' => $titulo,
                'mensaje' => $mensaje,
                'tipo' => $tipo,
                'prioridad' => $prioridad,
                'fecha_pub' => $fechaPub,
                'fecha_exp' => ($fechaExp !== '' ? $fechaExp : null),
                'estado' => $estado,
                'userId' => $adminId,
                'id' => $id,
                'rid' => $residencialId,
            ]);

            json_out(true, ['message' => 'Comunicado actualizado.']);
        }

        $stmtIns = $pdo->prepare("
            INSERT INTO comunicados_residenciales (
                residencial_id,
                titulo,
                mensaje,
                tipo,
                prioridad,
                fecha_publicacion,
                fecha_expiracion,
                visible_para_residentes,
                estado,
                creado_por,
                creado_at
            ) VALUES (
                :resid,
                :titulo,
                :mensaje,
                :tipo,
                :prioridad,
                :fecha_pub,
                :fecha_exp,
                1,
                :estado,
                :userId,
                NOW()
            )
        ");
        $stmtIns->execute([
            'resid' => $residencialId,
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'tipo' => $tipo,
            'prioridad' => $prioridad,
            'fecha_pub' => $fechaPub,
            'fecha_exp' => ($fechaExp !== '' ? $fechaExp : null),
            'estado' => $estado,
            'userId' => $adminId,
        ]);

        json_out(true, ['message' => 'Comunicado creado.']);
    }

    json_out(false, ['error' => 'Método no soportado.']);
} catch (Throwable $e) {
    app_json_exception($e, 'No pudimos procesar los comunicados.');
}
