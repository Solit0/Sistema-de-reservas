<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Repositories\EspacioRepositorio;
use App\Security\Csrf;
use App\Services\GestorImagenes;
use App\Validation\Validador;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "=== INICIANDO PRUEBAS DE INTEGRACIÓN: CREAR ESPACIO ===\n\n";

// 1. Simulación de envío con campos vacíos (validación del servidor con novalidate)
$postVacio = [
    'nombre'      => '',
    'tipo'        => '',
    'capacidad'   => '',
    'tarifa_base' => '',
];

$v1 = new Validador();
$v1->requerido('nombre', $postVacio['nombre'])
   ->longitud('nombre', $postVacio['nombre'], 3, 100)
   ->requerido('tipo', $postVacio['tipo'])
   ->listaBlanca('tipo', $postVacio['tipo'], ['cancha', 'escritorio', 'sala'])
   ->requerido('capacidad', $postVacio['capacidad'])
   ->entero('capacidad', $postVacio['capacidad'], 1)
   ->requerido('tarifa_base', $postVacio['tarifa_base'])
   ->numero('tarifa_base', $postVacio['tarifa_base'], 0.01);

assert(!$v1->esValido(), 'Error: El formulario vacío debe ser inválido.');
$errores = $v1->getErrores();
assert(isset($errores['nombre']), 'Error: Debe existir error en nombre.');
assert(isset($errores['tipo']), 'Error: Debe existir error en tipo.');
assert(isset($errores['capacidad']), 'Error: Debe existir error en capacidad.');
assert(isset($errores['tarifa_base']), 'Error: Debe existir error en tarifa_base.');
echo "✔ [PASS] Rechazo de formulario vacío: se generan errores en los 4 campos obligatorios.\n";

// 2. Simulación de persistencia de valores ingresados ($old) y conservación tras errores
$postParcial = [
    'nombre'      => 'Sala de Conferencias Magna',
    'tipo'        => 'sala',
    'capacidad'   => '-5', // Inválido
    'tarifa_base' => '220.00'
];

$v2 = new Validador();
$v2->requerido('nombre', $postParcial['nombre'])
   ->longitud('nombre', $postParcial['nombre'], 3, 100)
   ->requerido('tipo', $postParcial['tipo'])
   ->listaBlanca('tipo', $postParcial['tipo'], ['cancha', 'escritorio', 'sala'])
   ->requerido('capacidad', $postParcial['capacidad'])
   ->entero('capacidad', $postParcial['capacidad'], 1)
   ->requerido('tarifa_base', $postParcial['tarifa_base'])
   ->numero('tarifa_base', $postParcial['tarifa_base'], 0.01);

assert(!$v2->esValido(), 'Error: Capacidad negativa debe ser inválida.');
assert(!isset($v2->getErrores()['nombre']), 'Error: Nombre válido no debe tener error.');
assert(!isset($v2->getErrores()['tipo']), 'Error: Tipo válido no debe tener error.');
assert(!isset($v2->getErrores()['tarifa_base']), 'Error: Tarifa válida no debe tener error.');
assert(isset($v2->getErrores()['capacidad']), 'Error: Capacidad debe tener error.');
echo "✔ [PASS] Validación selectiva: aísla el error en el campo incorrecto.\n";

// 3. Verificación de seguridad CSRF en el flujo de envío
$tokenGenerado = Csrf::generarToken();
assert(Csrf::verificarToken($tokenGenerado) === true, 'Error: Token legítimo debe ser aceptado.');
assert(Csrf::verificarToken('token_falsificado') === false, 'Error: Token alterado debe ser rechazado.');
echo "✔ [PASS] Protección CSRF: intercepta intentos de falsificación de petición.\n";

// 4. Validación de imagen con GestorImagenes integrado
$gestorImg = new GestorImagenes();
$archivoTextoFalso = [
    'error' => UPLOAD_ERR_OK,
    'size' => 100,
    'tmp_name' => tempnam(sys_get_temp_dir(), 'txt_fake')
];
file_put_contents($archivoTextoFalso['tmp_name'], 'esto no es una imagen');

$errorImg = $gestorImg->validar($archivoTextoFalso);
assert($errorImg !== null, 'Error: Archivo de texto plano no debe pasar validación de imagen.');
@unlink($archivoTextoFalso['tmp_name']);
echo "✔ [PASS] GestorImagenes: detecta y rechaza archivos con formato MIME indebido.\n";

// 5. Inserción mediante sentencia preparada en EspacioRepositorio (usando SQLite en memoria para test unitario)
$pdoMemory = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$pdoMemory->exec('
    CREATE TABLE espacios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tipo TEXT NOT NULL,
        nombre TEXT NOT NULL,
        tarifa_base REAL NOT NULL,
        capacidad INTEGER NOT NULL,
        imagen TEXT,
        tipo_grama TEXT,
        iluminacion_nocturna INTEGER,
        tiene_computadora INTEGER,
        tiene_proyector INTEGER
    )
');

$repoMemory = new EspacioRepositorio($pdoMemory);
$idInsertado = $repoMemory->insertar([
    'tipo'                 => 'cancha',
    'nombre'               => 'Cancha Techada 05',
    'tarifa_base'          => 120.00,
    'capacidad'            => 10,
    'imagen'               => 'cancha_05.webp',
    'tipo_grama'           => 'Sintética',
    'iluminacion_nocturna' => 1,
    'tiene_computadora'    => null,
    'tiene_proyector'      => null
]);

assert($idInsertado > 0, 'Error: Debe retornar el ID insertado.');
$filaGuardada = $repoMemory->obtenerFilaPorId($idInsertado);
assert($filaGuardada !== null, 'Error: La fila debe existir en la BD.');
assert($filaGuardada['nombre'] === 'Cancha Techada 05', 'Error: El nombre debe coincidir.');
assert($filaGuardada['tipo_grama'] === 'Sintética', 'Error: Las columnas específicas deben persistir.');
echo "✔ [PASS] EspacioRepositorio::insertar: persiste columnas polimórficas con consultas preparadas.\n";

echo "\nTODAS LAS PRUEBAS DE INTEGRACIÓN PASARON EXITOSAMENTE (5/5).\n";
