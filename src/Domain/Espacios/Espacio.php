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

    /** @var Reserva[] */
    private array $reservas = [];

    public function __construct(string $nombre, int $capacidad)
    {
        if (trim($nombre) === '') {
            throw new InvalidArgumentException('El nombre del espacio no puede estar vacío.');
        }

        if ($capacidad <= 0) {
            throw new InvalidArgumentException('La capacidad debe ser mayor a cero.');
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

    public function __toString(): string
    {
        return sprintf('[%s] %s (cap: %d)', $this->getTipo(), $this->nombre, $this->capacidad);
    }
}
