<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Domain\Espacios\Espacio;
use App\Factories\EspacioFactory;
use PDO;

/**
 * Repositorio de espacios: encapsulates las consultas de lectura sobre la
 * tabla `espacios` y devuelve objetos del dominio.
 *
 * [INYECCION-DEPENDENCIAS]
 * El PDO se recibe por constructor y NUNCA se obtiene aquí mediante
 * Conexion::obtener(). Quien arma el objeto (las páginas public/*.php) llama
 * una sola vez a Conexion::obtener() y luego inyecta el PDO con
 * `new EspacioRepositorio($pdo)`. Así el repositorio depende solo de la
 * abstracción PDO y puede probarse con un PDO de prueba.
 *
 * [CRUD-READ]
 * Todas las consultas usan prepare()/execute() con placeholders named
 * (`:id`); nunca se concatena ninguna variable dentro del SQL, lo que
 * previene inyecciones SQL. Las PDOException se dejan propagar sin
 * capturar: Conexion ya configuró PDO::ERRMODE_EXCEPTION.
 *
 * [FABRICA]
 * El repositorio no decide qué subclase instanciar: delega ese trabajo en
 * EspacioFactory::desdeFila(), por lo que aquí no se usa instanceof,
 * switch ni match sobre el tipo de espacio.
 */
final class EspacioRepositorio
{
    /**
     * [ENCAPSULAMIENTO] Sentencias SQL fijas y sin interpolación de variables.
     */
    private const SQL_LISTAR = 'SELECT * FROM espacios ORDER BY id ASC';

    private const SQL_BUSCAR_POR_ID = 'SELECT * FROM espacios WHERE id = :id LIMIT 1';

    /**
     * [INYECCION-DEPENDENCIAS] El PDO se inyecta desde fuera.
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * [CRUD-READ] Lista todos los espacios ordenados por id.
     *
     * @return Espacio[] Espacios del dominio, en el mismo orden que la consulta.
     */
    public function listar(): array
    {
        $stmt = $this->pdo->prepare(self::SQL_LISTAR);
        $stmt->execute();

        /** @var Espacio[] $espacios */
        $espacios = [];

        while ($fila = $stmt->fetch()) {
            // [FABRICA] La fábrica decide qué subclase crear a partir de la fila.
            $espacios[] = EspacioFactory::desdeFila($fila);
        }

        return $espacios;
    }

    /**
     * [CRUD-READ] Busca un espacio por su id.
     *
     * @param int $id Identificador del espacio en la base de datos.
     *
     * @return Espacio|null El espacio encontrado o null si no existe.
     */
    public function buscarPorId(int $id): ?Espacio
    {
        $fila = $this->obtenerFilaPorId($id);

        if ($fila === null) {
            return null;
        }

        // [FABRICA] La fábrica decide qué subclase crear a partir de la fila.
        return EspacioFactory::desdeFila($fila);
    }

    /**
     * Obtiene la fila cruda (array asociativo) de un espacio por su id.
     *
     * Sirve para dos casos donde NO se necesita un objeto del dominio:
     *  1) Precargar formularios de edición, donde interesa leer también las
     *     columnas específicas del tipo (tipo_grama, tiene_proyector, etc.).
     *  2) Conocer el nombre de la imagen actual ANTES de reemplazarla o
     *     borrarla, para poder eliminar el archivo previo del disco.
     *
     * @param int $id Identificador del espacio en la base de datos.
     *
     * @return array<string, mixed>|null La fila encontrada o null si no existe.
     */
    public function obtenerFilaPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare(self::SQL_BUSCAR_POR_ID);
        $stmt->execute([':id' => $id]);

        $fila = $stmt->fetch();

        return $fila !== false ? $fila : null;
    }

    /**
     * [CRUD-CREATE] Inserta un nuevo espacio en la base de datos siguiendo Single Table Inheritance.
     *
     * @param array<string, mixed> $datos Datos del espacio a registrar.
     *
     * @return int ID autoincremental asignado por la base de datos.
     */
    public function insertar(array $datos): int
    {
        $sql = 'INSERT INTO espacios (
            tipo, nombre, tarifa_base, capacidad, imagen,
            tipo_grama, iluminacion_nocturna, tiene_computadora, tiene_proyector
        ) VALUES (
            :tipo, :nombre, :tarifa_base, :capacidad, :imagen,
            :tipo_grama, :iluminacion_nocturna, :tiene_computadora, :tiene_proyector
        )';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':tipo'                 => (string) ($datos['tipo'] ?? ''),
            ':nombre'               => (string) ($datos['nombre'] ?? ''),
            ':tarifa_base'          => (float) ($datos['tarifa_base'] ?? 0.0),
            ':capacidad'            => (int) ($datos['capacidad'] ?? 0),
            ':imagen'               => !empty($datos['imagen']) ? (string) $datos['imagen'] : null,
            ':tipo_grama'           => !empty($datos['tipo_grama']) ? (string) $datos['tipo_grama'] : null,
            ':iluminacion_nocturna' => isset($datos['iluminacion_nocturna']) && $datos['iluminacion_nocturna'] !== ''
                ? (int) (bool) $datos['iluminacion_nocturna']
                : null,
            ':tiene_computadora'    => isset($datos['tiene_computadora']) && $datos['tiene_computadora'] !== ''
                ? (int) (bool) $datos['tiene_computadora']
                : null,
            ':tiene_proyector'      => isset($datos['tiene_proyector']) && $datos['tiene_proyector'] !== ''
                ? (int) (bool) $datos['tiene_proyector']
                : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}