<?php
// login.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/auth.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Por favor ingresa tu correo y contraseña.';
    } else {
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
            // Login correcto
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['tipo_usuario_nombre']; // super_admin, guardia, etc.

            switch ($user['tipo_usuario_nombre']) {
                case 'super_admin':
                    header('Location: superadmin/dashboard_super_admin.php');
                    break;
                case 'admin_supervisor':
                    header('Location: admin_supervisor/dashboard_admin_supervisor.php');
                    break;
                case 'admin_residencial':
                    header('Location: admin_residencial/php/dashboard.php');
                    break;
                case 'guardia':
                    header('Location: guardia/php/dashboard.php');
                    break;
                case 'residente':
                default:
                    header('Location: residente/php/dashboard.php');
                    break;
            }
            exit;
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
<body class="min-h-screen flex items-center justify-center bg-slate-100">
    <div class="w-full max-w-md bg-white shadow-lg rounded-xl p-8">
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

        <form method="POST" action="login.php" class="space-y-4">
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
