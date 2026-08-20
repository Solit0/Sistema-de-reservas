<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final class Horario
{
    private readonly DateTimeImmutable $inicio;
    private readonly DateTimeImmutable $fin;

    public function __construct(DateTimeImmutable $inicio, DateTimeImmutable $fin)
    {
        if ($fin <= $inicio) {
            throw new InvalidArgumentException(
                'La fecha de fin debe ser posterior a la fecha de inicio.'
            );
        }

        $this->inicio = $inicio;
        $this->fin = $fin;
    }

    public function getInicio(): DateTimeImmutable
    {
        return $this->inicio;
    }

    public function getFin(): DateTimeImmutable
    {
        return $this->fin;
    }

    public function seSolapaCon(Horario $otro): bool
    {
        return $this->inicio < $otro->getFin() && $this->fin > $otro->getInicio();
    }

    public function obtenerDuracionEnMinutos(): int
    {
        $segundos = $this->fin->getTimestamp() - $this->inicio->getTimestamp();

        return (int) abs($segundos / 60);
    }

    public function obtenerDuracionEnHoras(): float
    {
        return $this->obtenerDuracionEnMinutos() / 60.0;
    }

    public function obtenerFecha(): string
    {
        return $this->inicio->format('Y-m-d');
    }

    public function perteneceALaFecha(DateTimeInterface $fecha): bool
    {
        return $this->inicio->format('Y-m-d') === $fecha->format('Y-m-d');
    }

    public function __toString(): string
    {
        return sprintf(
            '%s - %s',
            $this->inicio->format('Y-m-d H:i'),
            $this->fin->format('H:i')
        );
    }
}
