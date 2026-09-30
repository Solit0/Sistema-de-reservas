<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Domain\Espacios\Cancha;
use App\Domain\Espacios\EscritorioIndividual;
use App\Domain\Espacios\SalaReunion;
use App\Domain\Horario;
use App\Services\GestorReservas;

echo "=== INICIANDO SUITE DE PRUEBAS AUTOMATIZADAS (CASO A) ===\n\n";

$gestor = new GestorReservas();

// 1. Instanciación heterogénea (1 sala, 1 escritorio, 1 cancha)
$sala = new SalaReunion('Sala de Juntas', 8);
$escritorio = new EscritorioIndividual('Escritorio 01', 1);
$cancha = new Cancha('Cancha Sintética', 10);

$gestor->registrarEspacio($sala);
$gestor->registrarEspacio($escritorio);
$gestor->registrarEspacio($cancha);

// 2. Test Sala: Tarifa regular vs pico
$hSalaNormal = new Horario(
    new DateTimeImmutable('2026-08-19 09:00'),
    new DateTimeImmutable('2026-08-19 11:00')
);
$reservaSalaNormal = $gestor->crearReserva($sala, $hSalaNormal, 'María López', false);
assert($reservaSalaNormal->getCostoCalculado() === 360.00, 'Error: Sala horario normal debería ser $360.00');
echo "✔ [PASS] Sala de Reunión - Horario regular (2h * $180 = $360.00)\n";

$hSalaPico = new Horario(
    new DateTimeImmutable('2026-08-19 16:00'),
    new DateTimeImmutable('2026-08-19 18:00')
);
$reservaSalaPico = $gestor->crearReserva($sala, $hSalaPico, 'Carlos Ruiz', true);
assert($reservaSalaPico->getCostoCalculado() === 450.00, 'Error: Sala horario pico (+25%) debería ser $450.00');
echo "✔ [PASS] Sala de Reunión - Horario pico con recargo 25% ($450.00)\n";

// 3. Test Escritorio: Tarifa plana fraccionada
$hEscritorio = new Horario(
    new DateTimeImmutable('2026-08-19 10:00'),
    new DateTimeImmutable('2026-08-19 12:30')
);
$reservaEscritorio = $gestor->crearReserva($escritorio, $hEscritorio, 'Ana Gómez', false);
assert($reservaEscritorio->getCostoCalculado() === 187.50, 'Error: Escritorio 2.5h debería ser $187.50');
echo "✔ [PASS] Escritorio Individual - Tarifa plana (2.5h * $75 = $187.50)\n";

// 4. Test Cancha: Bloques redondeados con ceil() y recargo fijo pico
$hCanchaBloques = new Horario(
    new DateTimeImmutable('2026-08-19 13:00'),
    new DateTimeImmutable('2026-08-19 14:30')
);
$reservaCancha = $gestor->crearReserva($cancha, $hCanchaBloques, 'Equipo Fútbol', false);
assert($reservaCancha->getCostoCalculado() === 240.00, 'Error: Cancha 90 min (2 bloques * $120) debería ser $240.00');
echo "✔ [PASS] Cancha Sintética - Bloques cerrados con ceil() (90 min -> 2 bloques = $240.00)\n";

$hCanchaPico = new Horario(
    new DateTimeImmutable('2026-08-19 18:00'),
    new DateTimeImmutable('2026-08-19 19:00')
);
$reservaCanchaPico = $gestor->crearReserva($cancha, $hCanchaPico, 'Club A', true);
assert($reservaCanchaPico->getCostoCalculado() === 155.00, 'Error: Cancha pico debería ser $155.00');
echo "✔ [PASS] Cancha Sintética - Horario pico con recargo fijo por bloque ($120 + $35 = $155.00)\n";

// 5. Test Encapsulamiento y Prevención de Traslapes
$excepcionCapturada = false;
try {
    $hConflicto = new Horario(
        new DateTimeImmutable('2026-08-19 10:00'),
        new DateTimeImmutable('2026-08-19 11:00')
    );
    $gestor->crearReserva($sala, $hConflicto, 'Intruso');
} catch (InvalidArgumentException $e) {
    $excepcionCapturada = true;
}

assert($excepcionCapturada === true, 'Error: Debería lanzar InvalidArgumentException al traslapar horario');
echo "✔ [PASS] Encapsulamiento - Invariante protegido ante traslapes con InvalidArgumentException\n";

echo "\nTODAS LAS PRUEBAS PASARON EXITOSAMENTE (5/5).\n";
