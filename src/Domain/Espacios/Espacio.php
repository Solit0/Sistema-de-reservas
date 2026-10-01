<?php

declare(strict_types=1);

namespace App\Domain\Espacios;

use App\Contracts\Reservable;
use App\Domain\Horario;
use App\Domain\Reserva;
use InvalidArgumentException;

abstract class Espacio implements Reservable
{
    private static int $contador = 0;

    protected readonly int $id;
    protected string $nombre;
    protected int $capacidad;
    protected ?string $imagen = null;

    /** @var Reserva[] */
    private array $reservas = [];

    public function __construct(string $nombre, int $capacidad, ?string $imagen = null, ?int $id = null)
    {
        if (trim($nombre) === '') {
            throw new InvalidArgumentException('El nombre del espacio no puede estar vacío.');
        }

        if ($capacidad <= 0) {
            throw new InvalidArgumentException('La capacidad debe ser mayor a cero.');
        }

        if ($id !== null) {
            // Se usa el id real de la base de datos y el contador no avanza.
            $this->id = $id;
        } else {
            self::$contador++;
            $this->id = self::$contador;
        }

        $this->nombre = $nombre;
        $this->capacidad = $capacidad;
        $this->imagen = $imagen;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function getImagen(): ?string
    {
        return $this->imagen;
    }

    public function setImagen(?string $imagen): void
    {
        $this->imagen = $imagen;
    }

    public function obtenerTipoLegible(): string
    {
        return $this->getTipo();
    }

    public function getCapacidad(): int
    {
        return $this->capacidad;
    }

    public function agregarReserva(Reserva $reserva): void
    {
        foreach ($this->reservas as $reservaExistente) {
            if ($reservaExistente->getHorario()->seSolapaCon($reserva->getHorario())) {
                throw new InvalidArgumentException(
                    sprintf(
                        'El espacio "%s" ya tiene una reserva que se superpone con el horario indicado.',
                        $this->nombre
                    )
                );
            }
        }

        $this->reservas[] = $reserva;
    }

    /**
     * @return Reserva[]
     */
    public function obtenerReservas(): array
    {
        return $this->reservas;
    }

    public function verificarDisponibilidad(Horario $horario): bool
    {
        foreach ($this->reservas as $reserva) {
            if ($reserva->getHorario()->seSolapaCon($horario)) {
                return false;
            }
        }

        return true;
    }

    abstract public function getTipo(): string;

    /**
     * Retorna las especificaciones técnicas particulares del espacio.
     * [POLIMORFISMO] Cada subclase implementa sus propios atributos específicos
     * resolviendo el contrato sin necesidad de comprobación de tipos.
     *
     * @return array<string, string>
     */
    abstract public function obtenerCaracteristicas(): array;

    /**
     * Retorna una descripción informativa del espacio.
     */
    public function obtenerDescripcion(): string
    {
        return 'Espacio acondicionado profesionalmente para garantizar máxima comodidad, rendimiento y productividad.';
    }

    public function __toString(): string
    {
        return sprintf('[%s] %s (cap: %d)', $this->getTipo(), $this->nombre, $this->capacidad);
    }
}
