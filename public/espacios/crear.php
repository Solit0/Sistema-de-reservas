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

            <!-- Campo Fotografía / Imagen -->
            <div class="grupo-campo">
                <label for="imagen">Fotografía / Imagen del Espacio (Opcional - JPG, PNG o WEBP, máx. 2MB)</label>
                <input 
                    type="file" 
                    id="imagen" 
                    name="imagen" 
                    class="campo-control <?= isset($errores['imagen']) ? 'campo-error' : '' ?>" 
                    accept="image/png, image/jpeg, image/webp"
                >
                <?php if (isset($errores['imagen'])): ?>
                    <small class="mensaje-error"><?= e($errores['imagen']) ?></small>
                <?php endif; ?>
            </div>

            <!-- Acciones del Formulario -->
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem;">
                <a href="/espacios/index.php" class="btn btn-secundario">Cancelar</a>
                <button type="submit" class="btn btn-acento">Guardar Espacio</button>
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

        if (tipoSelect) {
            tipoSelect.addEventListener('change', sincronizarCamposDinamicos);
            // Sincronizar en carga inicial (útil cuando viene un valor previo en $old)
            sincronizarCamposDinamicos();
        }
    });
</script>

<?php
require_once __DIR__ . '/../../views/layout/pie.php';
