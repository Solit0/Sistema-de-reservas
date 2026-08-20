<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\Contracts\Reservable;
use App\Domain\Espacios\Cancha;
use App\Domain\Espacios\EscritorioIndividual;
use App\Domain\Espacios\SalaReunion;
use App\Domain\Horario;
use App\Services\GestorReservas;
use DateTimeImmutable;

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

    $fechaBase = new DateTimeImmutable('2026-08-19');

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

$gestor = construirReservas();
$gestor->generarReporteDelDia(new DateTimeImmutable('2026-08-19'));

echo "\nEstado de disponibilidad por espacio:\n";
foreach ($gestor->obtenerEspacios() as $espacio) {
    echo sprintf(
        "- %s: %s\n",
        $espacio->getNombre(),
        $espacio->verificarDisponibilidad(
            new Horario(
                new DateTimeImmutable('2026-08-19 15:00'),
                new DateTimeImmutable('2026-08-19 16:00')
            )
        ) ? 'Disponible' : 'Ocupado'
    );
}

echo "\n";
