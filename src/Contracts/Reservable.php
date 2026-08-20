<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Domain\Horario;
use App\Domain\Reserva;

interface Reservable
{
    public function verificarDisponibilidad(Horario $horario): bool;

    public function calcularTarifa(Horario $horario, bool $esPico = false): float;

    public function agregarReserva(Reserva $reserva): void;

    /**
     * @return Reserva[]
     */
    public function obtenerReservas(): array;

    public function getNombre(): string;

    public function getTipo(): string;

    public function getCapacidad(): int;
}

