<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Reservable;
use InvalidArgumentException;
use RuntimeException;

final class ReservaStorageService
{
    /**
     * Exporta las reservas agrupadas por espacio a un archivo en formato JSON.
     *
     * @param Reservable[] $espacios
     * @throws RuntimeException Si ocurre un error al escribir el archivo.
     */
    public function exportarAJson(array $espacios, string $rutaArchivo): void
    {
        $datos = [];

        foreach ($espacios as $espacio) {
            $reservasData = [];
            foreach ($espacio->obtenerReservas() as $reserva) {
                $reservasData[] = [
                    'id' => $reserva->getId(),
                    'titular' => $reserva->getTitular(),
                    'fecha' => $reserva->getHorario()->obtenerFecha(),
                    'hora_inicio' => $reserva->getHorario()->getInicio()->format('H:i'),
                    'hora_fin' => $reserva->getHorario()->getFin()->format('H:i'),
                    'duracion_minutos' => $reserva->getHorario()->obtenerDuracionEnMinutos(),
                    'costo' => $reserva->getCostoCalculado(),
                    'es_pico' => $reserva->esPico(),
                ];
            }

            $datos[] = [
                'espacio' => $espacio->getNombre(),
                'tipo' => $espacio->getTipo(),
                'capacidad' => $espacio->getCapacidad(),
                'total_reservas' => count($reservasData),
                'reservas' => $reservasData,
            ];
        }

        $json = json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Error al codificar los datos en formato JSON.');
        }

        if (file_put_contents($rutaArchivo, $json) === false) {
            throw new RuntimeException("No se pudo escribir en el archivo: {$rutaArchivo}");
        }
    }

    /**
     * Carga y decodifica los datos guardados en el archivo JSON.
     *
     * @return array<string, mixed>
     * @throws RuntimeException Si el archivo no existe o contiene JSON inválido.
     */
    public function leerDeJson(string $rutaArchivo): array
    {
        if (!file_exists($rutaArchivo)) {
            throw new RuntimeException("El archivo '{$rutaArchivo}' no existe.");
        }

        $contenido = file_get_contents($rutaArchivo);
        if ($contenido === false) {
            throw new RuntimeException("No se pudo leer el archivo: {$rutaArchivo}");
        }

        $datos = json_decode($contenido, true);
        if (!is_array($datos)) {
            throw new RuntimeException("Formato JSON inválido en el archivo: {$rutaArchivo}");
        }

        return $datos;
    }
}
