<?php
/**
 * ==============================================================================
 * Sistema de Reservas - Encabezado y Layout Superior
 * ==============================================================================
 * Define la estructura HTML5 inicial, gestión segura de sesiones, función
 * auxiliar de escape contra XSS, metadatos, navegación global y mensajes flash.
 */

// Iniciar sesión PHP de forma segura si aún no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Función auxiliar de escape seguro contra ataques XSS
if (!function_exists('e')) {
    function e(?string $v): string {
        return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); // [SEGURIDAD] Escape contra XSS
    }
}
// Detección contextual de la ruta actual para marcar el enlace activo en la barra de navegación
$rutaActual = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$esActivo = static function (string $ruta) use ($rutaActual): string {
    if ($ruta === '/index.php') {
        return ($rutaActual === '/' || $rutaActual === '/index.php' || $rutaActual === '') ? ' activo' : '';
    }
    return str_starts_with($rutaActual, $ruta) ? ' activo' : '';
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($tituloPagina) ? e($tituloPagina) . ' - ' : '' ?>Sistema de Reservas</title>
    <link rel="stylesheet" href="/css/estilos.css">
</head>
<body>
    <header class="cabecera-principal">
        <div class="contenedor barra-navegacion">
            <div class="marca">
                <a href="/index.php" class="logo">
                    <span>Sistema de Reservas</span>
                </a>
            </div>
            <nav class="navegacion-principal">
                <ul class="menu-navegacion">
                    <li><a href="/index.php" class="nav-link<?= $esActivo('/index.php') ?>">Inicio</a></li>
                    <li><a href="/espacios/index.php" class="nav-link<?= $esActivo('/espacios/index.php') ?>">Espacios</a></li>
                    <li><a href="/espacios/crear.php" class="nav-link<?= $esActivo('/espacios/crear.php') ?>">Nuevo Espacio</a></li>
                    <li><a href="/reservas/index.php" class="nav-link<?= $esActivo('/reservas/index.php') ?>">Reservas</a></li>
                    <li><a href="/reservas/crear.php" class="nav-link<?= $esActivo('/reservas/crear.php') ?>">Nueva Reserva</a></li>
                    <li><a href="/espacios/reporte.php" class="nav-link<?= $esActivo('/espacios/reporte.php') ?>">Reporte Financiero</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="contenedor principal">
        <?php if (isset($_SESSION['flash'])): ?>
            <div class="alerta alerta-exito"><?= e($_SESSION['flash']) ?></div>
            <?php unset($_SESSION['flash']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['errores']['general'])): ?>
            <div class="alerta alerta-error"><?= e($_SESSION['errores']['general']) ?></div>
            <?php unset($_SESSION['errores']['general']); ?>
        <?php endif; ?>
