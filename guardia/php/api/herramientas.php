<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/service_profile.php';
require_once __DIR__ . '/../../../config/operational_mode.php';
require_once __DIR__ . '/../../../config/image_uploads.php';

require_login();
require_role(['guardia', 'super_admin']);

function out(bool $ok, array $data = [], int $code = 200): void
{
    http_response_code($code);
    echo json_encode(array_merge(['ok' => $ok], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

function strv(string $value, int $maxLen): string
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

function intv(mixed $value, int $default = 0): int
{
    if ($value === null) {
        return $default;
    }
    if (is_int($value)) {
        return $value;
    }
    $raw = preg_replace('/[^\d-]/', '', (string)$value);
    if ($raw === '' || $raw === '-' || $raw === '+') {
        return $default;
    }
    return (int)$raw;
}

function guardia_require_module_access(array $profile, bool $isSuperAdmin, string $module, string $message): void
{
    if ($isSuperAdmin) {
        return;
    }
    if (!service_profile_module_allowed_for_role_and_service($profile, 'guardia', $module)) {
        out(false, ['error' => $message], 403);
    }
}

function save_operational_file_record(PDO $pdo, int $rid, string $entityType, int $entityId, string $subtype, array $file): ?array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $processed = operational_process_image_upload($file, [
        'folder_prefix' => 'herramientas',
        'quality' => 82,
        'preserve_text' => true,
        'max_long_side' => 1400,
    ]);

    $stmt = $pdo->prepare("
        INSERT INTO archivos_operativos (
            residencial_id, entidad_tipo, entidad_id, subtipo, storage_disk, storage_path, public_url,
            mime_type, size_bytes, width, height, created_at, updated_at
        ) VALUES (
            :rid, :entidad_tipo, :entidad_id, :subtipo, :storage_disk, :storage_path, :public_url,
            :mime_type, :size_bytes, :width, :height, NOW(), NOW()
        )
    ");
    $stmt->execute([
        'rid' => $rid,
        'entidad_tipo' => $entityType,
        'entidad_id' => $entityId,
        'subtipo' => $subtype,
        'storage_disk' => $processed['disk'],
        'storage_path' => $processed['path'],
        'public_url' => $processed['public_url'],
        'mime_type' => $processed['mime_type'],
        'size_bytes' => (int)$processed['size_bytes'],
        'width' => (int)$processed['width'],
        'height' => (int)$processed['height'],
    ]);

    return $processed;
}

function fetch_evidencias(PDO $pdo, int $rid, string $entityType, array $ids): array
{
    $ids = array_values(array_filter(array_map(static fn($v): int => (int)$v, $ids)));
    if (!$ids) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT entidad_id, public_url
        FROM archivos_operativos
        WHERE residencial_id = ?
          AND entidad_tipo = ?
          AND entidad_id IN ($placeholders)
        ORDER BY id ASC
    ");
    $stmt->execute(array_merge([$rid, $entityType], $ids));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $map = [];
    foreach ($rows as $row) {
        $eid = (int)($row['entidad_id'] ?? 0);
        if ($eid <= 0) continue;
        $map[$eid] = $map[$eid] ?? [];
        $map[$eid][] = (string)($row['public_url'] ?? '');
    }
    return $map;
}

try {
    operational_schema_ensure($pdo);
    service_profile_schema_ensure($pdo);

    $user = current_user();
    $uid = (int)($user['id'] ?? 0);
    $isSuperAdmin = (($user['role'] ?? '') === 'super_admin');

    $rid = service_profile_resolve_residencial_id_for_user($pdo, $uid);
    if ($rid <= 0) {
        out(false, ['error' => 'No se pudo resolver el servicio asignado.'], 422);
    }

    $profile = $isSuperAdmin
        ? service_profile_get($pdo, $rid)
        : service_profile_require_role_enabled($pdo, $rid, 'guardia', 'El panel de guardia no está habilitado para este cliente.');

    guardia_require_module_access($profile, $isSuperAdmin, 'herramientas', 'El módulo de herramientas no está habilitado para este cliente.');

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = (string)($_GET['action'] ?? $_POST['action'] ?? 'prestamos');

    if ($method === 'GET' && $action === 'catalogo') {
        $stmt = $pdo->prepare("
            SELECT id, nombre, descripcion
            FROM catalogo_herramientas
            WHERE residencial_id = :rid
              AND activo = 1
            ORDER BY nombre ASC, id ASC
            LIMIT 250
        ");
        $stmt->execute(['rid' => $rid]);
        out(true, ['data' => ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]]);
    }

    if ($method === 'GET' && $action === 'unidades') {
        $stmt = $pdo->prepare("
            SELECT id, clave, tipo, torre
            FROM unidades
            WHERE residencial_id = :rid
            ORDER BY clave ASC, id ASC
            LIMIT 400
        ");
        $stmt->execute(['rid' => $rid]);
        out(true, ['data' => ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]]);
    }

    if ($method === 'GET' && $action === 'residentes_unidad') {
        $unidadId = intv($_GET['unidad_id'] ?? 0, 0);
        if ($unidadId <= 0) {
            out(false, ['error' => 'Falta unidad_id.'], 422);
        }
        $stmt = $pdo->prepare("
            SELECT u.id AS user_id, u.name, u.email
            FROM residentes_unidades ru
            JOIN users u ON u.id = ru.user_id
            JOIN usuarios_residenciales ur ON ur.user_id = u.id AND ur.residencial_id = :rid
            JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
            WHERE ru.unidad_id = :unidad_id
              AND ru.activo = 1
              AND t.nombre = 'residente'
            ORDER BY u.name ASC, u.id ASC
            LIMIT 80
        ");
        $stmt->execute([
            'rid' => $rid,
            'unidad_id' => $unidadId,
        ]);
        out(true, ['data' => ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]]);
    }

    if ($method === 'GET' && $action === 'prestamos') {
        $estado = strv((string)($_GET['estado'] ?? ''), 20);
        if ($estado !== '' && !in_array($estado, ['prestado', 'devuelto'], true)) {
            out(false, ['error' => 'Estado inválido.'], 422);
        }
        $page = max(1, intv($_GET['page'] ?? 1, 1));
        $perPage = 3;
        $offset = ($page - 1) * $perPage;

        $where = ['p.residencial_id = :rid'];
        $params = ['rid' => $rid];
        if ($estado !== '') {
            $where[] = 'p.estado = :estado';
            $params['estado'] = $estado;
        }

        $countStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM prestamos_herramientas p
            WHERE " . implode(' AND ', $where) . "
        ");
        $countStmt->execute($params);
        $total = (int)($countStmt->fetchColumn() ?: 0);

        $stmt = $pdo->prepare("
            SELECT
                p.*,
                h.nombre AS herramienta_nombre,
                u.clave AS unidad_clave,
                g.name AS guardia_nombre,
                r.name AS residente_nombre,
                r.email AS residente_email
            FROM prestamos_herramientas p
            JOIN catalogo_herramientas h ON h.id = p.herramienta_id
            JOIN unidades u ON u.id = p.unidad_id
            LEFT JOIN users g ON g.id = p.guardia_id
            LEFT JOIN users r ON r.id = p.residente_id
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
        $evMap = fetch_evidencias($pdo, $rid, 'prestamo_herramienta', $ids);

        $items = array_map(static function (array $row) use ($evMap): array {
            $id = (int)($row['id'] ?? 0);
            return [
                'id' => $id,
                'estado' => (string)($row['estado'] ?? 'prestado'),
                'herramienta_id' => (int)($row['herramienta_id'] ?? 0),
                'herramienta_nombre' => (string)($row['herramienta_nombre'] ?? ''),
                'unidad_id' => (int)($row['unidad_id'] ?? 0),
                'unidad_clave' => (string)($row['unidad_clave'] ?? ''),
                'residente_id' => (int)($row['residente_id'] ?? 0),
                'residente_nombre' => (string)($row['residente_nombre'] ?? ''),
                'residente_email' => (string)($row['residente_email'] ?? ''),
                'guardia_id' => (int)($row['guardia_id'] ?? 0),
                'guardia_nombre' => (string)($row['guardia_nombre'] ?? ''),
                'notas' => (string)($row['notas'] ?? ''),
                'prestado_at' => (string)($row['prestado_at'] ?? ''),
                'devuelto_at' => (string)($row['devuelto_at'] ?? ''),
                'evidencias' => $evMap[$id] ?? [],
            ];
        }, $rows);

        out(true, [
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

    if ($method === 'POST' && $action === 'create_prestamo') {
        $herramientaId = intv($_POST['herramienta_id'] ?? 0, 0);
        $unidadId = intv($_POST['unidad_id'] ?? 0, 0);
        $residenteId = intv($_POST['residente_id'] ?? 0, 0);
        $notas = strv((string)($_POST['notas'] ?? ''), 1200);

        if ($herramientaId <= 0 || $unidadId <= 0) {
            out(false, ['error' => 'Debes seleccionar herramienta y unidad.'], 422);
        }

        $stmtH = $pdo->prepare("
            SELECT 1
            FROM catalogo_herramientas
            WHERE id = :id AND residencial_id = :rid AND activo = 1
            LIMIT 1
        ");
        $stmtH->execute(['id' => $herramientaId, 'rid' => $rid]);
        if (!(bool)$stmtH->fetchColumn()) {
            out(false, ['error' => 'Herramienta inválida o inactiva.'], 422);
        }

        $stmtU = $pdo->prepare("
            SELECT 1
            FROM unidades
            WHERE id = :id AND residencial_id = :rid
            LIMIT 1
        ");
        $stmtU->execute(['id' => $unidadId, 'rid' => $rid]);
        if (!(bool)$stmtU->fetchColumn()) {
            out(false, ['error' => 'Unidad inválida para este servicio.'], 422);
        }

        if ($residenteId > 0) {
            $stmtR = $pdo->prepare("
                SELECT 1
                FROM users u
                JOIN usuarios_residenciales ur ON ur.user_id = u.id AND ur.residencial_id = :rid
                JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
                WHERE u.id = :uid
                  AND t.nombre = 'residente'
                LIMIT 1
            ");
            $stmtR->execute(['rid' => $rid, 'uid' => $residenteId]);
            if (!(bool)$stmtR->fetchColumn()) {
                out(false, ['error' => 'Residente inválido para este servicio.'], 422);
            }
        } else {
            $residenteId = null;
        }

        $pdo->beginTransaction();
        $ins = $pdo->prepare("
            INSERT INTO prestamos_herramientas (
                residencial_id, herramienta_id, guardia_id, unidad_id, residente_id,
                estado, notas, prestado_at, devuelto_at, created_at, updated_at
            ) VALUES (
                :rid, :herramienta_id, :guardia_id, :unidad_id, :residente_id,
                'prestado', :notas, NOW(), NULL, NOW(), NOW()
            )
        ");
        $ins->execute([
            'rid' => $rid,
            'herramienta_id' => $herramientaId,
            'guardia_id' => $uid,
            'unidad_id' => $unidadId,
            'residente_id' => $residenteId,
            'notas' => $notas !== '' ? $notas : null,
        ]);
        $prestamoId = (int)$pdo->lastInsertId();

        $urls = [];
        for ($i = 1; $i <= 3; $i++) {
            $key = 'evidencia_' . $i;
            if (!isset($_FILES[$key])) {
                continue;
            }
            $processed = save_operational_file_record($pdo, $rid, 'prestamo_herramienta', $prestamoId, 'evidencia', $_FILES[$key]);
            if ($processed && !empty($processed['public_url'])) {
                $urls[] = (string)$processed['public_url'];
            }
        }

        $toolName = '';
        $unitClave = '';
        try {
            $toolName = (string)($pdo->query("SELECT nombre FROM catalogo_herramientas WHERE id=" . (int)$herramientaId)->fetchColumn() ?: '');
            $unitClave = (string)($pdo->query("SELECT clave FROM unidades WHERE id=" . (int)$unidadId)->fetchColumn() ?: '');
        } catch (_) {}

        operational_write_bitacora($pdo, [
            'residencial_id' => $rid,
            'guardia_id' => $uid,
            'tipo_origen' => 'prestamo_herramienta',
            'origen_id' => $prestamoId,
            'tipo_evento' => 'prestado',
            'resultado' => 'informativo',
            'observaciones' => trim(sprintf('Se prestó %s a la unidad %s.', $toolName ?: 'herramienta', $unitClave ?: '—')),
            'metadata_json' => [
                'prestamo_id' => $prestamoId,
                'herramienta_id' => $herramientaId,
                'unidad_id' => $unidadId,
                'residente_id' => $residenteId,
            ],
        ]);

        $pdo->commit();
        out(true, [
            'message' => 'Préstamo registrado.',
            'data' => [
                'id' => $prestamoId,
                'evidencias' => $urls,
            ],
        ]);
    }

    if ($method === 'POST' && $action === 'marcar_devuelto') {
        $prestamoId = intv($_POST['prestamo_id'] ?? 0, 0);
        if ($prestamoId <= 0) {
            out(false, ['error' => 'Falta prestamo_id.'], 422);
        }

        $pdo->beginTransaction();
        $stmtP = $pdo->prepare("
            SELECT p.*, h.nombre AS herramienta_nombre, u.clave AS unidad_clave
            FROM prestamos_herramientas p
            JOIN catalogo_herramientas h ON h.id = p.herramienta_id
            JOIN unidades u ON u.id = p.unidad_id
            WHERE p.id = :id AND p.residencial_id = :rid
            LIMIT 1
            FOR UPDATE
        ");
        $stmtP->execute(['id' => $prestamoId, 'rid' => $rid]);
        $row = $stmtP->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $pdo->rollBack();
            out(false, ['error' => 'Préstamo no encontrado.'], 404);
        }
        if (($row['estado'] ?? '') === 'devuelto') {
            $pdo->rollBack();
            out(true, ['message' => 'Este préstamo ya estaba marcado como devuelto.']);
        }

        $up = $pdo->prepare("
            UPDATE prestamos_herramientas
            SET estado = 'devuelto', devuelto_at = NOW()
            WHERE id = :id AND residencial_id = :rid
            LIMIT 1
        ");
        $up->execute(['id' => $prestamoId, 'rid' => $rid]);

        operational_write_bitacora($pdo, [
            'residencial_id' => $rid,
            'guardia_id' => $uid,
            'tipo_origen' => 'prestamo_herramienta',
            'origen_id' => $prestamoId,
            'tipo_evento' => 'devuelto',
            'resultado' => 'informativo',
            'observaciones' => trim(sprintf('Se devolvió %s (unidad %s).', (string)($row['herramienta_nombre'] ?? 'herramienta'), (string)($row['unidad_clave'] ?? '—'))),
            'metadata_json' => ['prestamo_id' => $prestamoId],
        ]);

        $pdo->commit();
        out(true, ['message' => 'Préstamo marcado como devuelto.']);
    }

    out(false, ['error' => 'Acción inválida.'], 422);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (function_exists('app_json_exception')) {
        app_json_exception($e, 'No pudimos procesar el módulo de herramientas.');
    }
    out(false, ['error' => 'No pudimos procesar el módulo de herramientas.'], 500);
}

