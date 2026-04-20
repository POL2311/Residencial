<?php
// login.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/auth.php';
$error = '';

if (is_logged_in()) {
    header('Location: ' . app_role_home_url((string)($_SESSION['user_role'] ?? '')));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Por favor ingresa tu correo y contraseña.';
    } else {
        try {
            $sql = 'SELECT u.*, t.nombre AS tipo_usuario_nombre
                    FROM users u
                    JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
                    WHERE u.email = :email
                    LIMIT 1';

            $stmt = $pdo->prepare($sql);
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $error = 'Credenciales incorrectas.';
            } elseif ((int)$user['is_active'] !== 1) {
                $error = 'Tu cuenta está bloqueada, contacta al administrador.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['tipo_usuario_nombre'];
                app_csrf_token();
                header('Location: ' . app_role_home_url((string)$user['tipo_usuario_nombre']));
                exit;
            }
        } catch (Throwable $e) {
            app_log_exception($e, 'login');
            $error = 'No pudimos procesar el inicio de sesión en este momento. Inténtalo de nuevo.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login - Sistema Residencial</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-100 via-slate-50 to-slate-200 px-4 py-8 md:py-16">
    <div class="mx-auto w-full max-w-md rounded-2xl bg-white p-8 shadow-lg">
        <h1 class="text-2xl font-bold text-slate-800 mb-2 text-center">
            Acceso al sistema
        </h1>
        <p class="text-sm text-slate-500 mb-6 text-center">
            Inicia sesión con tu cuenta
        </p>

        <?php if ($error): ?>
            <div class="mb-4 rounded-lg bg-red-100 border border-red-300 text-red-700 px-4 py-3 text-sm">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= htmlspecialchars(app_login_url(), ENT_QUOTES, 'UTF-8') ?>" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1" for="email">
                    Correo electrónico
                </label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    required
                    class="w-full rounded-lg border-slate-300 focus:border-slate-500 focus:ring-slate-500 text-sm px-3 py-2"
                    placeholder="tucorreo@ejemplo.com"
                >
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1" for="password">
                    Contraseña
                </label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    class="w-full rounded-lg border-slate-300 focus:border-slate-500 focus:ring-slate-500 text-sm px-3 py-2"
                    placeholder="••••••••"
                >
            </div>

            <button
                type="submit"
                class="w-full inline-flex justify-center items-center px-4 py-2 rounded-lg bg-slate-800 text-white font-medium text-sm hover:bg-slate-900 transition"
            >
                Iniciar sesión
            </button>
        </form>

        <p class="mt-6 text-xs text-center text-slate-400">
            © <?= date('Y') ?> Sistema Residencial. Todos los derechos reservados.
        </p>
    </div>
</body>
</html>
