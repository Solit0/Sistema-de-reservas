<?php

declare(strict_types=1);

namespace App\Services;

use finfo;
use InvalidArgumentException;
use RuntimeException;

/**
 * Servicio para gestión segura de carga y almacenamiento de imágenes.
 *
 * [SEGURIDAD]
 * - Valida tipos MIME en el servidor mediante Fileinfo (no confía en la extensión del cliente).
 * - Renombra los archivos con hashes aleatorios para evitar colisiones y Path Traversal.
 * - Limita el tamaño máximo de los archivos subidos.
 * - Garantiza la eliminación física segura de archivos en disco.
 */
class GestorImagenes
{
    private const TIPOS_PERMITIDOS = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    private const MAX_SIZE_BYTES = 3145728; // 3 MB (3 * 1024 * 1024)

    private string $directorioUploads;

    public function __construct(?string $directorioUploads = null)
    {
        $this->directorioUploads = $directorioUploads ?? dirname(__DIR__, 2) . '/public/uploads';

        if (!is_dir($this->directorioUploads) && !mkdir($this->directorioUploads, 0755, true) && !is_dir($this->directorioUploads)) {
            throw new RuntimeException(sprintf('No se pudo crear el directorio de subidas: "%s"', $this->directorioUploads));
        }
    }

    /**
     * Valida un archivo de subida ($_FILES['campo']).
     *
     * @param array<string, mixed>|null $archivo Estructura estándar de $_FILES['campo'].
     *
     * @return string|null Mensaje de error si la validación falla, o null si es válido (o si no se envió archivo).
     */
    public function validar(?array $archivo): ?string
    {
        if ($archivo === null || !isset($archivo['error']) || $archivo['error'] === UPLOAD_ERR_NO_FILE) {
            return null; // Imagen opcional
        }

        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            return sprintf('Error en la subida del archivo (código: %d).', (int) $archivo['error']);
        }

        if (($archivo['size'] ?? 0) > self::MAX_SIZE_BYTES) {
            return sprintf('El archivo supera el tamaño máximo permitido de %d MB.', self::MAX_SIZE_BYTES / (1024 * 1024));
        }

        $tmpPath = (string) ($archivo['tmp_name'] ?? '');
        if ($tmpPath === '' || !file_exists($tmpPath)) {
            return 'El archivo temporal no se encuentra disponible.';
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath);

        if (!is_string($mime) || !array_key_exists($mime, self::TIPOS_PERMITIDOS)) {
            return sprintf(
                'Formato de imagen no permitido (%s). Solo se aceptan formatos JPEG, PNG y WEBP.',
                is_string($mime) ? $mime : 'desconocido'
            );
        }

        return null;
    }

    /**
     * Procesa y almacena la imagen subida en el directorio de uploads.
     *
     * @param array<string, mixed>|null $archivo Estructura de $_FILES['campo'].
     *
     * @return string|null Nombre del archivo generado (ej. 'a1b2c3d4e5f6.webp') o null si no se envió archivo.
     *
     * @throws InvalidArgumentException Si el archivo no supera las validaciones de seguridad.
     * @throws RuntimeException Si falla el almacenamiento en disco.
     */
    public function subir(?array $archivo): ?string
    {
        $error = $this->validar($archivo);
        if ($error !== null) {
            throw new InvalidArgumentException($error);
        }

        if ($archivo === null || !isset($archivo['tmp_name']) || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $tmpPath = (string) $archivo['tmp_name'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmpPath);
        $extension = self::TIPOS_PERMITIDOS[$mime] ?? 'jpg';

        $nombreFinal = bin2hex(random_bytes(16)) . '.' . $extension;
        $destino = rtrim($this->directorioUploads, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $nombreFinal;

        // Soporta tanto subidas HTTP reales (is_uploaded_file) como tests locales
        $guardado = is_uploaded_file($tmpPath)
            ? move_uploaded_file($tmpPath, $destino)
            : copy($tmpPath, $destino);

        if (!$guardado) {
            throw new RuntimeException('Error al mover la imagen subida al directorio de almacenamiento público.');
        }

        return $nombreFinal;
    }

    /**
     * Procesa la subida obligatoria de un archivo, lanzando excepción si no se envió o es inválido.
     *
     * @param array<string, mixed> $archivo
     *
     * @return string Nombre del archivo guardado.
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function procesarSubida(array $archivo): string
    {
        $error = $archivo['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException('No se seleccionó ningún archivo para subir.');
        }

        $subido = $this->subir($archivo);
        if ($subido === null) {
            throw new InvalidArgumentException('No se pudo procesar la subida del archivo.');
        }

        return $subido;
    }

    /**
     * Elimina físicamente una imagen del directorio de uploads.
     *
     * @param string|null $nombreArchivo
     *
     * @return bool True si el archivo existía y fue eliminado, false en caso contrario.
     */
    public function eliminarImagen(?string $nombreArchivo): bool
    {
        if ($nombreArchivo === null || trim($nombreArchivo) === '') {
            return false;
        }

        $nombreLimpio = basename($nombreArchivo);
        if ($nombreLimpio === '' || $nombreLimpio === '.' || $nombreLimpio === '..') {
            return false;
        }

        $rutaCompleta = rtrim($this->directorioUploads, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $nombreLimpio;

        if (file_exists($rutaCompleta) && is_file($rutaCompleta)) {
            return unlink($rutaCompleta);
        }

        return false;
    }

    /**
     * Alias de eliminación segura.
     *
     * @param string|null $nombreArchivo
     *
     * @return bool
     */
    public function eliminar(?string $nombreArchivo): bool
    {
        if ($nombreArchivo === null || trim($nombreArchivo) === '') {
            return true;
        }

        $nombreLimpio = basename($nombreArchivo);
        $rutaCompleta = rtrim($this->directorioUploads, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $nombreLimpio;

        if (file_exists($rutaCompleta) && is_file($rutaCompleta)) {
            return unlink($rutaCompleta);
        }

        return true;
    }

    public function getDirectorioUploads(): string
    {
        return $this->directorioUploads;
    }

    public function getDirectorioDestino(): string
    {
        return $this->directorioUploads;
    }
}
