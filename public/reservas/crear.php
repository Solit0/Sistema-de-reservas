<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * Sistema de Reservas - Registrar Nueva Reserva
 * ==============================================================================
 * Formulario para agendar una nueva reserva de espacio con control de colisiones.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database\Conexion;

// [SEGURIDAD] Escape seguro contra XSS
if (!function_exists('e')) {
    function e(?string $valor): string
    {
        return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// Cargar lista de espacios desde la base de datos MySQL
$espaciosDisponibles = [];
if (class_exists(Conexion::class) && file_exists(__DIR__ . '/../../config/config.php')) {
    try {
        $pdo = Conexion::obtener();
        $stmt = $pdo->query('SELECT id, nombre, tipo, capacidad, tarifa_base FROM espacios ORDER BY nombre ASC');
        if ($stmt) {
            $espaciosDisponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (\Throwable) {
    }
}

// Fallback si aún no hay conexión MySQL configurada
if (empty($espaciosDisponibles)) {
    $espaciosDisponibles = [
        ['id' => 1, 'nombre' => 'Sala de Juntas', 'tipo' => 'sala', 'capacidad' => 8, 'tarifa_base' => 180.00],
        ['id' => 2, 'nombre' => 'Cancha Sintética', 'tipo' => 'cancha', 'capacidad' => 10, 'tarifa_base' => 120.00],
        ['id' => 3, 'nombre' => 'Escritorio 01', 'tipo' => 'escritorio', 'capacidad' => 1, 'tarifa_base' => 75.00],
    ];
}

$error = $_SESSION['error'] ?? null;
unset($_SESSION['error']);

$prev = $_SESSION['valores_previos'] ?? [];
unset($_SESSION['valores_previos']);

$tituloPagina = 'Nueva Reserva';
require_once __DIR__ . '/../../views/layout/encabezado.php';
?>

<style>
    .formulario-contenedor header,
    .formulario-contenedor .panel-header {
        background: transparent !important;
        background-color: transparent !important;
        box-shadow: none !important;
        position: static !important;
        color: inherit !important;
        padding: 0 !important;
    }
</style>

<section class="formulario-contenedor" style="max-width: 680px; margin: 0 auto;">
    <div style="margin-bottom: 1.5rem;">
        <a href="/reservas/index.php" class="btn btn-secundario" style="margin-bottom: 0.75rem; font-size: 0.85rem; padding: 0.35rem 0.75rem;">
            &larr; Volver a Reservas
        </a>
        <h1 style="margin: 0; font-size: 1.85rem;"><?= e($tituloPagina) ?></h1>
        <p style="margin: 0.25rem 0 0 0; color: var(--color-texto-mutado);">
            Programa una cita o reserva seleccionando el espacio y horario deseado con control de traslapes.
        </p>
    </div>

    <?php if ($error !== null): ?>
        <div style="background-color: #fef2f2; border: 1px solid #f87171; color: #991b1b; padding: 0.85rem 1.25rem; border-radius: 6px; margin-bottom: 1.25rem;">
            <strong>Error:</strong> <?= e($error) ?>
        </div>
    <?php endif; ?>

    <article class="panel">
        <form action="/reservas/guardar.php" method="POST" class="formulario">
            <div class="grupo-campo">
                <label for="cliente">Nombre del Cliente / Titular *</label>
                <input type="text" id="cliente" name="cliente" class="campo-control" 
                       placeholder="Ej: María López" 
                       value="<?= e($prev['cliente'] ?? '') ?>" required>
            </div>

            <div class="grupo-campo">
                <label for="espacio_id">Espacio a Reservar *</label>
                <select id="espacio_id" name="espacio_id" class="campo-control" required>
                    <option value="">-- Selecciona un espacio --</option>
                    <?php foreach ($espaciosDisponibles as $esp): ?>
                        <?php $selected = ((int)($prev['espacio_id'] ?? 0) === (int)$esp['id']) ? 'selected' : ''; ?>
                        <option value="<?= (int)$esp['id'] ?>" <?= $selected ?>>
                            <?= e($esp['nombre']) ?> (<?= e(ucfirst($esp['tipo'])) ?>, Cap: <?= (int)$esp['capacidad'] ?>, Tarifa: $<?= number_format((float)$esp['tarifa_base'], 2) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grupo-campo">
                <label for="fecha">Fecha de Reserva *</label>
                <input type="date" id="fecha" name="fecha" class="campo-control" 
                       value="<?= e($prev['fecha'] ?? date('Y-m-d')) ?>" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="grupo-campo">
                    <label for="hora_inicio">Hora de Inicio *</label>
                    <input type="time" id="hora_inicio" name="hora_inicio" class="campo-control" 
                           value="<?= e($prev['hora_inicio'] ?? '09:00') ?>" required>
                </div>
                <div class="grupo-campo">
                    <label for="hora_fin">Hora de Fin *</label>
                    <input type="time" id="hora_fin" name="hora_fin" class="campo-control" 
                           value="<?= e($prev['hora_fin'] ?? '11:00') ?>" required>
                </div>
            </div>

            <div class="grupo-campo" style="margin-top: 0.5rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" name="es_pico" value="1" <?= (!empty($prev['es_pico'])) ? 'checked' : '' ?>>
                    <span>Aplicar tarifa de <strong>Horario Pico</strong> (aplica recargo según tipo de espacio)</span>
                </label>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
                <a href="/reservas/index.php" class="btn btn-secundario">Cancelar</a>
                <button type="submit" class="btn btn-acento">Confirmar Reserva</button>
            </div>
        </form>
    </article>
</section>

<?php
require_once __DIR__ . '/../../views/layout/pie.php';
