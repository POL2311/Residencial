<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

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
        $config = sa_fetch_config_general($pdo);

        $nombreSistema = sa_clean_str($_POST['nombre_sistema'] ?? 'Sistema Residencial', 150);
        $empresa = sa_clean_str($_POST['empresa'] ?? '', 150);
        $emailSoporte = sa_clean_str($_POST['email_soporte'] ?? '', 150);
        $logoUrl = trim((string)($_POST['logo_url'] ?? ''));
        $colorPrimario = sa_clean_str($_POST['color_primario'] ?? '', 20);
        $colorSecundario = sa_clean_str($_POST['color_secundario'] ?? '', 20);

        if ($nombreSistema === '') {
            $nombreSistema = 'Sistema Residencial';
        }

        if ($emailSoporte !== '' && !filter_var($emailSoporte, FILTER_VALIDATE_EMAIL)) {
            sa_json_out(false, ['error' => 'El email de soporte no tiene un formato válido.'], 422);
        }

        $stmt = $pdo->prepare("
            UPDATE config_general
            SET nombre_sistema = :nombre_sistema,
                empresa = :empresa,
                email_soporte = :email_soporte,
                logo_url = :logo_url,
                color_primario = :color_primario,
                color_secundario = :color_secundario
            WHERE id = :id
        ");
        $stmt->execute([
            'nombre_sistema' => $nombreSistema,
            'empresa' => ($empresa !== '' ? $empresa : null),
            'email_soporte' => ($emailSoporte !== '' ? $emailSoporte : null),
            'logo_url' => ($logoUrl !== '' ? $logoUrl : null),
            'color_primario' => ($colorPrimario !== '' ? $colorPrimario : null),
            'color_secundario' => ($colorSecundario !== '' ? $colorSecundario : null),
            'id' => $config['id'],
        ]);

        sa_json_out(true, ['message' => 'Configuración general actualizada correctamente.']);
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
    sa_json_out(false, ['error' => 'Error: ' . $e->getMessage()], 500);
}
