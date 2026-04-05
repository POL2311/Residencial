<?php
// admin_residencial/comunicados.php
require_once __DIR__ . '/../config/auth.php';
require_role(['admin_residencial']);
require_once __DIR__ . '/../config/config.php';

$user       = current_user();
$pageTitle  = 'Comunicados del residencial';
$activeMenu = 'comunicados';

$success = '';
$error   = '';

// 1) Obtener residencial principal
try {
    $stmt = $pdo->prepare("
        SELECT r.*, ur.id AS ur_id
        FROM usuarios_residenciales ur
        JOIN residenciales r ON r.id = ur.residencial_id
        WHERE ur.user_id = :user_id
        ORDER BY ur.es_principal DESC, ur.created_at ASC
        LIMIT 1
    ");
    $stmt->execute(['user_id' => $user['id']]);
    $residencial = $stmt->fetch();
} catch (PDOException $e) {
    $error = 'Error al obtener tu residencial: ' . $e->getMessage();
}

if (!$residencial) {
    ob_start(); ?>
    <div class="text-sm text-slate-200">
        Tu usuario aún no está asignado a un residencial.
        Pide al superadmin que te vincule a uno.
    </div>
    <?php
    $content = ob_get_clean();
    include __DIR__ . '/../layouts/dashboard_layout.php';
    exit;
}

$residencialId = (int) $residencial['id'];

// 2) Acciones (crear / actualizar / eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)($_POST['comunicado_id'] ?? 0);
        if ($id > 0) {
            try {
                $stmtDel = $pdo->prepare("
                    DELETE FROM comunicados_residenciales
                    WHERE id = :id AND residencial_id = :residencial_id
                    LIMIT 1
                ");
                $stmtDel->execute([
                    'id'             => $id,
                    'residencial_id' => $residencialId,
                ]);
                $success = 'Comunicado eliminado correctamente.';
                header('Location: comunicados.php?ok=1');
                exit;
            } catch (PDOException $e) {
                $error = 'Error al eliminar el comunicado: ' . $e->getMessage();
            }
        }
    } elseif (in_array($action, ['create', 'update'], true)) {
        $titulo            = trim($_POST['titulo'] ?? '');
        $mensaje           = trim($_POST['mensaje'] ?? '');
        $tipo              = $_POST['tipo'] ?? 'general';
        $prioridad         = $_POST['prioridad'] ?? 'media';
        $fecha_publicacion = $_POST['fecha_publicacion'] ?? date('Y-m-d');
        $fecha_expiracion  = $_POST['fecha_expiracion'] ?? null;
        $estado            = $_POST['estado'] ?? 'publicado';
        $visible           = isset($_POST['visible_para_residentes']) ? 1 : 0;

        if ($titulo === '' || $mensaje === '') {
            $error = 'El título y el mensaje del comunicado son obligatorios.';
        } else {
            try {
                if ($action === 'create') {
                    $stmtIns = $pdo->prepare("
                        INSERT INTO comunicados_residenciales
                        (residencial_id, titulo, mensaje, tipo, prioridad,
                         fecha_publicacion, fecha_expiracion,
                         visible_para_residentes, estado,
                         creado_por, actualizado_por)
                        VALUES
                        (:residencial_id, :titulo, :mensaje, :tipo, :prioridad,
                         :fecha_publicacion, :fecha_expiracion,
                         :visible, :estado,
                         :user_id, :user_id)
                    ");
                    $stmtIns->execute([
                        'residencial_id'    => $residencialId,
                        'titulo'            => $titulo,
                        'mensaje'           => $mensaje,
                        'tipo'              => $tipo,
                        'prioridad'         => $prioridad,
                        'fecha_publicacion' => $fecha_publicacion,
                        'fecha_expiracion'  => $fecha_expiracion ?: null,
                        'visible'           => $visible,
                        'estado'            => $estado,
                        'user_id'           => $user['id'],
                    ]);
                    $success = 'Comunicado creado correctamente.';
                } else {
                    $id = (int)($_POST['comunicado_id'] ?? 0);
                    if ($id > 0) {
                        $stmtUp = $pdo->prepare("
                            UPDATE comunicados_residenciales
                            SET titulo = :titulo,
                                mensaje = :mensaje,
                                tipo = :tipo,
                                prioridad = :prioridad,
                                fecha_publicacion = :fecha_publicacion,
                                fecha_expiracion = :fecha_expiracion,
                                visible_para_residentes = :visible,
                                estado = :estado,
                                actualizado_por = :user_id
                            WHERE id = :id AND residencial_id = :residencial_id
                            LIMIT 1
                        ");
                        $stmtUp->execute([
                            'titulo'            => $titulo,
                            'mensaje'           => $mensaje,
                            'tipo'              => $tipo,
                            'prioridad'         => $prioridad,
                            'fecha_publicacion' => $fecha_publicacion,
                            'fecha_expiracion'  => $fecha_expiracion ?: null,
                            'visible'           => $visible,
                            'estado'            => $estado,
                            'user_id'           => $user['id'],
                            'id'                => $id,
                            'residencial_id'    => $residencialId,
                        ]);
                        $success = 'Comunicado actualizado correctamente.';
                    }
                }

                header('Location: comunicados.php?ok=1');
                exit;

            } catch (PDOException $e) {
                $error = 'Error al guardar el comunicado: ' . $e->getMessage();
            }
        }
    }
}

if (isset($_GET['ok']) && !$success) {
    $success = 'Cambios guardados correctamente.';
}

// 3) Filtros
$f_estado = $_GET['estado'] ?? 'todos';
$f_tipo   = $_GET['tipo']   ?? 'todos';

// 4) Obtener comunicados
$comunicados = [];
try {
    $sql = "
        SELECT *
        FROM comunicados_residenciales
        WHERE residencial_id = :residencial_id
    ";
    $params = ['residencial_id' => $residencialId];

    if ($f_estado !== 'todos') {
        $sql .= " AND estado = :estado";
        $params['estado'] = $f_estado;
    }
    if ($f_tipo !== 'todos') {
        $sql .= " AND tipo = :tipo";
        $params['tipo'] = $f_tipo;
    }

    $sql .= " ORDER BY fecha_publicacion DESC, created_at DESC";

    $stmtC = $pdo->prepare($sql);
    $stmtC->execute($params);
    $comunicados = $stmtC->fetchAll();
} catch (PDOException $e) {
    $error = 'Error al obtener los comunicados: ' . $e->getMessage();
}

// 5) Modal (new/edit)
$modalMode = null;
$editingComunicado = null;
if (isset($_GET['modal']) && $_GET['modal'] === 'new') {
    $modalMode = 'create';
} elseif (isset($_GET['edit'])) {
    $modalMode = 'edit';
    $editId = (int)$_GET['edit'];
    if ($editId > 0) {
        $stmtE = $pdo->prepare("
            SELECT *
            FROM comunicados_residenciales
            WHERE id = :id AND residencial_id = :residencial_id
            LIMIT 1
        ");
        $stmtE->execute([
            'id'             => $editId,
            'residencial_id' => $residencialId,
        ]);
        $editingComunicado = $stmtE->fetch();
        if (!$editingComunicado) {
            $modalMode = null;
        }
    }
}

ob_start();
?>

<div class="space-y-6 text-xs">

    <?php if ($success): ?>
        <div class="rounded-2xl bg-emerald-500/15 border border-emerald-400/50 px-4 py-3 text-emerald-100">
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="rounded-2xl bg-rose-500/15 border border-rose-400/50 px-4 py-3 text-rose-100">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <!-- Encabezado principal (igual que antes) -->
    <div class="rounded-2xl border border-white/15 bg-white/5 backdrop-blur-xl p-4 md:p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h4 class="text-sm font-semibold">
                Comunicados de <?= htmlspecialchars($residencial['nombre'], ENT_QUOTES, 'UTF-8') ?>
            </h4>
            <p class="text-[11px] text-slate-300/80">
                Envía avisos de mantenimiento, seguridad, pagos y anuncios generales a tus residentes.
            </p>
        </div>
        <div class="flex gap-2">
            <a href="comunicados.php?modal=new#lista"
               class="inline-flex items-center gap-1 rounded-2xl bg-gradient-to-r from-sky-500 to-indigo-500 px-4 py-2 text-[11px] font-medium text-white shadow-lg shadow-sky-900/40">
                + Nuevo comunicado
            </a>
        </div>
    </div>

    <!-- Filtros (mismo estilo) -->
    <div class="rounded-2xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4 md:p-5">
        <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-3 text-[11px]">
            <div>
                <label class="block mb-1 text-slate-200">Estado</label>
                <select name="estado"
                        class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
                    <option value="todos"      <?= $f_estado === 'todos' ? 'selected' : '' ?>>Todos</option>
                    <option value="publicado"  <?= $f_estado === 'publicado' ? 'selected' : '' ?>>Publicado</option>
                    <option value="borrador"   <?= $f_estado === 'borrador' ? 'selected' : '' ?>>Borrador</option>
                    <option value="archivado"  <?= $f_estado === 'archivado' ? 'selected' : '' ?>>Archivado</option>
                </select>
            </div>
            <div>
                <label class="block mb-1 text-slate-200">Tipo</label>
                <select name="tipo"
                        class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
                    <option value="todos"         <?= $f_tipo === 'todos' ? 'selected' : '' ?>>Todos</option>
                    <option value="general"       <?= $f_tipo === 'general' ? 'selected' : '' ?>>General</option>
                    <option value="mantenimiento" <?= $f_tipo === 'mantenimiento' ? 'selected' : '' ?>>Mantenimiento</option>
                    <option value="seguridad"     <?= $f_tipo === 'seguridad' ? 'selected' : '' ?>>Seguridad</option>
                    <option value="pagos"         <?= $f_tipo === 'pagos' ? 'selected' : '' ?>>Pagos / cuotas</option>
                    <option value="otro"          <?= $f_tipo === 'otro' ? 'selected' : '' ?>>Otro</option>
                </select>
            </div>
            <div class="md:col-span-2 flex items-end justify-end">
                <button type="submit"
                        class="inline-flex items-center gap-1 rounded-2xl bg-white/10 border border-white/20 px-4 py-2 text-[11px] text-slate-50 hover:bg-white/15">
                    Filtrar
                </button>
            </div>
        </form>
    </div>

    <div id="lista" class="rounded-3xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4 md:p-6 min-h-[50vh] flex flex-col">
        <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-semibold">Lista de comunicados</h4>
            <span class="text-[11px] text-slate-300/80">Total: <?= count($comunicados) ?></span>
        </div>

        <?php if (empty($comunicados)): ?>
            <p class="text-xs text-slate-400">Aún no has registrado comunicados.</p>
        <?php else: ?>
            <div class="flex-1 overflow-x-auto">
                <table class="min-w-full text-xs border-separate border-spacing-y-1">
                    <thead class="text-[11px] uppercase text-slate-300/80">
                    <tr>
                        <th class="text-left px-3 py-2">Título</th>
                        <th class="text-left px-3 py-2">Tipo</th>
                        <th class="text-left px-3 py-2">Prioridad</th>
                        <th class="text-left px-3 py-2">Publicación</th>
                        <th class="text-left px-3 py-2">Vigencia</th>
                        <th class="text-left px-3 py-2">Estado</th>
                        <th class="text-right px-3 py-2">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($comunicados as $c): ?>
                        <tr class="bg-white/5 hover:bg-white/10 transition rounded-xl">
                            <td class="px-3 py-2 rounded-l-xl max-w-xs">
                                <div class="font-medium text-slate-50 truncate">
                                    <?= htmlspecialchars($c['titulo'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div class="text-[11px] text-slate-300/80 line-clamp-1">
                                    <?= htmlspecialchars(mb_substr($c['mensaje'], 0, 80), ENT_QUOTES, 'UTF-8') ?>...
                                </div>
                            </td>
                            <td class="px-3 py-2 text-slate-200/90">
                                <?= htmlspecialchars($c['tipo'], ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td class="px-3 py-2 text-slate-200/90">
                                <?= htmlspecialchars($c['prioridad'], ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td class="px-3 py-2 text-slate-200/90">
                                <?= htmlspecialchars($c['fecha_publicacion'], ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td class="px-3 py-2 text-slate-200/90">
                                <?php if ($c['fecha_expiracion']): ?>
                                    <?= htmlspecialchars($c['fecha_expiracion'], ENT_QUOTES, 'UTF-8') ?>
                                <?php else: ?>
                                    <span class="text-[11px] text-slate-300/80">Sin fecha fin</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2">
                                <?php
                                $badgeCls = 'bg-sky-500/15 border-sky-400/40 text-sky-100';
                                if ($c['estado'] === 'borrador') {
                                    $badgeCls = 'bg-slate-500/20 border-slate-400/40 text-slate-100';
                                } elseif ($c['estado'] === 'archivado') {
                                    $badgeCls = 'bg-amber-500/15 border-amber-400/40 text-amber-100';
                                }
                                ?>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-[11px] <?= $badgeCls ?>">
                                    <?= htmlspecialchars(ucfirst($c['estado']), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td class="px-3 py-2 rounded-r-xl text-right">
                                <a href="comunicados.php?edit=<?= (int)$c['id'] ?>#lista"
                                   class="inline-flex items-center px-3 py-1 rounded-2xl bg-white/10 border border-white/20 text-[11px] text-slate-50 hover:bg-white/15">
                                    Editar
                                </a>

                                <form method="post" class="inline-block"
                                      onsubmit="return confirm('¿Eliminar este comunicado?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="comunicado_id" value="<?= (int)$c['id'] ?>">
                                    <button type="submit"
                                            class="ml-1 inline-flex items-center px-3 py-1 rounded-2xl bg-rose-500/15 border border-rose-400/40 text-[11px] text-rose-100 hover:bg-rose-500/25">
                                        Eliminar
                                    </button>
                                </form>
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
// 6) Modal crear/editar comunicado (mismo diseño que reglamento)
if ($modalMode):
    $c = $editingComunicado ?? [
        'id'                     => null,
        'titulo'                 => '',
        'mensaje'                => '',
        'tipo'                   => 'general',
        'prioridad'              => 'media',
        'fecha_publicacion'      => date('Y-m-d'),
        'fecha_expiracion'       => null,
        'visible_para_residentes'=> 1,
        'estado'                 => 'publicado',
    ];
    ?>
    <div class="fixed inset-0 z-40 bg-slate-950/80 backdrop-blur-sm overflow-y-auto">
        <div class="min-h-screen flex items-stretch px-2 md:px-8 py-4">
            <div class="w-full max-w-5xl mx-auto rounded-3xl border border-white/15 bg-slate-950/95 p-6 md:p-8 shadow-2xl flex flex-col">

                <!-- HEADER -->
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-base md:text-lg font-semibold text-slate-50">
                            <?= $modalMode === 'edit' ? 'Editar comunicado' : 'Nuevo comunicado' ?>
                        </h3>
                        <p class="text-[11px] text-slate-300/80">
                            <?= htmlspecialchars($residencial['nombre'], ENT_QUOTES, 'UTF-8') ?> · Texto que verán los residentes en su portal.
                        </p>
                    </div>
                    <button type="button"
                            onclick="window.location.href='comunicados.php#lista'"
                            class="h-8 w-8 rounded-full border border-white/20 bg-white/5 text-sm flex items-center justify-center">
                        ✕
                    </button>
                </div>

                <!-- FORM -->
                <form method="post" id="formComunicado" class="flex-1 flex flex-col gap-4 overflow-y-auto text-xs sm:text-sm">
                    <input type="hidden" name="action"
                           value="<?= $modalMode === 'edit' ? 'update' : 'create' ?>">
                    <?php if ($modalMode === 'edit' && $c['id']): ?>
                        <input type="hidden" name="comunicado_id" value="<?= (int)$c['id'] ?>">
                    <?php endif; ?>

                    <!-- Datos generales -->
                    <section class="space-y-4 rounded-2xl bg-slate-950/60 border border-white/10 px-4 md:px-5 py-4">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-[11px] font-semibold tracking-wide text-slate-200 uppercase">
                                Datos generales
                            </h3>
                            <span class="text-[10px] text-slate-400">Campos básicos del comunicado</span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex flex-col gap-1.5">
                                <label for="titulo" class="text-[11px] font-medium text-slate-200">
                                    Título <span class="text-rose-400">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="titulo"
                                    name="titulo"
                                    required
                                    value="<?= htmlspecialchars($c['titulo'], ENT_QUOTES, 'UTF-8') ?>"
                                    class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-xs sm:text-sm text-slate-50 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60"
                                    placeholder="Aviso de mantenimiento, corte de agua, etc."
                                >
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label for="tipo" class="text-[11px] font-medium text-slate-200">
                                    Tipo de comunicado
                                </label>
                                <select
                                    id="tipo"
                                    name="tipo"
                                    class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-xs sm:text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60"
                                >
                                    <option value="general" <?= $c['tipo'] === 'general' ? 'selected' : '' ?>>General</option>
                                    <option value="mantenimiento" <?= $c['tipo'] === 'mantenimiento' ? 'selected' : '' ?>>Mantenimiento</option>
                                    <option value="seguridad" <?= $c['tipo'] === 'seguridad' ? 'selected' : '' ?>>Seguridad</option>
                                    <option value="pagos" <?= $c['tipo'] === 'pagos' ? 'selected' : '' ?>>Pagos / cuotas</option>
                                    <option value="otro" <?= $c['tipo'] === 'otro' ? 'selected' : '' ?>>Otro</option>
                                </select>
                            </div>
                        </div>
                    </section>

                    <!-- Programación y visibilidad -->
                    <section class="space-y-4 rounded-2xl bg-slate-950/60 border border-white/10 px-4 md:px-5 py-4">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-[11px] font-semibold tracking-wide text-slate-200 uppercase">
                                Programación y visibilidad
                            </h3>
                            <span class="text-[10px] text-slate-400">Fechas, prioridad y estado</span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="flex flex-col gap-1.5">
                                <label for="prioridad" class="text-[11px] font-medium text-slate-200">
                                    Prioridad
                                </label>
                                <select
                                    id="prioridad"
                                    name="prioridad"
                                    class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-xs sm:text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60"
                                >
                                    <option value="baja"  <?= $c['prioridad'] === 'baja'  ? 'selected' : '' ?>>Baja</option>
                                    <option value="media" <?= $c['prioridad'] === 'media' ? 'selected' : '' ?>>Media</option>
                                    <option value="alta"  <?= $c['prioridad'] === 'alta'  ? 'selected' : '' ?>>Alta</option>
                                </select>
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label for="fecha_publicacion" class="text-[11px] font-medium text-slate-200">
                                    Fecha de publicación <span class="text-rose-400">*</span>
                                </label>
                                <input
                                    type="date"
                                    id="fecha_publicacion"
                                    name="fecha_publicacion"
                                    required
                                    value="<?= htmlspecialchars($c['fecha_publicacion'], ENT_QUOTES, 'UTF-8') ?>"
                                    class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-xs sm:text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60"
                                >
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label for="fecha_expiracion" class="text-[11px] font-medium text-slate-200">
                                    Fecha de expiración
                                </label>
                                <input
                                    type="date"
                                    id="fecha_expiracion"
                                    name="fecha_expiracion"
                                    value="<?= htmlspecialchars($c['fecha_expiracion'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-xs sm:text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60"
                                >
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
                            <div class="flex flex-col gap-1.5">
                                <label for="estado" class="text-[11px] font-medium text-slate-200">
                                    Estado del comunicado
                                </label>
                                <select
                                    id="estado"
                                    name="estado"
                                    class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-xs sm:text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60"
                                >
                                    <option value="publicado" <?= $c['estado'] === 'publicado' ? 'selected' : '' ?>>Publicado</option>
                                    <option value="borrador"  <?= $c['estado'] === 'borrador'  ? 'selected' : '' ?>>Borrador</option>
                                    <option value="archivado" <?= $c['estado'] === 'archivado' ? 'selected' : '' ?>>Archivado</option>
                                </select>
                            </div>

                            <div class="flex items-center">
                                <label class="inline-flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        name="visible_para_residentes"
                                        class="h-4 w-4 rounded border-slate-400/60 bg-slate-900 text-sky-500 focus:ring-sky-500/70"
                                        <?= (int)$c['visible_para_residentes'] === 1 ? 'checked' : '' ?>
                                    >
                                    <span class="text-[11px] sm:text-xs text-slate-200">
                                        Mostrar este comunicado en el portal del residente
                                    </span>
                                </label>
                            </div>
                        </div>
                    </section>

                    <!-- Mensaje -->
                    <section class="space-y-3 rounded-2xl bg-slate-950/65 border border-white/10 px-4 md:px-5 py-4">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-[11px] sm:text-xs font-semibold tracking-wide text-slate-200 uppercase">
                                Mensaje para residentes
                            </h3>
                            <span class="text-[10px] text-slate-400">
                                Redacta el contenido tal como aparecerá en el portal.
                            </span>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label for="mensaje" class="text-[11px] font-medium text-slate-200">
                                Mensaje <span class="text-rose-400">*</span>
                            </label>
                            <textarea
                                id="mensaje"
                                name="mensaje"
                                required
                                rows="6"
                                class="w-full rounded-2xl bg-black/40 border border-white/12 px-3.5 py-3 text-xs sm:text-sm text-slate-50 leading-relaxed resize-none focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60"
                                placeholder="Texto que verán los residentes en su portal."
                            ><?= htmlspecialchars($c['mensaje'], ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                    </section>
                </form>

                <!-- FOOTER -->
                <div class="mt-4 flex justify-end gap-2">
                    <button
                        type="button"
                        onclick="window.location.href='comunicados.php#lista'"
                        class="px-4 py-2 rounded-2xl border border-white/20 bg-white/5 text-[11px]"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        form="formComunicado"
                        class="px-4 py-2 rounded-2xl bg-gradient-to-r from-sky-500 to-indigo-500 text-[11px] font-medium text-white shadow-lg shadow-sky-900/40"
                    >
                        <?= $modalMode === 'edit' ? 'Guardar cambios' : 'Crear comunicado' ?>
                    </button>
                </div>

            </div>
        </div>
    </div>
<?php
endif;


$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
