<?php

declare(strict_types=1);

namespace App\Domain\Espacios;

use App\Domain\Horario;

final class EscritorioIndividual extends Espacio
{
    private const PRECIO_POR_HORA = 75.0;

    public function __construct(string $nombre, int $capacidad = 1)
    {
        parent::__construct($nombre, $capacidad);
    }

    public function getTipo(): string
    {
        return 'Escritorio Individual';
    }

    public function calcularTarifa(Horario $horario, bool $esPico = false): float
    {
        $horas = $horario->obtenerDuracionEnHoras();

        return round($horas * self::PRECIO_POR_HORA, 2);
    }
}
