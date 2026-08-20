<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Domain\Horario;

interface Reservable
{
    public function verificarDisponibilidad(Horario $horario): bool;

    public function calcularTarifa(Horario $horario, bool $esPico = false): float;

    public function getNombre(): string;

    public function getTipo(): string;
}
