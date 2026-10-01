<?php

declare(strict_types=1);

namespace App\Factories;

use App\Domain\Espacios\Cancha;
use App\Domain\Espacios\EscritorioIndividual;
use App\Domain\Espacios\Espacio;
use App\Domain\Espacios\SalaReunion;
use InvalidArgumentException;

/**
 * Fábrica de espacios: construye la subclase concreta de Espacio a partir
 * de una fila de la tabla `espacios` traída de la base de datos.
 *
 * [FABRICA]
 * Este es el ÚNICO archivo del proyecto autorizado a decidir qué subclase
 * se instancia según la columna `tipo` de la fila. Gracias a esto, el resto
 * del sistema nunca necesita usar instanceof ni switch sobre el tipo de
 * espacio.
 *
 * La fábrica solo se ocupa de traducir datos: NO consulta la base de datos
 * y NO ejecuta ninguna sentencia SQL.
 *
 * NOTA: la columna `tarifa_base` y las columnas propias de cada subclase
 * (tipo_grama, iluminacion_nocturna, tiene_computadora, tiene_proyector)
 * todavía NO se consumen aquí, porque en el dominio las tarifas están
 * definidas como constantes dentro de cada subclase. Se pasarán al
 * constructor en cuanto el dominio soporte configurarlas.
 *
 * [POLIMORFISMO]
 * Todos los espacios se devuelven como instancias de la clase abstracta
 * Espacio, por lo que el resto del sistema los trata de forma uniforme
 * (getNombre(), getCapacidad(), calcularTarifa(), etc.) sin conocer la
 * subclase concreta que hay por detrás.
 */
final class EspacioFactory
{
    /**
     * Mapa de tipos de la base de datos a las clases concretas del dominio.
     *
     * @var array<string, class-string<Espacio>>
     */
    private const MAPA = [
        'cancha' => Cancha::class,
        'escritorio' => EscritorioIndividual::class,
        'sala' => SalaReunion::class,
    ];

    /**
     * Construye un espacio concreto a partir de una fila de `espacios`.
     *
     * @param array<string, mixed> $fila Fila con las columnas id, tipo, nombre,
     *                                   capacidad e imagen.
     *
     * @return Espacio Instancia de la subclase correspondiente al tipo.
     *
     * @throws InvalidArgumentException Si el tipo no está en el mapa.
     */
    public static function desdeFila(array $fila): Espacio
    {
        $tipo = strtolower((string) ($fila['tipo'] ?? ''));

        if (!array_key_exists($tipo, self::MAPA)) {
            throw new InvalidArgumentException("Tipo de espacio desconocido: {$tipo}");
        }

        $nombre = (string) ($fila['nombre'] ?? '');
        $capacidad = (int) ($fila['capacidad'] ?? 0);
        $imagen = (string) ($fila['imagen'] ?? '');
        $id = (int) ($fila['id'] ?? 0);

        // La imagen vacía se normaliza a null.
        $imagen = $imagen !== '' ? $imagen : null;

        return match ($tipo) {
            'cancha' => new Cancha($nombre, $capacidad, $imagen, $id),
            'escritorio' => new EscritorioIndividual($nombre, $capacidad, $imagen, $id),
            'sala' => new SalaReunion($nombre, $capacidad, $imagen, $id),
        };
    }

    /**
     * Lista de tipos de espacio soportados por la fábrica.
     *
     * @return string[] Claves del mapa de tipos.
     */
    public static function tiposSoportados(): array
    {
        return array_keys(self::MAPA);
    }
}