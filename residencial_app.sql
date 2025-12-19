-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost
-- Tiempo de generación: 03-12-2025 a las 20:52:12
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
-- Base de datos: `residencial_app`
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
  `observaciones` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `accesos_guardia`
--

INSERT INTO `accesos_guardia` (`id`, `visita_id`, `guardia_id`, `fecha_hora`, `tipo_evento`, `resultado`, `observaciones`) VALUES
(1, 2, 3, '2025-12-02 20:44:39', 'entrada', 'permitido', '3921G6');

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
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `config_general`
--

INSERT INTO `config_general` (`id`, `nombre_sistema`, `empresa`, `email_soporte`, `logo_url`, `color_primario`, `color_secundario`, `created_at`, `updated_at`) VALUES
(1, 'Sistema Residencial', 'Tu Empresa', 'soporte@tuempresa.com', NULL, NULL, NULL, '2025-11-29 23:39:44', '2025-11-29 23:39:44');

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
-- Estructura de tabla para la tabla `incidencias`
--

CREATE TABLE `incidencias` (
  `id` int(11) NOT NULL,
  `residencial_id` int(11) NOT NULL,
  `unidad_id` int(11) NOT NULL,
  `residente_id` int(11) NOT NULL,
  `tipo` enum('seguridad','servicio','vecino','infraestructura','otro') NOT NULL DEFAULT 'otro',
  `titulo` varchar(150) NOT NULL,
  `descripcion` text NOT NULL,
  `prioridad` enum('baja','media','alta') NOT NULL DEFAULT 'media',
  `estado` enum('abierta','en_proceso','cerrada') NOT NULL DEFAULT 'abierta',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Estructura de tabla para la tabla `residenciales`
--

CREATE TABLE `residenciales` (
  `id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `tipo` enum('fraccionamiento','torre','mixto','privado','otro') NOT NULL DEFAULT 'fraccionamiento',
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

INSERT INTO `residenciales` (`id`, `nombre`, `codigo`, `tipo`, `max_casas`, `max_guardias`, `pais`, `estado`, `ciudad`, `colonia`, `calle`, `numero_exterior`, `numero_interior`, `codigo_postal`, `nombre_contacto`, `telefono_contacto`, `email_contacto`, `plan_id`, `fecha_inicio_plan`, `fecha_fin_plan`, `estatus_plan`, `zona_horaria`, `permite_qr`, `permite_trabajadores_recurrentes`, `requiere_placa_vehiculo`, `requiere_identificacion_visita`, `activo`, `created_at`, `updated_at`) VALUES
(1, 'san pablo garza', '10', 'fraccionamiento', 100, 100, 'México', 'mexico', 'mexico', 'san pedro', 'san pedro', '10', '0', '10542', 'jamas lo hemos visto', 'contacto', 'alatorrekevalat@gmail.com', NULL, NULL, NULL, 'activo', 'America/Mexico_City', 1, 1, 0, 0, 1, '2025-11-28 22:08:53', '2025-11-28 22:08:53');

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
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `residentes_unidades`
--

INSERT INTO `residentes_unidades` (`id`, `user_id`, `unidad_id`, `es_titular`, `activo`, `created_at`) VALUES
(1, 4, 1, 1, 1, '2025-12-02 20:36:19');

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
(1, 1, 'departamento', 'C-100', 'san pedro 1', '10', '11', 'Torre san juan', '105', NULL, 1, '2025-12-02 20:35:56', '2025-12-02 20:35:56');

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
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `tipo_usuario_id`, `name`, `email`, `telefono`, `password_hash`, `is_active`, `created_at`) VALUES
(1, 1, 'Kevin Super', 'superadmin@example.com', NULL, '$2y$10$O68qZw61ruSGCJxsU1fZSehxL2hFW175EMReLGAlJK9BFLNMfSQUe', 1, '2025-11-28 18:08:43'),
(2, 3, 'juan perez', 'residencial@gmail.com', NULL, '$2y$10$.JI/jVK7gAFeg5oLngXwv.NM6Bp1RuwRXOhge0fLaTDgyb5Lht3tW', 1, '2025-11-29 23:52:25'),
(3, 4, 'Guardia1', 'Guardia1@gmail.com', NULL, '$2y$10$U/1mjFF2Msw3wn.RHVO4bOn9EcJchZtq651Gh47r6.KF.UTe/WQU2', 1, '2025-12-02 19:25:27'),
(4, 5, 'Ana guzman', 'residente@gmail.com', NULL, '$2y$10$43m2dsXt2KF5AmdKGVJwNuyQAML89EFfYCgXsxhxJUDAVmEKKC7J6', 1, '2025-12-02 20:36:19');

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
(4, 4, 1, 1, '2025-12-02 20:36:19');

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
(1, 1, 1, 4, 'visita', 'Estela gomez', 'familiar', '3921G5', '2025-12-02', '2025-12-03', '20:41:00', '20:42:00', 1, 'd3bc71f6d9', 'pendiente', NULL, '2025-12-02 20:37:11', '2025-12-02 20:37:11'),
(2, 1, 1, 4, 'visita', 'Estela gomez', 'familiar', '3921G5', '2025-12-02', '2025-12-03', '20:41:00', '20:42:00', 1, 'bc8018529f', 'usado', NULL, '2025-12-02 20:42:13', '2025-12-02 20:44:39');

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
-- Indices de la tabla `incidencias`
--
ALTER TABLE `incidencias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_inc_residencial` (`residencial_id`),
  ADD KEY `fk_inc_unidad` (`unidad_id`),
  ADD KEY `fk_inc_residente` (`residente_id`);

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
-- Indices de la tabla `planes`
--
ALTER TABLE `planes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`);

--
-- Indices de la tabla `residenciales`
--
ALTER TABLE `residenciales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `fk_residenciales_plan` (`plan_id`);

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
-- AUTO_INCREMENT de la tabla `incidencias`
--
ALTER TABLE `incidencias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `paqueteria`
--
ALTER TABLE `paqueteria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `planes`
--
ALTER TABLE `planes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `residenciales`
--
ALTER TABLE `residenciales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `residentes_unidades`
--
ALTER TABLE `residentes_unidades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `tipos_usuario`
--
ALTER TABLE `tipos_usuario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `unidades`
--
ALTER TABLE `unidades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `usuarios_residenciales`
--
ALTER TABLE `usuarios_residenciales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
-- Filtros para la tabla `incidencias`
--
ALTER TABLE `incidencias`
  ADD CONSTRAINT `fk_inc_residencial` FOREIGN KEY (`residencial_id`) REFERENCES `residenciales` (`id`),
  ADD CONSTRAINT `fk_inc_residente` FOREIGN KEY (`residente_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_inc_unidad` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`);

--
-- Filtros para la tabla `paqueteria`
--
ALTER TABLE `paqueteria`
  ADD CONSTRAINT `paqueteria_ibfk_1` FOREIGN KEY (`residencial_id`) REFERENCES `residenciales` (`id`),
  ADD CONSTRAINT `paqueteria_ibfk_2` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`),
  ADD CONSTRAINT `paqueteria_ibfk_3` FOREIGN KEY (`residente_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `paqueteria_ibfk_4` FOREIGN KEY (`guardia_id`) REFERENCES `users` (`id`);

--
-- Filtros para la tabla `residenciales`
--
ALTER TABLE `residenciales`
  ADD CONSTRAINT `fk_residenciales_plan` FOREIGN KEY (`plan_id`) REFERENCES `planes` (`id`);

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
