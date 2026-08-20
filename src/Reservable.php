<?php

declare(strict_types=1);

namespace App;

/**
 * Contrato que deben cumplir todos los espacios reservables.
 *
 * @concept ABSTRACCION
 * Este contrato define QUE debe poder hacer un espacio reservable
 * (verificar disponibilidad, calcular tarifa) sin especificar COMO
 * cada tipo concreto lo resuelve. El resto del sistema (por ejemplo,
 * GestorReservas) dependera unicamente de esta interfaz, nunca de
 * clases concretas.
 */
interface Reservable
{
    /**
     * Verifica si el espacio está disponible para el horario indicado.
     *
     * @concept ABSTRACCION
     * Cada clase concreta decide sus propias reglas de disponibilidad
     * (por hora, por bloques, etc.) sin exponer ese detalle al exterior.
     */
    public function verificarDisponibilidad(Horario $horario): bool;

    /**
     * Calcula la tarifa según duración (en minutos) y si aplica horario pico.
     *
     * @concept POLIMORFISMO
     * Cada clase concreta (SalaReunion, EscritorioIndividual, Cancha)
     * implementará este método con su propia lógica de cálculo,
     * pero será invocado siempre de la misma forma.
     */
    public function calcularTarifa(int $duracion, bool $esPico): float;
}
