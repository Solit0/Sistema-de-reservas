<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

final class GestorImagenes
{
    private const TIPOS_PERMITIDOS = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    private const MAX_SIZE_BYTES = 3 * 1024 * 1024; // 3 MB

    private string $directorioUploads;

    public function __construct(?string $directorioUploads = null)
    {
        $this->directorioUploads = $directorioUploads ?? dirname(__DIR__, 2) . '/public/uploads';

        if (!is_dir($this->directorioUploads) && !mkdir($this->directorioUploads, 0755, true) && !is_dir($this->directorioUploads)) {
            throw new RuntimeException(sprintf('No se pudo crear el directorio de subidas: "%s"', $this->directorioUploads));
        }
    }

    /**
     * @param array<string, mixed> $archivo
     */
    public function procesarSubida(array $archivo): string
    {
        $error = $archivo['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException('No se seleccionó ningún archivo para subir.');
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(sprintf('Error en la subida del archivo (código: %d).', (int)$error));
        }

        $size = (int)($archivo['size'] ?? 0);
        if ($size > self::MAX_SIZE_BYTES) {
            throw new InvalidArgumentException(sprintf(
                'El archivo supera el tamaño máximo permitido de %d MB.',
                self::MAX_SIZE_BYTES / (1024 * 1024)
            ));
        }

        $tmpPath = (string)($archivo['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new InvalidArgumentException('El archivo subido no es válido o no proviene de una petición HTTP POST.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            throw new RuntimeException('No se pudo inicializar la extensión fileinfo en el servidor.');
        }

        $mime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        if (!is_string($mime) || !isset(self::TIPOS_PERMITIDOS[$mime])) {
            throw new InvalidArgumentException(sprintf(
                'Formato de imagen no permitido (%s). Solo se aceptan formatos JPEG, PNG y WEBP.',
                is_string($mime) ? $mime : 'desconocido'
            ));
        }

        $extension = self::TIPOS_PERMITIDOS[$mime];
        $nombreFinal = bin2hex(random_bytes(16)) . '.' . $extension;
        $destino = rtrim($this->directorioUploads, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $nombreFinal;

        if (!move_uploaded_file($tmpPath, $destino)) {
            throw new RuntimeException('Error al mover la imagen subida al directorio de almacenamiento público.');
        }

        return $nombreFinal;
    }

    public function getDirectorioUploads(): string
    {
        return $this->directorioUploads;
    }

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
}
