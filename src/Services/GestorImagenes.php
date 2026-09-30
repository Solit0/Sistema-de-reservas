<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

/**
 * Gestor de subida y almacenamiento seguro de archivos multimedia.
 *
 * [SEGURIDAD]
 * - Inspección binaria estricta de Magic Bytes mediante finfo_file (no confía en Content-Type del cliente).
 * - Generación de nombres criptográficamente aleatorios con random_bytes() para mitigar Path Traversal y sobreescrituras.
 * - Restricción estricta de formatos a solo imágenes web permitidas (PNG, JPEG, WEBP).
 */
final class GestorImagenes
{
    private const TIPOS_PERMITIDOS = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    private const MAX_SIZE_BYTES = 3 * 1024 * 1024; // 3 Megabytes

    private string $directorioUploads;

    public function __construct(?string $directorioUploads = null)
    {
        $this->directorioUploads = $directorioUploads ?? dirname(__DIR__, 2) . '/public/uploads';

        if (!is_dir($this->directorioUploads) && !mkdir($this->directorioUploads, 0755, true) && !is_dir($this->directorioUploads)) {
            throw new RuntimeException(sprintf('No se pudo crear el directorio de subidas: "%s"', $this->directorioUploads));
        }
    }

    /**
     * [SEGURIDAD] Valida y almacena un archivo subido vía $_FILES.
     *
     * @param array<string, mixed> $archivo Estructura de $_FILES['campo']
     * @return string Nombre del archivo generado almacenado en disco.
     * @throws InvalidArgumentException Si el archivo no es válido, supera el tamaño o no es una imagen permitida.
     * @throws RuntimeException Si ocurre un error al mover el archivo a su destino final.
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

        // [SEGURIDAD] Inspección binaria de Magic Bytes usando la extensión nativa fileinfo
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

        // [SEGURIDAD] Generación de nombre criptográficamente impredecible (32 caracteres hexadecimales)
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
}
