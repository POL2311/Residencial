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

// GET: listar incidencias (posible filtro por estado)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $estadoFilter = $_GET['estado'] ?? null;
    try {
        $sql = "SELECT i.id, i.titulo, i.descripcion, i.tipo, i.prioridad, i.estado, i.created_at,
                       res.name AS residente_nombre, un.clave AS unidad_clave,
                       gur.name AS guardia_nombre, gur.id AS guardia_id
                FROM incidencias i
                JOIN users res ON res.id = i.residente_id
                JOIN unidades un ON un.id = i.unidad_id
                LEFT JOIN users gur ON gur.id = i.guardia_id
                WHERE i.residencial_id = :resid";
        if ($estadoFilter) {
            $sql .= " AND i.estado = :estado";
        }
        $sql .= " ORDER BY CASE i.estado 
                            WHEN 'abierta' THEN 1 
                            WHEN 'en_proceso' THEN 2 
                            WHEN 'cerrada' THEN 3 END,
                         i.updated_at DESC, i.created_at DESC";
        $stmt = $pdo->prepare($sql);
        if ($estadoFilter) {
            $stmt->execute(['resid' => $residencialId, 'estado' => $estadoFilter]);
        } else {
            $stmt->execute(['resid' => $residencialId]);
        }
        $incidencias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        json_out(true, ['incidencias' => $incidencias]);
    } catch (PDOException $e) {
        json_out(false, ['error' => 'Error al obtener incidencias.']);
    }
}
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $incidentId = (int)($_POST['id'] ?? 0);
    if ($incidentId <= 0) json_out(false, ['error' => 'ID inválido.']);

    try {
        $stmt = $pdo->prepare("DELETE FROM incidencias WHERE id = ? AND residencial_id = ?");
        $stmt->execute([$incidentId, $residencialId]);
        json_out(true, ['message' => 'Incidencia eliminada.']);
    } catch (PDOException $e) {
        json_out(false, ['error' => 'Error al eliminar incidencia.']);
    }
}


// POST: crear nueva incidencia o actualizar una existente
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Actualizar incidencia (estado, guardia, prioridad)
    if (isset($_POST['id']) && $_POST['id'] !== '') {
        $incidentId = (int)$_POST['id'];
        $newState = $_POST['estado'] ?? null;
        $assignGuardId = isset($_POST['guardia_id']) && $_POST['guardia_id'] !== '' ? (int)$_POST['guardia_id'] : null;
        $newPriority = $_POST['prioridad'] ?? null;
        try {
            $stmtCheck = $pdo->prepare("SELECT estado, guardia_id, prioridad FROM incidencias WHERE id = ? AND residencial_id = ?");
            $stmtCheck->execute([$incidentId, $residencialId]);
            $current = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if (!$current) {
                json_out(false, ['error' => 'Incidencia no encontrada.']);
            }
            $curEstado = $current['estado'];
            $curGuard = $current['guardia_id'];
            $curPrio = $current['prioridad'];
            $updEstado = $curEstado;
            if ($newState !== null && in_array($newState, ['abierta','en_proceso','cerrada'])) {
                $updEstado = $newState;
            } elseif ($assignGuardId !== null && $curEstado === 'abierta') {
                $updEstado = 'en_proceso';
            }
            if ($assignGuardId === null) {
                $assignGuardId = $curGuard;
            }
            $updPrio = $curPrio;
            if ($newPriority !== null && in_array($newPriority, ['baja','media','alta'])) {
                $updPrio = $newPriority;
            }
            $stmtUpd = $pdo->prepare("UPDATE incidencias 
                                       SET guardia_id = :g, estado = :estado, prioridad = :prio, updated_at = NOW() 
                                       WHERE id = :id AND residencial_id = :resid");
            $stmtUpd->execute(['g' => $assignGuardId, 'estado' => $updEstado, 'prio' => $updPrio, 'id' => $incidentId, 'resid' => $residencialId]);
            json_out(true, ['message' => 'Incidencia actualizada.']);
        } catch (PDOException $e) {
            json_out(false, ['error' => 'Error al actualizar incidencia.']);
        }
    }

    // Registrar nueva incidencia
    $unidadId = (int)($_POST['unidad_id'] ?? 0);
    $tipo = $_POST['tipo'] ?? 'otro';
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $prioridad = $_POST['prioridad'] ?? 'media';
    if ($unidadId <= 0 || $titulo === '' || $descripcion === '') {
        json_out(false, ['error' => 'Unidad, título y descripción son requeridos.']);
    }
    try {
        // Determinar residente asociado a la unidad (titular si existe)
        $stmtRu = $pdo->prepare("SELECT ru.user_id 
                                 FROM residentes_unidades ru 
                                 JOIN unidades un ON un.id = ru.unidad_id 
                                 WHERE ru.unidad_id = ? AND un.residencial_id = ? AND ru.activo = 1 
                                 ORDER BY ru.es_titular DESC 
                                 LIMIT 1");
        $stmtRu->execute([$unidadId, $residencialId]);
        $residenteId = (int)$stmtRu->fetchColumn();
        if (!$residenteId) {
            json_out(false, ['error' => 'No hay un residente asignado a esa unidad.']);
        }
        $stmtIns = $pdo->prepare("INSERT INTO incidencias 
                                  (residencial_id, unidad_id, residente_id, guardia_id, tipo, titulo, descripcion, prioridad, estado, created_at, updated_at) 
                                  VALUES (:resid, :unidad, :residente, NULL, :tipo, :titulo, :descripcion, :prioridad, 'abierta', NOW(), NOW())");
        $stmtIns->execute([
            'resid' => $residencialId,
            'unidad' => $unidadId,
            'residente' => $residenteId,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'prioridad' => $prioridad
        ]);
        json_out(true, ['message' => 'Incidencia registrada exitosamente.']);
    } catch (PDOException $e) {
        json_out(false, ['error' => 'Error al registrar incidencia.']);
    }
}
?>
