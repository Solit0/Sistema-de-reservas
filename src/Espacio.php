<?php

declare(strict_types=1);

namespace App;

/**
 * Modelo base abstracto para todo espacio reservable del centro.
 *
 * @concept ABSTRACCION
 * Define los atributos y comportamiento común a cualquier espacio
 * (id, nombre, capacidad) sin implementar la lógica específica de
 * disponibilidad ni tarifa: eso queda delegado a las subclases
 * concretas mediante los métodos abstractos.
 *
 * @concept ENCAPSULAMIENTO
 * Los atributos son privados/protegidos; se exponen únicamente a
 * través de getters, evitando que código externo los modifique
 * directamente y rompa invariantes del objeto.
 *
 * @concept HERENCIA
 * Las clases concretas (SalaReunion, EscritorioIndividual, Cancha)
 * heredarán de esta clase reutilizando id, nombre y capacidad, y
 * además implementarán el contrato Reservable.
 */
abstract class Espacio implements Reservable
{
    private static int $contador = 0;

    protected readonly int $id;
    protected string $nombre;
    protected int $capacidad;

    public function __construct(string $nombre, int $capacidad)
    {
        if (trim($nombre) === '') {
            throw new \InvalidArgumentException('El nombre del espacio no puede estar vacío.');
        }
        if ($capacidad <= 0) {
            throw new \InvalidArgumentException('La capacidad debe ser mayor a cero.');
        }

        self::$contador++;
        $this->id = self::$contador;
        $this->nombre = $nombre;
        $this->capacidad = $capacidad;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function getCapacidad(): int
    {
        return $this->capacidad;
    }

    /**
     * Descripción legible del espacio y su tipo concreto.
     *
     * @concept POLIMORFISMO
     * getTipo() es abstracto: cada subclase concreta define su propia
     * etiqueta ("Sala de Reunión", "Escritorio Individual", "Cancha")
     * sin que esta clase base conozca esos detalles.
     */
    abstract public function getTipo(): string;

    public function __toString(): string
    {
        return sprintf('[%s] %s (cap: %d)', $this->getTipo(), $this->nombre, $this->capacidad);
    }
}
