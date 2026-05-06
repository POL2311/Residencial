-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost
-- Tiempo de generación: 02-05-2026 a las 03:57:05
-- Versión del servidor: 10.4.28-MariaDB
-- Versión de PHP: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `residencial_app4`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `accesos_guardia`
--

CREATE TABLE `accesos_guardia` (
  `id` int(11) NOT NULL,
  `visita_id` int(11) NOT NULL,
  `guardia_id` int(11) NOT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `tipo_evento` enum('entrada','salida','verificacion') NOT NULL DEFAULT 'entrada',
  `resultado` enum('permitido','denegado') NOT NULL DEFAULT 'permitido',
  `observaciones` varchar(255) DEFAULT NULL,
  `origen_acceso` enum('visita','residente_directo') NOT NULL DEFAULT 'visita',
  `residente_id` int(11) DEFAULT NULL,
  `unidad_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `accesos_guardia`
--

INSERT INTO `accesos_guardia` (`id`, `visita_id`, `guardia_id`, `fecha_hora`, `tipo_evento`, `resultado`, `observaciones`, `origen_acceso`, `residente_id`, `unidad_id`) VALUES
(1, 2, 3, '2025-12-02 20:44:39', 'entrada', 'permitido', '3921G6', 'visita', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `archivos_operativos`
--

CREATE TABLE `archivos_operativos` (
  `id` int(11) NOT NULL,
  `residencial_id` int(11) NOT NULL,
  `entidad_tipo` varchar(50) NOT NULL,
  `entidad_id` int(11) NOT NULL,
  `subtipo` varchar(50) DEFAULT NULL,
  `storage_disk` varchar(40) NOT NULL DEFAULT 'local_public',
  `storage_path` varchar(255) NOT NULL,
  `public_url` varchar(255) NOT NULL,
  `mime_type` varchar(80) NOT NULL,
  `size_bytes` int(11) NOT NULL DEFAULT 0,
  `width` int(11) DEFAULT NULL,
  `height` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `areas_operativas`
--

CREATE TABLE `areas_operativas` (
  `id` int(11) NOT NULL,
  `residencial_id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `codigo` varchar(50) DEFAULT NULL,
  `tipo` varchar(50) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `autos`
--

CREATE TABLE `autos` (
  `id` int(11) NOT NULL,
  `propietario_user_id` int(11) NOT NULL,
  `residencial_id` int(11) DEFAULT NULL,
  `unidad_id` int(11) DEFAULT NULL,
  `placas` varchar(20) NOT NULL,
  `modelo` varchar(100) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `notas` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `autos`
--

INSERT INTO `autos` (`id`, `propietario_user_id`, `residencial_id`, `unidad_id`, `placas`, `modelo`, `color`, `activo`, `created_at`, `updated_at`, `notas`) VALUES
(1, 4, 1, 1, 'ABC0123', 'rojo', 'Blanco', 1, '2026-04-03 00:11:29', '2026-04-03 00:11:29', ''),
(2, 4, 1, 1, '01040214', 'versa', 'Blanco', 1, '2026-04-03 00:20:01', '2026-04-03 00:20:01', ''),
(5, 4, 1, 1, 'ABC01233', 'rojo', 'Blanco1', 1, '2026-04-03 00:33:55', '2026-04-06 03:06:54', 'bien'),
(7, 10, 1, 2, 'ABC0123333', 'rojo', 'Blanco', 1, '2026-04-06 03:07:15', '2026-04-06 03:07:15', 'oo'),
(8, 3, 1, 2, '444', '4444', '444', 1, '2026-04-06 03:07:26', '2026-04-06 03:07:26', '444'),
(9, 3, 1, 1, '5125125', '41241', '124124', 1, '2026-04-06 03:07:34', '2026-04-06 03:07:34', '4124');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bitacora_operativa`
--

CREATE TABLE `bitacora_operativa` (
  `id` int(11) NOT NULL,
  `residencial_id` int(11) NOT NULL,
  `guardia_id` int(11) DEFAULT NULL,
  `tipo_origen` varchar(50) NOT NULL,
  `origen_id` int(11) DEFAULT NULL,
  `tipo_evento` varchar(50) NOT NULL,
  `resultado` varchar(30) NOT NULL DEFAULT 'permitido',
  `persona_recurrente_id` int(11) DEFAULT NULL,
  `visitante_rapido_id` int(11) DEFAULT NULL,
  `permiso_material_id` int(11) DEFAULT NULL,
  `area_id` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `metadata_json` longtext DEFAULT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `catalogo_materiales`
--

CREATE TABLE `catalogo_materiales` (
  `id` int(11) NOT NULL,
  `residencial_id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `categoria` varchar(80) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `comunicados_residenciales`
--

CREATE TABLE `comunicados_residenciales` (
  `id` int(11) NOT NULL,
  `residencial_id` int(11) NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `mensaje` text NOT NULL,
  `tipo` enum('general','mantenimiento','seguridad','pagos') NOT NULL DEFAULT 'general',
  `prioridad` enum('baja','media','alta') NOT NULL DEFAULT 'media',
  `fecha_publicacion` date NOT NULL,
  `fecha_expiracion` date DEFAULT NULL,
  `visible_para_residentes` tinyint(1) NOT NULL DEFAULT 1,
  `estado` enum('publicado','borrador','archivado') NOT NULL DEFAULT 'publicado',
  `creado_por` int(11) NOT NULL,
  `actualizado_por` int(11) DEFAULT NULL,
  `creado_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reglamentos_residenciales` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `comunicados_residenciales`
--

INSERT INTO `comunicados_residenciales` (`id`, `residencial_id`, `titulo`, `mensaje`, `tipo`, `prioridad`, `fecha_publicacion`, `fecha_expiracion`, `visible_para_residentes`, `estado`, `creado_por`, `actualizado_por`, `creado_at`, `updated_at`, `reglamentos_residenciales`) VALUES
(1, 1, 'llaves perdidas en casa', '123', 'seguridad', 'media', '2026-04-15', '2026-04-16', 1, 'archivado', 2, NULL, '2026-04-05 00:18:08', '2026-04-05 00:18:15', ''),
(2, 1, 'llaves perdidas en casa', '1234567', 'general', 'media', '2026-04-02', '2026-04-30', 1, 'publicado', 2, 2, '2026-04-05 00:22:39', '2026-04-20 09:04:00', '');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `config_general`
--

CREATE TABLE `config_general` (
  `id` int(11) NOT NULL,
  `nombre_sistema` varchar(150) NOT NULL DEFAULT 'Sistema Residencial',
  `empresa` varchar(150) DEFAULT NULL,
  `email_soporte` varchar(150) DEFAULT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `color_primario` varchar(7) DEFAULT NULL,
  `color_secundario` varchar(7) DEFAULT NULL,
  `smtp_host` varchar(190) DEFAULT NULL,
  `smtp_port` int(11) DEFAULT NULL,
  `smtp_username` varchar(190) DEFAULT NULL,
  `smtp_password` varchar(255) DEFAULT NULL,
  `smtp_encryption` enum('none','tls','ssl') NOT NULL DEFAULT 'tls',
  `smtp_from_email` varchar(190) DEFAULT NULL,
  `smtp_from_name` varchar(190) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `config_general`
--

INSERT INTO `config_general` (`id`, `nombre_sistema`, `empresa`, `email_soporte`, `logo_url`, `color_primario`, `color_secundario`, `smtp_host`, `smtp_port`, `smtp_username`, `smtp_password`, `smtp_encryption`, `smtp_from_email`, `smtp_from_name`, `created_at`, `updated_at`) VALUES
(1, 'Sistema Residencial', 'Tu Empresa', 'soporte@tuempresa.com', NULL, NULL, NULL, 'smtp.gmail.com', 587, 'alatorrekevalat@gmail.com', 'dkhlryyjmtfynfhj', 'tls', 'alatorrekevalat@gmail.com', 'Sistema residencial', '2025-11-29 23:39:44', '2026-05-01 19:33:19');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `config_seguridad`
--

CREATE TABLE `config_seguridad` (
  `id` int(11) NOT NULL,
  `max_intentos_login` int(11) NOT NULL DEFAULT 5,
  `minutos_bloqueo_login` int(11) NOT NULL DEFAULT 15,
  `tiempo_sesion_minutos` int(11) NOT NULL DEFAULT 60,
  `registrar_logs_acceso` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `config_seguridad`
--

INSERT INTO `config_seguridad` (`id`, `max_intentos_login`, `minutos_bloqueo_login`, `tiempo_sesion_minutos`, `registrar_logs_acceso`, `created_at`, `updated_at`) VALUES
(1, 5, 15, 60, 1, '2025-11-29 23:38:24', '2025-11-29 23:38:24');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `contactos_emergencia`
--

CREATE TABLE `contactos_emergencia` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `telefono` varchar(40) NOT NULL,
  `relacion` varchar(80) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `guardias_turnos`
--

CREATE TABLE `guardias_turnos` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `residencial_id` int(11) NOT NULL,
  `nombre_turno` varchar(50) NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `dias_semana` varchar(50) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `guardias_turnos`
--

INSERT INTO `guardias_turnos` (`id`, `user_id`, `residencial_id`, `nombre_turno`, `hora_inicio`, `hora_fin`, `dias_semana`, `activo`, `created_at`, `updated_at`) VALUES
(1, 3, 1, 'Vespertino', '10:00:00', '14:00:00', 'LUN,MAR,MIE,JUE,VIE', 1, '2026-04-03 21:16:41', '2026-04-03 21:17:08');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `home_banners_residenciales`
--

CREATE TABLE `home_banners_residenciales` (
  `id` int(11) NOT NULL,
  `residencial_id` int(11) NOT NULL,
  `titulo` varchar(180) NOT NULL,
  `subtitulo` varchar(255) DEFAULT NULL,
  `imagen_url` varchar(500) DEFAULT NULL,
  `categoria` varchar(80) NOT NULL DEFAULT 'general',
  `link_url` varchar(500) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `orden` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `home_banners_residenciales`
--

INSERT INTO `home_banners_residenciales` (`id`, `residencial_id`, `titulo`, `subtitulo`, `imagen_url`, `categoria`, `link_url`, `activo`, `orden`, `created_at`, `updated_at`) VALUES
(1, 1, 'Trabajos de reparación y mejora', 'Se realizarán mejoras en áreas comunes.', 'https://images.unsplash.com/photo-1504307651254-35680f356dfd?q=80&w=1600&auto=format&fit=crop', 'mantenimiento', NULL, 1, 1, '2026-04-05 18:34:16', '2026-04-05 18:34:16'),
(2, 1, 'Nuevo protocolo de acceso', 'Recuerda registrar tus visitas con anticipación.', 'https://images.unsplash.com/photo-1517048676732-d65bc937f952?q=80&w=1600&auto=format&fit=crop', 'seguridad', NULL, 1, 2, '2026-04-05 18:34:16', '2026-04-05 18:34:16'),
(3, 1, 'Recordatorio de cuotas', 'Consulta tus pagos pendientes y mantén tu cuenta al corriente.', 'https://images.unsplash.com/photo-1554224155-6726b3ff858f?q=80&w=1600&auto=format&fit=crop', 'pagos', NULL, 1, 3, '2026-04-05 18:34:16', '2026-04-05 18:34:16');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `home_servicios_globales`
--

CREATE TABLE `home_servicios_globales` (
  `id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `imagen_url` varchar(500) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `whatsapp` varchar(30) DEFAULT NULL,
  `link_url` varchar(500) DEFAULT NULL,
  `categoria` varchar(80) NOT NULL DEFAULT 'servicio',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `orden` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `home_servicios_residenciales`
--

CREATE TABLE `home_servicios_residenciales` (
  `id` int(11) NOT NULL,
  `residencial_id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `imagen_url` varchar(500) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `whatsapp` varchar(30) DEFAULT NULL,
  `link_url` varchar(500) DEFAULT NULL,
  `categoria` varchar(80) NOT NULL DEFAULT 'servicio',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `orden` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `home_servicios_residenciales`
--

INSERT INTO `home_servicios_residenciales` (`id`, `residencial_id`, `nombre`, `descripcion`, `imagen_url`, `telefono`, `whatsapp`, `link_url`, `categoria`, `activo`, `orden`, `created_at`, `updated_at`) VALUES
(1, 1, 'Cerrajero', 'Apertura, cambio de chapa y duplicado de llaves.', 'https://images.unsplash.com/photo-1581578731548-c64695cc6952?q=80&w=1200&auto=format&fit=crop', '5551112233', '5551112233', NULL, 'servicio', 1, 1, '2026-04-05 18:34:53', '2026-04-05 18:34:53'),
(2, 1, 'Plomería', 'Reparación de fugas, tuberías y mantenimiento.', 'https://images.unsplash.com/photo-1621905252507-b35492cc74b4?q=80&w=1200&auto=format&fit=crop', '5552223344', '5552223344', NULL, 'servicio', 1, 2, '2026-04-05 18:34:53', '2026-04-05 18:34:53'),
(3, 1, 'Electricista', 'Instalaciones, revisiones y mantenimiento eléctrico.', 'https://images.unsplash.com/photo-1621905251918-48416bd8575a?q=80&w=1200&auto=format&fit=crop', '5553334455', '5553334455', NULL, 'servicio', 1, 3, '2026-04-05 18:34:53', '2026-04-05 18:34:53');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `incidencias`
--

CREATE TABLE `incidencias` (
  `id` int(11) NOT NULL,
  `residencial_id` int(11) NOT NULL,
  `unidad_id` int(11) DEFAULT NULL,
  `residente_id` int(11) DEFAULT NULL,
  `guardia_id` int(11) DEFAULT NULL,
  `area_id` int(11) DEFAULT NULL,
  `persona_recurrente_id` int(11) DEFAULT NULL,
  `visitante_rapido_id` int(11) DEFAULT NULL,
  `permiso_material_id` int(11) DEFAULT NULL,
  `origen_tipo` varchar(50) DEFAULT NULL,
  `tipo` varchar(50) NOT NULL DEFAULT 'seguridad',
  `titulo` varchar(150) NOT NULL,
  `descripcion` text NOT NULL,
  `prioridad` enum('baja','media','alta') NOT NULL DEFAULT 'media',
  `estado` enum('abierta','en_proceso','cerrada') NOT NULL DEFAULT 'abierta',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `incidencias`
--

INSERT INTO `incidencias` (`id`, `residencial_id`, `unidad_id`, `residente_id`, `guardia_id`, `area_id`, `persona_recurrente_id`, `visitante_rapido_id`, `permiso_material_id`, `origen_tipo`, `tipo`, `titulo`, `descripcion`, `prioridad`, `estado`, `created_at`, `updated_at`) VALUES
(2, 1, 1, 4, 3, NULL, NULL, NULL, NULL, NULL, 'seguridad', 'OLa', '123', 'alta', 'en_proceso', '2026-04-07 21:59:21', '2026-04-07 23:39:38');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos`
--

CREATE TABLE `pagos` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `residencial_id` int(11) DEFAULT NULL,
  `unidad_id` int(11) DEFAULT NULL,
  `monto` decimal(10,2) NOT NULL,
  `fecha` date NOT NULL,
  `metodo` varchar(50) DEFAULT NULL,
  `concepto` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pagos`
--

INSERT INTO `pagos` (`id`, `user_id`, `residencial_id`, `unidad_id`, `monto`, `fecha`, `metodo`, `concepto`, `activo`, `created_at`, `updated_at`) VALUES
(1, 4, 1, 1, 850.00, '2026-04-02', 'efectivo', 'Mantenimiento enero', 1, '2026-04-03 00:11:15', '2026-04-03 00:11:15'),
(2, 4, 1, 1, 900.00, '2026-04-10', 'efectivo', 'otro', 1, '2026-04-03 00:19:53', '2026-04-03 00:19:53'),
(3, 4, 1, 1, 4214.00, '2026-04-08', 'efectivo', '12414', 1, '2026-04-03 00:34:01', '2026-04-03 00:34:01'),
(4, 8, 1, 1, 4124.00, '2026-04-08', 'efectivo', 'otro', 1, '2026-04-03 02:16:21', '2026-04-03 02:16:21');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `paqueteria`
--

CREATE TABLE `paqueteria` (
  `id` int(11) NOT NULL,
  `residencial_id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `residente_id` int(11) DEFAULT NULL,
  `guardia_id` int(11) NOT NULL,
  `empresa` varchar(100) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `codigo_rastreo` varchar(100) DEFAULT NULL,
  `estado` enum('registrado','entregado','devuelto') NOT NULL DEFAULT 'registrado',
  `notas` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `paqueteria`
--

INSERT INTO `paqueteria` (`id`, `residencial_id`, `unidad_id`, `residente_id`, `guardia_id`, `empresa`, `descripcion`, `codigo_rastreo`, `estado`, `notas`, `created_at`, `updated_at`) VALUES
(1, 1, 1, NULL, 3, 'amazon', 'ogoag', '102401042', 'entregado', NULL, '2026-04-05 19:23:15', '2026-04-07 23:26:14'),
(2, 1, 1, 4, 3, 'Amazon', 'Amazon', '10203040124', 'entregado', 'qkrkqwkrqwr', '2026-04-07 23:26:39', '2026-04-22 15:14:31');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `password_reset_codes`
--

CREATE TABLE `password_reset_codes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `codigo_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `attempt_count` int(11) NOT NULL DEFAULT 0,
  `last_attempt_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `password_reset_codes`
--

INSERT INTO `password_reset_codes` (`id`, `user_id`, `email`, `codigo_hash`, `expires_at`, `used_at`, `created_at`, `attempt_count`, `last_attempt_at`) VALUES
(1, 4, 'residente@gmail.com', '$2y$10$P.8/V3ZYMeHmpDM8EIyWd..xOOAXMetA5Ecf9NizxciDDfj2oe2.G', '2026-05-01 19:18:10', NULL, '2026-05-01 19:03:10', 0, NULL),
(2, 2, 'residencial@gmail.com', '$2y$10$aix/V9C8IJsiio9Gdu9MTOAgxEQ2ZMV4LNM0YpZyNP2rCnESO0a1q', '2026-05-01 19:48:27', NULL, '2026-05-01 19:33:27', 0, NULL),
(3, 15, 'alatorrekevalat@gmail.com', '$2y$10$z1GmTWr2x931Ee4iTWjz0OJiKw7x.GGZcLS8OEVAZKqJY2kP27ct.', '2026-05-01 19:57:58', '2026-05-01 19:46:10', '2026-05-01 19:42:58', 0, NULL),
(4, 15, 'alatorrekevalat@gmail.com', '$2y$10$qOAJXm76scEYlNvDT2fiUu3MWWHwTOhqZGWO1kIk.WQg6TQOJFQ8a', '2026-05-01 20:06:15', '2026-05-01 19:51:23', '2026-05-01 19:51:15', 0, NULL),
(5, 15, 'alatorrekevalat@gmail.com', '$2y$10$/5o/ZubVG7MN4ppcSPR9YuzG8I7zmq36fB9CTrP8eVP7QIaZCcF4m', '2026-05-01 20:06:23', '2026-05-01 19:52:16', '2026-05-01 19:51:23', 0, '2026-05-01 19:52:16');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permisos_materiales`
--

CREATE TABLE `permisos_materiales` (
  `id` int(11) NOT NULL,
  `residencial_id` int(11) NOT NULL,
  `area_id` int(11) DEFAULT NULL,
  `responsable_user_id` int(11) NOT NULL,
  `tipo_movimiento` varchar(20) NOT NULL DEFAULT 'entrada',
  `qr_token` varchar(80) NOT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'pendiente',
  `solicitado_por_user_id` int(11) DEFAULT NULL,
  `aprobado_por_user_id` int(11) DEFAULT NULL,
  `aprobado_at` datetime DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `fecha_desde` datetime DEFAULT NULL,
  `fecha_hasta` datetime DEFAULT NULL,
  `ejecutado_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permisos_materiales_items`
--

CREATE TABLE `permisos_materiales_items` (
  `id` int(11) NOT NULL,
  `permiso_id` int(11) NOT NULL,
  `material_id` int(11) DEFAULT NULL,
  `material_nombre` varchar(150) NOT NULL,
  `cantidad_texto` varchar(120) NOT NULL,
  `agregar_a_catalogo` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `personas_recurrentes`
--

CREATE TABLE `personas_recurrentes` (
  `id` int(11) NOT NULL,
  `residencial_id` int(11) NOT NULL,
  `area_id` int(11) DEFAULT NULL,
  `nombre` varchar(150) NOT NULL,
  `foto_url` varchar(255) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `empresa` varchar(120) DEFAULT NULL,
  `puesto` varchar(120) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `qr_token` varchar(80) NOT NULL,
  `pin_hash` varchar(255) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `esta_dentro` tinyint(1) NOT NULL DEFAULT 0,
  `ultima_entrada_at` datetime DEFAULT NULL,
  `ultima_salida_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `planes`
--

CREATE TABLE `planes` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `periodo` enum('mensual','anual') NOT NULL DEFAULT 'mensual',
  `precio_mensual` decimal(10,2) NOT NULL DEFAULT 0.00,
  `precio_anual` decimal(10,2) NOT NULL DEFAULT 0.00,
  `limites` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`limites`)),
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `planes`
--

INSERT INTO `planes` (`id`, `nombre`, `codigo`, `descripcion`, `periodo`, `precio_mensual`, `precio_anual`, `limites`, `activo`, `created_at`, `updated_at`) VALUES
(1, 'Básico', 'basic', 'Ideal para residenciales pequeños.', 'mensual', 499.00, 4990.00, '{\"max_casas\": 100, \"max_guardias\": 5, \"modulos_incluidos\": [\"accesos\", \"paqueteria\"]}', 1, '2025-11-28 22:01:36', '2025-11-28 22:01:36'),
(2, 'Pro', 'pro', 'Incluye rondines e incidencias avanzadas.', 'mensual', 999.00, 9990.00, '{\"max_casas\": 300, \"max_guardias\": 15, \"modulos_incluidos\": [\"accesos\", \"paqueteria\", \"rondines\", \"incidencias\"]}', 1, '2025-11-28 22:01:36', '2025-11-28 22:01:36'),
(3, 'Enterprise', 'enterprise', 'Para grupos residenciales grandes y personalización.', 'mensual', 1999.00, 19990.00, '{\"max_casas\": 1000, \"max_guardias\": 50, \"modulos_incluidos\": [\"accesos\", \"paqueteria\", \"rondines\", \"incidencias\", \"analytics\"]}', 1, '2025-11-28 22:01:36', '2025-11-28 22:01:36');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reglamentos_residenciales`
--

CREATE TABLE `reglamentos_residenciales` (
  `id` int(11) NOT NULL,
  `residencial_id` int(11) NOT NULL,
  `titulo` varchar(180) NOT NULL,
  `contenido` mediumtext NOT NULL,
  `version_label` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `reglamentos_residenciales`
--

INSERT INTO `reglamentos_residenciales` (`id`, `residencial_id`, `titulo`, `contenido`, `version_label`, `created_at`, `updated_at`) VALUES
(1, 1, 'Reglamento general residencial', '1. Disposiciones Generales\r\n\r\n* Objeto: Definir la finalidad del reglamento (mantener el orden y la armonía).\r\n* Alcance: A quiénes aplica (residentes, visitantes, personal de servicio).\r\n* Identificación: Obligación de portar credencial o llave de acceso.\r\n\r\n2. Derechos de los Residentes\r\n\r\n* Uso de instalaciones: Acceso a habitaciones y áreas comunes (cocina, gym, estudio).\r\n* Privacidad: Respeto al espacio personal y pertenencias.\r\n* Asistencia: Derecho a recibir mantenimiento y servicios básicos (agua, luz, wifi).\r\n\r\n3. Obligaciones y Deberes\r\n\r\n* Pagos: Cumplir con las fechas de mensualidad o cuotas de mantenimiento.\r\n* Limpieza: Mantener el orden en áreas propias y comunes.\r\n* Horarios de Silencio: Restricción de ruidos fuertes (ej. de 22:00 a 08:00 hrs).\r\n* Conservación: Cuidar el mobiliario y reportar daños de inmediato.\r\n\r\n4. Normas de Convivencia y Visitas\r\n\r\n* Registro de visitas: Obligación de anotar entrada y salida de invitados.\r\n* Pernocta: Prohibición de que visitas se queden a dormir sin autorización previa.\r\n* Conducta: Trato respetuoso hacia los demás residentes y el personal.\r\n\r\n5. Prohibiciones Estrictas\r\n\r\n* Sustancias: Prohibido el consumo de tabaco, alcohol o drogas dentro de las instalaciones.\r\n* Mascotas: Restricción de animales (a menos que se especifique lo contrario).\r\n* Subarriendo: Prohibido alquilar la habitación a terceros.\r\n* Alteraciones: No realizar perforaciones, cambios de pintura o modificaciones eléctricas.\r\n\r\n6. Seguridad y Emergencias\r\n\r\n* Acceso: Prohibido prestar llaves o códigos de seguridad a personas externas.\r\n* Prevención de incendios: Restricción de velas, estufas eléctricas no autorizadas o materiales inflamables.\r\n* Protocolo: Conocimiento de salidas de emergencia y puntos de reunión.\r\n\r\n7. Régimen Disciplinario (Sanciones)\r\n\r\n* Amonestación verbal: Para faltas leves (ruido ocasional, desorden menor).\r\n* Amonestación escrita: Reincidencia o faltas moderadas.\r\n* Multas económicas: Por daños materiales o faltas administrativas.\r\n* Expulsión definitiva: Por faltas graves (violencia, robo, consumo de drogas).', '1.0', '2026-04-05 11:53:44', '2026-04-05 11:53:44');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `residenciales`
--

CREATE TABLE `residenciales` (
  `id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `tipo` enum('fraccionamiento','torre','mixto','privado','otro') NOT NULL DEFAULT 'fraccionamiento',
  `modo_operacion` varchar(20) NOT NULL DEFAULT 'residencial',
  `max_casas` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `max_guardias` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `pais` varchar(80) NOT NULL,
  `estado` varchar(100) NOT NULL,
  `ciudad` varchar(100) NOT NULL,
  `colonia` varchar(150) NOT NULL,
  `calle` varchar(150) NOT NULL,
  `numero_exterior` varchar(20) NOT NULL,
  `numero_interior` varchar(20) DEFAULT NULL,
  `codigo_postal` varchar(10) NOT NULL,
  `nombre_contacto` varchar(150) NOT NULL,
  `telefono_contacto` varchar(30) NOT NULL,
  `email_contacto` varchar(150) NOT NULL,
  `plan_id` int(11) DEFAULT NULL,
  `fecha_inicio_plan` date DEFAULT NULL,
  `fecha_fin_plan` date DEFAULT NULL,
  `estatus_plan` enum('prueba','activo','suspendido','cancelado') NOT NULL DEFAULT 'activo',
  `zona_horaria` varchar(50) NOT NULL DEFAULT 'America/Mexico_City',
  `permite_qr` tinyint(1) NOT NULL DEFAULT 1,
  `permite_trabajadores_recurrentes` tinyint(1) NOT NULL DEFAULT 1,
  `requiere_placa_vehiculo` tinyint(1) NOT NULL DEFAULT 0,
  `requiere_identificacion_visita` tinyint(1) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `residenciales`
--

INSERT INTO `residenciales` (`id`, `nombre`, `codigo`, `tipo`, `modo_operacion`, `max_casas`, `max_guardias`, `pais`, `estado`, `ciudad`, `colonia`, `calle`, `numero_exterior`, `numero_interior`, `codigo_postal`, `nombre_contacto`, `telefono_contacto`, `email_contacto`, `plan_id`, `fecha_inicio_plan`, `fecha_fin_plan`, `estatus_plan`, `zona_horaria`, `permite_qr`, `permite_trabajadores_recurrentes`, `requiere_placa_vehiculo`, `requiere_identificacion_visita`, `activo`, `created_at`, `updated_at`) VALUES
(1, 'san pablo garza', '10', 'fraccionamiento', 'residencial', 100, 100, 'México', 'mexico', 'mexico', 'san pedro', 'san pedro', '10', '0', '10542', 'jamas lo hemos visto', 'contacto', 'alatorrekevalat@gmail.com', NULL, NULL, NULL, 'activo', 'America/Mexico_City', 1, 1, 0, 0, 1, '2025-11-28 22:08:53', '2026-04-29 09:32:34');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `residenciales_servicio_config`
--

CREATE TABLE `residenciales_servicio_config` (
  `id` int(11) NOT NULL,
  `residencial_id` int(11) NOT NULL,
  `preset_servicio` varchar(30) NOT NULL DEFAULT 'residencial',
  `habilita_admin_operativo` tinyint(1) NOT NULL DEFAULT 1,
  `habilita_guardia` tinyint(1) NOT NULL DEFAULT 1,
  `habilita_residente` tinyint(1) NOT NULL DEFAULT 1,
  `habilita_unidades` tinyint(1) NOT NULL DEFAULT 1,
  `habilita_residentes_catalogo` tinyint(1) NOT NULL DEFAULT 1,
  `habilita_guardias_catalogo` tinyint(1) NOT NULL DEFAULT 1,
  `habilita_autos` tinyint(1) NOT NULL DEFAULT 1,
  `habilita_visitas_residente` tinyint(1) NOT NULL DEFAULT 1,
  `habilita_paqueteria` tinyint(1) NOT NULL DEFAULT 1,
  `habilita_pagos` tinyint(1) NOT NULL DEFAULT 1,
  `habilita_comunicados` tinyint(1) NOT NULL DEFAULT 1,
  `habilita_servicios_directorio` tinyint(1) NOT NULL DEFAULT 1,
  `habilita_control_acceso` tinyint(1) NOT NULL DEFAULT 1,
  `habilita_incidencias` tinyint(1) NOT NULL DEFAULT 1,
  `habilita_personal_recurrente` tinyint(1) NOT NULL DEFAULT 0,
  `habilita_visitantes_rapidos` tinyint(1) NOT NULL DEFAULT 0,
  `habilita_materiales` tinyint(1) NOT NULL DEFAULT 0,
  `habilita_solicitudes_pendientes` tinyint(1) NOT NULL DEFAULT 0,
  `habilita_bitacora_operativa` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `residenciales_servicio_config`
--

INSERT INTO `residenciales_servicio_config` (`id`, `residencial_id`, `preset_servicio`, `habilita_admin_operativo`, `habilita_guardia`, `habilita_residente`, `habilita_unidades`, `habilita_residentes_catalogo`, `habilita_guardias_catalogo`, `habilita_autos`, `habilita_visitas_residente`, `habilita_paqueteria`, `habilita_pagos`, `habilita_comunicados`, `habilita_servicios_directorio`, `habilita_control_acceso`, `habilita_incidencias`, `habilita_personal_recurrente`, `habilita_visitantes_rapidos`, `habilita_materiales`, `habilita_solicitudes_pendientes`, `habilita_bitacora_operativa`, `created_at`, `updated_at`) VALUES
(1, 1, 'residencial', 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-04-30 00:24:09', '2026-04-30 00:24:09');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `residentes_unidades`
--

CREATE TABLE `residentes_unidades` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `es_titular` tinyint(1) NOT NULL DEFAULT 1,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `acceso_baneado_manual` tinyint(1) NOT NULL DEFAULT 0,
  `acceso_baneo_motivo` varchar(255) DEFAULT NULL,
  `acceso_baneado_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `residentes_unidades`
--

INSERT INTO `residentes_unidades` (`id`, `user_id`, `unidad_id`, `es_titular`, `activo`, `created_at`, `acceso_baneado_manual`, `acceso_baneo_motivo`, `acceso_baneado_at`) VALUES
(1, 4, 1, 0, 1, '2025-12-02 20:36:19', 0, NULL, NULL),
(5, 8, 1, 0, 1, '2026-04-02 19:22:55', 0, NULL, NULL),
(6, 9, 1, 0, 1, '2026-04-02 19:45:04', 0, NULL, NULL),
(7, 10, 1, 0, 1, '2026-04-02 19:45:17', 0, NULL, NULL),
(8, 11, 1, 0, 1, '2026-04-02 19:45:29', 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_usuario`
--

CREATE TABLE `tipos_usuario` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tipos_usuario`
--

INSERT INTO `tipos_usuario` (`id`, `nombre`, `descripcion`) VALUES
(1, 'super_admin', 'Administra toda la plataforma y todos los residenciales'),
(2, 'admin_supervisor', 'Supervisor con acceso de lectura ampliado'),
(3, 'admin_residencial', 'Administrador interno de un residencial'),
(4, 'guardia', 'Guardia de seguridad'),
(5, 'residente', 'Residente del fraccionamiento');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `unidades`
--

CREATE TABLE `unidades` (
  `id` int(11) NOT NULL,
  `residencial_id` int(11) NOT NULL,
  `tipo` enum('casa','departamento','local','otro') NOT NULL DEFAULT 'casa',
  `clave` varchar(50) NOT NULL,
  `calle` varchar(150) DEFAULT NULL,
  `numero_exterior` varchar(20) DEFAULT NULL,
  `numero_interior` varchar(20) DEFAULT NULL,
  `torre` varchar(50) DEFAULT NULL,
  `nivel` varchar(20) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `unidades`
--

INSERT INTO `unidades` (`id`, `residencial_id`, `tipo`, `clave`, `calle`, `numero_exterior`, `numero_interior`, `torre`, `nivel`, `notas`, `activo`, `created_at`, `updated_at`) VALUES
(1, 1, 'departamento', 'C-100', 'san pedro 1', '10', '11', 'Torre san juan', '105', NULL, 1, '2025-12-02 20:35:56', '2025-12-02 20:35:56'),
(2, 1, 'casa', 'C-200', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-04-05 20:58:28', '2026-04-05 20:58:28');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `tipo_usuario_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `guardia_en_servicio` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `tipo_usuario_id`, `name`, `email`, `telefono`, `password_hash`, `is_active`, `guardia_en_servicio`, `created_at`) VALUES
(1, 1, 'Kevin Super', 'superadmin@example.com', NULL, '123456', 1, 0, '2025-11-28 18:08:43'),
(2, 3, 'juan perez', 'residencial@gmail.com', NULL, '$2y$10$.JI/jVK7gAFeg5oLngXwv.NM6Bp1RuwRXOhge0fLaTDgyb5Lht3tW', 1, 0, '2025-11-29 23:52:25'),
(3, 4, 'Guardia3', 'guardia2@gmail.com', '5646950032', '$2y$10$U/1mjFF2Msw3wn.RHVO4bOn9EcJchZtq651Gh47r6.KF.UTe/WQU2', 1, 1, '2025-12-02 19:25:27'),
(4, 1, 'Ana guzman', 'residente@gmail.com', '564695003211', '$2y$10$7vL9O.4mIXG4oFI7bd71megYGPXownPvC3KDmfQho3Ep07z04jhpW', 1, 0, '2025-12-02 20:36:19'),
(5, 5, 'Juan gArcia', 'issac_issac18@live.com', '5646950032', '123456', 1, 0, '2026-04-02 18:20:11'),
(6, 5, 'Ana guzman', '41243@gmail.com', '12413', '$2y$10$rKy1JcnLfyQdF4dBB0o5F.lkpTd1baR7tZKBhQ/QssB9o1BzXHCbu', 1, 0, '2026-04-02 18:34:33'),
(7, 5, 'otro', 'otororqo@gmail.com', '421043021', '$2y$10$IHGwW2YN0vB8ziY6eg3H6uBWmQY1GatDrVvM0PudP3yu2V1D5iVl2', 1, 0, '2026-04-02 19:22:10'),
(8, 5, 'otro', 'otro@gmail.com', '4102401204', '$2y$10$LmGWzZq5/Q9e1xc/heYMhedAL11f5PbUzBZ5zDOIg5lFKp2C3laia', 1, 0, '2026-04-02 19:22:55'),
(9, 5, 'Juan gArcia', '12412444@gmail.com', '56469500322', '$2y$10$Z2yzNnjQFVLzIxhftQbzZOcdMAEEADfB3b5Mb3Ve1Wfew6ijOBnti', 1, 0, '2026-04-02 19:45:04'),
(10, 5, 'Juan gArcia', 'resident4444@gmail.com', '56469500321', '$2y$10$Vrc/6GyrFbTd1BgcubhK5ef9Z1BHPvdlouqPMfFXoBpSPCaaBeY0m', 1, 0, '2026-04-02 19:45:17'),
(11, 5, 'Juan gArcia', 'issac_issac184@live.com', '14241412414', '123456', 1, 0, '2026-04-02 19:45:29'),
(12, 5, 'otro1', 'otro1@gmail.com', NULL, '$2y$10$JoTDLPOPaApcRBOcb9SfM.S.lrTMPpDHyxWOvG65njr2hkooCctoq', 1, 0, '2026-04-07 23:47:45'),
(13, 4, '12355', 'issac_issac184214@live.com', '5646950032', '$2y$10$u87w3X0LGfxk02Or4Dihc.DDwoUrqQf9CdAUZzAt0QHBD.0KkocjS', 0, 0, '2026-04-22 00:25:26'),
(14, 1, 'kevin', 'superadmin1@gmail.com', '5646950032', '123456', 1, 0, '2026-04-28 16:44:12'),
(15, 1, 'kevin', 'alatorrekevalat@gmail.com', '5646950032', '$2y$10$eetEY3vltixyMYFnZjr2i.lobr80vjmugiEt7TjGOgHgn56UkDG.2', 1, 0, '2026-05-01 19:42:42');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios_residenciales`
--

CREATE TABLE `usuarios_residenciales` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `residencial_id` int(11) NOT NULL,
  `es_principal` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios_residenciales`
--

INSERT INTO `usuarios_residenciales` (`id`, `user_id`, `residencial_id`, `es_principal`, `created_at`) VALUES
(1, 1, 1, 1, '2025-11-29 23:40:41'),
(2, 2, 1, 1, '2025-11-29 23:57:06'),
(3, 3, 1, 1, '2025-12-02 19:25:27'),
(4, 4, 1, 1, '2025-12-02 20:36:19'),
(8, 8, 1, 1, '2026-04-02 19:22:55'),
(9, 9, 1, 1, '2026-04-02 19:45:04'),
(11, 10, 1, 1, '2026-04-02 19:45:17'),
(12, 11, 1, 1, '2026-04-02 19:45:29'),
(13, 13, 1, 1, '2026-04-22 00:25:26');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `visitantes_rapidos`
--

CREATE TABLE `visitantes_rapidos` (
  `id` int(11) NOT NULL,
  `residencial_id` int(11) NOT NULL,
  `responsable_user_id` int(11) NOT NULL,
  `area_id` int(11) DEFAULT NULL,
  `nombre_visitante` varchar(150) NOT NULL,
  `empresa` varchar(120) DEFAULT NULL,
  `placa_vehiculo` varchar(20) DEFAULT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `qr_token` varchar(80) NOT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'activo',
  `notas_admin` text DEFAULT NULL,
  `fecha_desde` datetime DEFAULT NULL,
  `fecha_hasta` datetime DEFAULT NULL,
  `esta_dentro` tinyint(1) NOT NULL DEFAULT 0,
  `ultimo_evento_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `visitas`
--

CREATE TABLE `visitas` (
  `id` int(11) NOT NULL,
  `residencial_id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `residente_id` int(11) NOT NULL,
  `tipo` enum('visita','servicio','trabajador_recurrente') NOT NULL DEFAULT 'visita',
  `nombre_visitante` varchar(150) NOT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `placa_vehiculo` varchar(20) DEFAULT NULL,
  `fecha_desde` date NOT NULL,
  `fecha_hasta` date NOT NULL,
  `hora_desde` time DEFAULT NULL,
  `hora_hasta` time DEFAULT NULL,
  `uso_unico` tinyint(1) NOT NULL DEFAULT 1,
  `codigo_acceso` varchar(64) NOT NULL,
  `estado` enum('pendiente','usado','vencido','cancelado') NOT NULL DEFAULT 'pendiente',
  `notas` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `visitas`
--

INSERT INTO `visitas` (`id`, `residencial_id`, `unidad_id`, `residente_id`, `tipo`, `nombre_visitante`, `motivo`, `placa_vehiculo`, `fecha_desde`, `fecha_hasta`, `hora_desde`, `hora_hasta`, `uso_unico`, `codigo_acceso`, `estado`, `notas`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 4, 'visita', 'Estela gomez', 'familiar', '3921G5', '2025-12-02', '2025-12-03', '20:41:00', '20:42:00', 1, '900543', 'pendiente', NULL, '2025-12-02 20:37:11', '2026-04-23 21:45:41'),
(2, 1, 1, 4, 'visita', 'Estela gomez', 'familiar', '3921G5', '2025-12-02', '2025-12-03', '20:41:00', '20:42:00', 1, '266971', 'usado', NULL, '2025-12-02 20:42:13', '2026-04-23 21:45:41');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `accesos_guardia`
--
ALTER TABLE `accesos_guardia`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ag_visita` (`visita_id`),
  ADD KEY `fk_ag_guardia` (`guardia_id`);

--
-- Indices de la tabla `archivos_operativos`
--
ALTER TABLE `archivos_operativos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_archivos_operativos_residencial` (`residencial_id`),
  ADD KEY `idx_archivos_operativos_entidad` (`entidad_tipo`,`entidad_id`);

--
-- Indices de la tabla `areas_operativas`
--
ALTER TABLE `areas_operativas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_areas_operativas_residencial` (`residencial_id`),
  ADD KEY `idx_areas_operativas_activo` (`activo`);

--
-- Indices de la tabla `autos`
--
ALTER TABLE `autos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_placas` (`placas`),
  ADD KEY `idx_user` (`propietario_user_id`),
  ADD KEY `idx_residencial` (`residencial_id`),
  ADD KEY `idx_unidad` (`unidad_id`);

--
-- Indices de la tabla `bitacora_operativa`
--
ALTER TABLE `bitacora_operativa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_bitacora_operativa_residencial` (`residencial_id`),
  ADD KEY `idx_bitacora_operativa_guardia` (`guardia_id`),
  ADD KEY `idx_bitacora_operativa_fecha` (`fecha_hora`),
  ADD KEY `idx_bitacora_operativa_area` (`area_id`),
  ADD KEY `idx_bitacora_operativa_tipo` (`tipo_origen`,`tipo_evento`),
  ADD KEY `fk_bitacora_operativa_persona` (`persona_recurrente_id`),
  ADD KEY `fk_bitacora_operativa_visitante` (`visitante_rapido_id`),
  ADD KEY `fk_bitacora_operativa_permiso` (`permiso_material_id`);

--
-- Indices de la tabla `catalogo_materiales`
--
ALTER TABLE `catalogo_materiales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_catalogo_materiales_residencial` (`residencial_id`),
  ADD KEY `idx_catalogo_materiales_activo` (`activo`);

--
-- Indices de la tabla `comunicados_residenciales`
--
ALTER TABLE `comunicados_residenciales`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `config_general`
--
ALTER TABLE `config_general`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `config_seguridad`
--
ALTER TABLE `config_seguridad`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `contactos_emergencia`
--
ALTER TABLE `contactos_emergencia`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ce_user` (`user_id`);

--
-- Indices de la tabla `guardias_turnos`
--
ALTER TABLE `guardias_turnos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_guardias_turnos_user` (`user_id`),
  ADD KEY `fk_guardias_turnos_residencial` (`residencial_id`);

--
-- Indices de la tabla `home_banners_residenciales`
--
ALTER TABLE `home_banners_residenciales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_home_banners_residencial` (`residencial_id`),
  ADD KEY `idx_home_banners_activo` (`activo`),
  ADD KEY `idx_home_banners_orden` (`orden`);

--
-- Indices de la tabla `home_servicios_globales`
--
ALTER TABLE `home_servicios_globales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_home_servicios_globales_activo` (`activo`),
  ADD KEY `idx_home_servicios_globales_orden` (`orden`);

--
-- Indices de la tabla `home_servicios_residenciales`
--
ALTER TABLE `home_servicios_residenciales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_home_servicios_residencial` (`residencial_id`),
  ADD KEY `idx_home_servicios_activo` (`activo`),
  ADD KEY `idx_home_servicios_orden` (`orden`);

--
-- Indices de la tabla `incidencias`
--
ALTER TABLE `incidencias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_inc_residencial` (`residencial_id`),
  ADD KEY `fk_inc_unidad` (`unidad_id`),
  ADD KEY `fk_inc_residente` (`residente_id`),
  ADD KEY `fk_incidencias_guardia` (`guardia_id`),
  ADD KEY `idx_incidencias_area` (`area_id`),
  ADD KEY `idx_incidencias_persona` (`persona_recurrente_id`),
  ADD KEY `idx_incidencias_visitante` (`visitante_rapido_id`),
  ADD KEY `idx_incidencias_permiso` (`permiso_material_id`);

--
-- Indices de la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_residencial` (`residencial_id`),
  ADD KEY `idx_unidad` (`unidad_id`),
  ADD KEY `idx_fecha` (`fecha`);

--
-- Indices de la tabla `paqueteria`
--
ALTER TABLE `paqueteria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `residencial_id` (`residencial_id`),
  ADD KEY `unidad_id` (`unidad_id`),
  ADD KEY `residente_id` (`residente_id`),
  ADD KEY `guardia_id` (`guardia_id`);

--
-- Indices de la tabla `password_reset_codes`
--
ALTER TABLE `password_reset_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_password_reset_codes_email` (`email`),
  ADD KEY `idx_password_reset_codes_user` (`user_id`),
  ADD KEY `idx_password_reset_codes_expires` (`expires_at`);

--
-- Indices de la tabla `permisos_materiales`
--
ALTER TABLE `permisos_materiales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_permisos_materiales_qr_token` (`qr_token`),
  ADD KEY `idx_permisos_materiales_residencial` (`residencial_id`),
  ADD KEY `idx_permisos_materiales_area` (`area_id`),
  ADD KEY `idx_permisos_materiales_responsable` (`responsable_user_id`),
  ADD KEY `idx_permisos_materiales_estado` (`estado`),
  ADD KEY `fk_permisos_materiales_solicitado` (`solicitado_por_user_id`),
  ADD KEY `fk_permisos_materiales_aprobado` (`aprobado_por_user_id`);

--
-- Indices de la tabla `permisos_materiales_items`
--
ALTER TABLE `permisos_materiales_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_permisos_materiales_items_permiso` (`permiso_id`),
  ADD KEY `idx_permisos_materiales_items_material` (`material_id`);

--
-- Indices de la tabla `personas_recurrentes`
--
ALTER TABLE `personas_recurrentes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_personas_recurrentes_qr_token` (`qr_token`),
  ADD KEY `idx_personas_recurrentes_residencial` (`residencial_id`),
  ADD KEY `idx_personas_recurrentes_area` (`area_id`),
  ADD KEY `idx_personas_recurrentes_activo` (`activo`),
  ADD KEY `idx_personas_recurrentes_dentro` (`esta_dentro`);

--
-- Indices de la tabla `planes`
--
ALTER TABLE `planes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`);

--
-- Indices de la tabla `reglamentos_residenciales`
--
ALTER TABLE `reglamentos_residenciales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reglamento_residencial` (`residencial_id`);

--
-- Indices de la tabla `residenciales`
--
ALTER TABLE `residenciales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `fk_residenciales_plan` (`plan_id`);

--
-- Indices de la tabla `residenciales_servicio_config`
--
ALTER TABLE `residenciales_servicio_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_residenciales_servicio_config_rid` (`residencial_id`);

--
-- Indices de la tabla `residentes_unidades`
--
ALTER TABLE `residentes_unidades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ru_user` (`user_id`),
  ADD KEY `fk_ru_unidad` (`unidad_id`);

--
-- Indices de la tabla `tipos_usuario`
--
ALTER TABLE `tipos_usuario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `unidades`
--
ALTER TABLE `unidades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_unidades_residencial` (`residencial_id`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `tipo_usuario_id` (`tipo_usuario_id`);

--
-- Indices de la tabla `usuarios_residenciales`
--
ALTER TABLE `usuarios_residenciales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ur_user` (`user_id`),
  ADD KEY `fk_ur_residencial` (`residencial_id`);

--
-- Indices de la tabla `visitantes_rapidos`
--
ALTER TABLE `visitantes_rapidos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_visitantes_rapidos_qr_token` (`qr_token`),
  ADD KEY `idx_visitantes_rapidos_residencial` (`residencial_id`),
  ADD KEY `idx_visitantes_rapidos_responsable` (`responsable_user_id`),
  ADD KEY `idx_visitantes_rapidos_area` (`area_id`),
  ADD KEY `idx_visitantes_rapidos_estado` (`estado`);

--
-- Indices de la tabla `visitas`
--
ALTER TABLE `visitas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo_acceso` (`codigo_acceso`),
  ADD KEY `fk_visitas_residencial` (`residencial_id`),
  ADD KEY `fk_visitas_unidad` (`unidad_id`),
  ADD KEY `fk_visitas_residente` (`residente_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `accesos_guardia`
--
ALTER TABLE `accesos_guardia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `archivos_operativos`
--
ALTER TABLE `archivos_operativos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `areas_operativas`
--
ALTER TABLE `areas_operativas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `autos`
--
ALTER TABLE `autos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `bitacora_operativa`
--
ALTER TABLE `bitacora_operativa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `catalogo_materiales`
--
ALTER TABLE `catalogo_materiales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `comunicados_residenciales`
--
ALTER TABLE `comunicados_residenciales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `config_general`
--
ALTER TABLE `config_general`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `config_seguridad`
--
ALTER TABLE `config_seguridad`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `contactos_emergencia`
--
ALTER TABLE `contactos_emergencia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `guardias_turnos`
--
ALTER TABLE `guardias_turnos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `home_banners_residenciales`
--
ALTER TABLE `home_banners_residenciales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `home_servicios_globales`
--
ALTER TABLE `home_servicios_globales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `home_servicios_residenciales`
--
ALTER TABLE `home_servicios_residenciales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `incidencias`
--
ALTER TABLE `incidencias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `pagos`
--
ALTER TABLE `pagos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `paqueteria`
--
ALTER TABLE `paqueteria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `password_reset_codes`
--
ALTER TABLE `password_reset_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `permisos_materiales`
--
ALTER TABLE `permisos_materiales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `permisos_materiales_items`
--
ALTER TABLE `permisos_materiales_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `personas_recurrentes`
--
ALTER TABLE `personas_recurrentes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `planes`
--
ALTER TABLE `planes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `reglamentos_residenciales`
--
ALTER TABLE `reglamentos_residenciales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `residenciales`
--
ALTER TABLE `residenciales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `residenciales_servicio_config`
--
ALTER TABLE `residenciales_servicio_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `residentes_unidades`
--
ALTER TABLE `residentes_unidades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `tipos_usuario`
--
ALTER TABLE `tipos_usuario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `unidades`
--
ALTER TABLE `unidades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `usuarios_residenciales`
--
ALTER TABLE `usuarios_residenciales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `visitantes_rapidos`
--
ALTER TABLE `visitantes_rapidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `visitas`
--
ALTER TABLE `visitas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `accesos_guardia`
--
ALTER TABLE `accesos_guardia`
  ADD CONSTRAINT `fk_ag_guardia` FOREIGN KEY (`guardia_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_ag_visita` FOREIGN KEY (`visita_id`) REFERENCES `visitas` (`id`);

--
-- Filtros para la tabla `archivos_operativos`
--
ALTER TABLE `archivos_operativos`
  ADD CONSTRAINT `fk_archivos_operativos_residencial` FOREIGN KEY (`residencial_id`) REFERENCES `residenciales` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `areas_operativas`
--
ALTER TABLE `areas_operativas`
  ADD CONSTRAINT `fk_areas_operativas_residencial` FOREIGN KEY (`residencial_id`) REFERENCES `residenciales` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `bitacora_operativa`
--
ALTER TABLE `bitacora_operativa`
  ADD CONSTRAINT `fk_bitacora_operativa_area` FOREIGN KEY (`area_id`) REFERENCES `areas_operativas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_bitacora_operativa_guardia` FOREIGN KEY (`guardia_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_bitacora_operativa_permiso` FOREIGN KEY (`permiso_material_id`) REFERENCES `permisos_materiales` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_bitacora_operativa_persona` FOREIGN KEY (`persona_recurrente_id`) REFERENCES `personas_recurrentes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_bitacora_operativa_residencial` FOREIGN KEY (`residencial_id`) REFERENCES `residenciales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bitacora_operativa_visitante` FOREIGN KEY (`visitante_rapido_id`) REFERENCES `visitantes_rapidos` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `catalogo_materiales`
--
ALTER TABLE `catalogo_materiales`
  ADD CONSTRAINT `fk_catalogo_materiales_residencial` FOREIGN KEY (`residencial_id`) REFERENCES `residenciales` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `contactos_emergencia`
--
ALTER TABLE `contactos_emergencia`
  ADD CONSTRAINT `fk_ce_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `guardias_turnos`
--
ALTER TABLE `guardias_turnos`
  ADD CONSTRAINT `fk_guardias_turnos_residencial` FOREIGN KEY (`residencial_id`) REFERENCES `residenciales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_guardias_turnos_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `home_banners_residenciales`
--
ALTER TABLE `home_banners_residenciales`
  ADD CONSTRAINT `fk_home_banners_residencial` FOREIGN KEY (`residencial_id`) REFERENCES `residenciales` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `home_servicios_residenciales`
--
ALTER TABLE `home_servicios_residenciales`
  ADD CONSTRAINT `fk_home_servicios_residencial` FOREIGN KEY (`residencial_id`) REFERENCES `residenciales` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `incidencias`
--
ALTER TABLE `incidencias`
  ADD CONSTRAINT `fk_inc_residencial` FOREIGN KEY (`residencial_id`) REFERENCES `residenciales` (`id`),
  ADD CONSTRAINT `fk_inc_residente` FOREIGN KEY (`residente_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_inc_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`),
  ADD CONSTRAINT `fk_incidencias_area_operativa` FOREIGN KEY (`area_id`) REFERENCES `areas_operativas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_incidencias_guardia` FOREIGN KEY (`guardia_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_incidencias_permiso_operativa` FOREIGN KEY (`permiso_material_id`) REFERENCES `permisos_materiales` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_incidencias_persona_operativa` FOREIGN KEY (`persona_recurrente_id`) REFERENCES `personas_recurrentes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_incidencias_visitante_operativa` FOREIGN KEY (`visitante_rapido_id`) REFERENCES `visitantes_rapidos` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `paqueteria`
--
ALTER TABLE `paqueteria`
  ADD CONSTRAINT `paqueteria_ibfk_1` FOREIGN KEY (`residencial_id`) REFERENCES `residenciales` (`id`),
  ADD CONSTRAINT `paqueteria_ibfk_2` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`),
  ADD CONSTRAINT `paqueteria_ibfk_3` FOREIGN KEY (`residente_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `paqueteria_ibfk_4` FOREIGN KEY (`guardia_id`) REFERENCES `users` (`id`);

--
-- Filtros para la tabla `password_reset_codes`
--
ALTER TABLE `password_reset_codes`
  ADD CONSTRAINT `fk_password_reset_codes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `permisos_materiales`
--
ALTER TABLE `permisos_materiales`
  ADD CONSTRAINT `fk_permisos_materiales_aprobado` FOREIGN KEY (`aprobado_por_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_permisos_materiales_area` FOREIGN KEY (`area_id`) REFERENCES `areas_operativas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_permisos_materiales_residencial` FOREIGN KEY (`residencial_id`) REFERENCES `residenciales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_permisos_materiales_responsable` FOREIGN KEY (`responsable_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_permisos_materiales_solicitado` FOREIGN KEY (`solicitado_por_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `permisos_materiales_items`
--
ALTER TABLE `permisos_materiales_items`
  ADD CONSTRAINT `fk_permisos_materiales_items_material` FOREIGN KEY (`material_id`) REFERENCES `catalogo_materiales` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_permisos_materiales_items_permiso` FOREIGN KEY (`permiso_id`) REFERENCES `permisos_materiales` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `personas_recurrentes`
--
ALTER TABLE `personas_recurrentes`
  ADD CONSTRAINT `fk_personas_recurrentes_area` FOREIGN KEY (`area_id`) REFERENCES `areas_operativas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_personas_recurrentes_residencial` FOREIGN KEY (`residencial_id`) REFERENCES `residenciales` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `reglamentos_residenciales`
--
ALTER TABLE `reglamentos_residenciales`
  ADD CONSTRAINT `fk_reglamento_residencial` FOREIGN KEY (`residencial_id`) REFERENCES `residenciales` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `residenciales`
--
ALTER TABLE `residenciales`
  ADD CONSTRAINT `fk_residenciales_plan` FOREIGN KEY (`plan_id`) REFERENCES `planes` (`id`);

--
-- Filtros para la tabla `residenciales_servicio_config`
--
ALTER TABLE `residenciales_servicio_config`
  ADD CONSTRAINT `fk_residenciales_servicio_config_residencial` FOREIGN KEY (`residencial_id`) REFERENCES `residenciales` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `residentes_unidades`
--
ALTER TABLE `residentes_unidades`
  ADD CONSTRAINT `fk_ru_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`),
  ADD CONSTRAINT `fk_ru_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Filtros para la tabla `unidades`
--
ALTER TABLE `unidades`
  ADD CONSTRAINT `fk_unidades_residencial` FOREIGN KEY (`residencial_id`) REFERENCES `residenciales` (`id`);

--
-- Filtros para la tabla `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`tipo_usuario_id`) REFERENCES `tipos_usuario` (`id`);

--
-- Filtros para la tabla `usuarios_residenciales`
--
ALTER TABLE `usuarios_residenciales`
  ADD CONSTRAINT `fk_ur_residencial` FOREIGN KEY (`residencial_id`) REFERENCES `residenciales` (`id`),
  ADD CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Filtros para la tabla `visitantes_rapidos`
--
ALTER TABLE `visitantes_rapidos`
  ADD CONSTRAINT `fk_visitantes_rapidos_area` FOREIGN KEY (`area_id`) REFERENCES `areas_operativas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_visitantes_rapidos_residencial` FOREIGN KEY (`residencial_id`) REFERENCES `residenciales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_visitantes_rapidos_responsable` FOREIGN KEY (`responsable_user_id`) REFERENCES `users` (`id`);

--
-- Filtros para la tabla `visitas`
--
ALTER TABLE `visitas`
  ADD CONSTRAINT `fk_visitas_residencial` FOREIGN KEY (`residencial_id`) REFERENCES `residenciales` (`id`),
  ADD CONSTRAINT `fk_visitas_residente` FOREIGN KEY (`residente_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_visitas_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
