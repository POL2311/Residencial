<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$action = sa_post_action('list');

function sa_comunicado_normalize(array $row): array
{
    return [
        'id' => (int)($row['id'] ?? 0),
        'residencial_id' => (int)($row['residencial_id'] ?? 0),
        'residencial_nombre' => (string)($row['residencial_nombre'] ?? ''),
        'residencial_codigo' => (string)($row['residencial_codigo'] ?? ''),
        'titulo' => (string)($row['titulo'] ?? ''),
        'mensaje' => (string)($row['mensaje'] ?? ''),
        'tipo' => (string)($row['tipo'] ?? 'general'),
        'prioridad' => (string)($row['prioridad'] ?? 'media'),
        'fecha_publicacion' => (string)($row['fecha_publicacion'] ?? ''),
        'fecha_expiracion' => (string)($row['fecha_expiracion'] ?? ''),
        'estado' => (string)($row['estado'] ?? 'publicado'),
        'creado_por' => isset($row['creado_por']) ? (int)$row['creado_por'] : null,
        'creado_por_nombre' => (string)($row['creado_por_nombre'] ?? ''),
        'actualizado_por' => isset($row['actualizado_por']) ? (int)$row['actualizado_por'] : null,
        'actualizado_por_nombre' => (string)($row['actualizado_por_nombre'] ?? ''),
        'updated_at' => (string)($row['updated_at'] ?? ''),
    ];
}

try {
    if ($action === 'meta') {
        sa_json_out(true, [
            'data' => [
                'csrf_token' => sa_csrf_token(),
                'servicios' => sa_fetch_services($pdo),
            ],
        ]);
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && $action === 'list') {
        $residencialId = (int)($_GET['residencial_id'] ?? 0);
        $estado = trim((string)($_GET['estado'] ?? ''));
        $prioridad = trim((string)($_GET['prioridad'] ?? ''));
        $q = sa_clean_str($_GET['q'] ?? '', 120);

        $sql = "
            SELECT
                c.*,
                r.nombre AS residencial_nombre,
                r.codigo AS residencial_codigo,
                uc.name AS creado_por_nombre,
                uu.name AS actualizado_por_nombre
            FROM comunicados_residenciales c
            JOIN residenciales r ON r.id = c.residencial_id
            LEFT JOIN users uc ON uc.id = c.creado_por
            LEFT JOIN users uu ON uu.id = c.actualizado_por
            WHERE 1 = 1
        ";
        $params = [];

        if ($residencialId > 0) {
            $sql .= " AND c.residencial_id = :rid";
            $params['rid'] = $residencialId;
        }
        if ($estado !== '' && in_array($estado, ['publicado', 'borrador', 'archivado'], true)) {
            $sql .= " AND c.estado = :estado";
            $params['estado'] = $estado;
        }
        if ($prioridad !== '' && in_array($prioridad, ['baja', 'media', 'alta'], true)) {
            $sql .= " AND c.prioridad = :prioridad";
            $params['prioridad'] = $prioridad;
        }
        if ($q !== '') {
            $sql .= " AND (c.titulo LIKE :q OR c.mensaje LIKE :q OR r.nombre LIKE :q OR r.codigo LIKE :q)";
            $params['q'] = '%' . $q . '%';
        }

        $sql .= " ORDER BY c.fecha_publicacion DESC, c.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = array_map('sa_comunicado_normalize', $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

        $summary = [
            'total' => count($items),
            'publicados' => 0,
            'borradores' => 0,
            'archivados' => 0,
        ];
        foreach ($items as $item) {
            $status = (string)($item['estado'] ?? '');
            if ($status === 'publicado') {
                $summary['publicados']++;
            } elseif ($status === 'borrador') {
                $summary['borradores']++;
            } elseif ($status === 'archivado') {
                $summary['archivados']++;
            }
        }

        sa_json_out(true, ['data' => ['items' => $items, 'summary' => $summary]]);
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        sa_require_csrf();

        if ($action === 'archive') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                sa_json_out(false, ['error' => 'Comunicado inválido.'], 422);
            }

            $stmt = $pdo->prepare("
                UPDATE comunicados_residenciales
                SET estado = 'archivado',
                    updated_at = NOW(),
                    actualizado_por = :uid
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => $id,
                'uid' => (int)(current_user()['id'] ?? 0),
            ]);

            if ($stmt->rowCount() <= 0) {
                sa_json_out(false, ['error' => 'No se pudo archivar el comunicado.'], 404);
            }

            sa_json_out(true, ['message' => 'Comunicado archivado.']);
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                sa_json_out(false, ['error' => 'Comunicado inválido.'], 422);
            }

            $stmt = $pdo->prepare("DELETE FROM comunicados_residenciales WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $id]);
            if ($stmt->rowCount() <= 0) {
                sa_json_out(false, ['error' => 'No encontramos el comunicado a borrar.'], 404);
            }

            sa_json_out(true, ['message' => 'Comunicado eliminado definitivamente.']);
        }

        if ($action === 'save') {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $residencialId = (int)($_POST['residencial_id'] ?? 0);
            $titulo = sa_clean_str($_POST['titulo'] ?? '', 150);
            $mensaje = trim((string)($_POST['mensaje'] ?? ''));
            $tipo = sa_clean_str($_POST['tipo'] ?? 'general', 30);
            $prioridad = sa_clean_str($_POST['prioridad'] ?? 'media', 20);
            $fechaPub = sa_clean_str($_POST['fecha_publicacion'] ?? '', 20) ?: date('Y-m-d');
            $fechaExp = sa_clean_str($_POST['fecha_expiracion'] ?? '', 20);
            $estado = sa_clean_str($_POST['estado'] ?? 'publicado', 20);
            $userId = (int)(current_user()['id'] ?? 0);

            if ($residencialId <= 0) {
                sa_json_out(false, ['error' => 'Debes seleccionar un servicio.'], 422);
            }
            if ($titulo === '' || $mensaje === '') {
                sa_json_out(false, ['error' => 'Título y mensaje son requeridos.'], 422);
            }
            if (!in_array($tipo, ['general', 'mantenimiento', 'seguridad', 'pagos'], true)) {
                sa_json_out(false, ['error' => 'Tipo inválido.'], 422);
            }
            if (!in_array($prioridad, ['baja', 'media', 'alta'], true)) {
                sa_json_out(false, ['error' => 'Prioridad inválida.'], 422);
            }
            if (!in_array($estado, ['publicado', 'borrador', 'archivado'], true)) {
                sa_json_out(false, ['error' => 'Estado inválido.'], 422);
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaPub)) {
                sa_json_out(false, ['error' => 'Fecha de publicación inválida.'], 422);
            }
            if ($fechaExp !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaExp)) {
                sa_json_out(false, ['error' => 'Fecha de expiración inválida.'], 422);
            }
            if ($fechaExp !== '' && $fechaExp < $fechaPub) {
                sa_json_out(false, ['error' => 'La fecha de expiración no puede ser menor a la de publicación.'], 422);
            }

            $stmtService = $pdo->prepare("SELECT id FROM residenciales WHERE id = :id LIMIT 1");
            $stmtService->execute(['id' => $residencialId]);
            if (!$stmtService->fetchColumn()) {
                sa_json_out(false, ['error' => 'Servicio no encontrado.'], 404);
            }

            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE comunicados_residenciales
                    SET residencial_id = :rid,
                        titulo = :titulo,
                        mensaje = :mensaje,
                        tipo = :tipo,
                        prioridad = :prioridad,
                        fecha_publicacion = :fecha_pub,
                        fecha_expiracion = :fecha_exp,
                        estado = :estado,
                        actualizado_por = :user_id,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    'rid' => $residencialId,
                    'titulo' => $titulo,
                    'mensaje' => $mensaje,
                    'tipo' => $tipo,
                    'prioridad' => $prioridad,
                    'fecha_pub' => $fechaPub,
                    'fecha_exp' => $fechaExp !== '' ? $fechaExp : null,
                    'estado' => $estado,
                    'user_id' => $userId,
                    'id' => $id,
                ]);

                sa_json_out(true, ['message' => 'Comunicado actualizado.']);
            }

            $stmt = $pdo->prepare("
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
                    creado_at,
                    reglamentos_residenciales
                ) VALUES (
                    :rid,
                    :titulo,
                    :mensaje,
                    :tipo,
                    :prioridad,
                    :fecha_pub,
                    :fecha_exp,
                    1,
                    :estado,
                    :user_id,
                    NOW(),
                    ''
                )
            ");
            $stmt->execute([
                'rid' => $residencialId,
                'titulo' => $titulo,
                'mensaje' => $mensaje,
                'tipo' => $tipo,
                'prioridad' => $prioridad,
                'fecha_pub' => $fechaPub,
                'fecha_exp' => $fechaExp !== '' ? $fechaExp : null,
                'estado' => $estado,
                'user_id' => $userId,
            ]);

            sa_json_out(true, ['message' => 'Comunicado creado.']);
        }
    }

    sa_json_out(false, ['error' => 'Acción no soportada.'], 400);
} catch (Throwable $e) {
    sa_json_out(false, ['error' => 'No pudimos procesar los comunicados.'], 500);
}
