<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Repositories\ReservaRepositorio;

echo "=== INICIANDO SUITE DE PRUEBAS RESERVA REPOSITORIO (ANTI-TRASLAPE) ===\n\n";

/**
 * Test Double de PDOStatement para simulación en memoria sin requerir drivers nativos C.
 */
class FakeStatement extends PDOStatement
{
    /** @var array<int, array<string, mixed>> */
    private array $data;
    private int $cursor = 0;
    private int $affectedRows = 0;

    /**
     * @param array<int, array<string, mixed>> $data
     * @param int $affectedRows
     */
    public function __construct(array $data = [], int $affectedRows = 0)
    {
        $this->data = $data;
        $this->affectedRows = $affectedRows;
    }

    public function execute(?array $params = null): bool
    {
        return true;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->data;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        if ($this->cursor >= count($this->data)) {
            return false;
        }

        $row = $this->data[$this->cursor];
        $this->cursor++;
        return $row;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        if (empty($this->data)) {
            return 0;
        }
        $first = reset($this->data);
        return reset($first);
    }

    public function rowCount(): int
    {
        return $this->affectedRows;
    }
}

/**
 * Test Double de PDO para simulación completa del almacenamiento en memoria.
 */
class FakePDO extends PDO
{
    /** @var array<int, array<string, mixed>> */
    public array $espacios = [];

    /** @var array<int, array<string, mixed>> */
    public array $reservas = [];

    private int $lastId = 0;

    public function __construct()
    {
        // Constructor vacío para bypass de DSN nativo
    }

    public function lastInsertId(?string $name = null): string
    {
        return (string)$this->lastId;
    }

    public function prepare(string $query, array $options = []): PDOStatement
    {
        $pdo = $this;

        return new class($pdo, $query) extends PDOStatement {
            private FakePDO $fakePdo;
            private string $query;
            private ?FakeStatement $resultStmt = null;

            public function __construct(FakePDO $fakePdo, string $query)
            {
                $this->fakePdo = $fakePdo;
                $this->query = $query;
            }

            public function execute(?array $params = null): bool
            {
                $params = $params ?? [];

                if (str_contains($this->query, 'INSERT INTO reservas')) {
                    $newId = count($this->fakePdo->reservas) + 1;
                    $this->fakePdo->reservas[$newId] = [
                        'id'          => $newId,
                        'espacio_id'  => (int)$params[':espacio_id'],
                        'cliente'     => (string)$params[':cliente'],
                        'fecha'       => (string)$params[':fecha'],
                        'hora_inicio' => (string)$params[':hora_inicio'],
                        'hora_fin'    => (string)$params[':hora_fin'],
                        'monto_total' => (float)$params[':monto_total'],
                        'created_at'  => date('Y-m-d H:i:s'),
                    ];
                    $this->fakePdo->setLastId($newId);
                    $this->resultStmt = new FakeStatement([], 1);
                    return true;
                }

                if (str_contains($this->query, 'SELECT COUNT(*) FROM reservas')) {
                    $espacioId = (int)$params[':espacio_id'];
                    $fecha = (string)$params[':fecha'];
                    $horaInicio = (string)$params[':hora_inicio'];
                    $horaFin = (string)$params[':hora_fin'];
                    $excluirId = isset($params[':excluir_id']) ? (int)$params[':excluir_id'] : null;

                    $count = 0;
                    foreach ($this->fakePdo->reservas as $r) {
                        if ($excluirId !== null && (int)$r['id'] === $excluirId) {
                            continue;
                        }
                        if ((int)$r['espacio_id'] === $espacioId && (string)$r['fecha'] === $fecha) {
                            // Algoritmo matemático: traslape si r.hora_inicio < horaFin && r.hora_fin > horaInicio
                            if ((string)$r['hora_inicio'] < $horaFin && (string)$r['hora_fin'] > $horaInicio) {
                                $count++;
                            }
                        }
                    }
                    $this->resultStmt = new FakeStatement([['count' => $count]]);
                    return true;
                }

                if (str_contains($this->query, 'WHERE r.id = :id')) {
                    $id = (int)($params[':id'] ?? 0);
                    $fila = null;
                    if (isset($this->fakePdo->reservas[$id])) {
                        $r = $this->fakePdo->reservas[$id];
                        $espId = (int)$r['espacio_id'];
                        $esp = $this->fakePdo->espacios[$espId] ?? [
                            'nombre'      => 'Espacio General',
                            'tipo'        => 'sala',
                            'tarifa_base' => 100.0,
                        ];
                        $fila = array_merge($r, [
                            'espacio_nombre'      => $esp['nombre'],
                            'espacio_tipo'        => $esp['tipo'],
                            'espacio_tarifa_base' => $esp['tarifa_base'],
                        ]);
                    }
                    $this->resultStmt = new FakeStatement($fila ? [$fila] : []);
                    return true;
                }

                if (str_contains($this->query, 'SELECT r.id, r.espacio_id, r.cliente')) {
                    $filas = [];
                    foreach ($this->fakePdo->reservas as $r) {
                        $espId = (int)$r['espacio_id'];
                        $esp = $this->fakePdo->espacios[$espId] ?? [
                            'nombre'      => 'Espacio General',
                            'tipo'        => 'sala',
                            'tarifa_base' => 100.0,
                        ];
                        $filas[] = array_merge($r, [
                            'espacio_nombre'      => $esp['nombre'],
                            'espacio_tipo'        => $esp['tipo'],
                            'espacio_tarifa_base' => $esp['tarifa_base'],
                        ]);
                    }
                    $this->resultStmt = new FakeStatement($filas);
                    return true;
                }

                if (str_contains($this->query, 'DELETE FROM reservas WHERE id = :id')) {
                    $id = (int)($params[':id'] ?? 0);
                    $affected = 0;
                    if (isset($this->fakePdo->reservas[$id])) {
                        unset($this->fakePdo->reservas[$id]);
                        $affected = 1;
                    }
                    $this->resultStmt = new FakeStatement([], $affected);
                    return true;
                }

                $this->resultStmt = new FakeStatement([]);
                return true;
            }

            public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
            {
                return $this->resultStmt ? $this->resultStmt->fetchAll($mode) : [];
            }

            public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
            {
                return $this->resultStmt ? $this->resultStmt->fetch($mode) : false;
            }

            public function fetchColumn(int $column = 0): mixed
            {
                return $this->resultStmt ? $this->resultStmt->fetchColumn($column) : 0;
            }

            public function rowCount(): int
            {
                return $this->resultStmt ? $this->resultStmt->rowCount() : 0;
            }
        };
    }

    public function setLastId(int $id): void
    {
        $this->lastId = $id;
    }
}

// Inicialización de la prueba
$fakePdo = new FakePDO();
$fakePdo->espacios[1] = ['nombre' => 'Sala de Juntas', 'tipo' => 'sala', 'tarifa_base' => 180.0];
$fakePdo->espacios[2] = ['nombre' => 'Cancha Sintética', 'tipo' => 'cancha', 'tarifa_base' => 120.0];

$repo = new ReservaRepositorio($fakePdo);

// 1. Test lista vacía inicial
$reservasIniciales = $repo->listarTodas();
assert(count($reservasIniciales) === 0, 'Error: Inicialmente no deberían haber reservas.');
echo "✔ [PASS] Repositorio inicializado sin reservas previas\n";

// 2. Test registrar reserva
$idReserva1 = $repo->registrar(1, 'Carlos Gómez', '2026-10-05', '09:00', '11:00', 360.00);
assert($idReserva1 === 1, 'Error: El ID generado debe ser 1.');

$reserva = $repo->buscarPorId(1);
assert($reserva !== null, 'Error: La reserva registrada debe existir.');
assert($reserva['cliente'] === 'Carlos Gómez', 'Error: El cliente no coincide.');
assert($reserva['espacio_nombre'] === 'Sala de Juntas', 'Error: El join con espacios falló.');
echo "✔ [PASS] Inserción de reserva y consulta JOIN exitosa\n";

// 3. Test detección de traslape - Intervalo totalmente contenido (10:00 - 10:30)
$hayTraslape1 = $repo->existeTraslape(1, '2026-10-05', '10:00', '10:30');
assert($hayTraslape1 === true, 'Error: Debió detectar traslape para intervalo contenido.');
echo "✔ [PASS] Detección de traslape: Intervalo interno contenido (10:00 - 10:30)\n";

// 4. Test detección de traslape - Intervalo cruzado por inicio (08:30 - 09:30)
$hayTraslape2 = $repo->existeTraslape(1, '2026-10-05', '08:30', '09:30');
assert($hayTraslape2 === true, 'Error: Debió detectar traslape en frontera inicial.');
echo "✔ [PASS] Detección de traslape: Frontera inicial cruzada (08:30 - 09:30)\n";

// 5. Test detección de traslape - Intervalo cruzado por fin (10:30 - 12:00)
$hayTraslape3 = $repo->existeTraslape(1, '2026-10-05', '10:30', '12:00');
assert($hayTraslape3 === true, 'Error: Debió detectar traslape en frontera final.');
echo "✔ [PASS] Detección de traslape: Frontera final cruzada (10:30 - 12:00)\n";

// 6. Test no traslape - Horario adyacente posterior exacto (11:00 - 13:00)
$hayTraslape4 = $repo->existeTraslape(1, '2026-10-05', '11:00', '13:00');
assert($hayTraslape4 === false, 'Error: Horario contiguo exacto (11:00) no debe considerarse traslape.');
echo "✔ [PASS] No traslape: Horario adyacente posterior libre (11:00 - 13:00)\n";

// 7. Test no traslape - Horario adyacente previo exacto (07:00 - 09:00)
$hayTraslape5 = $repo->existeTraslape(1, '2026-10-05', '07:00', '09:00');
assert($hayTraslape5 === false, 'Error: Horario contiguo previo (09:00) no debe considerarse traslape.');
echo "✔ [PASS] No traslape: Horario adyacente previo libre (07:00 - 09:00)\n";

// 8. Test no traslape - Diferente fecha
$hayTraslape6 = $repo->existeTraslape(1, '2026-10-06', '09:30', '10:30');
assert($hayTraslape6 === false, 'Error: Mismo horario en fecha diferente no debe traslapar.');
echo "✔ [PASS] No traslape: Fecha diferente en el mismo horario\n";

// 9. Test no traslape - Diferente espacio
$hayTraslape7 = $repo->existeTraslape(2, '2026-10-05', '09:30', '10:30');
assert($hayTraslape7 === false, 'Error: Mismo horario en diferente espacio no debe traslapar.');
echo "✔ [PASS] No traslape: Diferente espacio en el mismo horario\n";

// 10. Test excluir ID en actualización
$hayTraslape8 = $repo->existeTraslape(1, '2026-10-05', '09:00', '11:00', 1);
assert($hayTraslape8 === false, 'Error: Excluir la misma reserva debe permitir mantener su propio horario.');
echo "✔ [PASS] Exclusión de ID propio en verificación de traslape\n";

// 11. Test eliminación
$eliminado = $repo->eliminar(1);
assert($eliminado === true, 'Error: La reserva debió eliminarse.');
assert($repo->buscarPorId(1) === null, 'Error: La reserva no debe existir tras eliminación.');
echo "✔ [PASS] Eliminación de reserva exitosa\n";

echo "\n>>> TODAS LAS PRUEBAS DE RESERVA REPOSITORIO PASARON EXITOSAMENTE (11/11)\n";
