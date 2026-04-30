<?php
// guardia/php/dashboard.php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/service_profile.php';

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
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <audio id="soundOk" src="/assets/sounds/ok.mp3" preload="auto"></audio>
    <audio id="soundNo" src="/assets/sounds/no.mp3" preload="auto"></audio>

  <!-- Tailwind CDN (si ya lo cargas global, puedes quitarlo) -->
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<?php
ob_start();
?>

<div id="guardiaApp">
  <?php include __DIR__ . '/../templates/dashboard.html'; ?>
</div>

<script src="../js/dashboard.js" defer></script>
<?php
