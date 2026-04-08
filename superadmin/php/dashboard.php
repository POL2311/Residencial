<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/config.php';

require_login();
require_role(['super_admin']);

$pageTitle = 'Panel Super Admin';
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

  <script src="../js/dashboard.js" defer></script>
</body>
</html>
