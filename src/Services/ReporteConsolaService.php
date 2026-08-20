<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Reservable;
use DateTimeInterface;

final class ReporteConsolaService
{
    /**
     * @param Reservable[] $espacios
     */
    public function generarReporteDelDia(DateTimeInterface $fecha, array $espacios): void
    {
        echo "\n=== REPORTE DEL DÍA " . $fecha->format('Y-m-d') . " ===\n\n";

        $linea = "+----------------------+----------------------+------------+---------------------+------------------+\n";
        echo $linea;
        echo sprintf(
            "| %-20s | %-20s | %-10s | %-19s | %-16s |\n",
            'ESPACIO',
            'TIPO',
            'ESTADO',
            'HORARIO',
            'MONTO'
        );
        echo $linea;

        $totalGeneral = 0.0;
        $hayReservas = false;

        foreach ($espacios as $espacio) {
            $reservasDelDia = $this->obtenerReservasDelDia($espacio, $fecha);

            if ($reservasDelDia === []) {
                echo sprintf(
                    "| %-20s | %-20s | %-10s | %-19s | %-16s |\n",
                    $espacio->getNombre(),
                    $espacio->getTipo(),
                    'LIBRE',
                    'Sin reservas',
                    '$0.00'
                );
                echo $linea;
                continue;
            }

            $hayReservas = true;
            $subtotal = 0.0;

            foreach ($reservasDelDia as $reserva) {
                $subtotal += $reserva->getCostoCalculado();
                $totalGeneral += $reserva->getCostoCalculado();

                echo sprintf(
                    "| %-20s | %-20s | %-10s | %-19s | %-16s |\n",
                    $espacio->getNombre(),
                    $espacio->getTipo(),
                    $reserva->esPico() ? 'PICO' : 'OK',
                    $reserva->getHorario()->getInicio()->format('H:i') . '-' . $reserva->getHorario()->getFin()->format('H:i'),
                    '$' . number_format($reserva->getCostoCalculado(), 2, ',', '.')
                );
                echo $linea;
            }

            echo sprintf(
                "| %-20s | %-20s | %-10s | %-19s | %-16s |\n",
                'SUBTOTAL',
                '',
                '',
                '',
                '$' . number_format($subtotal, 2, ',', '.')
            );
            echo $linea;
        }

        if (!$hayReservas) {
            echo "No hay reservas registradas para este día.\n\n";
            return;
        }

        echo sprintf(
            "| %-20s | %-20s | %-10s | %-19s | %-16s |\n",
            'TOTAL GENERAL',
            '',
            '',
            '',
            '$' . number_format($totalGeneral, 2, ',', '.')
        );
        echo $linea;
    }

    /**
     * @return \App\Domain\Reserva[]
     */
    private function obtenerReservasDelDia(Reservable $espacio, DateTimeInterface $fecha): array
    {
        $reservas = [];

        foreach ($espacio->obtenerReservas() as $reserva) {
            if ($reserva->getHorario()->perteneceALaFecha($fecha)) {
                $reservas[] = $reserva;
            }
        }

        return $reservas;
    }
}
