<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * Sistema de Reservas - Ficha Técnica del Espacio (Commit 5)
 * ==============================================================================
 * Vista detallada de un espacio específico del catálogo por su ID.
 * Implementa resolución polimórfica pura para la obtención de especificaciones
 * técnicas particulares delegando directamente al modelo (sin inspección de tipos),
 * cálculo dinámico de tarifas por duración y recargo, sección multimedia con
 * imagen hero accesible, y prevención estricta de XSS mediante htmlspecialchars.
 */

// Carga del autoloader de dependencias de Composer
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database\Conexion;
use App\Domain\Espacios\Cancha;
use App\Domain\Espacios\EscritorioIndividual;
use App\Domain\Espacios\Espacio;
use App\Domain\Espacios\SalaReunion;
use App\Repositories\EspacioRepositorio;
use App\Security\Csrf;
use App\Services\ReservaStorageService;

// Iniciar sesión PHP si aún no está activa para manejo de mensajes de estado
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// [SEGURIDAD] Escapado riguroso con htmlspecialchars para prevenir XSS
if (!function_exists('e')) {
    function e(?string $valor): string
    {
        // [SEGURIDAD] Escapado riguroso con htmlspecialchars para prevenir XSS
        return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// -----------------------------------------------------------------------------
// 1. CAPTURA Y VALIDACIÓN DE ENTRADA (GET)
// -----------------------------------------------------------------------------
$idSolicitado = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($idSolicitado === null && isset($_GET['id'])) {
    $idSolicitado = filter_var($_GET['id'], FILTER_VALIDATE_INT);
}

if ($idSolicitado === null || $idSolicitado === false || $idSolicitado <= 0) {
    $_SESSION['errores'] = [
        'general' => 'El identificador de espacio proporcionado no es válido o no fue especificado.'
    ];
    header('Location: index.php');
    exit;
}

// -----------------------------------------------------------------------------
// 2. RECUPERACIÓN DEL ESPACIO (PERSISTENCIA Y FALLBACKS)
// -----------------------------------------------------------------------------
/** @var Espacio|null $espacio */
$espacio = null;
$reservasEspacio = [];

// Estrategia A: Consulta relacional a través del Repositorio si MySQL está configurado
if (class_exists(Conexion::class) && file_exists(__DIR__ . '/../../config/config.php')) {
    try {
        $pdo = Conexion::obtener();
        $repositorio = new EspacioRepositorio($pdo);
        $espacio = $repositorio->buscarPorId($idSolicitado);
    } catch (\Throwable) {
        // En caso de contingencia con MySQL se procede con la siguiente estrategia
    }
}

// Estrategia B: Persistencia JSON (reservas.json)
if ($espacio === null) {
    $rutaJson = __DIR__ . '/../../reservas.json';
    if (file_exists($rutaJson)) {
        try {
            $storage = new ReservaStorageService();
            $datosJson = $storage->leerDeJson($rutaJson);
            $contador = 1;
            foreach ($datosJson as $item) {
                $idItem = isset($item['id']) ? (int) $item['id'] : $contador;
                if ($idItem === $idSolicitado) {
                    $tipoNorm = mb_strtolower((string) ($item['tipo'] ?? ''));
                    $nombre = (string) ($item['espacio'] ?? 'Espacio');
                    $capacidad = (int) ($item['capacidad'] ?? 1);
                    $imagen = !empty($item['imagen']) ? (string) $item['imagen'] : null;

                    if (str_contains($tipoNorm, 'cancha')) {
                        $espacio = new Cancha($nombre, $capacidad, $imagen, $idSolicitado);
                    } elseif (str_contains($tipoNorm, 'sala')) {
                        $espacio = new SalaReunion($nombre, $capacidad, $imagen, $idSolicitado);
                    } elseif (str_contains($tipoNorm, 'escritorio')) {
                        $espacio = new EscritorioIndividual($nombre, $capacidad, $imagen, $idSolicitado);
                    }

                    // Extracción directa de reservas asociadas sin duplicación de lectura
                    if (!empty($item['reservas']) && is_array($item['reservas'])) {
                        foreach ($item['reservas'] as $r) {
                            $reservasEspacio[] = [
                                'cliente'     => $r['titular'] ?? ($r['cliente'] ?? 'Cliente'),
                                'fecha'       => $r['fecha'] ?? '',
                                'hora_inicio' => $r['hora_inicio'] ?? '',
                                'hora_fin'    => $r['hora_fin'] ?? '',
                                'monto_total' => (float) ($r['costo'] ?? ($r['monto_total'] ?? 0.0)),
                            ];
                        }
                    }
                    break;
                }
                $contador++;
            }
        } catch (\Throwable) {
            // Manejo silencioso de contingencia
        }
    }
}

// Estrategia C: Dataset de demostración inicial con objetos polimórficos
if ($espacio === null && $idSolicitado >= 1 && $idSolicitado <= 6) {
    $espaciosDemo = [
        1 => new Cancha('Cancha Central Sintética', 10, 'img/presets/cancha_sintetica.png', 1, 'Sintética', true),
        2 => new Cancha('Cancha Norte Grama Natural', 10, null, 2, 'Natural', false),
        3 => new SalaReunion('Sala de Juntas Principal', 8, 'img/presets/sala_ejecutiva.png', 3, true),
        4 => new SalaReunion('Sala Ejecutiva de Conferencias', 12, null, 4, true),
        5 => new EscritorioIndividual('Escritorio Individual 01', 1, 'img/presets/escritorio_individual.png', 5, true),
        6 => new EscritorioIndividual('Escritorio Individual 02', 1, null, 6, false),
    ];
    $espacio = $espaciosDemo[$idSolicitado] ?? null;
}

// Redirección amigable si el espacio no existe en ninguna fuente de datos
if ($espacio === null) {
    $_SESSION['errores'] = [
        'general' => sprintf('No se encontró ningún espacio con el identificador #%d en el catálogo.', $idSolicitado)
    ];
    header('Location: index.php');
    exit;
}

// -----------------------------------------------------------------------------
// 3. RECUPERACIÓN DE RESERVAS ASOCIADAS AL ESPACIO (SI AÚN NO SE CARGARON)
// -----------------------------------------------------------------------------
if (empty($reservasEspacio)) {
    // Origen A: Colección interna del objeto de dominio
    if (!empty($espacio->obtenerReservas())) {
        foreach ($espacio->obtenerReservas() as $res) {
            $reservasEspacio[] = [
                'cliente'     => $res->getTitular(),
                'fecha'       => $res->getHorario()->getInicio()->format('Y-m-d'),
                'hora_inicio' => $res->getHorario()->getInicio()->format('H:i'),
                'hora_fin'    => $res->getHorario()->getFin()->format('H:i'),
                'monto_total' => $res->getCostoCalculado(),
            ];
        }
    }

    // Origen B: Base de datos si aún no hay reservas en memoria
    if (empty($reservasEspacio) && class_exists(Conexion::class) && file_exists(__DIR__ . '/../../config/config.php')) {
        try {
            $pdo = Conexion::obtener();
            $stmtRes = $pdo->prepare('SELECT id, cliente, fecha, hora_inicio, hora_fin, monto_total FROM reservas WHERE espacio_id = :id ORDER BY fecha DESC, hora_inicio ASC LIMIT 10');
            $stmtRes->execute([':id' => $idSolicitado]);
            $filasBD = $stmtRes->fetchAll(\PDO::FETCH_ASSOC);
            if (!empty($filasBD)) {
                $reservasEspacio = $filasBD;
            }
        } catch (\Throwable) {
        }
    }
}

// -----------------------------------------------------------------------------
// 4. RESOLUCIÓN POLIMÓRFICA Y DELEGACIÓN AL MODELO
// -----------------------------------------------------------------------------
$nombreEspacio      = $espacio->getNombre();
$tipoLegible        = $espacio->obtenerTipoLegible();
$capacidad          = $espacio->getCapacidad();
$descripcionEspacio = $espacio->obtenerDescripcion();

// [POLIMORFISMO] Cálculo dinámico de tarifas delegado al contrato de cada espacio
$tarifa1h     = $espacio->calcularTarifa(1);
$tarifa2h     = $espacio->calcularTarifa(2);
$tarifa4h     = $espacio->calcularTarifa(4);
$tarifa2hPico = $espacio->calcularTarifa(2, true);

// [POLIMORFISMO] Características particulares resueltas por delegación al objeto sin comprobación de tipos
$caracteristicas = $espacio->obtenerCaracteristicas();

// -----------------------------------------------------------------------------
// 5. RESOLUCIÓN DE IMAGEN CON FALLBACK ESTÁTICO SEGURO
// -----------------------------------------------------------------------------
$imagenRelativa = $espacio->getImagen();
$srcImagen = null;

if ($imagenRelativa !== null && trim($imagenRelativa) !== '') {
    $imagenRelativa = trim($imagenRelativa);
    if (str_starts_with($imagenRelativa, 'http://') || str_starts_with($imagenRelativa, 'https://') || str_starts_with($imagenRelativa, 'data:')) {
        $srcImagen = $imagenRelativa;
    } else {
        $rutaLimpia = ltrim($imagenRelativa, '/');
        if (file_exists(__DIR__ . '/../../public/' . $rutaLimpia) && !is_dir(__DIR__ . '/../../public/' . $rutaLimpia)) {
            $srcImagen = '/' . $rutaLimpia;
        } elseif (file_exists(__DIR__ . '/../../public/uploads/' . $rutaLimpia) && !is_dir(__DIR__ . '/../../public/uploads/' . $rutaLimpia)) {
            $srcImagen = '/uploads/' . $rutaLimpia;
        } elseif (file_exists(__DIR__ . '/../../public/img/presets/' . $rutaLimpia) && !is_dir(__DIR__ . '/../../public/img/presets/' . $rutaLimpia)) {
            $srcImagen = '/img/presets/' . $rutaLimpia;
        }
    }
}

// Fallback estático y seguro en SVG ante imágenes inexistentes o no cargadas
if ($srcImagen === null) {
    $srcImagen = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='800' height='450' viewBox='0 0 800 450'%3E%3Crect width='800' height='450' fill='%23f1f5f9' rx='8'/%3E%3Cpath d='M260 310l90-120 75 90 105-150 150 180H260z' fill='%23cbd5e1'/%3E%3Ccircle cx='350' cy='170' r='45' fill='%2394a3b8'/%3E%3Ctext x='50%25' y='82%25' text-anchor='middle' font-family='sans-serif' font-size='20' font-weight='600' fill='%2364748b'%3EFotograf%C3%ADa no disponible%3C/text%3E%3C/svg%3E";
}

// -----------------------------------------------------------------------------
// 6. CONTEXTO DE LAYOUT Y TÍTULO DINÁMICO
// -----------------------------------------------------------------------------
$tituloPagina = "Ficha Técnica: " . $espacio->getNombre();

// Inclusión del encabezado global (abre la etiqueta semántica <main class="contenedor principal">)
require_once __DIR__ . '/../../views/layout/encabezado.php';
?>

<!-- Estilos específicos para la Ficha Técnica -->
<style>
    /* Reseteo para encabezados semánticos que colisionan con el selector global 'header' */
    .ficha-tecnica header,
    .ficha-tecnica .panel-header {
        background: transparent !important;
        background-color: transparent !important;
        box-shadow: none !important;
        position: static !important;
        color: inherit !important;
        padding: 0 !important;
        top: auto !important;
        z-index: auto !important;
    }

    .ficha-tecnica {
        display: flex;
        flex-direction: column;
        gap: 1.75rem;
        animation: fadeIn 0.3s ease-in-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(6px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Barra de navegación superior y acciones */
    .ficha-nav-superior {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
        padding-bottom: 1.25rem;
        border-bottom: 1px solid var(--color-borde);
    }

    .ficha-encabezado-titulos h1 {
        margin: 0 0 0.4rem 0;
        font-size: 2rem;
        font-weight: 800;
        color: var(--color-secundario);
        letter-spacing: -0.02em;
    }

    .ficha-badges-info {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        flex-wrap: wrap;
    }

    .badge-tipo-ficha {
        background-color: #f1f5f9;
        color: var(--color-secundario);
        font-weight: 600;
        font-size: 0.85rem;
        padding: 0.25rem 0.75rem;
        border-radius: 4px;
        border: 1px solid var(--color-borde);
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }

    .badge-estado-activo {
        background-color: var(--color-exito-fondo);
        color: var(--color-exito);
        border: 1px solid #bbf7d0;
        font-weight: 600;
        font-size: 0.8rem;
        padding: 0.25rem 0.65rem;
        border-radius: 9999px;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }

    /* Grid principal de la Ficha Técnica */
    .ficha-grid {
        display: grid;
        grid-template-columns: 1.7fr 1.3fr;
        gap: 1.75rem;
        align-items: start;
    }

    @media (max-width: 900px) {
        .ficha-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Sección multimedia / Hero card */
    .ficha-multimedia {
        margin: 0 0 1.5rem 0;
        background-color: #f1f5f9;
        border: 1px solid var(--color-borde);
        border-radius: var(--radio-lg);
        overflow: hidden;
        box-shadow: var(--sombra-sm);
    }

    .ficha-multimedia .hero-foto {
        width: 100%;
        height: 380px;
        object-fit: cover;
        display: block;
        transition: transform 0.3s ease;
    }

    @media (max-width: 600px) {
        .ficha-multimedia .hero-foto {
            height: 230px;
        }
    }

    .ficha-multimedia figcaption {
        padding: 0.65rem 1rem;
        background: #f8fafc;
        border-top: 1px solid var(--color-borde);
        font-size: 0.825rem;
        color: var(--color-texto-mutado);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* Lista semántica de especificaciones técnicas (dl, dt, dd) */
    .lista-atributos {
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
        margin: 1.25rem 0 0 0;
    }

    .item-atributo {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        padding: 0.75rem 1rem;
        background-color: #f8fafc;
        border: 1px solid var(--color-borde);
        border-radius: var(--radio-md);
        gap: 1rem;
    }

    @media (max-width: 600px) {
        .item-atributo {
            flex-direction: column;
            gap: 0.25rem;
        }
    }

    .etiqueta-atributo {
        font-weight: 700;
        color: var(--color-secundario);
        font-size: 0.875rem;
        min-width: 170px;
    }

    .valor-atributo {
        margin: 0;
        color: var(--color-texto);
        font-size: 0.9rem;
        text-align: right;
    }

    @media (max-width: 600px) {
        .valor-atributo {
            text-align: left;
        }
    }

    /* Tabla de tarifas calculadas */
    .tabla-tarifas th {
        font-size: 0.8rem;
    }

    .tabla-tarifas td {
        padding: 0.75rem 1rem;
    }

    /* Lista de reservas asociadas */
    .lista-reservas {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .item-reserva {
        padding: 0.85rem 1rem;
        background: #f8fafc;
        border-radius: var(--radio-md);
        border: 1px solid var(--color-borde);
        font-size: 0.875rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.75rem;
    }
</style>

<!-- ======================================================================= -->
<!-- FICHA TÉCNICA DEL ESPACIO (HTML5 Semántico)                             -->
<!-- ======================================================================= -->
<article class="ficha-tecnica">

    <!-- Barra de Navegación y Acciones Rápidas -->
    <nav class="ficha-nav-superior" aria-label="Navegación de la ficha técnica">
        <div>
            <!-- [SEGURIDAD] Escapado riguroso con htmlspecialchars para prevenir XSS -->
            <a href="index.php" class="btn btn-secundario" style="margin-bottom: 0.6rem; font-size: 0.85rem; padding: 0.35rem 0.8rem;">
                &larr; Volver al Catálogo
            </a>
            <div class="ficha-encabezado-titulos">
                <!-- [SEGURIDAD] Escapado riguroso con htmlspecialchars para prevenir XSS -->
                <h1><?= e($nombreEspacio) ?></h1>
                <div class="ficha-badges-info">
                    <!-- [SEGURIDAD] Escapado riguroso con htmlspecialchars para prevenir XSS -->
                    <span class="badge-tipo-ficha">
                        <?= e($tipoLegible) ?>
                    </span>
                    <span class="badge-estado-activo">
                        <svg width="10" height="10" viewBox="0 0 10 10" fill="currentColor" aria-hidden="true">
                            <circle cx="5" cy="5" r="4"/>
                        </svg>
                        Disponible para Reservas
                    </span>
                </div>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
            <!-- [CRUD-UPDATE] Enlace al formulario de edición con precarga -->
            <a href="editar.php?id=<?= e((string) $idSolicitado) ?>" class="btn btn-secundario" style="font-weight: 600; padding: 0.75rem 1.1rem;">
                ✏️ Editar Espacio
            </a>

            <!-- [CRUD-DELETE] Formulario de borrado seguro estrictamente por POST con CSRF -->
            <form action="eliminar.php" method="POST" onsubmit="return confirm('¿Estás seguro de que deseas eliminar permanentemente este espacio? Esta acción no se puede deshacer.');" style="display: inline; margin: 0;">
                <?= Csrf::campoHtml() ?>
                <input type="hidden" name="id" value="<?= e((string) $idSolicitado) ?>">
                <button type="submit" class="btn btn-peligro" style="font-weight: 600; padding: 0.75rem 1.1rem;">
                    🗑️ Eliminar
                </button>
            </form>

            <!-- [SEGURIDAD] Escapado riguroso con htmlspecialchars para prevenir XSS -->
            <a href="/reservas/crear.php?espacio_id=<?= e((string) $idSolicitado) ?>" class="btn btn-acento" style="font-weight: 700; padding: 0.75rem 1.4rem;">
                Reservar este Espacio &rarr;
            </a>
        </div>
    </nav>

    <!-- Contenedor Principal en Grid -->
    <div class="ficha-grid">

        <!-- Columna Izquierda: Sección Multimedia y Especificaciones Técnicas -->
        <section class="ficha-columna-detalles" aria-label="Detalles generales y técnicos del espacio">
            
            <!-- Sección Multimedia / Hero Card -->
            <figure class="ficha-multimedia">
                <!-- [SEGURIDAD] Escapado riguroso con htmlspecialchars para prevenir XSS -->
                <img 
                    src="<?= e($srcImagen) ?>" 
                    alt="Fotografía de <?= e($nombreEspacio) ?>" 
                    class="hero-foto" 
                    width="800" 
                    height="450" 
                    loading="eager"
                >
                <figcaption>
                    <!-- [SEGURIDAD] Escapado riguroso con htmlspecialchars para prevenir XSS -->
                    <span><strong><?= e($nombreEspacio) ?></strong> &bull; <?= e($tipoLegible) ?></span>
                    <span>Capacidad: <strong><?= e((string) $capacidad) ?> <?= $capacidad === 1 ? 'persona' : 'personas' ?></strong></span>
                </figcaption>
            </figure>

            <!-- Datos Generales Comunes -->
            <div class="panel" style="margin-bottom: 1.5rem;">
                <header class="panel-header">
                    <h2>Descripción General</h2>
                </header>
                <div class="panel-cuerpo">
                    <!-- [SEGURIDAD] Escapado riguroso con htmlspecialchars para prevenir XSS -->
                    <p style="font-size: 1.05rem; line-height: 1.7; margin-bottom: 1.25rem;">
                        <?= e($descripcionEspacio) ?>
                    </p>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--color-borde);">
                        <div>
                            <span style="font-size: 0.8rem; color: var(--color-texto-mutado); display: block;">Capacidad Máxima</span>
                            <strong style="font-size: 1.15rem; color: var(--color-secundario);">
                                <!-- [SEGURIDAD] Escapado riguroso con htmlspecialchars para prevenir XSS -->
                                <?= e((string) $capacidad) ?> <?= $capacidad === 1 ? 'persona' : 'personas' ?>
                            </strong>
                        </div>
                        <div>
                            <span style="font-size: 0.8rem; color: var(--color-texto-mutado); display: block;">Categoría de Espacio</span>
                            <strong style="font-size: 1.15rem; color: var(--color-secundario);">
                                <!-- [SEGURIDAD] Escapado riguroso con htmlspecialchars para prevenir XSS -->
                                <?= e($tipoLegible) ?>
                            </strong>
                        </div>
                        <div>
                            <span style="font-size: 0.8rem; color: var(--color-texto-mutado); display: block;">Identificador de Registro</span>
                            <strong style="font-size: 1.15rem; color: var(--color-secundario);">
                                <!-- [SEGURIDAD] Escapado riguroso con htmlspecialchars para prevenir XSS -->
                                #<?= e((string) $idSolicitado) ?>
                            </strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Especificaciones Técnicas Particulares (Delegación Polimórfica Pura) -->
            <div class="panel">
                <header class="panel-header">
                    <h2>Especificaciones Técnicas Particulares</h2>
                </header>
                <div class="panel-cuerpo">
                    <p style="font-size: 0.9rem; color: var(--color-texto-mutado); margin-bottom: 0.5rem;">
                        Equipamiento y condiciones específicas resueltas de forma modular por la entidad correspondiente:
                    </p>

                    <!-- Lista Semántica de Atributos Particulares -->
                    <dl class="lista-atributos">
                        <!-- [POLIMORFISMO] Características particulares resueltas por delegación al objeto sin comprobación de tipos -->
                        <?php foreach ($caracteristicas as $etiqueta => $valor): ?>
                            <div class="item-atributo">
                                <!-- [SEGURIDAD] Escapado riguroso con htmlspecialchars para prevenir XSS -->
                                <dt class="etiqueta-atributo"><?= e((string) $etiqueta) ?></dt>
                                <dd class="valor-atributo"><?= e((string) $valor) ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                </div>
            </div>

        </section>

        <!-- Columna Derecha: Tarifas Polimórficas y Reservas Asociadas -->
        <aside class="ficha-columna-tarifas" aria-label="Tarificación y programación de reservas">
            
            <!-- Panel de Cálculo de Tarifas Polimórficas -->
            <article class="panel" style="margin-bottom: 1.5rem;">
                <header class="panel-header">
                    <h3>Tarifas Calculadas (Polimorfismo)</h3>
                </header>
                <div class="panel-cuerpo">
                    <p style="font-size: 0.875rem; color: var(--color-texto-mutado); margin-bottom: 1rem;">
                        Cálculo en tiempo de ejecución invocando <code>$espacio-&gt;calcularTarifa()</code> de acuerdo al modelo de negocio:
                    </p>

                    <div class="tabla-responsive">
                        <table class="tabla-datos tabla-tarifas">
                            <thead>
                                <tr>
                                    <th scope="col">Escenario / Duración</th>
                                    <th scope="col" style="text-align: right;">Total Estimado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <strong>1 Hora</strong>
                                        <small style="color: var(--color-texto-mutado); display: block; font-size: 0.75rem;">Bloque o fracción regular</small>
                                    </td>
                                    <td style="text-align: right;">
                                        <!-- [SEGURIDAD] Escapado riguroso con htmlspecialchars para prevenir XSS -->
                                        <strong style="color: var(--color-secundario);">$ <?= e(number_format($tarifa1h, 2)) ?></strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <strong>2 Horas (Estándar)</strong>
                                        <small style="color: var(--color-texto-mutado); display: block; font-size: 0.75rem;">Duración promedio sugerida</small>
                                    </td>
                                    <td style="text-align: right;">
                                        <!-- [SEGURIDAD] Escapado riguroso con htmlspecialchars para prevenir XSS -->
                                        <strong style="color: var(--color-acento); font-size: 1.05rem;">$ <?= e(number_format($tarifa2h, 2)) ?></strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <strong>4 Horas</strong>
                                        <small style="color: var(--color-texto-mutado); display: block; font-size: 0.75rem;">Media jornada de uso</small>
                                    </td>
                                    <td style="text-align: right;">
                                        <!-- [SEGURIDAD] Escapado riguroso con htmlspecialchars para prevenir XSS -->
                                        <strong style="color: var(--color-secundario);">$ <?= e(number_format($tarifa4h, 2)) ?></strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <strong>2 Horas en Horario Pico</strong>
                                        <small style="color: var(--color-texto-mutado); display: block; font-size: 0.75rem;">Con recargo aplicado por tipo</small>
                                    </td>
                                    <td style="text-align: right;">
                                        <!-- [SEGURIDAD] Escapado riguroso con htmlspecialchars para prevenir XSS -->
                                        <strong style="color: var(--color-aviso); font-size: 1.05rem;">$ <?= e(number_format($tarifa2hPico, 2)) ?></strong>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top: 1.25rem; text-align: center;">
                        <!-- [SEGURIDAD] Escapado riguroso con htmlspecialchars para prevenir XSS -->
                        <a href="/reservas/crear.php?espacio_id=<?= e((string) $idSolicitado) ?>" class="btn btn-primario" style="width: 100%; font-weight: 600;">
                            Programar Nueva Reserva
                        </a>
                    </div>
                </div>
            </article>

            <!-- Panel de Reservas Asociadas -->
            <article class="panel">
                <header class="panel-header">
                    <h3>Reservas Programadas</h3>
                </header>
                <div class="panel-cuerpo">
                    <?php if (empty($reservasEspacio)): ?>
                        <div style="text-align: center; padding: 2rem 1rem; color: var(--color-texto-mutado);">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto 0.75rem auto; opacity: 0.6; display: block;" aria-hidden="true">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/>
                                <line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                            <p style="margin: 0; font-size: 0.9rem;">No hay reservas programadas actualmente para este espacio.</p>
                            <small style="font-size: 0.8rem; color: var(--color-texto-mutado);">¡Sé el primero en reservar!</small>
                        </div>
                    <?php else: ?>
                        <ul class="lista-reservas">
                            <?php foreach ($reservasEspacio as $res): ?>
                                <li class="item-reserva">
                                    <div>
                                        <!-- [SEGURIDAD] Escapado riguroso con htmlspecialchars para prevenir XSS -->
                                        <strong style="color: var(--color-secundario); display: block; font-size: 0.9rem;">
                                            <?= e($res['cliente'] ?? 'Cliente') ?>
                                        </strong>
                                        <span style="color: var(--color-texto-mutado); font-size: 0.8rem;">
                                            <?= e($res['fecha'] ?? '') ?> &bull; <?= e($res['hora_inicio'] ?? '') ?> - <?= e($res['hora_fin'] ?? '') ?>
                                        </span>
                                    </div>
                                    <div style="text-align: right;">
                                        <!-- [SEGURIDAD] Escapado riguroso con htmlspecialchars para prevenir XSS -->
                                        <strong style="color: var(--color-acento); font-size: 0.95rem;">
                                            $ <?= e(number_format((float) ($res['monto_total'] ?? 0), 2)) ?>
                                        </strong>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </article>

        </aside>

    </div>

</article>

<?php
// Inclusión del pie de página global (cierra la etiqueta </main> y el documento HTML)
require_once __DIR__ . '/../../views/layout/pie.php';
