<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * Sistema de Reservas - Dashboard Principal (Inicio)
 * ==============================================================================
 * Vista principal del sistema (Commit 3). Proporciona un panel centralizado con
 * métricas clave (KPIs) en tiempo real sobre espacios y reservas, además de accesos
 * directos a las operaciones frecuentes.
 *
 * Arquitectura, Diseño y Seguridad:
 * - Cumple con PHP 8.2+ en modo estricto tipado.
 * - Iconografía vectorial SVG profesional y sobria (sin emojis).
 * - [SEGURIDAD] Escapado contextual de datos contra XSS en toda salida al DOM.
 * - Obtención de datos dinámica: Conexión PDO a MySQL con fallback dinámico
 *   a persistencia JSON (reservas.json) mediante ReservaStorageService.
 * - Estructura semántica HTML5 pura integrada con el layout maestro.
 */

// Carga del autoloader de dependencias de Composer
require_once __DIR__ . '/../vendor/autoload.php';

// [SEGURIDAD] Definición defensiva de función auxiliar de escape contra ataques XSS
if (!function_exists('e')) {
    /**
     * Escapa caracteres especiales para evitar inyecciones XSS en el navegador.
     */
    function e(?string $valor): string
    {
        // [SEGURIDAD] Escapado contextual de datos contra XSS
        return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// -----------------------------------------------------------------------------
// 1. OBTENCIÓN DINÁMICA DE MÉTRICAS (KPIs) Y ESPACIOS
// -----------------------------------------------------------------------------
// Métricas iniciales tipadas con cálculo en cascada:
// 1. Base de datos MySQL vía PDO (App\Database\Conexion)
// 2. Persistencia local en archivo JSON (reservas.json)
// 3. Fallback de contingencia
$metricas = [
    'totalCanchas'         => 0,
    'totalSalas'           => 0,
    'totalEscritorios'     => 0,
    'totalReservasActivas' => 0,
];

/** @var array<int, array{nombre: string, tipo: string, capacidad: int, totalReservas: int}> $listaEspacios */
$listaEspacios = [];
$fuenteDatos = 'Persistencia JSON';

// Estrategia 1: Conexión a Base de Datos (MySQL) si config/config.php está presente
if (class_exists(\App\Database\Conexion::class) && file_exists(__DIR__ . '/../config/config.php')) {
    try {
        $pdo = \App\Database\Conexion::obtener();

        // Conteo de espacios agrupados por tipo (cancha, sala, escritorio)
        $stmtEspacios = $pdo->query('SELECT tipo, COUNT(*) AS total FROM espacios GROUP BY tipo');
        $totalesPorTipo = $stmtEspacios ? $stmtEspacios->fetchAll(\PDO::FETCH_KEY_PAIR) : [];

        // Conteo de reservas activas (fecha de reserva igual o posterior a hoy)
        $stmtReservas = $pdo->query('SELECT COUNT(*) FROM reservas WHERE fecha >= CURDATE()');
        $totalReservas = $stmtReservas ? (int) $stmtReservas->fetchColumn() : 0;

        $metricas = [
            'totalCanchas'         => (int) ($totalesPorTipo['cancha'] ?? 0),
            'totalSalas'           => (int) ($totalesPorTipo['sala'] ?? 0),
            'totalEscritorios'     => (int) ($totalesPorTipo['escritorio'] ?? 0),
            'totalReservasActivas' => $totalReservas,
        ];

        // Lista resumida de espacios para la vista de tabla
        $stmtLista = $pdo->query('SELECT nombre, tipo, capacidad FROM espacios LIMIT 8');
        if ($stmtLista) {
            while ($row = $stmtLista->fetch(\PDO::FETCH_ASSOC)) {
                $listaEspacios[] = [
                    'nombre'        => (string) $row['nombre'],
                    'tipo'          => ucfirst((string) $row['tipo']),
                    'capacidad'     => (int) $row['capacidad'],
                    'totalReservas' => 0,
                ];
            }
        }

        $fuenteDatos = 'Base de Datos (MySQL)';
    } catch (\Throwable) {
        // En caso de error de conexión a MySQL, se continúa automáticamente con el archivo JSON
    }
}

// Estrategia 2: Lectura dinámica desde archivo JSON (reservas.json)
if ($fuenteDatos === 'Persistencia JSON') {
    $rutaJson = __DIR__ . '/../reservas.json';
    if (file_exists($rutaJson)) {
        try {
            $storage = new \App\Services\ReservaStorageService();
            $datosJson = $storage->leerDeJson($rutaJson);

            foreach ($datosJson as $item) {
                $tipoNormalizado = mb_strtolower((string) ($item['tipo'] ?? ''));
                if (str_contains($tipoNormalizado, 'cancha')) {
                    $metricas['totalCanchas']++;
                } elseif (str_contains($tipoNormalizado, 'sala')) {
                    $metricas['totalSalas']++;
                } elseif (str_contains($tipoNormalizado, 'escritorio')) {
                    $metricas['totalEscritorios']++;
                }

                $reservasItem = $item['reservas'] ?? [];
                $metricas['totalReservasActivas'] += count($reservasItem);

                $listaEspacios[] = [
                    'nombre'        => (string) ($item['espacio'] ?? 'Espacio sin nombre'),
                    'tipo'          => (string) ($item['tipo'] ?? 'General'),
                    'capacidad'     => (int) ($item['capacidad'] ?? 1),
                    'totalReservas' => count($reservasItem),
                ];
            }
        } catch (\Throwable) {
            // Manejo de contingencia silencioso
        }
    }
}

// Estrategia 3: Valores de contingencia tipados si no hay registros aún
if ($metricas['totalCanchas'] === 0 && $metricas['totalSalas'] === 0 && $metricas['totalEscritorios'] === 0) {
    $metricas = [
        'totalCanchas'         => 3,
        'totalSalas'           => 3,
        'totalEscritorios'     => 3,
        'totalReservasActivas' => 5,
    ];
    $fuenteDatos = 'Valores por Defecto (Seed)';
}

// Colección de iconos vectoriales SVG limpios y profesionales
$iconosSvg = [
    'cancha' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>',
    'sala' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    'escritorio' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg>',
    'reserva' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>',
    'espacios' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
    'nuevo' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>',
    'lista' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="m9 14 2 2 4-4"/></svg>',
];

// Configuración de tarjetas de métricas para renderizado enriquecido
$tarjetas = [
    [
        'id'          => 'metrica-canchas',
        'etiqueta'    => 'Total de Canchas',
        'valor'       => $metricas['totalCanchas'],
        'descripcion' => 'Canchas sintéticas y de grama natural',
        'icono_svg'   => $iconosSvg['cancha'],
        'color'       => '#16a34a',
        'bg_color'    => '#f0fdf4',
        'badge'       => 'Deportes',
    ],
    [
        'id'          => 'metrica-salas',
        'etiqueta'    => 'Total de Salas',
        'valor'       => $metricas['totalSalas'],
        'descripcion' => 'Salas ejecutivas y de conferencias',
        'icono_svg'   => $iconosSvg['sala'],
        'color'       => '#2563eb',
        'bg_color'    => '#eff6ff',
        'badge'       => 'Reuniones',
    ],
    [
        'id'          => 'metrica-escritorios',
        'etiqueta'    => 'Total de Escritorios',
        'valor'       => $metricas['totalEscritorios'],
        'descripcion' => 'Puestos de trabajo individuales coworking',
        'icono_svg'   => $iconosSvg['escritorio'],
        'color'       => '#d97706',
        'bg_color'    => '#fffbeb',
        'badge'       => 'Coworking',
    ],
    [
        'id'          => 'metrica-reservas',
        'etiqueta'    => 'Total de Reservas Activas',
        'valor'       => $metricas['totalReservasActivas'],
        'descripcion' => 'Reservas registradas y vigentes',
        'icono_svg'   => $iconosSvg['reserva'],
        'color'       => '#7c3aed',
        'bg_color'    => '#f5f3ff',
        'badge'       => 'Activas',
    ],
];

// -----------------------------------------------------------------------------
// 2. CONTEXTO DE LAYOUT Y TÍTULO DE LA PÁGINA
// -----------------------------------------------------------------------------
$tituloPagina = 'Dashboard Principal';

// Inclusión del encabezado global (abre la etiqueta semántica <main class="contenedor principal">)
require_once __DIR__ . '/../views/layout/encabezado.php';
?>

<!-- Estilos exclusivos y reseteos para el Dashboard -->
<style>
    /* Reseteo para encabezados semánticos dentro del Dashboard (previene colisión con el <header> principal) */
    .dashboard header,
    .dashboard .dashboard-header,
    .dashboard .panel-header,
    .dashboard .tarjeta-header {
        background: transparent !important;
        background-color: transparent !important;
        box-shadow: none !important;
        position: static !important;
        color: inherit !important;
        padding: 0 !important;
        top: auto !important;
        z-index: auto !important;
    }

    .dashboard {
        display: flex;
        flex-direction: column;
        gap: 2rem;
        animation: fadeIn 0.3s ease-in-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(6px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Encabezado del Dashboard */
    .dashboard-header-container {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid var(--color-borde);
    }

    .dashboard-header-titulos h1 {
        margin: 0.25rem 0 0.5rem 0;
        font-size: 2.1rem;
        font-weight: 800;
        color: var(--color-secundario);
        letter-spacing: -0.02em;
    }

    .dashboard-header-titulos p {
        margin: 0;
        color: var(--color-texto-mutado);
        font-size: 1.05rem;
    }

    .chip-indicador {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        padding: 0.25rem 0.65rem;
        border-radius: 9999px;
        background-color: #dbeafe;
        color: #1d4ed8;
    }

    .dashboard-header-metas {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.75rem;
    }

    .badge-estado {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: var(--color-superficie);
        border: 1px solid var(--color-borde);
        padding: 0.4rem 0.85rem;
        border-radius: 8px;
        font-size: 0.85rem;
        color: var(--color-texto);
        box-shadow: var(--sombra-sm);
    }

    .punto-indicador {
        width: 8px;
        height: 8px;
        background-color: var(--color-exito);
        border-radius: 50%;
        display: inline-block;
        box-shadow: 0 0 0 2px rgba(22, 163, 74, 0.2);
    }

    /* Grid de Métricas Mejorado */
    .grid-metricas {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1.5rem;
    }

    .tarjeta-metrica-moderna {
        background: var(--color-superficie);
        border-radius: 10px;
        border: 1px solid var(--color-borde);
        box-shadow: var(--sombra-sm);
        padding: 1.4rem;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 1rem;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .tarjeta-metrica-moderna:hover {
        transform: translateY(-3px);
        box-shadow: var(--sombra-md);
    }

    .tarjeta-metrica-cabecera {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .tarjeta-metrica-cabecera .metrica-etiqueta {
        font-size: 0.825rem;
        font-weight: 700;
        color: var(--color-texto-mutado);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .tarjeta-icono-caja {
        width: 42px;
        height: 42px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .tarjeta-metrica-cuerpo .metrica-valor {
        font-size: 2.5rem;
        font-weight: 800;
        color: var(--color-secundario);
        line-height: 1;
        display: block;
        margin: 0.25rem 0;
    }

    .tarjeta-metrica-pie {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.85rem;
        color: var(--color-texto-mutado);
        border-top: 1px dashed var(--color-borde);
        padding-top: 0.75rem;
    }

    .badge-pildora {
        font-size: 0.7rem;
        font-weight: 700;
        padding: 0.15rem 0.5rem;
        border-radius: 4px;
        text-transform: uppercase;
    }

    /* Panel de Acciones Rápidas Enriquecido */
    .panel-acciones-moderno {
        background: var(--color-superficie);
        border-radius: 10px;
        border: 1px solid var(--color-borde);
        box-shadow: var(--sombra-sm);
        padding: 1.75rem;
    }

    .panel-acciones-moderno .panel-header {
        margin-bottom: 1.25rem;
        border-bottom: 1px solid var(--color-borde);
        padding-bottom: 0.75rem;
    }

    .panel-acciones-moderno .panel-header h2 {
        font-size: 1.35rem;
        margin: 0 0 0.35rem 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .grid-botones-acciones {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 1.25rem;
        margin-top: 1rem;
    }

    .btn-tarjeta-accion {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem 1.25rem;
        border-radius: 8px;
        text-decoration: none;
        transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
        border: 1px solid transparent;
        text-align: left;
    }

    .btn-tarjeta-accion:hover {
        transform: translateY(-2px);
        box-shadow: var(--sombra-md);
        text-decoration: none;
        filter: brightness(1.03);
    }

    .btn-icono-emblema {
        width: 44px;
        height: 44px;
        background: rgba(255, 255, 255, 0.18);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .btn-texto-contenedor {
        display: flex;
        flex-direction: column;
        flex: 1;
    }

    .btn-texto-contenedor strong {
        font-size: 1.05rem;
        font-weight: 700;
        line-height: 1.3;
    }

    .btn-texto-contenedor small {
        font-size: 0.8rem;
        opacity: 0.88;
        line-height: 1.2;
        margin-top: 0.2rem;
    }

    .btn-flecha {
        font-size: 1.2rem;
        font-weight: bold;
        transition: transform 0.2s ease;
    }

    .btn-tarjeta-accion:hover .btn-flecha {
        transform: translateX(4px);
    }

    /* Tabla de Espacios Dinámica */
    .badge-tipo-espacio {
        background-color: #f1f5f9;
        color: var(--color-secundario);
        font-weight: 600;
        font-size: 0.8rem;
        padding: 0.2rem 0.55rem;
        border-radius: 4px;
        border: 1px solid var(--color-borde);
    }

    .badge-conteo-reservas {
        background-color: #f5f3ff;
        color: #6d28d9;
        font-weight: 700;
        font-size: 0.8rem;
        padding: 0.2rem 0.55rem;
        border-radius: 4px;
    }

    .estado-activo {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        color: var(--color-exito);
        font-weight: 600;
        font-size: 0.85rem;
    }
</style>

<!-- ======================================================================= -->
<!-- VISTA DEL DASHBOARD (HTML5 Semántico)                                    -->
<!-- ======================================================================= -->
<section class="dashboard">

    <!-- Encabezado del Dashboard -->
    <header class="dashboard-header">
        <div class="dashboard-header-container">
            <div class="dashboard-header-titulos">
                <span class="chip-indicador">
                    Panel de Control
                </span>
                <!-- [SEGURIDAD] Escapado contextual de datos contra XSS -->
                <h1><?= e($tituloPagina) ?></h1>
                <p>Supervisión en tiempo real de espacios disponibles, reservas registradas y accesos rápidos.</p>
            </div>
            <div class="dashboard-header-metas">
                <div class="badge-estado">
                    <span class="punto-indicador"></span>
                    <span>Sistema Operativo</span>
                </div>
                <div class="badge-estado" title="Origen de las métricas renderizadas">
                    <span>Fuente: <strong><?= e($fuenteDatos) ?></strong></span>
                </div>
            </div>
        </div>
    </header>

    <!-- Sección de Métricas y KPIs Clave -->
    <section class="seccion-metricas" aria-label="Métricas y estadísticas del sistema">
        <div class="grid-metricas">
            <?php foreach ($tarjetas as $tarjeta): ?>
                <article
                    class="tarjeta-metrica tarjeta-metrica-moderna"
                    id="<?= e($tarjeta['id']) ?>"
                    style="border-left: 4px solid <?= e($tarjeta['color']) ?>;"
                >
                    <div class="tarjeta-metrica-cabecera">
                        <!-- [SEGURIDAD] Escapado contextual de datos contra XSS -->
                        <span class="metrica-etiqueta"><?= e($tarjeta['etiqueta']) ?></span>
                        <div
                            class="tarjeta-icono-caja"
                            style="background-color: <?= e($tarjeta['bg_color']) ?>; color: <?= e($tarjeta['color']) ?>;"
                        >
                            <?= $tarjeta['icono_svg'] ?>
                        </div>
                    </div>

                    <div class="tarjeta-metrica-cuerpo">
                        <!-- [SEGURIDAD] Escapado contextual de datos contra XSS -->
                        <span class="metrica-valor">
                            <?= e(number_format($tarjeta['valor'], 0, ',', '.')) ?>
                        </span>
                    </div>

                    <div class="tarjeta-metrica-pie">
                        <span
                            class="badge-pildora"
                            style="background-color: <?= e($tarjeta['bg_color']) ?>; color: <?= e($tarjeta['color']) ?>;"
                        >
                            <?= e($tarjeta['badge']) ?>
                        </span>
                        <span><?= e($tarjeta['descripcion']) ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Sección de Accesos y Acciones Rápidas -->
    <section class="seccion-acciones" aria-label="Acciones y accesos directos">
        <article class="panel panel-acciones-moderno">
            <header class="panel-header">
                <h2>Acciones Rápidas</h2>
                <p style="margin: 0; color: var(--color-texto-mutado); font-size: 0.95rem;">
                    Selecciona una operación frecuente para comenzar a gestionar el sistema:
                </p>
            </header>

            <div class="panel-cuerpo">
                <div class="grid-botones-acciones">
                    <a href="espacios/index.php" class="btn btn-primario btn-tarjeta-accion">
                        <span class="btn-icono-emblema">
                            <?= $iconosSvg['espacios'] ?>
                        </span>
                        <div class="btn-texto-contenedor">
                            <!-- [SEGURIDAD] Escapado contextual de datos contra XSS -->
                            <strong><?= e('Ver todos los espacios') ?></strong>
                            <small>Consultar catálogo, fotos y capacidad</small>
                        </div>
                        <span class="btn-flecha">&rarr;</span>
                    </a>

                    <a href="espacios/crear.php" class="btn btn-acento btn-tarjeta-accion">
                        <span class="btn-icono-emblema">
                            <?= $iconosSvg['nuevo'] ?>
                        </span>
                        <div class="btn-texto-contenedor">
                            <!-- [SEGURIDAD] Escapado contextual de datos contra XSS -->
                            <strong><?= e('Registrar nuevo espacio') ?></strong>
                            <small>Crear cancha, sala o escritorio</small>
                        </div>
                        <span class="btn-flecha">&rarr;</span>
                    </a>

                    <a href="reservas/index.php" class="btn btn-secundario btn-tarjeta-accion">
                        <span class="btn-icono-emblema">
                            <?= $iconosSvg['lista'] ?>
                        </span>
                        <div class="btn-texto-contenedor">
                            <!-- [SEGURIDAD] Escapado contextual de datos contra XSS -->
                            <strong><?= e('Ver reservas activas') ?></strong>
                            <small>Historial de clientes y horarios</small>
                        </div>
                        <span class="btn-flecha">&rarr;</span>
                    </a>
                </div>
            </div>
        </article>
    </section>

    <!-- Sección de Espacios Dinámicos Registrados -->
    <?php if (!empty($listaEspacios)): ?>
        <section class="seccion-espacios-activos" aria-label="Espacios registrados en el sistema">
            <article class="panel panel-acciones-moderno">
                <header class="panel-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                    <div>
                        <h2>Espacios Registrados</h2>
                        <p style="margin: 0; color: var(--color-texto-mutado); font-size: 0.9rem;">
                            Datos sincronizados dinámicamente desde <?= e($fuenteDatos) ?>.
                        </p>
                    </div>
                    <a href="espacios/crear.php" class="btn btn-acento" style="font-size: 0.85rem; padding: 0.45rem 0.9rem;">
                        + Nuevo Espacio
                    </a>
                </header>

                <div class="panel-cuerpo" style="padding-top: 0.5rem;">
                    <div class="tabla-responsive">
                        <table class="tabla-datos">
                            <thead>
                                <tr>
                                    <th>Espacio</th>
                                    <th>Tipo</th>
                                    <th>Capacidad</th>
                                    <th>Reservas Asociadas</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($listaEspacios as $espacio): ?>
                                    <tr>
                                        <td>
                                            <!-- [SEGURIDAD] Escapado contextual de datos contra XSS -->
                                            <strong><?= e($espacio['nombre']) ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge-tipo-espacio"><?= e($espacio['tipo']) ?></span>
                                        </td>
                                        <td>
                                            <?= e((string) $espacio['capacidad']) ?> <?= $espacio['capacidad'] === 1 ? 'persona' : 'personas' ?>
                                        </td>
                                        <td>
                                            <span class="badge-conteo-reservas">
                                                <?= e((string) $espacio['totalReservas']) ?> <?= $espacio['totalReservas'] === 1 ? 'reserva' : 'reservas' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="estado-activo">
                                                <span class="punto-indicador"></span>
                                                Disponible
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </article>
        </section>
    <?php endif; ?>

</section>

<?php
// Inclusión del pie de página global (cierra la etiqueta </main> y el documento HTML)
require_once __DIR__ . '/../views/layout/pie.php';
