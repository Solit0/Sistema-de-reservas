<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Validation\Validador;

echo "=== INICIANDO SUITE DE PRUEBAS DE LA CLASE VALIDADOR ===\n\n";

// 1. Test Regla Requerido
$v1 = new Validador();
$v1->requerido('cliente', '')
   ->requerido('espacio_id', '   ')
   ->requerido('fecha', null)
   ->requerido('hora_inicio', '09:00');

assert(!$v1->esValido(), 'Error: Deberían existir errores de presencia.');
assert(isset($v1->getErrores()['cliente']), 'Error: cliente debería ser obligatorio.');
assert(isset($v1->getErrores()['espacio_id']), 'Error: espacio_id con espacios debería fallar.');
assert(isset($v1->getErrores()['fecha']), 'Error: fecha null debería fallar.');
assert(!isset($v1->getErrores()['hora_inicio']), 'Error: hora_inicio con valor no debería fallar.');
echo "✔ [PASS] Regla 'requerido': Detecta cadenas vacías, espacios en blanco y valores nulos.\n";

// 2. Test Longitud con soporte UTF-8 
$v2 = new Validador();
$v2->longitud('corto', 'ab', 3, 50)
   ->longitud('largo', str_repeat('a', 51), 3, 50)
   ->longitud('utf8_valido', 'María Peña', 3, 50); // 10 caracteres reales

assert(isset($v2->getErrores()['corto']), 'Error: texto menor a min debería fallar.');
assert(isset($v2->getErrores()['largo']), 'Error: texto mayor a max debería fallar.');
assert(!isset($v2->getErrores()['utf8_valido']), 'Error: texto UTF-8 válido no debería fallar.');
echo "✔ [PASS] Regla 'longitud': Valida límites mínimos/máximos y maneja caracteres UTF-8.\n";

// 3. Test Entero y Rangos
$v3 = new Validador();
$v3->entero('texto', 'no_es_numero')
   ->entero('decimal', '12.5')
   ->entero('fuera_rango', '5', 10, 100)
   ->entero('valido', '25', 1, 50);

assert(isset($v3->getErrores()['texto']), 'Error: texto no entero debería fallar.');
assert(isset($v3->getErrores()['decimal']), 'Error: decimal no es entero.');
assert(isset($v3->getErrores()['fuera_rango']), 'Error: 5 está fuera de [10, 100].');
assert(!isset($v3->getErrores()['valido']), 'Error: 25 es un entero válido en [1, 50].');
echo "✔ [PASS] Regla 'entero': Valida tipos enteros y rangos numéricos con FILTER_VALIDATE_INT.\n";

// 4. Test Número / Decimal y Límites
$v4 = new Validador();
$v4->numero('no_num', 'cien_dolares')
   ->numero('negativo', '-50.00', 0.01)
   ->numero('tarifa_valida', '180.50', 0.01);

assert(isset($v4->getErrores()['no_num']), 'Error: texto no numérico debería fallar.');
assert(isset($v4->getErrores()['negativo']), 'Error: tarifa negativa debería fallar.');
assert(!isset($v4->getErrores()['tarifa_valida']), 'Error: 180.50 es un número decimal válido.');
echo "✔ [PASS] Regla 'numero': Valida valores decimales y cotas mínimas/máximas.\n";

// 5. Test Lista Blanca (Whitelist)
$v5 = new Validador();
$tiposPermitidos = ['cancha', 'escritorio', 'sala'];
$v5->listaBlanca('tipo_invalido', 'piscina', $tiposPermitidos)
   ->listaBlanca('tipo_valido', 'sala', $tiposPermitidos);

assert(isset($v5->getErrores()['tipo_invalido']), 'Error: "piscina" no está en la lista blanca.');
assert(!isset($v5->getErrores()['tipo_valido']), 'Error: "sala" está en la lista blanca.');
echo "✔ [PASS] Regla 'listaBlanca': Restringe valores a colecciones permitidas de forma estricta.\n";

// 6. Test Fechas Calendario y Horas
$v6 = new Validador();
$v6->fecha('fecha_invalida', '2026-02-30') // Febrero no tiene 30 días
   ->fecha('formato_malo', '19/08/2026')   // Formato esperado es Y-m-d
   ->fecha('fecha_buena', '2026-08-19')
   ->hora('hora_invalida', '26:70')
   ->hora('hora_buena', '14:30');

assert(isset($v6->getErrores()['fecha_invalida']), 'Error: 2026-02-30 no existe en el calendario.');
assert(isset($v6->getErrores()['formato_malo']), 'Error: 19/08/2026 no coincide con Y-m-d.');
assert(!isset($v6->getErrores()['fecha_buena']), 'Error: 2026-08-19 es una fecha válida.');
assert(isset($v6->getErrores()['hora_invalida']), 'Error: 26:70 no es hora válida.');
assert(!isset($v6->getErrores()['hora_buena']), 'Error: 14:30 es hora válida.');
echo "✔ [PASS] Reglas 'fecha' y 'hora': Valida consistencia de calendario y formato horario.\n";

// 7. Test Comparación de Horarios (horaFin > horaInicio)
$v7 = new Validador();
$v7->horaMayorQue('hora_fin_menor', '09:00', '11:00')
   ->horaMayorQue('hora_fin_igual', '10:00', '10:00')
   ->horaMayorQue('hora_fin_valida', '12:00', '10:00');

assert(isset($v7->getErrores()['hora_fin_menor']), 'Error: hora_fin anterior a inicio debe fallar.');
assert(isset($v7->getErrores()['hora_fin_igual']), 'Error: hora_fin igual a inicio debe fallar.');
assert(!isset($v7->getErrores()['hora_fin_valida']), 'Error: 12:00 > 10:00 debe ser válido.');
echo "✔ [PASS] Regla 'horaMayorQue': Asegura que la reserva tenga intervalo temporal positivo.\n";

// 8. Test Expresión Regular y Correo Electrónico
$v8 = new Validador();
$v8->email('correo_malo', 'carlos@')
   ->email('correo_bueno', 'carlos@dominio.com')
   ->regex('solo_letras_malo', 'Carlos123', '/^[\p{L} ]+$/u')
   ->regex('solo_letras_bueno', 'Carlos Gómez', '/^[\p{L} ]+$/u');

assert(isset($v8->getErrores()['correo_malo']), 'Error: correo sin dominio debe fallar.');
assert(!isset($v8->getErrores()['correo_bueno']), 'Error: correo con formato estándar debe pasar.');
assert(isset($v8->getErrores()['solo_letras_malo']), 'Error: números deben fallar en regex de letras.');
assert(!isset($v8->getErrores()['solo_letras_bueno']), 'Error: letras y espacios con tildes deben pasar.');
echo "✔ [PASS] Reglas 'email' y 'regex': Valida patrones y formato RFC de correos.\n";

// 9. Test Acumulación Asociativa campo => mensaje y Primer Error por Campo
$v9 = new Validador();
$v9->requerido('cliente', '')
   ->longitud('cliente', '', 3, 50); // No debería sobreescribir el mensaje de requerido

$errores = $v9->getErrores();
assert(count($errores) === 1, 'Error: Solo debe conservarse el primer error de cada campo.');
assert(str_contains($errores['cliente'], 'obligatorio'), 'Error: Debe conservar el mensaje de requerido.');
echo "✔ [PASS] Acumulación asociativa: No satura con mensajes secundarios si el campo está vacío.\n";

// 10. Test Simulación de Validación Completa de Formulario de Reserva ($_POST simulado)
$postSimulado = [
    'cliente'     => 'Carlos Ruiz',
    'espacio_id'  => '2',
    'fecha'       => '2026-08-19',
    'hora_inicio' => '14:00',
    'hora_fin'    => '16:00',
    'es_pico'     => '1'
];

$vForm = new Validador();
$vForm->requerido('cliente', $postSimulado['cliente'])
      ->longitud('cliente', $postSimulado['cliente'], 3, 100)
      ->requerido('espacio_id', $postSimulado['espacio_id'])
      ->entero('espacio_id', $postSimulado['espacio_id'], 1)
      ->requerido('fecha', $postSimulado['fecha'])
      ->fecha('fecha', $postSimulado['fecha'])
      ->requerido('hora_inicio', $postSimulado['hora_inicio'])
      ->hora('hora_inicio', $postSimulado['hora_inicio'])
      ->requerido('hora_fin', $postSimulado['hora_fin'])
      ->hora('hora_fin', $postSimulado['hora_fin'])
      ->horaMayorQue('hora_fin', $postSimulado['hora_fin'], $postSimulado['hora_inicio']);

assert($vForm->esValido() === true, 'Error: El formulario con datos correctos debe ser válido.');
assert(empty($vForm->getErrores()), 'Error: No deben existir errores en formulario válido.');

// Regla de Negocio Externa: Agregar error de disponibilidad si el espacio está ocupado
$vForm->agregarError('disponibilidad', 'El espacio ya se encuentra reservado en el horario seleccionado.');
assert($vForm->esValido() === false, 'Error: Debe volverse inválido tras agregarError manual.');
assert($vForm->getError('disponibilidad') !== null, 'Error: Debe permitir consultar error individual.');

echo "✔ [PASS] Flujo completo: Validación de formulario y compatibilidad con reglas de negocio.\n";

echo "\nTODAS LAS PRUEBAS DEL VALIDADOR PASARON EXITOSAMENTE (10/10).\n";
