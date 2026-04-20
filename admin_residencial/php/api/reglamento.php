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

function normalize_reglamento(array $r): array {
    return [
        'id' => (int)($r['id'] ?? 0),
        'titulo' => (string)($r['titulo'] ?? ''),
        'contenido' => (string)($r['contenido'] ?? ''),
        'version_label' => (string)($r['version_label'] ?? ''),
    ];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $stmt = $pdo->prepare("
            SELECT id, titulo, contenido, version_label
            FROM reglamentos_residenciales
            WHERE residencial_id = :rid
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute(['rid' => $residencialId]);

        $reglamento = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$reglamento) {
            $reglamento = [
                'id' => 0,
                'titulo' => '',
                'contenido' => '',
                'version_label' => '',
            ];
        }

        json_out(true, ['reglamento' => normalize_reglamento($reglamento)]);
    }

    if ($method === 'POST') {
        $titulo = clean_str($_POST['titulo'] ?? '');
        $contenido = trim((string)($_POST['contenido'] ?? ''));
        $version = clean_str($_POST['version_label'] ?? '');

        if ($titulo === '' || $contenido === '') {
            json_out(false, ['error' => 'Título y contenido son obligatorios.']);
        }

        if (mb_strlen($titulo) < 3) {
            json_out(false, ['error' => 'El título debe tener al menos 3 caracteres.']);
        }

        if (mb_strlen($titulo) > 180) {
            json_out(false, ['error' => 'El título no puede exceder 180 caracteres.']);
        }

        if (mb_strlen($contenido) < 20) {
            json_out(false, ['error' => 'El contenido del reglamento es demasiado corto.']);
        }

        if (mb_strlen($contenido) > 30000) {
            json_out(false, ['error' => 'El contenido del reglamento es demasiado largo.']);
        }

        if ($version !== '' && mb_strlen($version) > 50) {
            json_out(false, ['error' => 'La versión no puede exceder 50 caracteres.']);
        }

        $stmtChk = $pdo->prepare("
            SELECT id
            FROM reglamentos_residenciales
            WHERE residencial_id = :rid
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmtChk->execute(['rid' => $residencialId]);

        $existingId = (int)$stmtChk->fetchColumn();

        if ($existingId > 0) {
            $stmtUp = $pdo->prepare("
                UPDATE reglamentos_residenciales
                SET titulo = :titulo,
                    contenido = :contenido,
                    version_label = :version
                WHERE id = :id
            ");
            $stmtUp->execute([
                'titulo' => $titulo,
                'contenido' => $contenido,
                'version' => $version !== '' ? $version : null,
                'id' => $existingId,
            ]);

            json_out(true, ['message' => 'Reglamento actualizado.']);
        }

        $stmtIns = $pdo->prepare("
            INSERT INTO reglamentos_residenciales (
                residencial_id,
                titulo,
                contenido,
                version_label
            ) VALUES (
                :rid,
                :titulo,
                :contenido,
                :version
            )
        ");
        $stmtIns->execute([
            'rid' => $residencialId,
            'titulo' => $titulo,
            'contenido' => $contenido,
            'version' => $version !== '' ? $version : null,
        ]);

        json_out(true, ['message' => 'Reglamento guardado.']);
    }

    json_out(false, ['error' => 'Método no soportado.']);
} catch (Throwable $e) {
    app_json_exception($e, 'No pudimos guardar el reglamento.');
}
