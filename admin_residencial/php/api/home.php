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
require_once __DIR__ . '/../../../config/comunicados_helpers.php';
require_once __DIR__ . '/_operational_bootstrap.php';

require_login();
admin_module_required('home', 'El inicio no está habilitado para este cliente.');

$user = current_user();
$userId = (int)($user['id'] ?? 0);

function admin_home_count(PDO $pdo, string $sql, array $params): int
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)($stmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function admin_home_retailops_dashboard(PDO $pdo, int $residencialId): array
{
    $personasDentro = tableExists($pdo, 'personas_recurrentes')
        ? admin_home_count($pdo, "
            SELECT COUNT(*)
            FROM personas_recurrentes
            WHERE residencial_id = :rid
              AND activo = 1
              AND esta_dentro = 1
        ", ['rid' => $residencialId])
        : 0;

    $visitantesDentro = tableExists($pdo, 'visitantes_rapidos')
        ? admin_home_count($pdo, "
            SELECT COUNT(*)
            FROM visitantes_rapidos
            WHERE residencial_id = :rid
              AND esta_dentro = 1
              AND estado IN ('activo', 'en_curso')
        ", ['rid' => $residencialId])
        : 0;

    $materialesEnProceso = tableExists($pdo, 'permisos_materiales')
        ? admin_home_count($pdo, "
            SELECT COUNT(*)
            FROM permisos_materiales
            WHERE residencial_id = :rid
              AND estado = 'en_proceso'
        ", ['rid' => $residencialId])
        : 0;

    $personalAutorizadoActivo = tableExists($pdo, 'personas_recurrentes')
        ? admin_home_count($pdo, "
            SELECT COUNT(*)
            FROM personas_recurrentes
            WHERE residencial_id = :rid
              AND activo = 1
        ", ['rid' => $residencialId])
        : 0;

    $ingresosDia = tableExists($pdo, 'bitacora_operativa')
        ? admin_home_count($pdo, "
            SELECT COUNT(*)
            FROM bitacora_operativa
            WHERE residencial_id = :rid
              AND DATE(fecha_hora) = CURDATE()
              AND tipo_evento = 'entrada'
              AND resultado = 'permitido'
        ", ['rid' => $residencialId])
        : 0;

    $pasesUsadosHoy = tableExists($pdo, 'bitacora_operativa')
        ? admin_home_count($pdo, "
            SELECT COUNT(DISTINCT visitante_rapido_id)
            FROM bitacora_operativa
            WHERE residencial_id = :rid
              AND visitante_rapido_id IS NOT NULL
              AND DATE(fecha_hora) = CURDATE()
              AND resultado = 'permitido'
        ", ['rid' => $residencialId])
        : 0;

    if ($pasesUsadosHoy === 0 && tableExists($pdo, 'visitantes_rapidos')) {
        $pasesUsadosHoy = admin_home_count($pdo, "
            SELECT COUNT(*)
            FROM visitantes_rapidos
            WHERE residencial_id = :rid
              AND ultimo_evento_at IS NOT NULL
              AND DATE(ultimo_evento_at) = CURDATE()
        ", ['rid' => $residencialId]);
    }

    $incidentesAbiertos = tableExists($pdo, 'incidencias')
        ? admin_home_count($pdo, "
            SELECT COUNT(*)
            FROM incidencias
            WHERE residencial_id = :rid
              AND estado IN ('abierta', 'en_proceso')
        ", ['rid' => $residencialId])
        : 0;

    $materialesAutorizados = tableExists($pdo, 'permisos_materiales')
        ? admin_home_count($pdo, "
            SELECT COUNT(*)
            FROM permisos_materiales
            WHERE residencial_id = :rid
              AND estado IN ('aprobado', 'en_proceso')
        ", ['rid' => $residencialId])
        : 0;

    $herramientasPrestadas = tableExists($pdo, 'prestamos_herramientas')
        ? admin_home_count($pdo, "
            SELECT COUNT(*)
            FROM prestamos_herramientas
            WHERE residencial_id = :rid
              AND estado = 'prestado'
        ", ['rid' => $residencialId])
        : 0;

    $rechazosBitacora = tableExists($pdo, 'bitacora_operativa')
        ? admin_home_count($pdo, "
            SELECT COUNT(*)
            FROM bitacora_operativa
            WHERE residencial_id = :rid
              AND DATE(fecha_hora) = CURDATE()
              AND resultado = 'denegado'
        ", ['rid' => $residencialId])
        : 0;

    $rechazosAccesos = tableExists($pdo, 'accesos_guardia')
        ? admin_home_count($pdo, "
            SELECT COUNT(*)
            FROM accesos_guardia ag
            LEFT JOIN visitas v ON v.id = ag.visita_id
            LEFT JOIN usuarios_residenciales ur ON ur.user_id = ag.guardia_id AND ur.residencial_id = :rid_guardia
            WHERE DATE(ag.fecha_hora) = CURDATE()
              AND ag.resultado = 'denegado'
              AND (v.residencial_id = :rid_visita OR ur.residencial_id = :rid_asignado)
        ", [
            'rid_guardia' => $residencialId,
            'rid_visita' => $residencialId,
            'rid_asignado' => $residencialId,
        ])
        : 0;

    $rondinesPendientes = tableExists($pdo, 'rondines_ejecuciones')
        ? admin_home_count($pdo, "
            SELECT COUNT(*)
            FROM rondines_ejecuciones
            WHERE residencial_id = :rid
              AND estado IN ('en_proceso', 'incompleto')
        ", ['rid' => $residencialId])
        : 0;

    $rondinesCompletadosHoy = tableExists($pdo, 'rondines_ejecuciones')
        ? admin_home_count($pdo, "
            SELECT COUNT(*)
            FROM rondines_ejecuciones
            WHERE residencial_id = :rid
              AND estado = 'completado'
              AND DATE(COALESCE(fin_at, inicio_at, created_at)) = CURDATE()
        ", ['rid' => $residencialId])
        : 0;

    $eventos = [];
    if (tableExists($pdo, 'bitacora_operativa')) {
        try {
            $stmt = $pdo->prepare("
                SELECT
                    b.id,
                    b.tipo_origen,
                    b.tipo_evento,
                    b.resultado,
                    b.observaciones,
                    b.fecha_hora,
                    g.name AS guardia_nombre,
                    p.nombre AS persona_nombre,
                    vr.nombre_visitante,
                    pm.tipo_movimiento AS permiso_tipo_movimiento,
                    a.nombre AS area_nombre
                FROM bitacora_operativa b
                LEFT JOIN users g ON g.id = b.guardia_id
                LEFT JOIN personas_recurrentes p ON p.id = b.persona_recurrente_id
                LEFT JOIN visitantes_rapidos vr ON vr.id = b.visitante_rapido_id
                LEFT JOIN permisos_materiales pm ON pm.id = b.permiso_material_id
                LEFT JOIN areas_operativas a ON a.id = b.area_id
                WHERE b.residencial_id = :rid
                ORDER BY b.fecha_hora DESC, b.id DESC
                LIMIT 8
            ");
            $stmt->execute(['rid' => $residencialId]);
            $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $eventos = [];
        }
    }

    $personasDentroAhora = $personasDentro + $visitantesDentro;

    return [
        'personas_dentro' => $personasDentroAhora,
        'ingresos_dia' => $ingresosDia,
        'salidas_pendientes' => $personasDentroAhora + $materialesEnProceso,
        'pases_usados_hoy' => $pasesUsadosHoy,
        'personal_autorizado_activo' => $personalAutorizadoActivo,
        'incidentes_abiertos' => $incidentesAbiertos,
        'materiales_autorizados' => $materialesAutorizados,
        'herramientas_prestadas' => $herramientasPrestadas,
        'rondines_pendientes' => $rondinesPendientes,
        'rondines_completados_hoy' => $rondinesCompletadosHoy,
        'accesos_rechazados' => $rechazosBitacora + $rechazosAccesos,
        'ultimos_eventos' => $eventos,
    ];
}

try {
    comunicados_schema_ensure($pdo);

    $stmt = $pdo->prepare("
        SELECT residencial_id
        FROM usuarios_residenciales
        WHERE user_id = ?
        ORDER BY es_principal DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $residencialId = (int)$stmt->fetchColumn();

    if (!$residencialId) {
        json_out(false, ['error' => 'No tienes un residencial asignado.']);
    }

    // 1) Preferimos comunicados publicados reales para alimentar el carrusel.
    $banners = [];
    if (tableExists($pdo, 'comunicados_residenciales')) {
        $stmtCom = $pdo->prepare("
            SELECT
                id,
                titulo,
                mensaje,
                imagen_url,
                tipo,
                prioridad,
                fecha_publicacion,
                fecha_expiracion
            FROM comunicados_residenciales
            WHERE residencial_id = :rid
              AND estado = 'publicado'
              AND fecha_publicacion <= CURDATE()
              AND (fecha_expiracion IS NULL OR fecha_expiracion >= CURDATE())
            ORDER BY
              CASE prioridad
                WHEN 'alta' THEN 3
                WHEN 'media' THEN 2
                ELSE 1
              END DESC,
              fecha_publicacion DESC,
              id DESC
            LIMIT 5
        ");
        $stmtCom->execute(['rid' => $residencialId]);
        $comRows = $stmtCom->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($comRows as $row) {
            $raw = (string)($row['mensaje'] ?? '');
            $raw = preg_replace('/\\s+/u', ' ', trim($raw)) ?: '';
            $sub = function_exists('mb_substr') ? mb_substr($raw, 0, 110) : substr($raw, 0, 110);
            if (strlen($raw) > 110) {
                $sub .= '…';
            }
            $banners[] = [
                'id' => (int)($row['id'] ?? 0),
                'titulo' => (string)($row['titulo'] ?? ''),
                'subtitulo' => $sub,
                'imagen_url' => (string)($row['imagen_url'] ?? ''),
                'categoria' => (string)($row['tipo'] ?? 'general'),
                'link_url' => '#comunicados',
                'orden' => 0,
            ];
        }
    }

    $stmtServicios = $pdo->prepare("
        SELECT
            id,
            nombre,
            descripcion,
            imagen_url,
            telefono,
            whatsapp,
            link_url,
            categoria,
            orden
        FROM home_servicios_residenciales
        WHERE residencial_id = :rid
          AND activo = 1
        ORDER BY orden ASC, id DESC
    ");
    $stmtServicios->execute(['rid' => $residencialId]);
    $serviciosResidenciales = $stmtServicios->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $serviciosGlobales = [];
    if (tableExists($pdo, 'home_servicios_globales')) {
        $stmtGlobales = $pdo->query("
            SELECT
                id,
                nombre,
                descripcion,
                imagen_url,
                telefono,
                whatsapp,
                link_url,
                categoria,
                orden
            FROM home_servicios_globales
            WHERE activo = 1
            ORDER BY orden ASC, id DESC
        ");
        $serviciosGlobales = $stmtGlobales->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    $servicios = [];
    foreach ($serviciosGlobales as $item) {
        $item['origen'] = 'global';
        $servicios[] = $item;
    }
    foreach ($serviciosResidenciales as $item) {
        $item['origen'] = 'residencial';
        $servicios[] = $item;
    }

    $presetServicio = (string)($serviceProfile['preset_servicio'] ?? 'residencial');
    $modoOperacion = (string)($operationalMode ?? 'residencial');
    $retailopsDashboard = (operational_is_operational_mode($presetServicio) || operational_is_operational_mode($modoOperacion))
        ? admin_home_retailops_dashboard($pdo, $residencialId)
        : null;

    json_out(true, [
        'banners' => $banners,
        'servicios' => $servicios,
        'retailops_dashboard' => $retailopsDashboard,
    ]);
} catch (Throwable $e) {
    app_json_exception($e, 'No pudimos cargar el inicio del servicio.');
}
