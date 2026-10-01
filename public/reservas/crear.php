<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * Sistema de Reservas - Registrar Nueva Reserva
 * ==============================================================================
 * Formulario para agendar una nueva reserva de espacio.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// [SEGURIDAD] Escape seguro contra XSS
if (!function_exists('e')) {
    function e(?string $valor): string
    {
        return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
    }
}

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
            Programa una cita o reserva seleccionando el espacio y horario deseado.
        </p>
    </div>

    <article class="panel">
        <form action="#" method="POST" class="formulario">
            <div class="grupo-campo">
                <label for="cliente">Nombre del Cliente / Titular *</label>
                <input type="text" id="cliente" name="cliente" class="campo-control" placeholder="Ej: María López" required>
            </div>

            <div class="grupo-campo">
                <label for="espacio_id">Espacio a Reservar *</label>
                <select id="espacio_id" name="espacio_id" class="campo-control" required>
                    <option value="">-- Selecciona un espacio --</option>
                    <option value="1">Sala de Juntas (Cap: 8)</option>
                    <option value="2">Cancha Sintética (Cap: 10)</option>
                    <option value="3">Escritorio 01 (Cap: 1)</option>
                </select>
            </div>

            <div class="grupo-campo">
                <label for="fecha">Fecha de Reserva *</label>
                <input type="date" id="fecha" name="fecha" class="campo-control" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="grupo-campo">
                    <label for="hora_inicio">Hora de Inicio *</label>
                    <input type="time" id="hora_inicio" name="hora_inicio" class="campo-control" value="09:00" required>
                </div>
                <div class="grupo-campo">
                    <label for="hora_fin">Hora de Fin *</label>
                    <input type="time" id="hora_fin" name="hora_fin" class="campo-control" value="11:00" required>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem;">
                <a href="/reservas/index.php" class="btn btn-secundario">Cancelar</a>
                <button type="submit" class="btn btn-acento">Confirmar Reserva</button>
            </div>
        </form>
    </article>
</section>

<?php
require_once __DIR__ . '/../../views/layout/pie.php';
