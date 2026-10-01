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

$espacioSeleccionadoId = (int)($antiguo['espacio_id'] ?? ($espacios[0]?->getId() ?? 1));

$tituloPagina = 'Nueva Reserva';
require_once __DIR__ . '/../../views/layout/encabezado.php';
?>

<div style="margin-bottom: 1.5rem;">
    <a href="/reservas/index.php" class="btn btn-secundario" style="margin-bottom: 0.75rem; font-size: 0.85rem; padding: 0.35rem 0.75rem;">
        &larr; Volver a Reservas
    </a>
    <h1 style="margin: 0; font-size: 1.85rem;"><?= e($tituloPagina) ?></h1>
    <p style="margin: 0.25rem 0 0 0; color: var(--color-texto-mutado);">
        Selecciona un espacio y horario. El costo total se calcula automáticamente.
    </p>
</div>

<?php if (!empty($errores['general'])): ?>
    <div style="background-color: #fef2f2; border: 1px solid #f87171; color: #991b1b; padding: 0.85rem 1.15rem; border-radius: 8px; margin-bottom: 1.5rem;">
        <?= e($errores['general']) ?>
    </div>
<?php endif; ?>

<form id="formReserva" action="/reservas/guardar.php" method="POST">
    <div class="layout-reserva-grid">
        <!-- Columna Izquierda: Formulario Principal -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            <!-- 1. Selección visual del espacio -->
            <article class="panel" style="padding: 1.25rem;">
                <label style="font-size: 1rem; font-weight: 700; margin-bottom: 0.25rem;">1. Selecciona el Espacio</label>
                <p style="color: var(--color-texto-mutado); font-size: 0.85rem; margin-bottom: 0.75rem;">
                    Elige el espacio que deseas reservar:
                </p>

                <input type="hidden" name="espacio_id" id="inputEspacioId" value="<?= $espacioSeleccionadoId ?>">

                <div class="selector-espacios-grid">
                    <?php foreach ($espacios as $esp): ?>
                        <?php
                            $esActivo = ($esp->getId() === $espacioSeleccionadoId);
                            $tipoStr = mb_strtolower($esp->getTipo());
                            $icono = '🏢';
                            $tarifaTexto = '$180 / hora';
                            $tipoCode = 'sala';

                            if (str_contains($tipoStr, 'cancha')) {
                                $icono = '⚽';
                                $tarifaTexto = '$120 / bloque (60m)';
                                $tipoCode = 'cancha';
                            } elseif (str_contains($tipoStr, 'escritorio')) {
                                $icono = '💻';
                                $tarifaTexto = '$75 / hora';
                                $tipoCode = 'escritorio';
                            }
                        ?>
                        <div class="tarjeta-espacio-opt <?= $esActivo ? 'activa' : '' ?>"
                             data-id="<?= $esp->getId() ?>"
                             data-nombre="<?= e($esp->getNombre()) ?>"
                             data-tipo="<?= $tipoCode ?>"
                             data-tipo-legible="<?= e($esp->obtenerTipoLegible()) ?>"
                             data-capacidad="<?= $esp->getCapacidad() ?>"
                             data-tarifa-texto="<?= $tarifaTexto ?>">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-size: 1.35rem;"><?= $icono ?></span>
                                <span class="badge-tipo-chip"><?= e($esp->obtenerTipoLegible()) ?></span>
                            </div>
                            <strong style="font-size: 0.95rem; color: var(--color-secundario); margin-top: 0.25rem;">
                                <?= e($esp->getNombre()) ?>
                            </strong>
                            <div style="font-size: 0.8rem; color: var(--color-texto-mutado);">
                                Capacidad: <?= $esp->getCapacidad() ?> <?= $esp->getCapacidad() === 1 ? 'persona' : 'personas' ?>
                            </div>
                            <div style="font-size: 0.8rem; font-weight: 600; color: var(--color-acento); margin-top: auto;">
                                <?= $tarifaTexto ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (isset($errores['espacio_id'])): ?>
                    <div style="color: #dc2626; font-size: 0.8rem; margin-top: 0.4rem;"><?= e($errores['espacio_id']) ?></div>
                <?php endif; ?>
            </article>

            <!-- 2. Datos del Titular y Fecha -->
            <article class="panel" style="padding: 1.25rem;">
                <label style="font-size: 1rem; font-weight: 700; margin-bottom: 0.25rem;">2. Titular y Fecha</label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 0.75rem;">
                    <div class="grupo-campo" style="margin-bottom: 0;">
                        <label for="cliente">Nombre del Cliente / Titular *</label>
                        <input type="text" id="cliente" name="cliente" class="campo-control"
                               placeholder="Ej: Sofía Ramírez"
                               value="<?= e($antiguo['cliente'] ?? '') ?>" required>
                        <?php if (isset($errores['cliente'])): ?>
                            <div style="color: #dc2626; font-size: 0.8rem; margin-top: 0.25rem;"><?= e($errores['cliente']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="grupo-campo" style="margin-bottom: 0;">
                        <label for="fecha">Fecha de Reserva *</label>
                        <input type="date" id="fecha" name="fecha" class="campo-control"
                               value="<?= e($antiguo['fecha'] ?? date('Y-m-d')) ?>" required>
                        <?php if (isset($errores['fecha'])): ?>
                            <div style="color: #dc2626; font-size: 0.8rem; margin-top: 0.25rem;"><?= e($errores['fecha']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </article>

            <!-- 3. Horario y Atajos Rápidos -->
            <article class="panel" style="padding: 1.25rem;">
                <div style="display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 0.5rem;">
                    <label style="font-size: 1rem; font-weight: 700;">3. Horario de la Reserva</label>
                    <span style="font-size: 0.8rem; color: var(--color-texto-mutado);">
                        Horario pico: 14:00 a 19:00 hrs
                    </span>
                </div>

                <!-- Chips de horarios rápidos -->
                <div style="margin-top: 0.5rem; margin-bottom: 1rem;">
                    <div style="font-size: 0.8rem; color: var(--color-texto-mutado); margin-bottom: 0.35rem;">
                        Selección rápida:
                    </div>
                    <div class="chips-horario">
                        <button type="button" class="chip-btn" data-inicio="09:00" data-fin="11:00">Mañana (09:00 - 11:00)</button>
                        <button type="button" class="chip-btn" data-inicio="11:00" data-fin="13:00">Mediodía (11:00 - 13:00)</button>
                        <button type="button" class="chip-btn" data-inicio="14:00" data-fin="16:00">Tarde (14:00 - 16:00)</button>
                        <button type="button" class="chip-btn" data-inicio="16:00" data-fin="18:00">Tarde Pico (16:00 - 18:00)</button>
                        <button type="button" class="chip-btn" data-inicio="18:00" data-fin="20:00">Noche Pico (18:00 - 20:00)</button>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="grupo-campo" style="margin-bottom: 0;">
                        <label for="hora_inicio">Hora de Inicio *</label>
                        <input type="time" id="hora_inicio" name="hora_inicio" class="campo-control"
                               value="<?= e($antiguo['hora_inicio'] ?? '09:00') ?>" required>
                        <?php if (isset($errores['hora_inicio'])): ?>
                            <div style="color: #dc2626; font-size: 0.8rem; margin-top: 0.25rem;"><?= e($errores['hora_inicio']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="grupo-campo" style="margin-bottom: 0;">
                        <label for="hora_fin">Hora de Fin *</label>
                        <input type="time" id="hora_fin" name="hora_fin" class="campo-control"
                               value="<?= e($antiguo['hora_fin'] ?? '11:00') ?>" required>
                        <?php if (isset($errores['hora_fin'])): ?>
                            <div style="color: #dc2626; font-size: 0.8rem; margin-top: 0.25rem;"><?= e($errores['hora_fin']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        </div>

        <!-- Columna Derecha: Tarjeta de Resumen en Vivo -->
        <aside>
            <div class="tarjeta-resumen-flotante">
                <h3 style="margin: 0 0 1rem 0; font-size: 1.15rem; color: var(--color-secundario); border-bottom: 1px solid var(--color-borde); padding-bottom: 0.75rem;">
                    Resumen Estimado
                </h3>

                <div style="display: flex; flex-direction: column; gap: 0.85rem; font-size: 0.9rem;">
                    <div>
                        <div style="color: var(--color-texto-mutado); font-size: 0.8rem;">Espacio</div>
                        <strong id="resumenEspacioNombre" style="font-size: 1rem; color: var(--color-secundario);">-</strong>
                        <div id="resumenEspacioTipo" style="font-size: 0.8rem; color: var(--color-texto-mutado);">-</div>
                    </div>

                    <div style="display: flex; justify-content: space-between;">
                        <div>
                            <div style="color: var(--color-texto-mutado); font-size: 0.8rem;">Duración</div>
                            <strong id="resumenDuracion">-</strong>
                        </div>
                        <div style="text-align: right;">
                            <div style="color: var(--color-texto-mutado); font-size: 0.8rem;">Tipo de Horario</div>
                            <div id="resumenBadgePico">
                                <span class="badge-regular-smooth">Regular</span>
                            </div>
                        </div>
                    </div>

                    <div style="border-top: 1px dashed var(--color-borde); padding-top: 0.75rem;">
                        <div style="color: var(--color-texto-mutado); font-size: 0.8rem;">Detalle del Cálculo</div>
                        <div id="resumenDetalleCalculo" style="font-size: 0.85rem; color: #475569; margin-top: 0.2rem;">-</div>
                    </div>

                    <div style="background: #f8fafc; padding: 1rem; border-radius: 8px; border: 1px solid #e2e8f0; margin-top: 0.5rem;">
                        <div style="font-size: 0.8rem; color: var(--color-texto-mutado); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">
                            Total Estimado
                        </div>
                        <div id="resumenMontoTotal" style="font-size: 2.15rem; font-weight: 800; color: var(--color-secundario); line-height: 1.1; margin-top: 0.25rem;">
                            $0.00
                        </div>
                    </div>

                    <div id="alertaHorarioInvalido" style="display: none; color: #dc2626; font-size: 0.8rem; background: #fef2f2; padding: 0.5rem 0.75rem; border-radius: 6px; border: 1px solid #fca5a5;">
                        ⚠ La hora de fin debe ser posterior a la hora de inicio.
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.5rem;">
                        <button type="submit" id="btnSubmitReserva" class="btn btn-acento" style="width: 100%; padding: 0.75rem; font-size: 1rem; font-weight: 700; border-radius: 8px; justify-content: center;">
                            Confirmar Reserva &rarr;
                        </button>
                        <a href="/reservas/index.php" class="btn btn-secundario" style="width: 100%; text-align: center; justify-content: center; font-size: 0.85rem; padding: 0.45rem;">
                            Cancelar
                        </a>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const inputEspacioId = document.getElementById('inputEspacioId');
    const tarjetasEspacios = document.querySelectorAll('.tarjeta-espacio-opt');
    const inputHoraInicio = document.getElementById('hora_inicio');
    const inputHoraFin = document.getElementById('hora_fin');
    const chipsHorario = document.querySelectorAll('.chip-btn');
    const btnSubmit = document.getElementById('btnSubmitReserva');
    const alertaInvalido = document.getElementById('alertaHorarioInvalido');

    // Elementos del resumen
    const resumenNombre = document.getElementById('resumenEspacioNombre');
    const resumenTipo = document.getElementById('resumenEspacioTipo');
    const resumenDuracion = document.getElementById('resumenDuracion');
    const resumenBadgePico = document.getElementById('resumenBadgePico');
    const resumenDetalle = document.getElementById('resumenDetalleCalculo');
    const resumenTotal = document.getElementById('resumenMontoTotal');

    let espacioActual = null;

    // Manejo de clic en tarjetas de espacio
    tarjetasEspacios.forEach(card => {
        card.addEventListener('click', function () {
            tarjetasEspacios.forEach(c => c.classList.remove('activa'));
            this.classList.add('activa');
            inputEspacioId.value = this.dataset.id;
            actualizarSeleccionEspacio(this);
            recalcularResumen();
        });

        if (card.classList.contains('activa')) {
            actualizarSeleccionEspacio(card);
        }
    });

    function actualizarSeleccionEspacio(card) {
        espacioActual = {
            id: card.dataset.id,
            nombre: card.dataset.nombre,
            tipo: card.dataset.tipo,
            tipoLegible: card.dataset.tipoLegible,
            capacidad: card.dataset.capacidad,
            tarifaTexto: card.dataset.tarifaTexto
        };
        resumenNombre.textContent = espacioActual.nombre;
        resumenTipo.textContent = `${espacioActual.tipoLegible} (Cap: ${espacioActual.capacidad})`;
    }

    // Atajos de horario en chips
    chipsHorario.forEach(chip => {
        chip.addEventListener('click', function () {
            chipsHorario.forEach(c => c.classList.remove('activo'));
            this.classList.add('activo');
            inputHoraInicio.value = this.dataset.inicio;
            inputHoraFin.value = this.dataset.fin;
            recalcularResumen();
        });
    });

    // Escuchar cambios de hora
    inputHoraInicio.addEventListener('input', () => {
        chipsHorario.forEach(c => c.classList.remove('activo'));
        recalcularResumen();
    });
    inputHoraFin.addEventListener('input', () => {
        chipsHorario.forEach(c => c.classList.remove('activo'));
        recalcularResumen();
    });

    function recalcularResumen() {
        if (!espacioActual) return;

        const hIni = inputHoraInicio.value;
        const hFin = inputHoraFin.value;

        if (!hIni || !hFin) return;

        const [iH, iM] = hIni.split(':').map(Number);
        const [fH, fM] = hFin.split(':').map(Number);

        const minutosIni = iH * 60 + iM;
        const minutosFin = fH * 60 + fM;
        const duracionMin = minutosFin - minutosIni;

        if (duracionMin <= 0) {
            alertaInvalido.style.display = 'block';
            btnSubmit.disabled = true;
            btnSubmit.style.opacity = '0.5';
            resumenDuracion.textContent = 'Horario inválido';
            resumenTotal.textContent = '$0.00';
            resumenDetalle.textContent = 'Ajusta los horarios para calcular el valor.';
            return;
        }

        alertaInvalido.style.display = 'none';
        btnSubmit.disabled = false;
        btnSubmit.style.opacity = '1';

        const horas = duracionMin / 60.0;
        const esPico = (iH >= 14 && iH < 19);

        // Actualizar badge pico
        if (esPico) {
            resumenBadgePico.innerHTML = '<span class="badge-pico-smooth">⚡ Horario Pico</span>';
        } else {
            resumenBadgePico.innerHTML = '<span class="badge-regular-smooth">🌿 Tarifa Regular</span>';
        }

        resumenDuracion.textContent = `${horas.toFixed(horas % 1 === 0 ? 0 : 2)} hrs (${duracionMin} min)`;

        // Cálculo polimórfico en cliente acorde a las reglas del dominio
        let total = 0;
        let detalle = '';

        if (espacioActual.tipo === 'sala') {
            const base = horas * 180;
            if (esPico) {
                total = base * 1.25;
                detalle = `${horas.toFixed(2)}h × $180/h + 25% recargo pico`;
            } else {
                total = base;
                detalle = `${horas.toFixed(2)}h × $180/h`;
            }
        } else if (espacioActual.tipo === 'cancha') {
            const bloques = Math.ceil(duracionMin / 60);
            const base = bloques * 120;
            if (esPico) {
                const recargo = bloques * 35;
                total = base + recargo;
                detalle = `${bloques} bloque(s) × $120 + ${bloques} × $35 recargo pico`;
            } else {
                total = base;
                detalle = `${bloques} bloque(s) × $120`;
            }
        } else {
            // Escritorio individual
            total = horas * 75;
            detalle = `${horas.toFixed(2)}h × $75/h tarifa plana`;
        }

        resumenDetalle.textContent = detalle;
        resumenTotal.textContent = `$${total.toFixed(2)}`;
    }

    // Inicializar cálculo en carga
    recalcularResumen();
});
</script>

<?php
require_once __DIR__ . '/../../views/layout/pie.php';
