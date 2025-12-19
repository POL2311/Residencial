<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';
require_role(['super_admin']);

$pageTitle  = 'Usuarios y roles (Super Admin)';
$activeMenu = 'users';

$success = '';
$error   = '';

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

// estado UI
$showCreateModal = false;
$showAssignModal = false;

// form state (para preservar valores)
$formCreate = [
    'nombre' => '',
    'email' => '',
    'tipo_usuario_id' => '',
    'is_active' => 1,
];
$formAssign = [
    'user_id' => '',
    'residencial_id' => '',
    'es_principal' => 1,
];

// 1) Obtener tipos de usuario
$tiposUsuario = [];
try {
    $stmtT = $pdo->query("SELECT id, nombre, descripcion FROM tipos_usuario ORDER BY nombre ASC");
    $tiposUsuario = $stmtT->fetchAll();
} catch (PDOException $e) {
    $error = 'Error al obtener tipos de usuario: ' . $e->getMessage();
}

// 2) Obtener usuarios y residenciales para selects
$usuariosAll = [];
$residencialesAll = [];
try {
    $stmtUAll = $pdo->query("SELECT id, name, email FROM users ORDER BY name ASC");
    $usuariosAll = $stmtUAll->fetchAll();

    $stmtRAll = $pdo->query("SELECT id, nombre, codigo FROM residenciales ORDER BY nombre ASC");
    $residencialesAll = $stmtRAll->fetchAll();
} catch (PDOException $e) {
    $error = 'Error al obtener usuarios/residenciales para asignación: ' . $e->getMessage();
}

// Abrir modales por GET
if (isset($_GET['new']) && $_GET['new'] === 'user') $showCreateModal = true;
if (isset($_GET['new']) && $_GET['new'] === 'assign') $showAssignModal = true;

// 3) Manejar formularios (dos tipos)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf, $postedCsrf)) {
        $error = 'Sesión inválida. Recarga la página e inténtalo de nuevo.';
    } else {
        $formType = $_POST['form_type'] ?? 'crear_usuario';

        // --- A) Formulario: crear usuario ---
        if ($formType === 'crear_usuario') {

            // preservar valores
            $formCreate['nombre'] = trim($_POST['nombre'] ?? '');
            $formCreate['email'] = trim($_POST['email'] ?? '');
            $formCreate['tipo_usuario_id'] = (string)($_POST['tipo_usuario_id'] ?? '');
            $formCreate['is_active'] = isset($_POST['is_active']) ? 1 : 0;

            $nombre   = $formCreate['nombre'];
            $email    = $formCreate['email'];
            $password = $_POST['password'] ?? '';
            $password2= $_POST['password_confirm'] ?? '';
            $tipo_id  = (int)($formCreate['tipo_usuario_id'] !== '' ? $formCreate['tipo_usuario_id'] : 0);
            $is_active= (int)$formCreate['is_active'];

            $errores = [];

            if ($nombre === '') $errores[] = 'El nombre del usuario es obligatorio.';
            if ($email === '')  $errores[] = 'El email es obligatorio.';
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
            if ($tipo_id <= 0) {
                $errores[] = 'Debes seleccionar un tipo de usuario.';
            }

            if (empty($errores)) {
                try {
                    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
                    $stmtCheck->execute(['email' => $email]);
                    if ($stmtCheck->fetch()) {
                        $errores[] = 'Ya existe un usuario con ese correo.';
                    }
                } catch (PDOException $e) {
                    $errores[] = 'Error al validar el email: ' . $e->getMessage();
                }
            }

            if (!empty($errores)) {
                $error = implode(' ', $errores);
                $showCreateModal = true;
            } else {
                try {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmtInsert = $pdo->prepare("
                        INSERT INTO users (tipo_usuario_id, name, email, password_hash, is_active)
                        VALUES (:tipo, :name, :email, :hash, :active)
                    ");
                    $stmtInsert->execute([
                        'tipo'   => $tipo_id,
                        'name'   => $nombre,
                        'email'  => $email,
                        'hash'   => $hash,
                        'active' => $is_active,
                    ]);

                    header('Location: superadmin_usuarios.php?ok=user_created');
                    exit;
                } catch (PDOException $e) {
                    $error = 'Error al crear el usuario: ' . $e->getMessage();
                    $showCreateModal = true;
                }
            }
        }

        // --- B) Formulario: asignar usuario a residencial ---
        if ($formType === 'asignar_residencial') {

            // preservar valores
            $formAssign['user_id'] = (string)($_POST['user_id'] ?? '');
            $formAssign['residencial_id'] = (string)($_POST['residencial_id'] ?? '');
            $formAssign['es_principal'] = isset($_POST['es_principal']) ? 1 : 0;

            $user_id        = (int)($formAssign['user_id'] !== '' ? $formAssign['user_id'] : 0);
            $residencial_id = (int)($formAssign['residencial_id'] !== '' ? $formAssign['residencial_id'] : 0);
            $es_principal   = (int)$formAssign['es_principal'];

            $errores = [];
            if ($user_id <= 0)        $errores[] = 'Debes seleccionar un usuario.';
            if ($residencial_id <= 0) $errores[] = 'Debes seleccionar un residencial.';

            if (empty($errores)) {
                try {
                    $stmtCheck = $pdo->prepare("
                        SELECT id FROM usuarios_residenciales
                        WHERE user_id = :user_id AND residencial_id = :residencial_id
                        LIMIT 1
                    ");
                    $stmtCheck->execute([
                        'user_id'        => $user_id,
                        'residencial_id' => $residencial_id,
                    ]);

                    if ($stmtCheck->fetch()) {
                        $errores[] = 'Ese usuario ya está asignado a ese residencial.';
                    }
                } catch (PDOException $e) {
                    $errores[] = 'Error al validar asignación previa: ' . $e->getMessage();
                }
            }

            if (!empty($errores)) {
                $error = implode(' ', $errores);
                $showAssignModal = true;
            } else {
                try {
                    if ($es_principal === 1) {
                        $stmtReset = $pdo->prepare("
                            UPDATE usuarios_residenciales
                            SET es_principal = 0
                            WHERE user_id = :user_id
                        ");
                        $stmtReset->execute(['user_id' => $user_id]);
                    }

                    $stmtInsert = $pdo->prepare("
                        INSERT INTO usuarios_residenciales (user_id, residencial_id, es_principal)
                        VALUES (:user_id, :residencial_id, :es_principal)
                    ");
                    $stmtInsert->execute([
                        'user_id'        => $user_id,
                        'residencial_id' => $residencial_id,
                        'es_principal'   => $es_principal,
                    ]);

                    header('Location: superadmin_usuarios.php?ok=assigned');
                    exit;
                } catch (PDOException $e) {
                    $error = 'Error al asignar usuario al residencial: ' . $e->getMessage();
                    $showAssignModal = true;
                }
            }
        }
    }
}

// Mensajes por GET
if (isset($_GET['ok'])) {
    if ($_GET['ok'] === 'user_created') $success = 'Usuario creado correctamente.';
    if ($_GET['ok'] === 'assigned')     $success = 'Asignación usuario-residencial creada correctamente.';
}

// 4) Filtros usuarios (GET)
$uq = trim($_GET['uq'] ?? '');
$urol = trim($_GET['urol'] ?? '');      // tipo_usuario_id
$ustatus = trim($_GET['ustatus'] ?? ''); // 1/0

// 5) Filtros asignaciones (GET)
$aq = trim($_GET['aq'] ?? '');
$aRes = trim($_GET['ares'] ?? ''); // residencial_id

// 6) Listado de usuarios existentes (filtrado)
$usuarios = [];
try {
    $sqlU = "
        SELECT u.id, u.name, u.email, u.is_active, u.created_at,
               t.nombre AS rol_nombre, t.id AS rol_id
        FROM users u
        JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
        WHERE 1=1
    ";
    $params = [];

    if ($uq !== '') {
        $sqlU .= " AND (u.name LIKE :uq OR u.email LIKE :uq)";
        $params['uq'] = "%{$uq}%";
    }
    if ($urol !== '') {
        $sqlU .= " AND u.tipo_usuario_id = :urol";
        $params['urol'] = (int)$urol;
    }
    if ($ustatus !== '') {
        $sqlU .= " AND u.is_active = :ustatus";
        $params['ustatus'] = (int)$ustatus;
    }

    $sqlU .= " ORDER BY u.created_at DESC";

    $stmtU = $pdo->prepare($sqlU);
    $stmtU->execute($params);
    $usuarios = $stmtU->fetchAll();
} catch (PDOException $e) {
    $error = 'Error al obtener la lista de usuarios: ' . $e->getMessage();
}

// 7) Listado de asignaciones usuario-residencial (filtrado)
$asignaciones = [];
try {
    $sqlA = "
        SELECT ur.id,
               u.name AS usuario_nombre,
               u.email AS usuario_email,
               t.nombre AS usuario_rol,
               r.nombre AS residencial_nombre,
               r.codigo AS residencial_codigo,
               r.id AS residencial_id,
               ur.es_principal,
               ur.created_at
        FROM usuarios_residenciales ur
        JOIN users u ON u.id = ur.user_id
        JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
        JOIN residenciales r ON r.id = ur.residencial_id
        WHERE 1=1
    ";
    $paramsA = [];

    if ($aq !== '') {
        $sqlA .= " AND (
            u.name LIKE :aq OR u.email LIKE :aq OR r.nombre LIKE :aq OR r.codigo LIKE :aq
        )";
        $paramsA['aq'] = "%{$aq}%";
    }
    if ($aRes !== '') {
        $sqlA .= " AND r.id = :ares";
        $paramsA['ares'] = (int)$aRes;
    }

    $sqlA .= " ORDER BY ur.created_at DESC";

    $stmtA = $pdo->prepare($sqlA);
    $stmtA->execute($paramsA);
    $asignaciones = $stmtA->fetchAll();
} catch (PDOException $e) {
    $error = 'Error al obtener las asignaciones usuario-residencial: ' . $e->getMessage();
}

// 8) Render contenido
ob_start();
?>

<div class="space-y-6">

    <?php if ($success): ?>
        <div class="rounded-2xl bg-emerald-500/15 border border-emerald-400/50 px-4 py-3 text-xs text-emerald-100">
            <?= h($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="rounded-2xl bg-rose-500/15 border border-rose-400/50 px-4 py-3 text-xs text-rose-100">
            <?= h($error) ?>
        </div>
    <?php endif; ?>

    <!-- Header + botones -->
    <div class="rounded-2xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4 md:p-5">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
            <div>
                <h4 class="text-sm font-semibold">Usuarios y asignaciones</h4>
                <p class="text-[11px] text-slate-300/80">Crea usuarios y asígnalos a residenciales.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="superadmin_usuarios.php?new=user"
                   class="inline-flex items-center gap-1 rounded-2xl bg-gradient-to-r from-sky-500 to-indigo-500 px-4 py-2 text-xs font-medium text-white shadow-lg shadow-sky-900/40">
                    + Crear usuario
                </a>
                <a href="superadmin_usuarios.php?new=assign"
                   class="inline-flex items-center gap-1 rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-500 px-4 py-2 text-xs font-medium text-white shadow-lg shadow-emerald-900/40">
                    + Asignar a residencial
                </a>
            </div>
        </div>

        <!-- Filtros Usuarios -->
        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
            <div class="flex items-center justify-between mb-2">
                <h5 class="text-xs font-semibold text-slate-100">Usuarios</h5>
                <span class="text-[11px] text-slate-300/80">Total: <?= count($usuarios) ?></span>
            </div>

            <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <div class="md:col-span-2">
                    <label class="block mb-1 text-slate-200 text-xs" for="uq">Buscar</label>
                    <input id="uq" name="uq" value="<?= h($uq) ?>"
                           class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50"
                           placeholder="Nombre o email...">
                </div>
                <div>
                    <label class="block mb-1 text-slate-200 text-xs" for="urol">Rol</label>
                    <select id="urol" name="urol"
                            class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
                        <option value="">Todos</option>
                        <?php foreach ($tiposUsuario as $t): ?>
                            <option value="<?= (int)$t['id'] ?>" <?= ((string)$urol === (string)$t['id']) ? 'selected' : '' ?>>
                                <?= h($t['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block mb-1 text-slate-200 text-xs" for="ustatus">Estado</label>
                    <select id="ustatus" name="ustatus"
                            class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
                        <option value="">Todos</option>
                        <option value="1" <?= ($ustatus === '1') ? 'selected' : '' ?>>Activo</option>
                        <option value="0" <?= ($ustatus === '0') ? 'selected' : '' ?>>Inactivo</option>
                    </select>
                </div>

                <div class="md:col-span-4 flex justify-end gap-2">
                    <a href="superadmin_usuarios.php"
                       class="px-4 py-2 rounded-2xl border border-white/20 bg-white/5 text-xs">
                        Limpiar
                    </a>
                    <button type="submit"
                            class="px-4 py-2 rounded-2xl bg-white/10 border border-white/20 text-xs font-medium hover:bg-white/15">
                        Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla Usuarios -->
    <div class="rounded-2xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4 md:p-5">
        <?php if (empty($usuarios)): ?>
            <p class="text-xs text-slate-400">No hay usuarios para mostrar.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs border-separate border-spacing-y-1">
                    <thead class="text-[11px] uppercase text-slate-300/80">
                        <tr>
                            <th class="text-left px-3 py-2">Nombre</th>
                            <th class="text-left px-3 py-2">Email</th>
                            <th class="text-left px-3 py-2">Rol</th>
                            <th class="text-left px-3 py-2">Estado</th>
                            <th class="text-left px-3 py-2">Creado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                            <tr class="bg-white/5 hover:bg-white/10 transition rounded-xl">
                                <td class="px-3 py-2 rounded-l-xl">
                                    <span class="font-medium text-slate-50"><?= h($u['name']) ?></span>
                                </td>
                                <td class="px-3 py-2 text-slate-200/90"><?= h($u['email']) ?></td>
                                <td class="px-3 py-2 text-slate-200/90"><?= h($u['rol_nombre']) ?></td>
                                <td class="px-3 py-2">
                                    <?php if ((int)$u['is_active'] === 1): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-400/40 text-[11px] text-emerald-100">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span> Activo
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-500/20 border border-slate-400/40 text-[11px] text-slate-200">
                                            Inactivo
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2 rounded-r-xl text-slate-300/80"><?= h($u['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Filtros Asignaciones -->
    <div class="rounded-2xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4 md:p-5">
        <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-semibold">Asignaciones usuario ↔ residencial</h4>
            <span class="text-[11px] text-slate-300/80">Total: <?= count($asignaciones) ?></span>
        </div>

        <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-4">
            <!-- preserva filtros de usuarios -->
            <input type="hidden" name="uq" value="<?= h($uq) ?>">
            <input type="hidden" name="urol" value="<?= h($urol) ?>">
            <input type="hidden" name="ustatus" value="<?= h($ustatus) ?>">

            <div class="md:col-span-2">
                <label class="block mb-1 text-slate-200 text-xs" for="aq">Buscar</label>
                <input id="aq" name="aq" value="<?= h($aq) ?>"
                       class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50"
                       placeholder="Usuario, email, residencial, código...">
            </div>

            <div>
                <label class="block mb-1 text-slate-200 text-xs" for="ares">Residencial</label>
                <select id="ares" name="ares"
                        class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
                    <option value="">Todos</option>
                    <?php foreach ($residencialesAll as $r): ?>
                        <option value="<?= (int)$r['id'] ?>" <?= ((string)$aRes === (string)$r['id']) ? 'selected' : '' ?>>
                            <?= h($r['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex items-end justify-end gap-2">
                <a href="superadmin_usuarios.php?uq=<?= urlencode($uq) ?>&urol=<?= urlencode($urol) ?>&ustatus=<?= urlencode($ustatus) ?>"
                   class="px-4 py-2 rounded-2xl border border-white/20 bg-white/5 text-xs">
                    Limpiar
                </a>
                <button type="submit"
                        class="px-4 py-2 rounded-2xl bg-white/10 border border-white/20 text-xs font-medium hover:bg-white/15">
                    Filtrar
                </button>
            </div>
        </form>

        <?php if (empty($asignaciones)): ?>
            <p class="text-xs text-slate-400">Aún no hay asignaciones registradas.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs border-separate border-spacing-y-1">
                    <thead class="text-[11px] uppercase text-slate-300/80">
                        <tr>
                            <th class="text-left px-3 py-2">Usuario</th>
                            <th class="text-left px-3 py-2">Residencial</th>
                            <th class="text-left px-3 py-2">Rol</th>
                            <th class="text-left px-3 py-2">Principal</th>
                            <th class="text-left px-3 py-2">Asignado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($asignaciones as $a): ?>
                            <tr class="bg-white/5 hover:bg-white/10 transition rounded-xl">
                                <td class="px-3 py-2 rounded-l-xl">
                                    <span class="font-medium text-slate-50"><?= h($a['usuario_nombre']) ?></span>
                                    <div class="text-[11px] text-slate-300/80"><?= h($a['usuario_email']) ?></div>
                                </td>
                                <td class="px-3 py-2">
                                    <span class="text-slate-200/90"><?= h($a['residencial_nombre']) ?></span>
                                    <div class="text-[11px] text-slate-300/80"><?= h($a['residencial_codigo']) ?></div>
                                </td>
                                <td class="px-3 py-2 text-slate-200/90"><?= h($a['usuario_rol']) ?></td>
                                <td class="px-3 py-2">
                                    <?php if ((int)$a['es_principal'] === 1): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-sky-500/20 border border-sky-400/50 text-[11px] text-sky-100">
                                            Sí
                                        </span>
                                    <?php else: ?>
                                        <span class="text-[11px] text-slate-300/80">No</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2 rounded-r-xl text-slate-300/80"><?= h($a['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- MODAL: Crear usuario -->
<?php if ($showCreateModal): ?>
<div class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm flex justify-center items-start overflow-y-auto">
  <div class="w-full max-w-3xl my-8 mx-2 md:mx-4 rounded-3xl border border-white/15 bg-slate-950/95 p-6 md:p-8 shadow-2xl">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h3 class="text-base font-semibold text-slate-50">Crear nuevo usuario</h3>
        <p class="text-[11px] text-slate-300/80">Completa la información y guarda.</p>
      </div>
      <button type="button" onclick="window.location.href='superadmin_usuarios.php'"
              class="h-8 w-8 rounded-full border border-white/20 bg-white/5 text-sm flex items-center justify-center">✕</button>
    </div>

    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <input type="hidden" name="form_type" value="crear_usuario">

      <div class="md:col-span-2">
        <label class="block mb-1 text-slate-200" for="nombre">Nombre completo *</label>
        <input type="text" id="nombre" name="nombre" required value="<?= h($formCreate['nombre']) ?>"
               class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50"
               placeholder="Ej. Juan Pérez">
      </div>

      <div class="md:col-span-2">
        <label class="block mb-1 text-slate-200" for="email">Correo electrónico *</label>
        <input type="email" id="email" name="email" required value="<?= h($formCreate['email']) ?>"
               class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50"
               placeholder="correo@ejemplo.com">
      </div>

      <div>
        <label class="block mb-1 text-slate-200" for="password">Contraseña *</label>
        <input type="password" id="password" name="password" required
               class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50"
               placeholder="Mínimo 6 caracteres">
      </div>

      <div>
        <label class="block mb-1 text-slate-200" for="password_confirm">Confirmar contraseña *</label>
        <input type="password" id="password_confirm" name="password_confirm" required
               class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
      </div>

      <div>
        <label class="block mb-1 text-slate-200" for="tipo_usuario_id">Rol / tipo de usuario *</label>
        <select id="tipo_usuario_id" name="tipo_usuario_id" required
                class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
          <option value="">Selecciona un rol</option>
          <?php foreach ($tiposUsuario as $tipo): ?>
            <option value="<?= (int)$tipo['id'] ?>" <?= ((string)$formCreate['tipo_usuario_id'] === (string)$tipo['id']) ? 'selected' : '' ?>>
              <?= h($tipo['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="flex items-center gap-2 mt-2">
        <label class="inline-flex items-center gap-2">
          <input type="checkbox" name="is_active" <?= ((int)$formCreate['is_active'] === 1) ? 'checked' : '' ?>
                 class="rounded border-white/30 bg-white/5 text-sky-500">
          <span class="text-[11px] text-slate-200">Usuario activo</span>
        </label>
      </div>

      <div class="md:col-span-2 flex justify-end gap-2 mt-2">
        <button type="button" onclick="window.location.href='superadmin_usuarios.php'"
                class="px-4 py-2 rounded-2xl border border-white/20 bg-white/5 text-[11px]">Cancelar</button>
        <button type="submit"
                class="px-4 py-2 rounded-2xl bg-gradient-to-r from-sky-500 to-indigo-500 text-[11px] font-medium text-white shadow-lg shadow-sky-900/40">
          Crear usuario
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- MODAL: Asignar usuario a residencial -->
<?php if ($showAssignModal): ?>
<div class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm flex justify-center items-start overflow-y-auto">
  <div class="w-full max-w-3xl my-8 mx-2 md:mx-4 rounded-3xl border border-white/15 bg-slate-950/95 p-6 md:p-8 shadow-2xl">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h3 class="text-base font-semibold text-slate-50">Asignar usuario a residencial</h3>
        <p class="text-[11px] text-slate-300/80">Selecciona usuario y residencial.</p>
      </div>
      <button type="button" onclick="window.location.href='superadmin_usuarios.php'"
              class="h-8 w-8 rounded-full border border-white/20 bg-white/5 text-sm flex items-center justify-center">✕</button>
    </div>

    <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <input type="hidden" name="form_type" value="asignar_residencial">

      <div>
        <label class="block mb-1 text-slate-200" for="user_id">Usuario *</label>
        <select id="user_id" name="user_id" required
                class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
          <option value="">Selecciona usuario</option>
          <?php foreach ($usuariosAll as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= ((string)$formAssign['user_id'] === (string)$u['id']) ? 'selected' : '' ?>>
              <?= h($u['name'] . ' (' . $u['email'] . ')') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block mb-1 text-slate-200" for="residencial_id">Residencial *</label>
        <select id="residencial_id" name="residencial_id" required
                class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
          <option value="">Selecciona residencial</option>
          <?php foreach ($residencialesAll as $r): ?>
            <option value="<?= (int)$r['id'] ?>" <?= ((string)$formAssign['residencial_id'] === (string)$r['id']) ? 'selected' : '' ?>>
              <?= h($r['nombre'] . ' (' . $r['codigo'] . ')') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="flex items-center gap-2 mt-2">
        <label class="inline-flex items-center gap-2">
          <input type="checkbox" name="es_principal" <?= ((int)$formAssign['es_principal'] === 1) ? 'checked' : '' ?>
                 class="rounded border-white/30 bg-white/5 text-sky-500">
          <span class="text-[11px] text-slate-200">Marcar como principal</span>
        </label>
      </div>

      <div class="md:col-span-3 flex justify-end gap-2 mt-2">
        <button type="button" onclick="window.location.href='superadmin_usuarios.php'"
                class="px-4 py-2 rounded-2xl border border-white/20 bg-white/5 text-[11px]">Cancelar</button>
        <button type="submit"
                class="px-4 py-2 rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-500 text-[11px] font-medium text-white shadow-lg shadow-emerald-900/40">
          Asignar
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
