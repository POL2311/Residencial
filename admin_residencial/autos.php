<?php
// admin_residencial/autos.php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/config.php';

// Permitir 3 roles
require_role(['admin_residencial', 'guardia', 'residente']);

$user       = current_user();
$pageTitle  = 'Autos';
$activeMenu = 'autos';

$success = '';
$error   = '';

// Detectar rol (ajusta aquí si tu app usa otra llave)
$role = $user['role'] ?? ($user['tipo_usuario'] ?? ($user['tipo_usuario_nombre'] ?? ''));

// Helpers de permiso
$canManageAll = ($role === 'admin_residencial');
$canReadOnly  = ($role === 'guardia');
$isResidente  = ($role === 'residente');

// 1) Obtener residencial principal del usuario (admin/guardia/residente)
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
    $error = 'Error al obtener el residencial: ' . $e->getMessage();
}

if (!$residencial) {
    ob_start(); ?>
    <div class="text-sm text-slate-200">
        No tienes residencial asignado. Contacta al administrador.
    </div>
    <?php
    $content = ob_get_clean();
    include __DIR__ . '/../layouts/dashboard_layout.php';
    exit;
}

$residencialId = (int)$residencial['id'];

// 2) Para residentes: obtener su unidad asignada (para restringir y para auto-asignación)
$residenteUnidadId = null;
$residenteUnidadClave = null;

if ($isResidente) {
    try {
        $stmtRU = $pdo->prepare("
            SELECT ru.unidad_id, u.clave
            FROM residentes_unidades ru
            JOIN unidades u ON u.id = ru.unidad_id
            WHERE ru.user_id = :uid
            LIMIT 1
        ");
        $stmtRU->execute(['uid' => (int)$user['id']]);
        $ruRow = $stmtRU->fetch();
        if ($ruRow) {
            $residenteUnidadId = (int)$ruRow['unidad_id'];
            $residenteUnidadClave = $ruRow['clave'];
        }
    } catch (PDOException $e) {
        // no rompemos la pantalla
    }
}

// 3) Datos para selects (admin solamente necesita escoger propietario/unidad)
$unidades = [];
$residentes = [];

if ($canManageAll) {
    try {
        $stmtU = $pdo->prepare("
            SELECT id, clave, tipo
            FROM unidades
            WHERE residencial_id = :rid
            ORDER BY clave ASC
        ");
        $stmtU->execute(['rid' => $residencialId]);
        $unidades = $stmtU->fetchAll();
    } catch (PDOException $e) {
        $error = 'Error al cargar unidades: ' . $e->getMessage();
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
            ORDER BY u.name ASC
        ");
        $stmtRes->execute(['rid' => $residencialId]);
        $residentes = $stmtRes->fetchAll();
    } catch (PDOException $e) {
        $error = 'Error al cargar residentes: ' . $e->getMessage();
    }
}

// 4) Modal create/edit state
$showModal = isset($_GET['modal']) && in_array($_GET['modal'], ['new','edit'], true);
$modalMode = $_GET['modal'] ?? '';
$editId    = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Form state
$formAuto = [
    'placas'  => '',
    'modelo'  => '',
    'color'   => '',
    'unidad_id' => '',
    'propietario_user_id' => '',
];

// Si edit: cargar auto
$autoEdit = null;
if ($showModal && $modalMode === 'edit' && $editId > 0) {
    try {
        $stmtE = $pdo->prepare("
            SELECT a.*
            FROM autos a
            WHERE a.id = :id AND a.residencial_id = :rid
            LIMIT 1
        ");
        $stmtE->execute(['id' => $editId, 'rid' => $residencialId]);
        $autoEdit = $stmtE->fetch();

        if (!$autoEdit) {
            $error = 'Auto no encontrado.';
            $showModal = false;
        } else {
            // Permisos edit:
            if ($canReadOnly) {
                $error = 'Tu rol no puede editar autos.';
                $showModal = false;
            } elseif ($isResidente && (int)$autoEdit['propietario_user_id'] !== (int)$user['id']) {
                $error = 'No puedes editar autos que no son tuyos.';
                $showModal = false;
            } else {
                $formAuto['placas']  = (string)($autoEdit['placas'] ?? '');
                $formAuto['modelo']  = (string)($autoEdit['modelo'] ?? '');
                $formAuto['color']   = (string)($autoEdit['color'] ?? '');
                $formAuto['unidad_id'] = (int)($autoEdit['unidad_id'] ?? 0);
                $formAuto['propietario_user_id'] = (int)($autoEdit['propietario_user_id'] ?? 0);
            }
        }
    } catch (PDOException $e) {
        $error = 'Error al cargar auto: ' . $e->getMessage();
        $showModal = false;
    }
}

// 5) Acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // 5.a) Crear / Editar
    if (in_array($accion, ['crear_auto', 'editar_auto'], true)) {
        if ($canReadOnly) {
            $error = 'Tu rol no puede registrar ni editar autos.';
        } else {
            $placas = strtoupper(trim($_POST['placas'] ?? ''));
            $modelo = trim($_POST['modelo'] ?? '');
            $color  = trim($_POST['color'] ?? '');

            // Reglas de asignación:
            if ($canManageAll) {
                $unidad_id = (int)($_POST['unidad_id'] ?? 0);
                $propietario_user_id = (int)($_POST['propietario_user_id'] ?? 0);
            } else {
                // residente: siempre asigna a él mismo y a su unidad
                $propietario_user_id = (int)$user['id'];
                $unidad_id = (int)$residenteUnidadId;
            }

            // Para mantener valores si hay error y reabrir modal
            $formAuto['placas'] = $placas;
            $formAuto['modelo'] = $modelo;
            $formAuto['color']  = $color;
            $formAuto['unidad_id'] = $unidad_id;
            $formAuto['propietario_user_id'] = $propietario_user_id;

            $errs = [];
            if ($placas === '') $errs[] = 'Las placas son obligatorias.';
            if (strlen($placas) < 5) $errs[] = 'Placas muy cortas.';
            if (!$unidad_id) $errs[] = 'Unidad inválida.';
            if (!$propietario_user_id) $errs[] = 'Propietario inválido.';

            // Validar que unidad pertenece al residencial
            if ($unidad_id > 0) {
                $stmtChkU = $pdo->prepare("SELECT COUNT(*) FROM unidades WHERE id=:uid AND residencial_id=:rid");
                $stmtChkU->execute(['uid'=>$unidad_id,'rid'=>$residencialId]);
                if ((int)$stmtChkU->fetchColumn() === 0) $errs[] = 'La unidad no pertenece al residencial.';
            }

            // Validar que propietario sea residente del residencial (solo admin)
            if ($canManageAll && $propietario_user_id > 0) {
                $stmtChkP = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM usuarios_residenciales ur
                    JOIN users u ON u.id = ur.user_id
                    JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
                    WHERE ur.residencial_id = :rid
                      AND ur.user_id = :uid
                      AND t.nombre = 'residente'
                ");
                $stmtChkP->execute(['rid'=>$residencialId,'uid'=>$propietario_user_id]);
                if ((int)$stmtChkP->fetchColumn() === 0) $errs[] = 'El propietario debe ser un residente del residencial.';
            }

            // Checar duplicado de placas dentro del residencial (recomendado)
            if (empty($errs)) {
                if ($accion === 'crear_auto') {
                    $stmtDup = $pdo->prepare("SELECT id FROM autos WHERE residencial_id=:rid AND placas=:placas LIMIT 1");
                    $stmtDup->execute(['rid'=>$residencialId,'placas'=>$placas]);
                } else {
                    $auto_id = (int)($_POST['auto_id'] ?? 0);
                    $stmtDup = $pdo->prepare("SELECT id FROM autos WHERE residencial_id=:rid AND placas=:placas AND id <> :id LIMIT 1");
                    $stmtDup->execute(['rid'=>$residencialId,'placas'=>$placas,'id'=>$auto_id]);
                }
                if ($stmtDup->fetch()) $errs[] = 'Ya existe un auto con esas placas en el residencial.';
            }

            if (!empty($errs)) {
                $error = implode(' ', $errs);
                $showModal = true;
                $modalMode = ($accion === 'crear_auto') ? 'new' : 'edit';
                if ($accion === 'editar_auto') {
                    $editId = (int)($_POST['auto_id'] ?? 0);
                }
            } else {
                try {
                    if ($accion === 'crear_auto') {
                        $stmtIns = $pdo->prepare("
                            INSERT INTO autos (residencial_id, unidad_id, propietario_user_id, placas, modelo, color)
                            VALUES (:rid, :unidad_id, :propietario_user_id, :placas, :modelo, :color)
                        ");
                        $stmtIns->execute([
                            'rid' => $residencialId,
                            'unidad_id' => $unidad_id,
                            'propietario_user_id' => $propietario_user_id,
                            'placas' => $placas,
                            'modelo' => $modelo !== '' ? $modelo : null,
                            'color'  => $color !== '' ? $color : null,
                        ]);
                        header('Location: autos.php?ok=1');
                        exit;
                    } else {
                        $auto_id = (int)($_POST['auto_id'] ?? 0);
                        if ($auto_id <= 0) throw new Exception('Auto inválido.');

                        // Permisos update
                        if ($isResidente) {
                            $stmtOwn = $pdo->prepare("SELECT propietario_user_id FROM autos WHERE id=:id AND residencial_id=:rid LIMIT 1");
                            $stmtOwn->execute(['id'=>$auto_id,'rid'=>$residencialId]);
                            $own = $stmtOwn->fetchColumn();
                            if ((int)$own !== (int)$user['id']) throw new Exception('No puedes editar autos que no son tuyos.');
                        }

                        $stmtUpd = $pdo->prepare("
                            UPDATE autos
                            SET unidad_id = :unidad_id,
                                propietario_user_id = :propietario_user_id,
                                placas = :placas,
                                modelo = :modelo,
                                color = :color
                            WHERE id = :id AND residencial_id = :rid
                        ");
                        $stmtUpd->execute([
                            'unidad_id' => $unidad_id,
                            'propietario_user_id' => $propietario_user_id,
                            'placas' => $placas,
                            'modelo' => $modelo !== '' ? $modelo : null,
                            'color'  => $color !== '' ? $color : null,
                            'id' => $auto_id,
                            'rid' => $residencialId,
                        ]);

                        header('Location: autos.php?ok_edit=1');
                        exit;
                    }
                } catch (Exception $e) {
                    $error = 'Error: ' . $e->getMessage();
                    $showModal = true;
                } catch (PDOException $e) {
                    $error = 'Error DB: ' . $e->getMessage();
                    $showModal = true;
                }
            }
        }
    }

    // 5.b) Eliminar
    if ($accion === 'eliminar_auto') {
        if ($canReadOnly) {
            $error = 'Tu rol no puede eliminar autos.';
        } else {
            $auto_id = (int)($_POST['auto_id'] ?? 0);
            if ($auto_id <= 0) {
                $error = 'Auto inválido.';
            } else {
                try {
                    // Permisos delete
                    if ($isResidente) {
                        $stmtOwn = $pdo->prepare("SELECT propietario_user_id FROM autos WHERE id=:id AND residencial_id=:rid LIMIT 1");
                        $stmtOwn->execute(['id'=>$auto_id,'rid'=>$residencialId]);
                        $own = $stmtOwn->fetchColumn();
                        if ((int)$own !== (int)$user['id']) {
                            throw new Exception('No puedes eliminar autos que no son tuyos.');
                        }
                    }

                    $stmtDel = $pdo->prepare("DELETE FROM autos WHERE id=:id AND residencial_id=:rid");
                    $stmtDel->execute(['id'=>$auto_id,'rid'=>$residencialId]);

                    header('Location: autos.php?ok_del=1');
                    exit;
                } catch (Exception $e) {
                    $error = 'Error: ' . $e->getMessage();
                } catch (PDOException $e) {
                    $error = 'Error DB: ' . $e->getMessage();
                }
            }
        }
    }
}

// Mensajes tras redirect
if (isset($_GET['ok']) && !$success)      $success = 'Auto registrado correctamente.';
if (isset($_GET['ok_edit']) && !$success) $success = 'Auto actualizado correctamente.';
if (isset($_GET['ok_del']) && !$success)  $success = 'Auto eliminado correctamente.';

// 6) Filtros (GET)
$qPlacas = trim($_GET['placas'] ?? '');
$qModelo = trim($_GET['modelo'] ?? '');
$qUnidad = (int)($_GET['unidad_id'] ?? 0);
$qProp   = (int)($_GET['propietario_user_id'] ?? 0);

// 7) Listado de autos
$autos = [];
try {
    $sql = "
        SELECT a.id, a.placas, a.modelo, a.color, a.unidad_id, a.propietario_user_id, a.created_at,
               un.clave AS unidad_clave,
               u.name AS propietario_nombre
        FROM autos a
        JOIN unidades un ON un.id = a.unidad_id
        LEFT JOIN users u ON u.id = a.propietario_user_id
        WHERE a.residencial_id = :rid
    ";
    $params = ['rid' => $residencialId];

    // Restricciones por rol
    if ($isResidente) {
        $sql .= " AND a.propietario_user_id = :myid";
        $params['myid'] = (int)$user['id'];
    }

    if ($qPlacas !== '') {
        $sql .= " AND a.placas LIKE :placas";
        $params['placas'] = '%' . $qPlacas . '%';
    }
    if ($qModelo !== '') {
        $sql .= " AND a.modelo LIKE :modelo";
        $params['modelo'] = '%' . $qModelo . '%';
    }
    if ($qUnidad > 0) {
        $sql .= " AND a.unidad_id = :unidad_id";
        $params['unidad_id'] = $qUnidad;
    }
    if ($qProp > 0 && $canManageAll) {
        $sql .= " AND a.propietario_user_id = :prop";
        $params['prop'] = $qProp;
    }

    $sql .= " ORDER BY a.created_at DESC LIMIT 300";

    $stmtA = $pdo->prepare($sql);
    $stmtA->execute($params);
    $autos = $stmtA->fetchAll();
} catch (PDOException $e) {
    $error = 'Error al obtener autos: ' . $e->getMessage();
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

    <!-- Header -->
    <div class="rounded-2xl border border-white/15 bg-white/5 backdrop-blur-xl p-4 md:p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h4 class="text-sm font-semibold">Autos · <?= htmlspecialchars($residencial['nombre'], ENT_QUOTES, 'UTF-8') ?></h4>
            <p class="text-[11px] text-slate-300/80">
                <?= $isResidente ? 'Administra tus autos registrados.' : 'Listado para control de acceso y administración.' ?>
            </p>
        </div>

        <?php if (!$canReadOnly): ?>
            <a href="autos.php?modal=new"
               class="inline-flex items-center gap-1 rounded-2xl bg-gradient-to-r from-sky-500 to-indigo-500 px-4 py-2 text-[11px] font-medium text-white shadow-lg shadow-sky-900/40">
                + Agregar auto
            </a>
        <?php endif; ?>
    </div>

    <!-- Filtros -->
    <div class="rounded-2xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4 md:p-5">
        <form method="GET" class="flex flex-wrap gap-3 items-end text-[11px]">

            <div>
                <label class="block mb-1" for="placas">Placas</label>
                <input id="placas" name="placas" value="<?= htmlspecialchars($qPlacas, ENT_QUOTES, 'UTF-8') ?>"
                       class="rounded-xl border border-white/15 bg-white/5 px-2 py-1" placeholder="ABC-123">
            </div>

            <div>
                <label class="block mb-1" for="modelo">Modelo</label>
                <input id="modelo" name="modelo" value="<?= htmlspecialchars($qModelo, ENT_QUOTES, 'UTF-8') ?>"
                       class="rounded-xl border border-white/15 bg-white/5 px-2 py-1" placeholder="Mazda 3">
            </div>

            <?php if ($canManageAll): ?>
                <div>
                    <label class="block mb-1" for="unidad_id">Unidad</label>
                    <select id="unidad_id" name="unidad_id" class="rounded-xl border border-white/15 bg-white/5 px-2 py-1">
                        <option value="0">Todas</option>
                        <?php foreach ($unidades as $u): ?>
                            <option value="<?= (int)$u['id'] ?>" <?= $qUnidad === (int)$u['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['clave'] . ' (' . $u['tipo'] . ')', ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block mb-1" for="propietario_user_id">Propietario</label>
                    <select id="propietario_user_id" name="propietario_user_id" class="rounded-xl border border-white/15 bg-white/5 px-2 py-1">
                        <option value="0">Todos</option>
                        <?php foreach ($residentes as $r): ?>
                            <option value="<?= (int)$r['id'] ?>" <?= $qProp === (int)$r['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r['name'] . ' · ' . $r['unidad_clave'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <button type="submit" class="rounded-xl bg-white/10 border border-white/20 px-3 py-1 font-medium">
                Filtrar
            </button>

            <a href="autos.php" class="rounded-xl bg-white/5 border border-white/15 px-3 py-1 font-medium text-slate-200">
                Limpiar
            </a>

        </form>
    </div>

    <!-- Listado -->
    <div class="rounded-2xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4 md:p-5">
        <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-semibold">Listado</h4>
            <span class="text-[11px] text-slate-300/80">Total: <?= count($autos) ?></span>
        </div>

        <?php if (empty($autos)): ?>
            <p class="text-xs text-slate-400">No hay autos con los filtros seleccionados.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs border-separate border-spacing-y-1">
                    <thead class="text-[11px] uppercase text-slate-300/80">
                    <tr>
                        <th class="text-left px-3 py-2">Placas</th>
                        <th class="text-left px-3 py-2">Modelo</th>
                        <th class="text-left px-3 py-2">Color</th>
                        <th class="text-left px-3 py-2">Propietario</th>
                        <th class="text-left px-3 py-2">Casa / Unidad</th>
                        <th class="text-right px-3 py-2">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($autos as $a): ?>
                        <?php
                        $isOwner = $isResidente && ((int)$a['propietario_user_id'] === (int)$user['id']);
                        $canEditThis = !$canReadOnly && ($canManageAll || $isOwner);
                        ?>
                        <tr class="bg-white/5 hover:bg-white/10 transition rounded-xl">
                            <td class="px-3 py-2 rounded-l-xl font-medium text-slate-50">
                                <?= htmlspecialchars($a['placas'], ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td class="px-3 py-2 text-slate-200/90">
                                <?= htmlspecialchars($a['modelo'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td class="px-3 py-2 text-slate-200/90">
                                <?= htmlspecialchars($a['color'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td class="px-3 py-2 text-slate-200/90">
                                <?= htmlspecialchars($a['propietario_nombre'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td class="px-3 py-2 text-slate-200/90">
                                <?= htmlspecialchars($a['unidad_clave'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td class="px-3 py-2 rounded-r-xl text-right">
                                <div class="inline-flex gap-2">
                                    <?php if ($canEditThis): ?>
                                        <a href="autos.php?modal=edit&id=<?= (int)$a['id'] ?>"
                                           class="inline-flex items-center px-3 py-1 rounded-2xl bg-white/10 border border-white/20 text-[11px] text-slate-50 hover:bg-white/15">
                                            Editar
                                        </a>

                                        <form method="POST" onsubmit="return confirm('¿Eliminar este auto?');">
                                            <input type="hidden" name="accion" value="eliminar_auto">
                                            <input type="hidden" name="auto_id" value="<?= (int)$a['id'] ?>">
                                            <button type="submit"
                                                    class="inline-flex items-center px-3 py-1 rounded-2xl bg-rose-500/15 border border-rose-400/40 text-[11px] text-rose-100 hover:bg-rose-500/25">
                                                Eliminar
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-[11px] text-slate-400">—</span>
                                    <?php endif; ?>
                                </div>
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
// ------------------ MODAL (New/Edit) estilo tu UI ------------------
if ($showModal && !$canReadOnly):
    $isEdit = ($modalMode === 'edit');
    $title  = $isEdit ? 'Editar auto' : 'Agregar auto';
    $subtitle = $isEdit ? 'Actualiza la información del vehículo.' : 'Registra un auto para control de acceso.';
?>
<div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-xl">
    <div class="min-h-full flex items-center justify-center px-4 sm:px-6 py-8">
        <div class="relative w-full max-w-4xl rounded-3xl border border-white/15 bg-slate-950/95 shadow-2xl overflow-hidden">
            <div class="px-6 sm:px-8 pt-5 sm:pt-6 pb-6 sm:pb-7 flex flex-col gap-5">

                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg sm:text-xl font-semibold text-white"><?= $title ?></h2>
                        <p class="text-[11px] sm:text-xs text-slate-300/85"><?= $subtitle ?></p>
                    </div>
                    <button type="button"
                            onclick="window.location.href='autos.php'"
                            class="shrink-0 h-9 w-9 rounded-full bg-slate-900/80 border border-white/15 flex items-center justify-center text-slate-300 hover:bg-slate-800 hover:text-white transition">
                        ✕
                    </button>
                </div>

                <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs sm:text-sm text-slate-100">
                    <input type="hidden" name="accion" value="<?= $isEdit ? 'editar_auto' : 'crear_auto' ?>">
                    <?php if ($isEdit): ?>
                        <input type="hidden" name="auto_id" value="<?= (int)$editId ?>">
                    <?php endif; ?>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-medium text-slate-200">Placas *</label>
                        <input name="placas" required
                               value="<?= htmlspecialchars($formAuto['placas'], ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-xs sm:text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60"
                               placeholder="ABC-123">
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-medium text-slate-200">Modelo</label>
                        <input name="modelo"
                               value="<?= htmlspecialchars($formAuto['modelo'], ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-xs sm:text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60"
                               placeholder="Ej. Versa 2020">
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-medium text-slate-200">Color</label>
                        <input name="color"
                               value="<?= htmlspecialchars($formAuto['color'], ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-xs sm:text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60"
                               placeholder="Ej. Blanco">
                    </div>

                    <?php if ($canManageAll): ?>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[11px] font-medium text-slate-200">Unidad *</label>
                            <select name="unidad_id" required
                                    class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-xs sm:text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60">
                                <option value="">Selecciona</option>
                                <?php foreach ($unidades as $u): ?>
                                    <option value="<?= (int)$u['id'] ?>" <?= (int)$formAuto['unidad_id'] === (int)$u['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($u['clave'] . ' (' . $u['tipo'] . ')', ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="md:col-span-2 flex flex-col gap-1.5">
                            <label class="text-[11px] font-medium text-slate-200">Propietario (residente) *</label>
                            <select name="propietario_user_id" required
                                    class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-xs sm:text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60">
                                <option value="">Selecciona</option>
                                <?php foreach ($residentes as $r): ?>
                                    <option value="<?= (int)$r['id'] ?>" <?= (int)$formAuto['propietario_user_id'] === (int)$r['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($r['name'] . ' · ' . $r['unidad_clave'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <!-- Residente: bloqueado a su propia unidad y su usuario -->
                        <div class="md:col-span-3 rounded-2xl border border-white/12 bg-white/5 px-4 py-3 text-[11px] text-slate-200/90">
                            Se registrará para tu cuenta
                            <?= $residenteUnidadClave ? (' y unidad <strong>' . htmlspecialchars($residenteUnidadClave, ENT_QUOTES, 'UTF-8') . '</strong>.') : '.' ?>
                        </div>
                    <?php endif; ?>

                    <div class="md:col-span-3 flex justify-end gap-2 mt-2">
                        <button type="button"
                                onclick="window.location.href='autos.php'"
                                class="rounded-2xl border border-white/15 bg-slate-900/80 px-4 py-2.5 text-[11px] sm:text-xs font-medium text-slate-100 hover:bg-slate-800 transition">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="rounded-2xl bg-gradient-to-r from-sky-500 to-indigo-500 px-5 sm:px-6 py-2.5 text-[11px] sm:text-xs font-semibold text-white shadow-lg shadow-sky-900/40 hover:from-sky-400 hover:to-indigo-400 transition">
                            <?= $isEdit ? 'Guardar cambios' : 'Registrar auto' ?>
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
