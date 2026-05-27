<?php
// guardia/php/dashboard.php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/service_profile.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_login();
require_role(['guardia', 'super_admin']);
$user = current_user(); 
if (($user['role'] ?? '') !== 'super_admin') {
    $residencialId = service_profile_resolve_residencial_id_for_user($pdo, (int)($user['id'] ?? 0));
    if ($residencialId > 0) {
        service_profile_require_role_enabled($pdo, $residencialId, 'guardia', 'El panel de guardia no está habilitado para el perfil de servicio de este cliente.');
    }
}

$pageTitle  = 'Panel de guardia';
$activeMenu = 'security';

$toastV = @filemtime(__DIR__ . '/../../assets/js/app-toast.js') ?: time();
$labelsV = @filemtime(__DIR__ . '/../../assets/js/osgate-labels.js') ?: time();
$modalCssV = @filemtime(__DIR__ . '/../../assets/css/osgate-modals.css') ?: time();
$sharedDesignCssV = @filemtime(__DIR__ . '/../../assets/design/shared-ui.css') ?: time();
$modalJsV = @filemtime(__DIR__ . '/../../assets/js/osgate-modal.js') ?: time();
$pwaV = @filemtime(__DIR__ . '/../js/pwa.js') ?: time();
$dashV = @filemtime(__DIR__ . '/../js/dashboard.js') ?: time();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="theme-color" content="#2E5D73" />
  <meta name="mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-title" content="OS Gate Operador" />
  <link rel="manifest" href="../manifest.webmanifest" />
  <link rel="apple-touch-icon" href="../../assets/img/logo_asher.png" />
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../../assets/css/osgate-modals.css?v=<?= (int)$modalCssV ?>" />
  <link rel="stylesheet" href="../../assets/design/shared-ui.css?v=<?= (int)$sharedDesignCssV ?>" />
</head>
<body class="min-h-screen bg-[#F2F3F5] text-slate-900">
  <audio id="soundOk" src="/assets/sounds/ok.mp3" preload="auto"></audio>
  <audio id="soundNo" src="/assets/sounds/no.mp3" preload="auto"></audio>

  <div id="guardiaApp">
    <?php include __DIR__ . '/../templates/dashboard.html'; ?>
  </div>

  <script src="../../assets/js/app-toast.js?v=<?= (int)$toastV ?>" defer></script>
  <script src="../../assets/js/osgate-labels.js?v=<?= (int)$labelsV ?>" defer></script>
  <script src="../../assets/js/osgate-modal.js?v=<?= (int)$modalJsV ?>" defer></script>
  <script src="../js/pwa.js?v=<?= (int)$pwaV ?>" defer></script>
  <script src="../js/dashboard.js?v=<?= (int)$dashV ?>" defer></script>
</body>
</html>
