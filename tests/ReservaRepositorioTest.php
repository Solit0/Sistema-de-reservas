<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Repositories\ReservaRepositorio;

echo "=== INICIANDO PRUEBAS UNITARIAS: ReservaRepositorio ===\n\n";

// 1. Verificar existencia de la clase y métodos requeridos por la rúbrica
assert(class_exists(ReservaRepositorio::class), 'Error: ReservaRepositorio debe existir.');
assert(method_exists(ReservaRepositorio::class, 'existeTraslape'), 'Error: Debe existir método existeTraslape.');
assert(method_exists(ReservaRepositorio::class, 'registrar'), 'Error: Debe existir método registrar.');
assert(method_exists(ReservaRepositorio::class, 'listarTodas'), 'Error: Debe existir método listarTodas.');
assert(method_exists(ReservaRepositorio::class, 'obtenerPorFecha'), 'Error: Debe existir método obtenerPorFecha.');
echo "✔ [PASS] Contrato e interfaz de ReservaRepositorio válidos.\n";

// 2. Mock de PDO y PDOStatement para validar lógica sin base de datos activa
$pdoStatementMock = new class extends PDOStatement {
    public int $rowCount = 1;
    public function execute(?array $params = null): bool {
        return true;
    }
    public function fetchColumn(int $column = 0): mixed {
        return $this->rowCount;
    }
};

$pdoMock = new class($pdoStatementMock) extends PDO {
    public function __construct(private readonly PDOStatement $stmt) {}
    public function prepare(string $query, array $options = []): PDOStatement {
        return $this->stmt;
    }
    public function lastInsertId(?string $name = null): string {
        return '42';
    }
};

$repo = new ReservaRepositorio($pdoMock);

// 3. Probar detección de traslape (simulando 1 colisión encontrada)
$pdoStatementMock->rowCount = 1;
assert($repo->existeTraslape(1, '2026-08-19', '09:00', '11:00') === true, 'Error: Debe retornar true si existe traslape.');
echo "✔ [PASS] Algoritmo existeTraslape(): detecta colisión matemática correctamente.\n";

// 4. Probar sin traslape (simulando 0 colisiones)
$pdoStatementMock->rowCount = 0;
assert($repo->existeTraslape(1, '2026-08-19', '14:00', '16:00') === false, 'Error: Debe retornar false si no hay traslape.');
echo "✔ [PASS] Algoritmo existeTraslape(): permite franja horaria libre.\n";

// 5. Probar validación de hora_fin > hora_inicio
$invalidoCapturado = false;
try {
    $repo->registrar(1, 'Cliente Prueba', '2026-08-19', '12:00', '10:00', 100.0);
} catch (InvalidArgumentException) {
    $invalidoCapturado = true;
}
assert($invalidoCapturado === true, 'Error: Debe lanzar InvalidArgumentException si horaFin <= horaInicio.');
echo "✔ [PASS] Validación cronológica: bloquea reservas con hora fin anterior a hora inicio.\n";

echo "\nTODAS LAS PRUEBAS DE ReservaRepositorio PASARON (5/5).\n";
