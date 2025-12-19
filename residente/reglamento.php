<?php
// residente/reglamento.php
require_once __DIR__ . '/../config/auth.php';
require_role(['residente']);
require_once __DIR__ . '/../config/config.php';

$user       = current_user();
$pageTitle  = 'Reglamento';
$activeMenu = 'reglamento';

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// -------------------------
// 1) Obtener contexto (residencial del residente)
// -------------------------
$ctx = null;
$error = '';

try {
    $stmt = $pdo->prepare("
        SELECT r.id AS residencial_id, r.nombre
        FROM usuarios_residenciales ur
        JOIN residenciales r ON r.id = ur.residencial_id
        WHERE ur.user_id = :uid
        ORDER BY ur.es_principal DESC, ur.created_at ASC
        LIMIT 1
    ");
    $stmt->execute(['uid' => $user['id']]);
    $ctx = $stmt->fetch();
} catch (PDOException $e) {
    $ctx = null;
    $error = 'Error al obtener tu residencial: ' . $e->getMessage();
}

if (!$ctx) {
    ob_start(); ?>
    <div class="text-sm text-slate-200">
        <?= $error ? h($error) : 'No tienes residencial asignado.' ?>
    </div>
    <?php
    $content = ob_get_clean();
    include __DIR__ . '/../layouts/dashboard_layout.php';
    exit;
}

$residencial_id = (int)$ctx['residencial_id'];

// -------------------------
// 2) Detección de tabla(s) reglamento
//   - Soporta:
//      A) tabla "reglamentos" (tu base actual)
//      B) tabla "reglamentos_residenciales" (si existe en tu BD final)
// -------------------------
function tableExists(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :t
        ");
        $stmt->execute(['t' => $table]);
        return ((int)$stmt->fetchColumn() > 0);
    } catch (Throwable $e) {
        return false;
    }
}

$has_reglamentos              = tableExists($pdo, 'reglamentos');
$has_reglamentos_residenciales= tableExists($pdo, 'reglamentos_residenciales');

// -------------------------
// 3) Traer reglamento publicado (más reciente)
//   - Preferimos "reglamentos_residenciales" si existe,
//     si no, usamos "reglamentos" (tu base).
//   - Campos esperados (flexibles):
//     titulo, contenido, version, pdf_path/pdf_url, publicado_en, created_at, updated_at, activo/mostrar_en_portal
// -------------------------
$reglamento = null;
$sourceTable = null;

if ($has_reglamentos_residenciales) {
    try {
        // Intentamos lo más compatible posible con lo que normalmente se maneja:
        // - activo / mostrar_en_portal (si no existen, no pasa nada si tu tabla no los tiene: por eso, primero probamos con query simple)
        // Hacemos 2 intentos: uno con filtros y otro sin filtros, para no tronar si no existen columnas.
        $sql1 = "
            SELECT *
            FROM reglamentos_residenciales
            WHERE residencial_id = :rid
              AND (mostrar_en_portal = 1 OR activo = 1 OR 1=1)
            ORDER BY
              COALESCE(publicado_en, updated_at, created_at) DESC,
              updated_at DESC,
              created_at DESC
            LIMIT 1
        ";

        // Si tu tabla no tiene esas columnas, MySQL podría fallar. Capturamos y hacemos fallback.
        try {
            $stmt = $pdo->prepare($sql1);
            $stmt->execute(['rid' => $residencial_id]);
            $reglamento = $stmt->fetch();
            $sourceTable = 'reglamentos_residenciales';
        } catch (PDOException $e) {
            // fallback simple sin columnas opcionales
            $stmt = $pdo->prepare("
                SELECT *
                FROM reglamentos_residenciales
                WHERE residencial_id = :rid
                ORDER BY updated_at DESC, created_at DESC
                LIMIT 1
            ");
            $stmt->execute(['rid' => $residencial_id]);
            $reglamento = $stmt->fetch();
            $sourceTable = 'reglamentos_residenciales';
        }
    } catch (PDOException $e) {
        $reglamento = null;
        $sourceTable = 'reglamentos_residenciales';
        $error = 'Error al obtener reglamento: ' . $e->getMessage();
    }
} elseif ($has_reglamentos) {
    try {
        $stmtR = $pdo->prepare("
            SELECT *
            FROM reglamentos
            WHERE residencial_id = :rid
            ORDER BY updated_at DESC, created_at DESC
            LIMIT 1
        ");
        $stmtR->execute(['rid' => $residencial_id]);
        $reglamento = $stmtR->fetch();
        $sourceTable = 'reglamentos';
    } catch (PDOException $e) {
        $reglamento = null;
        $sourceTable = 'reglamentos';
        $error = 'Error al obtener reglamento: ' . $e->getMessage();
    }
} else {
    $reglamento = null;
    $sourceTable = null;
}

// -------------------------
// 4) Normalizar campos (para UI consistente)
// -------------------------
$titulo = $reglamento['titulo'] ?? 'Reglamento';
$contenido = $reglamento['contenido'] ?? '';
$version = $reglamento['version'] ?? ($reglamento['numero_version'] ?? null);

// Soporte PDF (si tú guardas ruta/URL)
// - pdf_path: ruta local tipo "uploads/reglamentos/xxx.pdf"
// - pdf_url: url completa
$pdfPath = $reglamento['pdf_path'] ?? ($reglamento['archivo_pdf'] ?? null);
$pdfUrl  = $reglamento['pdf_url']  ?? null;

// Fecha “publicado” preferente
$publicado = $reglamento['publicado_en']
    ?? $reglamento['updated_at']
    ?? $reglamento['created_at']
    ?? null;

// -------------------------
// 5) Render
// -------------------------
ob_start();
?>

<div class="space-y-6 text-xs">

  <?php if (!empty($error)): ?>
    <div class="rounded-2xl bg-rose-500/15 border border-rose-400/50 px-4 py-3 text-rose-100">
      <?= h($error) ?>
    </div>
  <?php endif; ?>

  <!-- Encabezado -->
  <div class="rounded-2xl border border-white/15 bg-white/5 backdrop-blur-xl p-4 md:p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
    <div>
      <h4 class="text-sm font-semibold">Reglamento</h4>
      <p class="text-[11px] text-slate-300/80">
        Residencial: <?= h($ctx['nombre']) ?>
        <?php if ($sourceTable): ?>
        <?php endif; ?>
      </p>
    </div>

    <?php if ($reglamento): ?>
      <div class="flex items-center gap-2 ">
        <?php if ($version): ?>
          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-white/10 border border-white/20 text-[11px] text-slate-100">
            Versión: <?= h($version) ?>
          </span>
        <?php endif; ?>

        <?php if ($publicado): ?>
          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-white/10 border border-white/20 text-[11px] text-slate-300/90">
            Actualizado: <?= h($publicado) ?>
          </span>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Cuerpo -->
  <div class="rounded-2xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4 md:p-6 min-h-[50vh]">

    <?php if (!$has_reglamentos && !$has_reglamentos_residenciales): ?>
      <div class="space-y-2">
        <p class="text-slate-300/80">
          Aún no se ha configurado un módulo de reglamento en el sistema.
        </p>
        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
          <p class="text-[11px] text-slate-300/90 mb-2">
            Opciones para habilitarlo:
          </p>
          <ul class="list-disc ml-5 text-[11px] text-slate-300/80 space-y-1">
            <li>Crear tabla <code class="px-2 py-1 rounded bg-black/40">reglamentos</code> (como tu base actual).</li>
            <li>O usar <code class="px-2 py-1 rounded bg-black/40">reglamentos_residenciales</code> si ya existe en tu BD final.</li>
            <li>Opcional: guardar un PDF (ruta o URL) para que el residente lo descargue.</li>
          </ul>
        </div>
      </div>

    <?php elseif (!$reglamento): ?>
      <p class="text-slate-300/80">
        No hay reglamento publicado para este residencial por el momento.
      </p>

    <?php else: ?>
      <!-- Barra de acciones (PDF / Copiar / Imprimir) -->
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
        <div>
          <div class="text-xs text-slate-100 font-semibold"><?= h($titulo) ?></div>
          <p class="text-[11px] text-slate-300/80">
            Revisa las normas de convivencia, seguridad y uso de áreas comunes.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <?php if ($pdfUrl): ?>
            <a href="<?= h($pdfUrl) ?>" target="_blank"
               class="inline-flex items-center gap-1 rounded-2xl bg-white/10 border border-white/20 px-4 py-2 text-[11px] text-slate-50 hover:bg-white/15">
              📄 Abrir PDF
            </a>
          <?php elseif ($pdfPath): ?>
            <a href="<?= h($pdfPath) ?>" target="_blank"
               class="inline-flex items-center gap-1 rounded-2xl bg-white/10 border border-white/20 px-4 py-2 text-[11px] text-slate-50 hover:bg-white/15">
              📄 Abrir PDF
            </a>
          <?php endif; ?>

          <button type="button" onclick="window.print()"
            class="inline-flex items-center gap-1 rounded-2xl bg-white/10 border border-white/20 px-4 py-2 text-[11px] text-slate-50 hover:bg-white/15">
            🖨️ Imprimir
          </button>

          <button type="button" onclick="copyReglamento()"
            class="inline-flex items-center gap-1 rounded-2xl bg-gradient-to-r from-sky-500 to-indigo-500 px-4 py-2 text-[11px] font-medium text-white shadow-lg shadow-sky-900/40">
            📋 Copiar texto
          </button>
        </div>
      </div>

      <!-- Contenido -->
      <?php if (trim($contenido) === '' && !$pdfUrl && !$pdfPath): ?>
        <div class="rounded-2xl bg-amber-500/15 border border-amber-400/40 px-4 py-3 text-amber-100">
          Este reglamento no tiene contenido en texto y tampoco hay un PDF asociado.
          Carga el contenido o un PDF desde administración.
        </div>
      <?php elseif (trim($contenido) === '' && ($pdfUrl || $pdfPath)): ?>
        <div class="rounded-2xl bg-white/5 border border-white/12 px-4 py-3 text-slate-200/90">
          Este reglamento está disponible únicamente en PDF. Usa el botón <b>Abrir PDF</b>.
        </div>
      <?php else: ?>
        <div id="reglamentoText" class="prose prose-invert max-w-none text-[12px] leading-relaxed">
          <?= nl2br(h($contenido)) ?>
        </div>
      <?php endif; ?>

    <?php endif; ?>

  </div>

</div>

<script>
function copyReglamento() {
  const el = document.getElementById('reglamentoText');
  if (!el) {
    alert('No hay texto disponible para copiar (revisa si el reglamento está en PDF).');
    return;
  }
  const text = el.innerText || el.textContent || '';
  navigator.clipboard.writeText(text).then(() => {
    alert('Reglamento copiado al portapapeles.');
  }).catch(() => {
    alert('No se pudo copiar. Intenta desde un navegador compatible o manualmente.');
  });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
