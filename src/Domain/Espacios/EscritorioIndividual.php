<?php

declare(strict_types=1);

namespace App\Domain\Espacios;

use App\Domain\Horario;

final class EscritorioIndividual extends Espacio
{
    private const PRECIO_POR_HORA = 75.0;

    public function __construct(string $nombre, int $capacidad = 1, ?string $imagen = null)
    {
        parent::__construct($nombre, $capacidad, $imagen);
    }

    public function getTipo(): string
    {
        return 'Escritorio Individual';
    }

    public function obtenerTipoLegible(): string
    {
        return 'Escritorio Individual';
    }

    public function calcularTarifa(Horario|int|float $horario, bool $esPico = false): float
    {
        $horas = $horario instanceof Horario ? $horario->obtenerDuracionEnHoras() : (float) $horario;

        return round($horas * self::PRECIO_POR_HORA, 2);
    }
}
