<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Reservable;
use App\Domain\Horario;
use App\Domain\Reserva;
use DateTimeInterface;
use InvalidArgumentException;

final class GestorReservas
{
    /** @var Reservable[] */
    private array $espacios = [];

    public function registrarEspacio(Reservable $espacio): void
    {
        $this->espacios[] = $espacio;
    }

    /**
     * @return Reservable[]
     */
    public function obtenerEspacios(): array
    {
        return $this->espacios;
    }

    public function crearReserva(
        Reservable $espacio,
        Horario $horario,
        string $titular,
        bool $esPico = false
    ): Reserva {
        if (!$espacio->verificarDisponibilidad($horario)) {
            throw new InvalidArgumentException(
                sprintf(
                    'El espacio "%s" no está disponible para el horario indicado.',
                    $espacio->getNombre()
                )
            );
        }

        $costo = $espacio->calcularTarifa($horario, $esPico);
        $reserva = new Reserva($horario, $titular, $costo, $esPico);

        $this->registrarReservaEnEspacio($espacio, $reserva);

        return $reserva;
    }

    public function generarReporteDelDia(DateTimeInterface $fecha): void
    {
        $reporte = new ReporteConsolaService();
        $reporte->generarReporteDelDia($fecha, $this->espacios);
    }

    private function registrarReservaEnEspacio(Reservable $espacio, Reserva $reserva): void
    {
        foreach ($this->espacios as $espacioRegistrado) {
            if ($espacioRegistrado === $espacio && method_exists($espacioRegistrado, 'agregarReserva')) {
                $espacioRegistrado->agregarReserva($reserva);
                return;
            }
        }

        throw new InvalidArgumentException('No se pudo registrar la reserva en el espacio indicado.');
    }
}
