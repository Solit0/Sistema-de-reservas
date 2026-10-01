<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * Sistema de Reservas - Reporte Financiero y de Ocupación
 * ==============================================================================
 * Muestra el desglose de ingresos y estadísticas de reservas generadas por espacio.
 * Soporta agregación dinámica vía PDO (MySQL) con fallback automático a JSON.
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
    'Sala de Reunión'       => ['reservas' => 0, 'ingresos' => 0.0],
    'Cancha'                => ['reservas' => 0, 'ingresos' => 0.0],
    'Escritorio Individual' => ['reservas' => 0, 'ingresos' => 0.0],
];

$fuenteReporte = 'Predeterminada';

// Estrategia 1: Carga y agregación dinámica desde MySQL (PDO)
if (class_exists(\App\Database\Conexion::class) && file_exists(__DIR__ . '/../../config/config.php')) {
    try {
        $pdo = \App\Database\Conexion::obtener();
        $query = 'SELECT e.tipo, COUNT(r.id) AS total_reservas, COALESCE(SUM(r.monto_total), 0) AS total_ingresos
                  FROM espacios e
                  LEFT JOIN reservas r ON e.id = r.espacio_id
                  GROUP BY e.tipo';
        $stmt = $pdo->query($query);
        if ($stmt) {
            $filas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (!empty($filas)) {
                $nombresTipo = [
                    'sala'       => 'Sala de Reunión',
                    'cancha'     => 'Cancha',
                    'escritorio' => 'Escritorio Individual',
                ];
                $ingresosPorTipo = [
                    'Sala de Reunión'       => ['reservas' => 0, 'ingresos' => 0.0],
                    'Cancha'                => ['reservas' => 0, 'ingresos' => 0.0],
                    'Escritorio Individual' => ['reservas' => 0, 'ingresos' => 0.0],
                ];
                foreach ($filas as $f) {
                    $tKey = strtolower((string)$f['tipo']);
                    $label = $nombresTipo[$tKey] ?? ucfirst($tKey);
                    $ingresosPorTipo[$label] = [
                        'reservas' => (int)$f['total_reservas'],
                        'ingresos' => (float)$f['total_ingresos'],
                    ];
                }
                $fuenteReporte = 'Base de Datos (MySQL / PDO)';
            }
        }
    } catch (\Throwable) {
    }
}

// Estrategia 2: Fallback a persistencia JSON
if ($fuenteReporte === 'Predeterminada') {
    $rutaJson = __DIR__ . '/../../reservas.json';
    if (file_exists($rutaJson)) {
        try {
            $storage = new \App\Services\ReservaStorageService();
            $datosJson = $storage->leerDeJson($rutaJson);
            $temp = [
                'Sala de Reunión'       => ['reservas' => 0, 'ingresos' => 0.0],
                'Cancha'                => ['reservas' => 0, 'ingresos' => 0.0],
                'Escritorio Individual' => ['reservas' => 0, 'ingresos' => 0.0],
            ];
            foreach ($datosJson as $esp) {
                $tipoEspacio = (string)($esp['tipo'] ?? 'Otro');
                if (!isset($temp[$tipoEspacio])) {
                    $temp[$tipoEspacio] = ['reservas' => 0, 'ingresos' => 0.0];
                }
                foreach (($esp['reservas'] ?? []) as $r) {
                    $temp[$tipoEspacio]['reservas']++;
                    $temp[$tipoEspacio]['ingresos'] += (float)($r['costo'] ?? 0.0);
                }
            }
            $ingresosPorTipo = $temp;
            $fuenteReporte = 'Persistencia JSON';
        } catch (\Throwable) {
        }
    }
}

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
    <div style="margin-bottom: 1.5rem; border-bottom: 1px solid var(--color-borde); padding-bottom: 1rem; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 0.75rem;">
        <div>
            <h1 style="margin: 0; font-size: 2rem;"><?= e($tituloPagina) ?></h1>
            <p style="margin: 0.25rem 0 0 0; color: var(--color-texto-mutado);">
                Resumen consolidado de ingresos brutos y ocupación de espacios.
            </p>
        </div>
        <div>
            <span style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; background: #f1f5f9; color: var(--color-secundario); padding: 0.35rem 0.75rem; border-radius: 9999px; border: 1px solid var(--color-borde); font-weight: 600;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                Fuente: <?= e($fuenteReporte) ?>
            </span>
        </div>
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
