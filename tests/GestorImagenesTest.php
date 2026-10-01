<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Services\GestorImagenes;

echo "=== INICIANDO SUITE DE PRUEBAS GESTOR DE IMÁGENES ===\n\n";

$tmpDir = sys_get_temp_dir() . '/test_uploads_' . bin2hex(random_bytes(4));
mkdir($tmpDir, 0755, true);

$gestor = new GestorImagenes($tmpDir);

// 1. Test rechazo de valor nulo o vacío
assert($gestor->eliminarImagen(null) === false, 'Error: eliminarImagen(null) debería retornar false');
assert($gestor->eliminarImagen('') === false, 'Error: eliminarImagen("") debería retornar false');
assert($gestor->eliminarImagen('   ') === false, 'Error: eliminarImagen("   ") debería retornar false');
echo "✔ [PASS] Rechazo de valores nulos y cadenas vacías\n";

// 2. Test protección contra Path Traversal
assert($gestor->eliminarImagen('../../../etc/passwd') === false, 'Error: Path traversal no debe permitirse');
assert($gestor->eliminarImagen('..') === false, 'Error: Directorio padre no debe permitirse');
assert($gestor->eliminarImagen('.') === false, 'Error: Directorio actual no debe permitirse');
echo "✔ [PASS] Mitigación efectiva de ataques de Path / Directory Traversal\n";

// 3. Test archivo inexistente
assert($gestor->eliminarImagen('archivo_fantasma_123.jpg') === false, 'Error: Archivo inexistente debe retornar false');
echo "✔ [PASS] Manejo correcto de archivos inexistentes\n";

// 4. Test eliminación física exitosa
$testFile = 'foto_prueba.jpg';
file_put_contents($tmpDir . '/' . $testFile, 'dummy image content');
assert(file_exists($tmpDir . '/' . $testFile), 'Error: El archivo de prueba debió crearse');

$resultado = $gestor->eliminarImagen($testFile);
assert($resultado === true, 'Error: eliminarImagen debería retornar true para archivo existente');
assert(!file_exists($tmpDir . '/' . $testFile), 'Error: El archivo físico debió eliminarse del disco');
echo "✔ [PASS] Eliminación física real en disco exitosa\n";

// Limpieza de directorio temporal
rmdir($tmpDir);

echo "\n>>> TODAS LAS PRUEBAS DE GESTOR DE IMÁGENES PASARON EXITOSAMENTE (4/4)\n";
