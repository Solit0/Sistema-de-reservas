<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database\Conexion;
use App\Services\GestorImagenes;
use App\Services\ReservaStorageService;
use App\Validation\Validador;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /espacios/crear.php');
    exit;
}

$nombre = trim((string)($_POST['nombre'] ?? ''));
$tipo = trim((string)($_POST['tipo'] ?? ''));
$capacidad = (int)($_POST['capacidad'] ?? 0);
$tarifaBase = (float)($_POST['tarifa_base'] ?? 0.0);
$presetImagen = trim((string)($_POST['preset_imagen'] ?? ''));

$validador = new Validador();
$validador
    ->requerido('nombre', $nombre, 'El nombre del espacio es obligatorio.')
    ->longitud('nombre', $nombre, 3, 100, 'El nombre debe tener entre 3 y 100 caracteres.')
    ->requerido('tipo', $tipo, 'El tipo de espacio es obligatorio.')
    ->listaBlanca('tipo', $tipo, ['sala', 'cancha', 'escritorio'], 'El tipo de espacio seleccionado no es válido.')
    ->entero('capacidad', $capacidad, 1, 500, 'La capacidad debe ser un número entero mayor a 0.')
    ->numero('tarifa_base', $tarifaBase, 1.0, 100000.0, 'La tarifa base debe ser mayor a 0.');

if (!$validador->esValido()) {
    $_SESSION['errores'] = $validador->getErrores();
    $_SESSION['antiguo'] = [
        'nombre'      => $nombre,
        'tipo'        => $tipo,
        'capacidad'   => $capacidad,
        'tarifa_base' => $tarifaBase,
    ];
    header('Location: /espacios/crear.php');
    exit;
}

$nombreArchivoImagen = null;
$gestorImagenes = new GestorImagenes();

if (isset($_FILES['imagen']) && is_array($_FILES['imagen']) && ($_FILES['imagen']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    try {
        $nombreArchivoImagen = $gestorImagenes->procesarSubida($_FILES['imagen']);
    } catch (\Throwable $ex) {
        $_SESSION['errores'] = ['imagen' => $ex->getMessage()];
        $_SESSION['antiguo'] = [
            'nombre'      => $nombre,
            'tipo'        => $tipo,
            'capacidad'   => $capacidad,
            'tarifa_base' => $tarifaBase,
        ];
        header('Location: /espacios/crear.php');
        exit;
    }
} elseif ($presetImagen !== '') {
    $nombrePresetLimpio = basename($presetImagen);
    $rutaPresetOrigen = dirname(__DIR__, 2) . '/public/img/presets/' . $nombrePresetLimpio;
    if (file_exists($rutaPresetOrigen)) {
        $nombreFinal = 'preset_' . bin2hex(random_bytes(6)) . '_' . $nombrePresetLimpio;
        $rutaDestino = dirname(__DIR__, 2) . '/public/uploads/' . $nombreFinal;
        if (copy($rutaPresetOrigen, $rutaDestino)) {
            $nombreArchivoImagen = $nombreFinal;
        }
    }
}

$rutaRelativaImagen = $nombreArchivoImagen !== null ? '/uploads/' . $nombreArchivoImagen : null;

$guardado = false;

if (class_exists(Conexion::class) && file_exists(__DIR__ . '/../../config/config.php')) {
    try {
        $pdo = Conexion::obtener();
        $stmt = $pdo->prepare('
            INSERT INTO espacios (tipo, nombre, tarifa_base, capacidad, imagen)
            VALUES (:tipo, :nombre, :tarifa_base, :capacidad, :imagen)
        ');
        $stmt->execute([
            ':tipo'        => $tipo,
            ':nombre'      => $nombre,
            ':tarifa_base' => $tarifaBase,
            ':capacidad'   => $capacidad,
            ':imagen'      => $rutaRelativaImagen,
        ]);
        $guardado = true;
    } catch (\Throwable) {
    }
}

if (!$guardado) {
    $rutaJson = __DIR__ . '/../../reservas.json';
    $storage = new ReservaStorageService();
    $datos = file_exists($rutaJson) ? $storage->leerDeJson($rutaJson) : [];
    $datos[] = [
        'espacio'        => $nombre,
        'tipo'           => $tipo,
        'capacidad'      => $capacidad,
        'imagen'         => $rutaRelativaImagen,
        'tarifa_base'    => $tarifaBase,
        'total_reservas' => 0,
        'reservas'       => [],
    ];
    file_put_contents($rutaJson, json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

unset($_SESSION['antiguo'], $_SESSION['errores']);
$_SESSION['flash'] = sprintf('¡Espacio "%s" registrado con éxito!', $nombre);

header('Location: /espacios/index.php');
exit;
