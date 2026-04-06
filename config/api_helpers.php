<?php
declare(strict_types=1);

if (!function_exists('json_out')) {
    function json_out(bool $ok, array $extra = []): void {
        echo json_encode(
            array_merge(['ok' => $ok], $extra),
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }
}

if (!function_exists('clean_str')) {
    function clean_str(?string $v): string {
        $v = trim((string)($v ?? ''));
        $v = preg_replace('/\s+/', ' ', $v);
        return $v ?: '';
    }
}

if (!function_exists('digits')) {
    function digits(?string $v): string {
        return preg_replace('/\D+/', '', (string)($v ?? ''));
    }
}

if (!function_exists('tableExists')) {
    function tableExists(PDO $pdo, string $table): bool {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :t
        ");
        $stmt->execute(['t' => $table]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('getCols')) {
    function getCols(PDO $pdo, string $table): array {
        $stmt = $pdo->prepare("
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :t
            ORDER BY ORDINAL_POSITION
        ");
        $stmt->execute(['t' => $table]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
}

if (!function_exists('pickCol')) {
    function pickCol(array $cols, array $candidates): ?string {
        foreach ($candidates as $c) {
            if (in_array($c, $cols, true)) {
                return $c;
            }
        }
        return null;
    }
}