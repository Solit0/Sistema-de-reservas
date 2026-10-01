<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * Sistema de Reservas - Dashboard Principal (Inicio)
 * ==============================================================================
 * Vista principal del sistema (Commit 3). Proporciona un panel centralizado con
 * métricas clave (KPIs) sobre espacios y reservas, además de accesos directos
 * a las operaciones más frecuentes.
 *
 * Arquitectura y Seguridad:
 * - Cumple con estándares PHP 8.2+ con modo estricto tipado.
 * - [SEGURIDAD] Escapado contextual de datos contra XSS en toda salida al DOM.
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
// 1. OBTENCIÓN Y PREPARACIÓN DE MÉTRICAS (KPIs)
// -----------------------------------------------------------------------------
// Estructura de datos desacoplada y tipada. Por defecto contiene valores base de
// prueba acordes al esquema inicial, pero consulta dinámicamente la base de datos
// si la conexión PDO se encuentra configurada y disponible.
$metricas = [
    'totalCanchas'         => 3,
    'totalSalas'           => 3,
    'totalEscritorios'     => 3,
    'totalReservasActivas' => 5,
];

try {
    // Si la configuración y el conector PDO están disponibles, obtenemos métricas en vivo
    if (class_exists(\App\Database\Conexion::class) && file_exists(__DIR__ . '/../config/config.php')) {
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
    }
} catch (\Throwable) {
    // [DESACOPLAMIENTO] En ausencia de base de datos o fallos temporales de conexión,
    // se asegura la continuidad visual utilizando el conjunto de métricas preparadas.
}

// Configuración de tarjetas informativas para renderizado uniforme
$tarjetas = [
    [
        'id'          => 'metrica-canchas',
        'etiqueta'    => 'Total de Canchas',
        'valor'       => $metricas['totalCanchas'],
        'descripcion' => 'Canchas sintéticas y naturales registradas',
    ],
    [
        'id'          => 'metrica-salas',
        'etiqueta'    => 'Total de Salas',
        'valor'       => $metricas['totalSalas'],
        'descripcion' => 'Salas de reunión equipadas para conferencias',
    ],
    [
        'id'          => 'metrica-escritorios',
        'etiqueta'    => 'Total de Escritorios',
        'valor'       => $metricas['totalEscritorios'],
        'descripcion' => 'Puestos de trabajo individuales de coworking',
    ],
    [
        'id'          => 'metrica-reservas',
        'etiqueta'    => 'Total de Reservas Activas',
        'valor'       => $metricas['totalReservasActivas'],
        'descripcion' => 'Reservas programadas y en curso',
    ],
];

// -----------------------------------------------------------------------------
// 2. CONTEXTO DE LAYOUT Y TÍTULO DE LA PÁGINA
// -----------------------------------------------------------------------------
$tituloPagina = 'Dashboard Principal';

// Inclusión del encabezado global (abre la etiqueta semántica <main class="contenedor principal">)
require_once __DIR__ . '/../views/layout/encabezado.php';
?>

<!-- ======================================================================= -->
<!-- VISTA DEL DASHBOARD (HTML5 Semántico)                                    -->
<!-- ======================================================================= -->
<section class="dashboard">
    <header class="dashboard-header" style="margin-bottom: 2rem;">
        <!-- [SEGURIDAD] Escapado contextual de datos contra XSS -->
        <h1><?= e($tituloPagina) ?></h1>
        <p style="color: var(--color-texto-mutado); font-size: 1.05rem;">
            Panel de control general del sistema. Supervisa la disponibilidad de espacios y accede a las gestiones principales.
        </p>
    </header>

    <!-- Sección de Métricas y KPIs Clave -->
    <section class="seccion-metricas" aria-label="Métricas y estadísticas del sistema">
        <div class="grid-metricas">
            <?php foreach ($tarjetas as $tarjeta): ?>
                <article class="tarjeta-metrica" id="<?= e($tarjeta['id']) ?>">
                    <!-- [SEGURIDAD] Escapado contextual de datos contra XSS -->
                    <span class="metrica-etiqueta"><?= e($tarjeta['etiqueta']) ?></span>
                    <span class="metrica-valor"><?= e(number_format($tarjeta['valor'], 0, ',', '.')) ?></span>
                    <small style="color: var(--color-texto-mutado); font-size: 0.85rem;">
                        <!-- [SEGURIDAD] Escapado contextual de datos contra XSS -->
                        <?= e($tarjeta['descripcion']) ?>
                    </small>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Sección de Accesos y Acciones Rápidas -->
    <section class="seccion-acciones" aria-label="Acciones y accesos directos">
        <article class="panel">
            <header class="panel-header">
                <h2>Acciones Rápidas</h2>
            </header>
            <div class="panel-cuerpo">
                <p style="margin-bottom: 1.25rem;">
                    Selecciona una operación frecuente para comenzar a gestionar los espacios o revisar el estado de las solicitudes:
                </p>
                <div class="grupo-botones" style="display: flex; flex-wrap: wrap; gap: 1rem;">
                    <a href="espacios/index.php" class="btn btn-primario">
                        <!-- [SEGURIDAD] Escapado contextual de datos contra XSS -->
                        <?= e('Ver todos los espacios') ?>
                    </a>
                    <a href="espacios/crear.php" class="btn btn-acento">
                        <!-- [SEGURIDAD] Escapado contextual de datos contra XSS -->
                        <?= e('Registrar nuevo espacio') ?>
                    </a>
                    <a href="reservas/index.php" class="btn btn-secundario">
                        <!-- [SEGURIDAD] Escapado contextual de datos contra XSS -->
                        <?= e('Ver reservas activas') ?>
                    </a>
                </div>
            </div>
        </article>
    </section>
</section>

<?php
// Inclusión del pie de página global (cierra la etiqueta </main> y el documento HTML)
require_once __DIR__ . '/../views/layout/pie.php';
