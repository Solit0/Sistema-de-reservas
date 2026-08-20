<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\Domain\Espacios\Cancha;
use App\Domain\Espacios\EscritorioIndividual;
use App\Domain\Espacios\SalaReunion;
use App\Domain\Horario;
use App\Services\GestorReservas;
use App\Services\ReporteConsolaService;
use App\Services\ReservaStorageService;

function imprimirCabecera(): void
{
    echo "\n============================================================\n";
    echo "    SISTEMA DE RESERVAS DE ESPACIOS - DEMOSTRACIÓN CLI\n";
    echo "============================================================\n\n";
}

function construirReservas(): GestorReservas
{
    $gestor = new GestorReservas();

    $sala = new SalaReunion('Sala de Juntas', 8);
    $escritorio = new EscritorioIndividual('Escritorio 01', 1);
    $cancha = new Cancha('Cancha Sintética', 10);

    $gestor->registrarEspacio($sala);
    $gestor->registrarEspacio($escritorio);
    $gestor->registrarEspacio($cancha);

    $gestor->crearReserva(
        $sala,
        new Horario(
            DateTimeImmutable::createFromFormat('Y-m-d H:i', '2026-08-19 09:00'),
            DateTimeImmutable::createFromFormat('Y-m-d H:i', '2026-08-19 11:00')
        ),
        'María López',
        false
    );

    $gestor->crearReserva(
        $sala,
        new Horario(
            DateTimeImmutable::createFromFormat('Y-m-d H:i', '2026-08-19 16:00'),
            DateTimeImmutable::createFromFormat('Y-m-d H:i', '2026-08-19 18:00')
        ),
        'Carlos Ruiz',
        true
    );

    $gestor->crearReserva(
        $escritorio,
        new Horario(
            DateTimeImmutable::createFromFormat('Y-m-d H:i', '2026-08-19 10:00'),
            DateTimeImmutable::createFromFormat('Y-m-d H:i', '2026-08-19 12:30')
        ),
        'Ana Gómez',
        false
    );

    $gestor->crearReserva(
        $cancha,
        new Horario(
            DateTimeImmutable::createFromFormat('Y-m-d H:i', '2026-08-19 13:00'),
            DateTimeImmutable::createFromFormat('Y-m-d H:i', '2026-08-19 14:30')
        ),
        'Equipo Fútbol',
        false
    );

    $gestor->crearReserva(
        $cancha,
        new Horario(
            DateTimeImmutable::createFromFormat('Y-m-d H:i', '2026-08-19 18:00'),
            DateTimeImmutable::createFromFormat('Y-m-d H:i', '2026-08-19 19:00')
        ),
        'Club A',
        true
    );

    return $gestor;
}

imprimirCabecera();

// 1. Carga y Reporte Polimórfico del Día
$gestor = construirReservas();
$fechaReporte = new DateTimeImmutable('2026-08-19');
$gestor->generarReporteDelDia($fechaReporte);
(new ReporteConsolaService())->imprimirResumenEstadistico(
    $fechaReporte,
    $gestor->obtenerEspacios()
);

// 2. Consulta de Disponibilidad Polimórfica
echo "\nEstado de disponibilidad por espacio (15:00 a 16:00):\n";
$horarioConsulta = new Horario(
    new DateTimeImmutable('2026-08-19 15:00'),
    new DateTimeImmutable('2026-08-19 16:00')
);

foreach ($gestor->obtenerEspacios() as $espacio) {
    echo sprintf(
        "- %s: %s\n",
        $espacio->getNombre(),
        $espacio->verificarDisponibilidad($horarioConsulta) ? 'Disponible' : 'Ocupado'
    );
}

// 3. Manejo de Archivos: Exportación a JSON (Avance de Persistencia)
echo "\n--- Manejo de Archivos: Persistencia en JSON ---\n";
$storage = new ReservaStorageService();
$archivoJson = __DIR__ . '/reservas.json';
$storage->exportarAJson($gestor->obtenerEspacios(), $archivoJson);
echo "✔ Reservas exportadas con éxito a: {$archivoJson}\n";

$datosCargados = $storage->leerDeJson($archivoJson);
echo "✔ Total de espacios leídos desde el archivo JSON: " . count($datosCargados) . "\n";

// 4. Demostración de Encapsulamiento y Manejo de Excepciones en Vivo
echo "\n--- Demostración de Validación y Excepción Controlada ---\n";
try {
    echo "Intentando crear reserva en horario ya ocupado (Sala de Juntas 10:00 - 11:00)...\n";
    $primerEspacio = $gestor->obtenerEspacios()[0];
    $gestor->crearReserva(
        $primerEspacio,
        new Horario(
            DateTimeImmutable::createFromFormat('Y-m-d H:i', '2026-08-19 10:00'),
            DateTimeImmutable::createFromFormat('Y-m-d H:i', '2026-08-19 11:00')
        ),
        'Cliente Conflicto'
    );
} catch (InvalidArgumentException $e) {
    echo "✔ Excepción capturada correctamente: " . $e->getMessage() . "\n";
}

echo "\n";
