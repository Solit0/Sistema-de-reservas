<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * Sistema de Reservas - Controlador de Eliminación de Espacio
 * ==============================================================================
 * Procesa la eliminación física y lógica de un espacio del catálogo.
 * REQUISITOS ESTRICTOS:
 * - Procesado exclusivamente mediante el método HTTP POST. Si entra por GET,
 *   redirige inmediatamente sin borrar ningún registro.
 * - Validación obligatoria del token de protección CSRF.
 * - Eliminación de la fila en base de datos (con borrado en cascada de reservas).
 * - Limpieza física del archivo de imagen del disco con GestorImagenes.
 * - Mensaje flash en sesión informando el resultado y redirección PRG al catálogo.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database\Conexion;
use App\Repositories\EspacioRepositorio;
use App\Security\Csrf;
use App\Services\GestorImagenes;
use App\Services\ReservaStorageService;

// Iniciar sesión si aún no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// [SEGURIDAD] Regla estricta: Solo se procesan solicitudes por el método POST.
// Si un usuario o atacante intenta acceder vía GET, se redirige de inmediato sin borrar.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /espacios/index.php');
    exit;
}

// [SEGURIDAD] 1. Verificación del Token CSRF en sesión
$tokenCsrf = $_POST['csrf_token'] ?? null;
if (!Csrf::verificarToken(is_string($tokenCsrf) ? $tokenCsrf : null)) {
    $_SESSION['errores'] = [
        'general' => 'Acción no autorizada: token de seguridad inválido o expirado.'
    ];
    header('Location: /espacios/index.php');
    exit;
}

// 2. Captura y validación del identificador
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if ($id === null && isset($_POST['id'])) {
    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
}

if ($id === null || $id === false || $id <= 0) {
    $_SESSION['errores'] = [
        'general' => 'El identificador del espacio a eliminar no es válido.'
    ];
    header('Location: /espacios/index.php');
    exit;
}

// 3. Localizar el espacio antes de borrar para conocer su nombre e imagen asociada
$nombreEspacio = 'Espacio #' . $id;
$nombreImagen = null;
$encontrado = false;

// Estrategia A: Repositorio MySQL
if (class_exists(Conexion::class) && file_exists(__DIR__ . '/../../config/config.php')) {
    try {
        $pdo = Conexion::obtener();
        $repositorio = new EspacioRepositorio($pdo);
        $fila = $repositorio->obtenerFilaPorId($id);

        if ($fila !== null) {
            $encontrado = true;
            $nombreEspacio = (string) ($fila['nombre'] ?? $nombreEspacio);
            $nombreImagen = !empty($fila['imagen']) ? (string) $fila['imagen'] : null;

            // Ejecución del borrado en MySQL (las reservas se borran en cascada por FK ON DELETE CASCADE)
            $repositorio->eliminar($id);
        }
    } catch (\Throwable) {
        // En caso de contingencia con MySQL, se evalúa en JSON
    }
}

// Estrategia B: Persistencia JSON (reservas.json)
$rutaJson = __DIR__ . '/../../reservas.json';
if (file_exists($rutaJson)) {
    try {
        $storage = new ReservaStorageService();
        $datosJson = $storage->leerDeJson($rutaJson);
        $nuevoJson = [];
        $contador = 1;

        foreach ($datosJson as $item) {
            $idItem = isset($item['id']) ? (int) $item['id'] : $contador;
            if ($idItem === $id) {
                $encontrado = true;
                $nombreEspacio = (string) ($item['espacio'] ?? $nombreEspacio);
                if ($nombreImagen === null && !empty($item['imagen'])) {
                    $nombreImagen = (string) $item['imagen'];
                }
                // Omitir este elemento para eliminarlo
            } else {
                $nuevoJson[] = $item;
            }
            $contador++;
        }

        if ($encontrado) {
            file_put_contents($rutaJson, json_encode(array_values($nuevoJson), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    } catch (\Throwable) {
        // Silenciar contingencias de archivo
    }
}

// Si no se encontró el espacio en ninguna fuente
if (!$encontrado) {
    $_SESSION['errores'] = [
        'general' => 'El espacio que intentas eliminar no fue encontrado o ya había sido removido.'
    ];
    header('Location: /espacios/index.php');
    exit;
}

// 4. Limpieza física del archivo de imagen en disco para prevenir archivos huérfanos
if ($nombreImagen !== null && trim($nombreImagen) !== '') {
    $gestorImagenes = new GestorImagenes();
    $gestorImagenes->eliminarImagen($nombreImagen);
}

// 5. Notificación flash y redirección PRG al catálogo
$_SESSION['flash'] = sprintf('¡Espacio "%s" eliminado exitosamente!', $nombreEspacio);

header('Location: /espacios/index.php');
exit;
