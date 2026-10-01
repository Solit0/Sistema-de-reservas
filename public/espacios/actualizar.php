<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * Sistema de Reservas - Controlador de Actualización de Espacios
 * ==============================================================================
 * Procesa la modificación de un espacio aplicando el patrón PRG (Post/Redirect/Get),
 * verificación estricta de CSRF, validación en servidor con la clase Validador,
 * gestión segura de reemplazo de imagen con GestorImagenes (eliminando la foto
 * anterior del disco) y persistencia relacional con EspacioRepositorio (con fallback a JSON).
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
    header('Location: /espacios/index.php');
    exit;
}

// Captura y validación del identificador
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if ($id === null && isset($_POST['id'])) {
    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
}

if ($id === null || $id === false || $id <= 0) {
    $_SESSION['errores'] = [
        'general' => 'El identificador de espacio no es válido o no fue proporcionado.'
    ];
    header('Location: /espacios/index.php');
    exit;
}

// [SEGURIDAD] 1. Verificación del Token CSRF en sesión
$tokenCsrf = $_POST['csrf_token'] ?? null;
if (!Csrf::verificarToken(is_string($tokenCsrf) ? $tokenCsrf : null)) {
    $_SESSION['errores'] = [
        'general' => 'Token de seguridad inválido o expirado. Por favor, reenvía el formulario.'
    ];
    $_SESSION['old'] = $_POST;
    header('Location: /espacios/editar.php?id=' . $id);
    exit;
}

// 2. Extracción de campos enviados
$nombre = isset($_POST['nombre']) ? trim((string) $_POST['nombre']) : '';
$tipo = isset($_POST['tipo']) ? trim((string) $_POST['tipo']) : '';
$capacidad = isset($_POST['capacidad']) ? trim((string) $_POST['capacidad']) : '';
$tarifaBase = isset($_POST['tarifa_base']) ? trim((string) $_POST['tarifa_base']) : '';
$eliminarImagenActual = !empty($_POST['eliminar_imagen_actual']);

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

// 4. Validación de nueva imagen si se adjuntó un archivo
$gestorImagenes = new GestorImagenes();
$archivoImagen = $_FILES['imagen'] ?? null;
$hayNuevaImagen = ($archivoImagen !== null && isset($archivoImagen['error']) && $archivoImagen['error'] !== UPLOAD_ERR_NO_FILE);

if ($hayNuevaImagen) {
    $errorImagen = $gestorImagenes->validar($archivoImagen);
    if ($errorImagen !== null) {
        $validador->agregarError('imagen', $errorImagen);
    }
}

// 5. Si existen errores de validación, redireccionar con errores y valores antiguos
if (!$validador->esValido()) {
    $_SESSION['errores'] = $validador->getErrores();
    $_SESSION['old'] = $_POST;
    header('Location: /espacios/editar.php?id=' . $id);
    exit;
}

// 6. Obtener datos previos del espacio para conocer la imagen actual
$imagenPrevia = null;
$espacioEncontrado = false;

// Estrategia A: Repositorio MySQL
if (class_exists(Conexion::class) && file_exists(__DIR__ . '/../../config/config.php')) {
    try {
        $pdo = Conexion::obtener();
        $repositorio = new EspacioRepositorio($pdo);
        $filaActual = $repositorio->obtenerFilaPorId($id);
        if ($filaActual !== null) {
            $espacioEncontrado = true;
            $imagenPrevia = !empty($filaActual['imagen']) ? (string) $filaActual['imagen'] : null;
        }
    } catch (\Throwable) {
        // Contingencia: continuará con la estrategia JSON
    }
}

// Estrategia B: JSON fallback si no se encontró en MySQL
if (!$espacioEncontrado) {
    $rutaJson = __DIR__ . '/../../reservas.json';
    if (file_exists($rutaJson)) {
        $storage = new ReservaStorageService();
        $datosJson = $storage->leerDeJson($rutaJson);
        $contador = 1;
        foreach ($datosJson as $item) {
            $idItem = isset($item['id']) ? (int) $item['id'] : $contador;
            if ($idItem === $id) {
                $espacioEncontrado = true;
                $imagenPrevia = !empty($item['imagen']) ? (string) $item['imagen'] : null;
                break;
            }
            $contador++;
        }
    }
}

// 7. Procesamiento de imagen: reemplazo, eliminación o conservación
$nombreFinalImagen = $imagenPrevia;

try {
    if ($hayNuevaImagen && $archivoImagen['error'] === UPLOAD_ERR_OK) {
        // Subida de nueva imagen
        $nombreSubido = $gestorImagenes->subir($archivoImagen);
        if ($nombreSubido !== null) {
            // Eliminar físicamente la imagen previa del disco si existía
            if ($imagenPrevia !== null) {
                $gestorImagenes->eliminarImagen($imagenPrevia);
            }
            $nombreFinalImagen = $nombreSubido;
        }
    } elseif ($eliminarImagenActual && $imagenPrevia !== null) {
        // El usuario solicitó expresamente remover la imagen existente
        $gestorImagenes->eliminarImagen($imagenPrevia);
        $nombreFinalImagen = null;
    }

    $actualizado = false;

    // Estrategia 1: Persistencia relacional en MySQL
    if (class_exists(Conexion::class) && file_exists(__DIR__ . '/../../config/config.php')) {
        try {
            $pdo = Conexion::obtener();
            $repositorio = new EspacioRepositorio($pdo);

            $datosEspacio = [
                'tipo'                 => $tipo,
                'nombre'               => $nombre,
                'tarifa_base'          => (float) $tarifaBase,
                'capacidad'            => (int) $capacidad,
                'imagen'               => $nombreFinalImagen,
                'tipo_grama'           => $tipo === 'cancha' && $tipoGrama !== '' ? $tipoGrama : null,
                'iluminacion_nocturna' => $tipo === 'cancha' ? $iluminacionNocturna : null,
                'tiene_computadora'    => $tipo === 'escritorio' ? $tieneComputadora : null,
                'tiene_proyector'      => $tipo === 'sala' ? $tieneProyector : null,
            ];

            $actualizado = $repositorio->actualizar($id, $datosEspacio);
        } catch (\Throwable) {
            // En contingencia MySQL, se intenta fallback a JSON
        }
    }

    // Estrategia 2: Persistencia en JSON (reservas.json)
    if (!$actualizado) {
        $rutaJson = __DIR__ . '/../../reservas.json';
        if (file_exists($rutaJson)) {
            $storage = new ReservaStorageService();
            $datosJson = $storage->leerDeJson($rutaJson);
            $contador = 1;
            foreach ($datosJson as $indice => $item) {
                $idItem = isset($item['id']) ? (int) $item['id'] : $contador;
                if ($idItem === $id) {
                    $datosJson[$indice]['espacio'] = $nombre;
                    $datosJson[$indice]['tipo'] = $tipo;
                    $datosJson[$indice]['capacidad'] = (int) $capacidad;
                    $datosJson[$indice]['tarifa_base'] = (float) $tarifaBase;
                    $datosJson[$indice]['imagen'] = $nombreFinalImagen;
                    $actualizado = true;
                    break;
                }
                $contador++;
            }
            if ($actualizado) {
                file_put_contents($rutaJson, json_encode($datosJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }
    }

    unset($_SESSION['old'], $_SESSION['errores']);
    $_SESSION['flash'] = sprintf('¡Espacio "%s" actualizado con éxito!', $nombre);

    header('Location: /espacios/ver.php?id=' . $id);
    exit;

} catch (\Throwable $e) {
    $_SESSION['errores'] = [
        'general' => 'Ocurrió un error inesperado al actualizar el espacio: ' . $e->getMessage()
    ];
    $_SESSION['old'] = $_POST;
    header('Location: /espacios/editar.php?id=' . $id);
    exit;
}
