<?php

declare(strict_types=1);

namespace App\Domain\Espacios;

use App\Domain\Horario;

final class SalaReunion extends Espacio
{
    private const PRECIO_POR_HORA = 180.0;
    private const RECARGO_PICO = 0.25;

    public function __construct(
        string $nombre,
        int $capacidad = 8,
        ?string $imagen = null,
        ?int $id = null,
        private readonly ?bool $tieneProyector = null
    ) {
        parent::__construct($nombre, $capacidad, $imagen, $id);
    }

    public function getTipo(): string
    {
        return 'Sala de Reunión';
    }

    public function obtenerTipoLegible(): string
    {
        return 'Sala de Reunión';
    }

    public function tieneProyector(): ?bool
    {
        return $this->tieneProyector;
    }

    public function obtenerDescripcion(): string
    {
        return 'Ambiente corporativo de alta gama optimizado para reuniones de negocios, juntas directivas, presentaciones ejecutivas y videoconferencias internacionales.';
    }

    /**
     * [POLIMORFISMO] Especificaciones técnicas resueltas por delegación
     *
     * @return array<string, string>
     */
    public function obtenerCaracteristicas(): array
    {
        $proyector = ($this->tieneProyector ?? true)
            ? 'Incluido (Proyector láser 4K y pantalla motorizada de 120")'
            : 'No incluido (Pantalla inteligente de apoyo)';

        return [
            'Equipamiento Audiovisual'    => $proyector,
            'Sistema de Videoconferencia' => 'Cámara inteligente 4K con encuadre automático y micrófonos Beamforming',
            'Conectividad de Medios'      => 'Tomas HDMI, USB-C Plug & Play y red Wi-Fi 6 de ultra alta velocidad',
            'Mobiliario y Confort'        => 'Mesa ejecutiva con canalización de cables y 8 sillas ergonómicas',
            'Climatización y Acústica'    => 'Aire acondicionado independiente e insonorización certificada',
            'Modalidad de Facturación'    => 'Cobro por hora fraccionable con recargo del 25% en horario de alta demanda',
        ];
    }

    public function calcularTarifa(Horario|int|float $horario, bool $esPico = false): float
    {
        $horas = $horario instanceof Horario ? $horario->obtenerDuracionEnHoras() : (float) $horario;
        $tarifa = $horas * self::PRECIO_POR_HORA;

        if ($esPico) {
            $tarifa *= 1 + self::RECARGO_PICO;
        }

        return round($tarifa, 2);
    }
}
