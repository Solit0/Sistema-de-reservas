<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * Sistema de Reservas - Controlador de Procesamiento y Registro (PRG)
 * ==============================================================================
 * [CRUD-CREATE] [VALIDACION] [SEGURIDAD] [PRG]
 * Procesa la creación de reservas con control de traslapes, cálculo dinámico
 * de tarifas y redirección mediante el patrón Post-Redirect-Get.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database\Conexion;
use App\Repositories\ReservaRepositorio;

// Solo se procesan solicitudes por método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /reservas/crear.php', true, 303);
    exit;
}

$espacioId   = (int)($_POST['espacio_id'] ?? 0);
$cliente     = trim((string)($_POST['cliente'] ?? ''));
$fecha       = trim((string)($_POST['fecha'] ?? ''));
$horaInicio  = trim((string)($_POST['hora_inicio'] ?? ''));
$horaFin     = trim((string)($_POST['hora_fin'] ?? ''));
$esPico      = isset($_POST['es_pico']) && $_POST['es_pico'] === '1';

// [VALIDACION] Validación básica de campos requeridos
if ($espacioId <= 0 || $cliente === '' || $fecha === '' || $horaInicio === '' || $horaFin === '') {
    $_SESSION['error'] = 'Por favor complete todos los campos obligatorios del formulario.';
    $_SESSION['valores_previos'] = $_POST;
    header('Location: /reservas/crear.php', true, 303);
    exit;
}

// [VALIDACION] Verificación de consistencia cronológica
if ($horaFin <= $horaInicio) {
    $_SESSION['error'] = 'La hora de finalización debe ser posterior a la hora de inicio.';
    $_SESSION['valores_previos'] = $_POST;
    header('Location: /reservas/crear.php', true, 303);
    exit;
}

try {
    $pdo = Conexion::obtener();
    $reservaRepo = new ReservaRepositorio($pdo);

    // [VALIDACION] Verificación central anti-traslape en MySQL
    if ($reservaRepo->existeTraslape($espacioId, $fecha, $horaInicio, $horaFin)) {
        $_SESSION['error'] = 'El espacio seleccionado ya está ocupado en ese horario. Por favor elija otra hora.';
        $_SESSION['valores_previos'] = $_POST;
        header('Location: /reservas/crear.php', true, 303);
        exit;
    }

    // Consulta de información del espacio para cálculo polimórfico de tarifa
    $stmtEspacio = $pdo->prepare('SELECT id, tipo, nombre, tarifa_base FROM espacios WHERE id = :id');
    $stmtEspacio->execute([':id' => $espacioId]);
    $espacio = $stmtEspacio->fetch(PDO::FETCH_ASSOC);

    if (!$espacio) {
        $_SESSION['error'] = 'El espacio seleccionado no existe en el sistema.';
        header('Location: /reservas/crear.php', true, 303);
        exit;
    }

    // Cálculo dinámico del monto según tipo de espacio y duración
    $inicioDt = new DateTimeImmutable("{$fecha} {$horaInicio}");
    $finDt = new DateTimeImmutable("{$fecha} {$horaFin}");
    $duracionMinutos = (int)abs(($finDt->getTimestamp() - $inicioDt->getTimestamp()) / 60);
    $duracionHoras = $duracionMinutos / 60.0;
    $tarifaBase = (float)$espacio['tarifa_base'];

    $tipo = strtolower((string)$espacio['tipo']);
    if ($tipo === 'cancha') {
        $bloques = (int)ceil($duracionMinutos / 60.0);
        $montoTotal = $bloques * $tarifaBase;
        if ($esPico) {
            $montoTotal += $bloques * 35.0; // Recargo fijo de hora pico para cancha
        }
    } elseif ($tipo === 'sala') {
        $montoTotal = $duracionHoras * $tarifaBase;
        if ($esPico) {
            $montoTotal *= 1.25; // Recargo de 25% para salas en hora pico
        }
    } else {
        // Escritorio individual u otros
        $montoTotal = $duracionHoras * $tarifaBase;
    }

    $montoTotal = round($montoTotal, 2);

    // [CRUD-CREATE] Inserción de la reserva en base de datos
    $reservaRepo->registrar($espacioId, $cliente, $fecha, $horaInicio, $horaFin, $montoTotal);

    $_SESSION['exito'] = sprintf('¡Reserva registrada con éxito para "%s" ($ %.2f)!', $cliente, $montoTotal);
    unset($_SESSION['valores_previos']);

    // [PRG] Redirección limpia hacia el listado de reservas
    header('Location: /reservas/index.php', true, 303);
    exit;

} catch (\Throwable $e) {
    $_SESSION['error'] = 'Error al procesar la reserva: ' . $e->getMessage();
    $_SESSION['valores_previos'] = $_POST;
    header('Location: /reservas/crear.php', true, 303);
    exit;
}
