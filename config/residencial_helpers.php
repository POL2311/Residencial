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