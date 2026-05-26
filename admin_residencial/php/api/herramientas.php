<?php
declare(strict_types=1);

require_once __DIR__ . '/_operational_bootstrap.php';
admin_module_required('herramientas', 'El módulo de herramientas no está habilitado para este cliente.');

function clean_text(string $value, int $maxLen): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (mb_strlen($value) > $maxLen) {
        $value = mb_substr($value, 0, $maxLen);
    }
    return $value;
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = clean_str($_GET['action'] ?? $_POST['action'] ?? 'prestamos');

    if ($method === 'GET' && $action === 'catalogo') {
        $stmt = $pdo->prepare("
            SELECT id, nombre, descripcion, activo, created_at, updated_at
            FROM catalogo_herramientas
            WHERE residencial_id = :rid
            ORDER BY nombre ASC, id ASC
            LIMIT 500
        ");
        $stmt->execute(['rid' => $residencialId]);
        json_out(true, ['data' => ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]]);
    }

    if ($method === 'POST' && $action === 'create_tool') {
        $nombre = clean_text((string)($_POST['nombre'] ?? ''), 150);
        $descripcion = clean_text((string)($_POST['descripcion'] ?? ''), 1200);
        if ($nombre === '') {
            json_out(false, ['error' => 'Debes capturar el nombre de la herramienta.'], 422);
        }
        $stmt = $pdo->prepare("
            INSERT INTO catalogo_herramientas (residencial_id, nombre, descripcion, activo, created_at, updated_at)
            VALUES (:rid, :nombre, :descripcion, 1, NOW(), NOW())
        ");
        $stmt->execute([
            'rid' => $residencialId,
            'nombre' => $nombre,
            'descripcion' => $descripcion !== '' ? $descripcion : null,
        ]);
        json_out(true, ['message' => 'Herramienta creada.']);
    }

    if ($method === 'POST' && $action === 'update_tool') {
        $toolId = (int)($_POST['tool_id'] ?? 0);
        $nombre = clean_text((string)($_POST['nombre'] ?? ''), 150);
        $descripcion = clean_text((string)($_POST['descripcion'] ?? ''), 1200);
        if ($toolId <= 0) {
            json_out(false, ['error' => 'Falta tool_id.'], 422);
        }
        if ($nombre === '') {
            json_out(false, ['error' => 'Debes capturar el nombre de la herramienta.'], 422);
        }
        $stmt = $pdo->prepare("
            UPDATE catalogo_herramientas
            SET nombre = :nombre, descripcion = :descripcion
            WHERE id = :id AND residencial_id = :rid
            LIMIT 1
        ");
        $stmt->execute([
            'nombre' => $nombre,
            'descripcion' => $descripcion !== '' ? $descripcion : null,
            'id' => $toolId,
            'rid' => $residencialId,
        ]);
        json_out(true, ['message' => 'Herramienta actualizada.']);
    }

    if ($method === 'POST' && $action === 'toggle_tool') {
        $toolId = (int)($_POST['tool_id'] ?? 0);
        $activo = (int)($_POST['activo'] ?? 0) === 1 ? 1 : 0;
        if ($toolId <= 0) {
            json_out(false, ['error' => 'Falta tool_id.'], 422);
        }
        $stmt = $pdo->prepare("
            UPDATE catalogo_herramientas
            SET activo = :activo
            WHERE id = :id AND residencial_id = :rid
            LIMIT 1
        ");
        $stmt->execute([
            'activo' => $activo,
            'id' => $toolId,
            'rid' => $residencialId,
        ]);
        json_out(true, ['message' => $activo ? 'Herramienta activada.' : 'Herramienta desactivada.']);
    }

    if ($method === 'POST' && $action === 'delete_tool') {
        $toolId = (int)($_POST['tool_id'] ?? 0);
        if ($toolId <= 0) {
            json_out(false, ['error' => 'Falta tool_id.'], 422);
        }

        $stmtC = $pdo->prepare("
            SELECT COUNT(*)
            FROM prestamos_herramientas
            WHERE residencial_id = :rid AND herramienta_id = :hid
        ");
        $stmtC->execute(['rid' => $residencialId, 'hid' => $toolId]);
        $count = (int)($stmtC->fetchColumn() ?: 0);

        if ($count > 0) {
            $stmt = $pdo->prepare("
                UPDATE catalogo_herramientas
                SET activo = 0
                WHERE id = :id AND residencial_id = :rid
                LIMIT 1
            ");
            $stmt->execute(['id' => $toolId, 'rid' => $residencialId]);
            json_out(true, ['message' => 'La herramienta tiene historial de préstamos. Se desactivó en lugar de borrarse.']);
        }

        $stmt = $pdo->prepare("
            DELETE FROM catalogo_herramientas
            WHERE id = :id AND residencial_id = :rid
            LIMIT 1
        ");
        $stmt->execute(['id' => $toolId, 'rid' => $residencialId]);
        json_out(true, ['message' => 'Herramienta eliminada.']);
    }

    if ($method === 'GET' && $action === 'prestamos') {
        $estado = clean_str($_GET['estado'] ?? '');
        if ($estado !== '' && !in_array($estado, ['prestado', 'devuelto'], true)) {
            json_out(false, ['error' => 'Estado inválido.'], 422);
        }
        $q = clean_str($_GET['q'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 3;
        $offset = ($page - 1) * $perPage;

        $where = ['p.residencial_id = :rid'];
        $params = ['rid' => $residencialId];
        if ($estado !== '') {
            $where[] = 'p.estado = :estado';
            $params['estado'] = $estado;
        }
        if ($q !== '') {
            $where[] = '(h.nombre LIKE :q OR u.clave LIKE :q OR a.nombre LIKE :q OR r.name LIKE :q OR r.email LIKE :q OR pr.nombre LIKE :q OR p.responsable_nombre LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        $countStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM prestamos_herramientas p
            JOIN catalogo_herramientas h ON h.id = p.herramienta_id
            LEFT JOIN unidades u ON u.id = p.unidad_id
            LEFT JOIN areas_operativas a ON a.id = p.area_id
            LEFT JOIN users r ON r.id = p.residente_id
            LEFT JOIN personas_recurrentes pr ON pr.id = p.persona_recurrente_id
            WHERE " . implode(' AND ', $where) . "
        ");
        $countStmt->execute($params);
        $total = (int)($countStmt->fetchColumn() ?: 0);

        $stmt = $pdo->prepare("
            SELECT
                p.*,
                h.nombre AS herramienta_nombre,
                u.clave AS unidad_clave,
                a.nombre AS area_nombre,
                g.name AS guardia_nombre,
                r.name AS residente_nombre,
                r.email AS residente_email,
                pr.nombre AS persona_recurrente_nombre,
                pr.empresa AS persona_recurrente_empresa
            FROM prestamos_herramientas p
            JOIN catalogo_herramientas h ON h.id = p.herramienta_id
            LEFT JOIN unidades u ON u.id = p.unidad_id
            LEFT JOIN areas_operativas a ON a.id = p.area_id
            LEFT JOIN users g ON g.id = p.guardia_id
            LEFT JOIN users r ON r.id = p.residente_id
            LEFT JOIN personas_recurrentes pr ON pr.id = p.persona_recurrente_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY p.prestado_at DESC, p.id DESC
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $ids = array_map(static fn(array $row): int => (int)($row['id'] ?? 0), $rows);

        $evMap = [];
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $evStmt = $pdo->prepare("
                SELECT entidad_id, public_url
                FROM archivos_operativos
                WHERE residencial_id = ?
                  AND entidad_tipo = 'prestamo_herramienta'
                  AND entidad_id IN ($placeholders)
                ORDER BY id ASC
            ");
            $evStmt->execute(array_merge([$residencialId], $ids));
            $evRows = $evStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($evRows as $row) {
                $eid = (int)($row['entidad_id'] ?? 0);
                if ($eid <= 0) continue;
                $evMap[$eid] = $evMap[$eid] ?? [];
                $evMap[$eid][] = (string)($row['public_url'] ?? '');
            }
        }

        $items = array_map(static function (array $row) use ($evMap): array {
            $id = (int)($row['id'] ?? 0);
            return [
                'id' => $id,
                'estado' => (string)($row['estado'] ?? 'prestado'),
                'herramienta_id' => (int)($row['herramienta_id'] ?? 0),
                'herramienta_nombre' => (string)($row['herramienta_nombre'] ?? ''),
                'unidad_id' => (int)($row['unidad_id'] ?? 0),
                'unidad_clave' => (string)($row['unidad_clave'] ?? ''),
                'area_id' => (int)($row['area_id'] ?? 0),
                'area_nombre' => (string)($row['area_nombre'] ?? ''),
                'residente_id' => (int)($row['residente_id'] ?? 0),
                'residente_nombre' => (string)($row['residente_nombre'] ?? ''),
                'residente_email' => (string)($row['residente_email'] ?? ''),
                'persona_recurrente_id' => (int)($row['persona_recurrente_id'] ?? 0),
                'persona_recurrente_nombre' => (string)($row['persona_recurrente_nombre'] ?? ''),
                'persona_recurrente_empresa' => (string)($row['persona_recurrente_empresa'] ?? ''),
                'responsable_nombre' => (string)($row['responsable_nombre'] ?? ''),
                'guardia_id' => (int)($row['guardia_id'] ?? 0),
                'guardia_nombre' => (string)($row['guardia_nombre'] ?? ''),
                'notas' => (string)($row['notas'] ?? ''),
                'prestado_at' => (string)($row['prestado_at'] ?? ''),
                'devuelto_at' => (string)($row['devuelto_at'] ?? ''),
                'evidencias' => $evMap[$id] ?? [],
            ];
        }, $rows);

        json_out(true, [
            'data' => [
                'items' => $items,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => $perPage ? (int)ceil($total / $perPage) : 1,
                ],
            ],
        ]);
    }

    if ($method === 'POST' && $action === 'marcar_devuelto') {
        $prestamoId = (int)($_POST['prestamo_id'] ?? 0);
        if ($prestamoId <= 0) {
            json_out(false, ['error' => 'Falta prestamo_id.'], 422);
        }

        $pdo->beginTransaction();
        $stmtP = $pdo->prepare("
            SELECT
                p.*,
                h.nombre AS herramienta_nombre,
                u.clave AS unidad_clave,
                a.nombre AS area_nombre,
                pr.nombre AS persona_recurrente_nombre
            FROM prestamos_herramientas p
            JOIN catalogo_herramientas h ON h.id = p.herramienta_id
            LEFT JOIN unidades u ON u.id = p.unidad_id
            LEFT JOIN areas_operativas a ON a.id = p.area_id
            LEFT JOIN personas_recurrentes pr ON pr.id = p.persona_recurrente_id
            WHERE p.id = :id AND p.residencial_id = :rid
            LIMIT 1
            FOR UPDATE
        ");
        $stmtP->execute(['id' => $prestamoId, 'rid' => $residencialId]);
        $row = $stmtP->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $pdo->rollBack();
            json_out(false, ['error' => 'Préstamo no encontrado.'], 404);
        }
        if (($row['estado'] ?? '') === 'devuelto') {
            $pdo->rollBack();
            json_out(true, ['message' => 'Este préstamo ya estaba marcado como devuelto.']);
        }

        $up = $pdo->prepare("
            UPDATE prestamos_herramientas
            SET estado='devuelto', devuelto_at=NOW()
            WHERE id=:id AND residencial_id=:rid
            LIMIT 1
        ");
        $up->execute(['id' => $prestamoId, 'rid' => $residencialId]);

        operational_write_bitacora($pdo, [
            'residencial_id' => $residencialId,
            'guardia_id' => null,
            'tipo_origen' => 'prestamo_herramienta',
            'origen_id' => $prestamoId,
            'tipo_evento' => 'devuelto',
            'resultado' => 'informativo',
            'persona_recurrente_id' => $row['persona_recurrente_id'] !== null ? (int)$row['persona_recurrente_id'] : null,
            'area_id' => $row['area_id'] !== null ? (int)$row['area_id'] : null,
            'observaciones' => trim(sprintf(
                'Se devolvió %s (%s %s).',
                (string)($row['herramienta_nombre'] ?? 'herramienta'),
                !empty($row['area_nombre']) ? 'área' : 'unidad',
                (string)($row['area_nombre'] ?: ($row['unidad_clave'] ?? '—'))
            )),
            'metadata_json' => [
                'prestamo_id' => $prestamoId,
                'responsable_nombre' => $row['responsable_nombre'] ?? null,
            ],
        ]);

        $pdo->commit();
        json_out(true, ['message' => 'Préstamo marcado como devuelto.']);
    }

    json_out(false, ['error' => 'Acción inválida.'], 422);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    app_json_exception($e, 'No pudimos procesar el módulo de herramientas.');
}
