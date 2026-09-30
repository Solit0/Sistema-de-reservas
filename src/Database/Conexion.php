<?php

declare(strict_types=1);

namespace App\Database;

use LogicException;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Conexión PDO a MySQL/MariaDB implementada como Singleton.
 *
 * [ENCAPSULAMIENTO]
 * El acceso a la única instancia de PDO se encapsula dentro de esta
 * clase: el constructor y __clone() son privados, por lo que nada fuera
 * de esta clase puede instanciar ni clonar nuevas conexiones. La
 * configuración (host, puerto, credenciales, charset) se carga desde
 * config/config.php y queda oculta para el resto del sistema.
 *
 * [INYECCION-DEPENDENCIAS]
 * Conexion::obtener() se invoca UNA sola vez desde las páginas públicas
 * (public/*.php). El PDO resultante se inyecta por constructor a los
 * repositorios, y los repositorios NUNCA llaman a Conexion directamente.
 * Así, los repositorios solo dependen de la abstracción PDO y no de esta
 * clase concreta, lo que facilita las pruebas (inyectar un PDO de prueba
 * o un mock en lugar de la conexión real).
 */
final class Conexion
{
    /** [ENCAPSULAMIENTO] Única instancia de PDO del proceso. */
    private static ?PDO $instancia = null;

    /**
     * [ENCAPSULAMIENTO] Constructor privado: impide crear más instancias.
     */
    private function __construct()
    {
    }

    /**
     * [ENCAPSULAMIENTO] Clonación privada: impide clonar el singleton.
     */
    private function __clone()
    {
    }

    /**
     * [ENCAPSULAMIENTO] Impide deserializar (unserialize) el singleton.
     */
    public function __wakeup(): void
    {
        throw new LogicException('No se puede deserializar una instancia de Conexion.');
    }

    /**
     * Devuelve la única conexión PDO del proceso, creándola si hace falta.
     *
     * @return PDO Conexión activa a la base de datos.
     *
     * @throws RuntimeException Si falta config/config.php o si la conexión falla.
     */
    public static function obtener(): PDO
    {
        if (self::$instancia === null) {
            $config = self::cargarConfig();

            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['dbname'],
                $config['charset']
            );

            $opciones = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            try {
                self::$instancia = new PDO($dsn, $config['user'], $config['pass'], $opciones);
            } catch (PDOException $e) {
                // [ENCAPSULAMIENTO] Se ocultan usuario/contraseña del mensaje.
                throw new RuntimeException(
                    'No se pudo conectar a la base de datos',
                    0,
                    $e
                );
            }
        }

        return self::$instancia;
    }

    /**
     * Carga la configuración local desde config/config.php.
     *
     * @return array<string, mixed> Valores de conexión (host, port, dbname, ...).
     *
     * @throws RuntimeException Si el archivo de configuración no existe.
     */
    private static function cargarConfig(): array
    {
        $ruta = __DIR__ . '/../../config/config.php';

        if (!file_exists($ruta)) {
            throw new RuntimeException(
                'Falta config/config.php: copia config/config.example.php y ajusta tus credenciales'
            );
        }

        return require $ruta;
    }
}