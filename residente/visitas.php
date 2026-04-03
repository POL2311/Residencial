<?php
// residente/visitas.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

require_login();
require_role(['residente']);

$user       = current_user();
$pageTitle  = 'Mis visitas';
$activeMenu = 'visitas';

$success   = '';
$error     = '';
$showModal = false;
$modalMode = 'create';
$editingVisit = null;

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function is_disabled_state($estado){
    return in_array((string)$estado, ['usado','vencido','cancelado'], true);
}

// 1) Obtener residencial + unidad principal del residente
try {
    $stmtRU = $pdo->prepare("
        SELECT r.*, u.id AS unidad_id, u.clave AS unidad_clave
        FROM usuarios_residenciales ur
        JOIN residenciales r        ON r.id = ur.residencial_id
        JOIN residentes_unidades ru ON ru.user_id = ur.user_id
        JOIN unidades u             ON u.id = ru.unidad_id
        WHERE ur.user_id = :user_id
        ORDER BY ur.es_principal DESC, ur.created_at ASC
        LIMIT 1
    ");
    $stmtRU->execute(['user_id' => $user['id']]);
    $contexto = $stmtRU->fetch();
} catch (PDOException $e) {
    $error    = 'Error al obtener tu residencial: ' . $e->getMessage();
    $contexto = null;
}

if (!$contexto) {
    ob_start(); ?>
    <div class="text-sm text-slate-200">
        Tu cuenta de residente aún no está ligada a un residencial y una unidad.<br>
        Contacta al administrador del residencial para que te asigne.
    </div>
    <?php
    $content = ob_get_clean();
    include __DIR__ . '/../layouts/dashboard_layout.php';
    exit;
}

$residencial = $contexto;
$unidad_id   = (int)$contexto['unidad_id'];

// 2) Procesar acciones POST (crear / actualizar / eliminar / cancelar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['create', 'update'], true)) {

        $tipo             = $_POST['tipo'] ?? 'visita';
        $nombre_visitante = trim($_POST['nombre_visitante'] ?? '');
        $motivo           = trim($_POST['motivo'] ?? '');
        $placa            = trim($_POST['placa'] ?? '');
        $fecha_desde      = $_POST['fecha_desde'] ?? '';
        $fecha_hasta      = $_POST['fecha_hasta'] ?? '';
        $hora_desde       = $_POST['hora_desde'] ?? '';
        $hora_hasta       = $_POST['hora_hasta'] ?? '';
        $uso_unico        = isset($_POST['uso_unico']) ? 1 : 0;

        $errores = [];
        if ($nombre_visitante === '') $errores[] = 'El nombre de la visita es obligatorio.';
        if ($fecha_desde === '') $errores[] = 'La fecha de inicio es obligatoria.';
        if ($fecha_hasta === '') $errores[] = 'La fecha de fin es obligatoria.';
        if ($fecha_desde !== '' && $fecha_hasta !== '' && $fecha_hasta < $fecha_desde) {
            $errores[] = 'La fecha de fin no puede ser anterior a la de inicio.';
        }

        if (!empty($errores)) {
            $error     = implode(' ', $errores);
            $showModal = true;
            $modalMode = $action === 'update' ? 'edit' : 'create';
            if ($action === 'update') $editingVisit = $_POST;
        } else {
            try {
                if ($action === 'create') {
                    $codigo = bin2hex(random_bytes(5)); // 10 caracteres

                    $stmtIns = $pdo->prepare("
                        INSERT INTO visitas (
                            residencial_id, unidad_id, residente_id, tipo,
                            nombre_visitante, motivo, placa_vehiculo,
                            fecha_desde, fecha_hasta, hora_desde, hora_hasta,
                            uso_unico, codigo_acceso, estado, notas
                        ) VALUES (
                            :residencial_id, :unidad_id, :residente_id, :tipo,
                            :nombre_visitante, :motivo, :placa,
                            :fecha_desde, :fecha_hasta, :hora_desde, :hora_hasta,
                            :uso_unico, :codigo_acceso, 'pendiente', :notas
                        )
                    ");
                    $stmtIns->execute([
                        'residencial_id'   => $residencial['id'],
                        'unidad_id'        => $unidad_id,
                        'residente_id'     => $user['id'],
                        'tipo'             => $tipo,
                        'nombre_visitante' => $nombre_visitante,
                        'motivo'           => $motivo !== '' ? $motivo : null,
                        'placa'            => $placa !== '' ? $placa : null,
                        'fecha_desde'      => $fecha_desde,
                        'fecha_hasta'      => $fecha_hasta,
                        'hora_desde'       => $hora_desde !== '' ? $hora_desde : null,
                        'hora_hasta'       => $hora_hasta !== '' ? $hora_hasta : null,
                        'uso_unico'        => $uso_unico,
                        'codigo_acceso'    => $codigo,
                        'notas'            => null,
                    ]);

                    header('Location: visitas.php?ok=created#historial');
                    exit;
                }

                if ($action === 'update') {
                    $visita_id = (int)($_POST['visita_id'] ?? 0);

                    $stmtChk = $pdo->prepare("SELECT * FROM visitas WHERE id = :id AND residente_id = :uid LIMIT 1");
                    $stmtChk->execute(['id' => $visita_id, 'uid' => $user['id']]);
                    $row = $stmtChk->fetch();

                    if (!$row) {
                        $error = 'La visita no existe o no te pertenece.';
                    } else {
                        if (is_disabled_state($row['estado'] ?? '')) {
                            $error = 'No puedes editar una visita en estado: ' . ($row['estado'] ?? '');
                        } else {
                            $stmtUpd = $pdo->prepare("
                                UPDATE visitas
                                SET tipo = :tipo,
                                    nombre_visitante = :nombre_visitante,
                                    motivo = :motivo,
                                    placa_vehiculo = :placa,
                                    fecha_desde = :fecha_desde,
                                    fecha_hasta = :fecha_hasta,
                                    hora_desde = :hora_desde,
                                    hora_hasta = :hora_hasta,
                                    uso_unico = :uso_unico
                                WHERE id = :id AND residente_id = :uid
                            ");
                            $stmtUpd->execute([
                                'tipo'             => $tipo,
                                'nombre_visitante' => $nombre_visitante,
                                'motivo'           => $motivo !== '' ? $motivo : null,
                                'placa'            => $placa !== '' ? $placa : null,
                                'fecha_desde'      => $fecha_desde,
                                'fecha_hasta'      => $fecha_hasta,
                                'hora_desde'       => $hora_desde !== '' ? $hora_desde : null,
                                'hora_hasta'       => $hora_hasta !== '' ? $hora_hasta : null,
                                'uso_unico'        => $uso_unico,
                                'id'               => $visita_id,
                                'uid'              => $user['id'],
                            ]);

                            header('Location: visitas.php?ok=updated#historial');
                            exit;
                        }
                    }
                }
            } catch (Exception $e) {
                $error     = 'Error al guardar la visita: ' . $e->getMessage();
                $showModal = true;
                $modalMode = $action === 'update' ? 'edit' : 'create';
            }
        }

    } elseif ($action === 'delete') {
        $visita_id = (int)($_POST['visita_id'] ?? 0);

        try {
            $stmtChk = $pdo->prepare("SELECT id FROM visitas WHERE id = :id AND residente_id = :uid LIMIT 1");
            $stmtChk->execute(['id' => $visita_id, 'uid' => $user['id']]);
            $row = $stmtChk->fetch();

            if (!$row) {
                $error = 'La visita no existe o no te pertenece.';
            } else {
                $stmtDel = $pdo->prepare("DELETE FROM visitas WHERE id = :id AND residente_id = :uid");
                $stmtDel->execute(['id' => $visita_id, 'uid' => $user['id']]);

                header('Location: visitas.php?ok=deleted#historial');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Error al eliminar la visita: ' . $e->getMessage();
        }

    } elseif ($action === 'cancel') {
        $visita_id = (int)($_POST['visita_id'] ?? 0);

        try {
            $stmtChk = $pdo->prepare("SELECT estado FROM visitas WHERE id = :id AND residente_id = :uid LIMIT 1");
            $stmtChk->execute(['id' => $visita_id, 'uid' => $user['id']]);
            $row = $stmtChk->fetch();

            if (!$row) {
                $error = 'La visita no existe o no te pertenece.';
            } else {
                if (($row['estado'] ?? '') === 'usado') {
                    $error = 'No puedes cancelar una visita que ya fue usada.';
                } elseif (($row['estado'] ?? '') === 'cancelado') {
                    $error = 'Esta visita ya está cancelada.';
                } else {
                    $stmtUpd = $pdo->prepare("UPDATE visitas SET estado = 'cancelado' WHERE id = :id AND residente_id = :uid");
                    $stmtUpd->execute(['id' => $visita_id, 'uid' => $user['id']]);

                    header('Location: visitas.php?ok=cancelled#historial');
                    exit;
                }
            }
        } catch (PDOException $e) {
            $error = 'Error al cancelar la visita: ' . $e->getMessage();
        }
    }
}

// 3) Mensajes por GET
if (isset($_GET['ok'])) {
    if ($_GET['ok'] === 'created')      $success = 'Visita creada correctamente.';
    elseif ($_GET['ok'] === 'updated')  $success = 'Visita actualizada correctamente.';
    elseif ($_GET['ok'] === 'deleted')  $success = 'Visita eliminada correctamente.';
    elseif ($_GET['ok'] === 'cancelled')$success = 'Acceso cancelado correctamente.';
}

// 4) Apertura de modal por GET
if (isset($_GET['new'])) {
    $showModal = true;
    $modalMode = 'create';
}

if (isset($_GET['edit'])) {
    $visita_id = (int)$_GET['edit'];
    try {
        $stmtE = $pdo->prepare("SELECT * FROM visitas WHERE id = :id AND residente_id = :uid LIMIT 1");
        $stmtE->execute(['id' => $visita_id, 'uid' => $user['id']]);
        $editingVisit = $stmtE->fetch();

        if ($editingVisit) {
            if (is_disabled_state($editingVisit['estado'] ?? '')) {
                $error = 'No puedes editar una visita en estado: ' . ($editingVisit['estado'] ?? '');
                $editingVisit = null;
                $showModal = false;
            } else {
                $showModal = true;
                $modalMode = 'edit';
            }
        }
    } catch (PDOException $e) {
        $error = 'Error al obtener la visita para edición: ' . $e->getMessage();
    }
}

// 5) Filtros para el historial
$f_estado = $_GET['estado'] ?? 'todos';
$f_desde  = $_GET['f_desde'] ?? '';
$f_hasta  = $_GET['f_hasta'] ?? '';

$visitas = [];
try {
    $sql    = "SELECT * FROM visitas WHERE residente_id = :uid";
    $params = ['uid' => $user['id']];

    if ($f_estado !== '' && $f_estado !== 'todos') {
        $sql .= " AND estado = :estado";
        $params['estado'] = $f_estado;
    }
    if ($f_desde !== '') { $sql .= " AND fecha_desde >= :fdesde"; $params['fdesde'] = $f_desde; }
    if ($f_hasta !== '') { $sql .= " AND fecha_hasta <= :fhasta"; $params['fhasta'] = $f_hasta; }

    $sql  .= " ORDER BY created_at DESC";
    $stmtV = $pdo->prepare($sql);
    $stmtV->execute($params);
    $visitas = $stmtV->fetchAll();
} catch (PDOException $e) {
    $error = 'Error al obtener tus visitas: ' . $e->getMessage();
}

ob_start();
?>

<div class="space-y-6 text-xs" id="historial">

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

    <div class="rounded-2xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4 md:p-5 min-h-[70vh]">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
            <div>
                <h4 class="text-sm font-semibold">Historial de visitas</h4>
                <p class="text-[11px] text-slate-300/80">
                    Residencial <?= h($residencial['nombre']) ?> · unidad <?= h($contexto['unidad_clave']) ?>
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="visitas.php?new=1#historial"
                   class="inline-flex items-center gap-1 rounded-2xl bg-gradient-to-r from-sky-500 to-indigo-500 px-4 py-2 text-xs font-medium text-white shadow-lg shadow-sky-900/40">
                    + Nueva visita
                </a>
            </div>
        </div>

        <form method="get" class="grid grid-cols-1 sm:grid-cols-4 gap-3 mb-4">
            <div>
                <label class="block mb-1 text-slate-200" for="estado">Estado</label>
                <select id="estado" name="estado"
                        class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
                    <option value="todos"     <?= $f_estado === 'todos' ? 'selected' : '' ?>>Todos</option>
                    <option value="pendiente" <?= $f_estado === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                    <option value="usado"     <?= $f_estado === 'usado' ? 'selected' : '' ?>>Usado</option>
                    <option value="vencido"   <?= $f_estado === 'vencido' ? 'selected' : '' ?>>Vencido</option>
                    <option value="cancelado" <?= $f_estado === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                </select>
            </div>
            <div>
                <label class="block mb-1 text-slate-200" for="f_desde">Desde</label>
                <input type="date" id="f_desde" name="f_desde"
                       value="<?= h($f_desde) ?>"
                       class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
            </div>
            <div>
                <label class="block mb-1 text-slate-200" for="f_hasta">Hasta</label>
                <input type="date" id="f_hasta" name="f_hasta"
                       value="<?= h($f_hasta) ?>"
                       class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
            </div>
            <div class="flex items-end">
                <button type="submit"
                        class="w-full rounded-2xl bg-white/10 border border-white/20 px-3 py-2 text-xs font-medium hover:bg-white/15">
                    Filtrar
                </button>
            </div>
        </form>

        <?php if (empty($visitas)): ?>
            <p class="text-xs text-slate-400">Aún no has registrado visitas.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs border-separate border-spacing-y-1">
                    <thead class="text-[11px] uppercase text-slate-300/80">
                    <tr>
                        <th class="text-left px-3 py-2">Visita</th>
                        <th class="text-left px-3 py-2">Vigencia</th>
                        <th class="text-left px-3 py-2">Tipo</th>
                        <th class="text-left px-3 py-2">Código</th>
                        <th class="text-left px-3 py-2">Estado</th>
                        <th class="text-right px-3 py-2">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($visitas as $v): ?>
                        <?php
                            $code = (string)($v['codigo_acceso'] ?? '');
                            $linkRel = "ver_qr.php?code=" . urlencode($code);
                            $estado = (string)($v['estado'] ?? '');
                            $isDisabled = is_disabled_state($estado);

                            $badge = 'bg-slate-500/20 border-slate-400/40 text-slate-200';
                            if ($estado === 'pendiente')  $badge = 'bg-sky-500/15 border-sky-400/40 text-sky-100';
                            if ($estado === 'usado')      $badge = 'bg-emerald-500/15 border-emerald-400/40 text-emerald-100';
                            if ($estado === 'vencido')    $badge = 'bg-slate-500/20 border-slate-400/40 text-slate-200';
                            if ($estado === 'cancelado')  $badge = 'bg-rose-500/15 border-rose-400/40 text-rose-100';
                        ?>
                        <tr class="bg-white/5 hover:bg-white/10 transition rounded-xl">
                            <td class="px-3 py-2 rounded-l-xl">
                                <div class="font-medium text-slate-50"><?= h($v['nombre_visitante'] ?? '') ?></div>
                                <?php if (!empty($v['motivo'])): ?>
                                    <div class="text-[11px] text-slate-300/80"><?= h($v['motivo']) ?></div>
                                <?php endif; ?>
                            </td>

                            <td class="px-3 py-2 text-slate-200/90">
                                <?= h($v['fecha_desde'] ?? '') ?>
                                <?php if (($v['fecha_hasta'] ?? '') !== ($v['fecha_desde'] ?? '')): ?>
                                    – <?= h($v['fecha_hasta'] ?? '') ?>
                                <?php endif; ?>
                                <?php if (!empty($v['hora_desde']) || !empty($v['hora_hasta'])): ?>
                                    <div class="text-[11px] text-slate-300/80">
                                        <?= h($v['hora_desde'] ?? '--') ?> – <?= h($v['hora_hasta'] ?? '--') ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="px-3 py-2 text-slate-200/90"><?= h($v['tipo'] ?? '') ?></td>

                            <td class="px-3 py-2 text-slate-200/90">
                                <code class="px-2 py-1 rounded-lg bg-black/40 text-[11px]"><?= h($code) ?></code>
                            </td>

                            <td class="px-3 py-2">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border <?= $badge ?> text-[11px]">
                                    <?= h($estado) ?>
                                </span>
                            </td>

                            <td class="px-3 py-2 rounded-r-xl text-right">
                                <div class="inline-flex flex-wrap gap-2 justify-end">

                                <button type="button"
                                onclick='openQRModal(<?= json_encode($code) ?>, <?= json_encode($v["nombre_visitante"] ?? "Visita") ?>)'
                                class="px-3 py-1.5 rounded-2xl border border-white/20 bg-white/5 text-[11px] hover:bg-white/10">
                                Ver QR
                                </button>

                                <button type="button"
                                onclick='copyLink(<?= json_encode($linkRel) ?>)'
                                class="px-3 py-1.5 rounded-2xl border border-white/20 bg-white/5 text-[11px] hover:bg-white/10">
                                Copiar link
                                </button>

                                <button type="button"
                                onclick='shareWhatsApp(<?= json_encode($linkRel) ?>, <?= json_encode($v["nombre_visitante"] ?? "Visita") ?>)'
                                class="px-3 py-1.5 rounded-2xl border border-white/20 bg-white/5 text-[11px] hover:bg-white/10">
                                WhatsApp
                                </button>


                                    <a href="visitas.php?edit=<?= (int)($v['id'] ?? 0) ?>#historial"
                                       class="px-3 py-1.5 rounded-2xl border border-white/20 bg-white/5 text-[11px] hover:bg-white/10 <?= $isDisabled ? 'pointer-events-none opacity-40' : '' ?>">
                                        Editar
                                    </a>

                                    <form method="post" onsubmit="return confirm('¿Cancelar este acceso?');">
                                        <input type="hidden" name="action" value="cancel">
                                        <input type="hidden" name="visita_id" value="<?= (int)($v['id'] ?? 0) ?>">
                                        <button type="submit"
                                                <?= $isDisabled ? 'disabled' : '' ?>
                                                class="px-3 py-1.5 rounded-2xl border border-amber-400/60 bg-amber-500/10 text-[11px] text-amber-100 hover:bg-amber-500/20 disabled:opacity-40 disabled:cursor-not-allowed">
                                            Cancelar
                                        </button>
                                    </form>

                                    <form method="post" onsubmit="return confirm('¿Eliminar esta visita?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="visita_id" value="<?= (int)($v['id'] ?? 0) ?>">
                                        <button type="submit"
                                                class="px-3 py-1.5 rounded-2xl border border-rose-400/60 bg-rose-500/10 text-[11px] text-rose-100 hover:bg-rose-500/20">
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
    </div>
</div>

<!-- TOAST -->
<div id="toast"
     class="hidden fixed bottom-5 right-5 z-50 rounded-2xl border border-white/15 bg-slate-950/90 px-4 py-3 text-[11px] text-slate-100 shadow-2xl">
</div>

<!-- MODAL QR (SIEMPRE DISPONIBLE) -->
<div id="qrModal" class="hidden fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center">
  <div class="bg-slate-950 border border-white/20 rounded-3xl w-full max-w-md p-6 relative">
    <button type="button" onclick="closeQRModal()"
      class="absolute top-3 right-3 h-8 w-8 rounded-full bg-white/10 border border-white/20 text-slate-100">
      ✕
    </button>

    <h3 class="text-sm font-semibold text-slate-50 mb-1">Código de acceso</h3>
    <p id="qrNombre" class="text-[11px] text-slate-300 mb-4"></p>

    <div class="flex justify-center mb-4">
      <img id="qrImg" class="rounded-2xl border border-white/20 bg-white p-2" alt="QR">
    </div>

    <div class="text-center mb-3">
      <code id="qrCode" class="px-3 py-1 rounded-lg bg-black/40 text-xs text-slate-100"></code>
    </div>

    <div class="grid grid-cols-2 gap-2">
      <button type="button" onclick="copyQRLink()"
        class="rounded-2xl bg-white/10 border border-white/20 py-2 text-xs text-slate-100">
        Copiar link
      </button>

      <button type="button" onclick="shareQRWhatsApp()"
        class="rounded-2xl bg-emerald-500/15 border border-emerald-400/40 py-2 text-xs text-emerald-100">
        WhatsApp
      </button>
    </div>
  </div>
</div>

<script>
let currentQRLink = '';
let currentQRName = '';

function toast(msg){
  const t = document.getElementById('toast');
  if(!t) return;
  t.textContent = msg;
  t.classList.remove('hidden');
  setTimeout(()=>t.classList.add('hidden'), 1400);
}

function absUrl(rel){
  const base = window.location.origin + window.location.pathname.replace(/[^\/]+$/, '');
  return new URL(rel, base).toString();
}

async function copyText(text){
  try {
    await navigator.clipboard.writeText(text);
    toast('Copiado ✅');
  } catch {
    toast('Error al copiar ❌');
  }
}

function copyLink(rel){
  copyText(absUrl(rel));
}

function shareWhatsApp(rel, nombre){
  const text = `Acceso para visita: ${nombre}\n\n${absUrl(rel)}`;
  window.open(`https://wa.me/?text=${encodeURIComponent(text)}`, '_blank');
}

function openQRModal(code, nombre){
  currentQRName = nombre;
  currentQRLink = absUrl('ver_qr.php?code=' + code);

  document.getElementById('qrNombre').textContent = nombre;
  document.getElementById('qrCode').textContent = code;
  document.getElementById('qrImg').src =
    'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' +
    encodeURIComponent(currentQRLink);

  document.getElementById('qrModal').classList.remove('hidden');
}

function closeQRModal(){
  document.getElementById('qrModal').classList.add('hidden');
}

function copyQRLink(){
  copyText(currentQRLink);
}

function shareQRWhatsApp(){
  const text = `Acceso para visita: ${currentQRName}\n\n${currentQRLink}`;
  window.open(`https://wa.me/?text=${encodeURIComponent(text)}`, '_blank');
}
</script>


<?php
// 7) Modal (crear / editar)
if ($showModal):

$mv = $editingVisit ?? [
    'nombre_visitante' => '',
    'motivo'           => '',
    'placa_vehiculo'   => '',
    'fecha_desde'      => '',
    'fecha_hasta'      => '',
    'hora_desde'       => '',
    'hora_hasta'       => '',
    'tipo'             => 'visita',
    'uso_unico'        => 1,
];
?>
<div class="fixed inset-0 z-40 bg-slate-950/60 backdrop-blur-sm flex justify-center items-start overflow-y-auto">
    <div class="w-full max-w-5xl my-8 mx-2 md:mx-4 rounded-3xl border border-white/15 bg-slate-950/95 p-6 md:p-8 shadow-2xl">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-base font-semibold text-slate-50">
                    <?= $modalMode === 'edit' ? 'Editar visita' : 'Nueva visita' ?>
                    – <?= h($residencial['nombre']) ?>
                    · unidad <?= h($contexto['unidad_clave']) ?>
                </h3>
            </div>
            <button type="button"
                    onclick="window.location.href='visitas.php#historial'"
                    class="h-8 w-8 rounded-full border border-white/20 bg-white/5 text-sm flex items-center justify-center text-slate-100">
                ✕
            </button>
        </div>

        <form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="hidden" name="action" value="<?= $modalMode === 'edit' ? 'update' : 'create' ?>">
            <?php if ($modalMode === 'edit' && isset($mv['id'])): ?>
                <input type="hidden" name="visita_id" value="<?= (int)$mv['id'] ?>">
            <?php endif; ?>

            <div class="md:col-span-2">
                <label class="block mb-1 text-slate-200" for="nombre_visitante">Nombre de la visita *</label>
                <input type="text" id="nombre_visitante" name="nombre_visitante" required
                       value="<?= h($mv['nombre_visitante'] ?? '') ?>"
                       class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50"
                       placeholder="Ej. Familia Pérez, Plomero, Uber Eats...">
            </div>

            <div>
                <label class="block mb-1 text-slate-200" for="tipo">Tipo</label>
                <select id="tipo" name="tipo"
                        class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
                    <option value="visita" <?= (($mv['tipo'] ?? '') === 'visita') ? 'selected' : '' ?>>Visita</option>
                    <option value="servicio" <?= (($mv['tipo'] ?? '') === 'servicio') ? 'selected' : '' ?>>Servicio</option>
                    <option value="trabajador_recurrente" <?= (($mv['tipo'] ?? '') === 'trabajador_recurrente') ? 'selected' : '' ?>>
                        Trabajador recurrente
                    </option>
                </select>
            </div>

            <div>
                <label class="block mb-1 text-slate-200" for="motivo">Motivo / nota</label>
                <input type="text" id="motivo" name="motivo"
                       value="<?= h($mv['motivo'] ?? '') ?>"
                       class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50"
                       placeholder="Opcional">
            </div>

            <div>
                <label class="block mb-1 text-slate-200" for="placa">Placa del vehículo</label>
                <input type="text" id="placa" name="placa"
                       value="<?= h($mv['placa_vehiculo'] ?? '') ?>"
                       class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50"
                       placeholder="Opcional">
            </div>

            <div>
                <label class="block mb-1 text-slate-200" for="fecha_desde">Fecha desde *</label>
                <input type="date" id="fecha_desde" name="fecha_desde" required
                       value="<?= h($mv['fecha_desde'] ?? '') ?>"
                       class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
            </div>
            <div>
                <label class="block mb-1 text-slate-200" for="fecha_hasta">Fecha hasta *</label>
                <input type="date" id="fecha_hasta" name="fecha_hasta" required
                       value="<?= h($mv['fecha_hasta'] ?? '') ?>"
                       class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
            </div>

            <div>
                <label class="block mb-1 text-slate-200" for="hora_desde">Hora desde</label>
                <input type="time" id="hora_desde" name="hora_desde"
                       value="<?= h($mv['hora_desde'] ?? '') ?>"
                       class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
            </div>
            <div>
                <label class="block mb-1 text-slate-200" for="hora_hasta">Hora hasta</label>
                <input type="time" id="hora_hasta" name="hora_hasta"
                       value="<?= h($mv['hora_hasta'] ?? '') ?>"
                       class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
            </div>

            <div class="md:col-span-2 mt-2">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="uso_unico"
                           class="rounded border-white/30 bg-white/5 text-sky-500"
                           <?= ((int)($mv['uso_unico'] ?? 1) === 1) ? 'checked' : '' ?>>
                    <span class="text-[11px] text-slate-200">Código de un solo uso</span>
                </label>
            </div>

            <div class="md:col-span-2 flex justify-end gap-2 mt-3">
                <button type="button"
                        onclick="window.location.href='visitas.php#historial'"
                        class="px-4 py-2 rounded-2xl border border-white/20 bg-white/5 text-[11px] text-slate-100">
                    Cancelar
                </button>
                <button type="submit"
                        class="px-4 py-2 rounded-2xl bg-gradient-to-r from-sky-500 to-indigo-500 text-[11px] font-medium text-white shadow-lg shadow-sky-900/40">
                    <?= $modalMode === 'edit' ? 'Guardar cambios' : 'Crear visita' ?>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
