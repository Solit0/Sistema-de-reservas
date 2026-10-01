<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Repositorio de reservas: encapsula las operaciones de persistencia y consultas
 * sobre la tabla `reservas`, incluyendo la verificación matemática de traslapes de horario.
 *
 * [INYECCION-DEPENDENCIAS]
 * Se inyecta la instancia de PDO vía constructor.
 *
 * [CRUD & SEGURIDAD]
 * Todas las sentencias utilizan sentencias preparadas (prepare/execute) con parámetros
 * nombrados, garantizando protección total contra inyecciones SQL.
 */
final class ReservaRepositorio
{
    private const SQL_LISTAR_TODAS = 'SELECT r.id, r.espacio_id, r.cliente, r.fecha, r.hora_inicio, r.hora_fin, r.monto_total, r.created_at,
               e.nombre AS espacio_nombre, e.tipo AS espacio_tipo, e.tarifa_base AS espacio_tarifa_base
        FROM reservas r
        JOIN espacios e ON r.espacio_id = e.id
        ORDER BY r.fecha DESC, r.hora_inicio ASC';

    private const SQL_BUSCAR_POR_ID = 'SELECT r.id, r.espacio_id, r.cliente, r.fecha, r.hora_inicio, r.hora_fin, r.monto_total, r.created_at,
               e.nombre AS espacio_nombre, e.tipo AS espacio_tipo, e.tarifa_base AS espacio_tarifa_base
        FROM reservas r
        JOIN espacios e ON r.espacio_id = e.id
        WHERE r.id = :id
        LIMIT 1';

    private const SQL_REGISTRAR = 'INSERT INTO reservas (espacio_id, cliente, fecha, hora_inicio, hora_fin, monto_total)
        VALUES (:espacio_id, :cliente, :fecha, :hora_inicio, :hora_fin, :monto_total)';

    private const SQL_ELIMINAR = 'DELETE FROM reservas WHERE id = :id';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Lista todas las reservas registradas unidas con la información del espacio.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarTodas(): array
    {
        $stmt = $this->pdo->prepare(self::SQL_LISTAR_TODAS);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca una reserva específica por su ID.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare(self::SQL_BUSCAR_POR_ID);
        $stmt->execute([':id' => $id]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($resultado) ? $resultado : null;
    }

    /**
     * [ALGORITMO-TRASLAPE]
     * Verifica si existe conflicto horario para un espacio en una fecha dada.
     * Dos intervalos [A, B] y [C, D] se traslapan si y solo si: A < D && B > C.
     * En SQL: hora_inicio < :hora_fin AND hora_fin > :hora_inicio.
     *
     * @param int $espacioId ID del espacio a verificar.
     * @param string $fecha Fecha de la reserva (formato Y-m-d).
     * @param string $horaInicio Hora de inicio (formato H:i o H:i:s).
     * @param string $horaFin Hora de fin (formato H:i o H:i:s).
     * @param int|null $excluirId ID de reserva a excluir (útil al editar).
     * @return bool True si hay traslape (espacio ocupado), false si está libre.
     */
    public function existeTraslape(
        int $espacioId,
        string $fecha,
        string $horaInicio,
        string $horaFin,
        ?int $excluirId = null
    ): bool {
        $sql = 'SELECT COUNT(*) FROM reservas
                WHERE espacio_id = :espacio_id
                  AND fecha = :fecha
                  AND hora_inicio < :hora_fin
                  AND hora_fin > :hora_inicio';

        $params = [
            ':espacio_id'   => $espacioId,
            ':fecha'        => $fecha,
            ':hora_inicio'  => $horaInicio,
            ':hora_fin'     => $horaFin,
        ];

        if ($excluirId !== null) {
            $sql .= ' AND id != :excluir_id';
            $params[':excluir_id'] = $excluirId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return ((int)$stmt->fetchColumn()) > 0;
    }

    /**
     * Registra una nueva reserva en la base de datos.
     *
     * @param int $espacioId
     * @param string $cliente
     * @param string $fecha
     * @param string $horaInicio
     * @param string $horaFin
     * @param float $montoTotal
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
        $stmt = $this->pdo->prepare(self::SQL_REGISTRAR);
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
     * Elimina una reserva de la base de datos por su ID.
     *
     * @param int $id
     * @return bool True si se eliminó alguna fila.
     */
    public function eliminar(int $id): bool
    {
        $stmt = $this->pdo->prepare(self::SQL_ELIMINAR);
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }
}
