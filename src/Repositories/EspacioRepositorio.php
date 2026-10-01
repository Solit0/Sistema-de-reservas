<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Domain\Espacios\Espacio;
use App\Factories\EspacioFactory;
use InvalidArgumentException;
use PDO;

/**
 * Repositorio de espacios: encapsula las consultas de lectura y escritura
 * sobre la tabla `espacios` y traduce las filas a objetos del dominio.
 *
 * [INYECCION-DEPENDENCIAS]
 * El PDO se recibe por constructor y NUNCA se obtiene aquí mediante
 * Conexion::obtener(). Quien arma el objeto (las páginas public/*.php) llama
 * una sola vez a Conexion::obtener() y luego inyecta el PDO con
 * `new EspacioRepositorio($pdo)`. Así el repositorio depende solo de la
 * abstracción PDO y puede probarse con un PDO de prueba.
 *
 * [CRUD-READ]
 * listar(), buscarPorId() y obtenerFilaPorId() devuelven los datos ya
 * traducidos a objetos del dominio (o la fila cruda, cuando el llamador la
 * necesita tal cual, por ejemplo para leer el nombre de la imagen).
 *
 * [CRUD-CREATE]
 * crear() inserta un espacio siguiendo Single Table Inheritance y devuelve
 * el id autoincremental asignado por la base de datos. insertar() se
 * conserva como alias en desuso por compatibilidad.
 *
 * [CRUD-UPDATE]
 * actualizar() reemplaza todas las columnas del espacio existente.
 *
 * [CRUD-DELETE]
 * eliminar() borra el espacio; por el ON DELETE CASCADE del esquema, sus
 * reservas se eliminan también de forma automática.
 *
 * [SEGURIDAD]
 * Todas las consultas usan prepare()/execute() con placeholders named
 * (`:id`, `:tipo`, `:nombre`, ...); nunca se concatena ninguna variable
 * dentro del SQL, lo que previene inyecciones SQL. Además crear() y
 * actualizar() validan que el `tipo` recibido sea uno de los soportados por
 * EspacioFactory, de modo que nunca se persista un tipo que la fábrica no
 * sepa reconstruir al leer. Las PDOException se dejan propagar sin
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

    private const SQL_INSERTAR = 'INSERT INTO espacios (
            tipo, nombre, tarifa_base, capacidad, imagen,
            tipo_grama, iluminacion_nocturna, tiene_computadora, tiene_proyector
        ) VALUES (
            :tipo, :nombre, :tarifa_base, :capacidad, :imagen,
            :tipo_grama, :iluminacion_nocturna, :tiene_computadora, :tiene_proyector
        )';

    private const SQL_ACTUALIZAR = 'UPDATE espacios SET
            tipo = :tipo,
            nombre = :nombre,
            tarifa_base = :tarifa_base,
            capacidad = :capacidad,
            imagen = :imagen,
            tipo_grama = :tipo_grama,
            iluminacion_nocturna = :iluminacion_nocturna,
            tiene_computadora = :tiene_computadora,
            tiene_proyector = :tiene_proyector
        WHERE id = :id';

    private const SQL_ELIMINAR = 'DELETE FROM espacios WHERE id = :id';

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
     * [CRUD-CREATE] Inserta un nuevo espacio siguiendo Single Table Inheritance.
     *
     * Persiste las columnas comunes (tipo, nombre, tarifa_base, capacidad,
     * imagen) y las columnas específicas del tipo (tipo_grama,
     * iluminacion_nocturna, tiene_computadora, tiene_proyector), que quedan
     * en NULL cuando no aplican.
     *
     * [SEGURIDAD] El tipo se valida contra EspacioFactory::tiposSoportados()
     * antes de tocar la base de datos, para no persistir un tipo que la
     * fábrica no sabría reconstruir al leer.
     *
     * @param array<string, mixed> $datos Datos del espacio a registrar.
     *
     * @return int ID autoincremental asignado por la base de datos.
     *
     * @throws InvalidArgumentException Si el tipo no está entre los soportados.
     */
    public function crear(array $datos): int
    {
        $this->validarTipo($datos);

        $stmt = $this->pdo->prepare(self::SQL_INSERTAR);
        $stmt->execute($this->parametros($datos));

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Alias en desuso de crear(), conservado por compatibilidad con el
     * código existente (public/espacios/guardar.php y los tests).
     *
     * @param array<string, mixed> $datos Datos del espacio a registrar.
     *
     * @return int ID autoincremental asignado por la base de datos.
     *
     * @deprecated Usar crear() en su lugar.
     */
    public function insertar(array $datos): int
    {
        return $this->crear($datos);
    }

    /**
     * [CRUD-UPDATE] Actualiza un espacio existente siguiendo Single Table Inheritance.
     *
     * IMPORTANTE: el UPDATE REEMPLAZA TODAS las columnas del espacio, no solo
     * las que el llamador desea modificar. Por eso, quien llama debe enviar el
     * juego completo de datos en $datos y, en particular, el NOMBRE de la
     * imagen actual en $datos['imagen'] si quiere conservarla: si se omite,
     * la columna `imagen` se guarda en NULL y la imagen se pierde. Así lo
     * hace public/espacios/actualizar.php, que envía $nombreFinalImagen.
     *
     * [SEGURIDAD] El tipo se valida contra EspacioFactory::tiposSoportados()
     * antes de tocar la base de datos.
     *
     * @param int $id Identificador único del espacio a modificar.
     * @param array<string, mixed> $datos Nuevos datos del espacio.
     *
     * @return bool True si la sentencia se ejecutó correctamente.
     *
     * @throws InvalidArgumentException Si el tipo no está entre los soportados.
     */
    public function actualizar(int $id, array $datos): bool
    {
        $this->validarTipo($datos);

        $stmt = $this->pdo->prepare(self::SQL_ACTUALIZAR);

        return $stmt->execute($this->parametros($datos) + [':id' => $id]);
    }

    /**
     * [CRUD-DELETE] Elimina un espacio por su id.
     *
     * CUIDADO: por la restricción `fk_reserva_espacio` con ON DELETE CASCADE,
     * borrar el espacio elimina TAMBIÉN todas sus reservas de forma
     * automática e irreversible. Si el llamador necesita conservarlas, debe
     * reasignarlas o archivarlas antes de invocar este método.
     *
     * La eliminación del ARCHIVO de imagen no se hace aquí: el repositorio
     * solo trabaja con la base de datos. Ese borrado físico es
     * responsabilidad de GestorImagenes, por lo que quien llama debe leer
     * el nombre de la imagen con obtenerFilaPorId() ANTES de eliminar(), y
     * luego borrarla del disco con GestorImagenes. Así lo hace
     * public/espacios/eliminar.php.
     *
     * @param int $id Identificador único del espacio a eliminar.
     *
     * @return bool True si la sentencia se ejecutó correctamente.
     */
    public function eliminar(int $id): bool
    {
        $stmt = $this->pdo->prepare(self::SQL_ELIMINAR);
        return $stmt->execute([':id' => $id]);
    }

    /**
     * [SEGURIDAD] Valida que el tipo recibido sea uno de los soportados.
     *
     * Se ejecuta antes de preparar cualquier sentencia, para que un tipo
     * inválido nunca llegue a persistirse. La lista de tipos válidos vive en
     * EspacioFactory, que es la única fuente de verdad del mapeo STI.
     *
     * @param array<string, mixed> $datos Datos del espacio.
     *
     * @return string El tipo normalizado en minúsculas.
     *
     * @throws InvalidArgumentException Si el tipo no está entre los soportados.
     */
    private function validarTipo(array $datos): string
    {
        $tipo = strtolower((string) ($datos['tipo'] ?? ''));

        if (!in_array($tipo, EspacioFactory::tiposSoportados(), true)) {
            throw new InvalidArgumentException("Tipo de espacio desconocido: {$tipo}");
        }

        return $tipo;
    }

    /**
     * [ENCAPSULAMIENTO] Construye el array de parámetros de crear() y
     * actualizar(), para que ambos métodos compartan exactamente los mismos
     * casts y la misma normalización de valores vacíos a NULL.
     *
     * @param array<string, mixed> $datos Datos del espacio.
     *
     * @return array<string, mixed> Placeholders listos para execute().
     */
    private function parametros(array $datos): array
    {
        return [
            ':tipo'                 => (string) ($datos['tipo'] ?? ''),
            ':nombre'               => (string) ($datos['nombre'] ?? ''),
            ':tarifa_base'          => (float) ($datos['tarifa_base'] ?? 0.0),
            ':capacidad'            => (int) ($datos['capacidad'] ?? 0),
            ':imagen'               => !empty($datos['imagen']) ? (string) $datos['imagen'] : null,
            ':tipo_grama'           => !empty($datos['tipo_grama']) ? (string) $datos['tipo_grama'] : null,
            ':iluminacion_nocturna' => $this->aBooleanoSql($datos['iluminacion_nocturna'] ?? null),
            ':tiene_computadora'    => $this->aBooleanoSql($datos['tiene_computadora'] ?? null),
            ':tiene_proyector'      => $this->aBooleanoSql($datos['tiene_proyector'] ?? null),
        ];
    }

    /**
     * [ENCAPSULAMIENTO] Normaliza un valor de formulario a la columna
     * booleana (TINYINT) de la tabla: null o cadena vacía se guardan como
     * NULL (la columna no aplica a ese tipo de espacio) y cualquier otro
     * valor se convierte a 0 o 1.
     *
     * @param mixed $valor Valor recibido del formulario.
     *
     * @return int|null 0, 1 o NULL si la columna no aplica.
     */
    private function aBooleanoSql(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return (int) (bool) $valor;
    }
}