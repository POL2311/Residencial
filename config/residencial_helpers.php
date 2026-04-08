<?php
declare(strict_types=1);

require_once __DIR__ . '/api_helpers.php';

if (!function_exists('require_residencial_id')) {
    function require_residencial_id(PDO $pdo, int $adminId): int {
        $stmt = $pdo->prepare("
            SELECT residencial_id
            FROM usuarios_residenciales
            WHERE user_id = ?
            ORDER BY es_principal DESC, created_at ASC
            LIMIT 1
        ");
        $stmt->execute([$adminId]);

        $rid = (int)$stmt->fetchColumn();
        if (!$rid) {
            json_out(false, ['error' => 'No tienes residencial asignado.']);
        }

        return $rid;
    }
}

if (!function_exists('get_context_for_user')) {
    function get_context_for_user(PDO $pdo, int $userId): array {
        $stmt = $pdo->prepare("
            SELECT ur.residencial_id,
                   ru.unidad_id
            FROM usuarios_residenciales ur
            LEFT JOIN residentes_unidades ru ON ru.user_id = ur.user_id
            WHERE ur.user_id = :uid
            ORDER BY ur.es_principal DESC, ur.created_at ASC
            LIMIT 1
        ");
        $stmt->execute(['uid' => $userId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'residencial_id' => (int)($row['residencial_id'] ?? 0),
            'unidad_id'      => (int)($row['unidad_id'] ?? 0),
        ];
    }
}

if (!function_exists('resolve_resident_context')) {
    function resolve_resident_context(PDO $pdo, int $userId, bool $requireUnit = true): array {
        $stmt = $pdo->prepare("
            SELECT
                ur.residencial_id,
                r.nombre AS residencial_nombre,
                r.telefono_contacto,
                ru.unidad_id,
                ru.activo AS unidad_activa,
                u.id AS unidad_exists,
                u.residencial_id AS unidad_residencial_id,
                u.clave AS unidad_clave
            FROM usuarios_residenciales ur
            LEFT JOIN residenciales r ON r.id = ur.residencial_id
            LEFT JOIN residentes_unidades ru ON ru.user_id = ur.user_id
            LEFT JOIN unidades u ON u.id = ru.unidad_id
            WHERE ur.user_id = :uid
            ORDER BY ur.es_principal DESC, ru.activo DESC, ur.created_at ASC, ru.created_at ASC
            LIMIT 1
        ");
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$row) {
            return [
                'ok' => false,
                'http_status' => 403,
                'error' => 'Tu cuenta no está ligada a un residencial.',
                'missing' => ['residencial'],
                'ctx' => null,
            ];
        }

        $residencialId = (int)($row['residencial_id'] ?? 0);
        $unidadId = (int)($row['unidad_id'] ?? 0);
        $unidadExists = (int)($row['unidad_exists'] ?? 0);
        $unidadActiva = (int)($row['unidad_activa'] ?? 0);
        $unidadResidencialId = (int)($row['unidad_residencial_id'] ?? 0);

        $ctx = [
            'residencial_id' => $residencialId,
            'residencial_nombre' => (string)($row['residencial_nombre'] ?? ''),
            'caseta_phone' => $row['telefono_contacto'] ?? null,
            'unidad_id' => $unidadId,
            'unidad_clave' => (string)($row['unidad_clave'] ?? ''),
            'unidad_activa' => $unidadActiva,
        ];

        $missing = [];
        if ($residencialId <= 0) {
            $missing[] = 'residencial';
        }
        if ($requireUnit && $unidadId <= 0) {
            $missing[] = 'unidad';
        }

        if ($requireUnit && $unidadId > 0 && $unidadActiva !== 1) {
            return [
                'ok' => false,
                'http_status' => 403,
                'error' => 'Tu cuenta no tiene una unidad activa asignada.',
                'missing' => ['unidad_activa'],
                'ctx' => $ctx,
            ];
        }

        if ($requireUnit && $unidadId > 0 && $unidadExists <= 0) {
            return [
                'ok' => false,
                'http_status' => 422,
                'error' => 'La unidad asignada a tu cuenta no existe en la base de datos.',
                'missing' => ['unidad_invalida'],
                'ctx' => $ctx,
            ];
        }

        if ($requireUnit && $unidadId > 0 && $unidadResidencialId > 0 && $unidadResidencialId !== $residencialId) {
            return [
                'ok' => false,
                'http_status' => 422,
                'error' => 'La unidad asignada no pertenece al mismo residencial de tu cuenta.',
                'missing' => ['unidad_residencial_mismatch'],
                'ctx' => $ctx,
            ];
        }

        if ($missing) {
            $label = (count($missing) > 1)
                ? 'un residencial y una unidad'
                : ($missing[0] === 'unidad' ? 'una unidad' : 'un residencial');

            return [
                'ok' => false,
                'http_status' => 403,
                'error' => 'Tu cuenta no está ligada a ' . $label . '.',
                'missing' => $missing,
                'ctx' => $ctx,
            ];
        }

        return [
            'ok' => true,
            'http_status' => 200,
            'error' => null,
            'missing' => [],
            'ctx' => $ctx,
        ];
    }
}
