<?php

declare(strict_types=1);

namespace App\Domain\Espacios;

use App\Domain\Horario;

final class Cancha extends Espacio
{
    private const PRECIO_POR_BLOQUE = 120.0;
    private const RECARGO_PICO_POR_BLOQUE = 35.0;

    public function __construct(string $nombre, int $capacidad = 10, ?string $imagen = null)
    {
        parent::__construct($nombre, $capacidad, $imagen);
    }

    public function getTipo(): string
    {
        return 'Cancha';
    }

    public function obtenerTipoLegible(): string
    {
        return 'Cancha';
    }

    public function calcularTarifa(Horario|int|float $horario, bool $esPico = false): float
    {
        if ($horario instanceof Horario) {
            $bloques = (int) ceil($horario->obtenerDuracionEnMinutos() / 60.0);
        } else {
            $bloques = (int) ceil((float) $horario);
        }
        $total = $bloques * self::PRECIO_POR_BLOQUE;

        if ($esPico) {
            $total += $bloques * self::RECARGO_PICO_POR_BLOQUE;
        }

        return round($total, 2);
    }
}
