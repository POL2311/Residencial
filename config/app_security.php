<?php
declare(strict_types=1);

if (!function_exists('app_project_root_path')) {
    function app_project_root_path(): string
    {
        return realpath(__DIR__ . '/..') ?: dirname(__DIR__);
    }
}

if (!function_exists('app_base_url')) {
    function app_base_url(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $projectRoot = str_replace('\\', '/', app_project_root_path());
        $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
        $docRoot = $docRoot ? str_replace('\\', '/', $docRoot) : '';

        if ($docRoot !== '' && str_starts_with($projectRoot, $docRoot)) {
            $relative = trim(substr($projectRoot, strlen($docRoot)), '/');
            $cached = $relative === '' ? '' : '/' . $relative;
            return $cached;
        }

        $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $parts = array_values(array_filter(explode('/', trim($scriptName, '/'))));
        $cached = isset($parts[0]) ? '/' . $parts[0] : '';
        return $cached;
    }
}

if (!function_exists('app_url')) {
    function app_url(string $path = ''): string
    {
        $base = rtrim(app_base_url(), '/');
        $path = ltrim($path, '/');
        if ($path === '') {
            return $base === '' ? '/' : $base;
        }
        return ($base === '' ? '' : $base) . '/' . $path;
    }
}

if (!function_exists('app_login_url')) {
    function app_login_url(): string
    {
        return app_url('login.php');
    }
}

if (!function_exists('app_logout_url')) {
    function app_logout_url(): string
    {
        return app_url('logout.php');
    }
}

if (!function_exists('app_role_home_url')) {
    function app_role_home_url(?string $role): string
    {
        return match ((string)$role) {
            'super_admin' => app_url('superadmin/php/dashboard.php'),
            'admin_supervisor' => app_url('admin_supervisor/dashboard_admin_supervisor.php'),
            'admin_residencial' => app_url('admin_residencial/php/dashboard.php'),
            'guardia' => app_url('guardia/php/dashboard.php'),
            'residente' => app_url('residente/php/dashboard.php'),
            default => app_login_url(),
        };
    }
}

if (!function_exists('app_expects_json')) {
    function app_expects_json(): bool
    {
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        $xhr = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $uri = strtolower((string)($_SERVER['REQUEST_URI'] ?? ''));
        return str_contains($uri, '/api/')
            || str_contains($accept, 'application/json')
            || $xhr === 'xmlhttprequest';
    }
}

if (!function_exists('app_status_text')) {
    function app_status_text(int $status): string
    {
        return match ($status) {
            401 => 'Sesión requerida',
            403 => 'Acceso denegado',
            404 => 'Página no encontrada',
            419 => 'Sesión expirada',
            500 => 'Error interno',
            default => 'Solicitud no disponible',
        };
    }
}

if (!function_exists('app_ensure_session')) {
    function app_ensure_session(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => $secure,
        ]);
        session_start();
    }
}

if (!function_exists('app_log_exception')) {
    function app_log_exception(Throwable $e, string $context = ''): void
    {
        $prefix = $context !== '' ? '[' . $context . '] ' : '';
        error_log($prefix . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    }
}

if (!function_exists('app_json_out')) {
    function app_json_out(bool $ok, array $extra = [], int $status = 200): void
    {
        http_response_code($status);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }
        echo json_encode(array_merge(['ok' => $ok], $extra), JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('app_json_exception')) {
    function app_json_exception(Throwable $e, string $publicMessage = 'Ocurrió un error interno. Inténtalo de nuevo en un momento.', int $status = 500): void
    {
        $debugId = 'api-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4));

        // Log full diagnostic for server-side troubleshooting.
        error_log('[api][' . $debugId . '] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        error_log('[api][' . $debugId . '] trace=' . $e->getTraceAsString());

        app_json_out(false, ['error' => $publicMessage, 'debug_id' => $debugId], $status);
    }
}

if (!function_exists('app_render_error_page')) {
    function app_render_error_page(int $status, string $title, string $message, array $actions = []): void
    {
        http_response_code($status);
        $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $msgEsc = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $statusEsc = htmlspecialchars(app_status_text($status), ENT_QUOTES, 'UTF-8');
        $actionHtml = '';

        foreach ($actions as $action) {
            $label = htmlspecialchars((string)($action['label'] ?? 'Volver'), ENT_QUOTES, 'UTF-8');
            $href = htmlspecialchars((string)($action['href'] ?? app_login_url()), ENT_QUOTES, 'UTF-8');
            $kind = (string)($action['kind'] ?? 'primary');
            $classes = $kind === 'secondary'
                ? 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
                : 'bg-[#2E5D73] text-white hover:opacity-95';
            $actionHtml .= '<a href="' . $href . '" class="inline-flex items-center justify-center rounded-2xl px-4 py-3 text-sm font-medium ' . $classes . '">' . $label . '</a>';
        }

        if ($actionHtml === '') {
            $actionHtml = '<a href="' . htmlspecialchars(app_login_url(), ENT_QUOTES, 'UTF-8') . '" class="inline-flex items-center justify-center rounded-2xl bg-[#2E5D73] px-4 py-3 text-sm font-medium text-white hover:opacity-95">Ir al inicio de sesión</a>';
        }

        echo '<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>' . $titleEsc . '</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-[#F2F3F5] px-4 py-8 text-slate-900">
  <div class="mx-auto flex min-h-[80vh] max-w-xl items-center justify-center">
    <div class="w-full rounded-[2rem] border border-slate-200 bg-white p-8 shadow-lg">
      <div class="text-xs uppercase tracking-[0.2em] text-slate-400">' . $statusEsc . ' · ' . $status . '</div>
      <h1 class="mt-3 text-3xl font-semibold text-slate-900">' . $titleEsc . '</h1>
      <p class="mt-3 text-sm leading-6 text-slate-600">' . $msgEsc . '</p>
      <div class="mt-6 flex flex-col gap-3 sm:flex-row">' . $actionHtml . '</div>
    </div>
  </div>
</body>
</html>';
        exit;
    }
}

if (!function_exists('app_abort')) {
    function app_abort(int $status, string $title, string $message, array $actions = []): void
    {
        if (app_expects_json()) {
            app_json_out(false, ['error' => $message, 'title' => $title], $status);
        }
        app_render_error_page($status, $title, $message, $actions);
    }
}

if (!function_exists('app_request_origin')) {
    function app_request_origin(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        return $scheme . '://' . $host;
    }
}

if (!function_exists('app_request_is_same_origin')) {
    function app_request_is_same_origin(): bool
    {
        $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
        $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
        $expected = app_request_origin();

        if ($origin !== '') {
            return str_starts_with($origin, $expected);
        }

        if ($referer !== '') {
            return str_starts_with($referer, $expected);
        }

        return true;
    }
}

if (!function_exists('app_csrf_token')) {
    function app_csrf_token(): string
    {
        app_ensure_session();
        if (empty($_SESSION['app_csrf_token'])) {
            $_SESSION['app_csrf_token'] = bin2hex(random_bytes(16));
        }
        return (string)$_SESSION['app_csrf_token'];
    }
}

if (!function_exists('app_require_write_guard')) {
    function app_require_write_guard(): void
    {
        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            return;
        }

        $sent = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if ($sent !== '' && hash_equals(app_csrf_token(), $sent)) {
            return;
        }

        if (app_request_is_same_origin()) {
            return;
        }

        app_abort(
            419,
            'Sesión expirada',
            'No pudimos validar tu sesión. Recarga la página e inténtalo de nuevo.',
            [
                ['label' => 'Ir al inicio de sesión', 'href' => app_login_url()],
            ]
        );
    }
}
