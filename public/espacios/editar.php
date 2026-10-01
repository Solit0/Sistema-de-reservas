<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * Sistema de Reservas - Formulario de Edición de Espacio
 * ==============================================================================
 * Permite modificar los datos de un espacio existente en el catálogo.
 * Implementa precarga de datos relacionales (Single Table Inheritance),
 * soporte sticky ($old) en caso de errores tras validación en servidor,
 * previsualización de imagen actual con opción de reemplazo, protección CSRF
 * y flujo PRG (Post/Redirect/Get).
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database\Conexion;
use App\Repositories\EspacioRepositorio;
use App\Security\Csrf;
use App\Services\ReservaStorageService;

// Iniciar sesión si aún no está activa para CSRF y mensajes flash/errores
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si la petición entra por POST a este mismo archivo, delegar directamente a actualizar.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/actualizar.php';
    exit;
}

// [SEGURIDAD] Escape seguro contra XSS
if (!function_exists('e')) {
    function e(?string $valor): string
    {
        return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// 1. Captura y validación del ID vía GET
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id === null && isset($_GET['id'])) {
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
}

if ($id === null || $id === false || $id <= 0) {
    $_SESSION['errores'] = [
        'general' => 'Identificador de espacio no válido o no especificado.'
    ];
    header('Location: /espacios/index.php');
    exit;
}

// 2. Recuperar errores y valores antiguos de la sesión (patrón Flash)
$errores = $_SESSION['errores'] ?? [];
$old = $_SESSION['old'] ?? [];
unset($_SESSION['errores'], $_SESSION['old']);

// 3. Cargar datos actuales del espacio desde persistencia
$espacioData = null;

// Estrategia A: Repositorio MySQL
if (class_exists(Conexion::class) && file_exists(__DIR__ . '/../../config/config.php')) {
    try {
        $pdo = Conexion::obtener();
        $repositorio = new EspacioRepositorio($pdo);
        $espacioData = $repositorio->obtenerFilaPorId($id);
    } catch (\Throwable) {
        // En contingencia MySQL, se procederá con la siguiente estrategia
    }
}

// Estrategia B: Fallback JSON
if ($espacioData === null) {
    $rutaJson = __DIR__ . '/../../reservas.json';
    if (file_exists($rutaJson)) {
        $storage = new ReservaStorageService();
        $datosJson = $storage->leerDeJson($rutaJson);
        $contador = 1;
        foreach ($datosJson as $item) {
            $idItem = isset($item['id']) ? (int) $item['id'] : $contador;
            if ($idItem === $id) {
                $espacioData = [
                    'id'                   => $id,
                    'nombre'               => (string) ($item['espacio'] ?? ''),
                    'tipo'                 => (string) ($item['tipo'] ?? ''),
                    'capacidad'            => (int) ($item['capacidad'] ?? 0),
                    'tarifa_base'          => (float) ($item['tarifa_base'] ?? 0.0),
                    'imagen'               => !empty($item['imagen']) ? (string) $item['imagen'] : null,
                    'tipo_grama'           => $item['tipo_grama'] ?? null,
                    'iluminacion_nocturna' => $item['iluminacion_nocturna'] ?? null,
                    'tiene_computadora'    => $item['tiene_computadora'] ?? null,
                    'tiene_proyector'      => $item['tiene_proyector'] ?? null,
                ];
                break;
            }
            $contador++;
        }
    }
}

// Si el espacio no existe, redirigir al catálogo
if ($espacioData === null) {
    $_SESSION['errores'] = [
        'general' => 'El espacio solicitado no existe o fue eliminado.'
    ];
    header('Location: /espacios/index.php');
    exit;
}

// Combinación sticky: valores enviados previamente ($old) tienen precedencia sobre la base de datos
$valorNombre = (string) ($old['nombre'] ?? $espacioData['nombre'] ?? '');
$valorTipo = (string) ($old['tipo'] ?? $espacioData['tipo'] ?? '');
$valorCapacidad = (string) ($old['capacidad'] ?? $espacioData['capacidad'] ?? '');
$valorTarifa = (string) ($old['tarifa_base'] ?? $espacioData['tarifa_base'] ?? '');

$valorTipoGrama = (string) ($old['tipo_grama'] ?? $espacioData['tipo_grama'] ?? '');
$valorIluminacion = isset($old['iluminacion_nocturna'])
    ? !empty($old['iluminacion_nocturna'])
    : !empty($espacioData['iluminacion_nocturna']);
$valorComputadora = isset($old['tiene_computadora'])
    ? !empty($old['tiene_computadora'])
    : !empty($espacioData['tiene_computadora']);
$valorProyector = isset($old['tiene_proyector'])
    ? !empty($old['tiene_proyector'])
    : !empty($espacioData['tiene_proyector']);

$imagenActual = !empty($espacioData['imagen']) ? (string) $espacioData['imagen'] : null;

$tituloPagina = 'Editar Espacio: ' . ($espacioData['nombre'] ?? 'Sin Nombre');
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

    .miniatura-actual-card {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.75rem;
        background: #f8fafc;
        border: 1px solid var(--color-borde);
        border-radius: var(--radio-md);
        margin-bottom: 0.75rem;
    }

    .miniatura-actual-card img {
        width: 72px;
        height: 72px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #cbd5e1;
    }
</style>

<section class="formulario-contenedor" style="max-width: 680px; margin: 0 auto;">
    <div style="margin-bottom: 1.5rem;">
        <a href="/espacios/ver.php?id=<?= e((string) $id) ?>" class="btn btn-secundario" style="margin-bottom: 0.75rem; font-size: 0.85rem; padding: 0.35rem 0.75rem;">
            &larr; Volver a la Ficha Técnica
        </a>
        <h1 style="margin: 0; font-size: 1.85rem;"><?= e($tituloPagina) ?></h1>
        <p style="margin: 0.25rem 0 0 0; color: var(--color-texto-mutado);">
            Modifica la información del espacio y guarda los cambios en el catálogo.
        </p>
    </div>

    <?php if (isset($errores['general'])): ?>
        <div class="alerta alerta-error">
            <?= e($errores['general']) ?>
        </div>
    <?php endif; ?>

    <article class="panel">
        <form action="/espacios/actualizar.php" method="POST" enctype="multipart/form-data" novalidate class="formulario">
            <!-- [SEGURIDAD] Token de protección contra ataques CSRF -->
            <?= Csrf::campoHtml() ?>

            <!-- Identificador del espacio -->
            <input type="hidden" name="id" value="<?= e((string) $id) ?>">

            <!-- Campo Nombre -->
            <div class="grupo-campo">
                <label for="nombre">Nombre del Espacio *</label>
                <input 
                    type="text" 
                    id="nombre" 
                    name="nombre" 
                    class="campo-control <?= isset($errores['nombre']) ? 'campo-error' : '' ?>" 
                    placeholder="Ej: Cancha de Fútbol Rápido 1" 
                    value="<?= e($valorNombre) ?>"
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
                    <option value="cancha" <?= $valorTipo === 'cancha' ? 'selected' : '' ?>>Cancha Deportiva</option>
                    <option value="sala" <?= $valorTipo === 'sala' ? 'selected' : '' ?>>Sala de Reunión</option>
                    <option value="escritorio" <?= $valorTipo === 'escritorio' ? 'selected' : '' ?>>Escritorio Individual</option>
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
                        <option value="Sintética" <?= $valorTipoGrama === 'Sintética' ? 'selected' : '' ?>>Sintética</option>
                        <option value="Natural" <?= $valorTipoGrama === 'Natural' ? 'selected' : '' ?>>Natural</option>
                    </select>
                    <?php if (isset($errores['tipo_grama'])): ?>
                        <small class="mensaje-error"><?= e($errores['tipo_grama']) ?></small>
                    <?php endif; ?>
                </div>

                <div class="grupo-campo">
                    <label class="checkbox-inline">
                        <input type="checkbox" id="iluminacion_nocturna" name="iluminacion_nocturna" value="1" <?= $valorIluminacion ? 'checked' : '' ?>>
                        <span>Cuenta con iluminación nocturna (+ disponible en horario vespertino)</span>
                    </label>
                </div>
            </div>

            <!-- Campos específicos para Sala de Reunión -->
            <div id="campos-sala" class="seccion-especifica" style="display: none;">
                <h3>Equipamiento de la Sala</h3>
                <div class="grupo-campo">
                    <label class="checkbox-inline">
                        <input type="checkbox" id="tiene_proyector" name="tiene_proyector" value="1" <?= $valorProyector ? 'checked' : '' ?>>
                        <span>Incluye proyector audiovisual HD y pantalla retráctil</span>
                    </label>
                </div>
            </div>

            <!-- Campos específicos para Escritorio Individual -->
            <div id="campos-escritorio" class="seccion-especifica" style="display: none;">
                <h3>Equipamiento del Escritorio</h3>
                <div class="grupo-campo">
                    <label class="checkbox-inline">
                        <input type="checkbox" id="tiene_computadora" name="tiene_computadora" value="1" <?= $valorComputadora ? 'checked' : '' ?>>
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
                    placeholder="Ej: 10" 
                    value="<?= e($valorCapacidad) ?>"
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
                    placeholder="Ej: 120.00" 
                    value="<?= e($valorTarifa) ?>"
                    required
                >
                <?php if (isset($errores['tarifa_base'])): ?>
                    <small class="mensaje-error"><?= e($errores['tarifa_base']) ?></small>
                <?php endif; ?>
            </div>

            <!-- Campo Fotografía / Imagen Actual y Reemplazo -->
            <div class="grupo-campo">
                <label for="imagen">Fotografía / Imagen del Espacio (JPG, PNG o WEBP, máx. 2MB)</label>
                
                <?php if ($imagenActual !== null): 
                    $rutaImgWeb = str_starts_with($imagenActual, 'http') || str_starts_with($imagenActual, '/')
                        ? $imagenActual
                        : '/uploads/' . $imagenActual;
                ?>
                    <div class="miniatura-actual-card">
                        <img src="<?= e($rutaImgWeb) ?>" alt="Fotografía actual de <?= e($valorNombre) ?>">
                        <div>
                            <strong style="display:block; font-size: 0.9rem; color: var(--color-texto);">Imagen actual registrada</strong>
                            <small style="color: var(--color-texto-mutado); display: block; margin-bottom: 0.25rem;">
                                Archivo: <?= e(basename($imagenActual)) ?>
                            </small>
                            <label class="checkbox-inline" style="font-size: 0.85rem; color: var(--color-peligro, #dc2626);">
                                <input type="checkbox" name="eliminar_imagen_actual" value="1">
                                <span>Eliminar imagen actual y dejar sin foto</span>
                            </label>
                        </div>
                    </div>
                <?php endif; ?>

                <input 
                    type="file" 
                    id="imagen" 
                    name="imagen" 
                    class="campo-control <?= isset($errores['imagen']) ? 'campo-error' : '' ?>" 
                    accept="image/png, image/jpeg, image/webp"
                >
                <small style="color: var(--color-texto-mutado); display: block; margin-top: 0.25rem;">
                    <?= $imagenActual !== null ? 'Selecciona un archivo si deseas reemplazar la imagen actual.' : 'Opcional: Sube una imagen representativa del espacio.' ?>
                </small>
                <?php if (isset($errores['imagen'])): ?>
                    <small class="mensaje-error"><?= e($errores['imagen']) ?></small>
                <?php endif; ?>
            </div>

            <!-- Acciones del Formulario -->
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
                <a href="/espacios/ver.php?id=<?= e((string) $id) ?>" class="btn btn-secundario">Cancelar</a>
                <button type="submit" class="btn btn-primario" style="padding: 0.65rem 1.5rem; font-weight: 700;">
                    Guardar Cambios &rarr;
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
            // Sincronizar en carga inicial (importante para precarga de datos)
            sincronizarCamposDinamicos();
        }
    });
</script>

<?php
require_once __DIR__ . '/../../views/layout/pie.php';
