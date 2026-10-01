<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Services\GestorImagenes;

echo "=== INICIANDO SUITE DE PRUEBAS GESTOR DE IMÁGENES ===\n\n";

$tmpDir = sys_get_temp_dir() . '/test_uploads_' . bin2hex(random_bytes(4));
mkdir($tmpDir, 0755, true);

$gestor = new GestorImagenes($tmpDir);

assert($gestor->eliminarImagen(null) === false);
assert($gestor->eliminarImagen('') === false);
assert($gestor->eliminarImagen('   ') === false);
echo "✔ [PASS] Rechazo de valores nulos y cadenas vacías\n";

assert($gestor->eliminarImagen('../../../etc/passwd') === false);
assert($gestor->eliminarImagen('..') === false);
assert($gestor->eliminarImagen('.') === false);
echo "✔ [PASS] Mitigación efectiva de ataques de Path Traversal\n";

assert($gestor->eliminarImagen('archivo_fantasma_123.jpg') === false);
echo "✔ [PASS] Manejo correcto de archivos inexistentes\n";

$testFile = 'foto_prueba.jpg';
file_put_contents($tmpDir . '/' . $testFile, 'dummy image content');
assert(file_exists($tmpDir . '/' . $testFile));

$resultado = $gestor->eliminarImagen($testFile);
assert($resultado === true);
assert(!file_exists($tmpDir . '/' . $testFile));
echo "✔ [PASS] Eliminación física real en disco exitosa\n";

rmdir($tmpDir);

echo "\n>>> TODAS LAS PRUEBAS DE GESTOR DE IMÁGENES PASARON EXITOSAMENTE (4/4)\n";
