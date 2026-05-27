<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/service_profile.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_login();
require_role(['residente']);
$resident = current_user();
$residencialId = service_profile_resolve_residencial_id_for_user($pdo, (int)($resident['id'] ?? 0));
if ($residencialId > 0) {
  service_profile_require_role_enabled($pdo, $residencialId, 'residente', 'El portal de residente no está habilitado para el perfil de servicio de este cliente.');
}

$pageTitle = 'Portal del residente';

$toastV = @filemtime(__DIR__ . '/../../assets/js/app-toast.js') ?: time();
$modalCssV = @filemtime(__DIR__ . '/../../assets/css/osgate-modals.css') ?: time();
$sharedDesignCssV = @filemtime(__DIR__ . '/../../assets/design/shared-ui.css') ?: time();
$modalJsV = @filemtime(__DIR__ . '/../../assets/js/osgate-modal.js') ?: time();
$dashV = @filemtime(__DIR__ . '/../js/dashboard.js') ?: time();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

  <!-- Tailwind CDN (si ya lo cargas global, puedes quitarlo) -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../../assets/css/osgate-modals.css?v=<?= (int)$modalCssV ?>" />
  <link rel="stylesheet" href="../../assets/design/shared-ui.css?v=<?= (int)$sharedDesignCssV ?>" />
</head>

<body class="min-h-screen bg-[#F2F3F5] text-slate-900">
  <?php include __DIR__ . '/../templates/dashboard.html'; ?>

  <script src="../../assets/js/app-toast.js?v=<?= (int)$toastV ?>" defer></script>
  <script src="../../assets/js/osgate-modal.js?v=<?= (int)$modalJsV ?>" defer></script>
  <script src="../js/dashboard.js?v=<?= (int)$dashV ?>" defer></script>
</body>
</html>
