<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

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
     * @return array<int, array<string, mixed>>
     */
    public function listarTodas(): array
    {
        $stmt = $this->pdo->prepare(self::SQL_LISTAR_TODAS);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare(self::SQL_BUSCAR_POR_ID);
        $stmt->execute([':id' => $id]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($resultado) ? $resultado : null;
    }

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

    public function eliminar(int $id): bool
    {
        $stmt = $this->pdo->prepare(self::SQL_ELIMINAR);
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }
}
