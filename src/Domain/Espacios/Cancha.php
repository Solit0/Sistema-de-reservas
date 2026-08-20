<?php

declare(strict_types=1);

namespace App\Domain\Espacios;

use App\Domain\Horario;

final class Cancha extends Espacio
{
    private const PRECIO_POR_BLOQUE = 120.0;
    private const RECARGO_PICO_POR_BLOQUE = 35.0;

    public function __construct(string $nombre, int $capacidad = 10)
    {
        parent::__construct($nombre, $capacidad);
    }

    public function getTipo(): string
    {
        return 'Cancha';
    }

    public function calcularTarifa(Horario $horario, bool $esPico = false): float
    {
        $bloques = (int) ceil($horario->obtenerDuracionEnMinutos() / 60.0);
        $total = $bloques * self::PRECIO_POR_BLOQUE;

        if ($esPico) {
            $total += $bloques * self::RECARGO_PICO_POR_BLOQUE;
        }

        return round($total, 2);
    }
}
