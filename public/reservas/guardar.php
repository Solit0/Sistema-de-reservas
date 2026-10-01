<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database\Conexion;
use App\Domain\Espacios\Cancha;
use App\Domain\Espacios\EscritorioIndividual;
use App\Domain\Espacios\SalaReunion;
use App\Domain\Horario;
use App\Repositories\EspacioRepositorio;
use App\Repositories\ReservaRepositorio;
use App\Services\ReservaStorageService;
use App\Validation\Validador;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /reservas/crear.php');
    exit;
}

$cliente = trim((string)($_POST['cliente'] ?? ''));
$espacioId = (int)($_POST['espacio_id'] ?? 0);
$fecha = trim((string)($_POST['fecha'] ?? ''));
$horaInicio = trim((string)($_POST['hora_inicio'] ?? ''));
$horaFin = trim((string)($_POST['hora_fin'] ?? ''));

$_SESSION['antiguo'] = [
    'cliente'     => $cliente,
    'espacio_id'  => $espacioId,
    'fecha'       => $fecha,
    'hora_inicio' => $horaInicio,
    'hora_fin'    => $horaFin,
];

$validador = new Validador();
$validador
    ->requerido('cliente', $cliente, 'El nombre del cliente o titular es obligatorio.')
    ->longitud('cliente', $cliente, 3, 100, 'El nombre debe tener entre 3 y 100 caracteres.')
    ->entero('espacio_id', $espacioId, 1, null, 'Debe seleccionar un espacio válido.')
    ->requerido('fecha', $fecha, 'La fecha de reserva es obligatoria.')
    ->fecha('fecha', $fecha, 'Y-m-d', 'La fecha seleccionada no es válida (formato YYYY-MM-DD).')
    ->requerido('hora_inicio', $horaInicio, 'La hora de inicio es obligatoria.')
    ->hora('hora_inicio', $horaInicio, 'H:i', 'La hora de inicio no tiene un formato válido (HH:MM).')
    ->requerido('hora_fin', $horaFin, 'La hora de fin es obligatoria.')
    ->hora('hora_fin', $horaFin, 'H:i', 'La hora de fin no tiene un formato válido (HH:MM).')
    ->horaMayorQue('hora_fin', $horaFin, $horaInicio, 'La hora de fin debe ser posterior a la hora de inicio.');

if (!$validador->esValido()) {
    $_SESSION['errores'] = $validador->getErrores();
    header('Location: /reservas/crear.php');
    exit;
}

try {
    $espacio = null;
    $pdo = null;

    if (class_exists(Conexion::class) && file_exists(__DIR__ . '/../../config/config.php')) {
        try {
            $pdo = Conexion::obtener();
            $espacioRepo = new EspacioRepositorio($pdo);
            $espacio = $espacioRepo->buscarPorId($espacioId);
        } catch (\Throwable) {
        }
    }

    if ($espacio === null) {
        $rutaJson = __DIR__ . '/../../reservas.json';
        if (file_exists($rutaJson)) {
            $storage = new ReservaStorageService();
            $datosJson = $storage->leerDeJson($rutaJson);
            $idx = 1;
            foreach ($datosJson as $item) {
                if ($idx === $espacioId) {
                    $tipoNormalizado = mb_strtolower((string)($item['tipo'] ?? ''));
                    $nombre = (string)($item['espacio'] ?? 'Espacio');
                    $capacidad = (int)($item['capacidad'] ?? 1);
                    if (str_contains($tipoNormalizado, 'cancha')) {
                        $espacio = new Cancha($nombre, $capacidad, null, $idx);
                    } elseif (str_contains($tipoNormalizado, 'sala')) {
                        $espacio = new SalaReunion($nombre, $capacidad, null, $idx);
                    } else {
                        $espacio = new EscritorioIndividual($nombre, $capacidad, null, $idx);
                    }
                    break;
                }
                $idx++;
            }
        }
    }

    if ($espacio === null) {
        $catalogoFallback = [
            1 => new SalaReunion('Sala de Juntas Principal', 8, null, 1),
            2 => new Cancha('Cancha Central Sintética', 10, null, 2),
            3 => new EscritorioIndividual('Escritorio Individual 01', 1, null, 3),
        ];
        $espacio = $catalogoFallback[$espacioId] ?? null;
    }

    if ($espacio === null) {
        $_SESSION['errores'] = ['espacio_id' => 'El espacio seleccionado no existe en el sistema.'];
        header('Location: /reservas/crear.php');
        exit;
    }

    $inicioDt = new DateTimeImmutable($fecha . ' ' . $horaInicio);
    $finDt = new DateTimeImmutable($fecha . ' ' . $horaFin);
    $horario = new Horario($inicioDt, $finDt);

    $horaInt = (int)$inicioDt->format('H');
    $esPico = ($horaInt >= 14 && $horaInt < 19);
    $montoTotal = $espacio->calcularTarifa($horario, $esPico);

    if ($pdo !== null) {
        $reservaRepo = new ReservaRepositorio($pdo);
        if ($reservaRepo->existeTraslape($espacioId, $fecha, $horaInicio, $horaFin)) {
            $_SESSION['errores'] = [
                'general' => sprintf(
                    'Conflicto de horario: El espacio "%s" ya cuenta con una reserva en el intervalo %s - %s para el día %s.',
                    $espacio->getNombre(),
                    $horaInicio,
                    $horaFin,
                    $fecha
                ),
            ];
            header('Location: /reservas/crear.php');
            exit;
        }

        $reservaId = $reservaRepo->registrar(
            $espacioId,
            $cliente,
            $fecha,
            $horaInicio,
            $horaFin,
            $montoTotal
        );
    } else {
        $rutaJson = __DIR__ . '/../../reservas.json';
        $storage = new ReservaStorageService();
        $datos = file_exists($rutaJson) ? $storage->leerDeJson($rutaJson) : [];

        foreach ($datos as $item) {
            if (($item['espacio'] ?? '') === $espacio->getNombre()) {
                foreach (($item['reservas'] ?? []) as $r) {
                    if (($r['fecha'] ?? '') === $fecha) {
                        $existIni = (string)($r['hora_inicio'] ?? '');
                        $existFin = (string)($r['hora_fin'] ?? '');
                        if ($existIni < $horaFin && $existFin > $horaInicio) {
                            $_SESSION['errores'] = [
                                'general' => sprintf(
                                    'Conflicto de horario: El espacio "%s" ya cuenta con una reserva en el intervalo %s - %s para el día %s.',
                                    $espacio->getNombre(),
                                    $horaInicio,
                                    $horaFin,
                                    $fecha
                                ),
                            ];
                            header('Location: /reservas/crear.php');
                            exit;
                        }
                    }
                }
            }
        }

        $nuevoId = count($datos, COUNT_RECURSIVE) + 1;
        $encontrado = false;
        foreach ($datos as &$item) {
            if (($item['espacio'] ?? '') === $espacio->getNombre()) {
                $item['reservas'][] = [
                    'id'               => $nuevoId,
                    'titular'          => $cliente,
                    'fecha'            => $fecha,
                    'hora_inicio'      => $horaInicio,
                    'hora_fin'         => $horaFin,
                    'duracion_minutos' => $horario->obtenerDuracionEnMinutos(),
                    'costo'            => $montoTotal,
                    'es_pico'          => $esPico,
                ];
                $item['total_reservas'] = count($item['reservas']);
                $encontrado = true;
                break;
            }
        }
        unset($item);

        if (!$encontrado) {
            $datos[] = [
                'espacio'        => $espacio->getNombre(),
                'tipo'           => $espacio->getTipo(),
                'capacidad'      => $espacio->getCapacidad(),
                'total_reservas' => 1,
                'reservas'       => [
                    [
                        'id'               => $nuevoId,
                        'titular'          => $cliente,
                        'fecha'            => $fecha,
                        'hora_inicio'      => $horaInicio,
                        'hora_fin'         => $horaFin,
                        'duracion_minutos' => $horario->obtenerDuracionEnMinutos(),
                        'costo'            => $montoTotal,
                        'es_pico'          => $esPico,
                    ],
                ],
            ];
        }

        file_put_contents($rutaJson, json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $reservaId = $nuevoId;
    }

    unset($_SESSION['antiguo'], $_SESSION['errores']);
    $_SESSION['flash'] = sprintf(
        '¡Reserva #%d confirmada con éxito para %s en "%s"! Total calculado: $%s',
        $reservaId,
        $cliente,
        $espacio->getNombre(),
        number_format($montoTotal, 2)
    );

    header('Location: /reservas/index.php');
    exit;

} catch (\Throwable $e) {
    $_SESSION['errores'] = [
        'general' => 'Ocurrió un error al procesar la reserva: ' . $e->getMessage(),
    ];
    header('Location: /reservas/crear.php');
    exit;
}
