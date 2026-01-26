<?php
// autos_admin.php
// Endpoint para obtener vehículos con información adicional de usuarios.
// Admite listados por residente y listados globales para el residencial.
// Requiere autenticación como administrador residencial.

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';

// Sólo admin residencial puede usar este endpoint
require_login();
require_role(['admin_residencial']);

$user    = current_user();
$uid     = (int)($user['id'] ?? 0);

/**
 * Responde en JSON y termina la ejecución.
 *
 * @param bool  $ok    Indica si la operación tuvo éxito.
 * @param array $extra Datos adicionales a incluir en la respuesta.
 */
function json_out(bool $ok, array $extra = []): void {
    echo json_encode(array_merge(['ok' => $ok], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

// Determinar el residencial asignado al admin
function getResidencialId(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare("SELECT residencial_id FROM usuarios_residenciales WHERE user_id = :uid ORDER BY es_principal DESC LIMIT 1");
    $stmt->execute(['uid' => $userId]);
    return (int)$stmt->fetchColumn();
}

$residencialId = getResidencialId($pdo, $uid);
if (!$residencialId) {
    json_out(false, ['error' => 'No tienes residencial asignado.']);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? 'list_by_resident';

/**
 * Devuelve todos los autos de un residente (sin filtrar por unidad) con datos del usuario y unidad.
 *
 * @param PDO $pdo
 * @param int $residencialId
 * @param int $userId
 * @return array
 */
function getAutosByResident(PDO $pdo, int $residencialId, int $userId): array {
    // Seleccionamos información del auto, usuario y unidad
    $stmt = $pdo->prepare(
        "SELECT a.id AS auto_id, a.placas, a.modelo, a.color, a.unidad_id, a.created_at, a.updated_at,\n                u.id AS user_id, u.name AS user_name, u.email, u.telefono,\n                un.id AS unidad_id_res, un.clave AS unidad_clave\n         FROM autos a\n         LEFT JOIN users u ON u.id = a.propietario_user_id\n         LEFT JOIN unidades un ON un.id = a.unidad_id\n         WHERE a.residencial_id = :rid AND a.propietario_user_id = :uid\n         ORDER BY a.created_at DESC"
    );
    $stmt->execute(['rid' => $residencialId, 'uid' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Devuelve todos los autos de todos los residentes en el residencial, con información de usuario y unidad.
 *
 * @param PDO $pdo
 * @param int $residencialId
 * @return array
 */
function getAllAutosInResidencial(PDO $pdo, int $residencialId): array {
    $stmt = $pdo->prepare(
        "SELECT a.id AS auto_id, a.placas, a.modelo, a.color, a.unidad_id, a.created_at, a.updated_at,\n                a.propietario_user_id AS user_id, u.name AS user_name, u.email, u.telefono,\n                un.id AS unidad_id_res, un.clave AS unidad_clave\n         FROM autos a\n         LEFT JOIN users u ON u.id = a.propietario_user_id\n         LEFT JOIN unidades un ON un.id = a.unidad_id\n         WHERE a.residencial_id = :rid\n         ORDER BY a.propietario_user_id ASC, a.created_at DESC"
    );
    $stmt->execute(['rid' => $residencialId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

try {
    if ($method === 'GET') {
        // Acción: listar autos de un residente
        if ($action === 'list_by_resident') {
            $userId = (int)($_GET['user_id'] ?? 0);
            if ($userId <= 0) {
                json_out(false, ['error' => 'Parámetro user_id requerido.']);
            }
            // Verificar que el usuario pertenezca al mismo residencial. En lugar de contar, obtenemos el residencial del usuario.
            // Comprobar que el usuario pertenece al mismo residencial que el admin.
            // En lugar de obtener sólo la primera fila (que podría apuntar a otro residencial),
            // verificamos que exista al menos una relación con el residencial actual.
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM usuarios_residenciales WHERE user_id = :uid AND residencial_id = :rid"
            );
            $stmt->execute(['uid' => $userId, 'rid' => $residencialId]);
            if ((int)$stmt->fetchColumn() === 0) {
                json_out(false, ['error' => 'El usuario no pertenece a tu residencial.']);
            }
            $autos = getAutosByResident($pdo, $residencialId, $userId);
            json_out(true, ['data' => ['autos' => $autos]]);
        }
        // Acción: listar autos de todos los residentes
        if ($action === 'list_all') {
            $autos = getAllAutosInResidencial($pdo, $residencialId);
            json_out(true, ['data' => ['autos' => $autos]]);
        }
        json_out(false, ['error' => 'Acción GET no soportada.']);
    }
    if ($method === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $placas   = trim($_POST['placas'] ?? '');
        $modelo   = trim($_POST['modelo'] ?? '');
        $color    = trim($_POST['color'] ?? '');
        $userId   = (int)($_POST['user_id'] ?? 0);
        $unidadId = (int)($_POST['unidad_id'] ?? 0);

        if (!$placas || !$userId) {
            json_out(false, ['error' => 'Placas y usuario son obligatorios.']);
        }

        $color = trim($_POST['color'] ?? '');
        $color = ucfirst(strtolower($color));
        $stmt = $pdo->prepare("
          INSERT INTO autos
          (placas, modelo, color, propietario_user_id, unidad_id, residencial_id)
          VALUES
          (:placas, :modelo, :color, :uid, :unidad, :rid)
        ");

        $stmt->execute([
          'placas' => $placas,
          'modelo' => $modelo,
          'color'  => $color,
          'uid'    => $userId,
          'unidad' => $unidadId ?: null,
          'rid'    => $residencialId
        ]);

        json_out(true);
    }
}

    json_out(false, ['error' => 'Método no soportado.']);
    
} catch (Throwable $e) {
    json_out(false, ['error' => 'Error: ' . $e->getMessage()]);
}
