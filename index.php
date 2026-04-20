<?php
require_once __DIR__ . '/config/app_security.php';
header('Location: ' . app_login_url());
exit;
