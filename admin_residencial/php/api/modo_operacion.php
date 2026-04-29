<?php
declare(strict_types=1);

require_once __DIR__ . '/_operational_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        json_out(true, [
            'data' => [
                'modo_operacion' => $operationalMode,
                'modos' => operational_allowed_modes(),
                'context' => admin_operational_context(),
            ],
        ]);
    }

    $modo = operational_normalize_mode((string)($_POST['modo_operacion'] ?? 'residencial'));

    $stmt = $pdo->prepare("
        UPDATE residenciales
        SET modo_operacion = :modo
        WHERE id = :rid
        LIMIT 1
    ");
    $stmt->execute([
        'modo' => $modo,
        'rid' => $residencialId,
    ]);

    json_out(true, ['message' => 'Modo operativo actualizado.', 'data' => ['modo_operacion' => $modo]]);
} catch (Throwable $e) {
    app_json_exception($e, 'No pudimos actualizar el modo operativo.');
}
