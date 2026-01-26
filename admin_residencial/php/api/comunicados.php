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

// GET: listar comunicados (no archivados)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->prepare("SELECT id, titulo, mensaje, tipo, prioridad, fecha_publicacion, fecha_expiracion, estado
                               FROM comunicados_residenciales
                               WHERE residencial_id = ? AND estado != 'archivado'
                               ORDER BY fecha_publicacion DESC, id DESC");
        $stmt->execute([$residencialId]);
        $comunicados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        json_out(true, ['comunicados' => $comunicados]);
    } catch (PDOException $e) {
        json_out(false, ['error' => 'Error al obtener comunicados.']);
    }
}

// POST: archivar o guardar comunicado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Archivar comunicado
    if (isset($_POST['action']) && $_POST['action'] === 'archive') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $stmt = $pdo->prepare("UPDATE comunicados_residenciales SET estado = 'archivado' WHERE id = ? AND residencial_id = ?");
            $stmt->execute([$id, $residencialId]);
            if ($stmt->rowCount() === 0) {
                json_out(false, ['error' => 'No se pudo archivar el comunicado.']);
            }
            json_out(true, ['message' => 'Comunicado archivado.']);
        } catch (PDOException $e) {
            json_out(false, ['error' => 'Error al archivar comunicado.']);
        }
    }

    // Crear o actualizar comunicado
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $titulo = trim($_POST['titulo'] ?? '');
    $mensaje = trim($_POST['mensaje'] ?? '');
    $tipo = $_POST['tipo'] ?? 'general';
    $prioridad = $_POST['prioridad'] ?? 'media';
    $fecha_pub = $_POST['fecha_publicacion'] ?: date('Y-m-d');
    $fecha_exp = $_POST['fecha_expiracion'] ?: null;
    $estado = $_POST['estado'] ?? 'publicado';
    if ($titulo === '' || $mensaje === '') {
        json_out(false, ['error' => 'Título y mensaje son requeridos.']);
    }
    try {
        if ($id > 0) {
            // Actualizar comunicado existente
            $stmtUp = $pdo->prepare("UPDATE comunicados_residenciales
                                     SET titulo = :titulo, mensaje = :mensaje, tipo = :tipo, prioridad = :prioridad,
                                         fecha_publicacion = :fecha_pub, fecha_expiracion = :fecha_exp, estado = :estado, actualizado_por = :userId
                                     WHERE id = :id AND residencial_id = :resid");
            $stmtUp->execute([
                'titulo' => $titulo, 'mensaje' => $mensaje, 'tipo' => $tipo, 'prioridad' => $prioridad,
                'fecha_pub' => $fecha_pub, 'fecha_exp' => $fecha_exp ?: NULL,
                'estado' => $estado, 'userId' => $adminId,
                'id' => $id, 'resid' => $residencialId
            ]);
            json_out(true, ['message' => 'Comunicado actualizado.']);
        } else {
            $stmtIns = $pdo->prepare("INSERT INTO comunicados_residenciales
                                      (residencial_id, titulo, mensaje, tipo, prioridad, fecha_publicacion, fecha_expiracion, visible_para_residentes, estado, creado_por, creado_at)
                                      VALUES (:resid, :titulo, :mensaje, :tipo, :prioridad, :fecha_pub, :fecha_exp, 1, :estado, :userId, NOW())");
            $stmtIns->execute([
                'resid' => $residencialId, 'titulo' => $titulo, 'mensaje' => $mensaje, 'tipo' => $tipo, 'prioridad' => $prioridad,
                'fecha_pub' => $fecha_pub, 'fecha_exp' => $fecha_exp ?: NULL,
                'estado' => $estado, 'userId' => $adminId
            ]);
            json_out(true, ['message' => 'Comunicado creado.']);
        }
    } catch (PDOException $e) {
        json_out(false, ['error' => 'Error al guardar comunicado.']);
    }
}
?>
