<?php
// admin_residencial/residentes.php
require_once __DIR__ . '/../config/auth.php';
require_role(['admin_residencial']);
require_once __DIR__ . '/../config/config.php';

$user       = current_user();
$pageTitle  = 'Residentes';
$activeMenu = 'residentes';

$success = '';
$error   = '';

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function phone_digits($raw) {
    $digits = preg_replace('/\D+/', '', (string)$raw);
    return $digits ?: '';
}

function phone_wa_mx($digits) {
    // WhatsApp en MX: si son 10 dígitos, anteponemos 52
    if ($digits && strlen($digits) === 10) return '52' . $digits;
    return $digits;
}

// ------------------------------------------------------
// 1) Obtener residencial principal del admin
// ------------------------------------------------------
try {
    $stmtR = $pdo->prepare("
        SELECT r.*
        FROM usuarios_residenciales ur
        JOIN residenciales r ON r.id = ur.residencial_id
        WHERE ur.user_id = :user_id
        ORDER BY ur.es_principal DESC, ur.created_at ASC
        LIMIT 1
    ");
    $stmtR->execute(['user_id' => $user['id']]);
    $residencial = $stmtR->fetch();
} catch (PDOException $e) {
    $error = 'Error al obtener el residencial: ' . $e->getMessage();
}

if (!$residencial) {
    ob_start(); ?>
    <div class="text-sm text-slate-200">
        No tienes residencial asignado. Pide al super admin que te asigne uno.
    </div>
    <?php
    $content = ob_get_clean();
    include __DIR__ . '/../layouts/dashboard_layout.php';
    exit;
}

$residencialId = (int)$residencial['id'];

// ------------------------------------------------------
// 2) Unidades (para selects)
// ------------------------------------------------------
$unidades = [];
try {
    $stmtU = $pdo->prepare("
        SELECT id, clave, tipo, calle, numero_exterior, numero_interior, torre, nivel
        FROM unidades
        WHERE residencial_id = :residencial_id AND activo = 1
        ORDER BY clave ASC
    ");
    $stmtU->execute(['residencial_id' => $residencialId]);
    $unidades = $stmtU->fetchAll();
} catch (PDOException $e) {
    $error = 'Error al obtener las unidades: ' . $e->getMessage();
}

// Estado del modal “Nuevo residente”
$showModalResidente = isset($_GET['modal']) && $_GET['modal'] === 'new';
$formResidente = [
    'nombre'     => '',
    'email'      => '',
    'telefono'   => '',
    'unidad_id'  => '',
    'es_titular' => 1,
    'es_activo'  => 1,
];

// ------------------------------------------------------
// 3) Detectar si existe columna autos.unidad_id (compatibilidad)
// ------------------------------------------------------
$autosTieneUnidadId = false;
try {
    $stmtCol = $pdo->prepare("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'autos'
          AND COLUMN_NAME = 'unidad_id'
    ");
    $stmtCol->execute();
    $autosTieneUnidadId = ((int)$stmtCol->fetchColumn() > 0);
} catch (Throwable $e) {
    $autosTieneUnidadId = false;
}

// Helper: validar que un residente pertenece a este residencial
function residente_pertenece($pdo, $residencialId, $userId) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM usuarios_residenciales ur
        JOIN users u ON u.id = ur.user_id
        JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
        WHERE ur.residencial_id = :rid
          AND ur.user_id = :uid
          AND t.nombre = 'residente'
    ");
    $stmt->execute(['rid' => $residencialId, 'uid' => $userId]);
    return ((int)$stmt->fetchColumn() > 0);
}

// ------------------------------------------------------
// 4) Acciones POST (estado, crear residente, pagos, autos CRUD)
// ------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // 4.a) Cambiar estado (activar/desactivar servicio)
    if ($accion === 'cambiar_estado_residente') {
        $residente_id = (int)($_POST['residente_id'] ?? 0);
        $nuevo_estado = (int)($_POST['nuevo_estado'] ?? 0);

        if ($residente_id <= 0 || !in_array($nuevo_estado, [0, 1], true)) {
            $error = 'Datos de cambio de estado inválidos.';
        } else {
            try {
                if (!residente_pertenece($pdo, $residencialId, $residente_id)) {
                    $error = 'No autorizado para modificar este residente.';
                } else {
                    $pdo->beginTransaction();

                    $stmtUpd = $pdo->prepare("UPDATE users SET is_active = :activo WHERE id = :id");
                    $stmtUpd->execute(['activo' => $nuevo_estado, 'id' => $residente_id]);

                    $stmtUpdRU = $pdo->prepare("UPDATE residentes_unidades SET activo = :activo WHERE user_id = :id");
                    $stmtUpdRU->execute(['activo' => $nuevo_estado, 'id' => $residente_id]);

                    $pdo->commit();

                    $success = $nuevo_estado === 1
                        ? 'Servicio activado correctamente.'
                        : 'Servicio desactivado por falta de pago.';
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Error al cambiar el estado del residente: ' . $e->getMessage();
            }
        }
    }

    // 4.b) Crear residente
    elseif ($accion === 'crear_residente') {
        $nombre      = trim($_POST['nombre'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $telefono    = trim($_POST['telefono'] ?? '');
        $password    = $_POST['password'] ?? '';
        $password2   = $_POST['password_confirm'] ?? '';
        $unidad_id   = (int)($_POST['unidad_id'] ?? 0);
        $es_titular  = isset($_POST['es_titular']) ? 1 : 0;
        $es_activo   = isset($_POST['es_activo']) ? 1 : 0;

        $formResidente['nombre']     = $nombre;
        $formResidente['email']      = $email;
        $formResidente['telefono']   = $telefono;
        $formResidente['unidad_id']  = $unidad_id;
        $formResidente['es_titular'] = $es_titular;
        $formResidente['es_activo']  = $es_activo;

        $errores = [];
        if ($nombre === '')  $errores[] = 'El nombre del residente es obligatorio.';
        if ($email === '')   $errores[] = 'El correo electrónico es obligatorio.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = 'El email no tiene un formato válido.';
        if ($password === '' || $password2 === '') $errores[] = 'La contraseña y su confirmación son obligatorias.';
        elseif ($password !== $password2) $errores[] = 'Las contraseñas no coinciden.';
        elseif (strlen($password) < 6) $errores[] = 'La contraseña debe tener al menos 6 caracteres.';
        if ($unidad_id <= 0) $errores[] = 'Debes seleccionar una unidad para el residente.';

        if ($unidad_id > 0) {
            $stmtCheckU = $pdo->prepare("
                SELECT COUNT(*)
                FROM unidades
                WHERE id = :unidad_id AND residencial_id = :residencial_id
            ");
            $stmtCheckU->execute(['unidad_id' => $unidad_id, 'residencial_id' => $residencialId]);
            if ((int)$stmtCheckU->fetchColumn() === 0) $errores[] = 'La unidad seleccionada no pertenece a tu residencial.';
        }

        if (empty($errores)) {
            $stmtCheckEmail = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $stmtCheckEmail->execute(['email' => $email]);
            if ($stmtCheckEmail->fetch()) $errores[] = 'Ya existe un usuario con ese correo.';
        }

        if (!empty($errores)) {
            $error = implode(' ', $errores);
            $showModalResidente = true;
        } else {
            try {
                $pdo->beginTransaction();

                $stmtTipo = $pdo->prepare("SELECT id FROM tipos_usuario WHERE nombre = 'residente' LIMIT 1");
                $stmtTipo->execute();
                $tipo = $stmtTipo->fetch();
                if (!$tipo) throw new Exception("No existe el tipo de usuario 'residente'.");

                $hash = password_hash($password, PASSWORD_DEFAULT);

                $stmtUser = $pdo->prepare("
                    INSERT INTO users (tipo_usuario_id, name, email, telefono, password_hash, is_active)
                    VALUES (:tipo_id, :name, :email, :telefono, :hash, :active)
                ");
                $stmtUser->execute([
                    'tipo_id'  => $tipo['id'],
                    'name'     => $nombre,
                    'email'    => $email,
                    'telefono' => $telefono !== '' ? $telefono : null,
                    'hash'     => $hash,
                    'active'   => $es_activo,
                ]);
                $user_id_residente = (int)$pdo->lastInsertId();

                $stmtUR = $pdo->prepare("
                    INSERT INTO usuarios_residenciales (user_id, residencial_id, es_principal)
                    VALUES (:user_id, :residencial_id, 1)
                ");
                $stmtUR->execute([
                    'user_id'        => $user_id_residente,
                    'residencial_id' => $residencialId,
                ]);

                $stmtRU = $pdo->prepare("
                    INSERT INTO residentes_unidades (user_id, unidad_id, es_titular, activo)
                    VALUES (:user_id, :unidad_id, :es_titular, :activo)
                ");
                $stmtRU->execute([
                    'user_id'    => $user_id_residente,
                    'unidad_id'  => $unidad_id,
                    'es_titular' => $es_titular,
                    'activo'     => $es_activo,
                ]);

                $pdo->commit();
                header('Location: residentes.php?ok=1');
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Error al crear el residente: ' . $e->getMessage();
                $showModalResidente = true;
            }
        }
    }

    // 4.c) Registrar pago
    elseif ($accion === 'registrar_pago_residente') {
        $residenteIdPago = (int)($_POST['residente_id'] ?? 0);
        $monto           = (float)($_POST['monto'] ?? 0);
        $fechaPago       = $_POST['fecha'] ?? '';
        $metodo          = trim($_POST['metodo'] ?? 'efectivo');
        $concepto        = trim($_POST['concepto'] ?? '');

        if ($residenteIdPago <= 0 || $monto <= 0 || $fechaPago === '') {
            $error = 'Debe indicar residente, monto y fecha del pago.';
        } else {
            try {
                if (!residente_pertenece($pdo, $residencialId, $residenteIdPago)) {
                    $error = 'No autorizado para registrar pagos a este residente.';
                } else {
                    $stmtPago = $pdo->prepare("
                        INSERT INTO pagos_residentes (residencial_id, user_id, monto, fecha_pago, metodo, concepto)
                        VALUES (:residencial_id, :user_id, :monto, :fecha_pago, :metodo, :concepto)
                    ");
                    $stmtPago->execute([
                        'residencial_id' => $residencialId,
                        'user_id'        => $residenteIdPago,
                        'monto'          => $monto,
                        'fecha_pago'     => $fechaPago,
                        'metodo'         => $metodo,
                        'concepto'       => ($concepto !== '' ? $concepto : null),
                    ]);

                    header('Location: residentes.php?detalle=' . $residenteIdPago . '&ok_pago=1');
                    exit;
                }
            } catch (PDOException $e) {
                $error = 'Error al registrar el pago: ' . $e->getMessage();
            }
        }
    }

    // 4.d) Crear auto
    elseif ($accion === 'crear_auto_residente') {
        $residenteId = (int)($_POST['residente_id'] ?? 0);
        $placas      = strtoupper(trim($_POST['placas'] ?? ''));
        $modelo      = trim($_POST['modelo'] ?? '');
        $color       = trim($_POST['color'] ?? '');
        $unidadAuto  = (int)($_POST['unidad_id'] ?? 0);

        if ($residenteId <= 0 || $placas === '') {
            $error = 'Debe indicar placas y residente.';
        } else {
            try {
                if (!residente_pertenece($pdo, $residencialId, $residenteId)) {
                    $error = 'No autorizado para agregar autos a este residente.';
                } else {
                    if ($autosTieneUnidadId) {
                        $stmtIns = $pdo->prepare("
                            INSERT INTO autos (residencial_id, propietario_user_id, unidad_id, placas, modelo, color)
                            VALUES (:rid, :uid, :unidad_id, :placas, :modelo, :color)
                        ");
                        $stmtIns->execute([
                            'rid'       => $residencialId,
                            'uid'       => $residenteId,
                            'unidad_id' => ($unidadAuto > 0 ? $unidadAuto : null),
                            'placas'    => $placas,
                            'modelo'    => ($modelo !== '' ? $modelo : null),
                            'color'     => ($color !== '' ? $color : null),
                        ]);
                    } else {
                        $stmtIns = $pdo->prepare("
                            INSERT INTO autos (residencial_id, propietario_user_id, placas, modelo, color)
                            VALUES (:rid, :uid, :placas, :modelo, :color)
                        ");
                        $stmtIns->execute([
                            'rid'    => $residencialId,
                            'uid'    => $residenteId,
                            'placas' => $placas,
                            'modelo' => ($modelo !== '' ? $modelo : null),
                            'color'  => ($color !== '' ? $color : null),
                        ]);
                    }

                    header('Location: residentes.php?detalle=' . $residenteId . '&ok_auto=1');
                    exit;
                }
            } catch (PDOException $e) {
                $error = 'Error al crear el auto: ' . $e->getMessage();
            }
        }
    }

    // 4.e) Editar auto
    elseif ($accion === 'editar_auto_residente') {
        $residenteId = (int)($_POST['residente_id'] ?? 0);
        $autoId      = (int)($_POST['auto_id'] ?? 0);
        $placas      = strtoupper(trim($_POST['placas'] ?? ''));
        $modelo      = trim($_POST['modelo'] ?? '');
        $color       = trim($_POST['color'] ?? '');
        $unidadAuto  = (int)($_POST['unidad_id'] ?? 0);

        if ($residenteId <= 0 || $autoId <= 0 || $placas === '') {
            $error = 'Datos inválidos para editar auto.';
        } else {
            try {
                $stmtCheck = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM autos
                    WHERE id = :auto_id
                      AND residencial_id = :rid
                      AND propietario_user_id = :uid
                ");
                $stmtCheck->execute([
                    'auto_id' => $autoId,
                    'rid'     => $residencialId,
                    'uid'     => $residenteId,
                ]);

                if ((int)$stmtCheck->fetchColumn() === 0) {
                    $error = 'No autorizado para editar este auto.';
                } else {
                    if ($autosTieneUnidadId) {
                        $stmtUpd = $pdo->prepare("
                            UPDATE autos
                            SET placas = :placas,
                                modelo = :modelo,
                                color  = :color,
                                unidad_id = :unidad_id
                            WHERE id = :id AND residencial_id = :rid AND propietario_user_id = :uid
                        ");
                        $stmtUpd->execute([
                            'placas'    => $placas,
                            'modelo'    => ($modelo !== '' ? $modelo : null),
                            'color'     => ($color !== '' ? $color : null),
                            'unidad_id' => ($unidadAuto > 0 ? $unidadAuto : null),
                            'id'        => $autoId,
                            'rid'       => $residencialId,
                            'uid'       => $residenteId,
                        ]);
                    } else {
                        $stmtUpd = $pdo->prepare("
                            UPDATE autos
                            SET placas = :placas,
                                modelo = :modelo,
                                color  = :color
                            WHERE id = :id AND residencial_id = :rid AND propietario_user_id = :uid
                        ");
                        $stmtUpd->execute([
                            'placas' => $placas,
                            'modelo' => ($modelo !== '' ? $modelo : null),
                            'color'  => ($color !== '' ? $color : null),
                            'id'     => $autoId,
                            'rid'    => $residencialId,
                            'uid'    => $residenteId,
                        ]);
                    }

                    header('Location: residentes.php?detalle=' . $residenteId . '&ok_auto=1');
                    exit;
                }
            } catch (PDOException $e) {
                $error = 'Error al editar el auto: ' . $e->getMessage();
            }
        }
    }

    // 4.f) Eliminar auto
    elseif ($accion === 'eliminar_auto_residente') {
        $residenteId = (int)($_POST['residente_id'] ?? 0);
        $autoId      = (int)($_POST['auto_id'] ?? 0);

        if ($residenteId <= 0 || $autoId <= 0) {
            $error = 'Datos inválidos para eliminar auto.';
        } else {
            try {
                $stmtDel = $pdo->prepare("
                    DELETE FROM autos
                    WHERE id = :id
                      AND residencial_id = :rid
                      AND propietario_user_id = :uid
                ");
                $stmtDel->execute([
                    'id'  => $autoId,
                    'rid' => $residencialId,
                    'uid' => $residenteId,
                ]);

                header('Location: residentes.php?detalle=' . $residenteId . '&ok_auto=1');
                exit;
            } catch (PDOException $e) {
                $error = 'Error al eliminar el auto: ' . $e->getMessage();
            }
        }
    }
}

// mensajes tras redirect
if (isset($_GET['ok']) && !$success) $success = 'Residente creado correctamente.';
if (isset($_GET['ok_pago']) && !$success) $success = 'Pago registrado correctamente.';
if (isset($_GET['ok_auto']) && !$success) $success = 'Cambios en autos guardados correctamente.';

// ------------------------------------------------------
// 5) Listado de residentes
// ------------------------------------------------------
$residentes = [];
try {
    $stmtRes = $pdo->prepare("
        SELECT u.id,
               u.name,
               u.email,
               u.telefono,
               u.is_active,
               u.created_at,
               un.id   AS unidad_id,
               un.clave AS unidad_clave,
               un.tipo  AS unidad_tipo,
               un.calle,
               un.numero_exterior,
               un.numero_interior,
               ru.es_titular
        FROM usuarios_residenciales ur
        JOIN users u ON u.id = ur.user_id
        JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
        JOIN residentes_unidades ru ON ru.user_id = u.id
        JOIN unidades un ON un.id = ru.unidad_id
        WHERE ur.residencial_id = :residencial_id
          AND t.nombre = 'residente'
        ORDER BY un.clave ASC, u.name ASC
    ");
    $stmtRes->execute(['residencial_id' => $residencialId]);
    $residentes = $stmtRes->fetchAll();
} catch (PDOException $e) {
    $error = 'Error al obtener la lista de residentes: ' . $e->getMessage();
}

ob_start();
?>

<div class="space-y-6 text-xs">

    <?php if ($success): ?>
        <div class="rounded-2xl bg-emerald-500/15 border border-emerald-400/50 px-4 py-3 text-emerald-100">
            <?= h($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="rounded-2xl bg-rose-500/15 border border-rose-400/50 px-4 py-3 text-rose-100">
            <?= h($error) ?>
        </div>
    <?php endif; ?>

    <!-- Encabezado -->
    <div class="rounded-2xl border border-white/15 bg-white/5 backdrop-blur-xl p-4 md:p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h4 class="text-sm font-semibold">
                Residentes de <?= h($residencial['nombre']) ?>
            </h4>
            <p class="text-[11px] text-slate-300/80">
                Registra a las personas que tendrán acceso al portal y administra su servicio.
            </p>
        </div>
        <div class="flex gap-2">
            <a href="residentes.php?modal=new"
               class="inline-flex items-center gap-1 rounded-2xl bg-gradient-to-r from-sky-500 to-indigo-500 px-4 py-2 text-[11px] font-medium text-white shadow-lg shadow-sky-900/40">
                + Nuevo residente
            </a>
        </div>
    </div>

    <!-- Lista de residentes -->
    <div class="rounded-2xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4 md:p-5 min-h-[60vh] flex flex-col">
        <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-semibold">Residentes del residencial</h4>
            <span class="text-[11px] text-slate-300/80">Total: <?= count($residentes) ?></span>
        </div>

        <?php if (empty($residentes)): ?>
            <p class="text-xs text-slate-400">Aún no hay residentes registrados.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs border-separate border-spacing-y-1">
                    <thead class="text-[11px] uppercase text-slate-300/80">
                        <tr>
                            <th class="text-left px-3 py-2">Nombre</th>
                            <th class="text-left px-3 py-2">Calle / Casa</th>
                            <th class="text-left px-3 py-2">Estado servicio</th>
                            <th class="text-left px-3 py-2">Contacto</th>
                            <th class="text-right px-3 py-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($residentes as $r): ?>
                            <?php
                            $activo         = (int)$r['is_active'] === 1;
                            $telefonoRaw    = $r['telefono'] ?? '';
                            $telefonoDigits = phone_digits($telefonoRaw);
                            $telefonoWa     = phone_wa_mx($telefonoDigits);

                            $dir = trim(($r['calle'] ?? '') . ' ' . ($r['numero_exterior'] ?? ''));
                            if (!empty($r['numero_interior'])) $dir .= ' Int ' . $r['numero_interior'];
                            if ($dir === '') $dir = $r['unidad_clave'];
                            ?>
                            <tr class="bg-white/5 hover:bg-white/10 transition rounded-xl">
                                <td class="px-3 py-2 rounded-l-xl">
                                    <div class="font-medium text-slate-50">
                                        <?= h($r['name']) ?>
                                    </div>
                                    <div class="text-[11px] text-slate-300/80">
                                        Unidad: <?= h($r['unidad_clave']) ?> · <?= h($r['unidad_tipo']) ?> · <?= (int)$r['es_titular'] === 1 ? 'Titular' : 'Integrante' ?>
                                    </div>
                                </td>

                                <td class="px-3 py-2 text-slate-200/90">
                                    <?= h($dir) ?>
                                </td>

                                <td class="px-3 py-2">
                                    <form method="POST" class="inline-block"
                                          onsubmit="return confirm('¿Seguro que quieres <?= $activo ? 'desactivar el servicio por falta de pago' : 'activar el servicio' ?> de este residente?');">
                                        <input type="hidden" name="accion" value="cambiar_estado_residente">
                                        <input type="hidden" name="residente_id" value="<?= (int)$r['id'] ?>">
                                        <input type="hidden" name="nuevo_estado" value="<?= $activo ? 0 : 1 ?>">

                                        <button type="submit"
                                                class="relative inline-flex items-center h-6 w-11 rounded-full transition <?= $activo ? 'bg-emerald-500/80' : 'bg-rose-500/80' ?>">
                                            <span class="sr-only">Cambiar estado</span>
                                            <span class="absolute inset-y-0 left-1 flex items-center text-[9px] text-white/80">
                                                <?= $activo ? 'On' : 'Off' ?>
                                            </span>
                                            <span class="ml-auto mr-1 inline-block h-5 w-5 rounded-full bg-white shadow transform transition <?= $activo ? '' : 'translate-x-[-18px]' ?>"></span>
                                        </button>
                                    </form>
                                </td>

                                <td class="px-3 py-2 text-slate-200/90">
                                    <?php if ($telefonoDigits): ?>
                                        <div class="flex flex-wrap gap-2">
                                            <a href="tel:<?= h($telefonoDigits) ?>"
                                               class="inline-flex items-center gap-1 px-3 py-1 rounded-2xl bg-white/10 border border-white/20 text-[11px] text-slate-50 hover:bg-white/20">
                                                📞 Llamar
                                            </a>
                                            <a href="https://wa.me/<?= h($telefonoWa) ?>"
                                               target="_blank"
                                               class="inline-flex items-center gap-1 px-3 py-1 rounded-2xl bg-emerald-500/15 border border-emerald-400/40 text-[11px] text-emerald-100 hover:bg-emerald-500/25">
                                                💬 WhatsApp
                                            </a>
                                        </div>
                                        <div class="text-[11px] text-slate-300/80 mt-1">
                                            <?= h($telefonoRaw) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-[11px] text-slate-400">Sin teléfono</span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-3 py-2 rounded-r-xl text-right">
                                    <a href="residentes.php?detalle=<?= (int)$r['id'] ?>"
                                       class="inline-flex items-center px-3 py-1 rounded-2xl bg-white/10 border border-white/20 text-[11px] text-slate-50 hover:bg-white/15">
                                        Ver más
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php
// ------------------------------------------------------
// MODAL: Crear residente
// ------------------------------------------------------
if ($showModalResidente && !empty($unidades)):
?>
<div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-xl">
    <div class="min-h-full flex items-center justify-center px-4 sm:px-6 py-8">
        <div class="relative w-full max-w-4xl rounded-3xl border border-white/15 bg-slate-950/95 shadow-2xl overflow-hidden">
            <div class="px-6 sm:px-8 pt-5 sm:pt-6 pb-6 sm:pb-7 flex flex-col gap-5">

                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg sm:text-xl font-semibold text-white">Nuevo residente</h2>
                        <p class="text-[11px] sm:text-xs text-slate-300/85">
                            Asigna a la persona a una unidad y crea su acceso al portal.
                        </p>
                    </div>
                    <button type="button"
                            onclick="window.location.href='residentes.php'"
                            class="shrink-0 h-9 w-9 rounded-full bg-slate-900/80 border border-white/15 flex items-center justify-center text-slate-300 hover:bg-slate-800 hover:text-white transition">
                        ✕
                    </button>
                </div>

                <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs sm:text-sm text-slate-100">
                    <input type="hidden" name="accion" value="crear_residente">

                    <div class="md:col-span-2 flex flex-col gap-1.5">
                        <label class="text-[11px] font-medium text-slate-200" for="nombre">Nombre completo *</label>
                        <input type="text" id="nombre" name="nombre" required
                               value="<?= h($formResidente['nombre']) ?>"
                               class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-xs sm:text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60"
                               placeholder="Ej. Ana López">
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-medium text-slate-200" for="email">Correo electrónico *</label>
                        <input type="email" id="email" name="email" required
                               value="<?= h($formResidente['email']) ?>"
                               class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-xs sm:text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60"
                               placeholder="correo@ejemplo.com">
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-medium text-slate-200" for="telefono">Teléfono</label>
                        <input type="text" id="telefono" name="telefono"
                               value="<?= h($formResidente['telefono']) ?>"
                               class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-xs sm:text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60"
                               placeholder="Opcional">
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-medium text-slate-200" for="password">Contraseña *</label>
                        <input type="password" id="password" name="password" required
                               class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-xs sm:text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60">
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-medium text-slate-200" for="password_confirm">Confirmar contraseña *</label>
                        <input type="password" id="password_confirm" name="password_confirm" required
                               class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-xs sm:text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60">
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-medium text-slate-200" for="unidad_id">Unidad *</label>
                        <select id="unidad_id" name="unidad_id" required
                                class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-xs sm:text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60">
                            <option value="">Selecciona una unidad</option>
                            <?php foreach ($unidades as $u): ?>
                                <option value="<?= (int)$u['id'] ?>" <?= (int)$formResidente['unidad_id'] === (int)$u['id'] ? 'selected' : '' ?>>
                                    <?= h($u['clave'] . ' (' . $u['tipo'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex flex-col justify-center gap-2 mt-1 md:mt-4">
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="es_titular"
                                   class="rounded border-white/30 bg-slate-900 text-sky-500 focus:ring-sky-500/70"
                                   <?= $formResidente['es_titular'] ? 'checked' : '' ?>>
                            <span class="text-[11px] text-slate-200">Es titular de la unidad</span>
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="es_activo"
                                   class="rounded border-white/30 bg-slate-900 text-sky-500 focus:ring-sky-500/70"
                                   <?= $formResidente['es_activo'] ? 'checked' : '' ?>>
                            <span class="text-[11px] text-slate-200">Cuenta activa</span>
                        </label>
                    </div>

                    <div class="md:col-span-3 flex justify-end gap-2 mt-2">
                        <button type="button"
                                onclick="window.location.href='residentes.php'"
                                class="rounded-2xl border border-white/15 bg-slate-900/80 px-4 py-2.5 text-[11px] sm:text-xs font-medium text-slate-100 hover:bg-slate-800 transition">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="rounded-2xl bg-gradient-to-r from-sky-500 to-indigo-500 px-5 sm:px-6 py-2.5 text-[11px] sm:text-xs font-semibold text-white shadow-lg shadow-sky-900/40 hover:from-sky-400 hover:to-indigo-400 transition">
                            Crear residente
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>
<?php
endif;

// ------------------------------------------------------
// MODAL DETALLE / PAGOS / AUTOS
// ------------------------------------------------------
$showDetalle      = false;
$residenteDetalle = null;

$pagos       = [];
$totalPagado = 0.0;

$autos = [];
$autoModal = $_GET['auto_modal'] ?? ''; // new | edit
$autoEdit  = null;

if (isset($_GET['detalle'])) {
    $detalleId = (int)$_GET['detalle'];
    if ($detalleId > 0) {
        $stmtDet = $pdo->prepare("
            SELECT u.id,
                   u.name,
                   u.email,
                   u.telefono,
                   u.is_active,
                   un.id   AS unidad_id,
                   un.clave AS unidad_clave,
                   un.tipo  AS unidad_tipo
            FROM users u
            JOIN residentes_unidades ru ON ru.user_id = u.id
            JOIN unidades un ON un.id = ru.unidad_id
            JOIN usuarios_residenciales ur ON ur.user_id = u.id
            JOIN residenciales r ON r.id = ur.residencial_id
            JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
            WHERE u.id = :id
              AND r.id = :residencial_id
              AND t.nombre = 'residente'
            LIMIT 1
        ");
        $stmtDet->execute([
            'id'             => $detalleId,
            'residencial_id' => $residencialId,
        ]);
        $residenteDetalle = $stmtDet->fetch();

        if ($residenteDetalle) {
            $showDetalle = true;

            // Pagos
            try {
                $stmtPagos = $pdo->prepare("
                    SELECT monto, fecha_pago, metodo, concepto
                    FROM pagos_residentes
                    WHERE residencial_id = :residencial_id
                      AND user_id = :user_id
                    ORDER BY fecha_pago DESC, created_at DESC
                ");
                $stmtPagos->execute([
                    'residencial_id' => $residencialId,
                    'user_id'        => $residenteDetalle['id'],
                ]);
                $pagos = $stmtPagos->fetchAll();
                foreach ($pagos as $p) $totalPagado += (float)$p['monto'];
            } catch (PDOException $e) {
                $pagos = [];
            }

            // Autos
            try {
                if ($autosTieneUnidadId) {
                    $stmtAutos = $pdo->prepare("
                        SELECT a.id, a.placas, a.modelo, a.color, a.unidad_id, un.clave AS unidad_clave
                        FROM autos a
                        LEFT JOIN unidades un ON un.id = a.unidad_id
                        WHERE a.residencial_id = :rid
                          AND a.propietario_user_id = :uid
                        ORDER BY a.created_at DESC
                    ");
                } else {
                    $stmtAutos = $pdo->prepare("
                        SELECT id, placas, modelo, color
                        FROM autos
                        WHERE residencial_id = :rid
                          AND propietario_user_id = :uid
                        ORDER BY created_at DESC
                    ");
                }

                $stmtAutos->execute(['rid' => $residencialId, 'uid' => $residenteDetalle['id']]);
                $autos = $stmtAutos->fetchAll();
            } catch (PDOException $e) {
                $autos = [];
            }

            // Edit auto
            if ($autoModal === 'edit' && isset($_GET['auto_id'])) {
                $autoId = (int)$_GET['auto_id'];
                if ($autoId > 0) {
                    try {
                        if ($autosTieneUnidadId) {
                            $stmtOne = $pdo->prepare("
                                SELECT id, placas, modelo, color, unidad_id
                                FROM autos
                                WHERE id = :id AND residencial_id = :rid AND propietario_user_id = :uid
                                LIMIT 1
                            ");
                        } else {
                            $stmtOne = $pdo->prepare("
                                SELECT id, placas, modelo, color
                                FROM autos
                                WHERE id = :id AND residencial_id = :rid AND propietario_user_id = :uid
                                LIMIT 1
                            ");
                        }
                        $stmtOne->execute([
                            'id'  => $autoId,
                            'rid' => $residencialId,
                            'uid' => $residenteDetalle['id'],
                        ]);
                        $autoEdit = $stmtOne->fetch();
                        if (!$autoEdit) $autoModal = '';
                    } catch (PDOException $e) {
                        $autoModal = '';
                    }
                }
            }

            // Sanitizar: auto_modal solo puede ser new/edit
            if (!in_array($autoModal, ['', 'new', 'edit'], true)) $autoModal = '';
        }
    }
}

if ($showDetalle && $residenteDetalle):
?>
<div class="fixed inset-0 z-40 bg-slate-950/80 backdrop-blur-sm overflow-y-auto">
    <div class="min-h-screen flex items-stretch px-2 md:px-8 py-4">
        <div class="w-full max-w-5xl mx-auto rounded-3xl border border-white/15 bg-slate-950/95 p-6 md:p-8 shadow-2xl flex flex-col">

            <!-- HEADER -->
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-base md:text-lg font-semibold text-slate-50">Detalle de residente</h3>
                    <p class="text-[11px] text-slate-300/80">
                        <?= h($residenteDetalle['name']) ?> · Unidad <?= h($residenteDetalle['unidad_clave']) ?> (<?= h($residenteDetalle['unidad_tipo']) ?>)
                    </p>
                </div>
                <button type="button"
                        onclick="window.location.href='residentes.php'"
                        class="h-8 w-8 rounded-full border border-white/20 bg-white/5 text-sm flex items-center justify-center">
                    ✕
                </button>
            </div>

            <div class="flex-1 flex flex-col gap-4 overflow-y-auto text-xs sm:text-sm">

                <!-- Datos -->
                <section class="rounded-2xl bg-slate-950/60 border border-white/10 px-4 md:px-5 py-4 space-y-3">
                    <h4 class="text-[11px] font-semibold tracking-wide text-slate-200 uppercase">Datos del residente</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-1 text-slate-200">Nombre</label>
                            <input type="text" readonly value="<?= h($residenteDetalle['name']) ?>"
                                   class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
                        </div>
                        <div>
                            <label class="block mb-1 text-slate-200">Correo</label>
                            <input type="email" readonly value="<?= h($residenteDetalle['email']) ?>"
                                   class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
                        </div>
                        <div>
                            <label class="block mb-1 text-slate-200">Teléfono</label>
                            <input type="text" readonly value="<?= h($residenteDetalle['telefono'] ?? '') ?>"
                                   class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
                        </div>
                        <div>
                            <label class="block mb-1 text-slate-200">Unidad</label>
                            <input type="text" readonly value="<?= h($residenteDetalle['unidad_clave'] . ' (' . $residenteDetalle['unidad_tipo'] . ')') ?>"
                                   class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
                        </div>
                    </div>
                </section>

                <!-- Pagos -->
                <section class="rounded-2xl bg-slate-950/60 border border-white/10 px-4 md:px-5 py-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="text-[11px] font-semibold tracking-wide text-slate-200 uppercase">Adeudos y pagos</h4>
                        <span class="text-[11px] text-slate-300/80">Total pagado: $<?= number_format($totalPagado, 2) ?></span>
                    </div>

                    <form method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-3 text-xs">
                        <input type="hidden" name="accion" value="registrar_pago_residente">
                        <input type="hidden" name="residente_id" value="<?= (int)$residenteDetalle['id'] ?>">

                        <div>
                            <label class="block mb-1 text-slate-200">Monto</label>
                            <input type="number" step="0.01" name="monto" required
                                   class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2">
                        </div>
                        <div>
                            <label class="block mb-1 text-slate-200">Fecha</label>
                            <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required
                                   class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2">
                        </div>
                        <div>
                            <label class="block mb-1 text-slate-200">Método</label>
                            <select name="metodo" class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2">
                                <option value="efectivo">Efectivo</option>
                                <option value="transferencia">Transferencia</option>
                                <option value="tarjeta">Tarjeta</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block mb-1 text-slate-200">Concepto (opcional)</label>
                            <input type="text" name="concepto"
                                   class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2"
                                   placeholder="Ej. Mantenimiento noviembre 2025">
                        </div>
                        <div class="md:col-span-5 flex justify-end">
                            <button type="submit"
                                    class="rounded-2xl bg-gradient-to-r from-sky-500 to-indigo-500 px-5 py-2.5 text-[11px] font-medium text-white shadow-lg shadow-sky-900/40">
                                Registrar pago
                            </button>
                        </div>
                    </form>

                    <div class="mt-3">
                        <h5 class="text-[11px] text-slate-300/90 mb-1">Historial</h5>
                        <?php if (empty($pagos)): ?>
                            <p class="text-[11px] text-slate-400">Sin pagos registrados.</p>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-[11px] border-separate border-spacing-y-1">
                                    <thead class="text-slate-300/80">
                                        <tr>
                                            <th class="text-left px-2 py-1">Fecha</th>
                                            <th class="text-left px-2 py-1">Monto</th>
                                            <th class="text-left px-2 py-1">Método</th>
                                            <th class="text-left px-2 py-1">Concepto</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pagos as $p): ?>
                                            <tr class="bg-white/5 rounded-xl">
                                                <td class="px-2 py-1"><?= h($p['fecha_pago']) ?></td>
                                                <td class="px-2 py-1">$<?= number_format((float)$p['monto'], 2) ?></td>
                                                <td class="px-2 py-1"><?= h($p['metodo']) ?></td>
                                                <td class="px-2 py-1"><?= h($p['concepto'] ?? '') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- AUTOS -->
                <section class="rounded-2xl bg-slate-950/60 border border-white/10 px-4 md:px-5 py-4 space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <h4 class="text-[11px] font-semibold tracking-wide text-slate-200 uppercase">Autos asignados</h4>

                        <div class="flex items-center gap-2">
                            <a href="residentes.php?detalle=<?= (int)$residenteDetalle['id'] ?>&auto_modal=new"
                               class="inline-flex items-center px-3 py-1 rounded-2xl bg-white/10 border border-white/20 text-[11px] text-slate-50 hover:bg-white/15">
                                + Agregar auto
                            </a>
                            <!-- (Opcional) si ya tienes autos.php: -->
                            <a href="autos.php?propietario_user_id=<?= (int)$residenteDetalle['id'] ?>"
                               class="inline-flex items-center px-3 py-1 rounded-2xl bg-white/10 border border-white/20 text-[11px] text-slate-50 hover:bg-white/15">
                                Ver en módulo Autos
                            </a>
                        </div>
                    </div>

                    <?php if (empty($autos)): ?>
                        <p class="text-[11px] text-slate-400">Aún no hay autos asignados a este residente.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-[11px] border-separate border-spacing-y-1">
                                <thead class="text-slate-300/80 uppercase">
                                    <tr>
                                        <th class="text-left px-3 py-2">Placas</th>
                                        <th class="text-left px-3 py-2">Modelo</th>
                                        <th class="text-left px-3 py-2">Color</th>
                                        <?php if ($autosTieneUnidadId): ?>
                                            <th class="text-left px-3 py-2">Casa</th>
                                        <?php endif; ?>
                                        <th class="text-right px-3 py-2">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($autos as $a): ?>
                                        <tr class="bg-white/5 hover:bg-white/10 transition rounded-xl">
                                            <td class="px-3 py-2 rounded-l-xl font-medium text-slate-50"><?= h($a['placas']) ?></td>
                                            <td class="px-3 py-2 text-slate-200/90"><?= h($a['modelo'] ?? '—') ?></td>
                                            <td class="px-3 py-2 text-slate-200/90"><?= h($a['color'] ?? '—') ?></td>
                                            <?php if ($autosTieneUnidadId): ?>
                                                <td class="px-3 py-2 text-slate-200/90"><?= h($a['unidad_clave'] ?? '—') ?></td>
                                            <?php endif; ?>
                                            <td class="px-3 py-2 rounded-r-xl text-right">
                                                <div class="inline-flex gap-2">
                                                    <a href="residentes.php?detalle=<?= (int)$residenteDetalle['id'] ?>&auto_modal=edit&auto_id=<?= (int)$a['id'] ?>"
                                                       class="inline-flex items-center px-3 py-1 rounded-2xl bg-white/10 border border-white/20 text-[11px] text-slate-50 hover:bg-white/15">
                                                        Editar
                                                    </a>

                                                    <form method="POST" class="inline-block"
                                                          onsubmit="return confirm('¿Eliminar este auto?');">
                                                        <input type="hidden" name="accion" value="eliminar_auto_residente">
                                                        <input type="hidden" name="residente_id" value="<?= (int)$residenteDetalle['id'] ?>">
                                                        <input type="hidden" name="auto_id" value="<?= (int)$a['id'] ?>">
                                                        <button type="submit"
                                                                class="inline-flex items-center px-3 py-1 rounded-2xl bg-rose-500/15 border border-rose-400/40 text-[11px] text-rose-100 hover:bg-rose-500/25">
                                                            Eliminar
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>

            </div>

            <div class="mt-4 flex justify-end">
                <button type="button"
                        onclick="window.location.href='residentes.php'"
                        class="px-4 py-2 rounded-2xl border border-white/20 bg-white/5 text-[11px]">
                    Cerrar
                </button>
            </div>

        </div>
    </div>
</div>

<?php
// ------------------------------------------------------
// MODAL interno: Agregar/Editar auto (fuera del section para z-index limpio)
// ------------------------------------------------------
if ($showDetalle && ($autoModal === 'new' || ($autoModal === 'edit' && $autoEdit))):
    $isEdit = ($autoModal === 'edit' && $autoEdit);
    $titulo = $isEdit ? 'Editar auto' : 'Agregar auto';
?>
<div class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm">
    <div class="min-h-full flex items-center justify-center px-4 py-8">
        <div class="w-full max-w-3xl rounded-3xl border border-white/15 bg-slate-950/95 shadow-2xl overflow-hidden">
            <div class="px-6 sm:px-8 pt-5 sm:pt-6 pb-6 sm:pb-7 flex flex-col gap-5">

                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg sm:text-xl font-semibold text-white"><?= h($titulo) ?></h2>
                        <p class="text-[11px] sm:text-xs text-slate-300/85">
                            Residente: <?= h($residenteDetalle['name']) ?> · Unidad <?= h($residenteDetalle['unidad_clave']) ?>
                        </p>
                    </div>
                    <button type="button"
                            onclick="window.location.href='residentes.php?detalle=<?= (int)$residenteDetalle['id'] ?>'"
                            class="shrink-0 h-9 w-9 rounded-full bg-slate-900/80 border border-white/15 flex items-center justify-center text-slate-300 hover:bg-slate-800 hover:text-white transition">
                        ✕
                    </button>
                </div>

                <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs sm:text-sm text-slate-100">
                    <input type="hidden" name="residente_id" value="<?= (int)$residenteDetalle['id'] ?>">
                    <input type="hidden" name="accion" value="<?= $isEdit ? 'editar_auto_residente' : 'crear_auto_residente' ?>">
                    <?php if ($isEdit): ?>
                        <input type="hidden" name="auto_id" value="<?= (int)$autoEdit['id'] ?>">
                    <?php endif; ?>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-medium text-slate-200">Placas *</label>
                        <input type="text" name="placas" required
                               value="<?= $isEdit ? h($autoEdit['placas']) : '' ?>"
                               class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-xs sm:text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60"
                               placeholder="Ej. ABC-123">
                    </div>

                    <div class="md:col-span-2 flex flex-col gap-1.5">
                        <label class="text-[11px] font-medium text-slate-200">Modelo</label>
                        <input type="text" name="modelo"
                               value="<?= $isEdit ? h($autoEdit['modelo'] ?? '') : '' ?>"
                               class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-xs sm:text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60"
                               placeholder="Ej. Versa 2020">
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-medium text-slate-200">Color</label>
                        <input type="text" name="color"
                               value="<?= $isEdit ? h($autoEdit['color'] ?? '') : '' ?>"
                               class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-xs sm:text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60"
                               placeholder="Ej. Blanco">
                    </div>

                    <?php if ($autosTieneUnidadId): ?>
                        <div class="md:col-span-2 flex flex-col gap-1.5">
                            <label class="text-[11px] font-medium text-slate-200">Casa asignada</label>
                            <select name="unidad_id"
                                    class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-xs sm:text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60">
                                <option value="">(Opcional) Selecciona una unidad</option>
                                <?php foreach ($unidades as $u): ?>
                                    <?php
                                    $val = (int)$u['id'];
                                    $sel = '';

                                    if ($isEdit && isset($autoEdit['unidad_id']) && (int)$autoEdit['unidad_id'] === $val) $sel = 'selected';
                                    if (!$isEdit && (int)$residenteDetalle['unidad_id'] === $val) $sel = 'selected';
                                    ?>
                                    <option value="<?= $val ?>" <?= $sel ?>>
                                        <?= h($u['clave'] . ' (' . $u['tipo'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="md:col-span-3 flex justify-end gap-2 mt-2">
                        <button type="button"
                                onclick="window.location.href='residentes.php?detalle=<?= (int)$residenteDetalle['id'] ?>'"
                                class="rounded-2xl border border-white/15 bg-slate-900/80 px-4 py-2.5 text-[11px] sm:text-xs font-medium text-slate-100 hover:bg-slate-800 transition">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="rounded-2xl bg-gradient-to-r from-sky-500 to-indigo-500 px-5 sm:px-6 py-2.5 text-[11px] sm:text-xs font-semibold text-white shadow-lg shadow-sky-900/40 hover:from-sky-400 hover:to-indigo-400 transition">
                            <?= $isEdit ? 'Guardar cambios' : 'Crear auto' ?>
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>
<?php
endif; // modal autos
endif; // modal detalle

$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
