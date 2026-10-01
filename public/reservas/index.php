<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database\Conexion;
use App\Repositories\ReservaRepositorio;
use App\Services\ReservaStorageService;

if (!function_exists('e')) {
    function e(?string $valor): string
    {
        return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
    }
}

$reservas = [];
$fuente = 'Persistencia JSON';

if (class_exists(Conexion::class) && file_exists(__DIR__ . '/../../config/config.php')) {
    try {
        $pdo = Conexion::obtener();
        $reservaRepo = new ReservaRepositorio($pdo);
        $reservas = $reservaRepo->listarTodas();
        if (!empty($reservas)) {
            $fuente = 'Base de Datos (MySQL)';
        }
    } catch (\Throwable) {
    }
}

if (empty($reservas)) {
    $rutaJson = __DIR__ . '/../../reservas.json';
    if (file_exists($rutaJson)) {
        try {
            $storage = new ReservaStorageService();
            $datosJson = $storage->leerDeJson($rutaJson);
            foreach ($datosJson as $esp) {
                $nombreEspacio = $esp['espacio'] ?? 'Espacio';
                foreach (($esp['reservas'] ?? []) as $r) {
                    $reservas[] = [
                        'id'             => $r['id'] ?? 0,
                        'cliente'        => $r['titular'] ?? 'Cliente General',
                        'espacio_nombre' => $nombreEspacio,
                        'fecha'          => $r['fecha'] ?? '',
                        'hora_inicio'    => $r['hora_inicio'] ?? '',
                        'hora_fin'       => $r['hora_fin'] ?? '',
                        'monto_total'    => $r['costo'] ?? 0,
                        'es_pico'        => $r['es_pico'] ?? false,
                    ];
                }
            }
        } catch (\Throwable) {
        }
    }
}

$tituloPagina = 'Listado de Reservas';
require_once __DIR__ . '/../../views/layout/encabezado.php';
?>

<style>
    .catalogo-reservas header,
    .catalogo-reservas .panel-header {
        background: transparent !important;
        background-color: transparent !important;
        box-shadow: none !important;
        position: static !important;
        color: inherit !important;
        padding: 0 !important;
    }

    .badge-pico {
        background-color: #fffbeb;
        color: #b45309;
        font-weight: 700;
        font-size: 0.75rem;
        padding: 0.2rem 0.5rem;
        border-radius: 4px;
        border: 1px solid #fde68a;
    }

    .badge-regular {
        background-color: #f0fdf4;
        color: #15803d;
        font-weight: 600;
        font-size: 0.75rem;
        padding: 0.2rem 0.5rem;
        border-radius: 4px;
        border: 1px solid #bbf7d0;
    }
</style>

<section class="catalogo-reservas">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid var(--color-borde); padding-bottom: 1rem; flex-wrap: wrap; gap: 0.75rem;">
        <div>
            <h1 style="margin: 0; font-size: 2rem;"><?= e($tituloPagina) ?></h1>
            <p style="margin: 0.25rem 0 0 0; color: var(--color-texto-mutado);">
                Supervisión de agendas, clientes y estado de pagos (Fuente: <?= e($fuente) ?>).
            </p>
        </div>
        <div>
            <a href="/reservas/crear.php" class="btn btn-acento">
                + Nueva Reserva
            </a>
        </div>
    </div>

    <article class="panel">
        <?php if (empty($reservas)): ?>
            <div style="text-align: center; padding: 3rem 1rem;">
                <p style="color: var(--color-texto-mutado); font-size: 1.1rem; margin-bottom: 1.5rem;">
                    No hay reservas registradas en este momento.
                </p>
                <a href="/reservas/crear.php" class="btn btn-primario">
                    Crear la primera reserva
                </a>
            </div>
        <?php else: ?>
            <div class="tabla-responsive">
                <table class="tabla-datos">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Espacio</th>
                            <th>Fecha</th>
                            <th>Horario</th>
                            <th>Tarifa Aplicada</th>
                            <th>Monto Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservas as $reserva): ?>
                            <?php
                                $horaH = (int)substr((string)$reserva['hora_inicio'], 0, 2);
                                $esPico = !empty($reserva['es_pico']) || ($horaH >= 14 && $horaH < 19);
                            ?>
                            <tr>
                                <td>
                                    <strong><?= e((string) $reserva['cliente']) ?></strong>
                                </td>
                                <td>
                                    <span><?= e((string) $reserva['espacio_nombre']) ?></span>
                                </td>
                                <td><?= e((string) $reserva['fecha']) ?></td>
                                <td>
                                    <?= e((string) $reserva['hora_inicio']) ?> - <?= e((string) $reserva['hora_fin']) ?>
                                </td>
                                <td>
                                    <?php if ($esPico): ?>
                                        <span class="badge-pico">Horario Pico (+Recargo)</span>
                                    <?php else: ?>
                                        <span class="badge-regular">Regular</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="color: var(--color-secundario); font-size: 1rem;">
                                        $ <?= number_format((float) $reserva['monto_total'], 2) ?>
                                    </strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>
</section>

<?php
require_once __DIR__ . '/../../views/layout/pie.php';
