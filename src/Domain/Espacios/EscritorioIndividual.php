<?php

declare(strict_types=1);

namespace App\Domain\Espacios;

use App\Domain\Horario;

final class EscritorioIndividual extends Espacio
{
    private const PRECIO_POR_HORA = 75.0;

    public function __construct(
        string $nombre,
        int $capacidad = 1,
        ?string $imagen = null,
        ?int $id = null,
        private readonly ?bool $tieneComputadora = null
    ) {
        parent::__construct($nombre, $capacidad, $imagen, $id);
    }

    public function getTipo(): string
    {
        return 'Escritorio Individual';
    }

    public function obtenerTipoLegible(): string
    {
        return 'Escritorio Individual';
    }

    public function tieneComputadora(): ?bool
    {
        return $this->tieneComputadora;
    }

    public function obtenerDescripcion(): string
    {
        return 'Estación de trabajo ergonómica individual en área de coworking silenciosa, diseñada para profesionales autónomos, programadores y teletrabajadores enfocados.';
    }

    /**
     * [POLIMORFISMO] Especificaciones técnicas resueltas por delegación
     *
     * @return array<string, string>
     */
    public function obtenerCaracteristicas(): array
    {
        $computadora = ($this->tieneComputadora ?? true)
            ? 'Incluido (Estación Todo-en-Uno con monitor 27" y periféricos inalámbricos)'
            : 'Bring Your Own Device (Puesto equipado con dock USB-C y monitor auxiliar)';

        return [
            'Equipamiento Informático' => $computadora,
            'Alimentación Eléctrica'   => 'Regleta dedicada con tomas schuko, puertos USB-C PD y cargador Qi inalámbrico',
            'Ergonomía de Trabajo'     => 'Escritorio con superficie amplia, lámpara regulable y silla ergonómica de respaldo alto',
            'Conexión a Internet'      => 'Fibra óptica simétrica dedicada de 1 Gbps vía cable RJ45 y Wi-Fi 6',
            'Ambiente Operativo'       => 'Zona de concentración libre de ruidos con acceso a cafetería',
            'Modalidad de Facturación' => 'Tarifa plana por hora sin cargos adicionales en horas pico ($75.00/h)',
        ];
    }

    public function calcularTarifa(Horario|int|float $horario, bool $esPico = false): float
    {
        $horas = $horario instanceof Horario ? $horario->obtenerDuracionEnHoras() : (float) $horario;

        return round($horas * self::PRECIO_POR_HORA, 2);
    }
}
