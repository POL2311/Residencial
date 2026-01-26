<?php
// admin_residencial/guardias.php
require_once __DIR__ . '/../config/auth.php';
require_role(['admin_residencial']);
require_once __DIR__ . '/../config/config.php';

$user       = current_user();
$pageTitle  = 'Guardias de seguridad';
$activeMenu = 'guardias';

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
    // WhatsApp MX: si son 10 dígitos, anteponemos 52
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
// Estado del modal + valores del formulario
// ------------------------------------------------------
$showModalGuardia = isset($_GET['modal']) && $_GET['modal'] === 'new';

$formGuardia = [
    'nombre'    => '',
    'email'     => '',
    'telefono'  => '',
    'es_activo' => 1,
];

// ------------------------------------------------------
// 2) Acciones POST
//    - cambiar estado servicio/descanso
//    - alta de guardia
// ------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // 2.a) Cambiar estado servicio/descanso
    if ($accion === 'cambiar_servicio') {
        $guardiaId = (int)($_POST['guardia_id'] ?? 0);
        $nuevo     = (int)($_POST['nuevo_estado'] ?? 0);

        if ($guardiaId <= 0 || !in_array($nuevo, [0, 1], true)) {
            $error = 'Datos inválidos para cambiar estado del guardia.';
        } else {
            try {
                // Seguridad: que el guardia pertenezca a este residencial
                $stmt = $pdo->prepare("
                    UPDATE users u
                    JOIN usuarios_residenciales ur ON ur.user_id = u.id
                    JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
                    SET u.guardia_en_servicio = :estado
                    WHERE u.id = :id
                      AND ur.residencial_id = :rid
                      AND t.nombre = 'guardia'
                ");
                $stmt->execute([
                    'estado' => $nuevo,
                    'id'     => $guardiaId,
                    'rid'    => $residencialId,
                ]);

                $success = 'Estado del guardia actualizado correctamente.';
            } catch (PDOException $e) {
                $error = 'Error al cambiar estado del guardia: ' . $e->getMessage();
            }
        }
    }

    // 2.b) Alta de guardia (POST)
    elseif ($accion === 'crear_guardia') {
        $nombre      = trim($_POST['nombre'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $telefono    = trim($_POST['telefono'] ?? '');
        $password    = $_POST['password'] ?? '';
        $password2   = $_POST['password_confirm'] ?? '';
        $es_activo   = isset($_POST['es_activo']) ? 1 : 0;

        // mantener valores en el modal en caso de error
        $formGuardia['nombre']    = $nombre;
        $formGuardia['email']     = $email;
        $formGuardia['telefono']  = $telefono;
        $formGuardia['es_activo'] = $es_activo;

        $errores = [];

        if ($nombre === '')  $errores[] = 'El nombre del guardia es obligatorio.';
        if ($email === '')   $errores[] = 'El correo electrónico es obligatorio.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El email no tiene un formato válido.';
        }
        if ($password === '' || $password2 === '') {
            $errores[] = 'La contraseña y su confirmación son obligatorias.';
        } elseif ($password !== $password2) {
            $errores[] = 'Las contraseñas no coinciden.';
        } elseif (strlen($password) < 6) {
            $errores[] = 'La contraseña debe tener al menos 6 caracteres.';
        }

        // Validar que el correo no exista
        if (empty($errores)) {
            try {
                $stmtCheckEmail = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
                $stmtCheckEmail->execute(['email' => $email]);
                if ($stmtCheckEmail->fetch()) {
                    $errores[] = 'Ya existe un usuario con ese correo.';
                }
            } catch (PDOException $e) {
                $errores[] = 'Error al validar el email: ' . $e->getMessage();
            }
        }

        if (!empty($errores)) {
            $error = implode(' ', $errores);
            $showModalGuardia = true; // reabrir modal con errores
        } else {
            try {
                $pdo->beginTransaction();

                // 1) Obtener id del tipo_usuario 'guardia'
                $stmtTipo = $pdo->prepare("SELECT id FROM tipos_usuario WHERE nombre = 'guardia' LIMIT 1");
                $stmtTipo->execute();
                $tipo = $stmtTipo->fetch();
                if (!$tipo) {
                    throw new Exception("No existe el tipo de usuario 'guardia' en la tabla tipos_usuario.");
                }

                // 2) Crear usuario (guardia_en_servicio por default 0)
                $hash = password_hash($password, PASSWORD_DEFAULT);

                // Detectar si existe guardia_en_servicio (para compatibilidad)
                $tieneServicioCol = false;
                try {
                    $stmtCol = $pdo->prepare("
                        SELECT COUNT(*)
                        FROM INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE()
                          AND TABLE_NAME = 'users'
                          AND COLUMN_NAME = 'guardia_en_servicio'
                    ");
                    $stmtCol->execute();
                    $tieneServicioCol = ((int)$stmtCol->fetchColumn() > 0);
                } catch (Throwable $e) {}

                if ($tieneServicioCol) {
                    $stmtUser = $pdo->prepare("
                        INSERT INTO users (tipo_usuario_id, name, email, telefono, password_hash, is_active, guardia_en_servicio)
                        VALUES (:tipo_id, :name, :email, :telefono, :hash, :active, 0)
                    ");
                } else {
                    $stmtUser = $pdo->prepare("
                        INSERT INTO users (tipo_usuario_id, name, email, telefono, password_hash, is_active)
                        VALUES (:tipo_id, :name, :email, :telefono, :hash, :active)
                    ");
                }

                $stmtUser->execute([
                    'tipo_id'  => $tipo['id'],
                    'name'     => $nombre,
                    'email'    => $email,
                    'telefono' => $telefono !== '' ? $telefono : null,
                    'hash'     => $hash,
                    'active'   => $es_activo,
                ]);

                $user_id_guardia = (int)$pdo->lastInsertId();

                // 3) Ligarlo al residencial
                $stmtUR = $pdo->prepare("
                    INSERT INTO usuarios_residenciales (user_id, residencial_id, es_principal)
                    VALUES (:user_id, :residencial_id, 1)
                ");
                $stmtUR->execute([
                    'user_id'        => $user_id_guardia,
                    'residencial_id' => $residencialId,
                ]);

                $pdo->commit();

                header('Location: guardias.php?ok=1');
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Error al crear el guardia: ' . $e->getMessage();
                $showModalGuardia = true;
            }
        }
    }
}

// mensaje después de redirect
if (isset($_GET['ok']) && !$success) {
    $success = 'Guardia creado correctamente.';
}

// ------------------------------------------------------
// 3) Listado de guardias del residencial
// ------------------------------------------------------
$guardias = [];
try {
    // Compatibilidad: si no existe guardia_en_servicio, lo ponemos como 0
    $tieneServicioCol = false;
    try {
        $stmtCol = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'users'
              AND COLUMN_NAME = 'guardia_en_servicio'
        ");
        $stmtCol->execute();
        $tieneServicioCol = ((int)$stmtCol->fetchColumn() > 0);
    } catch (Throwable $e) {
        $tieneServicioCol = false;
    }

    $sql = "
        SELECT u.id,
               u.name,
               u.email,
               u.telefono,
               u.is_active,
               u.created_at
               " . ($tieneServicioCol ? ", u.guardia_en_servicio" : ", 0 AS guardia_en_servicio") . "
        FROM usuarios_residenciales ur
        JOIN users u ON u.id = ur.user_id
        JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
        WHERE ur.residencial_id = :residencial_id
          AND t.nombre = 'guardia'
        ORDER BY u.name ASC
    ";

    $stmtG = $pdo->prepare($sql);
    $stmtG->execute(['residencial_id' => $residencialId]);
    $guardias = $stmtG->fetchAll();
} catch (PDOException $e) {
    $error = 'Error al obtener la lista de guardias: ' . $e->getMessage();
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
                Guardias de <?= h($residencial['nombre']) ?>
            </h4>
            <p class="text-[11px] text-slate-300/80">
                Controla el personal de seguridad y su estatus de servicio.
            </p>
        </div>
        <div class="flex gap-2">
            <a href="guardias.php?modal=new"
               class="inline-flex items-center gap-1 rounded-2xl bg-gradient-to-r from-sky-500 to-indigo-500 px-4 py-2 text-[11px] font-medium text-white shadow-lg shadow-sky-900/40">
                + Nuevo guardia
            </a>
        </div>
    </div>

    <!-- Lista de guardias -->
    <div class="rounded-3xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4 md:p-6 min-h-[60vh] flex flex-col">
        <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-semibold">Guardias del residencial</h4>
            <span class="text-[11px] text-slate-300/80">Total: <?= count($guardias) ?></span>
        </div>

        <?php if (empty($guardias)): ?>
            <p class="text-xs text-slate-400">Aún no hay guardias registrados.</p>
        <?php else: ?>
            <div class="flex-1 overflow-x-auto">
                <table class="min-w-full text-xs border-separate border-spacing-y-1">
                    <thead class="text-[11px] uppercase text-slate-300/80">
                        <tr>
                            <th class="text-left px-3 py-2">Nombre</th>
                            <th class="text-left px-3 py-2">Teléfono</th>
                            <th class="text-left px-3 py-2">Servicio</th>
                            <th class="text-left px-3 py-2">Cuenta</th>
                            <th class="text-right px-3 py-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($guardias as $g): ?>
                            <?php
                            $enServicio    = (int)($g['guardia_en_servicio'] ?? 0) === 1;
                            $telefonoRaw   = $g['telefono'] ?? '';
                            $telDigits     = phone_digits($telefonoRaw);
                            $telWa         = phone_wa_mx($telDigits);
                            ?>
                            <tr class="bg-white/5 hover:bg-white/10 transition rounded-xl">
                                <td class="px-3 py-2 rounded-l-xl">
                                    <span class="font-medium text-slate-50">
                                        <?= h($g['name']) ?>
                                    </span>
                                    <div class="text-[11px] text-slate-300/80">
                                        <?= h($g['email']) ?>
                                    </div>
                                </td>

                                <td class="px-3 py-2 text-slate-200/90">
                                    <?php if ($telDigits): ?>
                                        <div class="flex flex-wrap gap-2">
                                            <a href="tel:<?= h($telDigits) ?>"
                                               class="inline-flex items-center gap-1 px-3 py-1 rounded-2xl bg-white/10 border border-white/20 text-[11px] text-slate-50 hover:bg-white/20">
                                                📞 Llamar
                                            </a>
                                            <a href="https://wa.me/<?= h($telWa) ?>"
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

                                <!-- Servicio / Descanso -->
                                <td class="px-3 py-2">
                                    <form method="POST" class="inline-block">
                                        <input type="hidden" name="accion" value="cambiar_servicio">
                                        <input type="hidden" name="guardia_id" value="<?= (int)$g['id'] ?>">
                                        <input type="hidden" name="nuevo_estado" value="<?= $enServicio ? 0 : 1 ?>">

                                        <button type="submit"
                                                class="relative inline-flex items-center h-6 w-11 rounded-full transition <?= $enServicio ? 'bg-emerald-500/80' : 'bg-slate-500/60' ?>">
                                            <span class="sr-only">Cambiar servicio</span>
                                            <span class="absolute inset-y-0 left-1 flex items-center text-[9px] text-white/80">
                                                <?= $enServicio ? 'On' : 'Off' ?>
                                            </span>
                                            <span class="ml-auto mr-1 inline-block h-5 w-5 rounded-full bg-white shadow transform transition <?= $enServicio ? '' : 'translate-x-[-18px]' ?>"></span>
                                        </button>
                                    </form>

                                    <div class="text-[11px] text-slate-300/80 mt-1">
                                        <?= $enServicio ? 'En servicio' : 'Descanso' ?>
                                    </div>
                                </td>

                                <!-- Estado cuenta -->
                                <td class="px-3 py-2">
                                    <?php if ((int)$g['is_active'] === 1): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-400/40 text-[11px] text-emerald-100">
                                            Activo
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-500/20 border border-slate-400/40 text-[11px] text-slate-200">
                                            Inactivo
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-3 py-2 rounded-r-xl text-right">
                                    <a href="admin_residencial_incidencias.php?modal=new&guardia_id=<?= (int)$g['id'] ?>"
                                       class="inline-flex items-center px-3 py-1 rounded-2xl bg-rose-500/15 border border-rose-400/40 text-[11px] text-rose-100 hover:bg-rose-500/25">
                                        🚨 Reportar incidencia
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
// MODAL: crear guardia
// ------------------------------------------------------
if ($showModalGuardia):
?>
<div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-xl">
    <div class="min-h-full flex items-center justify-center px-4 sm:px-6 py-8">
        <div class="relative w-full max-w-3xl rounded-3xl border border-white/15 bg-slate-950/95 shadow-2xl overflow-hidden">
            <div class="px-6 sm:px-8 pt-5 sm:pt-6 pb-6 sm:pb-7 flex flex-col gap-5">

                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg sm:text-xl font-semibold text-white">Nuevo guardia</h2>
                        <p class="text-[11px] sm:text-xs text-slate-300/85">
                            Registra una cuenta de acceso para el personal de seguridad.
                        </p>
                    </div>
                    <button type="button"
                            onclick="window.location.href='guardias.php'"
                            class="shrink-0 h-9 w-9 rounded-full bg-slate-900/80 border border-white/15 flex items-center justify-center text-slate-300 hover:bg-slate-800 hover:text-white transition">
                        ✕
                    </button>
                </div>

                <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs sm:text-sm text-slate-100">
                    <input type="hidden" name="accion" value="crear_guardia">

                    <div class="md:col-span-2 flex flex-col gap-1.5">
                        <label class="text-[11px] font-medium text-slate-200" for="nombre">Nombre completo *</label>
                        <input type="text" id="nombre" name="nombre" required
                               value="<?= h($formGuardia['nombre']) ?>"
                               class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-xs sm:text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60"
                               placeholder="Ej. Carlos Pérez">
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-medium text-slate-200" for="email">Correo electrónico *</label>
                        <input type="email" id="email" name="email" required
                               value="<?= h($formGuardia['email']) ?>"
                               class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-xs sm:text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60"
                               placeholder="correo@ejemplo.com">
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-medium text-slate-200" for="telefono">Teléfono</label>
                        <input type="text" id="telefono" name="telefono"
                               value="<?= h($formGuardia['telefono']) ?>"
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

                    <div class="flex flex-col justify-center gap-2 mt-1 md:mt-4">
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="es_activo"
                                   class="rounded border-white/30 bg-slate-900 text-sky-500 focus:ring-sky-500/70"
                                   <?= $formGuardia['es_activo'] ? 'checked' : '' ?>>
                            <span class="text-[11px] text-slate-200">Cuenta activa</span>
                        </label>
                    </div>

                    <div class="md:col-span-3 flex justify-end gap-2 mt-2">
                        <button type="button"
                                onclick="window.location.href='guardias.php'"
                                class="rounded-2xl border border-white/15 bg-slate-900/80 px-4 py-2.5 text-[11px] sm:text-xs font-medium text-slate-100 hover:bg-slate-800 transition">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="rounded-2xl bg-gradient-to-r from-sky-500 to-indigo-500 px-5 sm:px-6 py-2.5 text-[11px] sm:text-xs font-semibold text-white shadow-lg shadow-sky-900/40 hover:from-sky-400 hover:to-indigo-400 transition">
                            Crear guardia
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>
<?php
endif;

$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
