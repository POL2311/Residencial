<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/config.php';

require_login();
require_role(['super_admin']);

header('Location: php/dashboard.php#seguridad');
exit;
