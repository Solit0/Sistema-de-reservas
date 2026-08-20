<?php

declare(strict_types=1);

namespace App;

/**
 * Representa una reserva concreta hecha sobre un espacio.
 *
 * @concept ENCAPSULAMIENTO
 * Todos los atributos son privados y de solo lectura (readonly) tras
 * su creación; una Reserva no cambia de estado una vez confirmada,
 * lo cual evita inconsistencias (p. ej. modificar el costo luego de
 * generado el reporte del día).
 *
 * @concept ABSTRACCION
 * Reserva no sabe CÓMO se calculó el costo ni el tipo de espacio
 * reservado; solo almacena el resultado (Reservable, Horario,
 * titular y costo), delegando el cálculo al propio Espacio a través
 * del contrato Reservable.
 */
final class Reserva
{
    private static int $contador = 0;

    private readonly int $id;
    private readonly Reservable $espacio;
    private readonly Horario $horario;
    private readonly string $titular;
    private readonly float $costo;

    public function __construct(
        Reservable $espacio,
        Horario $horario,
        string $titular,
        float $costo
    ) {
        if (trim($titular) === '') {
            throw new \InvalidArgumentException('El titular de la reserva no puede estar vacío.');
        }
        if ($costo < 0) {
            throw new \InvalidArgumentException('El costo no puede ser negativo.');
        }

        self::$contador++;
        $this->id = self::$contador;
        $this->espacio = $espacio;
        $this->horario = $horario;
        $this->titular = $titular;
        $this->costo = $costo;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEspacio(): Reservable
    {
        return $this->espacio;
    }

    public function getHorario(): Horario
    {
        return $this->horario;
    }

    public function getTitular(): string
    {
        return $this->titular;
    }

    public function getCosto(): float
    {
        return $this->costo;
    }

    public function __toString(): string
    {
        return sprintf(
            'Reserva #%d | Titular: %s | Horario: %s | Costo: $%.2f',
            $this->id,
            $this->titular,
            (string) $this->horario,
            $this->costo
        );
    }
}
