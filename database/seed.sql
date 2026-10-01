-- =============================================================================
-- Datos iniciales - Sistema de Reservas de Espacios
--
-- Ejecutar una sola vez sobre una base recién creada con schema.sql.
-- Los valores de tarifa_base y capacidad son coherentes con la Fase 1:
--   * Cancha              -> 120.00 / capacidad 10
--   * EscritorioIndividual->  75.00 / capacidad  1
--   * SalaReunion         -> 180.00 / capacidad  8
-- Las columnas que no aplican al tipo quedan en NULL; imagen en NULL
-- para todos los espacios.
-- =============================================================================

USE sistema_reservas;

-- -----------------------------------------------------------------------------
-- Canchas (tipo = 'cancha')
-- -----------------------------------------------------------------------------
INSERT INTO espacios
    (tipo, nombre, tarifa_base, capacidad, imagen, tipo_grama, iluminacion_nocturna, tiene_computadora, tiene_proyector)
VALUES
    ('cancha', 'Cancha Central', 120.00, 10, 'cancha_sintetica.png', 'Sintética', 1, NULL, NULL),
    ('cancha', 'Cancha Norte', 120.00, 10, 'cancha_sintetica.png', 'Natural', 0, NULL, NULL),
    ('cancha', 'Cancha Este', 120.00, 10, 'cancha_sintetica.png', 'Sintética', 1, NULL, NULL);

-- -----------------------------------------------------------------------------
-- Escritorios individuales (tipo = 'escritorio')
-- -----------------------------------------------------------------------------
INSERT INTO espacios
    (tipo, nombre, tarifa_base, capacidad, imagen, tipo_grama, iluminacion_nocturna, tiene_computadora, tiene_proyector)
VALUES
    ('escritorio', 'Escritorio 01', 75.00, 1, 'escritorio_individual.png', NULL, NULL, 1, NULL),
    ('escritorio', 'Escritorio 02', 75.00, 1, 'escritorio_individual.png', NULL, NULL, 0, NULL),
    ('escritorio', 'Escritorio 03', 75.00, 1, 'escritorio_individual.png', NULL, NULL, 1, NULL);

-- -----------------------------------------------------------------------------
-- Salas de reunión (tipo = 'sala')
-- -----------------------------------------------------------------------------
INSERT INTO espacios
    (tipo, nombre, tarifa_base, capacidad, imagen, tipo_grama, iluminacion_nocturna, tiene_computadora, tiene_proyector)
VALUES
    ('sala', 'Sala de Reuniones A', 180.00, 8, 'sala_ejecutiva.png', NULL, NULL, NULL, 1),
    ('sala', 'Sala de Reuniones B', 180.00, 8, 'sala_ejecutiva.png', NULL, NULL, NULL, 0),
    ('sala', 'Sala de Reuniones C', 180.00, 8, 'sala_ejecutiva.png', NULL, NULL, NULL, 1);