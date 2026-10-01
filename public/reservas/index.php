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
                $tipoEspacio = $esp['tipo'] ?? 'general';
                foreach (($esp['reservas'] ?? []) as $r) {
                    $reservas[] = [
                        'id'             => $r['id'] ?? 0,
                        'cliente'        => $r['titular'] ?? 'Cliente General',
                        'espacio_nombre' => $nombreEspacio,
                        'espacio_tipo'   => $tipoEspacio,
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

// Estadísticas rápidas
$totalMonto = 0.0;
$conteoPorEspacio = [];
foreach ($reservas as $res) {
    $totalMonto += (float)($res['monto_total'] ?? 0);
    $espNombre = (string)($res['espacio_nombre'] ?? 'General');
    $conteoPorEspacio[$espNombre] = ($conteoPorEspacio[$espNombre] ?? 0) + 1;
}
arsort($conteoPorEspacio);
$espacioMasPopular = key($conteoPorEspacio) ?? 'N/A';

$tituloPagina = 'Listado de Reservas';
require_once __DIR__ . '/../../views/layout/encabezado.php';
?>

<section class="catalogo-reservas">
    <!-- Encabezado de página -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid var(--color-borde); padding-bottom: 1rem; flex-wrap: wrap; gap: 0.75rem;">
        <div>
            <h1 style="margin: 0; font-size: 1.85rem;"><?= e($tituloPagina) ?></h1>
            <p style="margin: 0.25rem 0 0 0; color: var(--color-texto-mutado); font-size: 0.9rem;">
                Supervisión de agendas y estado de cobros en tiempo real.
            </p>
        </div>
        <div>
            <a href="/reservas/crear.php" class="btn btn-acento" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.55rem 1rem; font-weight: 600;">
                <span>+</span> Nueva Reserva
            </a>
        </div>
    </div>

    <!-- Mini KPIs Estadísticos -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <div class="panel" style="padding: 1rem; display: flex; align-items: center; gap: 1rem;">
            <div style="width: 44px; height: 44px; border-radius: 10px; background: #eff6ff; color: var(--color-acento); display: flex; align-items: center; justify-content: center;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
            </div>
            <div>
                <div style="font-size: 0.78rem; color: var(--color-texto-mutado); text-transform: uppercase; font-weight: 600;">Reservas Activas</div>
                <div style="font-size: 1.4rem; font-weight: 800; color: var(--color-secundario);"><?= count($reservas) ?></div>
            </div>
        </div>

        <div class="panel" style="padding: 1rem; display: flex; align-items: center; gap: 1rem;">
            <div style="width: 44px; height: 44px; border-radius: 10px; background: #f0fdf4; color: #16a34a; display: flex; align-items: center; justify-content: center;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" x2="12" y1="2" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
            <div>
                <div style="font-size: 0.78rem; color: var(--color-texto-mutado); text-transform: uppercase; font-weight: 600;">Recaudación Total</div>
                <div style="font-size: 1.4rem; font-weight: 800; color: #15803d;">$<?= number_format($totalMonto, 2) ?></div>
            </div>
        </div>

        <div class="panel" style="padding: 1rem; display: flex; align-items: center; gap: 1rem;">
            <div style="width: 44px; height: 44px; border-radius: 10px; background: #faf5ff; color: #9333ea; display: flex; align-items: center; justify-content: center;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>
            </div>
            <div>
                <div style="font-size: 0.78rem; color: var(--color-texto-mutado); text-transform: uppercase; font-weight: 600;">Más Demandado</div>
                <div style="font-size: 1rem; font-weight: 700; color: var(--color-secundario);"><?= e($espacioMasPopular) ?></div>
            </div>
        </div>
    </div>

    <!-- Barra de Filtros y Búsqueda en Vivo -->
    <div class="barra-filtro-reservas">
        <input type="text" id="buscadorReservas" class="buscador-input" placeholder="Buscar por cliente o espacio...">
        
        <div class="filtros-tipo-chips">
            <button type="button" class="filtro-chip-btn activo" data-filtro="todos">Todas</button>
            <button type="button" class="filtro-chip-btn" data-filtro="sala">Salas</button>
            <button type="button" class="filtro-chip-btn" data-filtro="cancha">Canchas</button>
            <button type="button" class="filtro-chip-btn" data-filtro="escritorio">Escritorios</button>
            <button type="button" class="filtro-chip-btn" data-filtro="pico">Horario Pico</button>
        </div>
    </div>

    <article class="panel">
        <?php if (empty($reservas)): ?>
            <div style="text-align: center; padding: 3.5rem 1rem;">
                <div style="color: var(--color-texto-mutado); margin-bottom: 0.75rem; display: flex; justify-content: center;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                </div>
                <p style="color: var(--color-texto-mutado); font-size: 1.1rem; margin-bottom: 1.25rem;">
                    No hay reservas registradas en este momento.
                </p>
                <a href="/reservas/crear.php" class="btn btn-primario">
                    Crear la primera reserva
                </a>
            </div>
        <?php else: ?>
            <div class="tabla-responsive">
                <table class="tabla-datos" id="tablaReservas">
                    <thead>
                        <tr>
                            <th>Cliente / Titular</th>
                            <th>Espacio</th>
                            <th>Fecha</th>
                            <th>Horario</th>
                            <th>Tarifa</th>
                            <th style="text-align: right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservas as $reserva): ?>
                            <?php
                                $horaH = (int)substr((string)$reserva['hora_inicio'], 0, 2);
                                $esPico = !empty($reserva['es_pico']) || ($horaH >= 14 && $horaH < 19);
                                $tipoStr = mb_strtolower((string)($reserva['espacio_tipo'] ?? ''));
                                $filtroTipo = 'general';
                                if (str_contains($tipoStr, 'sala')) $filtroTipo = 'sala';
                                elseif (str_contains($tipoStr, 'cancha')) $filtroTipo = 'cancha';
                                elseif (str_contains($tipoStr, 'escritorio')) $filtroTipo = 'escritorio';
                            ?>
                            <tr class="fila-reserva"
                                data-cliente="<?= strtolower(e((string)$reserva['cliente'])) ?>"
                                data-espacio="<?= strtolower(e((string)$reserva['espacio_nombre'])) ?>"
                                data-tipo="<?= $filtroTipo ?>"
                                data-pico="<?= $esPico ? '1' : '0' ?>">
                                <td>
                                    <strong><?= e((string) $reserva['cliente']) ?></strong>
                                </td>
                                <td>
                                    <span><?= e((string) $reserva['espacio_nombre']) ?></span>
                                </td>
                                <td>
                                    <span style="font-family: var(--fuente-mono); font-size: 0.9rem;"><?= e((string) $reserva['fecha']) ?></span>
                                </td>
                                <td>
                                    <span style="font-family: var(--fuente-mono); font-size: 0.9rem;"><?= e((string) $reserva['hora_inicio']) ?> - <?= e((string) $reserva['hora_fin']) ?></span>
                                </td>
                                <td>
                                    <?php if ($esPico): ?>
                                        <span class="badge-pico-smooth">Horario Pico</span>
                                    <?php else: ?>
                                        <span class="badge-regular-smooth">Regular</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <strong style="color: var(--color-secundario); font-size: 1.05rem;">
                                        $<?= number_format((float) $reserva['monto_total'], 2) ?>
                                    </strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div id="sinResultadosBusqueda" style="display: none; text-align: center; padding: 2rem; color: var(--color-texto-mutado);">
                No se encontraron reservas que coincidan con la búsqueda.
            </div>
        <?php endif; ?>
    </article>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const inputBuscador = document.getElementById('buscadorReservas');
    const botonesFiltro = document.querySelectorAll('.filtro-chip-btn');
    const filas = document.querySelectorAll('.fila-reserva');
    const avisoSinResultados = document.getElementById('sinResultadosBusqueda');

    if (!filas.length) return;

    let filtroActivo = 'todos';
    let terminoBusqueda = '';

    function aplicarFiltros() {
        let visibles = 0;

        filas.forEach(fila => {
            const cliente = fila.dataset.cliente || '';
            const espacio = fila.dataset.espacio || '';
            const tipo = fila.dataset.tipo || '';
            const esPico = fila.dataset.pico === '1';

            const coincideTexto = !terminoBusqueda || cliente.includes(terminoBusqueda) || espacio.includes(terminoBusqueda);
            let coincideTipo = true;

            if (filtroActivo === 'pico') {
                coincideTipo = esPico;
            } else if (filtroActivo !== 'todos') {
                coincideTipo = (tipo === filtroActivo);
            }

            if (coincideTexto && coincideTipo) {
                fila.style.display = '';
                visibles++;
            } else {
                fila.style.display = 'none';
            }
        });

        if (avisoSinResultados) {
            avisoSinResultados.style.display = (visibles === 0) ? 'block' : 'none';
        }
    }

    if (inputBuscador) {
        inputBuscador.addEventListener('input', function () {
            terminoBusqueda = this.value.toLowerCase().trim();
            aplicarFiltros();
        });
    }

    botonesFiltro.forEach(btn => {
        btn.addEventListener('click', function () {
            botonesFiltro.forEach(b => b.classList.remove('activo'));
            this.classList.add('activo');
            filtroActivo = this.dataset.filtro;
            aplicarFiltros();
        });
    });
});
</script>

<?php
require_once __DIR__ . '/../../views/layout/pie.php';
