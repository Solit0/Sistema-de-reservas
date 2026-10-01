<?php

declare(strict_types=1);

namespace App\Repositories;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Repositorio para la persistencia relacional y control de concurrencia en Reservas.
 *
 * [INYECCION-DEPENDENCIAS]
 * Recibe una instancia de PDO por constructor para interactuar con la base de datos MySQL/MariaDB.
 *
 * [SEGURIDAD]
 * Todas las operaciones utilizan sentencias preparadas (PDO::prepare) con enlaces de parámetros,
 * erradicando cualquier posibilidad de inyecciones SQL.
 *
 * [VALIDACION]
 * Implementa la validación central del Caso A: verificación matemática de solapamientos
 * de horarios para un mismo espacio físico en una fecha determinada.
 */
final class ReservaRepositorio
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * [VALIDACION] [SEGURIDAD]
     * Verifica si existe una colisión de horario para el espacio indicado.
     *
     * Fórmula matemática de traslape entre intervalos:
     * Un nuevo rango (inicio_nuevo, fin_nuevo) colisiona con un rango existente (hora_inicio, hora_fin) si y solo si:
     *   (inicio_nuevo < hora_fin) AND (fin_nuevo > hora_inicio)
     *
     * @param int $espacioId ID del espacio en la tabla 'espacios'
     * @param string $fecha Fecha de la reserva en formato 'YYYY-MM-DD'
     * @param string $horaInicio Hora de inicio en formato 'HH:MM' o 'HH:MM:SS'
     * @param string $horaFin Hora de fin en formato 'HH:MM' o 'HH:MM:SS'
     * @return bool True si ya existe al menos una reserva que se superpone, false si está libre.
     */
    public function existeTraslape(int $espacioId, string $fecha, string $horaInicio, string $horaFin): bool
    {
        // [SEGURIDAD] Consulta preparada PDO
        $sql = 'SELECT COUNT(*) FROM reservas
                WHERE espacio_id = :espacio_id
                  AND fecha = :fecha
                  AND (:hora_inicio < hora_fin AND :hora_fin > hora_inicio)';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':espacio_id'  => $espacioId,
            ':fecha'       => $fecha,
            ':hora_inicio' => $horaInicio,
            ':hora_fin'    => $horaFin,
        ]);

        return ((int)$stmt->fetchColumn()) > 0;
    }

    /**
     * [CRUD-CREATE] [SEGURIDAD]
     * Registra una nueva reserva en la base de datos garantizando parámetros tipados.
     *
     * @return int ID de la reserva insertada.
     */
    public function registrar(
        int $espacioId,
        string $cliente,
        string $fecha,
        string $horaInicio,
        string $horaFin,
        float $montoTotal
    ): int {
        if ($horaFin <= $horaInicio) {
            throw new InvalidArgumentException('La hora de finalización debe ser posterior a la hora de inicio.');
        }

        if ($this->existeTraslape($espacioId, $fecha, $horaInicio, $horaFin)) {
            throw new InvalidArgumentException('El horario seleccionado colisiona con una reserva previa para este espacio.');
        }

        $sql = 'INSERT INTO reservas (espacio_id, cliente, fecha, hora_inicio, hora_fin, monto_total)
                VALUES (:espacio_id, :cliente, :fecha, :hora_inicio, :hora_fin, :monto_total)';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':espacio_id'   => $espacioId,
            ':cliente'      => trim($cliente),
            ':fecha'        => $fecha,
            ':hora_inicio'  => $horaInicio,
            ':hora_fin'     => $horaFin,
            ':monto_total'  => $montoTotal,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * [CRUD-READ]
     * Obtiene el listado completo de reservas registradas con información del espacio asociado.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarTodas(): array
    {
        $sql = 'SELECT r.id, r.espacio_id, r.cliente, r.fecha, r.hora_inicio, r.hora_fin, r.monto_total, r.created_at,
                       e.nombre AS espacio_nombre, e.tipo AS espacio_tipo, e.tarifa_base AS espacio_tarifa
                FROM reservas r
                JOIN espacios e ON r.espacio_id = e.id
                ORDER BY r.fecha DESC, r.hora_inicio ASC';

        $stmt = $this->pdo->query($sql);

        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * [CRUD-READ]
     * Obtiene las reservas asociadas a una fecha específica.
     *
     * @return array<int, array<string, mixed>>
     */
    public function obtenerPorFecha(string $fecha): array
    {
        $sql = 'SELECT r.id, r.espacio_id, r.cliente, r.fecha, r.hora_inicio, r.hora_fin, r.monto_total,
                       e.nombre AS espacio_nombre, e.tipo AS espacio_tipo
                FROM reservas r
                JOIN espacios e ON r.espacio_id = e.id
                WHERE r.fecha = :fecha
                ORDER BY r.hora_inicio ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':fecha' => $fecha]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
