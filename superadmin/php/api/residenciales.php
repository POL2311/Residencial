<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

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

try {
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
                'csrf_token' => sa_csrf_token(),
            ],
        ]);
    }

    if ($action === 'list') {
        $q = sa_clean_str($_POST['q'] ?? $_GET['q'] ?? '', 120);
        $planId = (string)($_POST['plan_id'] ?? $_GET['plan_id'] ?? '');
        $estatus = sa_clean_str($_POST['estatus_plan'] ?? $_GET['estatus_plan'] ?? '', 30);

        $sql = "
            SELECT r.*, p.nombre AS nombre_plan, p.codigo AS codigo_plan
            FROM residenciales r
            LEFT JOIN planes p ON p.id = r.plan_id
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
                'items' => array_map(static function (array $item): array {
                    $item['estatus_label'] = sa_residencial_status_badge((string)($item['estatus_plan'] ?? ''));
                    return $item;
                }, $items),
                'summary' => $summary,
            ],
        ]);
    }

    if ($action === 'create') {
        sa_require_csrf();

        $form = [
            'nombre' => sa_clean_str($_POST['nombre'] ?? '', 150),
            'codigo' => sa_clean_str($_POST['codigo'] ?? '', 50),
            'tipo' => sa_clean_str($_POST['tipo'] ?? 'fraccionamiento', 40),
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
                pais, estado, ciudad, colonia, calle,
                numero_exterior, numero_interior, codigo_postal,
                nombre_contacto, telefono_contacto, email_contacto,
                plan_id, fecha_inicio_plan, fecha_fin_plan, estatus_plan,
                zona_horaria, permite_qr, permite_trabajadores_recurrentes,
                requiere_placa_vehiculo, requiere_identificacion_visita, activo
            ) VALUES (
                :nombre, :codigo, :tipo, :max_casas, :max_guardias,
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

        sa_json_out(true, ['message' => 'Residencial creado correctamente.']);
    }

    sa_json_out(false, ['error' => 'Acción no soportada.'], 400);
} catch (Throwable $e) {
    app_json_exception($e, 'No pudimos procesar la información de residenciales.');
}
