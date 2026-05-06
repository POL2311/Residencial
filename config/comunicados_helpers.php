<?php
declare(strict_types=1);

require_once __DIR__ . '/api_helpers.php';

if (!function_exists('comunicados_column_exists')) {
    function comunicados_column_exists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
              AND COLUMN_NAME = :column
        ");
        $stmt->execute([
            'table' => $table,
            'column' => $column,
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('comunicados_schema_ensure')) {
    function comunicados_schema_ensure(PDO $pdo): void
    {
        if (!tableExists($pdo, 'comunicados_residenciales')) {
            return;
        }

        if (!comunicados_column_exists($pdo, 'comunicados_residenciales', 'imagen_url')) {
            $pdo->exec("ALTER TABLE comunicados_residenciales ADD COLUMN imagen_url VARCHAR(255) NULL DEFAULT NULL AFTER mensaje");
        }
    }
}

