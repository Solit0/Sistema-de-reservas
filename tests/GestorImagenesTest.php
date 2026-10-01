<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\GestorImagenes;

echo "=== INICIANDO PRUEBAS DE GESTOR DE IMÁGENES ===\n\n";

$directorioPruebas = sys_get_temp_dir() . '/test_uploads_' . bin2hex(random_bytes(4));
$gestor = new GestorImagenes($directorioPruebas);

// 1. Archivo nulo u omitido (opcional)
assert($gestor->validar(null) === null, 'Error: null debe considerarse válido (opcional).');
assert($gestor->validar(['error' => UPLOAD_ERR_NO_FILE]) === null, 'Error: UPLOAD_ERR_NO_FILE es válido.');
assert($gestor->subir(null) === null, 'Error: subir(null) debe devolver null.');
echo "✔ [PASS] Carga opcional: acepta ausencia de imagen sin error.\n";

// 2. Archivo con error de subida
$archivoError = ['error' => UPLOAD_ERR_INI_SIZE];
assert($gestor->validar($archivoError) !== null, 'Error: Debe detectar error UPLOAD_ERR_INI_SIZE.');
echo "✔ [PASS] Detección de errores nativos de subida de PHP.\n";

// 3. Archivo que excede tamaño máximo (> 3MB)
$archivoPesado = [
    'error' => UPLOAD_ERR_OK,
    'size' => 4 * 1024 * 1024,
    'tmp_name' => tempnam(sys_get_temp_dir(), 'img_heavy')
];
assert(str_contains((string) $gestor->validar($archivoPesado), '3 MB'), 'Error: Debe rechazar archivos mayores a 3MB.');
@unlink($archivoPesado['tmp_name']);
echo "✔ [PASS] Validación estricta de tamaño máximo de archivo.\n";

// 4. Tipo MIME no permitido (ej. archivo de texto plano o script PHP)
$tmpPhp = tempnam(sys_get_temp_dir(), 'fake_img');
file_put_contents($tmpPhp, '<?php echo "malicioso";');
$archivoInvalido = [
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($tmpPhp),
    'tmp_name' => $tmpPhp
];
assert(str_contains((string) $gestor->validar($archivoInvalido), 'JPEG, PNG y WEBP'), 'Error: Debe rechazar tipos MIME no admitidos.');
@unlink($tmpPhp);
echo "✔ [PASS] Verificación segura de tipo MIME real con Fileinfo.\n";

// 5. Subida exitosa y eliminación física de imagen válida
$tmpPng = tempnam(sys_get_temp_dir(), 'valid_png');
$pngValidoBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
file_put_contents($tmpPng, $pngValidoBytes);
$archivoValido = [
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($tmpPng),
    'tmp_name' => $tmpPng
];

assert($gestor->validar($archivoValido) === null, 'Error: PNG debe ser válido.');
$nombreGuardado = $gestor->subir($archivoValido);
assert($nombreGuardado !== null && str_ends_with($nombreGuardado, '.png'), 'Error: Debe guardar con extensión .png.');
assert(file_exists($directorioPruebas . '/' . $nombreGuardado), 'Error: El archivo debe existir en la carpeta destino.');
echo "✔ [PASS] Subida exitosa con renombrado aleatorio.\n";

// 6. Pruebas de seguridad de eliminación y mitigación de Path Traversal
assert($gestor->eliminarImagen(null) === false, 'Error: null debe retornar false en eliminarImagen.');
assert($gestor->eliminarImagen('') === false, 'Error: cadena vacía debe retornar false.');
assert($gestor->eliminarImagen('   ') === false, 'Error: espacios deben retornar false.');
assert($gestor->eliminarImagen('../../../etc/passwd') === false, 'Error: Path traversal debe rechazarse.');
assert($gestor->eliminarImagen('..') === false, 'Error: .. debe rechazarse.');
assert($gestor->eliminarImagen('.') === false, 'Error: . debe rechazarse.');
assert($gestor->eliminarImagen('archivo_fantasma_123.jpg') === false, 'Error: archivo inexistente debe retornar false.');
echo "✔ [PASS] Mitigación efectiva de ataques de Path Traversal y manejo de nulos.\n";

// 7. Eliminación física real en disco exitosa
$testFile = 'foto_prueba.jpg';
file_put_contents($directorioPruebas . '/' . $testFile, 'dummy image content');
assert(file_exists($directorioPruebas . '/' . $testFile));
assert($gestor->eliminarImagen($testFile) === true);
assert(!file_exists($directorioPruebas . '/' . $testFile));

assert($gestor->eliminar($nombreGuardado) === true, 'Error: eliminar() debe retornar true.');
assert(!file_exists($directorioPruebas . '/' . $nombreGuardado), 'Error: El archivo debe eliminarse físicamente.');
echo "✔ [PASS] Eliminación física real en disco exitosa.\n";

// Limpieza de directorio de pruebas
@unlink($tmpPng);
@rmdir($directorioPruebas);

echo "\nTODAS LAS PRUEBAS DE GESTOR DE IMÁGENES PASARON EXITOSAMENTE.\n";
