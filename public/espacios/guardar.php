<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * Sistema de Reservas - Controlador de Inserción de Espacios
 * ==============================================================================
 * Procesa la creación de un nuevo espacio aplicando el patrón PRG
 * (Post/Redirect/Get), verificación estricta de CSRF, validación en servidor
 * con la clase Validador, procesamiento seguro de imagen con GestorImagenes,
 * persistencia relacional con EspacioRepositorio y fallback a JSON.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database\Conexion;
use App\Repositories\EspacioRepositorio;
use App\Security\Csrf;
use App\Services\GestorImagenes;
use App\Services\ReservaStorageService;
use App\Validation\Validador;

// Iniciar sesión si aún no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// [SEGURIDAD] Solo se procesan solicitudes por el método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /espacios/crear.php');
    exit;
}

// [SEGURIDAD] 1. Verificación del Token CSRF en sesión
$tokenCsrf = $_POST['csrf_token'] ?? null;
if (!Csrf::verificarToken(is_string($tokenCsrf) ? $tokenCsrf : null)) {
    $_SESSION['errores'] = [
        'general' => 'Token de seguridad inválido o expirado. Por favor, reenvía el formulario.'
    ];
    $_SESSION['old'] = $_POST;
    header('Location: /espacios/crear.php');
    exit;
}

// 2. Extracción de campos enviados
$nombre = isset($_POST['nombre']) ? trim((string) $_POST['nombre']) : '';
$tipo = isset($_POST['tipo']) ? trim((string) $_POST['tipo']) : '';
$capacidad = isset($_POST['capacidad']) ? trim((string) $_POST['capacidad']) : '';
$tarifaBase = isset($_POST['tarifa_base']) ? trim((string) $_POST['tarifa_base']) : '';
$presetImagen = isset($_POST['preset_imagen']) ? trim((string) $_POST['preset_imagen']) : '';

// Campos específicos de subclases (Single Table Inheritance)
$tipoGrama = isset($_POST['tipo_grama']) ? trim((string) $_POST['tipo_grama']) : '';
$iluminacionNocturna = isset($_POST['iluminacion_nocturna']) ? 1 : 0;
$tieneComputadora = isset($_POST['tiene_computadora']) ? 1 : 0;
$tieneProyector = isset($_POST['tiene_proyector']) ? 1 : 0;

// 3. Validación de campos en el servidor con la clase Validador
$validador = new Validador();
$validador
    ->requerido('nombre', $nombre)
    ->longitud('nombre', $nombre, 3, 100)
    ->requerido('tipo', $tipo)
    ->listaBlanca('tipo', $tipo, ['cancha', 'escritorio', 'sala'])
    ->requerido('capacidad', $capacidad)
    ->entero('capacidad', $capacidad, 1, null, 'La capacidad debe ser un número entero mayor a 0.')
    ->requerido('tarifa_base', $tarifaBase)
    ->numero('tarifa_base', $tarifaBase, 0.01, null, 'La tarifa base debe ser un valor numérico positivo mayor a 0.');

// Validación específica para cancha si se seleccionó ese tipo
if ($tipo === 'cancha' && $tipoGrama !== '') {
    $validador->listaBlanca('tipo_grama', $tipoGrama, ['Sintética', 'Natural'], 'El tipo de grama debe ser Sintética o Natural.');
}

// 4. Validación de la imagen con GestorImagenes
$gestorImagenes = new GestorImagenes();
$archivoImagen = $_FILES['imagen'] ?? null;
$errorImagen = $gestorImagenes->validar($archivoImagen);
if ($errorImagen !== null) {
    $validador->agregarError('imagen', $errorImagen);
}

// 5. Si existen errores de validación, redireccionar con errores y valores antiguos
if (!$validador->esValido()) {
    $_SESSION['errores'] = $validador->getErrores();
    $_SESSION['old'] = $_POST;
    $_SESSION['antiguo'] = $_POST;
    header('Location: /espacios/crear.php');
    exit;
}

// 6. Subida segura de imagen y persistencia
$nombreArchivoImagen = null;

try {
    // Si se subió un archivo físico
    if ($archivoImagen !== null && isset($archivoImagen['error']) && $archivoImagen['error'] === UPLOAD_ERR_OK) {
        $nombreArchivoImagen = $gestorImagenes->subir($archivoImagen);
    } elseif ($presetImagen !== '') {
        // Soporte de imagen predefinida seleccionada (preset)
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

    $guardado = false;

    // Estrategia 1: Persistencia relacional en MySQL mediante EspacioRepositorio
    if (class_exists(Conexion::class) && file_exists(__DIR__ . '/../../config/config.php')) {
        try {
            $pdo = Conexion::obtener();
            $repositorio = new EspacioRepositorio($pdo);

            $datosEspacio = [
                'tipo'                 => $tipo,
                'nombre'               => $nombre,
                'tarifa_base'          => (float) $tarifaBase,
                'capacidad'            => (int) $capacidad,
                'imagen'               => $nombreArchivoImagen,
                'tipo_grama'           => $tipo === 'cancha' && $tipoGrama !== '' ? $tipoGrama : null,
                'iluminacion_nocturna' => $tipo === 'cancha' ? $iluminacionNocturna : null,
                'tiene_computadora'    => $tipo === 'escritorio' ? $tieneComputadora : null,
                'tiene_proyector'      => $tipo === 'sala' ? $tieneProyector : null,
            ];

            $repositorio->insertar($datosEspacio);
            $guardado = true;
        } catch (\Throwable) {
            // Si falla la base de datos relacional, se procede al fallback JSON
        }
    }

    // Estrategia 2: Fallback resiliente a persistencia JSON (reservas.json)
    if (!$guardado) {
        $rutaJson = __DIR__ . '/../../reservas.json';
        $storage = new ReservaStorageService();
        $datos = file_exists($rutaJson) ? $storage->leerDeJson($rutaJson) : [];
        $datos[] = [
            'espacio'        => $nombre,
            'tipo'           => $tipo,
            'capacidad'      => (int) $capacidad,
            'imagen'         => $nombreArchivoImagen,
            'tarifa_base'    => (float) $tarifaBase,
            'total_reservas' => 0,
            'reservas'       => [],
        ];
        file_put_contents($rutaJson, json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    unset($_SESSION['antiguo'], $_SESSION['old'], $_SESSION['errores']);
    $_SESSION['flash'] = sprintf('¡Espacio "%s" registrado con éxito!', $nombre);

    header('Location: /espacios/index.php');
    exit;
} catch (\Throwable $e) {
    if ($nombreArchivoImagen !== null) {
        $gestorImagenes->eliminar($nombreArchivoImagen);
    }

    $_SESSION['errores'] = [
        'general' => 'Ocurrió un error al persistir el espacio: ' . $e->getMessage()
    ];
    $_SESSION['old'] = $_POST;
    $_SESSION['antiguo'] = $_POST;
    header('Location: /espacios/crear.php');
    exit;
}
