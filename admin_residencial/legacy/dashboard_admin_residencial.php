<?php
// dashboard_admin_residencial.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';
require_role(['admin_residencial']);

$user = current_user();
$pageTitle  = 'Panel del residencial';
$activeMenu = 'overview';

$success = '';
$error   = '';

// 1) Encontrar el residencial principal del admin
$residencial = null;

try {
    $stmtUr = $pdo->prepare("
        SELECT r.*, p.nombre AS nombre_plan, p.codigo AS codigo_plan
        FROM usuarios_residenciales ur
        JOIN residenciales r ON r.id = ur.residencial_id
        LEFT JOIN planes p ON p.id = r.plan_id
        WHERE ur.user_id = :user_id
        ORDER BY ur.es_principal DESC, ur.created_at ASC
        LIMIT 1
    ");
    $stmtUr->execute(['user_id' => $user['id']]);
    $residencial = $stmtUr->fetch();
} catch (PDOException $e) {
    $error = 'Error al obtener el residencial asignado: ' . $e->getMessage();
}

// Si no tiene residencial asignado, solo mostramos mensaje
if (!$residencial) {
    ob_start();
    ?>
    <div class="space-y-4">
        <?php if ($error): ?>
            <div class="rounded-2xl bg-rose-500/15 border border-rose-400/50 px-4 py-3 text-xs text-rose-100">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="rounded-2xl border border-white/15 bg-white/5 backdrop-blur-xl p-4 md:p-5 text-sm text-slate-200/90">
            <p class="mb-2 font-semibold">Aún no tienes un residencial asignado como administrador.</p>
            <p class="text-xs text-slate-300/90">
                Pide al super admin que te asigne a un residencial desde el módulo
                <span class="font-semibold">“Usuarios y roles &rarr; Asignar usuario a residencial”</span>.
            </p>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    include __DIR__ . '/dashboard_layout.php';
    exit;
}

// 2) Calcular métricas del residencial (guardias, residentes, otros admins)
$stats = [
    'guardias'          => 0,
    'residentes'        => 0,
    'admins_residencial'=> 0,
    'admins_supervisor' => 0,
];

try {
    // Guardias
    $stmtG = $pdo->prepare("
        SELECT COUNT(*) FROM usuarios_residenciales ur
        JOIN users u ON u.id = ur.user_id
        JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
        WHERE ur.residencial_id = :resid
          AND t.nombre = 'guardia'
    ");
    $stmtG->execute(['resid' => $residencial['id']]);
    $stats['guardias'] = (int)$stmtG->fetchColumn();

    // Residentes
    $stmtR = $pdo->prepare("
        SELECT COUNT(*) FROM usuarios_residenciales ur
        JOIN users u ON u.id = ur.user_id
        JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
        WHERE ur.residencial_id = :resid
          AND t.nombre = 'residente'
    ");
    $stmtR->execute(['resid' => $residencial['id']]);
    $stats['residentes'] = (int)$stmtR->fetchColumn();

    // Admin residencial
    $stmtAR = $pdo->prepare("
        SELECT COUNT(*) FROM usuarios_residenciales ur
        JOIN users u ON u.id = ur.user_id
        JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
        WHERE ur.residencial_id = :resid
          AND t.nombre = 'admin_residencial'
    ");
    $stmtAR->execute(['resid' => $residencial['id']]);
    $stats['admins_residencial'] = (int)$stmtAR->fetchColumn();

    // Admin supervisor
    $stmtAS = $pdo->prepare("
        SELECT COUNT(*) FROM usuarios_residenciales ur
        JOIN users u ON u.id = ur.user_id
        JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
        WHERE ur.residencial_id = :resid
          AND t.nombre = 'admin_supervisor'
    ");
    $stmtAS->execute(['resid' => $residencial['id']]);
    $stats['admins_supervisor'] = (int)$stmtAS->fetchColumn();

} catch (PDOException $e) {
    $error = 'Error al calcular métricas del residencial: ' . $e->getMessage();
}

// 3) Render del dashboard del admin_residencial
ob_start();
?>

<div class="space-y-6">

    <?php if ($error): ?>
        <div class="rounded-2xl bg-rose-500/15 border border-rose-400/50 px-4 py-3 text-xs text-rose-100">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <!-- Encabezado: info del residencial -->
    <div class="rounded-2xl border border-white/15 bg-gradient-to-r from-sky-500/20 via-slate-900/60 to-indigo-500/20 backdrop-blur-2xl p-4 md:p-5">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <p class="text-[11px] uppercase tracking-wide text-sky-200/80 mb-1">Residencial asignado</p>
                <h2 class="text-xl font-semibold text-slate-50">
                    <?= htmlspecialchars($residencial['nombre'], ENT_QUOTES, 'UTF-8') ?>
                </h2>
                <p class="text-xs text-slate-200/80 mt-1">
                    <?= htmlspecialchars($residencial['ciudad'] . ', ' . $residencial['estado'] . ', ' . $residencial['pais'], ENT_QUOTES, 'UTF-8') ?>
                </p>
                <p class="text-[11px] text-slate-300/80 mt-1">
                    Código interno: <span class="font-mono"><?= htmlspecialchars($residencial['codigo'], ENT_QUOTES, 'UTF-8') ?></span>
                    · Tipo: <?= htmlspecialchars($residencial['tipo'], ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
            <div class="text-xs text-slate-200/90 md:text-right">
                <p class="mb-1">
                    Plan:
                    <?php if ($residencial['plan_id']): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-white/10 border border-white/20">
                            <?= htmlspecialchars($residencial['nombre_plan'] ?? 'Plan', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    <?php else: ?>
                        <span class="text-slate-300/80">Sin plan asignado</span>
                    <?php endif; ?>
                </p>
                <p class="text-[11px] text-slate-300/80">
                    Estatus del plan:
                    <span class="font-medium">
                        <?= htmlspecialchars($residencial['estatus_plan'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </p>
                <p class="text-[11px] text-slate-300/80 mt-1">
                    Zona horaria: <?= htmlspecialchars($residencial['zona_horaria'], ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-2 text-[11px] text-slate-100">
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-white/10 border border-white/20">
                Casas/deptos máx: <?= (int)$residencial['max_casas'] ?>
            </span>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-white/10 border border-white/20">
                Guardias máx: <?= (int)$residencial['max_guardias'] ?>
            </span>
            <?php if ((int)$residencial['permite_qr'] === 1): ?>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-500/20 border border-emerald-400/40">
                    QR habilitado
                </span>
            <?php endif; ?>
            <?php if ((int)$residencial['permite_trabajadores_recurrentes'] === 1): ?>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-500/20 border border-emerald-400/40">
                    Trabajadores recurrentes
                </span>
            <?php endif; ?>
            <?php if ((int)$residencial['requiere_placa_vehiculo'] === 1): ?>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-sky-500/20 border border-sky-400/40">
                    Requiere placa vehículo
                </span>
            <?php endif; ?>
            <?php if ((int)$residencial['requiere_identificacion_visita'] === 1): ?>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-sky-500/20 border border-sky-400/40">
                    Requiere identificación visita
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats principales -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="rounded-2xl border border-white/15 bg-white/5 backdrop-blur-xl p-4">
            <p class="text-[11px] uppercase tracking-wide text-slate-200/80 mb-1">Guardias</p>
            <p class="text-2xl font-semibold mb-1"><?= $stats['guardias'] ?></p>
            <p class="text-[11px] text-slate-300/80">Usuarios con rol guardia asignados a este residencial.</p>
        </div>
        <div class="rounded-2xl border border-white/15 bg-white/5 backdrop-blur-xl p-4">
            <p class="text-[11px] uppercase tracking-wide text-slate-200/80 mb-1">Residentes</p>
            <p class="text-2xl font-semibold mb-1"><?= $stats['residentes'] ?></p>
            <p class="text-[11px] text-slate-300/80">Usuarios con rol residente ligados a este residencial.</p>
        </div>
        <div class="rounded-2xl border border-white/15 bg-white/5 backdrop-blur-xl p-4">
            <p class="text-[11px] uppercase tracking-wide text-slate-200/80 mb-1">Admins residenciales</p>
            <p class="text-2xl font-semibold mb-1"><?= $stats['admins_residencial'] ?></p>
            <p class="text-[11px] text-slate-300/80">Incluyéndote a ti y otros admins.</p>
        </div>
        <div class="rounded-2xl border border-white/15 bg-white/5 backdrop-blur-xl p-4">
            <p class="text-[11px] uppercase tracking-wide text-slate-200/80 mb-1">Admins supervisor</p>
            <p class="text-2xl font-semibold mb-1"><?= $stats['admins_supervisor'] ?></p>
            <p class="text-[11px] text-slate-300/80">Usuarios con rol de supervisión de este residencial.</p>
        </div>
    </div>

    <!-- Secciones futuras -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="rounded-2xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4">
            <h4 class="text-sm font-semibold mb-2">Próximos módulos</h4>
            <ul class="list-disc list-inside text-xs text-slate-200/90 space-y-1">
                <li>Administrar casas/departamentos y asignar residentes.</li>
                <li>Gestión de guardias: altas, bajas y turnos.</li>
                <li>Configuración específica del residencial (horarios, reglas, etc.).</li>
            </ul>
        </div>
        <div class="rounded-2xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4">
            <h4 class="text-sm font-semibold mb-2">Acciones rápidas (idea)</h4>
            <p class="text-xs text-slate-200/90 mb-2">
                Aquí podrás agregar accesos directos a:
            </p>
            <ul class="list-disc list-inside text-xs text-slate-200/90 space-y-1">
                <li>Crear residente.</li>
                <li>Registrar guardia.</li>
                <li>Ver últimos accesos o incidencias.</li>
            </ul>
        </div>
    </div>

</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
