<?php
// residente/perfil.php
require_once __DIR__ . '/../config/auth.php';
require_role(['residente']);
require_once __DIR__ . '/../config/config.php';

$user       = current_user();
$pageTitle  = 'Mi perfil';
$activeMenu = 'perfil';

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$success = '';
$error   = '';

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

// Tabla de contactos emergencia (si no existe, damos SQL listo)
$hasCE = tableExists($pdo, 'contactos_emergencia');

// -------------------------
// Acciones POST
// -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // 1) Actualizar perfil básico
    if ($accion === 'update_profile') {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');

        $errs = [];
        if ($name === '') $errs[] = 'El nombre es obligatorio.';
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errs[] = 'Correo inválido.';

        if (!empty($errs)) {
            $error = implode(' ', $errs);
        } else {
            try {
                $stmtE = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1");
                $stmtE->execute(['email' => $email, 'id' => $user['id']]);
                if ($stmtE->fetch()) {
                    $error = 'Ese correo ya está en uso.';
                } else {
                    $stmtU = $pdo->prepare("
                        UPDATE users
                        SET name = :name, email = :email, telefono = :telefono
                        WHERE id = :id
                    ");
                    $stmtU->execute([
                        'name'     => $name,
                        'email'    => $email,
                        'telefono' => ($telefono !== '' ? $telefono : null),
                        'id'       => $user['id'],
                    ]);
                    $success = 'Perfil actualizado correctamente.';

                    // refrescar
                    $user['name'] = $name;
                    $user['email'] = $email;
                    $user['telefono'] = $telefono;
                }
            } catch (PDOException $e) {
                $error = 'Error al actualizar: ' . $e->getMessage();
            }
        }
    }

    // 2) Cambiar contraseña (si tu tabla users tiene password_hash)
    if ($accion === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new1    = $_POST['new_password'] ?? '';
        $new2    = $_POST['new_password_confirm'] ?? '';

        $errs = [];
        if ($current === '' || $new1 === '' || $new2 === '') $errs[] = 'Completa todos los campos de contraseña.';
        if ($new1 !== $new2) $errs[] = 'La confirmación no coincide.';
        if (strlen($new1) < 6) $errs[] = 'La nueva contraseña debe tener al menos 6 caracteres.';

        if (!empty($errs)) {
            $error = implode(' ', $errs);
        } else {
            try {
                $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id LIMIT 1");
                $stmt->execute(['id' => $user['id']]);
                $row = $stmt->fetch();

                if (!$row || empty($row['password_hash'])) {
                    $error = 'No se pudo validar tu contraseña actual (password_hash no disponible).';
                } elseif (!password_verify($current, $row['password_hash'])) {
                    $error = 'La contraseña actual es incorrecta.';
                } else {
                    $hash = password_hash($new1, PASSWORD_DEFAULT);
                    $upd = $pdo->prepare("UPDATE users SET password_hash = :h WHERE id = :id");
                    $upd->execute(['h' => $hash, 'id' => $user['id']]);
                    $success = 'Contraseña actualizada correctamente.';
                }
            } catch (PDOException $e) {
                $error = 'Error al cambiar contraseña: ' . $e->getMessage();
            }
        }
    }

    // 3) Contactos de emergencia (CRUD básico)
    if ($hasCE && $accion === 'add_ce') {
        $nombre = trim($_POST['ce_nombre'] ?? '');
        $tel    = trim($_POST['ce_telefono'] ?? '');
        $rel    = trim($_POST['ce_relacion'] ?? '');

        if ($nombre === '' || $tel === '') {
            $error = 'Nombre y teléfono de contacto son obligatorios.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO contactos_emergencia (user_id, nombre, telefono, relacion, created_at)
                    VALUES (:uid, :nombre, :telefono, :relacion, NOW())
                ");
                $stmt->execute([
                    'uid'      => $user['id'],
                    'nombre'   => $nombre,
                    'telefono' => $tel,
                    'relacion' => ($rel !== '' ? $rel : null),
                ]);
                $success = 'Contacto agregado.';
            } catch (PDOException $e) {
                $error = 'Error al agregar contacto: ' . $e->getMessage();
            }
        }
    }

    if ($hasCE && $accion === 'delete_ce') {
        $id = (int)($_POST['ce_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM contactos_emergencia WHERE id = :id AND user_id = :uid");
            $stmt->execute(['id' => $id, 'uid' => $user['id']]);
            $success = 'Contacto eliminado.';
        } catch (PDOException $e) {
            $error = 'Error al eliminar: ' . $e->getMessage();
        }
    }
}

// Cargar contactos
$contactos = [];
if ($hasCE) {
    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM contactos_emergencia
            WHERE user_id = :uid
            ORDER BY created_at DESC
        ");
        $stmt->execute(['uid' => $user['id']]);
        $contactos = $stmt->fetchAll();
    } catch (PDOException $e) {
        $contactos = [];
    }
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

  <div class="rounded-2xl border border-white/15 bg-white/5 backdrop-blur-xl p-4 md:p-5">
    <h4 class="text-sm font-semibold">Mi perfil</h4>
    <p class="text-[11px] text-slate-300/80">Actualiza tu información y contactos de emergencia.</p>
  </div>

  <!-- Perfil -->
  <div class="rounded-2xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4 md:p-6">
    <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <input type="hidden" name="accion" value="update_profile">

      <div class="md:col-span-2">
        <label class="block mb-1 text-slate-200">Nombre</label>
        <input name="name" value="<?= h($user['name'] ?? '') ?>" required
               class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
      </div>

      <div>
        <label class="block mb-1 text-slate-200">Teléfono</label>
        <input name="telefono" value="<?= h($user['telefono'] ?? '') ?>"
               class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
      </div>

      <div class="md:col-span-3">
        <label class="block mb-1 text-slate-200">Correo</label>
        <input type="email" name="email" value="<?= h($user['email'] ?? '') ?>" required
               class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
      </div>

      <div class="md:col-span-3 flex flex-col sm:flex-row sm:justify-between gap-2 mt-2">
        <a href="autos.php"
           class="inline-flex items-center justify-center px-4 py-2 rounded-2xl bg-white/10 border border-white/20 hover:bg-white/15 text-xs font-medium text-slate-50">
          Mis autos
        </a>

        <button type="submit"
                class="inline-flex items-center justify-center px-4 py-2 rounded-2xl bg-gradient-to-r from-sky-500 to-indigo-500 text-xs font-medium text-white shadow-lg shadow-sky-900/40">
          Guardar cambios
        </button>
      </div>
    </form>
  </div>

  <!-- Cambiar contraseña -->
  <div class="rounded-2xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4 md:p-6">
    <h4 class="text-sm font-semibold mb-3">Cambiar contraseña</h4>

    <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <input type="hidden" name="accion" value="change_password">

      <div class="md:col-span-1">
        <label class="block mb-1 text-slate-200">Contraseña actual</label>
        <input type="password" name="current_password" required
               class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
      </div>

      <div class="md:col-span-1">
        <label class="block mb-1 text-slate-200">Nueva contraseña</label>
        <input type="password" name="new_password" required
               class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
      </div>

      <div class="md:col-span-1">
        <label class="block mb-1 text-slate-200">Confirmar</label>
        <input type="password" name="new_password_confirm" required
               class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
      </div>

      <div class="md:col-span-3 flex justify-end">
        <button class="px-4 py-2 rounded-2xl bg-white/10 border border-white/20 hover:bg-white/15 text-xs font-medium text-slate-50">
          Actualizar contraseña
        </button>
      </div>
    </form>
  </div>

  <!-- Contactos de emergencia -->
  <div class="rounded-2xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4 md:p-6">
    <h4 class="text-sm font-semibold mb-3">Contactos de emergencia</h4>

    <?php if (!$hasCE): ?>
      <p class="text-slate-300/80 mb-3">
        No existe la tabla <code class="px-2 py-1 rounded bg-black/40">contactos_emergencia</code>.
        Si quieres, crea esta tabla y este archivo ya funcionará:
      </p>

      <pre class="text-[11px] text-slate-200/90 bg-black/30 border border-white/10 rounded-2xl p-4 overflow-x-auto"><code>CREATE TABLE contactos_emergencia (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  telefono VARCHAR(40) NOT NULL,
  relacion VARCHAR(80) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ce_user (user_id),
  CONSTRAINT fk_ce_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);</code></pre>

    <?php else: ?>

      <!-- Form add -->
      <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
        <input type="hidden" name="accion" value="add_ce">

        <div>
          <label class="block mb-1 text-slate-200">Nombre *</label>
          <input name="ce_nombre" required
                 class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
        </div>
        <div>
          <label class="block mb-1 text-slate-200">Teléfono *</label>
          <input name="ce_telefono" required
                 class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
        </div>
        <div>
          <label class="block mb-1 text-slate-200">Relación</label>
          <input name="ce_relacion"
                 class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
        </div>

        <div class="md:col-span-3 flex justify-end">
          <button class="px-4 py-2 rounded-2xl bg-gradient-to-r from-sky-500 to-indigo-500 text-xs font-medium text-white shadow-lg shadow-sky-900/40">
            Agregar contacto
          </button>
        </div>
      </form>

      <!-- List -->
      <?php if (empty($contactos)): ?>
        <p class="text-slate-400">Aún no agregas contactos.</p>
      <?php else: ?>
        <div class="space-y-2">
          <?php foreach ($contactos as $c): ?>
            <div class="rounded-2xl bg-white/5 border border-white/12 px-4 py-3 flex items-center justify-between gap-3">
              <div>
                <div class="text-slate-50 font-semibold text-xs"><?= h($c['nombre']) ?></div>
                <div class="text-[11px] text-slate-300/80">
                  Tel: <?= h($c['telefono']) ?>
                  <?= !empty($c['relacion']) ? ' · ' . h($c['relacion']) : '' ?>
                </div>
              </div>
              <form method="POST" onsubmit="return confirm('¿Eliminar contacto?');">
                <input type="hidden" name="accion" value="delete_ce">
                <input type="hidden" name="ce_id" value="<?= (int)$c['id'] ?>">
                <button class="px-3 py-1.5 rounded-2xl border border-rose-400/60 bg-rose-500/10 text-[11px] text-rose-100 hover:bg-rose-500/20">
                  Eliminar
                </button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    <?php endif; ?>
  </div>

</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
