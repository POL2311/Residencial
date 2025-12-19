<?php
// superadmin_residenciales.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';
require_role(['super_admin']);

$pageTitle  = 'Residenciales (Super Admin)';
$activeMenu = 'residences';

$success = '';
$error   = '';

$showModal = false; // abre/cierra modal
$form = [
  'nombre' => '',
  'codigo' => '',
  'tipo' => 'fraccionamiento',
  'max_casas' => '',
  'max_guardias' => '',
  'pais' => 'México',
  'estado' => '',
  'ciudad' => '',
  'colonia' => '',
  'calle' => '',
  'numero_exterior' => '',
  'numero_interior' => '',
  'codigo_postal' => '',
  'nombre_contacto' => '',
  'telefono_contacto' => '',
  'email_contacto' => '',
  'plan_id' => '',
  'fecha_inicio_plan' => '',
  'fecha_fin_plan' => '',
  'estatus_plan' => 'activo',
  'zona_horaria' => 'America/Mexico_City',
  'permite_qr' => 1,
  'permite_trabajadores_recurrentes' => 1,
  'requiere_placa_vehiculo' => 0,
  'requiere_identificacion_visita' => 0,
];

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// CSRF simple
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

// 1) Obtener planes activos para el <select>
$planes = [];
try {
  $stmtPlanes = $pdo->query("SELECT id, nombre, codigo FROM planes WHERE activo = 1 ORDER BY precio_mensual ASC, nombre ASC");
  $planes = $stmtPlanes->fetchAll();
} catch (PDOException $e) {
  $error = 'Error al obtener los planes: ' . $e->getMessage();
}

// 2) Apertura de modal por GET
if (isset($_GET['new'])) {
  $showModal = true;
}

// 3) Manejo de alta de residencial
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // CSRF check
  $postedCsrf = $_POST['csrf_token'] ?? '';
  if (!hash_equals($csrf, $postedCsrf)) {
    $error = 'Sesión inválida. Recarga la página e inténtalo de nuevo.';
    $showModal = true;
  } else {

    // llenar $form con POST para preservar valores
    foreach ($form as $k => $v) {
      if (isset($_POST[$k])) $form[$k] = is_string($_POST[$k]) ? trim($_POST[$k]) : $_POST[$k];
    }

    // checkboxes
    $form['permite_qr'] = isset($_POST['permite_qr']) ? 1 : 0;
    $form['permite_trabajadores_recurrentes'] = isset($_POST['permite_trabajadores_recurrentes']) ? 1 : 0;
    $form['requiere_placa_vehiculo'] = isset($_POST['requiere_placa_vehiculo']) ? 1 : 0;
    $form['requiere_identificacion_visita'] = isset($_POST['requiere_identificacion_visita']) ? 1 : 0;

    $nombre    = $form['nombre'];
    $codigo    = $form['codigo'];
    $tipo      = $form['tipo'];

    $max_casas     = (int)($form['max_casas'] !== '' ? $form['max_casas'] : 0);
    $max_guardias  = (int)($form['max_guardias'] !== '' ? $form['max_guardias'] : 0);

    $pais          = $form['pais'];
    $estado        = $form['estado'];
    $ciudad        = $form['ciudad'];
    $colonia       = $form['colonia'];
    $calle         = $form['calle'];
    $numero_ext    = $form['numero_exterior'];
    $numero_int    = $form['numero_interior'];
    $codigo_postal = $form['codigo_postal'];

    $nombre_contacto   = $form['nombre_contacto'];
    $telefono_contacto = $form['telefono_contacto'];
    $email_contacto    = $form['email_contacto'];

    $plan_id           = $form['plan_id'];
    $fecha_inicio_plan = $form['fecha_inicio_plan'];
    $fecha_fin_plan    = $form['fecha_fin_plan'];
    $estatus_plan      = $form['estatus_plan'];

    $zona_horaria = $form['zona_horaria'];

    $permite_qr = (int)$form['permite_qr'];
    $permite_trabajadores = (int)$form['permite_trabajadores_recurrentes'];
    $requiere_placa_vehiculo = (int)$form['requiere_placa_vehiculo'];
    $requiere_identificacion_visita = (int)$form['requiere_identificacion_visita'];

    // Validaciones básicas
    $errores = [];
    if ($nombre === '')  $errores[] = 'El nombre del residencial es obligatorio.';
    if ($codigo === '')  $errores[] = 'El código interno es obligatorio.';
    if ($pais === '')    $errores[] = 'El país es obligatorio.';
    if ($estado === '')  $errores[] = 'El estado es obligatorio.';
    if ($ciudad === '')  $errores[] = 'La ciudad es obligatoria.';
    if ($colonia === '') $errores[] = 'La colonia es obligatoria.';
    if ($calle === '')   $errores[] = 'La calle es obligatoria.';
    if ($numero_ext === '') $errores[] = 'El número exterior es obligatorio.';
    if ($codigo_postal === '') $errores[] = 'El código postal es obligatorio.';
    if ($nombre_contacto === '')   $errores[] = 'El nombre de contacto es obligatorio.';
    if ($telefono_contacto === '') $errores[] = 'El teléfono de contacto es obligatorio.';
    if ($email_contacto === '')    $errores[] = 'El email de contacto es obligatorio.';

    if ($max_casas < 0)    $errores[] = 'El máximo de casas no puede ser negativo.';
    if ($max_guardias < 0) $errores[] = 'El máximo de guardias no puede ser negativo.';

    if (!empty($email_contacto) && !filter_var($email_contacto, FILTER_VALIDATE_EMAIL)) {
      $errores[] = 'El email de contacto no tiene un formato válido.';
    }

    if (!empty($fecha_inicio_plan) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio_plan)) {
      $errores[] = 'La fecha de inicio del plan no es válida (usa formato YYYY-MM-DD).';
    }
    if (!empty($fecha_fin_plan) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fin_plan)) {
      $errores[] = 'La fecha de fin del plan no es válida (usa formato YYYY-MM-DD).';
    }

    if (!in_array($estatus_plan, ['prueba','activo','suspendido','cancelado'], true)) {
      $errores[] = 'El estatus del plan no es válido.';
    }

    if ($fecha_inicio_plan !== '' && $fecha_fin_plan !== '' && $fecha_fin_plan < $fecha_inicio_plan) {
      $errores[] = 'La fecha de fin del plan no puede ser anterior a la fecha de inicio.';
    }

    if (!empty($errores)) {
      $error = implode(' ', $errores);
      $showModal = true;
    } else {
      try {
        $stmt = $pdo->prepare("
          INSERT INTO residenciales (
            nombre, codigo, tipo, max_casas, max_guardias,
            pais, estado, ciudad, colonia, calle,
            numero_exterior, numero_interior, codigo_postal,
            nombre_contacto, telefono_contacto, email_contacto,
            plan_id, fecha_inicio_plan, fecha_fin_plan, estatus_plan,
            zona_horaria,
            permite_qr, permite_trabajadores_recurrentes,
            requiere_placa_vehiculo, requiere_identificacion_visita,
            activo
          ) VALUES (
            :nombre, :codigo, :tipo, :max_casas, :max_guardias,
            :pais, :estado, :ciudad, :colonia, :calle,
            :numero_exterior, :numero_interior, :codigo_postal,
            :nombre_contacto, :telefono_contacto, :email_contacto,
            :plan_id, :fecha_inicio_plan, :fecha_fin_plan, :estatus_plan,
            :zona_horaria,
            :permite_qr, :permite_trabajadores_recurrentes,
            :requiere_placa_vehiculo, :requiere_identificacion_visita,
            1
          )
        ");

        $stmt->execute([
          'nombre'    => $nombre,
          'codigo'    => $codigo,
          'tipo'      => $tipo,
          'max_casas' => $max_casas,
          'max_guardias' => $max_guardias,
          'pais'      => $pais,
          'estado'    => $estado,
          'ciudad'    => $ciudad,
          'colonia'   => $colonia,
          'calle'     => $calle,
          'numero_exterior' => $numero_ext,
          'numero_interior' => ($numero_int !== '' ? $numero_int : null),
          'codigo_postal'   => $codigo_postal,
          'nombre_contacto'   => $nombre_contacto,
          'telefono_contacto' => $telefono_contacto,
          'email_contacto'    => $email_contacto,
          'plan_id'           => ($plan_id !== '' ? $plan_id : null),
          'fecha_inicio_plan' => ($fecha_inicio_plan !== '' ? $fecha_inicio_plan : null),
          'fecha_fin_plan'    => ($fecha_fin_plan !== '' ? $fecha_fin_plan : null),
          'estatus_plan'      => $estatus_plan,
          'zona_horaria'      => $zona_horaria,
          'permite_qr'                    => $permite_qr,
          'permite_trabajadores_recurrentes' => $permite_trabajadores,
          'requiere_placa_vehiculo'       => $requiere_placa_vehiculo,
          'requiere_identificacion_visita'=> $requiere_identificacion_visita,
        ]);

        // Redirige para evitar re-POST
        header('Location: superadmin_residenciales.php?ok=created');
        exit;

      } catch (PDOException $e) {
        $error = 'Error al crear el residencial: ' . $e->getMessage();
        $showModal = true;
      }
    }
  }
}

// Mensajes por GET
if (isset($_GET['ok']) && $_GET['ok'] === 'created') {
  $success = 'Residencial creado correctamente.';
}

// 4) Filtros (GET)
$q = trim($_GET['q'] ?? '');
$f_plan = trim($_GET['plan_id'] ?? '');
$f_estatus = trim($_GET['estatus_plan'] ?? '');

// 5) Obtener lista de residenciales 
$residenciales = [];
try {
  $sqlRes = "
    SELECT r.*, p.nombre AS nombre_plan, p.codigo AS codigo_plan
    FROM residenciales r
    LEFT JOIN planes p ON p.id = r.plan_id
    WHERE 1=1
  ";
  $params = [];

  if ($q !== '') {
    $sqlRes .= " AND (
      r.nombre LIKE :q OR r.codigo LIKE :q OR r.ciudad LIKE :q OR r.estado LIKE :q OR r.pais LIKE :q
    )";
    $params['q'] = "%{$q}%";
  }

  if ($f_plan !== '') {
    $sqlRes .= " AND r.plan_id = :plan_id";
    $params['plan_id'] = $f_plan;
  }

  if ($f_estatus !== '') {
    $sqlRes .= " AND r.estatus_plan = :estatus_plan";
    $params['estatus_plan'] = $f_estatus;
  }

  $sqlRes .= " ORDER BY r.created_at DESC";

  $stmtRes = $pdo->prepare($sqlRes);
  $stmtRes->execute($params);
  $residenciales = $stmtRes->fetchAll();

} catch (PDOException $e) {
  $error = 'Error al obtener la lista de residenciales: ' . $e->getMessage();
}

// 6) Contenido del dashboard
ob_start();
?>

<div class="space-y-6">

  <!-- Mensajes -->
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

  <!-- Encabezado + acciones -->
  <div class="rounded-2xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4 md:p-5 min-h-[70vh]">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
      <div>
        <h4 class="text-sm font-semibold">Residenciales registrados</h4>
        <p class="text-[11px] text-slate-300/80">
          Administra residenciales, planes y estatus.
        </p>
      </div>
      <div class="flex items-center gap-2">
        <a href="superadmin_residenciales.php?new=1"
           class="inline-flex items-center gap-1 rounded-2xl bg-gradient-to-r from-sky-500 to-indigo-500 px-4 py-2 text-xs font-medium text-white shadow-lg shadow-sky-900/40">
          + Crear residencial
        </a>
      </div>
    </div>

    <!-- Filtros -->
    <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-3">
      <div class="md:col-span-2">
        <label class="block mb-1 text-slate-200 text-xs" for="q">Buscar</label>
        <input id="q" name="q" value="<?= h($q) ?>"
               class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50"
               placeholder="Nombre, código, ciudad, estado...">
      </div>

      <div>
        <label class="block mb-1 text-slate-200 text-xs" for="plan_id">Plan</label>
        <select id="plan_id" name="plan_id"
                class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
          <option value="">Todos</option>
          <?php foreach ($planes as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= ((string)$f_plan === (string)$p['id']) ? 'selected' : '' ?>>
              <?= h($p['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block mb-1 text-slate-200 text-xs" for="estatus_plan">Estatus</label>
        <select id="estatus_plan" name="estatus_plan"
                class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
          <option value="">Todos</option>
          <?php foreach (['prueba','activo','suspendido','cancelado'] as $st): ?>
            <option value="<?= $st ?>" <?= ($f_estatus === $st) ? 'selected' : '' ?>>
              <?= h($st) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="md:col-span-4 flex justify-end gap-2">
        <a href="superadmin_residenciales.php"
           class="px-4 py-2 rounded-2xl border border-white/20 bg-white/5 text-xs">
          Limpiar
        </a>
        <button type="submit"
                class="px-4 py-2 rounded-2xl bg-white/10 border border-white/20 text-xs font-medium hover:bg-white/15">
          Filtrar
        </button>
      </div>
    </form>

    <div class="mt-4 text-[11px] text-slate-300/80">
      Total: <?= count($residenciales) ?>
    </div>

    <!-- Tabla -->
    <?php if (empty($residenciales)): ?>
      <p class="mt-3 text-xs text-slate-400">Aún no hay residenciales registrados.</p>
    <?php else: ?>
      <div class="mt-3 overflow-x-auto">
        <table class="min-w-full text-xs border-separate border-spacing-y-1">
          <thead class="text-[11px] uppercase text-slate-300/80">
            <tr>
              <th class="text-left px-3 py-2">Nombre</th>
              <th class="text-left px-3 py-2">Ubicación</th>
              <th class="text-left px-3 py-2">Plan</th>
              <th class="text-left px-3 py-2">Estatus</th>
              <th class="text-left px-3 py-2">Creado</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($residenciales as $res): ?>
              <?php
                $st = (string)($res['estatus_plan'] ?? '');
                $badge = 'bg-white/10 border-white/20 text-slate-100';
                if ($st === 'activo') $badge = 'bg-emerald-500/15 border-emerald-400/40 text-emerald-100';
                if ($st === 'prueba') $badge = 'bg-sky-500/15 border-sky-400/40 text-sky-100';
                if ($st === 'suspendido') $badge = 'bg-amber-500/15 border-amber-400/40 text-amber-100';
                if ($st === 'cancelado') $badge = 'bg-rose-500/15 border-rose-400/40 text-rose-100';
              ?>
              <tr class="bg-white/5 hover:bg-white/10 transition rounded-xl">
                <td class="px-3 py-2 rounded-l-xl">
                  <span class="font-medium text-slate-50"><?= h($res['nombre'] ?? '') ?></span>
                  <div class="text-[11px] text-slate-300/80">
                    Código: <?= h($res['codigo'] ?? '') ?>
                  </div>
                </td>
                <td class="px-3 py-2">
                  <span class="text-slate-200/90">
                    <?= h(($res['ciudad'] ?? '') . ', ' . ($res['estado'] ?? '')) ?>
                  </span>
                  <div class="text-[11px] text-slate-300/80"><?= h($res['pais'] ?? '') ?></div>
                </td>
                <td class="px-3 py-2">
                  <?php if (!empty($res['plan_id'])): ?>
                    <span class="text-slate-200/90"><?= h($res['nombre_plan'] ?? 'Plan') ?></span>
                    <div class="text-[11px] text-slate-300/80"><?= h($res['codigo_plan'] ?? '') ?></div>
                  <?php else: ?>
                    <span class="text-[11px] text-slate-400/90">Sin plan</span>
                  <?php endif; ?>
                </td>
                <td class="px-3 py-2">
                  <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border <?= $badge ?> text-[11px]">
                    <?= h($st) ?>
                  </span>
                </td>
                <td class="px-3 py-2 rounded-r-xl text-slate-300/80">
                  <?= h($res['created_at'] ?? '') ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($showModal): ?>
  <div class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm flex justify-center items-start overflow-y-auto">
    <div class="w-full max-w-6xl my-8 mx-2 md:mx-4 rounded-3xl border border-white/15 bg-slate-950/95 p-6 md:p-8 shadow-2xl">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h3 class="text-base font-semibold text-slate-50">Crear nuevo residencial</h3>
          <p class="text-[11px] text-slate-300/80">Completa la información y guarda.</p>
        </div>
        <button type="button"
                onclick="window.location.href='superadmin_residenciales.php'"
                class="h-8 w-8 rounded-full border border-white/20 bg-white/5 text-sm flex items-center justify-center">
          ✕
        </button>
      </div>

      <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

        <!-- Identidad básica -->
        <div class="md:col-span-2">
          <label class="block mb-1 text-slate-200" for="nombre">Nombre del residencial *</label>
          <input type="text" id="nombre" name="nombre" required
                 value="<?= h($form['nombre']) ?>"
                 class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50 placeholder:text-slate-400">
        </div>

        <div>
          <label class="block mb-1 text-slate-200" for="codigo">Código interno *</label>
          <input type="text" id="codigo" name="codigo" required
                 value="<?= h($form['codigo']) ?>"
                 class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50 placeholder:text-slate-400">
        </div>

        <div>
          <label class="block mb-1 text-slate-200" for="tipo">Tipo</label>
          <select id="tipo" name="tipo"
                  class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
            <?php
              $tipos = [
                'fraccionamiento' => 'Fraccionamiento',
                'torre' => 'Torre',
                'mixto' => 'Mixto',
                'privado' => 'Privado',
                'otro' => 'Otro',
              ];
              foreach ($tipos as $val => $lbl):
            ?>
              <option value="<?= $val ?>" <?= ($form['tipo'] === $val) ? 'selected' : '' ?>><?= h($lbl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Capacidad -->
        <div>
          <label class="block mb-1 text-slate-200" for="max_casas">Máx. casas/deptos</label>
          <input type="number" id="max_casas" name="max_casas" min="0"
                 value="<?= h($form['max_casas']) ?>"
                 class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
        </div>

        <div>
          <label class="block mb-1 text-slate-200" for="max_guardias">Máx. guardias</label>
          <input type="number" id="max_guardias" name="max_guardias" min="0"
                 value="<?= h($form['max_guardias']) ?>"
                 class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
        </div>

        <!-- Ubicación -->
        <div>
          <label class="block mb-1 text-slate-200" for="pais">País *</label>
          <input type="text" id="pais" name="pais" required
                 value="<?= h($form['pais']) ?>"
                 class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
        </div>

        <div>
          <label class="block mb-1 text-slate-200" for="estado">Estado *</label>
          <input type="text" id="estado" name="estado" required
                 value="<?= h($form['estado']) ?>"
                 class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
        </div>

        <div>
          <label class="block mb-1 text-slate-200" for="ciudad">Ciudad *</label>
          <input type="text" id="ciudad" name="ciudad" required
                 value="<?= h($form['ciudad']) ?>"
                 class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
        </div>

        <div>
          <label class="block mb-1 text-slate-200" for="colonia">Colonia *</label>
          <input type="text" id="colonia" name="colonia" required
                 value="<?= h($form['colonia']) ?>"
                 class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
        </div>

        <div>
          <label class="block mb-1 text-slate-200" for="calle">Calle *</label>
          <input type="text" id="calle" name="calle" required
                 value="<?= h($form['calle']) ?>"
                 class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
        </div>

        <div>
          <label class="block mb-1 text-slate-200" for="numero_exterior">Número exterior *</label>
          <input type="text" id="numero_exterior" name="numero_exterior" required
                 value="<?= h($form['numero_exterior']) ?>"
                 class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
        </div>

        <div>
          <label class="block mb-1 text-slate-200" for="numero_interior">Número interior</label>
          <input type="text" id="numero_interior" name="numero_interior"
                 value="<?= h($form['numero_interior']) ?>"
                 class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
        </div>

        <div>
          <label class="block mb-1 text-slate-200" for="codigo_postal">Código postal *</label>
          <input type="text" id="codigo_postal" name="codigo_postal" required
                 value="<?= h($form['codigo_postal']) ?>"
                 class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
        </div>

        <!-- Contacto -->
        <div class="md:col-span-2 mt-2">
          <p class="text-[11px] text-slate-300/80 mb-1 font-semibold">Contacto principal</p>
        </div>

        <div>
          <label class="block mb-1 text-slate-200" for="nombre_contacto">Nombre de contacto *</label>
          <input type="text" id="nombre_contacto" name="nombre_contacto" required
                 value="<?= h($form['nombre_contacto']) ?>"
                 class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
        </div>

        <div>
          <label class="block mb-1 text-slate-200" for="telefono_contacto">Teléfono de contacto *</label>
          <input type="text" id="telefono_contacto" name="telefono_contacto" required
                 value="<?= h($form['telefono_contacto']) ?>"
                 class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
        </div>

        <div class="md:col-span-2">
          <label class="block mb-1 text-slate-200" for="email_contacto">Email de contacto *</label>
          <input type="email" id="email_contacto" name="email_contacto" required
                 value="<?= h($form['email_contacto']) ?>"
                 class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
        </div>

        <!-- Plan -->
        <div class="md:col-span-2 mt-2">
          <p class="text-[11px] text-slate-300/80 mb-1 font-semibold">Plan y suscripción</p>
        </div>

        <div>
          <label class="block mb-1 text-slate-200" for="plan_id">Plan</label>
          <select id="plan_id" name="plan_id"
                  class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
            <option value="">Sin asignar</option>
            <?php foreach ($planes as $plan): ?>
              <option value="<?= (int)$plan['id'] ?>" <?= ((string)$form['plan_id'] === (string)$plan['id']) ? 'selected' : '' ?>>
                <?= h($plan['nombre'] . ' (' . $plan['codigo'] . ')') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block mb-1 text-slate-200" for="estatus_plan">Estatus del plan</label>
          <select id="estatus_plan" name="estatus_plan"
                  class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
            <?php foreach (['prueba','activo','suspendido','cancelado'] as $st): ?>
              <option value="<?= $st ?>" <?= ($form['estatus_plan'] === $st) ? 'selected' : '' ?>>
                <?= h($st) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block mb-1 text-slate-200" for="fecha_inicio_plan">Inicio del plan</label>
          <input type="date" id="fecha_inicio_plan" name="fecha_inicio_plan"
                 value="<?= h($form['fecha_inicio_plan']) ?>"
                 class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
        </div>

        <div>
          <label class="block mb-1 text-slate-200" for="fecha_fin_plan">Fin del plan</label>
          <input type="date" id="fecha_fin_plan" name="fecha_fin_plan"
                 value="<?= h($form['fecha_fin_plan']) ?>"
                 class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
        </div>

        <!-- Config -->
        <div class="md:col-span-2 mt-2">
          <p class="text-[11px] text-slate-300/80 mb-1 font-semibold">Configuración</p>
        </div>

        <div>
          <label class="block mb-1 text-slate-200" for="zona_horaria">Zona horaria</label>
          <input type="text" id="zona_horaria" name="zona_horaria"
                 value="<?= h($form['zona_horaria']) ?>"
                 class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
        </div>

        <div class="md:col-span-2 flex flex-wrap gap-3 text-[11px] text-slate-200">
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="permite_qr" <?= ((int)$form['permite_qr'] === 1) ? 'checked' : '' ?>
                   class="rounded border-white/30 bg-white/5 text-sky-500">
            <span>Permitir accesos con QR</span>
          </label>
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="permite_trabajadores_recurrentes" <?= ((int)$form['permite_trabajadores_recurrentes'] === 1) ? 'checked' : '' ?>
                   class="rounded border-white/30 bg-white/5 text-sky-500">
            <span>Permitir trabajadores recurrentes</span>
          </label>
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="requiere_placa_vehiculo" <?= ((int)$form['requiere_placa_vehiculo'] === 1) ? 'checked' : '' ?>
                   class="rounded border-white/30 bg-white/5 text-sky-500">
            <span>Requerir placa de vehículo</span>
          </label>
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="requiere_identificacion_visita" <?= ((int)$form['requiere_identificacion_visita'] === 1) ? 'checked' : '' ?>
                   class="rounded border-white/30 bg-white/5 text-sky-500">
            <span>Requerir identificación de visita</span>
          </label>
        </div>

        <div class="md:col-span-2 flex justify-end gap-2 mt-2">
          <button type="button"
                  onclick="window.location.href='superadmin_residenciales.php'"
                  class="px-4 py-2 rounded-2xl border border-white/20 bg-white/5 text-[11px]">
            Cancelar
          </button>
          <button type="submit"
                  class="px-4 py-2 rounded-2xl bg-gradient-to-r from-sky-500 to-indigo-500 text-[11px] font-medium text-white shadow-lg shadow-sky-900/40">
            Crear residencial
          </button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
