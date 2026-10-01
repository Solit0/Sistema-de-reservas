<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Servicio de protección contra ataques Cross-Site Request Forgery (CSRF).
 *
 * [SEGURIDAD]
 * Genera y valida tokens aleatorios almacenados en la sesión del usuario.
 * Utiliza bytes criptográficamente seguros y comparación en tiempo constante (hash_equals)
 * para mitigar ataques de temporización.
 */
final class Csrf
{
    private const CLAVE_SESION = 'csrf_token';

    /**
     * Obtiene el token CSRF activo o genera uno nuevo si no existe en la sesión.
     *
     * @return string Token en formato hexadecimal de 64 caracteres.
     */
    public static function generarToken(): string
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }

        if (empty($_SESSION[self::CLAVE_SESION]) || !is_string($_SESSION[self::CLAVE_SESION])) {
            $_SESSION[self::CLAVE_SESION] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::CLAVE_SESION];
    }

    /**
     * Alias de generarToken() para recuperar el token actual.
     */
    public static function obtenerToken(): string
    {
        return self::generarToken();
    }

    /**
     * Verifica que el token recibido coincida de forma segura con el almacenado en la sesión.
     *
     * @param string|null $token Token recibido típicamente por POST.
     *
     * @return bool True si el token es válido, false en caso contrario.
     */
    public static function verificarToken(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }

        $guardado = $_SESSION[self::CLAVE_SESION] ?? '';

        if (!is_string($guardado) || $guardado === '' || $token === null || $token === '') {
            return false;
        }

        // [SEGURIDAD] Comparación en tiempo constante para evitar ataques de canal lateral (timing attacks)
        return hash_equals($guardado, $token);
    }

    /**
     * Genera la etiqueta HTML oculta lista para incrustar en un formulario.
     *
     * @return string Etiqueta <input type="hidden" ...>
     */
    public static function campoHtml(): string
    {
        $token = self::generarToken();

        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            htmlspecialchars(self::CLAVE_SESION, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }
}
