<?php

declare(strict_types=1);

namespace App\Domain\Espacios;

use App\Domain\Horario;

final class Cancha extends Espacio
{
    private const PRECIO_POR_BLOQUE = 120.0;
    private const RECARGO_PICO_POR_BLOQUE = 35.0;

    public function __construct(
        string $nombre,
        int $capacidad = 10,
        ?string $imagen = null,
        ?int $id = null,
        private readonly ?string $tipoGrama = null,
        private readonly ?bool $iluminacionNocturna = null
    ) {
        parent::__construct($nombre, $capacidad, $imagen, $id);
    }

    public function getTipo(): string
    {
        return 'Cancha';
    }

    public function obtenerTipoLegible(): string
    {
        return 'Cancha';
    }

    public function getTipoGrama(): ?string
    {
        return $this->tipoGrama;
    }

    public function tieneIluminacionNocturna(): ?bool
    {
        return $this->iluminacionNocturna;
    }

    public function obtenerDescripcion(): string
    {
        return 'Instalación deportiva reglamentaria acondicionada para entrenamientos y partidos de alta intensidad, con mantenimiento constante de superficie y opciones de iluminación nocturna.';
    }

    /**
     * [POLIMORFISMO] Especificaciones técnicas resueltas por delegación
     *
     * @return array<string, string>
     */
    public function obtenerCaracteristicas(): array
    {
        $superficie = $this->tipoGrama ?? 'Sintética (Césped monofilamento)';
        $iluminacion = ($this->iluminacionNocturna ?? true)
            ? 'Disponible (Sistema de reflectores LED para horario nocturno)'
            : 'No disponible (Uso exclusivo en horario diurno)';

        return [
            'Tipo de Superficie'        => $superficie,
            'Iluminación Nocturna'      => $iluminacion,
            'Dimensiones del Campo'     => 'Reglamentarias para fútbol 5 y multideporte',
            'Equipamiento Incluido'     => 'Arcos oficiales, redes de alta resistencia y balones de entrenamiento',
            'Servicios Complementarios' => 'Acceso a camerinos, casilleros seguros y duchas con agua caliente',
            'Modalidad de Facturación'  => 'Bloques cerrados de 60 minutos con recargo fijo en horario pico ($35.00/blq)',
        ];
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
