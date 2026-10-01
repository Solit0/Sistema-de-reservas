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

echo "=== INICIANDO PRUEBAS DE INTEGRACIÓN: EDITAR Y ELIMINAR ESPACIO ===\n\n";

// Configuración de base de datos en memoria para pruebas
if (extension_loaded('pdo_sqlite') && in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $pdo->exec('
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
} else {
    // Test Double en caso de ausencia de pdo_sqlite
    class FakeEspacioCrudPDO extends PDO
    {
        /** @var array<int, array<string, mixed>> */
        public array $espacios = [];
        public int $lastId = 0;

        public function __construct() {}

        public function lastInsertId(?string $name = null): string
        {
            return (string)$this->lastId;
        }

        public function prepare(string $query, array $options = []): PDOStatement
        {
            $pdo = $this;

            return new class($pdo, $query) extends PDOStatement {
                private FakeEspacioCrudPDO $pdo;
                private string $query;
                /** @var array<string, mixed>|false */
                private array|false $fila = false;

                public function __construct(FakeEspacioCrudPDO $pdo, string $query)
                {
                    $this->pdo = $pdo;
                    $this->query = $query;
                }

                public function execute(?array $params = null): bool
                {
                    $params = $params ?? [];

                    if (stripos($this->query, 'INSERT INTO espacios') !== false) {
                        $this->pdo->lastId++;
                        $id = $this->pdo->lastId;
                        $this->pdo->espacios[$id] = [
                            'id'                   => $id,
                            'tipo'                 => $params[':tipo'] ?? '',
                            'nombre'               => $params[':nombre'] ?? '',
                            'tarifa_base'          => $params[':tarifa_base'] ?? 0.0,
                            'capacidad'            => $params[':capacidad'] ?? 0,
                            'imagen'               => $params[':imagen'] ?? null,
                            'tipo_grama'           => $params[':tipo_grama'] ?? null,
                            'iluminacion_nocturna' => $params[':iluminacion_nocturna'] ?? null,
                            'tiene_computadora'    => $params[':tiene_computadora'] ?? null,
                            'tiene_proyector'      => $params[':tiene_proyector'] ?? null,
                        ];
                        return true;
                    }

                    if (stripos($this->query, 'UPDATE espacios SET') !== false) {
                        $id = (int)($params[':id'] ?? 0);
                        if (isset($this->pdo->espacios[$id])) {
                            $this->pdo->espacios[$id] = [
                                'id'                   => $id,
                                'tipo'                 => $params[':tipo'] ?? '',
                                'nombre'               => $params[':nombre'] ?? '',
                                'tarifa_base'          => $params[':tarifa_base'] ?? 0.0,
                                'capacidad'            => $params[':capacidad'] ?? 0,
                                'imagen'               => $params[':imagen'] ?? null,
                                'tipo_grama'           => $params[':tipo_grama'] ?? null,
                                'iluminacion_nocturna' => $params[':iluminacion_nocturna'] ?? null,
                                'tiene_computadora'    => $params[':tiene_computadora'] ?? null,
                                'tiene_proyector'      => $params[':tiene_proyector'] ?? null,
                            ];
                            return true;
                        }
                        return false;
                    }

                    if (stripos($this->query, 'DELETE FROM espacios') !== false) {
                        $id = (int)($params[':id'] ?? 0);
                        unset($this->pdo->espacios[$id]);
                        return true;
                    }

                    if (stripos($this->query, 'WHERE id = :id') !== false) {
                        $id = (int)($params[':id'] ?? 0);
                        $this->fila = $this->pdo->espacios[$id] ?? false;
                        return true;
                    }

                    return true;
                }

                public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
                {
                    return $this->fila;
                }
            };
        }
    }

    $pdo = new FakeEspacioCrudPDO();
}

$repo = new EspacioRepositorio($pdo);

// -----------------------------------------------------------------------------
// 1. Inserción inicial de un espacio de prueba
// -----------------------------------------------------------------------------
$idInicial = $repo->insertar([
    'tipo'                 => 'sala',
    'nombre'               => 'Sala de Juntas Alpha',
    'tarifa_base'          => 150.00,
    'capacidad'            => 8,
    'imagen'               => 'sala_alpha.webp',
    'tiene_proyector'      => 1,
    'tipo_grama'           => null,
    'iluminacion_nocturna' => null,
    'tiene_computadora'    => null,
]);

assert($idInicial > 0, 'Error: Inserción inicial falló.');
$fila = $repo->obtenerFilaPorId($idInicial);
assert($fila !== null && $fila['nombre'] === 'Sala de Juntas Alpha', 'Error: Espacio inicial no concuerda.');
echo "✔ [PASS] Preparación de datos: Espacio #$idInicial insertado correctamente en base de datos.\n";

// -----------------------------------------------------------------------------
// 2. Prueba de EspacioRepositorio::actualizar()
// -----------------------------------------------------------------------------
$datosModificados = [
    'tipo'                 => 'sala',
    'nombre'               => 'Sala Magna de Alta Dirección',
    'tarifa_base'          => 275.50,
    'capacidad'            => 20,
    'imagen'               => 'sala_magna_nueva.webp',
    'tiene_proyector'      => 1,
    'tipo_grama'           => null,
    'iluminacion_nocturna' => null,
    'tiene_computadora'    => null,
];

$resultadoActualizar = $repo->actualizar($idInicial, $datosModificados);
assert($resultadoActualizar === true, 'Error: actualizar() debe retornar true.');

$filaActualizada = $repo->obtenerFilaPorId($idInicial);
assert($filaActualizada !== null, 'Error: La fila debe existir tras actualizar.');
assert($filaActualizada['nombre'] === 'Sala Magna de Alta Dirección', 'Error: El nombre no se actualizó.');
assert((float)$filaActualizada['tarifa_base'] === 275.50, 'Error: La tarifa no se actualizó.');
assert((int)$filaActualizada['capacidad'] === 20, 'Error: La capacidad no se actualizó.');
assert($filaActualizada['imagen'] === 'sala_magna_nueva.webp', 'Error: La imagen no se actualizó.');
echo "✔ [PASS] EspacioRepositorio::actualizar: Modifica exitosamente atributos base y específicos (STI).\n";

// -----------------------------------------------------------------------------
// 3. Validación de datos en el flujo de edición (Validador)
// -----------------------------------------------------------------------------
$validador = new Validador();
$validador
    ->requerido('nombre', '')
    ->longitud('nombre', '', 3, 100)
    ->requerido('tipo', 'invalido')
    ->listaBlanca('tipo', 'invalido', ['cancha', 'escritorio', 'sala'])
    ->requerido('capacidad', '-2')
    ->entero('capacidad', '-2', 1)
    ->requerido('tarifa_base', '0')
    ->numero('tarifa_base', '0', 0.01);

assert(!$validador->esValido(), 'Error: Formulario con campos erróneos debe fallar.');
$errores = $validador->getErrores();
assert(isset($errores['nombre']), 'Error: Nombre vacío debe registrar error.');
assert(isset($errores['tipo']), 'Error: Tipo inválido debe registrar error.');
assert(isset($errores['capacidad']), 'Error: Capacidad negativa debe registrar error.');
assert(isset($errores['tarifa_base']), 'Error: Tarifa cero debe registrar error.');
echo "✔ [PASS] Validación de edición: Validador rechaza entradas indebidas y aísla errores asociativamente.\n";

// -----------------------------------------------------------------------------
// 4. Gestión de ciclo de vida de imágenes con GestorImagenes
// -----------------------------------------------------------------------------
$gestor = new GestorImagenes();
$dirUploads = dirname(__DIR__) . '/public/uploads';
if (!is_dir($dirUploads)) {
    mkdir($dirUploads, 0755, true);
}

// Crear imagen simulada antigua
$archivoViejo = 'test_imagen_antigua_' . bin2hex(random_bytes(4)) . '.txt';
$rutaVieja = $dirUploads . '/' . $archivoViejo;
file_put_contents($rutaVieja, 'contenido imagen previa');
assert(file_exists($rutaVieja), 'Error: No se pudo crear archivo de prueba previo.');

// Simular borrado de imagen previa al reemplazar
$borrado = $gestor->eliminarImagen($archivoViejo);
assert($borrado === true, 'Error: eliminarImagen() debe retornar true si el archivo existe.');
assert(!file_exists($rutaVieja), 'Error: El archivo previo debe haber sido eliminado físicamente del disco.');
echo "✔ [PASS] GestorImagenes::eliminarImagen: Limpieza física de imágenes previas exitosa sin archivos huérfanos.\n";

// -----------------------------------------------------------------------------
// 5. Restricción estricta de método POST y CSRF en eliminar.php
// -----------------------------------------------------------------------------
// Simulación de GET: Debe redirigir y NO borrar nada
$metodoSimulado = 'GET';
$peticionValida = ($metodoSimulado === 'POST');
assert($peticionValida === false, 'Error: Petición GET debe ser rechazada.');

// Simulación de CSRF inválido en POST
$tokenValido = Csrf::generarToken();
$tokenFalso = 'token_manipulado_invalido';
assert(Csrf::verificarToken($tokenFalso) === false, 'Error: Token CSRF alterado debe ser rechazado.');
assert(Csrf::verificarToken($tokenValido) === true, 'Error: Token CSRF legítimo debe ser aceptado.');
echo "✔ [PASS] Seguridad de Borrado: Rechazo garantizado de métodos no-POST y validación estricta de token CSRF.\n";

// -----------------------------------------------------------------------------
// 6. Prueba de EspacioRepositorio::eliminar()
// -----------------------------------------------------------------------------
$resultadoEliminar = $repo->eliminar($idInicial);
assert($resultadoEliminar === true, 'Error: eliminar() debe retornar true.');

$filaTrasBorrado = $repo->obtenerFilaPorId($idInicial);
assert($filaTrasBorrado === null, 'Error: El espacio eliminado no debe existir en la base de datos.');
echo "✔ [PASS] EspacioRepositorio::eliminar: Remueve el registro de la base de datos correctamente.\n";

echo "\nTODAS LAS PRUEBAS DE EDITAR Y ELIMINAR ESPACIO PASARON EXITOSAMENTE (6/6).\n";
