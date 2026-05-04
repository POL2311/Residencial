<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../config/operational_mode.php';
require_once __DIR__ . '/../../../config/service_profile.php';

$action = sa_post_action('list');

function sa_residencial_status_badge(string $status): string
{
    return match ($status) {
        'activo' => 'Activo',
        'prueba' => 'Prueba',
        'suspendido' => 'Suspendido',
        'cancelado' => 'Cancelado',
        default => $status !== '' ? ucfirst($status) : '—',
    };
}

function sa_service_operator_meta(array $profile, array $operator): array
{
    $status = service_profile_operator_status_for_service(
        $profile,
        (string)($operator['rol_nombre'] ?? ''),
        (int)($operator['is_active'] ?? 0) === 1
    );

    return [
        'id' => (int)($operator['id'] ?? 0),
        'user_id' => (int)($operator['user_id'] ?? 0),
        'name' => (string)($operator['name'] ?? ''),
        'email' => (string)($operator['email'] ?? ''),
        'is_active' => (int)($operator['is_active'] ?? 0),
        'rol_nombre' => (string)($operator['rol_nombre'] ?? ''),
        'rol_key' => service_profile_normalize_role_name((string)($operator['rol_nombre'] ?? '')),
        'es_principal' => (int)($operator['es_principal'] ?? 0),
        'created_at' => (string)($operator['created_at'] ?? ''),
        'operator_status' => $status,
    ];
}

function sa_service_operator_summary(array $operators): array
{
    $summary = [
        'total' => 0,
        'active' => 0,
        'ready' => 0,
        'inactive' => 0,
        'pending' => 0,
    ];

    foreach ($operators as $operator) {
        $summary['total']++;
        if ((int)($operator['is_active'] ?? 0) === 1) {
            $summary['active']++;
        }
        $code = (string)($operator['operator_status']['code'] ?? '');
        if ($code === 'listo') {
            $summary['ready']++;
            continue;
        }
        if ($code === 'inactivo') {
            $summary['inactive']++;
            continue;
        }
        if ($code === 'pendiente') {
            $summary['pending']++;
        }
    }

    return $summary;
}

function sa_fetch_service_operators(PDO $pdo, int $residencialId, array $profile): array
{
    $stmtOperators = $pdo->prepare("
        SELECT ur.id,
               ur.es_principal,
               ur.created_at,
               u.id AS user_id,
               u.name,
               u.email,
               u.is_active,
               t.nombre AS rol_nombre
        FROM usuarios_residenciales ur
        JOIN users u ON u.id = ur.user_id
        JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
        WHERE ur.residencial_id = :id
        ORDER BY ur.es_principal DESC, u.is_active DESC, u.name ASC
    ");
    $stmtOperators->execute(['id' => $residencialId]);
    $operators = $stmtOperators->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return array_map(static fn(array $operator): array => sa_service_operator_meta($profile, $operator), $operators);
}

try {
    operational_schema_ensure($pdo);
    service_profile_schema_ensure($pdo);

    if ($action === 'meta') {
        $planes = $pdo->query("
            SELECT id, nombre, codigo
            FROM planes
            WHERE activo = 1
            ORDER BY precio_mensual ASC, nombre ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        sa_json_out(true, [
            'data' => [
                'planes' => $planes,
                'service_labels' => service_profile_labels(),
                'service_matrix' => service_profile_frontend_matrix(),
                'service_presets' => array_map(static function (string $preset): array {
                    $defaults = service_profile_defaults($preset);
                    return [
                        'key' => $preset,
                        'label' => ucfirst($preset),
                        'defaults' => $defaults,
                    ];
                }, service_profile_allowed_presets()),
                'csrf_token' => sa_csrf_token(),
            ],
        ]);
    }

    if ($action === 'list') {
        $q = sa_clean_str($_POST['q'] ?? $_GET['q'] ?? '', 120);
        $planId = (string)($_POST['plan_id'] ?? $_GET['plan_id'] ?? '');
        $estatus = sa_clean_str($_POST['estatus_plan'] ?? $_GET['estatus_plan'] ?? '', 30);
        $modoOperacion = operational_normalize_mode((string)($_POST['modo_operacion'] ?? $_GET['modo_operacion'] ?? ''));
        $modoRaw = trim((string)($_POST['modo_operacion'] ?? $_GET['modo_operacion'] ?? ''));

        $sql = "
            SELECT r.*, p.nombre AS nombre_plan, p.codigo AS codigo_plan, sc.preset_servicio,
                   (
                     SELECT COUNT(*)
                     FROM usuarios_residenciales ur
                     WHERE ur.residencial_id = r.id
                   ) AS usuarios_asignados,
                   (
                     SELECT COUNT(*)
                     FROM usuarios_residenciales ur
                     JOIN users u ON u.id = ur.user_id
                     WHERE ur.residencial_id = r.id
                       AND u.is_active = 1
                   ) AS usuarios_activos_asignados
            FROM residenciales r
            LEFT JOIN planes p ON p.id = r.plan_id
            LEFT JOIN residenciales_servicio_config sc ON sc.residencial_id = r.id
            WHERE 1 = 1
        ";
        $params = [];

        if ($q !== '') {
            $sql .= " AND (r.nombre LIKE :q OR r.codigo LIKE :q OR r.ciudad LIKE :q OR r.estado LIKE :q OR r.pais LIKE :q)";
            $params['q'] = '%' . $q . '%';
        }

        if ($planId !== '') {
            $sql .= " AND r.plan_id = :plan_id";
            $params['plan_id'] = (int)$planId;
        }

        if ($estatus !== '') {
            $sql .= " AND r.estatus_plan = :estatus_plan";
            $params['estatus_plan'] = $estatus;
        }

        if ($modoRaw !== '') {
            $sql .= " AND r.modo_operacion = :modo_operacion";
            $params['modo_operacion'] = $modoOperacion;
        }

        $sql .= " ORDER BY r.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $summary = [
            'total' => count($items),
            'activos' => 0,
            'prueba' => 0,
            'con_plan' => 0,
        ];

        foreach ($items as $item) {
            if ((string)($item['estatus_plan'] ?? '') === 'activo') $summary['activos']++;
            if ((string)($item['estatus_plan'] ?? '') === 'prueba') $summary['prueba']++;
            if (!empty($item['plan_id'])) $summary['con_plan']++;
        }

        sa_json_out(true, [
            'data' => [
                'items' => array_map(static function (array $item) use ($pdo): array {
                    $item['modo_operacion'] = operational_normalize_mode((string)($item['modo_operacion'] ?? 'residencial'));
                    $profile = service_profile_get($pdo, (int)$item['id']);
                    $operators = sa_fetch_service_operators($pdo, (int)$item['id'], $profile);
                    $item['service_profile'] = service_profile_frontend_payload($pdo, (int)$item['id'], 'admin_residencial');
                    $item['preset_servicio'] = $profile['preset_servicio'];
                    $item['estatus_label'] = sa_residencial_status_badge((string)($item['estatus_plan'] ?? ''));
                    $item['operators_summary'] = sa_service_operator_summary($operators);
                    return $item;
                }, $items),
                'summary' => $summary,
            ],
        ]);
    }

    if ($action === 'get_service_profile') {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        if ($id <= 0) {
            sa_json_out(false, ['error' => 'Cliente inválido.'], 422);
        }

        $stmt = $pdo->prepare("SELECT id, nombre, modo_operacion FROM residenciales WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $residencial = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$residencial) {
            sa_json_out(false, ['error' => 'Cliente no encontrado.'], 404);
        }

        $profile = service_profile_get($pdo, $id);
        $operators = sa_fetch_service_operators($pdo, $id, $profile);
        $operatorsSummary = sa_service_operator_summary($operators);

        sa_json_out(true, [
            'data' => [
                'item' => [
                    'id' => (int)$residencial['id'],
                    'nombre' => (string)$residencial['nombre'],
                    'modo_operacion' => operational_normalize_mode((string)$residencial['modo_operacion']),
                    'service_profile' => service_profile_frontend_payload($pdo, (int)$residencial['id'], 'admin_residencial'),
                    'operators' => $operators,
                    'operators_summary' => $operatorsSummary,
                ],
            ],
        ]);
    }

    if ($action === 'update_service_profile') {
        sa_require_csrf();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            sa_json_out(false, ['error' => 'Cliente inválido.'], 422);
        }

        $stmt = $pdo->prepare("SELECT id FROM residenciales WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            sa_json_out(false, ['error' => 'Cliente no encontrado.'], 404);
        }

        $input = [
            'preset_servicio' => service_profile_normalize_preset((string)($_POST['preset_servicio'] ?? 'residencial')),
        ];
        foreach (service_profile_all_flags() as $flag) {
            $input[$flag] = isset($_POST[$flag]) && (string)$_POST[$flag] === '1' ? 1 : 0;
        }

        $stmtUpdateMode = $pdo->prepare("
            UPDATE residenciales
            SET modo_operacion = :modo_operacion,
                updated_at = NOW()
            WHERE id = :id
            LIMIT 1
        ");
        $stmtUpdateMode->execute([
            'modo_operacion' => $input['preset_servicio'],
            'id' => $id,
        ]);

        $saved = service_profile_save($pdo, $id, $input);

        sa_json_out(true, [
            'message' => 'Perfil de servicio actualizado correctamente.',
            'data' => [
                'service_profile' => service_profile_frontend_payload($pdo, $id, 'admin_residencial'),
                'preset_servicio' => $saved['preset_servicio'],
            ],
        ]);
    }

    if ($action === 'disable_service') {
        sa_require_csrf();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            sa_json_out(false, ['error' => 'Servicio inválido.'], 422);
        }

        $stmt = $pdo->prepare("
            SELECT id, nombre, activo, estatus_plan
            FROM residenciales
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            sa_json_out(false, ['error' => 'Servicio no encontrado.'], 404);
        }

        if ((int)($item['activo'] ?? 0) === 0 && in_array((string)($item['estatus_plan'] ?? ''), ['suspendido', 'cancelado'], true)) {
            sa_json_out(false, ['error' => 'Ese servicio ya se encuentra desactivado.'], 422);
        }

        $disable = $pdo->prepare("
            UPDATE residenciales
            SET activo = 0,
                estatus_plan = 'suspendido',
                updated_at = NOW()
            WHERE id = :id
            LIMIT 1
        ");
        $disable->execute(['id' => $id]);

        sa_json_out(true, [
            'message' => 'Servicio desactivado correctamente. Se conservó su historial y relaciones.',
            'data' => [
                'item' => [
                    'id' => (int)$item['id'],
                    'nombre' => (string)$item['nombre'],
                    'activo' => 0,
                    'estatus_plan' => 'suspendido',
                    'estatus_label' => sa_residencial_status_badge('suspendido'),
                ],
            ],
        ]);
    }

    if ($action === 'create') {
        sa_require_csrf();

        $form = [
            'nombre' => sa_clean_str($_POST['nombre'] ?? '', 150),
            'codigo' => sa_clean_str($_POST['codigo'] ?? '', 50),
            'tipo' => sa_clean_str($_POST['tipo'] ?? 'fraccionamiento', 40),
            'modo_operacion' => operational_normalize_mode((string)($_POST['modo_operacion'] ?? 'residencial')),
            'max_casas' => (int)($_POST['max_casas'] ?? 0),
            'max_guardias' => (int)($_POST['max_guardias'] ?? 0),
            'pais' => sa_clean_str($_POST['pais'] ?? 'México', 80),
            'estado' => sa_clean_str($_POST['estado'] ?? '', 100),
            'ciudad' => sa_clean_str($_POST['ciudad'] ?? '', 100),
            'colonia' => sa_clean_str($_POST['colonia'] ?? '', 150),
            'calle' => sa_clean_str($_POST['calle'] ?? '', 150),
            'numero_exterior' => sa_clean_str($_POST['numero_exterior'] ?? '', 20),
            'numero_interior' => sa_clean_str($_POST['numero_interior'] ?? '', 20),
            'codigo_postal' => sa_clean_str($_POST['codigo_postal'] ?? '', 10),
            'nombre_contacto' => sa_clean_str($_POST['nombre_contacto'] ?? '', 150),
            'telefono_contacto' => sa_clean_str($_POST['telefono_contacto'] ?? '', 30),
            'email_contacto' => sa_clean_str($_POST['email_contacto'] ?? '', 150),
            'plan_id' => (string)($_POST['plan_id'] ?? ''),
            'fecha_inicio_plan' => sa_clean_str($_POST['fecha_inicio_plan'] ?? '', 20),
            'fecha_fin_plan' => sa_clean_str($_POST['fecha_fin_plan'] ?? '', 20),
            'estatus_plan' => sa_clean_str($_POST['estatus_plan'] ?? 'activo', 20),
            'zona_horaria' => sa_clean_str($_POST['zona_horaria'] ?? 'America/Mexico_City', 50),
            'permite_qr' => isset($_POST['permite_qr']) && (string)$_POST['permite_qr'] === '1' ? 1 : 0,
            'permite_trabajadores_recurrentes' => isset($_POST['permite_trabajadores_recurrentes']) && (string)$_POST['permite_trabajadores_recurrentes'] === '1' ? 1 : 0,
            'requiere_placa_vehiculo' => isset($_POST['requiere_placa_vehiculo']) && (string)$_POST['requiere_placa_vehiculo'] === '1' ? 1 : 0,
            'requiere_identificacion_visita' => isset($_POST['requiere_identificacion_visita']) && (string)$_POST['requiere_identificacion_visita'] === '1' ? 1 : 0,
        ];

        $errors = [];
        foreach (['nombre', 'codigo', 'pais', 'estado', 'ciudad', 'colonia', 'calle', 'numero_exterior', 'codigo_postal', 'nombre_contacto', 'telefono_contacto', 'email_contacto'] as $field) {
            if ($form[$field] === '') {
                $errors[] = 'El campo ' . str_replace('_', ' ', $field) . ' es obligatorio.';
            }
        }
        if ($form['max_casas'] < 0) $errors[] = 'El máximo de casas no puede ser negativo.';
        if ($form['max_guardias'] < 0) $errors[] = 'El máximo de guardias no puede ser negativo.';
        if ($form['email_contacto'] !== '' && !filter_var($form['email_contacto'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El email de contacto no tiene un formato válido.';
        }
        if (!in_array($form['estatus_plan'], ['prueba', 'activo', 'suspendido', 'cancelado'], true)) {
            $errors[] = 'El estatus del plan no es válido.';
        }
        if ($form['fecha_inicio_plan'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $form['fecha_inicio_plan'])) {
            $errors[] = 'La fecha de inicio del plan no es válida.';
        }
        if ($form['fecha_fin_plan'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $form['fecha_fin_plan'])) {
            $errors[] = 'La fecha de fin del plan no es válida.';
        }
        if ($form['fecha_inicio_plan'] !== '' && $form['fecha_fin_plan'] !== '' && $form['fecha_fin_plan'] < $form['fecha_inicio_plan']) {
            $errors[] = 'La fecha de fin del plan no puede ser anterior a la fecha de inicio.';
        }

        if ($errors) {
            sa_json_out(false, ['error' => implode(' ', $errors)], 422);
        }

        $stmt = $pdo->prepare("
            INSERT INTO residenciales (
                nombre, codigo, tipo, max_casas, max_guardias,
                modo_operacion,
                pais, estado, ciudad, colonia, calle,
                numero_exterior, numero_interior, codigo_postal,
                nombre_contacto, telefono_contacto, email_contacto,
                plan_id, fecha_inicio_plan, fecha_fin_plan, estatus_plan,
                zona_horaria, permite_qr, permite_trabajadores_recurrentes,
                requiere_placa_vehiculo, requiere_identificacion_visita, activo
            ) VALUES (
                :nombre, :codigo, :tipo, :max_casas, :max_guardias,
                :modo_operacion,
                :pais, :estado, :ciudad, :colonia, :calle,
                :numero_exterior, :numero_interior, :codigo_postal,
                :nombre_contacto, :telefono_contacto, :email_contacto,
                :plan_id, :fecha_inicio_plan, :fecha_fin_plan, :estatus_plan,
                :zona_horaria, :permite_qr, :permite_trabajadores_recurrentes,
                :requiere_placa_vehiculo, :requiere_identificacion_visita, 1
            )
        ");
        $stmt->execute([
            'nombre' => $form['nombre'],
            'codigo' => $form['codigo'],
            'tipo' => $form['tipo'],
            'max_casas' => $form['max_casas'],
            'max_guardias' => $form['max_guardias'],
            'modo_operacion' => $form['modo_operacion'],
            'pais' => $form['pais'],
            'estado' => $form['estado'],
            'ciudad' => $form['ciudad'],
            'colonia' => $form['colonia'],
            'calle' => $form['calle'],
            'numero_exterior' => $form['numero_exterior'],
            'numero_interior' => ($form['numero_interior'] !== '' ? $form['numero_interior'] : null),
            'codigo_postal' => $form['codigo_postal'],
            'nombre_contacto' => $form['nombre_contacto'],
            'telefono_contacto' => $form['telefono_contacto'],
            'email_contacto' => $form['email_contacto'],
            'plan_id' => ($form['plan_id'] !== '' ? (int)$form['plan_id'] : null),
            'fecha_inicio_plan' => ($form['fecha_inicio_plan'] !== '' ? $form['fecha_inicio_plan'] : null),
            'fecha_fin_plan' => ($form['fecha_fin_plan'] !== '' ? $form['fecha_fin_plan'] : null),
            'estatus_plan' => $form['estatus_plan'],
            'zona_horaria' => $form['zona_horaria'],
            'permite_qr' => $form['permite_qr'],
            'permite_trabajadores_recurrentes' => $form['permite_trabajadores_recurrentes'],
            'requiere_placa_vehiculo' => $form['requiere_placa_vehiculo'],
            'requiere_identificacion_visita' => $form['requiere_identificacion_visita'],
        ]);

        $residencialId = (int)$pdo->lastInsertId();
        $serviceInput = [
            'preset_servicio' => service_profile_normalize_preset((string)($_POST['preset_servicio'] ?? $form['modo_operacion'])),
        ];
        if ($serviceInput['preset_servicio'] !== $form['modo_operacion']) {
            $pdo->prepare("UPDATE residenciales SET modo_operacion = :modo WHERE id = :id LIMIT 1")
                ->execute([
                    'modo' => $serviceInput['preset_servicio'],
                    'id' => $residencialId,
                ]);
        }
        foreach (service_profile_all_flags() as $flag) {
            $serviceInput[$flag] = isset($_POST[$flag]) && (string)$_POST[$flag] === '1' ? 1 : 0;
        }
        service_profile_seed_defaults($pdo, $residencialId, [
            'modo_operacion' => $form['modo_operacion'],
            'permite_qr' => $form['permite_qr'],
            'permite_trabajadores_recurrentes' => $form['permite_trabajadores_recurrentes'],
        ]);
        service_profile_save($pdo, $residencialId, $serviceInput);

        sa_json_out(true, [
            'message' => 'Servicio creado correctamente.',
            'data' => [
                'item' => [
                    'id' => $residencialId,
                    'nombre' => $form['nombre'],
                    'preset_servicio' => $serviceInput['preset_servicio'],
                ],
            ],
        ]);
    }

    sa_json_out(false, ['error' => 'Acción no soportada.'], 400);
} catch (Throwable $e) {
    app_json_exception($e, 'No pudimos procesar la información de residenciales.');
}
