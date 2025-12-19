<?php
// guardia/control_accesos.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

require_login();
require_role(['guardia']);

$user       = current_user();
$pageTitle  = 'Control de accesos';
$activeMenu = 'control_accesos';

$success = '';
$error   = '';

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function is_disabled_state($estado){
    return in_array((string)$estado, ['usado','vencido','cancelado'], true);
}

// Código buscado (POST tiene prioridad, si no GET)
$codigoBuscado = trim((string)($_POST['codigo'] ?? ($_GET['code'] ?? '')));
$visitaEncontrada = null;

// 1) Obtener residencial asignado al guardia
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
    $error = 'Error al obtener el residencial del guardia: ' . $e->getMessage();
    $residencial = null;
}

if (!$residencial) {
    ob_start(); ?>
    <div class="text-sm text-slate-200">
        Tu usuario de guardia aún no está asignado a un residencial. Contacta al administrador.
    </div>
    <?php
    $content = ob_get_clean();
    include __DIR__ . '/../layouts/dashboard_layout.php';
    exit;
}

// 2) Buscar visita por código
if ($codigoBuscado !== '') {
    try {
        $stmtV = $pdo->prepare("
            SELECT v.*,
                   u.clave AS unidad_clave,
                   res.name     AS residente_nombre,
                   res.telefono AS residente_telefono
            FROM visitas v
            JOIN unidades u   ON u.id = v.unidad_id
            JOIN users   res  ON res.id = v.residente_id
            WHERE v.codigo_acceso = :codigo
              AND v.residencial_id = :residencial_id
            LIMIT 1
        ");
        $stmtV->execute([
            'codigo'         => $codigoBuscado,
            'residencial_id' => $residencial['id'],
        ]);
        $visitaEncontrada = $stmtV->fetch();

        if (!$visitaEncontrada) {
            $error = 'No se encontró ninguna visita con ese código para este residencial.';
        } else {
            // Validar vigencia y actualizar a vencido si aplica
            $ahora   = new DateTime('now');
            $iniStr  = $visitaEncontrada['fecha_desde'] . ' ' . ($visitaEncontrada['hora_desde'] ?? '00:00:00');
            $finStr  = $visitaEncontrada['fecha_hasta'] . ' ' . ($visitaEncontrada['hora_hasta'] ?? '23:59:59');
            $inicio  = new DateTime($iniStr);
            $fin     = new DateTime($finStr);

            if ($ahora > $fin && ($visitaEncontrada['estado'] ?? '') === 'pendiente') {
                $stmtUpd = $pdo->prepare("UPDATE visitas SET estado = 'vencido' WHERE id = :id");
                $stmtUpd->execute(['id' => $visitaEncontrada['id']]);
                $visitaEncontrada['estado'] = 'vencido';
            }

            // Si aún no inicia, puedes permitir solo verificación/denegar (opcional)
            // (lo dejamos como warning visual)
            $visitaEncontrada['_aun_no_inicia'] = ($ahora < $inicio);
        }
    } catch (PDOException $e) {
        $error = 'Error al buscar la visita: ' . $e->getMessage();
    }
}

// 3) Registrar acceso
if (isset($_POST['registrar_acceso']) && isset($_POST['visita_id'])) {
    $visita_id   = (int)$_POST['visita_id'];
    $tipo_evento = $_POST['tipo_evento'] ?? 'entrada';
    $resultado   = $_POST['resultado'] ?? 'permitido';
    $observ      = trim($_POST['observaciones'] ?? '');

    try {
        $pdo->beginTransaction();

        // Traer visita con lock
        $stmtInfo = $pdo->prepare("
            SELECT v.*, u.clave AS unidad_clave
            FROM visitas v
            JOIN unidades u ON u.id = v.unidad_id
            WHERE v.id = :id
              AND v.residencial_id = :residencial_id
            FOR UPDATE
        ");
        $stmtInfo->execute([
            'id' => $visita_id,
            'residencial_id' => $residencial['id']
        ]);
        $infoV = $stmtInfo->fetch();

        if (!$infoV) {
            throw new Exception("La visita no existe o no pertenece a este residencial.");
        }

        // Bloqueos: si está cancelada/vencida -> no permitir entrada (pero sí registrar verificación si quieres)
        $estadoActual = (string)($infoV['estado'] ?? '');
        if (in_array($estadoActual, ['cancelado','vencido'], true) && $resultado === 'permitido' && $tipo_evento === 'entrada') {
            throw new Exception("No puedes permitir entrada: la visita está en estado '{$estadoActual}'.");
        }

        // Si es uso único y ya está usada -> no permitir otra entrada
        if ((int)($infoV['uso_unico'] ?? 0) === 1 && $estadoActual === 'usado' && $resultado === 'permitido' && $tipo_evento === 'entrada') {
            throw new Exception("No puedes permitir entrada: el código es de uso único y ya fue usado.");
        }

        // Insertar evento en accesos_guardia
        $stmtIns = $pdo->prepare("
            INSERT INTO accesos_guardia (visita_id, guardia_id, tipo_evento, resultado, observaciones)
            VALUES (:visita_id, :guardia_id, :tipo_evento, :resultado, :observaciones)
        ");
        $stmtIns->execute([
            'visita_id'     => $visita_id,
            'guardia_id'    => $user['id'],
            'tipo_evento'   => $tipo_evento,
            'resultado'     => $resultado,
            'observaciones' => $observ !== '' ? $observ : null,
        ]);

        // Si se permitió la entrada y la visita es de uso único -> marcar como usada
        if ($tipo_evento === 'entrada' && $resultado === 'permitido') {
            if ((int)($infoV['uso_unico'] ?? 0) === 1 && $estadoActual === 'pendiente') {
                $stmtUpd2 = $pdo->prepare("UPDATE visitas SET estado = 'usado' WHERE id = :id");
                $stmtUpd2->execute(['id' => $visita_id]);
            }
        }

        $pdo->commit();

        header('Location: control_accesos.php?ok=registered#historial');
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = 'Error al registrar el acceso: ' . $e->getMessage();
    }
}

// 4) Mensajes GET
if (isset($_GET['ok']) && $_GET['ok'] === 'registered') {
    $success = 'Acceso registrado correctamente.';
}

// 5) Historial rápido
$ultimosAccesos = [];
try {
    $stmtHist = $pdo->prepare("
        SELECT ag.*, v.codigo_acceso, v.nombre_visitante, v.tipo,
               u.clave AS unidad_clave
        FROM accesos_guardia ag
        JOIN visitas v ON v.id = ag.visita_id
        JOIN unidades u ON u.id = v.unidad_id
        WHERE v.residencial_id = :residencial_id
        ORDER BY ag.fecha_hora DESC
        LIMIT 30
    ");
    $stmtHist->execute(['residencial_id' => $residencial['id']]);
    $ultimosAccesos = $stmtHist->fetchAll();
} catch (PDOException $e) {
    $error = 'Error al obtener el historial de accesos: ' . $e->getMessage();
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

  <!-- BUSCADOR + BOTÓN SCAN -->
  <div class="rounded-2xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4 md:p-5">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
      <div>
        <h4 class="text-sm font-semibold">Control de accesos</h4>
        <p class="text-[11px] text-slate-300/80"><?= h($residencial['nombre']) ?></p>
      </div>

      <div class="flex items-center gap-2">
        <button type="button"
                onclick="openScanModal()"
                class="inline-flex items-center gap-1 rounded-2xl bg-gradient-to-r from-indigo-500 to-sky-500 px-4 py-2 text-xs font-medium text-white shadow-lg shadow-sky-900/40">
          Escanear QR
        </button>
      </div>
    </div>

    <form method="POST" class="flex flex-col md:flex-row gap-3 items-start md:items-center">
      <div class="flex-1 w-full">
        <label class="block mb-1 text-slate-200" for="codigo">Código de acceso</label>
        <input type="text"
               name="codigo"
               id="codigo"
               value="<?= h($codigoBuscado) ?>"
               class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50"
               placeholder="Pega o escribe el código (o escanea QR)">
      </div>

      <button type="submit"
              class="inline-flex items-center gap-1 rounded-2xl bg-gradient-to-r from-sky-500 to-indigo-500 px-4 py-2 text-xs font-medium text-white shadow-lg shadow-sky-900/40 mt-2 md:mt-5">
        Buscar
      </button>
    </form>

    <p class="text-[11px] text-slate-300/80 mt-2">
      Tip: el QR debe abrir esta página con <code>?code=ELCODIGO</code>.
    </p>
  </div>

  <!-- DETALLE VISITA -->
  <?php if ($visitaEncontrada): ?>
    <?php
      $estado = (string)($visitaEncontrada['estado'] ?? '');
      $isDisabled = is_disabled_state($estado);

      $badge = 'bg-slate-500/20 border-slate-400/40 text-slate-200';
      if ($estado === 'pendiente')  $badge = 'bg-sky-500/15 border-sky-400/40 text-sky-100';
      if ($estado === 'usado')      $badge = 'bg-emerald-500/15 border-emerald-400/40 text-emerald-100';
      if ($estado === 'vencido')    $badge = 'bg-slate-500/20 border-slate-400/40 text-slate-200';
      if ($estado === 'cancelado')  $badge = 'bg-rose-500/15 border-rose-400/40 text-rose-100';
    ?>

    <div class="rounded-2xl border border-white/15 bg-white/5 backdrop-blur-xl p-4 md:p-5">
      <div class="flex items-center justify-between gap-3 mb-3">
        <div>
          <h4 class="text-sm font-semibold">Visita encontrada</h4>
          <p class="text-[11px] text-slate-300/80">
            Unidad <?= h($visitaEncontrada['unidad_clave'] ?? '') ?> · <?= h($visitaEncontrada['tipo'] ?? '') ?>
          </p>
        </div>
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border <?= $badge ?> text-[11px]">
          Estado: <?= h($estado) ?>
        </span>
      </div>

      <?php if (!empty($visitaEncontrada['_aun_no_inicia'])): ?>
        <div class="mb-3 rounded-2xl bg-amber-500/10 border border-amber-400/40 px-4 py-3 text-amber-100">
          Ojo: la vigencia aún no inicia. Puedes registrar “verificación” o “denegado”.
        </div>
      <?php endif; ?>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
        <div>
          <div class="text-[11px] text-slate-300/80">Visitante</div>
          <div class="text-sm font-semibold text-slate-50"><?= h($visitaEncontrada['nombre_visitante'] ?? '') ?></div>
          <?php if (!empty($visitaEncontrada['motivo'])): ?>
            <div class="text-[11px] text-slate-300/90"><?= h($visitaEncontrada['motivo']) ?></div>
          <?php endif; ?>
        </div>

        <div>
          <div class="text-[11px] text-slate-300/80">Residente</div>
          <div class="text-xs text-slate-100"><?= h($visitaEncontrada['residente_nombre'] ?? '') ?></div>
          <?php if (!empty($visitaEncontrada['residente_telefono'])): ?>
            <div class="text-[11px] text-slate-300/80"><?= h($visitaEncontrada['residente_telefono']) ?></div>
          <?php endif; ?>
        </div>

        <div>
          <div class="text-[11px] text-slate-300/80">Vigencia</div>
          <div class="text-xs text-slate-100">
            <?= h($visitaEncontrada['fecha_desde'] ?? '') ?> – <?= h($visitaEncontrada['fecha_hasta'] ?? '') ?>
          </div>
          <?php if (!empty($visitaEncontrada['hora_desde']) || !empty($visitaEncontrada['hora_hasta'])): ?>
            <div class="text-[11px] text-slate-300/80">
              <?= h($visitaEncontrada['hora_desde'] ?? '--') ?> – <?= h($visitaEncontrada['hora_hasta'] ?? '--') ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-2 mb-4 text-[11px]">
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-white/10 border border-white/20 text-slate-100">
          Código: <code><?= h($visitaEncontrada['codigo_acceso'] ?? '') ?></code>
        </span>
        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-white/10 border border-white/20 text-slate-100">
          <?= ((int)($visitaEncontrada['uso_unico'] ?? 0) === 1) ? 'Uso único' : 'Múltiples accesos' ?>
        </span>
      </div>

      <form method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
        <input type="hidden" name="visita_id" value="<?= (int)$visitaEncontrada['id'] ?>">

        <div>
          <label class="block mb-1 text-slate-200" for="tipo_evento">Tipo de evento</label>
          <select id="tipo_evento" name="tipo_evento"
                  class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
            <option value="entrada">Entrada</option>
            <option value="salida">Salida</option>
            <option value="verificacion">Solo verificación</option>
          </select>
        </div>

        <div>
          <label class="block mb-1 text-slate-200" for="resultado">Resultado</label>
          <select id="resultado" name="resultado"
                  class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
            <option value="permitido">Permitido</option>
            <option value="denegado">Denegado</option>
          </select>
        </div>

        <div class="md:col-span-2">
          <label class="block mb-1 text-slate-200" for="observaciones">Observaciones</label>
          <input type="text" id="observaciones" name="observaciones"
                 class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50"
                 placeholder="Placas, acompañantes, motivo de rechazo...">
        </div>

        <div class="md:col-span-4 flex justify-end mt-1">
          <button type="submit" name="registrar_acceso"
                  class="inline-flex items-center gap-1 rounded-2xl bg-gradient-to-r from-emerald-500 to-sky-500 px-4 py-2 text-xs font-medium text-white shadow-lg shadow-emerald-900/40">
            Registrar acceso
          </button>
        </div>
      </form>

      <?php if ($isDisabled): ?>
        <p class="mt-3 text-[11px] text-slate-300/80">
          Nota: esta visita está en estado <b><?= h($estado) ?></b>. Si intentas “permitido + entrada” el sistema puede bloquearlo según reglas.
        </p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- HISTORIAL -->
  <div class="rounded-2xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4 md:p-5">
    <div class="flex items-center justify-between mb-3">
      <h4 class="text-sm font-semibold">Últimos accesos registrados</h4>
      <span class="text-[11px] text-slate-300/80">Total mostrados: <?= count($ultimosAccesos) ?></span>
    </div>

    <?php if (empty($ultimosAccesos)): ?>
      <p class="text-xs text-slate-400">Aún no hay accesos registrados.</p>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="min-w-full text-xs border-separate border-spacing-y-1">
          <thead class="text-[11px] uppercase text-slate-300/80">
            <tr>
              <th class="text-left px-3 py-2">Fecha / hora</th>
              <th class="text-left px-3 py-2">Visita</th>
              <th class="text-left px-3 py-2">Unidad</th>
              <th class="text-left px-3 py-2">Evento</th>
              <th class="text-left px-3 py-2">Resultado</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ultimosAccesos as $a): ?>
              <tr class="bg-white/5 hover:bg-white/10 transition rounded-xl">
                <td class="px-3 py-2 rounded-l-xl text-slate-200/90"><?= h($a['fecha_hora'] ?? '') ?></td>
                <td class="px-3 py-2 text-slate-200/90">
                  <?= h($a['nombre_visitante'] ?? '') ?>
                  <div class="text-[11px] text-slate-300/80">Código: <?= h($a['codigo_acceso'] ?? '') ?></div>
                </td>
                <td class="px-3 py-2 text-slate-200/90"><?= h($a['unidad_clave'] ?? '') ?></td>
                <td class="px-3 py-2 text-slate-200/90"><?= h($a['tipo_evento'] ?? '') ?></td>
                <td class="px-3 py-2 rounded-r-xl">
                  <?php if (($a['resultado'] ?? '') === 'permitido'): ?>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-400/40 text-[11px] text-emerald-100">
                      Permitido
                    </span>
                  <?php else: ?>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-rose-500/15 border border-rose-400/40 text-[11px] text-rose-100">
                      Denegado
                    </span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

</div>

<!-- MODAL SCAN QR -->
<div id="scanModal" class="hidden fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center">
  <div class="bg-slate-950 border border-white/20 rounded-3xl w-full max-w-xl p-6 relative">
    <button onclick="closeScanModal()"
      class="absolute top-3 right-3 h-8 w-8 rounded-full bg-white/10 border border-white/20">
      ✕
    </button>

    <h3 class="text-sm font-semibold text-slate-50 mb-1">Escanear QR</h3>
    <p class="text-[11px] text-slate-300 mb-4">
      Apunta la cámara al QR del visitante (o al QR que muestra el residente).
    </p>

    <div id="qr-reader" class="rounded-2xl overflow-hidden border border-white/15"></div>

    <div class="mt-4 flex gap-2 justify-end">
      <button onclick="closeScanModal()"
        class="rounded-2xl bg-white/10 border border-white/20 px-4 py-2 text-xs">
        Cerrar
      </button>
    </div>

    <p class="mt-3 text-[11px] text-slate-400">
      Si no te pide permisos, revisa que estés en <b>HTTPS</b>.
    </p>
  </div>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.10/html5-qrcode.min.js"></script>
<script>
let html5Qr = null;

function openScanModal(){
  const modal = document.getElementById('scanModal');
  modal.classList.remove('hidden');

  // iniciar scanner
  if (!html5Qr) html5Qr = new Html5Qrcode("qr-reader");

  Html5Qrcode.getCameras().then(devices => {
    const camId = devices && devices.length ? devices[0].id : null;
    if (!camId) throw new Error("No se detectó cámara.");
    return html5Qr.start(
      camId,
      { fps: 10, qrbox: { width: 240, height: 240 } },
      (decodedText) => {
        // decodedText puede ser URL (ej /guardia/control_accesos.php?code=xxx) o solo el código
        const code = extractCode(decodedText);
        if (code) {
          stopScanner();
          closeScanModal();
          const input = document.getElementById('codigo');
          input.value = code;
          // submit automático
          input.closest('form').submit();
        }
      }
    );
  }).catch(err => {
    console.error(err);
    alert("No se pudo abrir la cámara. Revisa permisos / HTTPS.");
  });
}

function closeScanModal(){
  const modal = document.getElementById('scanModal');
  modal.classList.add('hidden');
  stopScanner();
}

function stopScanner(){
  if (html5Qr) {
    html5Qr.stop().then(() => {
      html5Qr.clear();
    }).catch(() => {});
  }
}

// Si el QR trae URL, extrae ?code=...
function extractCode(text){
  try {
    // Si viene una URL completa o relativa
    if (text.includes('code=')) {
      const url = new URL(text, window.location.origin);
      return (url.searchParams.get('code') || '').trim();
    }
    // Si viene solo el código
    return (text || '').trim();
  } catch(e) {
    // fallback: intenta regex
    const m = String(text).match(/code=([^&]+)/i);
    if (m && m[1]) return decodeURIComponent(m[1]).trim();
    return String(text || '').trim();
  }
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
