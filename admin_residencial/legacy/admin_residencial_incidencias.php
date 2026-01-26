<?php
// admin_residencial/admin_residencial_incidencias.php
require_once __DIR__ . '/../config/auth.php';
require_role(['admin_residencial']);
require_once __DIR__ . '/../config/config.php';

$user       = current_user();
$pageTitle  = 'Incidencias';
$activeMenu = 'incidencias';

$success = '';
$error   = '';

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// ------------------------------------------------------
// 1) Obtener el residencial que administra este usuario
// ------------------------------------------------------
try {
    $stmtR = $pdo->prepare("
        SELECT r.*, ur.es_principal
        FROM usuarios_residenciales ur
        JOIN residenciales r ON r.id = ur.residencial_id
        WHERE ur.user_id = :user_id
        ORDER BY ur.es_principal DESC, ur.created_at ASC
        LIMIT 1
    ");
    $stmtR->execute(['user_id' => $user['id']]);
    $residencial = $stmtR->fetch();
} catch (PDOException $e) {
    $error = 'Error al obtener el residencial asignado: ' . $e->getMessage();
}

if (!$residencial) {
    ob_start(); ?>
    <div class="text-sm text-slate-200">
        Tu cuenta de administrador no tiene un residencial asignado.
        Contacta al super admin.
    </div>
    <?php
    $content = ob_get_clean();
    include __DIR__ . '/../layouts/dashboard_layout.php';
    exit;
}

$residencial_id = (int)$residencial['id'];

// ------------------------------------------------------
// 2) Detectar columnas opcionales (guardia_id) para evitar errores
// ------------------------------------------------------
$incidenciasTieneGuardiaId = false;
try {
    $stmtCol = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'incidencias'
          AND COLUMN_NAME = 'guardia_id'
    ");
    $stmtCol->execute();
    $incidenciasTieneGuardiaId = ((int)$stmtCol->fetchColumn() > 0);
} catch (Throwable $e) {
    $incidenciasTieneGuardiaId = false;
}

// ------------------------------------------------------
// 3) Unidades + Residentes + Guardias (para crear incidencia)
// ------------------------------------------------------
$unidades = [];
$residentes = [];
$guardias = [];

try {
    $stmtU = $pdo->prepare("
        SELECT id, clave, tipo
        FROM unidades
        WHERE residencial_id = :rid AND activo = 1
        ORDER BY clave ASC
    ");
    $stmtU->execute(['rid' => $residencial_id]);
    $unidades = $stmtU->fetchAll();
} catch (PDOException $e) {
    // no rompemos la página; solo mostramos error si hace falta
}

try {
    $stmtRes = $pdo->prepare("
        SELECT u.id, u.name, un.clave AS unidad_clave
        FROM usuarios_residenciales ur
        JOIN users u ON u.id = ur.user_id
        JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
        JOIN residentes_unidades ru ON ru.user_id = u.id
        JOIN unidades un ON un.id = ru.unidad_id
        WHERE ur.residencial_id = :rid
          AND t.nombre = 'residente'
        ORDER BY un.clave ASC, u.name ASC
    ");
    $stmtRes->execute(['rid' => $residencial_id]);
    $residentes = $stmtRes->fetchAll();
} catch (PDOException $e) {
}

if ($incidenciasTieneGuardiaId) {
    try {
        $stmtG = $pdo->prepare("
            SELECT u.id, u.name
            FROM usuarios_residenciales ur
            JOIN users u ON u.id = ur.user_id
            JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
            WHERE ur.residencial_id = :rid
              AND t.nombre = 'guardia'
            ORDER BY u.name ASC
        ");
        $stmtG->execute(['rid' => $residencial_id]);
        $guardias = $stmtG->fetchAll();
    } catch (PDOException $e) {
        $guardias = [];
    }
}

// ------------------------------------------------------
// 4) Acciones POST (crear incidencia / cambiar estado)
// ------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // 4.a) Crear incidencia
    if ($accion === 'crear_incidencia') {
        $titulo     = trim($_POST['titulo'] ?? '');
        $descripcion= trim($_POST['descripcion'] ?? '');
        $tipo       = trim($_POST['tipo'] ?? '');
        $prioridad  = trim($_POST['prioridad'] ?? 'media');
        $unidad_id  = (int)($_POST['unidad_id'] ?? 0);
        $residente_id = (int)($_POST['residente_id'] ?? 0);

        $guardia_id = 0;
        if ($incidenciasTieneGuardiaId) {
            $guardia_id = (int)($_POST['guardia_id'] ?? 0);
        }

        $errores = [];
        if ($titulo === '') $errores[] = 'El título es obligatorio.';
        if ($descripcion === '') $errores[] = 'La descripción es obligatoria.';
        if ($unidad_id <= 0) $errores[] = 'Debes seleccionar una unidad.';
        if ($residente_id <= 0) $errores[] = 'Debes seleccionar un residente.';

        $tiposValidos = ['seguridad','servicio','vecino','infraestructura','otro'];
        if (!in_array($tipo, $tiposValidos, true)) $errores[] = 'Tipo de incidencia no válido.';

        $prioridadesValidas = ['baja','media','alta'];
        if (!in_array($prioridad, $prioridadesValidas, true)) $errores[] = 'Prioridad no válida.';

        // Validar que unidad pertenece al residencial
        if ($unidad_id > 0) {
            $stmtCheckU = $pdo->prepare("
                SELECT COUNT(*) FROM unidades
                WHERE id = :uid AND residencial_id = :rid
            ");
            $stmtCheckU->execute(['uid' => $unidad_id, 'rid' => $residencial_id]);
            if ((int)$stmtCheckU->fetchColumn() === 0) $errores[] = 'La unidad seleccionada no pertenece a este residencial.';
        }

        // Validar que residente pertenece al residencial
        if ($residente_id > 0) {
            $stmtCheckR = $pdo->prepare("
                SELECT COUNT(*)
                FROM usuarios_residenciales ur
                JOIN users u ON u.id = ur.user_id
                JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
                WHERE ur.residencial_id = :rid
                  AND ur.user_id = :residente_id
                  AND t.nombre = 'residente'
            ");
            $stmtCheckR->execute(['rid' => $residencial_id, 'residente_id' => $residente_id]);
            if ((int)$stmtCheckR->fetchColumn() === 0) $errores[] = 'El residente seleccionado no pertenece a este residencial.';
        }

        // Validar guardia si aplica
        if ($incidenciasTieneGuardiaId && $guardia_id > 0) {
            $stmtCheckG = $pdo->prepare("
                SELECT COUNT(*)
                FROM usuarios_residenciales ur
                JOIN users u ON u.id = ur.user_id
                JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
                WHERE ur.residencial_id = :rid
                  AND ur.user_id = :guardia_id
                  AND t.nombre = 'guardia'
            ");
            $stmtCheckG->execute(['rid' => $residencial_id, 'guardia_id' => $guardia_id]);
            if ((int)$stmtCheckG->fetchColumn() === 0) $errores[] = 'El guardia seleccionado no pertenece a este residencial.';
        }

        if (!empty($errores)) {
            $error = implode(' ', $errores);
        } else {
            try {
                // Inserción (con o sin guardia_id)
                if ($incidenciasTieneGuardiaId) {
                    $stmtIns = $pdo->prepare("
                        INSERT INTO incidencias
                            (residencial_id, unidad_id, residente_id, guardia_id, titulo, descripcion, tipo, prioridad, estado, created_at)
                        VALUES
                            (:rid, :unidad_id, :residente_id, :guardia_id, :titulo, :descripcion, :tipo, :prioridad, 'abierta', NOW())
                    ");
                    $stmtIns->execute([
                        'rid'          => $residencial_id,
                        'unidad_id'    => $unidad_id,
                        'residente_id' => $residente_id,
                        'guardia_id'   => ($guardia_id > 0 ? $guardia_id : null),
                        'titulo'       => $titulo,
                        'descripcion'  => $descripcion,
                        'tipo'         => $tipo,
                        'prioridad'    => $prioridad,
                    ]);
                } else {
                    $stmtIns = $pdo->prepare("
                        INSERT INTO incidencias
                            (residencial_id, unidad_id, residente_id, titulo, descripcion, tipo, prioridad, estado, created_at)
                        VALUES
                            (:rid, :unidad_id, :residente_id, :titulo, :descripcion, :tipo, :prioridad, 'abierta', NOW())
                    ");
                    $stmtIns->execute([
                        'rid'          => $residencial_id,
                        'unidad_id'    => $unidad_id,
                        'residente_id' => $residente_id,
                        'titulo'       => $titulo,
                        'descripcion'  => $descripcion,
                        'tipo'         => $tipo,
                        'prioridad'    => $prioridad,
                    ]);
                }

                header('Location: admin_residencial_incidencias.php?ok=1');
                exit;
            } catch (PDOException $e) {
                $error = 'Error al crear la incidencia: ' . $e->getMessage();
            }
        }
    }

    // 4.b) Cambiar estado de incidencia
    if ($accion === 'cambiar_estado') {
        $incidencia_id = (int)($_POST['incidencia_id'] ?? 0);
        $nuevo_estado  = $_POST['estado'] ?? '';

        if ($incidencia_id <= 0 || !in_array($nuevo_estado, ['abierta', 'en_proceso', 'cerrada'], true)) {
            $error = 'Datos de estado no válidos.';
        } else {
            try {
                $stmtUpd = $pdo->prepare("
                    UPDATE incidencias
                    SET estado = :estado, updated_at = NOW()
                    WHERE id = :id AND residencial_id = :residencial_id
                ");
                $stmtUpd->execute([
                    'estado'         => $nuevo_estado,
                    'id'             => $incidencia_id,
                    'residencial_id' => $residencial_id,
                ]);

                if ($stmtUpd->rowCount() > 0) {
                    header('Location: admin_residencial_incidencias.php?ok_estado=1');
                    exit;
                } else {
                    $error = 'No se pudo actualizar la incidencia (verifica que pertenece a este residencial).';
                }
            } catch (PDOException $e) {
                $error = 'Error al actualizar el estado: ' . $e->getMessage();
            }
        }
    }
}

// mensajes tras redirect
if (isset($_GET['ok']) && !$success) $success = 'Incidencia creada correctamente.';
if (isset($_GET['ok_estado']) && !$success) $success = 'Estado de la incidencia actualizado correctamente.';

// ------------------------------------------------------
// 5) Filtros desde GET
// ------------------------------------------------------
$estadoFiltro    = $_GET['estado']    ?? '';
$prioridadFiltro = $_GET['prioridad'] ?? '';
$tipoFiltro      = $_GET['tipo']      ?? '';
$fdFiltro        = $_GET['fd']        ?? '';
$fhFiltro        = $_GET['fh']        ?? '';

$showModalNueva = (isset($_GET['modal']) && $_GET['modal'] === 'new');

// ------------------------------------------------------
// 6) Obtener incidencias del residencial (JOIN condicional guardia)
// ------------------------------------------------------
$incidencias = [];
try {
    $sql = "
        SELECT i.*,
               u.clave AS unidad_clave,
               us.name AS residente_nombre
    ";

    if ($incidenciasTieneGuardiaId) {
        $sql .= ", gu.name AS guardia_nombre ";
    }

    $sql .= "
        FROM incidencias i
        JOIN unidades u ON u.id = i.unidad_id
        JOIN users us ON us.id = i.residente_id
    ";

    if ($incidenciasTieneGuardiaId) {
        $sql .= " LEFT JOIN users gu ON gu.id = i.guardia_id ";
    }

    $sql .= " WHERE i.residencial_id = :residencial_id ";

    $params = ['residencial_id' => $residencial_id];

    if ($estadoFiltro !== '') {
        $sql .= " AND i.estado = :estado";
        $params['estado'] = $estadoFiltro;
    }
    if ($prioridadFiltro !== '') {
        $sql .= " AND i.prioridad = :prioridad";
        $params['prioridad'] = $prioridadFiltro;
    }
    if ($tipoFiltro !== '') {
        $sql .= " AND i.tipo = :tipo";
        $params['tipo'] = $tipoFiltro;
    }
    if ($fdFiltro !== '') {
        $sql .= " AND i.created_at >= :fd";
        $params['fd'] = $fdFiltro . ' 00:00:00';
    }
    if ($fhFiltro !== '') {
        $sql .= " AND i.created_at <= :fh";
        $params['fh'] = $fhFiltro . ' 23:59:59';
    }

    $sql .= " ORDER BY i.created_at DESC LIMIT 200";

    $stmtI = $pdo->prepare($sql);
    $stmtI->execute($params);
    $incidencias = $stmtI->fetchAll();
} catch (PDOException $e) {
    $error = 'Error al obtener las incidencias: ' . $e->getMessage();
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

    <!-- Encabezado (SIN el texto extra que querías eliminar) -->
    <div class="rounded-2xl border border-white/15 bg-white/5 backdrop-blur-xl p-4 md:p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h4 class="text-sm font-semibold">Incidencias</h4>
            <p class="text-[11px] text-slate-300/80">
                Residencial: <?= h($residencial['nombre']) ?>
            </p>
        </div>

        <a href="admin_residencial_incidencias.php?modal=new"
           class="inline-flex items-center gap-1 rounded-2xl bg-gradient-to-r from-sky-500 to-indigo-500 px-4 py-2 text-[11px] font-medium text-white shadow-lg shadow-sky-900/40">
            + Nueva incidencia
        </a>
    </div>

    <!-- Filtros -->
    <div class="rounded-2xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4 md:p-5">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-3 text-[11px]">
            <div>
                <label class="block mb-1 text-slate-200" for="estado">Estado</label>
                <select id="estado" name="estado"
                        class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
                    <option value="">Todos</option>
                    <option value="abierta"     <?= $estadoFiltro==='abierta'?'selected':'' ?>>Abiertas</option>
                    <option value="en_proceso"  <?= $estadoFiltro==='en_proceso'?'selected':'' ?>>En proceso</option>
                    <option value="cerrada"     <?= $estadoFiltro==='cerrada'?'selected':'' ?>>Cerradas</option>
                </select>
            </div>

            <div>
                <label class="block mb-1 text-slate-200" for="prioridad">Prioridad</label>
                <select id="prioridad" name="prioridad"
                        class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
                    <option value="">Todas</option>
                    <option value="baja"  <?= $prioridadFiltro==='baja'?'selected':'' ?>>Baja</option>
                    <option value="media" <?= $prioridadFiltro==='media'?'selected':'' ?>>Media</option>
                    <option value="alta"  <?= $prioridadFiltro==='alta'?'selected':'' ?>>Alta</option>
                </select>
            </div>

            <div>
                <label class="block mb-1 text-slate-200" for="tipo">Tipo</label>
                <select id="tipo" name="tipo"
                        class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
                    <option value="">Todos</option>
                    <option value="seguridad"       <?= $tipoFiltro==='seguridad'?'selected':'' ?>>Seguridad</option>
                    <option value="servicio"        <?= $tipoFiltro==='servicio'?'selected':'' ?>>Servicios</option>
                    <option value="vecino"          <?= $tipoFiltro==='vecino'?'selected':'' ?>>Vecino</option>
                    <option value="infraestructura" <?= $tipoFiltro==='infraestructura'?'selected':'' ?>>Infraestructura</option>
                    <option value="otro"            <?= $tipoFiltro==='otro'?'selected':'' ?>>Otro</option>
                </select>
            </div>

            <div>
                <label class="block mb-1 text-slate-200" for="fd">Desde</label>
                <input type="date" id="fd" name="fd"
                       value="<?= h($fdFiltro) ?>"
                       class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
            </div>

            <div>
                <label class="block mb-1 text-slate-200" for="fh">Hasta</label>
                <input type="date" id="fh" name="fh"
                       value="<?= h($fhFiltro) ?>"
                       class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
            </div>

            <div class="md:col-span-1 flex items-end justify-end">
                <button type="submit"
                        class="w-full md:w-auto rounded-2xl bg-white/10 border border-white/20 px-4 py-2 text-[11px] text-slate-50 hover:bg-white/15">
                    Filtrar
                </button>
            </div>
        </form>
    </div>

    <!-- Listado de incidencias -->
    <div class="rounded-3xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4 md:p-6 min-h-[50vh] flex flex-col">
        <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-semibold">Lista de incidencias</h4>
            <span class="text-[11px] text-slate-300/80">Total: <?= count($incidencias) ?></span>
        </div>

        <?php if (empty($incidencias)): ?>
            <p class="text-xs text-slate-400">No hay incidencias con los filtros seleccionados.</p>
        <?php else: ?>
            <div class="flex-1 space-y-3 overflow-y-auto">
                <?php foreach ($incidencias as $inc): ?>
                    <?php
                    $estado = $inc['estado'];
                    $claseEstado = $estado === 'abierta'
                        ? 'bg-amber-500/15 border-amber-400/40 text-amber-100'
                        : ($estado === 'en_proceso'
                            ? 'bg-sky-500/15 border-sky-400/40 text-sky-100'
                            : 'bg-emerald-500/15 border-emerald-400/40 text-emerald-100');
                    ?>
                    <div class="rounded-2xl bg-white/5 border border-white/12 px-4 py-3">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2 mb-1.5">
                            <div>
                                <div class="text-xs font-semibold text-slate-50">
                                    <?= h($inc['titulo']) ?>
                                </div>
                                <div class="text-[11px] text-slate-300/80">
                                    Unidad <?= h($inc['unidad_clave']) ?>
                                    · Residente: <?= h($inc['residente_nombre']) ?>
                                    <?php if ($incidenciasTieneGuardiaId): ?>
                                        <?php if (!empty($inc['guardia_nombre'])): ?>
                                            · Guardia: <?= h($inc['guardia_nombre']) ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-1 text-[11px]">
                                <span class="px-2 py-0.5 rounded-full bg-white/10 border border-white/20">
                                    <?= h(ucfirst($inc['tipo'])) ?>
                                </span>
                                <span class="px-2 py-0.5 rounded-full bg-white/10 border border-white/20">
                                    Prioridad: <?= h($inc['prioridad']) ?>
                                </span>
                                <span class="px-2 py-0.5 rounded-full border <?= $claseEstado ?>">
                                    <?= h(ucfirst(str_replace('_', ' ', $estado))) ?>
                                </span>
                            </div>
                        </div>

                        <div class="text-[11px] text-slate-200 mb-2 leading-relaxed">
                            <?= nl2br(h($inc['descripcion'])) ?>
                        </div>

                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2 text-[11px] text-slate-400">
                            <span>
                                Creada: <?= h($inc['created_at']) ?>
                                <?php if (!empty($inc['updated_at'])): ?>
                                    · Última actualización: <?= h($inc['updated_at']) ?>
                                <?php endif; ?>
                            </span>

                            <form method="POST" class="flex items-center gap-2">
                                <input type="hidden" name="accion" value="cambiar_estado">
                                <input type="hidden" name="incidencia_id" value="<?= (int)$inc['id'] ?>">

                                <select name="estado"
                                        class="rounded-2xl border border-white/20 bg-white/5 px-2 py-1 text-[11px] text-slate-50">
                                    <option value="abierta"    <?= $estado==='abierta'?'selected':'' ?>>Abierta</option>
                                    <option value="en_proceso" <?= $estado==='en_proceso'?'selected':'' ?>>En proceso</option>
                                    <option value="cerrada"    <?= $estado==='cerrada'?'selected':'' ?>>Cerrada</option>
                                </select>

                                <button type="submit"
                                        class="rounded-2xl bg-white/10 border border-white/20 px-3 py-1 text-[11px] text-slate-50 hover:bg-white/20">
                                    Guardar
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php if ($showModalNueva): ?>
<div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-xl">
    <div class="min-h-full flex items-center justify-center px-4 sm:px-6 py-8">
        <div class="relative w-full max-w-4xl rounded-3xl border border-white/15 bg-slate-950/95 shadow-2xl overflow-hidden">
            <div class="px-6 sm:px-8 pt-5 sm:pt-6 pb-6 sm:pb-7 flex flex-col gap-5">

                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg sm:text-xl font-semibold text-white">Nueva incidencia</h2>
                        <p class="text-[11px] sm:text-xs text-slate-300/85">
                            Registra una incidencia para este residencial.
                        </p>
                    </div>
                    <button type="button"
                            onclick="window.location.href='admin_residencial_incidencias.php'"
                            class="shrink-0 h-9 w-9 rounded-full bg-slate-900/80 border border-white/15 flex items-center justify-center text-slate-300 hover:bg-slate-800 hover:text-white transition">
                        ✕
                    </button>
                </div>

                <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs sm:text-sm text-slate-100">
                    <input type="hidden" name="accion" value="crear_incidencia">

                    <div class="md:col-span-2 flex flex-col gap-1.5">
                        <label class="text-[11px] font-medium text-slate-200">Título *</label>
                        <input type="text" name="titulo" required
                               class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60"
                               placeholder="Ej. Ruido excesivo / Fuga de agua / Portón dañado">
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-medium text-slate-200">Tipo *</label>
                        <select name="tipo" required
                                class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60">
                            <option value="seguridad">Seguridad</option>
                            <option value="servicio">Servicios</option>
                            <option value="vecino">Vecino</option>
                            <option value="infraestructura">Infraestructura</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-medium text-slate-200">Prioridad *</label>
                        <select name="prioridad" required
                                class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60">
                            <option value="baja">Baja</option>
                            <option value="media" selected>Media</option>
                            <option value="alta">Alta</option>
                        </select>
                    </div>

                    <div class="md:col-span-2 flex flex-col gap-1.5">
                        <label class="text-[11px] font-medium text-slate-200">Descripción *</label>
                        <textarea name="descripcion" rows="5" required
                                  class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60"
                                  placeholder="Describe lo ocurrido, lugar, hora aproximada, etc."></textarea>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-medium text-slate-200">Unidad *</label>
                        <select name="unidad_id" required
                                class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60">
                            <option value="">Selecciona una unidad</option>
                            <?php foreach ($unidades as $u): ?>
                                <option value="<?= (int)$u['id'] ?>">
                                    <?= h($u['clave'] . ' (' . $u['tipo'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="md:col-span-2 flex flex-col gap-1.5">
                        <label class="text-[11px] font-medium text-slate-200">Residente *</label>
                        <select name="residente_id" required
                                class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60">
                            <option value="">Selecciona un residente</option>
                            <?php foreach ($residentes as $r): ?>
                                <option value="<?= (int)$r['id'] ?>">
                                    <?= h($r['unidad_clave'] . ' · ' . $r['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($incidenciasTieneGuardiaId): ?>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[11px] font-medium text-slate-200">Guardia (opcional)</label>
                            <select name="guardia_id"
                                    class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60">
                                <option value="">(Ninguno)</option>
                                <?php foreach ($guardias as $g): ?>
                                    <option value="<?= (int)$g['id'] ?>"><?= h($g['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="md:col-span-3 flex justify-end gap-2 mt-2">
                        <button type="button"
                                onclick="window.location.href='admin_residencial_incidencias.php'"
                                class="rounded-2xl border border-white/15 bg-slate-900/80 px-4 py-2.5 text-[11px] font-medium text-slate-100 hover:bg-slate-800 transition">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="rounded-2xl bg-gradient-to-r from-sky-500 to-indigo-500 px-5 py-2.5 text-[11px] font-semibold text-white shadow-lg shadow-sky-900/40 hover:from-sky-400 hover:to-indigo-400 transition">
                            Crear incidencia
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
