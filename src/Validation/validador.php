<?php

declare(strict_types=1);

namespace App\Validation;
use DateTimeImmutable;
class Validador
{
    /** @var array<string, string> Arreglo asociativo campo => mensaje */
    private array $errores = [];


    public function requerido(string $campo, ?string $valor, string $mensaje = ''): self
    {
        if ($valor === null || trim((string) $valor) === '') {
            $this->agregarError(
                $campo,
                $mensaje !== '' ? $mensaje : "El campo $campo es obligatorio."
            );
        }
        return $this;
    }

    public function longitud(string $campo, ?string $valor, int $min, int $max, string $mensaje = ''): self
    {
        if ($this->tieneError($campo) || $valor === null) {
            return $this;
        }

        $longitud = mb_strlen(trim((string) $valor), 'UTF-8');
        if ($longitud < $min || $longitud > $max) {
            $this->agregarError(
                $campo,
                $mensaje !== '' ? $mensaje : "El campo $campo debe tener entre $min y $max caracteres."
            );
        }
        return $this;
    }

    public function entero(string $campo, mixed $valor, ?int $min = null, ?int $max = null, string $mensaje = ''): self
    {
        if ($this->tieneError($campo) || $valor === null || $valor === '') {
            return $this;
        }

        $opciones = [];
        if ($min !== null) {
            $opciones['options']['min_range'] = $min;
        }
        if ($max !== null) {
            $opciones['options']['max_range'] = $max;
        }

        if (filter_var($valor, FILTER_VALIDATE_INT, $opciones) === false) {
            if ($min !== null && $max !== null) {
                $defaultMsg = "El campo $campo debe ser un número entero entre $min y $max.";
            } elseif ($min !== null) {
                $defaultMsg = "El campo $campo debe ser un número entero mayor o igual a $min.";
            } else {
                $defaultMsg = "El campo $campo debe ser un número entero válido.";
            }
            $this->agregarError($campo, $mensaje !== '' ? $mensaje : $defaultMsg);
        }
        return $this;
    }

    public function numero(string $campo, mixed $valor, ?float $min = null, ?float $max = null, string $mensaje = ''): self
    {
        if ($this->tieneError($campo) || $valor === null || $valor === '') {
            return $this;
        }

        if (!is_numeric($valor)) {
            $this->agregarError($campo, $mensaje !== '' ? $mensaje : "El campo $campo debe ser un valor numérico.");
            return $this;
        }

        $num = (float) $valor;
        if ($min !== null && $num < $min) {
            $this->agregarError($campo, $mensaje !== '' ? $mensaje : "El campo $campo debe ser mayor o igual a $min.");
        } elseif ($max !== null && $num > $max) {
            $this->agregarError($campo, $mensaje !== '' ? $mensaje : "El campo $campo debe ser menor o igual a $max.");
        }

        return $this;
    }

    /**
     * Valida que el valor pertenezca a una lista blanca de valores permitidos.
     *
     * @param array<mixed> $permitidos
     */
    public function listaBlanca(string $campo, mixed $valor, array $permitidos, string $mensaje = ''): self
    {
        if ($this->tieneError($campo) || $valor === null || $valor === '') {
            return $this;
        }

        if (!in_array($valor, $permitidos, true)) {
            $this->agregarError(
                $campo,
                $mensaje !== '' ? $mensaje : "El valor seleccionado para $campo no es válido."
            );
        }
        return $this;
    }


    public function email(string $campo, ?string $valor, string $mensaje = ''): self
    {
        if ($this->tieneError($campo) || $valor === null || $valor === '') {
            return $this;
        }

        if (!filter_var($valor, FILTER_VALIDATE_EMAIL)) {
            $this->agregarError(
                $campo,
                $mensaje !== '' ? $mensaje : "El campo $campo no es un correo electrónico válido."
            );
        }
        return $this;
    }

    public function fecha(string $campo, ?string $valor, string $formato = 'Y-m-d', string $mensaje = ''): self
    {
        if ($this->tieneError($campo) || $valor === null || $valor === '') {
            return $this;
        }

        $dt = DateTimeImmutable::createFromFormat($formato, trim((string) $valor));
        if ($dt === false || $dt->format($formato) !== trim((string) $valor)) {
            $this->agregarError(
                $campo,
                $mensaje !== '' ? $mensaje : "El campo $campo no contiene una fecha válida ($formato)."
            );
        }
        return $this;
    }

    public function hora(string $campo, ?string $valor, string $formato = 'H:i', string $mensaje = ''): self
    {
        if ($this->tieneError($campo) || $valor === null || $valor === '') {
            return $this;
        }

        $dt = DateTimeImmutable::createFromFormat($formato, trim((string) $valor));
        if ($dt === false || $dt->format($formato) !== trim((string) $valor)) {
            $this->agregarError(
                $campo,
                $mensaje !== '' ? $mensaje : "El campo $campo no contiene una hora válida ($formato)."
            );
        }
        return $this;
    }

    public function horaMayorQue(string $campoFin, ?string $horaFin, ?string $horaInicio, string $mensaje = ''): self
    {
        if ($this->tieneError($campoFin) || empty($horaFin) || empty($horaInicio)) {
            return $this;
        }

        if ($horaFin <= $horaInicio) {
            $this->agregarError(
                $campoFin,
                $mensaje !== '' ? $mensaje : 'La hora de fin debe ser posterior a la hora de inicio.'
            );
        }
        return $this;
    }

    public function regex(string $campo, ?string $valor, string $patron, string $mensaje = ''): self
    {
        if ($this->tieneError($campo) || $valor === null || $valor === '') {
            return $this;
        }

        if (preg_match($patron, (string) $valor) !== 1) {
            $this->agregarError(
                $campo,
                $mensaje !== '' ? $mensaje : "El formato del campo $campo no es válido."
            );
        }
        return $this;
    }

    public function iguales(string $campo, mixed $a, mixed $b, string $mensaje = ''): self
    {
        if (!$this->tieneError($campo) && $a !== $b) {
            $this->agregarError(
                $campo,
                $mensaje !== '' ? $mensaje : 'Los valores no coinciden.'
            );
        }
        return $this;
    }


    public function agregarError(string $campo, string $mensaje): self
    {
        if (!$this->tieneError($campo)) {
            $this->errores[$campo] = $mensaje;
        }
        return $this;
    }

    public function tieneError(string $campo): bool
    {
        return isset($this->errores[$campo]);
    }

    public function getError(string $campo): ?string
    {
        return $this->errores[$campo] ?? null;
    }

    /**
     * @return array<string, string> Arreglo asociativo con todos los errores.
     */
    public function getErrores(): array
    {
        return $this->errores;
    }

    public function esValido(): bool
    {
        return empty($this->errores);
    }

    public function limpiar(): self
    {
        $this->errores = [];
        return $this;
    }
}
