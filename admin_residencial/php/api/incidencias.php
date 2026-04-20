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
require_role(['admin_residencial']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    app_require_write_guard();
}

$user = current_user();
$adminId = (int)($user['id'] ?? 0);
$residencialId = require_residencial_id($pdo, $adminId);

function normalize_incidencia(array $r): array {
    return [
        'id' => (int)($r['id'] ?? 0),
        'titulo' => (string)($r['titulo'] ?? ''),
        'descripcion' => (string)($r['descripcion'] ?? ''),
        'tipo' => (string)($r['tipo'] ?? ''),
        'prioridad' => (string)($r['prioridad'] ?? 'media'),
        'estado' => (string)($r['estado'] ?? 'abierta'),
        'created_at' => (string)($r['created_at'] ?? ''),
        'updated_at' => (string)($r['updated_at'] ?? ''),
        'residente_nombre' => (string)($r['residente_nombre'] ?? ''),
        'unidad_clave' => (string)($r['unidad_clave'] ?? ''),
        'guardia_nombre' => (string)($r['guardia_nombre'] ?? ''),
        'guardia_id' => isset($r['guardia_id']) ? (int)$r['guardia_id'] : null,
    ];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $estadoFilter = trim((string)($_GET['estado'] ?? ''));

        $sql = "
            SELECT
                i.id,
                i.titulo,
                i.descripcion,
                i.tipo,
                i.prioridad,
                i.estado,
                i.created_at,
                i.updated_at,
                res.name AS residente_nombre,
                un.clave AS unidad_clave,
                gur.name AS guardia_nombre,
                gur.id AS guardia_id
            FROM incidencias i
            JOIN users res ON res.id = i.residente_id
            JOIN unidades un ON un.id = i.unidad_id
            LEFT JOIN users gur ON gur.id = i.guardia_id
            WHERE i.residencial_id = :resid
        ";

        $params = ['resid' => $residencialId];

        if ($estadoFilter !== '' && in_array($estadoFilter, ['abierta', 'en_proceso', 'cerrada'], true)) {
            $sql .= " AND i.estado = :estado";
            $params['estado'] = $estadoFilter;
        }

        $sql .= "
            ORDER BY
                CASE i.estado
                    WHEN 'abierta' THEN 1
                    WHEN 'en_proceso' THEN 2
                    WHEN 'cerrada' THEN 3
                    ELSE 4
                END,
                i.updated_at DESC,
                i.created_at DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $incidencias = array_map('normalize_incidencia', $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

        json_out(true, ['incidencias' => $incidencias]);
    }

    if ($method === 'POST') {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'delete') {
            $incidentId = (int)($_POST['id'] ?? 0);
            if ($incidentId <= 0) {
                json_out(false, ['error' => 'ID inválido.']);
            }

            $stmt = $pdo->prepare("
                DELETE FROM incidencias
                WHERE id = :id
                  AND residencial_id = :resid
            ");
            $stmt->execute([
                'id' => $incidentId,
                'resid' => $residencialId,
            ]);

            if ($stmt->rowCount() === 0) {
                json_out(false, ['error' => 'Incidencia no encontrada o fuera de tu residencial.']);
            }

            json_out(true, ['message' => 'Incidencia eliminada.']);
        }

        // UPDATE
        if (isset($_POST['id']) && $_POST['id'] !== '') {
            $incidentId = (int)$_POST['id'];
            $newState = trim((string)($_POST['estado'] ?? ''));
            $assignGuardId = isset($_POST['guardia_id']) && $_POST['guardia_id'] !== ''
                ? (int)$_POST['guardia_id']
                : null;
            $newPriority = trim((string)($_POST['prioridad'] ?? ''));

            if ($incidentId <= 0) {
                json_out(false, ['error' => 'ID inválido.']);
            }

            $stmtCheck = $pdo->prepare("
                SELECT id, estado, guardia_id, prioridad
                FROM incidencias
                WHERE id = :id
                  AND residencial_id = :resid
                LIMIT 1
            ");
            $stmtCheck->execute([
                'id' => $incidentId,
                'resid' => $residencialId,
            ]);

            $current = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if (!$current) {
                json_out(false, ['error' => 'Incidencia no encontrada.']);
            }

            if ($newState !== '' && !in_array($newState, ['abierta', 'en_proceso', 'cerrada'], true)) {
                json_out(false, ['error' => 'Estado inválido.']);
            }

            if ($newPriority !== '' && !in_array($newPriority, ['baja', 'media', 'alta'], true)) {
                json_out(false, ['error' => 'Prioridad inválida.']);
            }

            if ($assignGuardId !== null) {
                $stmtGuard = $pdo->prepare("
                    SELECT u.id
                    FROM users u
                    JOIN usuarios_residenciales ur ON ur.user_id = u.id
                    JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
                    WHERE u.id = :id
                      AND ur.residencial_id = :resid
                      AND t.nombre = 'guardia'
                    LIMIT 1
                ");
                $stmtGuard->execute([
                    'id' => $assignGuardId,
                    'resid' => $residencialId,
                ]);

                if (!$stmtGuard->fetchColumn()) {
                    json_out(false, ['error' => 'El guardia seleccionado no es válido.']);
                }
            }

            $curEstado = (string)$current['estado'];
            $curGuard = $current['guardia_id'] !== null ? (int)$current['guardia_id'] : null;
            $curPrio = (string)$current['prioridad'];

            $updEstado = $curEstado;
            if ($newState !== '') {
                $updEstado = $newState;
            } elseif ($assignGuardId !== null && $curEstado === 'abierta') {
                $updEstado = 'en_proceso';
            }

            $updGuard = $assignGuardId;
            if (!isset($_POST['guardia_id'])) {
                $updGuard = $curGuard;
            }

            $updPrio = $newPriority !== '' ? $newPriority : $curPrio;

            $stmtUpd = $pdo->prepare("
                UPDATE incidencias
                SET guardia_id = :g,
                    estado = :estado,
                    prioridad = :prio,
                    updated_at = NOW()
                WHERE id = :id
                  AND residencial_id = :resid
            ");
            $stmtUpd->execute([
                'g' => $updGuard,
                'estado' => $updEstado,
                'prio' => $updPrio,
                'id' => $incidentId,
                'resid' => $residencialId,
            ]);

            json_out(true, ['message' => 'Incidencia actualizada.']);
        }

        // CREATE
        $unidadId = (int)($_POST['unidad_id'] ?? 0);
        $tipo = clean_str($_POST['tipo'] ?? 'otro');
        $titulo = clean_str($_POST['titulo'] ?? '');
        $descripcion = clean_str($_POST['descripcion'] ?? '');
        $prioridad = clean_str($_POST['prioridad'] ?? 'media');

        if ($unidadId <= 0 || $titulo === '' || $descripcion === '') {
            json_out(false, ['error' => 'Unidad, título y descripción son requeridos.']);
        }

        if (!in_array($tipo, ['seguridad', 'servicio', 'vecino', 'infraestructura', 'otro'], true)) {
            json_out(false, ['error' => 'Tipo inválido.']);
        }

        if (!in_array($prioridad, ['baja', 'media', 'alta'], true)) {
            json_out(false, ['error' => 'Prioridad inválida.']);
        }

        $stmtUnidad = $pdo->prepare("
            SELECT id
            FROM unidades
            WHERE id = :id
              AND residencial_id = :resid
            LIMIT 1
        ");
        $stmtUnidad->execute([
            'id' => $unidadId,
            'resid' => $residencialId,
        ]);

        if (!$stmtUnidad->fetchColumn()) {
            json_out(false, ['error' => 'La unidad no pertenece a tu residencial.']);
        }

        $stmtRu = $pdo->prepare("
            SELECT ru.user_id
            FROM residentes_unidades ru
            JOIN unidades un ON un.id = ru.unidad_id
            WHERE ru.unidad_id = :unidad
              AND un.residencial_id = :resid
              AND ru.activo = 1
            ORDER BY ru.es_titular DESC, ru.id ASC
            LIMIT 1
        ");
        $stmtRu->execute([
            'unidad' => $unidadId,
            'resid' => $residencialId,
        ]);

        $residenteId = (int)$stmtRu->fetchColumn();
        if (!$residenteId) {
            json_out(false, ['error' => 'No hay un residente asignado a esa unidad.']);
        }

        $stmtIns = $pdo->prepare("
            INSERT INTO incidencias (
                residencial_id,
                unidad_id,
                residente_id,
                guardia_id,
                tipo,
                titulo,
                descripcion,
                prioridad,
                estado,
                created_at,
                updated_at
            ) VALUES (
                :resid,
                :unidad,
                :residente,
                NULL,
                :tipo,
                :titulo,
                :descripcion,
                :prioridad,
                'abierta',
                NOW(),
                NOW()
            )
        ");
        $stmtIns->execute([
            'resid' => $residencialId,
            'unidad' => $unidadId,
            'residente' => $residenteId,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'prioridad' => $prioridad,
        ]);

        json_out(true, ['message' => 'Incidencia registrada exitosamente.']);
    }

    json_out(false, ['error' => 'Método no soportado.']);
} catch (Throwable $e) {
    app_json_exception($e, 'No pudimos procesar las incidencias.');
}
