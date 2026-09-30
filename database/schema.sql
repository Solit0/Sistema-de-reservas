-- =============================================================================
-- Esquema de base de datos - Sistema de Reservas de Espacios
--
-- ESTRATEGIA: SINGLE TABLE INHERITANCE (Herencia de Tabla Única)
-- -----------------------------------------------------------------------------
-- Cada subclase de espacio (Cancha, EscritorioIndividual, SalaReunion) se
-- persiste en una única tabla `espacios`. La columna `tipo` actúa como
-- discriminador y las columnas específicas de cada subclase son NULL para
-- los registros de otros tipos:
--
--   * Cancha              -> tipo_grama, iluminacion_nocturna
--   * EscritorioIndividual-> tiene_computadora
--   * SalaReunion         -> tiene_proyector
--
-- NOTA: las restricciones CHECK requieren MySQL 8.0.16+ o MariaDB 10.2+.
-- =============================================================================

CREATE DATABASE IF NOT EXISTS sistema_reservas
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE sistema_reservas;

-- -----------------------------------------------------------------------------
-- Tabla espacios: hereda los atributos comunes de la clase abstracta Espacio
-- (nombre, capacidad, tarifa_base) y agrega las columnas específicas de cada
-- subclase concreta usando Single Table Inheritance.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS espacios (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    -- Discriminador: identifica la subclase concreta del espacio.
    tipo                  VARCHAR(20) NOT NULL COMMENT 'Tipo de espacio: cancha, escritorio, sala',
    nombre                VARCHAR(100) NOT NULL,
    tarifa_base           DECIMAL(10,2) NOT NULL,
    capacidad             INT NOT NULL,
    imagen                VARCHAR(255) NULL,
    -- Columnas específicas de la subclase Cancha
    tipo_grama            VARCHAR(50) NULL COMMENT 'Cancha: tipo de superficie (Sintética/Natural)',
    iluminacion_nocturna  TINYINT(1) NULL COMMENT 'Cancha: 1 si cuenta con iluminación nocturna, 0 en caso contrario',
    -- Columna específica de la subclase EscritorioIndividual
    tiene_computadora     TINYINT(1) NULL COMMENT 'Escritorio Individual: 1 si incluye computadora, 0 en caso contrario',
    -- Columna específica de la subclase SalaReunion
    tiene_proyector       TINYINT(1) NULL COMMENT 'Sala de Reunión: 1 si cuenta con proyector, 0 en caso contrario',

    CONSTRAINT chk_tarifa_positiva CHECK (tarifa_base > 0),
    CONSTRAINT chk_capacidad_positiva CHECK (capacidad > 0)
) ENGINE = InnoDB;

-- -----------------------------------------------------------------------------
-- Tabla reservas: una reserva concreta sobre un espacio.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reservas (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    espacio_id  INT NOT NULL,
    cliente     VARCHAR(100) NOT NULL,
    fecha       DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin    TIME NOT NULL,
    monto_total DECIMAL(10,2) NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_reserva_espacio
        FOREIGN KEY (espacio_id) REFERENCES espacios(id) ON DELETE CASCADE,

    CONSTRAINT chk_horario_valido CHECK (hora_fin > hora_inicio)
) ENGINE = InnoDB;