<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/password_reset.php';

if (is_logged_in()) {
    header('Location: ' . app_role_home_url((string)($_SESSION['user_role'] ?? '')));
    exit;
}

$step = trim((string)($_GET['step'] ?? 'request'));
if (!in_array($step, ['request', 'verify', 'reset'], true)) {
    $step = 'request';
}

$email = strtolower(trim((string)($_GET['email'] ?? '')));
$emailIsValid = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL);
$requestEmailPrefill = $emailIsValid ? $email : '';
$requestRedirectBase = app_url('recuperar_password.php?step=verify');
$resetRedirectUrl = app_url('recuperar_password.php?step=reset');
$loginRedirectUrl = app_login_url();

$stepMessage = match ($step) {
    'verify' => 'Captura el código de 6 dígitos que recibiste por correo. Si pediste más de uno, usa el más reciente.',
    'reset' => 'Define una nueva contraseña para tu cuenta.',
    default => 'Te enviaremos un código de recuperación a tu correo.',
};

$step = ($step === 'verify' && !$emailIsValid) ? 'request' : $step;
$canReset = password_reset_get_verified_session() !== null;
if ($step === 'reset' && !$canReset) {
    $step = 'request';
}

$csrfToken = app_csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Restablecer contraseña</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-[100dvh] bg-gradient-to-br from-slate-100 via-slate-50 to-slate-200">
    <div class="flex min-h-[100dvh] items-center justify-center px-4 py-5 sm:px-6 sm:py-8">
        <div class="mx-auto w-full max-w-md rounded-[1.75rem] bg-white p-5 shadow-xl sm:p-8">
            <div class="mb-5">
                <a href="<?= htmlspecialchars(app_login_url(), ENT_QUOTES, 'UTF-8') ?>" class="text-sm text-slate-500 hover:text-slate-700">← Volver al inicio de sesión</a>
            </div>

            <h1 class="mb-2 text-center text-2xl font-bold text-slate-800 sm:text-[1.9rem]">
                Restablecer contraseña
            </h1>
            <p class="mb-6 text-center text-sm leading-6 text-slate-500">
                <?= htmlspecialchars($stepMessage, ENT_QUOTES, 'UTF-8') ?>
            </p>

            <div id="resetAlert" class="hidden mb-4 rounded-xl border px-4 py-3 text-sm"></div>

            <?php if ($step === 'request'): ?>
                <form id="requestResetForm" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="resetEmail">Correo electrónico</label>
                        <input
                            type="email"
                            id="resetEmail"
                            name="email"
                            required
                            value="<?= htmlspecialchars($requestEmailPrefill, ENT_QUOTES, 'UTF-8') ?>"
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-500 focus:ring-slate-500"
                            placeholder="tucorreo@ejemplo.com"
                        >
                    </div>
                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-slate-800 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-900">
                        Enviar código
                    </button>
                </form>
            <?php elseif ($step === 'verify'): ?>
                <form id="verifyResetForm" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                        Correo: <span class="font-medium text-slate-800"><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="resetCode">Código</label>
                        <input
                            type="text"
                            id="resetCode"
                            name="code"
                            inputmode="numeric"
                            pattern="[0-9]{6}"
                            maxlength="6"
                            required
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-center text-2xl tracking-[0.35em] text-slate-800 focus:border-slate-500 focus:ring-slate-500"
                            placeholder="000000"
                        >
                    </div>
                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-slate-800 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-900">
                        Validar código
                    </button>
                    <button type="button" id="btnResendCode" class="inline-flex w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                        Reenviar código
                    </button>
                </form>
            <?php else: ?>
                <form id="resetPasswordForm" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="newPassword">Nueva contraseña</label>
                        <input
                            type="password"
                            id="newPassword"
                            name="password"
                            minlength="6"
                            required
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-500 focus:ring-slate-500"
                            placeholder="Mínimo 6 caracteres"
                        >
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="newPasswordConfirm">Confirmar contraseña</label>
                        <input
                            type="password"
                            id="newPasswordConfirm"
                            name="password_confirm"
                            minlength="6"
                            required
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-500 focus:ring-slate-500"
                            placeholder="Repite tu nueva contraseña"
                        >
                    </div>
                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-slate-800 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-900">
                        Guardar nueva contraseña
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
    (() => {
        const API = <?= json_encode(app_url('password_reset_api.php'), JSON_UNESCAPED_UNICODE) ?>;
        const currentStep = <?= json_encode($step, JSON_UNESCAPED_UNICODE) ?>;
        const requestRedirectBase = <?= json_encode($requestRedirectBase, JSON_UNESCAPED_UNICODE) ?>;
        const resetRedirectUrl = <?= json_encode($resetRedirectUrl, JSON_UNESCAPED_UNICODE) ?>;
        const loginRedirectUrl = <?= json_encode($loginRedirectUrl, JSON_UNESCAPED_UNICODE) ?>;
        const alertBox = document.getElementById('resetAlert');

        function showAlert(type, message) {
            if (!alertBox) return;
            alertBox.classList.remove('hidden');
            alertBox.className = `mb-4 rounded-xl border px-4 py-3 text-sm ${
                type === 'ok'
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                    : 'border-rose-200 bg-rose-50 text-rose-800'
            }`;
            alertBox.textContent = message;
        }

        async function post(payload) {
            const fd = new FormData();
            Object.entries(payload).forEach(([key, value]) => fd.append(key, value));
            const res = await fetch(API, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: { Accept: 'application/json' }
            });
            const json = await res.json().catch(() => null);
            if (!res.ok || !json || !json.ok) {
                throw new Error(json?.error || 'No se pudo procesar la solicitud.');
            }
            return json;
        }

        if (currentStep === 'request') {
            const form = document.getElementById('requestResetForm');
            form?.addEventListener('submit', async (event) => {
                event.preventDefault();
                try {
                    const email = form.email.value.trim().toLowerCase();
                    const json = await post({
                        action: 'request_reset_code',
                        csrf_token: form.csrf_token.value,
                        email,
                    });
                    showAlert('ok', json.message || 'Si el correo existe, te enviamos un código.');
                    setTimeout(() => {
                        window.location.href = requestRedirectBase + '&email=' + encodeURIComponent(email);
                    }, 900);
                } catch (error) {
                    showAlert('error', error.message || 'No se pudo enviar el código.');
                }
            });
        }

        if (currentStep === 'verify') {
            const form = document.getElementById('verifyResetForm');
            const btnResend = document.getElementById('btnResendCode');

            form?.addEventListener('submit', async (event) => {
                event.preventDefault();
                try {
                    await post({
                        action: 'verify_reset_code',
                        csrf_token: form.csrf_token.value,
                        email: form.email.value,
                        code: form.code.value,
                    });
                    window.location.href = resetRedirectUrl;
                } catch (error) {
                    showAlert('error', error.message || 'No se pudo validar el código.');
                }
            });

            btnResend?.addEventListener('click', async () => {
                try {
                    const json = await post({
                        action: 'request_reset_code',
                        csrf_token: form.csrf_token.value,
                        email: form.email.value,
                    });
                    showAlert('ok', json.message || 'Si el correo existe, te enviamos un código.');
                } catch (error) {
                    showAlert('error', error.message || 'No se pudo reenviar el código.');
                }
            });
        }

        if (currentStep === 'reset') {
            const form = document.getElementById('resetPasswordForm');
            form?.addEventListener('submit', async (event) => {
                event.preventDefault();
                try {
                    const json = await post({
                        action: 'reset_password_with_code',
                        csrf_token: form.csrf_token.value,
                        password: form.password.value,
                        password_confirm: form.password_confirm.value,
                    });
                    showAlert('ok', json.message || 'Contraseña actualizada correctamente.');
                    setTimeout(() => {
                        window.location.href = json.redirect || loginRedirectUrl;
                    }, 1000);
                } catch (error) {
                    showAlert('error', error.message || 'No se pudo actualizar la contraseña.');
                }
            });
        }
    })();
    </script>
</body>
</html>
