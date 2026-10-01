<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * Sistema de Reservas - Controlador de Almacenamiento (PRG)
 * ==============================================================================
 * Procesa la creación de reservas con validación estricta, verificación
 * matemática anti-traslapes y cálculo polimórfico de tarifas.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database\Conexion;
use App\Domain\Horario;
use App\Repositories\EspacioRepositorio;
use App\Repositories\ReservaRepositorio;
use App\Validation\Validador;
use DateTimeImmutable;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// [SEGURIDAD] Solo aceptar peticiones vía HTTP POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /reservas/crear.php');
    exit;
}

$cliente = trim((string)($_POST['cliente'] ?? ''));
$espacioId = (int)($_POST['espacio_id'] ?? 0);
$fecha = trim((string)($_POST['fecha'] ?? ''));
$horaInicio = trim((string)($_POST['hora_inicio'] ?? ''));
$horaFin = trim((string)($_POST['hora_fin'] ?? ''));

// Preservar datos en sesión para repoblar el formulario en caso de error
$_SESSION['antiguo'] = [
    'cliente'     => $cliente,
    'espacio_id'  => $espacioId,
    'fecha'       => $fecha,
    'hora_inicio' => $horaInicio,
    'hora_fin'    => $horaFin,
];

// 1. Validación de entradas con la clase Validador
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
    $pdo = Conexion::obtener();
    $espacioRepo = new EspacioRepositorio($pdo);
    $reservaRepo = new ReservaRepositorio($pdo);

    // 2. Verificar existencia del espacio en el catálogo
    $espacio = $espacioRepo->buscarPorId($espacioId);
    if ($espacio === null) {
        $_SESSION['errores'] = ['espacio_id' => 'El espacio seleccionado no existe en el sistema.'];
        header('Location: /reservas/crear.php');
        exit;
    }

    // 3. [ALGORITMO-TRASLAPE] Comprobar disponibilidad temporal sin conflictos
    if ($reservaRepo->existeTraslape($espacioId, $fecha, $horaInicio, $horaFin)) {
        $_SESSION['errores'] = [
            'general' => sprintf(
                'Conflicto de horario: El espacio "%s" ya cuenta con una reserva activa en el intervalo %s - %s para el día %s.',
                $espacio->getNombre(),
                $horaInicio,
                $horaFin,
                $fecha
            ),
        ];
        header('Location: /reservas/crear.php');
        exit;
    }

    // 4. [POLIMORFISMO] Instanciar objeto Horario y calcular tarifa polimórfica
    $inicioDt = new DateTimeImmutable($fecha . ' ' . $horaInicio);
    $finDt = new DateTimeImmutable($fecha . ' ' . $horaFin);
    $horario = new Horario($inicioDt, $finDt);

    // Determinar horario pico (por ejemplo: horas de la tarde de 14:00 a 19:00)
    $horaInt = (int)$inicioDt->format('H');
    $esPico = ($horaInt >= 14 && $horaInt < 19);

    // Cálculo polimórfico puro llamando a la interfaz Reservable sin comprobar subclase concreta
    $montoTotal = $espacio->calcularTarifa($horario, $esPico);

    // 5. Persistir reserva mediante ReservaRepositorio
    $reservaId = $reservaRepo->registrar(
        $espacioId,
        $cliente,
        $fecha,
        $horaInicio,
        $horaFin,
        $montoTotal
    );

    // Limpiar sesión y preparar mensaje flash de éxito (PRG)
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
        'general' => 'Ocurrió un error inesperado al procesar la reserva: ' . $e->getMessage(),
    ];
    header('Location: /reservas/crear.php');
    exit;
}
