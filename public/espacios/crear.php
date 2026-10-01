<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * Sistema de Reservas - Registrar Nuevo Espacio
 * ==============================================================================
 * Formulario para el registro de nuevos espacios en el sistema.
 * Preparado para la integración con Validador y GestorImagenes (Integrantes 3 y 4).
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// [SEGURIDAD] Escape seguro contra XSS
if (!function_exists('e')) {
    function e(?string $valor): string
    {
        return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
    }
}

$tituloPagina = 'Registrar Nuevo Espacio';
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
        <!-- [SEGURIDAD] Sanitización de salida contra vectores XSS -->
        <a href="/espacios/index.php" class="btn btn-secundario" style="margin-bottom: 0.75rem; font-size: 0.85rem; padding: 0.35rem 0.75rem;">
            &larr; Volver al Catálogo
        </a>
        <h1 style="margin: 0; font-size: 1.85rem;"><?= e($tituloPagina) ?></h1>
        <p style="margin: 0.25rem 0 0 0; color: var(--color-texto-mutado);">
            Completa la información para dar de alta un nuevo espacio en el catálogo.
        </p>
    </div>

    <article class="panel">
        <form action="#" method="POST" enctype="multipart/form-data" class="formulario">
            <div class="grupo-campo">
                <label for="nombre">Nombre del Espacio *</label>
                <input type="text" id="nombre" name="nombre" class="campo-control" placeholder="Ej: Sala de Juntas VIP" required>
            </div>

            <div class="grupo-campo">
                <label for="tipo">Tipo de Espacio *</label>
                <select id="tipo" name="tipo" class="campo-control" required>
                    <option value="">-- Selecciona una categoría --</option>
                    <option value="cancha">Cancha Deportiva</option>
                    <option value="sala">Sala de Reunión</option>
                    <option value="escritorio">Escritorio Individual</option>
                </select>
            </div>

            <div class="grupo-campo">
                <label for="capacidad">Capacidad Máxima (personas) *</label>
                <input type="number" id="capacidad" name="capacidad" class="campo-control" min="1" placeholder="Ej: 8" required>
            </div>

            <div class="grupo-campo">
                <label for="tarifa_base">Tarifa Base por Hora ($) *</label>
                <input type="number" step="0.01" id="tarifa_base" name="tarifa_base" class="campo-control" min="1" placeholder="Ej: 150.00" required>
            </div>

            <div class="grupo-campo">
                <label for="imagen">Fotografía / Imagen del Espacio</label>
                <input type="file" id="imagen" name="imagen" class="campo-control" accept="image/*">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem;">
                <a href="/espacios/index.php" class="btn btn-secundario">Cancelar</a>
                <button type="submit" class="btn btn-acento">Guardar Espacio</button>
            </div>
        </form>
    </article>
</section>

<?php
require_once __DIR__ . '/../../views/layout/pie.php';
