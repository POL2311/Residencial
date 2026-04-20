<?php
// layouts/dashboard_layout.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';
require_login();
$user = current_user();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard', ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            colors: {
              primary: {
                50: '#ecfeff',
                100: '#cffafe',
                500: '#0ea5e9',
                600: '#0284c7',
                900: '#0b1120',
              },
            },
          }
        }
      }
    </script>
</head>
<body class="min-h-screen bg-slate-950 bg-[radial-gradient(circle_at_top,_rgba(56,189,248,0.35),_transparent_60%),_radial-gradient(circle_at_bottom,_rgba(129,140,248,0.25),_transparent_60%)] text-slate-100">

<div class="min-h-screen flex">

<aside
    id="sidebar"
    class="fixed inset-y-0 left-0 z-40 w-72 max-w-full
           border-r border-white/10 bg-white/5 backdrop-blur-2xl
           shadow-xl shadow-sky-500/10
           transform transition-transform duration-200
           -translate-x-full md:translate-x-0
           md:relative md:z-auto md:flex md:flex-col"
>
        <div class="px-6 py-5 border-b border-white/10 flex items-center gap-3">
             <button id="btnCloseSidebar"
                class="md:hidden absolute top-3 right-3 inline-flex items-center justify-center
                       h-8 w-8 rounded-full bg-white/10 border border-white/20">
            ✕
        </button>

            <div class="h-9 w-9 rounded-2xl bg-gradient-to-tr from-sky-400 to-indigo-500 flex items-center justify-center text-sm font-bold">
                SR
            </div>
            <div>
                <h1 class="text-sm font-semibold leading-tight">Sistema Residencial</h1>
                <p class="text-[11px] text-slate-300/80">Panel de administración</p>
            </div>
        </div>

        <div class="flex-1 px-4 py-5 space-y-6 overflow-y-auto">

            <!-- Usuario -->
            <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 flex items-center gap-3">
                <div class="h-9 w-9 rounded-full bg-gradient-to-tr from-sky-400/70 to-indigo-500/70 flex items-center justify-center text-xs font-semibold">
                    <?= strtoupper(substr($user['name'], 0, 2)) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium truncate"><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="text-[11px] text-slate-300/80 truncate"><?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>

            <?php
            // =========================
            // Menú por rol
            // =========================
            $menu = [];

            if ($user['role'] === 'super_admin') {
                $menu = [
                    'overview'    => [
                        'label' => 'Resumen',
                        'icon'  => '🏠',
                        'url'   => 'dashboard_super_admin.php',
                    ],
                    'residences'  => [
                        'label' => 'Residenciales',
                        'icon'  => '🏢',
                        'url'   => 'superadmin_residenciales.php',
                    ],
                    'users'       => [
                        'label' => 'Usuarios y roles',
                        'icon'  => '👥',
                        'url'   => 'superadmin_usuarios.php',
                    ],
                    'security'    => [
                        'label' => 'Seguridad y accesos',
                        'icon'  => '🛡️',
                        'url'   => 'superadmin_seguridad.php',
                    ],
                    'reports'     => [
                        'label' => 'Reportes y analítica',
                        'icon'  => '📊',
                        'url'   => 'superadmin_reportes.php',
                    ],
                    'settings'    => [
                        'label' => 'Configuración',
                        'icon'  => '⚙️',
                        'url'   => 'superadmin_configuracion.php',
                    ],
                ];
                } elseif ($user['role'] === 'admin_residencial') {
    $menu = [
        'overview' => [
            'label' => 'Resumen',
            'icon'  => '🏠',
            'url'   => 'dashboard_admin_residencial.php',
        ],
        'unidades' => [
            'label' => 'Unidades',
            'icon'  => '🏡',
            'url'   => 'unidades.php',
        ],
        'residentes' => [
            'label' => 'Residentes',
            'icon'  => '👥',
            'url'   => 'residentes.php',
        ],
        'autos' => [
        'label' => 'Autos',
        'icon'  => '🚗',
        'url'   => 'autos.php',
        ],
        'guardias' => [
            'label' => 'Guardias',
            'icon'  => '🛡️',
            'url'   => 'guardias.php',
        ],
        'incidencias' => [
            'label' => 'Incidencias',
            'icon'  => '🚨',
            'url'   => 'admin_residencial_incidencias.php',
        ],
        'reglamento' => [
            'label' => 'Reglamento',
            'icon'  => '📘',
            'url'   => 'reglamento.php',
        ],
        'comunicados' => [
            'label' => 'Comunicados',
            'icon'  => '📢',
            'url'   => 'comunicados.php',
        ],
    ];
}


                elseif ($user['role'] === 'admin_supervisor') {
                    $menu = [
                        'overview' => [
                            'label' => 'Resumen',
                            'icon'  => '🏠',
                            'url'   => '/admin_supervisor/dashboard_admin_supervisor.php',
                        ],
                    ];
                } elseif ($user['role'] === 'guardia') {
                            $menu = [
                                'overview' => [
                                    'label' => 'Resumen',
                                    'icon'  => '🏠',
                                    'url'   => 'dashboard_guard.php',
                                ],
                                'control_accesos' => [
                                    'label' => 'Control de accesos',
                                    'icon'  => '📇',
                                    'url'   => 'accesos.php',
                                ],
                                'paqueteria' => [
                                    'label' => 'Paquetería',
                                    'icon'  => '📦',
                                    'url'   => 'paqueteria.php',
                                ],
                                'incidencias' => [
                                    'label' => 'Incidencias',
                                    'icon'  => '🚨',
                                    'url'   => 'incidencias.php',
                                ],
                                'autos' => [
                                'label' => 'Autos',
                                'icon'  => '🚗',
                                'url'   => 'autos.php',
                                ],
                            ];
                        }
                elseif ($user['role'] === 'residente') {
                $menu = [
                    'overview' => [
                        'label' => 'Resumen',
                        'icon'  => '🏠',
                        'url'   => 'dashboard_resident.php',
                    ],
                    'visitas' => [
                        'label' => 'Visitas',
                        'icon'  => '📄',
                        'url'   => 'visitas.php',
                    ],
                    'incidencias' => [
                        'label' => 'Incidencias',
                        'icon'  => '🚨',
                        'url'   => 'incidencias.php',
                    ],
                    'reglamento' => [
                        'label' => 'Reglamento',
                        'icon'  => '📘',
                        'url'   => 'reglamento.php',
                    ],
                    'comunicados' => [
                        'label' => 'Comunicados',
                        'icon'  => '📢',
                        'url'   => 'comunicados.php',
                    ],
                    'Historial de pago' => [
                        'label' => 'Pagos',
                        'icon'  => '💰',
                        'url'   => 'pagos.php',
                    ],
                    'autos' => [
                        'label' => 'Autos',
                        'icon'  => '🚗',
                        'url'   => 'autos.php',
                    ],
                    'Mi perfil' => [
                        'label' => 'Perfil',
                        'icon'  => '👤',
                        'url'   => 'perfil.php',
                    ],
                    
                ];
            }

                    else {
                    // fallback genérico
                    $menu = [
                        'overview' => [
                            'label' => 'Resumen',
                            'icon'  => '🏠',
                            'url'   => '/index.php',
                        ],
                    ];
                }
                ?>

                <nav class="space-y-1 text-sm">
                    <?php foreach ($menu as $key => $item):
                        $isActive = ($activeMenu ?? '') === $key;
                    ?>
                        <a href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition
                                <?= $isActive
                                    ? 'bg-white/15 border border-white/20 shadow-lg shadow-sky-500/20 text-sky-100'
                                    : 'text-slate-200/80 hover:bg-white/5 hover:text-white' ?>">
                            <span class="text-base"><?= $item['icon'] ?></span>
                            <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>

            <div class="px-4 py-4 border-t border-white/10 text-[11px] text-slate-400/80">
                © <?= date('Y') ?> Sistema Residencial
            </div>
        </aside>

    <!-- Contenido principal -->
    <div class="flex-1 flex flex-col">

        <!-- Topbar glass -->
        <header class="sticky top-0 z-10 border-b border-white/10 bg-slate-900/40 backdrop-blur-2xl px-4 md:px-6 py-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <button id="btnToggleSidebar"
                class="md:hidden inline-flex items-center justify-center h-9 w-9 rounded-full bg-white/10 border border-white/20">
            ☰
        </button>
                <div>
                    <h2 class="text-sm font-semibold leading-tight"><?= htmlspecialchars($pageTitle ?? 'Dashboard', ENT_QUOTES, 'UTF-8') ?></h2>
                    <p class="text-[11px] text-slate-300/80">Panel general del sistema residencial</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <span class="hidden sm:inline text-[11px] text-slate-300/80">
                    Sesión iniciada como <span class="font-semibold"><?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') ?></span>
                </span>
                <a href="<?= htmlspecialchars(app_logout_url(), ENT_QUOTES, 'UTF-8') ?>"
                   class="inline-flex items-center px-3 py-1.5 rounded-full text-[11px] font-medium bg-white/10 border border-white/20 hover:bg-white/20 transition">
                    Cerrar sesión
                </a>
            </div>
        </header>

        <!-- Fondo glass principal -->
        <main class="flex-1 px-4 md:px-6 py-6">
            <div class="mx-auto max-w-6xl">
                <!-- tarjeta principal glass -->
                <div class="rounded-3xl border border-white/10 bg-gradient-to-br from-white/10 via-white/5 to-transparent backdrop-blur-2xl shadow-2xl shadow-sky-900/40 overflow-hidden">
                    <div class="px-5 py-4 border-b border-white/10 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-slate-50"><?= htmlspecialchars($pageTitle ?? 'Dashboard', ENT_QUOTES, 'UTF-8') ?></h3>
                            <p class="text-xs text-slate-200/80">
                                Bienvenido, <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>.
                            </p>
                        </div>
                        <div class="flex items-center gap-2 text-[11px]">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-500/15 border border-emerald-400/40 text-emerald-100">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                Sistema activo
                            </span>
                        </div>
                    </div>

                    <div class="p-5 md:p-6 space-y-5">
                        <?= $content ?? '' ?>
                    </div>
                </div>
            </div>
        </main>

    </div>
</div>
<script>
  (function () {
    const btnOpen  = document.getElementById('btnToggleSidebar');
    const btnClose = document.getElementById('btnCloseSidebar');
    const aside    = document.getElementById('sidebar');

    if (!aside) return;

    function toggleSidebar() {
      aside.classList.toggle('-translate-x-full');
    }

    if (btnOpen)  btnOpen.addEventListener('click', toggleSidebar);
    if (btnClose) btnClose.addEventListener('click', toggleSidebar);

    // Asegura que en desktop el menú siempre esté visible
    window.addEventListener('resize', () => {
      if (window.innerWidth >= 768) {
        aside.classList.remove('-translate-x-full');
      }
    });
  })();
</script>

</body>

</html>
