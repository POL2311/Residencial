<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/config.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_login();
require_role(['super_admin']);

$pageTitle = 'Panel Super Admin';

$toastV = @filemtime(__DIR__ . '/../../assets/js/app-toast.js') ?: time();
$dashV = @filemtime(__DIR__ . '/../js/dashboard.js') ?: time();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-[#F2F3F5] text-slate-900">
  <?php include __DIR__ . '/../templates/dashboard.html'; ?>

  <script src="../../assets/js/app-toast.js?v=<?= (int)$toastV ?>" defer></script>
  <script src="../js/dashboard.js?v=<?= (int)$dashV ?>" defer></script>
</body>
</html>
