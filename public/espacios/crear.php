<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * Sistema de Reservas - Registrar Nuevo Espacio
 * ==============================================================================
 * Formulario para el registro de nuevos espacios en el sistema.
 * Implementa protección CSRF, novalidate para evaluación en servidor,
 * persistencia de valores ingresados ($old) y renderizado de errores ($errores)
 * con clases semánticas (.campo-error, .mensaje-error).
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Security\Csrf;

// Iniciar sesión si aún no está activa para gestionar CSRF y mensajes flash/errores
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// [SEGURIDAD] Escape seguro contra XSS
if (!function_exists('e')) {
    function e(?string $valor): string
    {
        return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// Recuperar errores y valores antiguos de la sesión (patrón Flash)
$errores = $_SESSION['errores'] ?? [];
$old = $_SESSION['old'] ?? [];
unset($_SESSION['errores'], $_SESSION['old']);

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

    .seccion-especifica {
        background-color: var(--color-fondo);
        border: 1px dashed var(--color-borde);
        border-radius: var(--radio-md);
        padding: 1rem;
        margin-bottom: 1.25rem;
    }

    .seccion-especifica h3 {
        font-size: 1rem;
        margin-bottom: 0.75rem;
        color: var(--color-secundario);
    }

    .checkbox-inline {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        cursor: pointer;
    }

    .checkbox-inline input[type="checkbox"] {
        width: 1.15rem;
        height: 1.15rem;
        cursor: pointer;
    }

    .galeria-presets {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .tarjeta-preset {
        border: 2px solid var(--color-borde);
        border-radius: var(--radio-md, 8px);
        overflow: hidden;
        cursor: pointer;
        background: #ffffff;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.15s ease;
        position: relative;
    }

    .tarjeta-preset:hover {
        border-color: var(--color-primario, #2563eb);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
    }

    .tarjeta-preset.activa {
        border-color: var(--color-acento, #2563eb) !important;
        box-shadow: 0 0 0 1px var(--color-acento, #2563eb);
    }

    .tarjeta-preset img {
        width: 100%;
        height: 90px;
        object-fit: cover;
        display: block;
    }

    .tarjeta-preset-info {
        padding: 0.5rem 0.65rem;
        font-size: 0.82rem;
        font-weight: 600;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #ffffff;
    }

    .tarjeta-preset .preset-check {
        color: var(--color-acento, #2563eb);
        display: none;
    }

    .tarjeta-preset.activa .preset-check {
        display: inline-flex;
        align-items: center;
    }

    .zona-arrastre {
        border: 2px dashed #94a3b8;
        border-radius: var(--radio-md, 8px);
        padding: 1.5rem 1rem;
        text-align: center;
        background: #f8fafc;
        cursor: pointer;
        transition: border-color 0.2s ease, background-color 0.2s ease;
        position: relative;
    }

    .zona-arrastre:hover,
    .zona-arrastre.arrastre-activo {
        border-color: var(--color-acento, #2563eb);
        background: #eff6ff;
    }
</style>

<section class="formulario-contenedor" style="max-width: 680px; margin: 0 auto;">
    <div style="margin-bottom: 1.5rem;">
        <a href="/espacios/index.php" class="btn btn-secundario" style="margin-bottom: 0.75rem; font-size: 0.85rem; padding: 0.35rem 0.75rem;">
            &larr; Volver al Catálogo
        </a>
        <h1 style="margin: 0; font-size: 1.85rem;"><?= e($tituloPagina) ?></h1>
        <p style="margin: 0.25rem 0 0 0; color: var(--color-texto-mutado);">
            Completa la información para dar de alta un nuevo espacio en el catálogo.
        </p>
    </div>

    <?php if (isset($errores['general'])): ?>
        <div class="alerta alerta-error">
            <?= e($errores['general']) ?>
        </div>
    <?php endif; ?>

    <article class="panel">
        <form action="/espacios/guardar.php" method="POST" enctype="multipart/form-data" novalidate class="formulario">
            <!-- [SEGURIDAD] Token de protección contra ataques CSRF -->
            <?= Csrf::campoHtml() ?>

            <!-- Campo Nombre -->
            <div class="grupo-campo">
                <label for="nombre">Nombre del Espacio *</label>
                <input 
                    type="text" 
                    id="nombre" 
                    name="nombre" 
                    class="campo-control <?= isset($errores['nombre']) ? 'campo-error' : '' ?>" 
                    placeholder="Ej: Sala de Juntas VIP" 
                    value="<?= e($old['nombre'] ?? '') ?>"
                    required
                >
                <?php if (isset($errores['nombre'])): ?>
                    <small class="mensaje-error"><?= e($errores['nombre']) ?></small>
                <?php endif; ?>
            </div>

            <!-- Campo Tipo (Discriminador Single Table Inheritance) -->
            <div class="grupo-campo">
                <label for="tipo">Tipo de Espacio *</label>
                <select 
                    id="tipo" 
                    name="tipo" 
                    class="campo-control <?= isset($errores['tipo']) ? 'campo-error' : '' ?>" 
                    required
                >
                    <option value="">-- Selecciona una categoría --</option>
                    <option value="cancha" <?= ($old['tipo'] ?? '') === 'cancha' ? 'selected' : '' ?>>Cancha Deportiva</option>
                    <option value="sala" <?= ($old['tipo'] ?? '') === 'sala' ? 'selected' : '' ?>>Sala de Reunión</option>
                    <option value="escritorio" <?= ($old['tipo'] ?? '') === 'escritorio' ? 'selected' : '' ?>>Escritorio Individual</option>
                </select>
                <?php if (isset($errores['tipo'])): ?>
                    <small class="mensaje-error"><?= e($errores['tipo']) ?></small>
                <?php endif; ?>
            </div>

            <!-- Campos específicos para Cancha -->
            <div id="campos-cancha" class="seccion-especifica" style="display: none;">
                <h3>Características de la Cancha</h3>
                <div class="grupo-campo">
                    <label for="tipo_grama">Tipo de Superficie / Grama</label>
                    <select id="tipo_grama" name="tipo_grama" class="campo-control <?= isset($errores['tipo_grama']) ? 'campo-error' : '' ?>">
                        <option value="">-- Selecciona superficie --</option>
                        <option value="Sintética" <?= ($old['tipo_grama'] ?? '') === 'Sintética' ? 'selected' : '' ?>>Sintética</option>
                        <option value="Natural" <?= ($old['tipo_grama'] ?? '') === 'Natural' ? 'selected' : '' ?>>Natural</option>
                    </select>
                    <?php if (isset($errores['tipo_grama'])): ?>
                        <small class="mensaje-error"><?= e($errores['tipo_grama']) ?></small>
                    <?php endif; ?>
                </div>

                <div class="grupo-campo">
                    <label class="checkbox-inline">
                        <input type="checkbox" id="iluminacion_nocturna" name="iluminacion_nocturna" value="1" <?= !empty($old['iluminacion_nocturna']) ? 'checked' : '' ?>>
                        <span>Cuenta con iluminación nocturna (+ disponible en horario vespertino)</span>
                    </label>
                </div>
            </div>

            <!-- Campos específicos para Sala de Reunión -->
            <div id="campos-sala" class="seccion-especifica" style="display: none;">
                <h3>Equipamiento de la Sala</h3>
                <div class="grupo-campo">
                    <label class="checkbox-inline">
                        <input type="checkbox" id="tiene_proyector" name="tiene_proyector" value="1" <?= !empty($old['tiene_proyector']) ? 'checked' : '' ?>>
                        <span>Incluye proyector audiovisual HD y pantalla retráctil</span>
                    </label>
                </div>
            </div>

            <!-- Campos específicos para Escritorio Individual -->
            <div id="campos-escritorio" class="seccion-especifica" style="display: none;">
                <h3>Equipamiento del Escritorio</h3>
                <div class="grupo-campo">
                    <label class="checkbox-inline">
                        <input type="checkbox" id="tiene_computadora" name="tiene_computadora" value="1" <?= !empty($old['tiene_computadora']) ? 'checked' : '' ?>>
                        <span>Incluye equipo de cómputo de sobremesa / monitor conectado</span>
                    </label>
                </div>
            </div>

            <!-- Campo Capacidad -->
            <div class="grupo-campo">
                <label for="capacidad">Capacidad Máxima (personas) *</label>
                <input 
                    type="number" 
                    id="capacidad" 
                    name="capacidad" 
                    class="campo-control <?= isset($errores['capacidad']) ? 'campo-error' : '' ?>" 
                    min="1" 
                    placeholder="Ej: 8" 
                    value="<?= e((string) ($old['capacidad'] ?? '')) ?>"
                    required
                >
                <?php if (isset($errores['capacidad'])): ?>
                    <small class="mensaje-error"><?= e($errores['capacidad']) ?></small>
                <?php endif; ?>
            </div>

            <!-- Campo Tarifa Base -->
            <div class="grupo-campo">
                <label for="tarifa_base">Tarifa Base por Hora ($) *</label>
                <input 
                    type="number" 
                    step="0.01" 
                    id="tarifa_base" 
                    name="tarifa_base" 
                    class="campo-control <?= isset($errores['tarifa_base']) ? 'campo-error' : '' ?>" 
                    min="0.01" 
                    placeholder="Ej: 150.00" 
                    value="<?= e((string) ($old['tarifa_base'] ?? '')) ?>"
                    required
                >
                <?php if (isset($errores['tarifa_base'])): ?>
                    <small class="mensaje-error"><?= e($errores['tarifa_base']) ?></small>
                <?php endif; ?>
            </div>

            <!-- Sección de Fotografía (Presets Rápidos y Subida Personalizada) -->
            <div class="grupo-campo" style="margin-top: 1.5rem; border-top: 1px solid var(--color-borde); padding-top: 1.25rem;">
                <label style="font-size: 1rem; font-weight: 700; margin-bottom: 0.25rem; display: block;">
                    Fotografía del Espacio
                </label>
                <p style="color: var(--color-texto-mutado); font-size: 0.85rem; margin: 0 0 0.85rem 0;">
                    Selecciona una fotografía predeterminada con un clic o sube una imagen personalizada desde tu equipo.
                </p>

                <!-- Input oculto para transmitir el preset seleccionado -->
                <input 
                    type="hidden" 
                    name="preset_imagen" 
                    id="inputPresetImagen" 
                    value="<?= e($old['preset_imagen'] ?? '') ?>"
                >

                <!-- 1. Galería de Presets Rápidos -->
                <div style="font-size: 0.82rem; font-weight: 600; color: var(--color-secundario); margin-bottom: 0.45rem;">
                    Opciones Rápidas Preconfiguradas:
                </div>
                <div class="galeria-presets">
                    <div class="tarjeta-preset" data-preset="sala_ejecutiva.png" data-tipo="sala" title="Seleccionar fotografía de Sala Ejecutiva">
                        <img src="/img/presets/sala_ejecutiva.png" alt="Sala Ejecutiva">
                        <div class="tarjeta-preset-info">
                            <span>Sala Ejecutiva</span>
                            <span class="preset-check">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                            </span>
                        </div>
                    </div>

                    <div class="tarjeta-preset" data-preset="cancha_sintetica.png" data-tipo="cancha" title="Seleccionar fotografía de Cancha Sintética">
                        <img src="/img/presets/cancha_sintetica.png" alt="Cancha Sintética">
                        <div class="tarjeta-preset-info">
                            <span>Cancha Sintética</span>
                            <span class="preset-check">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                            </span>
                        </div>
                    </div>

                    <div class="tarjeta-preset" data-preset="escritorio_individual.png" data-tipo="escritorio" title="Seleccionar fotografía de Escritorio Coworking">
                        <img src="/img/presets/escritorio_individual.png" alt="Escritorio Coworking">
                        <div class="tarjeta-preset-info">
                            <span>Escritorio Coworking</span>
                            <span class="preset-check">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- 2. Zona Drag & Drop para Subida de Archivo Propio -->
                <div style="font-size: 0.82rem; font-weight: 600; color: var(--color-secundario); margin-bottom: 0.45rem;">
                    O subir imagen propia desde tu dispositivo:
                </div>
                <div id="dropzone" class="zona-arrastre">
                    <input 
                        type="file" 
                        id="inputArchivoImagen" 
                        name="imagen" 
                        class="<?= isset($errores['imagen']) ? 'campo-error' : '' ?>" 
                        accept="image/jpeg,image/png,image/webp" 
                        style="display: none;"
                    >
                    
                    <div id="dropzoneContent">
                        <div style="color: #64748b; margin-bottom: 0.4rem; display: flex; justify-content: center;">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                        </div>
                        <div style="font-size: 0.92rem; font-weight: 600; color: var(--color-secundario);">
                            Arrastra una imagen aquí o <span style="color: var(--color-acento); text-decoration: underline;">haz clic para explorar</span>
                        </div>
                        <div style="font-size: 0.78rem; color: var(--color-texto-mutado); margin-top: 0.25rem;">
                            Formatos permitidos: JPG, PNG o WEBP (máximo 3 MB)
                        </div>
                    </div>

                    <!-- Vista previa reactiva de archivo cargado -->
                    <div id="previewContainer" style="display: none; align-items: center; justify-content: center; gap: 1rem; flex-wrap: wrap;">
                        <img id="imgPreview" src="" alt="Vista previa" style="width: 120px; height: 80px; object-fit: cover; border-radius: 6px; border: 1px solid var(--color-borde); box-shadow: 0 2px 6px rgba(0,0,0,0.1);">
                        <div style="text-align: left;">
                            <strong id="previewFileName" style="font-size: 0.88rem; color: var(--color-secundario); display: block;">archivo.jpg</strong>
                            <span id="previewFileSize" style="font-size: 0.78rem; color: var(--color-texto-mutado);">1.2 MB</span>
                            <div style="margin-top: 0.35rem;">
                                <button type="button" id="btnQuitarImagen" style="background: #fee2e2; border: 1px solid #fca5a5; color: #b91c1c; padding: 0.25rem 0.65rem; border-radius: 4px; font-size: 0.75rem; cursor: pointer; font-weight: 600;">
                                    &times; Quitar archivo y usar preset
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (isset($errores['imagen'])): ?>
                    <small class="mensaje-error" style="display: block; margin-top: 0.35rem;"><?= e($errores['imagen']) ?></small>
                <?php endif; ?>
            </div>

            <!-- Acciones del Formulario -->
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; border-top: 1px solid var(--color-borde); padding-top: 1.25rem;">
                <a href="/espacios/index.php" class="btn btn-secundario">Cancelar</a>
                <button type="submit" class="btn btn-acento" style="padding: 0.65rem 1.5rem; font-weight: 700;">
                    Guardar Espacio &rarr;
                </button>
            </div>
        </form>
    </article>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tipoSelect = document.getElementById('tipo');
    const camposCancha = document.getElementById('campos-cancha');
    const camposSala = document.getElementById('campos-sala');
    const camposEscritorio = document.getElementById('campos-escritorio');

    const inputPreset = document.getElementById('inputPresetImagen');
    const tarjetasPreset = document.querySelectorAll('.tarjeta-preset');
    const inputTarifa = document.getElementById('tarifa_base');
    const inputCapacidad = document.getElementById('capacidad');

    const dropzone = document.getElementById('dropzone');
    const inputArchivo = document.getElementById('inputArchivoImagen');
    const dropzoneContent = document.getElementById('dropzoneContent');
    const previewContainer = document.getElementById('previewContainer');
    const imgPreview = document.getElementById('imgPreview');
    const previewFileName = document.getElementById('previewFileName');
    const previewFileSize = document.getElementById('previewFileSize');
    const btnQuitar = document.getElementById('btnQuitarImagen');

    function sincronizarCamposDinamicos() {
        const valor = tipoSelect ? tipoSelect.value : '';

        if (camposCancha) {
            camposCancha.style.display = valor === 'cancha' ? 'block' : 'none';
        }
        if (camposSala) {
            camposSala.style.display = valor === 'sala' ? 'block' : 'none';
        }
        if (camposEscritorio) {
            camposEscritorio.style.display = valor === 'escritorio' ? 'block' : 'none';
        }
    }

    function seleccionarPreset(presetName) {
        if (!presetName) {
            if (inputPreset) inputPreset.value = '';
            tarjetasPreset.forEach(c => c.classList.remove('activa'));
            return;
        }

        if (inputPreset) inputPreset.value = presetName;
        tarjetasPreset.forEach(c => {
            const esEste = (c.dataset.preset === presetName);
            c.classList.toggle('activa', esEste);
        });
    }

    function limpiarArchivoManual() {
        if (inputArchivo) inputArchivo.value = '';
        if (dropzoneContent) dropzoneContent.style.display = 'block';
        if (previewContainer) previewContainer.style.display = 'none';
    }

    function autoCompletarValoresSugeridos(tipo) {
        if (tipo === 'sala') {
            if (inputTarifa && (!inputTarifa.value || inputTarifa.value === '0')) inputTarifa.value = '180.00';
            if (inputCapacidad && (!inputCapacidad.value || inputCapacidad.value === '0')) inputCapacidad.value = '8';
        } else if (tipo === 'cancha') {
            if (inputTarifa && (!inputTarifa.value || inputTarifa.value === '0')) inputTarifa.value = '120.00';
            if (inputCapacidad && (!inputCapacidad.value || inputCapacidad.value === '0')) inputCapacidad.value = '10';
        } else if (tipo === 'escritorio') {
            if (inputTarifa && (!inputTarifa.value || inputTarifa.value === '0')) inputTarifa.value = '75.00';
            if (inputCapacidad && (!inputCapacidad.value || inputCapacidad.value === '0')) inputCapacidad.value = '1';
        }
    }

    // 1. Interacción con tarjetas de Presets
    tarjetasPreset.forEach(card => {
        card.addEventListener('click', function () {
            const preset = this.dataset.preset;
            const tipo = this.dataset.tipo;

            seleccionarPreset(preset);
            limpiarArchivoManual();

            // Sincronizar selector tipo si difiere
            if (tipoSelect && tipo && tipoSelect.value !== tipo) {
                tipoSelect.value = tipo;
                sincronizarCamposDinamicos();
                autoCompletarValoresSugeridos(tipo);
            }
        });
    });

    // Sincronización al cambiar el desplegable de tipo
    if (tipoSelect) {
        tipoSelect.addEventListener('change', function () {
            sincronizarCamposDinamicos();
            const val = this.value;
            autoCompletarValoresSugeridos(val);

            // Si el usuario no subió archivo manual propio, sugerir preset del tipo
            const archivoCargado = inputArchivo && inputArchivo.files && inputArchivo.files.length > 0;
            if (!archivoCargado) {
                if (val === 'sala') seleccionarPreset('sala_ejecutiva.png');
                else if (val === 'cancha') seleccionarPreset('cancha_sintetica.png');
                else if (val === 'escritorio') seleccionarPreset('escritorio_individual.png');
            }
        });
    }

    // 2. Drag & Drop y Selección manual de imagen
    if (dropzone && inputArchivo) {
        dropzone.addEventListener('click', (e) => {
            if (e.target !== btnQuitar && !btnQuitar?.contains(e.target)) {
                inputArchivo.click();
            }
        });

        ['dragenter', 'dragover'].forEach(evt => {
            dropzone.addEventListener(evt, (e) => {
                e.preventDefault();
                dropzone.classList.add('arrastre-activo');
            });
        });

        ['dragleave', 'drop'].forEach(evt => {
            dropzone.addEventListener(evt, (e) => {
                e.preventDefault();
                dropzone.classList.remove('arrastre-activo');
            });
        });

        dropzone.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if (files && files.length > 0) {
                inputArchivo.files = files;
                mostrarVistaPrevia(files[0]);
            }
        });

        inputArchivo.addEventListener('change', function () {
            if (this.files && this.files.length > 0) {
                mostrarVistaPrevia(this.files[0]);
            }
        });
    }

    function mostrarVistaPrevia(file) {
        if (!file.type.startsWith('image/')) {
            alert('Por favor selecciona un archivo de imagen válido (JPG, PNG o WEBP).');
            return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
            imgPreview.src = e.target.result;
            previewFileName.textContent = file.name;
            const sizeKB = (file.size / 1024).toFixed(1);
            previewFileSize.textContent = Number(sizeKB) > 1024 ? `${(Number(sizeKB) / 1024).toFixed(2)} MB` : `${sizeKB} KB`;

            dropzoneContent.style.display = 'none';
            previewContainer.style.display = 'flex';

            // Desmarcar presets porque se usa imagen manual
            seleccionarPreset('');
        };
        reader.readAsDataURL(file);
    }

    if (btnQuitar) {
        btnQuitar.addEventListener('click', (e) => {
            e.stopPropagation();
            limpiarArchivoManual();

            // Restaurar preset sugerido según el tipo actual
            const val = tipoSelect ? tipoSelect.value : '';
            if (val === 'cancha') seleccionarPreset('cancha_sintetica.png');
            else if (val === 'escritorio') seleccionarPreset('escritorio_individual.png');
            else seleccionarPreset('sala_ejecutiva.png');
        });
    }

    // Inicialización al cargar la página
    sincronizarCamposDinamicos();

    const presetInicial = inputPreset ? inputPreset.value : '';
    if (presetInicial) {
        seleccionarPreset(presetInicial);
    } else if (tipoSelect && tipoSelect.value) {
        if (tipoSelect.value === 'cancha') seleccionarPreset('cancha_sintetica.png');
        else if (tipoSelect.value === 'escritorio') seleccionarPreset('escritorio_individual.png');
        else if (tipoSelect.value === 'sala') seleccionarPreset('sala_ejecutiva.png');
    } else {
        seleccionarPreset('sala_ejecutiva.png');
    }
});
</script>

<?php
require_once __DIR__ . '/../../views/layout/pie.php';
