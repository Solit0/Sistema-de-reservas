<?php

declare(strict_types=1);

namespace App\Domain\Espacios;

use App\Domain\Horario;

final class SalaReunion extends Espacio
{
    private const PRECIO_POR_HORA = 180.0;
    private const RECARGO_PICO = 0.25;

    public function __construct(string $nombre, int $capacidad = 8)
    {
        parent::__construct($nombre, $capacidad);
    }

    public function getTipo(): string
    {
        return 'Sala de Reunión';
    }

    public function calcularTarifa(Horario $horario, bool $esPico = false): float
    {
        $horas = $horario->obtenerDuracionEnHoras();
        $tarifa = $horas * self::PRECIO_POR_HORA;

        if ($esPico) {
            $tarifa *= 1 + self::RECARGO_PICO;
        }

        return round($tarifa, 2);
    }
}
