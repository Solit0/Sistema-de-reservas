<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Representa un rango de tiempo (fecha/hora de inicio y fin).
 *
 * @concept ENCAPSULAMIENTO
 * Las propiedades son privadas e inmutables (readonly). El objeto se
 * valida a sí mismo en el constructor, garantizando que nunca exista
 * un Horario inválido (fin antes que inicio) en el sistema.
 */
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

    /**
     * Duración del horario en minutos.
     */
    public function duracionEnMinutos(): int
    {
        $segundos = $this->fin->getTimestamp() - $this->inicio->getTimestamp();
        return (int) ($segundos / 60);
    }

    /**
     * Determina si este horario se superpone con otro.
     * Útil para que las clases concretas resuelvan disponibilidad.
     */
    public function seSuperponeCon(Horario $otro): bool
    {
        return $this->inicio < $otro->getFin() && $this->fin > $otro->getInicio();
    }

    public function fecha(): string
    {
        return $this->inicio->format('Y-m-d');
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
