<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * Sistema de Reservas - Reporte Financiero y de Ocupación
 * ==============================================================================
 * Muestra el desglose de ingresos y estadísticas de reservas generadas por espacio.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// [SEGURIDAD] Escape seguro contra XSS
if (!function_exists('e')) {
    function e(?string $valor): string
    {
        return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
    }
}

$ingresosPorTipo = [
    'Sala de Reunión'       => ['reservas' => 2, 'ingresos' => 810.00],
    'Cancha'                => ['reservas' => 2, 'ingresos' => 395.00],
    'Escritorio Individual' => ['reservas' => 1, 'ingresos' => 187.50],
];

$totalIngresos = array_sum(array_column($ingresosPorTipo, 'ingresos'));
$totalReservas = array_sum(array_column($ingresosPorTipo, 'reservas'));

$tituloPagina = 'Reporte Financiero';
require_once __DIR__ . '/../../views/layout/encabezado.php';
?>

<style>
    .reporte-financiero header,
    .reporte-financiero .panel-header {
        background: transparent !important;
        background-color: transparent !important;
        box-shadow: none !important;
        position: static !important;
        color: inherit !important;
        padding: 0 !important;
    }
</style>

<section class="reporte-financiero">
    <div style="margin-bottom: 1.5rem; border-bottom: 1px solid var(--color-borde); padding-bottom: 1rem;">
        <h1 style="margin: 0; font-size: 2rem;"><?= e($tituloPagina) ?></h1>
        <p style="margin: 0.25rem 0 0 0; color: var(--color-texto-mutado);">
            Resumen consolidado de ingresos brutos y ocupación de espacios.
        </p>
    </div>

    <div class="grid-metricas">
        <article class="tarjeta-metrica" style="border-left: 4px solid var(--color-exito);">
            <span class="metrica-etiqueta">Ingresos Totales Brutos</span>
            <span class="metrica-valor" style="color: var(--color-exito);">$ <?= number_format($totalIngresos, 2) ?></span>
            <small style="color: var(--color-texto-mutado);">Facturación acumulada del centro</small>
        </article>

        <article class="tarjeta-metrica" style="border-left: 4px solid var(--color-acento);">
            <span class="metrica-etiqueta">Total de Reservas Cobradas</span>
            <span class="metrica-valor"><?= e((string) $totalReservas) ?></span>
            <small style="color: var(--color-texto-mutado);">Transacciones completadas</small>
        </article>
    </div>

    <article class="panel" style="margin-top: 1.5rem;">
        <header class="panel-header" style="border-bottom: 1px solid var(--color-borde); padding-bottom: 0.75rem; margin-bottom: 1rem;">
            <h2>Desglose Financiero por Categoría de Espacio</h2>
        </header>

        <div class="tabla-responsive">
            <table class="tabla-datos">
                <thead>
                    <tr>
                        <th>Categoría / Tipo</th>
                        <th>Reservas Realizadas</th>
                        <th>Ingresos Totales</th>
                        <th>Participación</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ingresosPorTipo as $tipo => $datos): ?>
                        <?php $porcentaje = $totalIngresos > 0 ? ($datos['ingresos'] / $totalIngresos) * 100 : 0; ?>
                        <tr>
                            <td><strong><?= e($tipo) ?></strong></td>
                            <td><?= e((string) $datos['reservas']) ?></td>
                            <td><strong>$ <?= number_format($datos['ingresos'], 2) ?></strong></td>
                            <td>
                                <span><?= number_format($porcentaje, 1) ?>%</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>

<?php
require_once __DIR__ . '/../../views/layout/pie.php';
