<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

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

$tituloPagina = 'Registrar Nuevo Espacio';
require_once __DIR__ . '/../../views/layout/encabezado.php';
?>

<div style="margin-bottom: 1.5rem;">
    <a href="/espacios/index.php" class="btn btn-secundario" style="margin-bottom: 0.75rem; font-size: 0.85rem; padding: 0.35rem 0.75rem;">
        &larr; Volver al Catálogo
    </a>
    <h1 style="margin: 0; font-size: 1.85rem;"><?= e($tituloPagina) ?></h1>
    <p style="margin: 0.25rem 0 0 0; color: var(--color-texto-mutado);">
        Completa los datos del espacio y selecciona una fotografía o preset visual.
    </p>
</div>

<?php if (!empty($errores['general'])): ?>
    <div style="background-color: #fef2f2; border: 1px solid #f87171; color: #991b1b; padding: 0.85rem 1.15rem; border-radius: 8px; margin-bottom: 1.5rem;">
        <?= e($errores['general']) ?>
    </div>
<?php endif; ?>

<section class="formulario-contenedor" style="max-width: 720px; margin: 0 auto;">
    <article class="panel" style="padding: 1.75rem;">
        <form id="formEspacio" action="/espacios/guardar.php" method="POST" enctype="multipart/form-data" class="formulario">
            <div class="grupo-campo">
                <label for="nombre">Nombre del Espacio *</label>
                <input type="text" id="nombre" name="nombre" class="campo-control"
                       placeholder="Ej: Sala Ejecutiva VIP"
                       value="<?= e($antiguo['nombre'] ?? '') ?>" required>
                <?php if (isset($errores['nombre'])): ?>
                    <div style="color: #dc2626; font-size: 0.8rem; margin-top: 0.25rem;"><?= e($errores['nombre']) ?></div>
                <?php endif; ?>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="grupo-campo">
                    <label for="tipo">Tipo de Espacio *</label>
                    <select id="tipo" name="tipo" class="campo-control" required>
                        <option value="">-- Selecciona categoría --</option>
                        <option value="sala" <?= ($antiguo['tipo'] ?? '') === 'sala' ? 'selected' : '' ?>>🏢 Sala de Reunión</option>
                        <option value="cancha" <?= ($antiguo['tipo'] ?? '') === 'cancha' ? 'selected' : '' ?>>⚽ Cancha Deportiva</option>
                        <option value="escritorio" <?= ($antiguo['tipo'] ?? '') === 'escritorio' ? 'selected' : '' ?>>💻 Escritorio Individual</option>
                    </select>
                    <?php if (isset($errores['tipo'])): ?>
                        <div style="color: #dc2626; font-size: 0.8rem; margin-top: 0.25rem;"><?= e($errores['tipo']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="grupo-campo">
                    <label for="capacidad">Capacidad (Personas) *</label>
                    <input type="number" id="capacidad" name="capacidad" class="campo-control"
                           min="1" max="500" placeholder="Ej: 8"
                           value="<?= e((string)($antiguo['capacidad'] ?? '8')) ?>" required>
                    <?php if (isset($errores['capacidad'])): ?>
                        <div style="color: #dc2626; font-size: 0.8rem; margin-top: 0.25rem;"><?= e($errores['capacidad']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grupo-campo">
                <label for="tarifa_base">Tarifa Base ($) *</label>
                <input type="number" step="0.01" id="tarifa_base" name="tarifa_base" class="campo-control"
                       min="1" placeholder="Ej: 180.00"
                       value="<?= e((string)($antiguo['tarifa_base'] ?? '180.00')) ?>" required>
                <small style="color: var(--color-texto-mutado); font-size: 0.8rem;">
                    Por hora en salas y escritorios; por bloque de 60 min en canchas.
                </small>
                <?php if (isset($errores['tarifa_base'])): ?>
                    <div style="color: #dc2626; font-size: 0.8rem; margin-top: 0.25rem;"><?= e($errores['tarifa_base']) ?></div>
                <?php endif; ?>
            </div>

            <!-- Sección de Fotografía Moderna -->
            <div class="grupo-campo" style="margin-top: 0.5rem; border-top: 1px solid var(--color-borde); padding-top: 1.25rem;">
                <label style="font-size: 1rem; font-weight: 700; margin-bottom: 0.25rem;">Fotografía del Espacio</label>
                <p style="color: var(--color-texto-mutado); font-size: 0.85rem; margin-bottom: 0.75rem;">
                    Elige una foto predefinida rápida o arrastra una imagen desde tu equipo:
                </p>

                <!-- Input oculto para presets -->
                <input type="hidden" name="preset_imagen" id="inputPresetImagen" value="sala_ejecutiva.png">

                <!-- 1. Galería de Presets Rápidos -->
                <div style="font-size: 0.8rem; font-weight: 600; color: #475569; margin-bottom: 0.4rem;">
                    Opciones Rápidas (un clic):
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; margin-bottom: 1rem;">
                    <div class="tarjeta-preset activa" data-preset="sala_ejecutiva.png" data-tipo="sala" style="border: 2px solid var(--color-acento); border-radius: 8px; overflow: hidden; cursor: pointer; background: #ffffff; transition: all 0.2s ease;">
                        <img src="/img/presets/sala_ejecutiva.png" alt="Sala Ejecutiva" style="width: 100%; height: 90px; object-fit: cover; display: block;">
                        <div style="padding: 0.5rem 0.65rem; font-size: 0.8rem; font-weight: 600; display: flex; justify-content: space-between; align-items: center;">
                            <span>🏢 Sala Ejecutiva</span>
                            <span class="preset-check" style="color: var(--color-acento);">✓</span>
                        </div>
                    </div>

                    <div class="tarjeta-preset" data-preset="cancha_sintetica.png" data-tipo="cancha" style="border: 2px solid var(--color-borde); border-radius: 8px; overflow: hidden; cursor: pointer; background: #ffffff; transition: all 0.2s ease;">
                        <img src="/img/presets/cancha_sintetica.png" alt="Cancha Sintética" style="width: 100%; height: 90px; object-fit: cover; display: block;">
                        <div style="padding: 0.5rem 0.65rem; font-size: 0.8rem; font-weight: 600; display: flex; justify-content: space-between; align-items: center;">
                            <span>⚽ Cancha Sintética</span>
                            <span class="preset-check" style="display: none; color: var(--color-acento);">✓</span>
                        </div>
                    </div>

                    <div class="tarjeta-preset" data-preset="escritorio_individual.png" data-tipo="escritorio" style="border: 2px solid var(--color-borde); border-radius: 8px; overflow: hidden; cursor: pointer; background: #ffffff; transition: all 0.2s ease;">
                        <img src="/img/presets/escritorio_individual.png" alt="Escritorio Individual" style="width: 100%; height: 90px; object-fit: cover; display: block;">
                        <div style="padding: 0.5rem 0.65rem; font-size: 0.8rem; font-weight: 600; display: flex; justify-content: space-between; align-items: center;">
                            <span>💻 Escritorio Cowork</span>
                            <span class="preset-check" style="display: none; color: var(--color-acento);">✓</span>
                        </div>
                    </div>
                </div>

                <!-- 2. Zona Drag & Drop para Subida Personalizada -->
                <div style="font-size: 0.8rem; font-weight: 600; color: #475569; margin-bottom: 0.4rem;">
                    O subir archivo propio:
                </div>
                <div id="dropzone" style="border: 2px dashed #94a3b8; border-radius: 10px; padding: 1.5rem; text-align: center; background: #f8fafc; cursor: pointer; transition: all 0.2s ease; position: relative;">
                    <input type="file" id="inputArchivoImagen" name="imagen" accept="image/jpeg,image/png,image/webp" style="display: none;">
                    
                    <div id="dropzoneContent">
                        <div style="font-size: 2rem; margin-bottom: 0.35rem;">📷</div>
                        <div style="font-size: 0.95rem; font-weight: 600; color: var(--color-secundario);">
                            Arrastra una imagen aquí o <span style="color: var(--color-acento); text-decoration: underline;">haz clic para explorar</span>
                        </div>
                        <div style="font-size: 0.78rem; color: var(--color-texto-mutado); margin-top: 0.25rem;">
                            Formatos permitidos: JPG, PNG o WEBP (máximo 3 MB)
                        </div>
                    </div>

                    <!-- Vista previa activa de archivo cargado -->
                    <div id="previewContainer" style="display: none; align-items: center; justify-content: center; gap: 1rem; flex-wrap: wrap;">
                        <img id="imgPreview" src="" alt="Vista previa" style="width: 120px; height: 80px; object-fit: cover; border-radius: 6px; border: 1px solid var(--color-borde); box-shadow: 0 2px 6px rgba(0,0,0,0.1);">
                        <div style="text-align: left;">
                            <strong id="previewFileName" style="font-size: 0.9rem; color: var(--color-secundario); display: block;">foto.jpg</strong>
                            <span id="previewFileSize" style="font-size: 0.8rem; color: var(--color-texto-mutado);">1.2 MB</span>
                            <div style="margin-top: 0.35rem;">
                                <button type="button" id="btnQuitarImagen" style="background: #fee2e2; border: 1px solid #fca5a5; color: #b91c1c; padding: 0.25rem 0.65rem; border-radius: 4px; font-size: 0.75rem; cursor: pointer; font-weight: 600;">
                                    ✕ Quitar archivo y usar preset
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (isset($errores['imagen'])): ?>
                    <div style="color: #dc2626; font-size: 0.8rem; margin-top: 0.35rem;"><?= e($errores['imagen']) ?></div>
                <?php endif; ?>
            </div>

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
    const inputPreset = document.getElementById('inputPresetImagen');
    const tarjetasPreset = document.querySelectorAll('.tarjeta-preset');
    const selectTipo = document.getElementById('tipo');
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

    // 1. Selección de presets rápidos
    tarjetasPreset.forEach(card => {
        card.addEventListener('click', function () {
            seleccionarPreset(this.dataset.preset);
            limpiarArchivoManual();
        });
    });

    function seleccionarPreset(presetName) {
        inputPreset.value = presetName;
        tarjetasPreset.forEach(c => {
            const esEste = (c.dataset.preset === presetName);
            c.style.borderColor = esEste ? 'var(--color-acento)' : 'var(--color-borde)';
            c.classList.toggle('activa', esEste);
            const check = c.querySelector('.preset-check');
            if (check) check.style.display = esEste ? 'inline' : 'none';
        });
    }

    // Auto-ajuste sugerido de preset y tarifas según tipo de espacio
    if (selectTipo) {
        selectTipo.addEventListener('change', function () {
            const val = this.value;
            if (val === 'sala') {
                seleccionarPreset('sala_ejecutiva.png');
                if (inputTarifa && !inputTarifa.value) inputTarifa.value = '180.00';
                if (inputCapacidad && !inputCapacidad.value) inputCapacidad.value = '8';
            } else if (val === 'cancha') {
                seleccionarPreset('cancha_sintetica.png');
                if (inputTarifa && !inputTarifa.value) inputTarifa.value = '120.00';
                if (inputCapacidad && !inputCapacidad.value) inputCapacidad.value = '10';
            } else if (val === 'escritorio') {
                seleccionarPreset('escritorio_individual.png');
                if (inputTarifa && !inputTarifa.value) inputTarifa.value = '75.00';
                if (inputCapacidad && !inputCapacidad.value) inputCapacidad.value = '1';
            }
        });
    }

    // 2. Drag & Drop y Selección manual de imagen
    dropzone.addEventListener('click', (e) => {
        if (e.target !== btnQuitar) {
            inputArchivo.click();
        }
    });

    ['dragenter', 'dragover'].forEach(evt => {
        dropzone.addEventListener(evt, (e) => {
            e.preventDefault();
            dropzone.style.borderColor = 'var(--color-acento)';
            dropzone.style.background = '#eff6ff';
        });
    });

    ['dragleave', 'drop'].forEach(evt => {
        dropzone.addEventListener(evt, (e) => {
            e.preventDefault();
            dropzone.style.borderColor = '#94a3b8';
            dropzone.style.background = '#f8fafc';
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
            previewFileSize.textContent = sizeKB > 1024 ? `${(sizeKB / 1024).toFixed(2)} MB` : `${sizeKB} KB`;

            dropzoneContent.style.display = 'none';
            previewContainer.style.display = 'flex';

            // Desmarcar presets visuales porque el usuario subió archivo propio
            inputPreset.value = '';
            tarjetasPreset.forEach(c => {
                c.style.borderColor = 'var(--color-borde)';
                c.classList.remove('activa');
                const check = c.querySelector('.preset-check');
                if (check) check.style.display = 'none';
            });
        };
        reader.readAsDataURL(file);
    }

    function limpiarArchivoManual() {
        inputArchivo.value = '';
        dropzoneContent.style.display = 'block';
        previewContainer.style.display = 'none';
    }

    btnQuitar.addEventListener('click', (e) => {
        e.stopPropagation();
        limpiarArchivoManual();
        // Restaurar preset por defecto
        seleccionarPreset('sala_ejecutiva.png');
    });
});
</script>

<?php
require_once __DIR__ . '/../../views/layout/pie.php';
