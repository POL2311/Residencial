<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../config/mailer.php';

$action = sa_post_action('get');

if (!function_exists('sa_ensure_global_services_table')) {
    function sa_ensure_global_services_table(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS home_servicios_globales (
                id INT(11) NOT NULL AUTO_INCREMENT,
                nombre VARCHAR(150) NOT NULL,
                descripcion VARCHAR(255) DEFAULT NULL,
                imagen_url VARCHAR(500) DEFAULT NULL,
                telefono VARCHAR(30) DEFAULT NULL,
                whatsapp VARCHAR(30) DEFAULT NULL,
                link_url VARCHAR(500) DEFAULT NULL,
                categoria VARCHAR(80) NOT NULL DEFAULT 'servicio',
                activo TINYINT(1) NOT NULL DEFAULT 1,
                orden INT(11) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_home_servicios_globales_activo (activo),
                KEY idx_home_servicios_globales_orden (orden)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }
}

if (!function_exists('sa_fetch_global_services')) {
    function sa_fetch_global_services(PDO $pdo): array
    {
        sa_ensure_global_services_table($pdo);
        return $pdo->query("
            SELECT id, nombre, descripcion, imagen_url, telefono, whatsapp, link_url, categoria, activo, orden, created_at, updated_at
            FROM home_servicios_globales
            ORDER BY orden ASC, id DESC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

try {
    if ($action === 'get') {
        app_mailer_ensure_schema($pdo);
        $config = sa_fetch_config_general($pdo);
        sa_json_out(true, [
            'data' => [
                'config' => $config,
                'global_services' => sa_fetch_global_services($pdo),
                'csrf_token' => sa_csrf_token(),
            ],
        ]);
    }

    if ($action === 'save') {
        sa_require_csrf();
        app_mailer_ensure_schema($pdo);
        $config = sa_fetch_config_general($pdo);

        $nombreSistema = sa_clean_str($_POST['nombre_sistema'] ?? 'Sistema Residencial', 150);
        $empresa = sa_clean_str($_POST['empresa'] ?? '', 150);
        $emailSoporte = sa_clean_str($_POST['email_soporte'] ?? '', 150);
        $logoUrl = trim((string)($_POST['logo_url'] ?? ''));
        $colorPrimario = sa_clean_str($_POST['color_primario'] ?? '', 20);
        $colorSecundario = sa_clean_str($_POST['color_secundario'] ?? '', 20);
        $smtpHost = trim((string)($_POST['smtp_host'] ?? ''));
        $smtpPort = (int)($_POST['smtp_port'] ?? 0);
        $smtpUsername = trim((string)($_POST['smtp_username'] ?? ''));
        $smtpPassword = (string)($_POST['smtp_password'] ?? '');
        $smtpEncryption = strtolower(trim((string)($_POST['smtp_encryption'] ?? 'tls')));
        $smtpFromEmail = trim((string)($_POST['smtp_from_email'] ?? ''));
        $smtpFromName = sa_clean_str($_POST['smtp_from_name'] ?? '', 190);

        if ($nombreSistema === '') {
            $nombreSistema = 'Sistema Residencial';
        }

        if ($emailSoporte !== '' && !filter_var($emailSoporte, FILTER_VALIDATE_EMAIL)) {
            sa_json_out(false, ['error' => 'El email de soporte no tiene un formato válido.'], 422);
        }
        if (!in_array($smtpEncryption, ['none', 'tls', 'ssl'], true)) {
            sa_json_out(false, ['error' => 'Selecciona un tipo de cifrado SMTP válido.'], 422);
        }
        if ($smtpPort < 0 || $smtpPort > 65535) {
            sa_json_out(false, ['error' => 'El puerto SMTP no es válido.'], 422);
        }
        if ($smtpFromEmail !== '' && !filter_var($smtpFromEmail, FILTER_VALIDATE_EMAIL)) {
            sa_json_out(false, ['error' => 'El correo remitente SMTP no tiene un formato válido.'], 422);
        }

        $stmt = $pdo->prepare("
            UPDATE config_general
            SET nombre_sistema = :nombre_sistema,
                empresa = :empresa,
                email_soporte = :email_soporte,
                logo_url = :logo_url,
                color_primario = :color_primario,
                color_secundario = :color_secundario,
                smtp_host = :smtp_host,
                smtp_port = :smtp_port,
                smtp_username = :smtp_username,
                smtp_password = :smtp_password,
                smtp_encryption = :smtp_encryption,
                smtp_from_email = :smtp_from_email,
                smtp_from_name = :smtp_from_name
            WHERE id = :id
        ");
        $stmt->execute([
            'nombre_sistema' => $nombreSistema,
            'empresa' => ($empresa !== '' ? $empresa : null),
            'email_soporte' => ($emailSoporte !== '' ? $emailSoporte : null),
            'logo_url' => ($logoUrl !== '' ? $logoUrl : null),
            'color_primario' => ($colorPrimario !== '' ? $colorPrimario : null),
            'color_secundario' => ($colorSecundario !== '' ? $colorSecundario : null),
            'smtp_host' => ($smtpHost !== '' ? $smtpHost : null),
            'smtp_port' => ($smtpPort > 0 ? $smtpPort : null),
            'smtp_username' => ($smtpUsername !== '' ? $smtpUsername : null),
            'smtp_password' => ($smtpPassword !== '' ? $smtpPassword : null),
            'smtp_encryption' => $smtpEncryption,
            'smtp_from_email' => ($smtpFromEmail !== '' ? $smtpFromEmail : null),
            'smtp_from_name' => ($smtpFromName !== '' ? $smtpFromName : null),
            'id' => $config['id'],
        ]);

        sa_json_out(true, ['message' => 'Configuración general actualizada correctamente.']);
    }

    if ($action === 'test_smtp') {
        sa_require_csrf();

        $testEmail = trim((string)($_POST['test_email'] ?? ''));
        if ($testEmail === '' || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            sa_json_out(false, ['error' => 'Ingresa un correo válido para la prueba SMTP.'], 422);
        }

        $settings = [
            'nombre_sistema' => sa_clean_str($_POST['nombre_sistema'] ?? 'Sistema Residencial', 150),
            'smtp_host' => trim((string)($_POST['smtp_host'] ?? '')),
            'smtp_port' => (int)($_POST['smtp_port'] ?? 0),
            'smtp_username' => trim((string)($_POST['smtp_username'] ?? '')),
            'smtp_password' => (string)($_POST['smtp_password'] ?? ''),
            'smtp_encryption' => trim((string)($_POST['smtp_encryption'] ?? 'tls')),
            'smtp_from_email' => trim((string)($_POST['smtp_from_email'] ?? '')),
            'smtp_from_name' => sa_clean_str($_POST['smtp_from_name'] ?? '', 190),
        ];

        app_mailer_send_with_settings(
            $settings,
            $testEmail,
            'Prueba SMTP',
            ($settings['nombre_sistema'] ?: 'Sistema Residencial') . ' · Correo de prueba',
            "Hola,\n\nEste es un correo de prueba para validar la configuración SMTP del sistema.\n\nSi recibiste este mensaje, el envío está funcionando correctamente."
        );

        sa_json_out(true, ['message' => 'Correo de prueba enviado correctamente.']);
    }

    if ($action === 'save_service') {
        sa_require_csrf();
        sa_ensure_global_services_table($pdo);

        $id = (int)($_POST['id'] ?? 0);
        $nombre = sa_clean_str($_POST['nombre'] ?? '', 150);
        $descripcion = sa_clean_str($_POST['descripcion'] ?? '', 255);
        $imagenUrl = trim((string)($_POST['imagen_url'] ?? ''));
        $telefono = sa_clean_str($_POST['telefono'] ?? '', 30);
        $whatsapp = sa_clean_str($_POST['whatsapp'] ?? '', 30);
        $linkUrl = trim((string)($_POST['link_url'] ?? ''));
        $categoria = sa_clean_str($_POST['categoria'] ?? 'servicio', 80);
        $orden = max(0, (int)($_POST['orden'] ?? 0));
        $activo = (int)(($_POST['activo'] ?? '0') === '1');

        if ($nombre === '') {
            sa_json_out(false, ['error' => 'El nombre del servicio global es obligatorio.'], 422);
        }

        foreach (['imagenUrl' => $imagenUrl, 'linkUrl' => $linkUrl] as $field => $value) {
            if ($value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                sa_json_out(false, ['error' => $field === 'imagenUrl' ? 'La URL de imagen no es válida.' : 'El enlace externo no es válido.'], 422);
            }
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("
                UPDATE home_servicios_globales
                SET nombre = :nombre,
                    descripcion = :descripcion,
                    imagen_url = :imagen_url,
                    telefono = :telefono,
                    whatsapp = :whatsapp,
                    link_url = :link_url,
                    categoria = :categoria,
                    activo = :activo,
                    orden = :orden
                WHERE id = :id
            ");
            $stmt->execute([
                'nombre' => $nombre,
                'descripcion' => ($descripcion !== '' ? $descripcion : null),
                'imagen_url' => ($imagenUrl !== '' ? $imagenUrl : null),
                'telefono' => ($telefono !== '' ? $telefono : null),
                'whatsapp' => ($whatsapp !== '' ? $whatsapp : null),
                'link_url' => ($linkUrl !== '' ? $linkUrl : null),
                'categoria' => ($categoria !== '' ? $categoria : 'servicio'),
                'activo' => $activo,
                'orden' => $orden,
                'id' => $id,
            ]);

            sa_json_out(true, ['message' => 'Servicio global actualizado correctamente.']);
        }

        $stmt = $pdo->prepare("
            INSERT INTO home_servicios_globales
                (nombre, descripcion, imagen_url, telefono, whatsapp, link_url, categoria, activo, orden)
            VALUES
                (:nombre, :descripcion, :imagen_url, :telefono, :whatsapp, :link_url, :categoria, :activo, :orden)
        ");
        $stmt->execute([
            'nombre' => $nombre,
            'descripcion' => ($descripcion !== '' ? $descripcion : null),
            'imagen_url' => ($imagenUrl !== '' ? $imagenUrl : null),
            'telefono' => ($telefono !== '' ? $telefono : null),
            'whatsapp' => ($whatsapp !== '' ? $whatsapp : null),
            'link_url' => ($linkUrl !== '' ? $linkUrl : null),
            'categoria' => ($categoria !== '' ? $categoria : 'servicio'),
            'activo' => $activo,
            'orden' => $orden,
        ]);

        sa_json_out(true, ['message' => 'Servicio global creado correctamente.']);
    }

    if ($action === 'delete_service') {
        sa_require_csrf();
        sa_ensure_global_services_table($pdo);

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            sa_json_out(false, ['error' => 'Servicio global inválido.'], 422);
        }

        $stmt = $pdo->prepare("DELETE FROM home_servicios_globales WHERE id = :id");
        $stmt->execute(['id' => $id]);
        sa_json_out(true, ['message' => 'Servicio global eliminado correctamente.']);
    }

    sa_json_out(false, ['error' => 'Acción no soportada.'], 400);
} catch (Throwable $e) {
    app_json_exception($e, 'No pudimos guardar la configuración.');
}
