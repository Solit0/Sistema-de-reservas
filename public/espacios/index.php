<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * Sistema de Reservas - Catálogo y Listado de Espacios
 * ==============================================================================
 * Vista del catálogo general de espacios (Commit 4).
 * Renderiza la colección polimórfica de espacios (Canchas, Salas, Escritorios)
 * resolviendo tarifas y nombres descriptivos por despacho dinámico.
 *
 * Arquitectura y Seguridad:
 * - Cumple con PHP 8.2+ con modo estricto tipado.
 * - [POLIMORFISMO] Despacho dinámico puro sin verificación de tipos concretos.
 * - [SEGURIDAD] Sanitización de salida contra vectores XSS en todo dato del DOM.
 * - Optimización de rendimiento con loading="lazy" y dimensiones CLS explícitas.
 */

// Carga del autoloader de dependencias de Composer
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Domain\Espacios\Cancha;
use App\Domain\Espacios\EscritorioIndividual;
use App\Domain\Espacios\Espacio;
use App\Domain\Espacios\SalaReunion;
use App\Security\Csrf;

// [SEGURIDAD] Definición de función auxiliar de escape contextual contra XSS
if (!function_exists('e')) {
    /**
     * Escapa caracteres especiales para evitar inyecciones XSS en el navegador.
     */
    function e(?string $valor): string
    {
        // [SEGURIDAD] Sanitización de salida contra vectores XSS
        return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// -----------------------------------------------------------------------------
// 1. CARGA DE LA COLECCIÓN POLIMÓRFICA DE ESPACIOS
// -----------------------------------------------------------------------------
if (!isset($espacios)) {
    /** @var Espacio[] $espacios */
    $espacios = [];

    // Estrategia 1: Carga desde Base de Datos MySQL si la conexión está configurada
    if (class_exists(\App\Database\Conexion::class) && file_exists(__DIR__ . '/../../config/config.php')) {
    try {
        $pdo = \App\Database\Conexion::obtener();
        $stmt = $pdo->query('SELECT id, tipo, nombre, capacidad, imagen FROM espacios ORDER BY id ASC');
        if ($stmt) {
            $clases = [
                'cancha'     => Cancha::class,
                'sala'       => SalaReunion::class,
                'escritorio' => EscritorioIndividual::class,
            ];

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $tipoKey = strtolower((string) ($row['tipo'] ?? ''));
                if (isset($clases[$tipoKey])) {
                    $clase = $clases[$tipoKey];
                    /** @var Espacio $instancia */
                    $instancia = new $clase(
                        (string) $row['nombre'],
                        (int) $row['capacidad'],
                        $row['imagen'] ? (string) $row['imagen'] : null,
                        (int) $row['id']
                    );
                    $espacios[] = $instancia;
                }
            }
        }
    } catch (\Throwable) {
        // En caso de indisponibilidad de MySQL se procede con la siguiente estrategia
    }
}

// Estrategia 2: Carga desde persistencia JSON (reservas.json)
if (empty($espacios)) {
    $rutaJson = __DIR__ . '/../../reservas.json';
    if (file_exists($rutaJson)) {
        try {
            $storage = new \App\Services\ReservaStorageService();
            $datosJson = $storage->leerDeJson($rutaJson);
            $contador = 1;
            foreach ($datosJson as $item) {
                $idItem = isset($item['id']) ? (int) $item['id'] : $contador;
                $tipoNormalizado = mb_strtolower((string) ($item['tipo'] ?? ''));
                $nombre = (string) ($item['espacio'] ?? 'Espacio');
                $capacidad = (int) ($item['capacidad'] ?? 1);
                $imagen = !empty($item['imagen']) ? (string) $item['imagen'] : null;

                if (str_contains($tipoNormalizado, 'cancha')) {
                    $espacios[] = new Cancha($nombre, $capacidad, $imagen, $idItem);
                } elseif (str_contains($tipoNormalizado, 'sala')) {
                    $espacios[] = new SalaReunion($nombre, $capacidad, $imagen, $idItem);
                } elseif (str_contains($tipoNormalizado, 'escritorio')) {
                    $espacios[] = new EscritorioIndividual($nombre, $capacidad, $imagen, $idItem);
                }
                $contador++;
            }
        } catch (\Throwable) {
            // Manejo de contingencia silencioso
        }
    }
}

    // Estrategia 3: Dataset de demostración inicial con objetos polimórficos
    if (empty($espacios)) {
        $espacios = [
            new Cancha('Cancha Central Sintética', 10, 'cancha_sintetica.png', 1),
            new Cancha('Cancha Norte Grama Natural', 10, 'cancha_sintetica.png', 2),
            new SalaReunion('Sala de Juntas Principal', 8, 'sala_ejecutiva.png', 3),
            new SalaReunion('Sala Ejecutiva de Conferencias', 12, 'sala_ejecutiva.png', 4),
            new EscritorioIndividual('Escritorio Individual 01', 1, 'escritorio_individual.png', 5),
            new EscritorioIndividual('Escritorio Individual 02', 1, 'escritorio_individual.png', 6),
        ];
    }
}

// -----------------------------------------------------------------------------
// 2. CONTEXTO DE LAYOUT Y TÍTULO DE LA PÁGINA
// -----------------------------------------------------------------------------
$tituloPagina = 'Catálogo de Espacios';

// Inclusión del encabezado global (abre la etiqueta semántica <main class="contenedor principal">)
require_once __DIR__ . '/../../views/layout/encabezado.php';
?>

<!-- Estilos exclusivos y reseteos para el Catálogo de Espacios -->
<style>
    /* Reseteo para encabezados semánticos que colisionan con el selector global 'header' */
    .catalogo-espacios header,
    .catalogo-espacios .catalogo-header,
    .catalogo-espacios .panel-header {
        background: transparent !important;
        background-color: transparent !important;
        box-shadow: none !important;
        position: static !important;
        color: inherit !important;
        padding: 0 !important;
        top: auto !important;
        z-index: auto !important;
    }

    .catalogo-espacios {
        display: flex;
        flex-direction: column;
        gap: 1.75rem;
        animation: fadeIn 0.3s ease-in-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(6px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Encabezado del Catálogo */
    .catalogo-header-container {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid var(--color-borde);
    }

    .catalogo-header-titulos h1 {
        margin: 0 0 0.4rem 0;
        font-size: 2rem;
        font-weight: 800;
        color: var(--color-secundario);
        letter-spacing: -0.02em;
    }

    .catalogo-header-titulos p {
        margin: 0;
        color: var(--color-texto-mutado);
        font-size: 1rem;
    }

    .badge-contador-catalogo {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: var(--color-superficie);
        border: 1px solid var(--color-borde);
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--color-texto);
        box-shadow: var(--sombra-sm);
    }

    /* Contenedor del Listado */
    .panel-catalogo {
        background: var(--color-superficie);
        border-radius: 10px;
        border: 1px solid var(--color-borde);
        box-shadow: var(--sombra-sm);
        padding: 1.5rem;
    }

    /* Insignias de Tipo */
    .badge-tipo-espacio {
        background-color: #f1f5f9;
        color: var(--color-secundario);
        font-weight: 600;
        font-size: 0.825rem;
        padding: 0.25rem 0.65rem;
        border-radius: 4px;
        border: 1px solid var(--color-borde);
        display: inline-block;
    }

    /* Estado Vacío (Empty State) */
    .empty-state {
        text-align: center;
        padding: 3.5rem 1.5rem;
        background: var(--color-superficie);
        border: 1px dashed var(--color-borde);
        border-radius: 10px;
        margin: 1rem 0;
    }

    .empty-state-icono {
        width: 56px;
        height: 56px;
        margin: 0 auto 1.25rem auto;
        background: #f1f5f9;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--color-texto-mutado);
    }

    .empty-state h3 {
        font-size: 1.35rem;
        color: var(--color-secundario);
        margin-bottom: 0.5rem;
    }

    .empty-state p {
        color: var(--color-texto-mutado);
        max-width: 480px;
        margin: 0 auto 1.5rem auto;
        font-size: 0.95rem;
    }

    /* Botón compacto */
    .btn-sm {
        padding: 0.4rem 0.85rem;
        font-size: 0.85rem;
    }

    /* Imagen miniatura optimizada */
    .miniatura-placeholder {
        background-color: #f8fafc;
        border: 1px solid var(--color-borde);
        display: flex;
        align-items: center;
        justify-content: center;
    }
</style>

<!-- ======================================================================= -->
<!-- VISTA DEL CATÁLOGO DE ESPACIOS (HTML5 Semántico)                        -->
<!-- ======================================================================= -->
<section class="catalogo-espacios">

    <!-- Encabezado de la Sección -->
    <header class="catalogo-header">
        <div class="catalogo-header-container">
            <div class="catalogo-header-titulos">
                <!-- [SEGURIDAD] Sanitización de salida contra vectores XSS -->
                <h1><?= e($tituloPagina) ?></h1>
                <p>Explora la disponibilidad, tarifas base y especificaciones técnicas de cada área.</p>
            </div>
            <div class="catalogo-header-acciones">
                <span class="badge-contador-catalogo">
                    <!-- [SEGURIDAD] Sanitización de salida contra vectores XSS -->
                    Total de Espacios: <strong><?= e((string) count($espacios)) ?></strong>
                </span>
                <a href="crear.php" class="btn btn-acento" style="display: inline-flex; align-items: center; gap: 0.45rem; margin-left: 0.5rem; font-weight: 600;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <span>Nuevo Espacio</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Contenedor Principal del Listado -->
    <section class="seccion-listado-espacios" aria-label="Listado de espacios disponibles">
        <?php if (empty($espacios)): ?>
            <!-- Estado Vacío (Empty State) -->
            <article class="empty-state">
                <div class="empty-state-icono">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                </div>
                <h3>No hay espacios registrados</h3>
                <p>Actualmente no se han registrado espacios en el catálogo. Registra un nuevo espacio para comenzar.</p>
                <a href="crear.php" class="btn btn-acento">
                    Registrar nuevo espacio
                </a>
            </article>
        <?php else: ?>
            <article class="panel panel-catalogo">
                <div class="tabla-responsive">
                    <table class="tabla-datos">
                        <thead>
                            <tr>
                                <th scope="col" style="width: 80px;">Foto</th>
                                <th scope="col">Nombre del Espacio</th>
                                <th scope="col">Tipo de Espacio</th>
                                <th scope="col">Capacidad</th>
                                <th scope="col">Tarifa Estándar (2h)</th>
                                <th scope="col" style="text-align: right;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($espacios as $espacio): ?>
                                <?php
                                // [POLIMORFISMO] Resolución dinámica de tarifa y tipo sin comprobación de tipos concretos
                                $tipoLegible    = $espacio->obtenerTipoLegible();
                                $tarifaEstandar = $espacio->calcularTarifa(2);
                                $nombreEspacio  = $espacio->getNombre();
                                $capacidad      = $espacio->getCapacidad();
                                $idEspacio      = $espacio->getId();
                                $imagenRelativa = $espacio->getImagen();

                                // Validación de la imagen miniatura con fallback seguro a SVG
                                $srcImagen = null;
                                if ($imagenRelativa !== null && trim($imagenRelativa) !== '') {
                                    $rutaLimpia = ltrim(trim($imagenRelativa), '/');
                                    if (file_exists(__DIR__ . '/../../public/' . $rutaLimpia) && !is_dir(__DIR__ . '/../../public/' . $rutaLimpia)) {
                                        $srcImagen = '/' . $rutaLimpia;
                                    } elseif (file_exists(__DIR__ . '/../../public/uploads/' . $rutaLimpia) && !is_dir(__DIR__ . '/../../public/uploads/' . $rutaLimpia)) {
                                        $srcImagen = '/uploads/' . $rutaLimpia;
                                    } elseif (file_exists(__DIR__ . '/../../public/img/presets/' . $rutaLimpia) && !is_dir(__DIR__ . '/../../public/img/presets/' . $rutaLimpia)) {
                                        $srcImagen = '/img/presets/' . $rutaLimpia;
                                    }
                                }

                                if ($srcImagen === null) {
                                    $srcImagen = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='60' height='60' viewBox='0 0 60 60'%3E%3Crect width='60' height='60' fill='%23f1f5f9' rx='6'/%3E%3Cpath d='M20 38l6-8 5 6 7-10 10 12H20z' fill='%23cbd5e1'/%3E%3Ccircle cx='26' cy='22' r='3' fill='%2394a3b8'/%3E%3C/svg%3E";
                                }
                                ?>
                                <tr>
                                    <td>
                                        <!-- [SEGURIDAD] Sanitización de salida contra vectores XSS -->
                                        <img
                                            src="<?= e($srcImagen) ?>"
                                            alt="Miniatura de <?= e($nombreEspacio) ?>"
                                            class="miniatura miniatura-placeholder"
                                            width="60"
                                            height="60"
                                            loading="lazy"
                                        >
                                    </td>
                                    <td>
                                        <!-- [SEGURIDAD] Sanitización de salida contra vectores XSS -->
                                        <strong><?= e($nombreEspacio) ?></strong>
                                    </td>
                                    <td>
                                        <!-- [SEGURIDAD] Sanitización de salida contra vectores XSS -->
                                        <span class="badge-tipo-espacio">
                                            <?= e($tipoLegible) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <!-- [SEGURIDAD] Sanitización de salida contra vectores XSS -->
                                        <?= e((string) $capacidad) ?> <?= $capacidad === 1 ? 'persona' : 'personas' ?>
                                    </td>
                                    <td>
                                        <!-- [SEGURIDAD] Sanitización de salida contra vectores XSS -->
                                        <strong style="color: var(--color-secundario); font-size: 1.05rem;">
                                            $ <?= number_format($tarifaEstandar, 2) ?>
                                        </strong>
                                        <small style="color: var(--color-texto-mutado); display: block; font-size: 0.775rem;">
                                            Estándar 2 horas
                                        </small>
                                    </td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <!-- [SEGURIDAD] Sanitización de salida contra vectores XSS -->
                                        <a href="ver.php?id=<?= e((string) $idEspacio) ?>" class="btn btn-secundario btn-sm" title="Ver ficha técnica">
                                            Ficha Técnica
                                        </a>
                                        <a href="editar.php?id=<?= e((string) $idEspacio) ?>" class="btn btn-secundario btn-sm" style="margin-left: 0.25rem;" title="Editar espacio">
                                            Editar
                                        </a>
                                        <!-- [CRUD-DELETE] Eliminación procesada estrictamente por POST con CSRF -->
                                        <form action="eliminar.php" method="POST" onsubmit="return confirm('¿Estás seguro de que deseas eliminar este espacio?');" style="display: inline; margin-left: 0.25rem;">
                                            <?= Csrf::campoHtml() ?>
                                            <input type="hidden" name="id" value="<?= e((string) $idEspacio) ?>">
                                            <button type="submit" class="btn btn-peligro btn-sm" title="Eliminar espacio">
                                                Eliminar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </article>
        <?php endif; ?>
    </section>

</section>

<?php
// Inclusión del pie de página global (cierra la etiqueta </main> y el documento HTML)
require_once __DIR__ . '/../../views/layout/pie.php';
