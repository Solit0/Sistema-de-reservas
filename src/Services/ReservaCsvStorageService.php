<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Reservable;
use RuntimeException;

final class ReservaCsvStorageService
{
    /**
     * Exporta una fila por reserva a un archivo CSV.
     *
     * @param Reservable[] $espacios
     * @throws RuntimeException Si ocurre un error al escribir el archivo.
     */
    public function exportarACsv(array $espacios, string $rutaArchivo): void
    {
        $archivo = fopen($rutaArchivo, 'wb');

        if ($archivo === false) {
            throw new RuntimeException("No se pudo abrir el archivo: {$rutaArchivo}");
        }

        try {
            if (fputcsv($archivo, ['Espacio', 'Tipo', 'Titular', 'Fecha', 'Inicio', 'Fin', 'Monto'], ',', '"', '') === false) {
                throw new RuntimeException("No se pudo escribir en el archivo: {$rutaArchivo}");
            }

            foreach ($espacios as $espacio) {
                foreach ($espacio->obtenerReservas() as $reserva) {
                    $horario = $reserva->getHorario();
                    $fila = [
                        $espacio->getNombre(),
                        $espacio->getTipo(),
                        $reserva->getTitular(),
                        $horario->obtenerFecha(),
                        $horario->getInicio()->format('H:i'),
                        $horario->getFin()->format('H:i'),
                        number_format($reserva->getCostoCalculado(), 2, '.', ''),
                    ];

                    if (fputcsv($archivo, $fila, ',', '"', '') === false) {
                        throw new RuntimeException("No se pudo escribir en el archivo: {$rutaArchivo}");
                    }
                }
            }
        } finally {
            fclose($archivo);
        }
    }
}