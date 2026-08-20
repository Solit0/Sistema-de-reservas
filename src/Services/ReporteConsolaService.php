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
     * @param Reservable[] $espacios
     */
    public function imprimirResumenEstadistico(DateTimeInterface $fecha, array $espacios): void
    {
        $totalRecaudado = 0.0;
        $horasReservadas = 0.0;
        $horasPorEspacio = [];

        foreach ($espacios as $espacio) {
            $horasPorEspacio[$espacio->getNombre()] = 0.0;

            foreach ($this->obtenerReservasDelDia($espacio, $fecha) as $reserva) {
                $totalRecaudado += $reserva->getCostoCalculado();
                $duracion = $reserva->getHorario()->obtenerDuracionEnHoras();
                $horasReservadas += $duracion;
                $horasPorEspacio[$espacio->getNombre()] += $duracion;
            }
        }

        $maximoHoras = $horasPorEspacio === [] ? 0.0 : max($horasPorEspacio);
        $espaciosMasDemandados = array_keys(
            array_filter(
                $horasPorEspacio,
                static fn (float $horas): bool => $horas === $maximoHoras && $horas > 0
            )
        );
        $capacidadDiariaEnHoras = count($espacios) * 24;
        $porcentajeOcupacion = $capacidadDiariaEnHoras > 0
            ? ($horasReservadas / $capacidadDiariaEnHoras) * 100
            : 0.0;

        echo "\n=== RESUMEN ESTADÍSTICO DEL DÍA " . $fecha->format('Y-m-d') . " ===\n";
        echo 'Total recaudado: $' . number_format($totalRecaudado, 2, ',', '.') . "\n";
        echo 'Espacio más demandado: ' . (
            $espaciosMasDemandados === []
                ? 'Ninguno'
                : implode(', ', $espaciosMasDemandados)
        ) . ' (' . number_format($maximoHoras, 2, ',', '.') . " horas reservadas)\n";
        echo 'Ocupación del centro: ' . number_format($porcentajeOcupacion, 2, ',', '.')
            . "% (sobre 24 horas por espacio)\n\n";
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
