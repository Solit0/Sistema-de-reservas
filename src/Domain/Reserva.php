<?php

declare(strict_types=1);

namespace App\Domain;

final class Reserva
{
    private static int $contador = 0;

    private readonly int $id;
    private readonly Horario $horario;
    private readonly string $titular;
    private readonly float $costoCalculado;
    private readonly bool $esPico;

    public function __construct(
        Horario $horario,
        string $titular,
        float $costoCalculado,
        bool $esPico = false
    ) {
        if (trim($titular) === '') {
            throw new \InvalidArgumentException('El titular de la reserva no puede estar vacío.');
        }

        if ($costoCalculado < 0) {
            throw new \InvalidArgumentException('El costo calculado no puede ser negativo.');
        }

        self::$contador++;
        $this->id = self::$contador;
        $this->horario = $horario;
        $this->titular = $titular;
        $this->costoCalculado = $costoCalculado;
        $this->esPico = $esPico;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getHorario(): Horario
    {
        return $this->horario;
    }

    public function getTitular(): string
    {
        return $this->titular;
    }

    public function getCostoCalculado(): float
    {
        return $this->costoCalculado;
    }

    public function esPico(): bool
    {
        return $this->esPico;
    }

    public function __toString(): string
    {
        return sprintf(
            'Reserva #%d | Titular: %s | Horario: %s | Costo: $%.2f',
            $this->id,
            $this->titular,
            (string) $this->horario,
            $this->costoCalculado
        );
    }
}
