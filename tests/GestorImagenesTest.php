<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\GestorImagenes;

echo "=== INICIANDO PRUEBAS UNITARIAS: GestorImagenes ===\n\n";

$dirTemp = __DIR__ . '/temp_uploads';
$gestor = new GestorImagenes($dirTemp);

assert(is_dir($dirTemp), 'Error: El directorio de uploads temporales debió crearse.');
echo "✔ [PASS] Directorio de subidas inicializado correctamente.\n";

// 1. Probar eliminación de archivo default o vacío
assert($gestor->eliminarImagen(null) === false, 'Error: No debe eliminar null.');
assert($gestor->eliminarImagen('default.png') === false, 'Error: No debe eliminar default.png.');
echo "✔ [PASS] Protección contra borrado de default.png y archivos nulos.\n";

// 2. Probar creación y eliminación física
$archivoPrueba = $dirTemp . '/prueba_borrado.png';
file_put_contents($archivoPrueba, 'fake-png-content');
assert(file_exists($archivoPrueba), 'Error: Archivo de prueba debe existir.');
$eliminado = $gestor->eliminarImagen('prueba_borrado.png');
assert($eliminado === true, 'Error: Debe eliminar físicamente el archivo existente.');
assert(!file_exists($archivoPrueba), 'Error: El archivo debe haber desaparecido del disco.');
echo "✔ [PASS] Eliminación física real (unlink) de imagen previa.\n";

// 3. Probar validación con archivo vacío
$errorDetectado = false;
try {
    $gestor->procesarSubida(['error' => UPLOAD_ERR_NO_FILE]);
} catch (InvalidArgumentException $e) {
    $errorDetectado = true;
}
assert($errorDetectado === true, 'Error: Debe lanzar InvalidArgumentException cuando no hay archivo.');
echo "✔ [PASS] Validación estricta de ausencia de archivo en subida.\n";

// Limpieza de directorio temporal
@rmdir($dirTemp);

echo "\nTODAS LAS PRUEBAS DE GestorImagenes PASARON (4/4).\n";
