<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';

require_login();
require_role(['admin_residencial']);

$user = current_user();
$adminId = (int)($user['id'] ?? 0);
try {
    $stmtRes = $pdo->prepare("SELECT residencial_id FROM usuarios_residenciales WHERE user_id = ? ORDER BY es_principal DESC LIMIT 1");
    $stmtRes->execute([$adminId]);
    $residencialId = (int)$stmtRes->fetchColumn();
    if (!$residencialId) {
        echo json_encode(['ok' => false, 'error' => 'No tienes un residencial asignado.']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'error' => 'Error al obtener residencial.']);
    exit;
}

function json_out(bool $ok, array $data = []) {
    echo json_encode(array_merge(['ok' => $ok], $data));
    exit;
}

// GET: obtener reglamento actual
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->prepare("SELECT id, titulo, contenido, version_label 
                               FROM reglamentos_residenciales 
                               WHERE residencial_id = ? 
                               ORDER BY id DESC 
                               LIMIT 1");
        $stmt->execute([$residencialId]);
        $reglamento = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$reglamento) {
            $reglamento = ['id' => 0, 'titulo' => '', 'contenido' => '', 'version_label' => ''];
        }
        json_out(true, ['reglamento' => $reglamento]);
    } catch (PDOException $e) {
        json_out(false, ['error' => 'Error al obtener reglamento.']);
    }
}

// POST: guardar o actualizar reglamento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $contenido = $_POST['contenido'] ?? '';
    $version = trim($_POST['version_label'] ?? '');
    if ($titulo === '' || $contenido === '') {
        json_out(false, ['error' => 'Título y contenido son obligatorios.']);
    }
    try {
        $stmtChk = $pdo->prepare("SELECT id FROM reglamentos_residenciales WHERE residencial_id = ? ORDER BY id DESC LIMIT 1");
        $stmtChk->execute([$residencialId]);
        $existingId = $stmtChk->fetchColumn();
        if ($existingId) {
            $stmtUp = $pdo->prepare("UPDATE reglamentos_residenciales 
                                     SET titulo = :titulo, contenido = :contenido, version_label = :version 
                                     WHERE id = :id");
            $stmtUp->execute(['titulo' => $titulo, 'contenido' => $contenido, 'version' => $version, 'id' => $existingId]);
            json_out(true, ['message' => 'Reglamento actualizado.']);
        } else {
            $stmtIns = $pdo->prepare("INSERT INTO reglamentos_residenciales (residencial_id, titulo, contenido, version_label) 
                                      VALUES (:resid, :titulo, :contenido, :version)");
            $stmtIns->execute(['resid' => $residencialId, 'titulo' => $titulo, 'contenido' => $contenido, 'version' => $version]);
            json_out(true, ['message' => 'Reglamento guardado.']);
        }
    } catch (PDOException $e) {
        json_out(false, ['error' => 'Error al guardar reglamento.']);
    }
}
?>
