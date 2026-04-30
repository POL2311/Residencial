<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/service_profile.php';

require_login();
require_role(['residente']);
$resident = current_user();
$residencialId = service_profile_resolve_residencial_id_for_user($pdo, (int)($resident['id'] ?? 0));
if ($residencialId > 0) {
  service_profile_require_role_enabled($pdo, $residencialId, 'residente', 'El portal de residente no está habilitado para el perfil de servicio de este cliente.');
}

$pageTitle = 'Portal del residente';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

  <!-- Tailwind CDN (si ya lo cargas global, puedes quitarlo) -->
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-[#F2F3F5] text-slate-900">
  <?php include __DIR__ . '/../templates/dashboard.html'; ?>

  <script src="../js/dashboard.js" defer></script>
</body>
</html>
