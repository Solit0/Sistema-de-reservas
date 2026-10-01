<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database\Conexion;
use App\Domain\Espacios\Cancha;
use App\Domain\Espacios\EscritorioIndividual;
use App\Domain\Espacios\SalaReunion;
use App\Repositories\EspacioRepositorio;
use App\Services\ReservaStorageService;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('e')) {
    function e(?string $valor): string
    {
        return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
    }
}

$errores = $_SESSION['errores'] ?? [];
$antiguo = $_SESSION['antiguo'] ?? [];
unset($_SESSION['errores'], $_SESSION['antiguo']);

$espacios = [];

if (class_exists(Conexion::class) && file_exists(__DIR__ . '/../../config/config.php')) {
    try {
        $pdo = Conexion::obtener();
        $espacioRepo = new EspacioRepositorio($pdo);
        $espacios = $espacioRepo->listar();
    } catch (\Throwable) {
    }
}

if (empty($espacios)) {
    $rutaJson = __DIR__ . '/../../reservas.json';
    if (file_exists($rutaJson)) {
        try {
            $storage = new ReservaStorageService();
            $datosJson = $storage->leerDeJson($rutaJson);
            $idx = 1;
            foreach ($datosJson as $item) {
                $tipoNormalizado = mb_strtolower((string) ($item['tipo'] ?? ''));
                $nombre = (string) ($item['espacio'] ?? 'Espacio');
                $capacidad = (int) ($item['capacidad'] ?? 1);

                if (str_contains($tipoNormalizado, 'cancha')) {
                    $espacios[] = new Cancha($nombre, $capacidad, null, $idx++);
                } elseif (str_contains($tipoNormalizado, 'sala')) {
                    $espacios[] = new SalaReunion($nombre, $capacidad, null, $idx++);
                } elseif (str_contains($tipoNormalizado, 'escritorio')) {
                    $espacios[] = new EscritorioIndividual($nombre, $capacidad, null, $idx++);
                }
            }
        } catch (\Throwable) {
        }
    }
}

if (empty($espacios)) {
    $espacios = [
        new SalaReunion('Sala de Juntas Principal', 8, null, 1),
        new Cancha('Cancha Central Sintética', 10, null, 2),
        new EscritorioIndividual('Escritorio Individual 01', 1, null, 3),
    ];
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
    .alerta-formulario {
        background-color: #fee2e2;
        border: 1px solid #ef4444;
        color: #991b1b;
        padding: 0.85rem 1rem;
        border-radius: 6px;
        margin-bottom: 1.25rem;
        font-size: 0.9rem;
    }
    .error-campo {
        color: #dc2626;
        font-size: 0.8rem;
        margin-top: 0.25rem;
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

    <?php if (!empty($errores['general'])): ?>
        <div class="alerta-formulario">
            <?= e($errores['general']) ?>
        </div>
    <?php endif; ?>

    <article class="panel">
        <form action="/reservas/guardar.php" method="POST" class="formulario">
            <div class="grupo-campo">
                <label for="cliente">Nombre del Cliente / Titular *</label>
                <input type="text" id="cliente" name="cliente" class="campo-control"
                       placeholder="Ej: María López"
                       value="<?= e($antiguo['cliente'] ?? '') ?>" required>
                <?php if (isset($errores['cliente'])): ?>
                    <div class="error-campo"><?= e($errores['cliente']) ?></div>
                <?php endif; ?>
            </div>

            <div class="grupo-campo">
                <label for="espacio_id">Espacio a Reservar *</label>
                <select id="espacio_id" name="espacio_id" class="campo-control" required>
                    <option value="">-- Selecciona un espacio --</option>
                    <?php foreach ($espacios as $esp): ?>
                        <?php $selected = ((int)($antiguo['espacio_id'] ?? 0) === $esp->getId()) ? 'selected' : ''; ?>
                        <option value="<?= $esp->getId() ?>" <?= $selected ?>>
                            <?= e($esp->getNombre()) ?> (<?= e($esp->obtenerTipoLegible()) ?> - Cap: <?= $esp->getCapacidad() ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errores['espacio_id'])): ?>
                    <div class="error-campo"><?= e($errores['espacio_id']) ?></div>
                <?php endif; ?>
            </div>

            <div class="grupo-campo">
                <label for="fecha">Fecha de Reserva *</label>
                <input type="date" id="fecha" name="fecha" class="campo-control"
                       value="<?= e($antiguo['fecha'] ?? date('Y-m-d')) ?>" required>
                <?php if (isset($errores['fecha'])): ?>
                    <div class="error-campo"><?= e($errores['fecha']) ?></div>
                <?php endif; ?>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="grupo-campo">
                    <label for="hora_inicio">Hora de Inicio *</label>
                    <input type="time" id="hora_inicio" name="hora_inicio" class="campo-control"
                           value="<?= e($antiguo['hora_inicio'] ?? '09:00') ?>" required>
                    <?php if (isset($errores['hora_inicio'])): ?>
                        <div class="error-campo"><?= e($errores['hora_inicio']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="grupo-campo">
                    <label for="hora_fin">Hora de Fin *</label>
                    <input type="time" id="hora_fin" name="hora_fin" class="campo-control"
                           value="<?= e($antiguo['hora_fin'] ?? '11:00') ?>" required>
                    <?php if (isset($errores['hora_fin'])): ?>
                        <div class="error-campo"><?= e($errores['hora_fin']) ?></div>
                    <?php endif; ?>
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
