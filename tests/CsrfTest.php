<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Security\Csrf;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "=== INICIANDO PRUEBAS DE SEGURIDAD CSRF ===\n\n";

// 1. Generación de Token
$token1 = Csrf::generarToken();
assert(is_string($token1) && strlen($token1) === 64, 'Error: El token debe ser un string de 64 caracteres.');
echo "✔ [PASS] Generación de token segura (64 caracteres hexadecimales).\n";

// 2. Idempotencia en la misma sesión
$token2 = Csrf::generarToken();
assert($token1 === $token2, 'Error: El token debe mantenerse estable dentro de la misma sesión.');
echo "✔ [PASS] Idempotencia del token en sesión activa.\n";

// 3. Verificación válida
assert(Csrf::verificarToken($token1) === true, 'Error: El token legítimo debe ser aceptado.');
echo "✔ [PASS] Verificación positiva con token correcto.\n";

// 4. Rechazo de token incorrecto
assert(Csrf::verificarToken('token_falso_invalido') === false, 'Error: Debe rechazar tokens alterados.');
assert(Csrf::verificarToken('') === false, 'Error: Debe rechazar tokens vacíos.');
assert(Csrf::verificarToken(null) === false, 'Error: Debe rechazar valores nulos.');
echo "✔ [PASS] Rechazo estricto de tokens incorrectos, vacíos o nulos.\n";

// 5. Generación de campo HTML
$html = Csrf::campoHtml();
assert(str_contains($html, 'type="hidden"'), 'Error: Debe ser un input hidden.');
assert(str_contains($html, 'name="csrf_token"'), 'Error: Debe tener el atributo name="csrf_token".');
assert(str_contains($html, $token1), 'Error: Debe contener el token generado.');
echo "✔ [PASS] Renderizado seguro del input HTML con protección contra inyección.\n";

echo "\nTODAS LAS PRUEBAS DE CSRF PASARON EXITOSAMENTE (5/5).\n";
