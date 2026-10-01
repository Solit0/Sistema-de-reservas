<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * Sistema de Reservas - Ficha Técnica del Espacio
 * ==============================================================================
 * Vista detallada de un espacio específico por ID.
 * Muestra tarifas desglosadas (estándar y horario pico), capacidad e historial
 * de reservas asociadas resolviendo las llamadas de forma polimórfica.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Domain\Espacios\Cancha;
use App\Domain\Espacios\EscritorioIndividual;
use App\Domain\Espacios\Espacio;
use App\Domain\Espacios\SalaReunion;

// [SEGURIDAD] Escape seguro contra XSS
if (!function_exists('e')) {
    function e(?string $valor): string
    {
        return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// 1. Obtención y sanitización del ID solicitado
$idSolicitado = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

/** @var Espacio|null $espacioEncontrado */
$espacioEncontrado = null;
$reservasEspacio = [];

// 2. Búsqueda polimórfica del espacio
// Estrategia A: Base de datos si está disponible
if (class_exists(\App\Database\Conexion::class) && file_exists(__DIR__ . '/../../config/config.php')) {
    try {
        $pdo = \App\Database\Conexion::obtener();
        $stmt = $pdo->prepare('SELECT id, tipo, nombre, capacidad, imagen FROM espacios WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $idSolicitado]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
            $clases = [
                'cancha'     => Cancha::class,
                'sala'       => SalaReunion::class,
                'escritorio' => EscritorioIndividual::class,
            ];
            $tipoKey = strtolower((string) ($row['tipo'] ?? ''));
            if (isset($clases[$tipoKey])) {
                $clase = $clases[$tipoKey];
                $espacioEncontrado = new $clase(
                    (string) $row['nombre'],
                    (int) $row['capacidad'],
                    $row['imagen'] ? (string) $row['imagen'] : null
                );
            }
        }
    } catch (\Throwable) {
    }
}

// Estrategia B: Persistencia JSON si no se encontró en BD
if ($espacioEncontrado === null) {
    $rutaJson = __DIR__ . '/../../reservas.json';
    if (file_exists($rutaJson)) {
        try {
            $storage = new \App\Services\ReservaStorageService();
            $datosJson = $storage->leerDeJson($rutaJson);
            $contador = 1;
            foreach ($datosJson as $item) {
                if ($contador === $idSolicitado) {
                    $tipoNorm = mb_strtolower((string) ($item['tipo'] ?? ''));
                    $nombre = (string) ($item['espacio'] ?? 'Espacio');
                    $capacidad = (int) ($item['capacidad'] ?? 1);

                    if (str_contains($tipoNorm, 'cancha')) {
                        $espacioEncontrado = new Cancha($nombre, $capacidad);
                    } elseif (str_contains($tipoNorm, 'sala')) {
                        $espacioEncontrado = new SalaReunion($nombre, $capacidad);
                    } elseif (str_contains($tipoNorm, 'escritorio')) {
                        $espacioEncontrado = new EscritorioIndividual($nombre, $capacidad);
                    }
                    $reservasEspacio = $item['reservas'] ?? [];
                    break;
                }
                $contador++;
            }
        } catch (\Throwable) {
        }
    }
}

// Fallback por defecto si no existe o ID fuera de rango
if ($espacioEncontrado === null) {
    $espacioEncontrado = new SalaReunion('Espacio de Demostración', 8);
}

// [POLIMORFISMO] Resolución dinámica de métodos polimórficos
$tipoLegible = $espacioEncontrado->obtenerTipoLegible();
$tarifa1h    = $espacioEncontrado->calcularTarifa(1);
$tarifa2h    = $espacioEncontrado->calcularTarifa(2);
$tarifaPico  = $espacioEncontrado->calcularTarifa(2, true);

$tituloPagina = 'Ficha Técnica - ' . $espacioEncontrado->getNombre();
require_once __DIR__ . '/../../views/layout/encabezado.php';
?>

<style>
    .ficha-detalle header,
    .ficha-detalle .panel-header {
        background: transparent !important;
        background-color: transparent !important;
        box-shadow: none !important;
        position: static !important;
        color: inherit !important;
        padding: 0 !important;
    }

    .ficha-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 1.5rem;
        margin-top: 1.5rem;
    }

    @media (max-width: 768px) {
        .ficha-grid {
            grid-template-columns: 1fr;
        }
    }

    .tabla-tarifas td {
        padding: 0.65rem 0.85rem;
    }
</style>

<section class="ficha-detalle">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid var(--color-borde); padding-bottom: 1rem;">
        <div>
            <!-- [SEGURIDAD] Sanitización de salida contra vectores XSS -->
            <a href="index.php" class="btn btn-secundario" style="margin-bottom: 0.75rem; font-size: 0.85rem; padding: 0.35rem 0.75rem;">
                &larr; Volver al Catálogo
            </a>
            <h1 style="margin: 0; font-size: 1.85rem;"><?= e($espacioEncontrado->getNombre()) ?></h1>
            <span class="badge-tipo" style="display: inline-block; margin-top: 0.35rem; padding: 0.2rem 0.6rem; background: #f1f5f9; border-radius: 4px; font-weight: 600;">
                <!-- [SEGURIDAD] Sanitización de salida contra vectores XSS -->
                <?= e($tipoLegible) ?>
            </span>
        </div>
        <div style="text-align: right;">
            <a href="/reservas/crear.php?espacio_id=<?= e((string) ($idSolicitado ?? 1)) ?>" class="btn btn-acento">
                Reservar este Espacio
            </a>
        </div>
    </div>

    <div class="ficha-grid">
        <article class="panel">
            <header class="panel-header" style="border-bottom: 1px solid var(--color-borde); padding-bottom: 0.5rem; margin-bottom: 1rem;">
                <h2>Especificaciones Técnicas</h2>
            </header>
            <div class="panel-cuerpo">
                <p><strong>Capacidad Máxima:</strong> <?= e((string) $espacioEncontrado->getCapacidad()) ?> personas</p>
                <p><strong>Disponibilidad:</strong> <span style="color: var(--color-exito); font-weight: bold;">Activo y Operativo</span></p>

                <h3 style="margin-top: 1.5rem; margin-bottom: 0.75rem;">Tarifas Calculadas (Polimorfismo)</h3>
                <div class="tabla-responsive">
                    <table class="tabla-datos tabla-tarifas">
                        <thead>
                            <tr>
                                <th>Duración / Condición</th>
                                <th>Monto Calculado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Bloque Regular de 1 Hora</td>
                                <td><strong>$ <?= number_format($tarifa1h, 2) ?></strong></td>
                            </tr>
                            <tr>
                                <td>Estándar de 2 Horas</td>
                                <td><strong style="color: var(--color-acento);">$ <?= number_format($tarifa2h, 2) ?></strong></td>
                            </tr>
                            <tr>
                                <td>Estándar 2 Horas en Horario Pico (+ Recargo)</td>
                                <td><strong style="color: var(--color-aviso);">$ <?= number_format($tarifaPico, 2) ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </article>

        <article class="panel">
            <header class="panel-header" style="border-bottom: 1px solid var(--color-borde); padding-bottom: 0.5rem; margin-bottom: 1rem;">
                <h3>Reservas Asociadas</h3>
            </header>
            <div class="panel-cuerpo">
                <?php if (empty($reservasEspacio)): ?>
                    <p style="color: var(--color-texto-mutado); font-size: 0.9rem;">No hay reservas programadas para este espacio.</p>
                <?php else: ?>
                    <ul style="list-style: none; padding: 0; display: flex; flex-direction: column; gap: 0.75rem;">
                        <?php foreach ($reservasEspacio as $res): ?>
                            <li style="padding: 0.65rem; background: #f8fafc; border-radius: 6px; border: 1px solid var(--color-borde); font-size: 0.85rem;">
                                <strong><?= e($res['titular'] ?? 'Cliente') ?></strong><br>
                                <span style="color: var(--color-texto-mutado);"><?= e($res['fecha'] ?? '') ?> | <?= e($res['hora_inicio'] ?? '') ?> - <?= e($res['hora_fin'] ?? '') ?></span><br>
                                <span style="font-weight: 600; color: var(--color-acento);">$ <?= number_format((float) ($res['costo'] ?? 0), 2) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </article>
    </div>
</section>

<?php
require_once __DIR__ . '/../../views/layout/pie.php';
