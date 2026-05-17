-- Plantilla de apoyo para probar el listado de accesos_residentes.
-- Recomendación: primero consulta un auto real con tag_id y usa esos IDs.

-- 1) Ver autos disponibles para prueba
SELECT
  a.id AS auto_id,
  a.residencial_id,
  a.unidad_id,
  a.propietario_user_id AS residente_id,
  a.placas,
  a.tag_id
FROM autos a
ORDER BY a.id DESC
LIMIT 20;

-- 2) Ejemplo de ingreso
INSERT INTO accesos_residentes
(
  residencial_id,
  residente_id,
  unidad_id,
  auto_id,
  tag_id,
  lector_check_id,
  tipo_movimiento,
  fecha_hora,
  fuente
)
VALUES
(
  1,
  25,
  10,
  8,
  'TAG-0001',
  1001,
  'ingreso',
  NOW(),
  'lector_tag'
);

-- 3) Ejemplo de egreso
INSERT INTO accesos_residentes
(
  residencial_id,
  residente_id,
  unidad_id,
  auto_id,
  tag_id,
  lector_check_id,
  tipo_movimiento,
  fecha_hora,
  fuente
)
VALUES
(
  1,
  25,
  10,
  8,
  'TAG-0001',
  1002,
  'egreso',
  DATE_ADD(NOW(), INTERVAL 5 MINUTE),
  'lector_tag'
);

-- 4) Plantilla vacía para copiar/pegar
-- INSERT INTO accesos_residentes
-- (
--   residencial_id,
--   residente_id,
--   unidad_id,
--   auto_id,
--   tag_id,
--   lector_check_id,
--   tipo_movimiento,
--   fecha_hora,
--   fuente,
--   metadata_json
-- )
-- VALUES
-- (
--   0,
--   0,
--   NULL,
--   0,
--   'TAG-XXXX',
--   NULL,
--   'ingreso',
--   NOW(),
--   'lector_tag',
--   NULL
-- );
