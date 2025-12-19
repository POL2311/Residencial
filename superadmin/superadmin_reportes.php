<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';
require_role(['super_admin']);

$pageTitle  = 'Reportes y analítica (Super Admin)';
$activeMenu = 'reports';

$error = '';

// Totales básicos
$totalResidenciales = 0;
$totalResidencialesActivos = 0;
$totalUsuarios = 0;
$totalUsuariosActivos = 0;
$usuariosPorRol = [];
$ultimoResidenciales = [];
$ultimoUsuarios = [];

try {
    $totalResidenciales = (int)$pdo->query("SELECT COUNT(*) FROM residenciales")->fetchColumn();
    $totalResidencialesActivos = (int)$pdo->query("SELECT COUNT(*) FROM residenciales WHERE activo = 1")->fetchColumn();
    $totalUsuarios = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalUsuariosActivos = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();

    $stmtRol = $pdo->query("
        SELECT t.nombre AS rol, COUNT(*) AS total
        FROM users u
        JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
        GROUP BY t.nombre
        ORDER BY total DESC
    ");
    $usuariosPorRol = $stmtRol->fetchAll();

    $stmtLastR = $pdo->query("
        SELECT nombre, ciudad, estado, created_at
        FROM residenciales
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $ultimoResidenciales = $stmtLastR->fetchAll();

    $stmtLastU = $pdo->query("
        SELECT u.name, u.email, t.nombre AS rol, u.created_at
        FROM users u
        JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
        ORDER BY u.created_at DESC
        LIMIT 5
    ");
    $ultimoUsuarios = $stmtLastU->fetchAll();

} catch (PDOException $e) {
    $error = 'Error al calcular los reportes: ' . $e->getMessage();
}

ob_start();
?>

<div class="space-y-6">

    <?php if ($error): ?>
        <div class="rounded-2xl bg-rose-500/15 border border-rose-400/50 px-4 py-3 text-xs text-rose-100">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="rounded-2xl border border-white/15 bg-white/5 backdrop-blur-xl p-4">
            <p class="text-[11px] uppercase tracking-wide text-slate-200/80 mb-1">Residenciales activos</p>
            <p class="text-2xl font-semibold mb-1"><?= $totalResidencialesActivos ?></p>
            <p class="text-[11px] text-slate-300/80">De <?= $totalResidenciales ?> totales</p>
        </div>
        <div class="rounded-2xl border border-white/15 bg-white/5 backdrop-blur-xl p-4">
            <p class="text-[11px] uppercase tracking-wide text-slate-200/80 mb-1">Usuarios activos</p>
            <p class="text-2xl font-semibold mb-1"><?= $totalUsuariosActivos ?></p>
            <p class="text-[11px] text-slate-300/80">De <?= $totalUsuarios ?> registrados</p>
        </div>
        <div class="rounded-2xl border border-white/15 bg-white/5 backdrop-blur-xl p-4">
            <p class="text-[11px] uppercase tracking-wide text-slate-200/80 mb-1">Roles diferentes</p>
            <p class="text-2xl font-semibold mb-1"><?= count($usuariosPorRol) ?></p>
            <p class="text-[11px] text-slate-300/80">Super admin, admin, guardias, residentes...</p>
        </div>
        <div class="rounded-2xl border border-white/15 bg-white/5 backdrop-blur-xl p-4">
            <p class="text-[11px] uppercase tracking-wide text-slate-200/80 mb-1">Asignaciones (idea futura)</p>
            <p class="text-2xl font-semibold mb-1">Multi-tenant</p>
            <p class="text-[11px] text-slate-300/80">Usuarios ligados a residenciales.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="rounded-2xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4">
            <h4 class="text-sm font-semibold mb-3">Usuarios por rol</h4>
            <?php if (empty($usuariosPorRol)): ?>
                <p class="text-xs text-slate-400">Sin datos aún.</p>
            <?php else: ?>
                <ul class="space-y-2 text-xs text-slate-200/90">
                    <?php foreach ($usuariosPorRol as $row): ?>
                        <li class="flex justify-between">
                            <span><?= htmlspecialchars($row['rol'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="text-slate-300"><?= (int)$row['total'] ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="rounded-2xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4">
            <h4 class="text-sm font-semibold mb-3">Últimos residenciales creados</h4>
            <?php if (empty($ultimoResidenciales)): ?>
                <p class="text-xs text-slate-400">Sin residenciales aún.</p>
            <?php else: ?>
                <ul class="space-y-2 text-xs text-slate-200/90">
                    <?php foreach ($ultimoResidenciales as $r): ?>
                        <li class="flex justify-between">
                            <div>
                                <p><?= htmlspecialchars($r['nombre'], ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="text-[11px] text-slate-300/80">
                                    <?= htmlspecialchars($r['ciudad'] . ', ' . $r['estado'], ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            </div>
                            <span class="text-[11px] text-slate-400">
                                <?= htmlspecialchars($r['created_at'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="rounded-2xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4">
        <h4 class="text-sm font-semibold mb-3">Últimos usuarios creados</h4>
        <?php if (empty($ultimoUsuarios)): ?>
            <p class="text-xs text-slate-400">Sin usuarios aún.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs border-separate border-spacing-y-1">
                    <thead class="text-[11px] uppercase text-slate-300/80">
                        <tr>
                            <th class="text-left px-3 py-2">Nombre</th>
                            <th class="text-left px-3 py-2">Email</th>
                            <th class="text-left px-3 py-2">Rol</th>
                            <th class="text-left px-3 py-2">Creado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ultimoUsuarios as $u): ?>
                            <tr class="bg-white/5 hover:bg-white/10 transition rounded-xl">
                                <td class="px-3 py-2 rounded-l-xl">
                                    <span class="font-medium text-slate-50">
                                        <?= htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-slate-200/90">
                                    <?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="px-3 py-2 text-slate-200/90">
                                    <?= htmlspecialchars($u['rol'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="px-3 py-2 rounded-r-xl text-slate-300/80">
                                    <?= htmlspecialchars($u['created_at'], ENT_QUOTES, 'UTF-8') ?>
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
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
