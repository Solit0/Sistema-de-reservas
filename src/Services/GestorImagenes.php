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
 * - Garantiza la eliminación física de archivos antiguos.
 */
class GestorImagenes
{
    private const TIPOS_PERMITIDOS = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    private const TAMANO_MAXIMO_BYTES = 2097152; // 2 MB (2 * 1024 * 1024)

    private string $directorioDestino;

    public function __construct(?string $directorioDestino = null)
    {
        $this->directorioDestino = $directorioDestino ?? dirname(__DIR__, 2) . '/public/uploads';

        if (!is_dir($this->directorioDestino)) {
            mkdir($this->directorioDestino, 0755, true);
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
            return 'Ocurrió un error al subir el archivo (código ' . $archivo['error'] . ').';
        }

        if (($archivo['size'] ?? 0) > self::TAMANO_MAXIMO_BYTES) {
            return 'La imagen no puede exceder los 2 MB de tamaño.';
        }

        $tmpPath = (string) ($archivo['tmp_name'] ?? '');
        if ($tmpPath === '' || !file_exists($tmpPath)) {
            return 'El archivo temporal no se encuentra disponible.';
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath);

        if (!is_string($mime) || !array_key_exists($mime, self::TIPOS_PERMITIDOS)) {
            return 'Formato de imagen no permitido. Solo se aceptan imágenes JPG, PNG y WEBP.';
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

        if ($archivo === null || !isset($archivo['tmp_name']) || $archivo['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $tmpPath = (string) $archivo['tmp_name'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmpPath);
        $extension = self::TIPOS_PERMITIDOS[$mime] ?? 'jpg';

        // Generar nombre aleatorio de 32 caracteres hexadecimales + extensión segura
        $nombreSeguro = bin2hex(random_bytes(16)) . '.' . $extension;
        $rutaDestino = $this->directorioDestino . '/' . $nombreSeguro;

        // Soporta tanto subidas HTTP reales (is_uploaded_file) como pruebas automatizadas
        $guardado = is_uploaded_file($tmpPath)
            ? move_uploaded_file($tmpPath, $rutaDestino)
            : copy($tmpPath, $rutaDestino);

        if (!$guardado) {
            throw new RuntimeException('No se pudo guardar la imagen en el directorio de destino.');
        }

        return $nombreSeguro;
    }

    /**
     * Elimina físicamente una imagen anterior del disco.
     *
     * @param string|null $nombreArchivo Nombre del archivo en public/uploads/.
     *
     * @return bool True si se eliminó o no existía, false si falló unlink.
     */
    public function eliminar(?string $nombreArchivo): bool
    {
        if ($nombreArchivo === null || trim($nombreArchivo) === '') {
            return true;
        }

        // Prevenir Path Traversal sanitizando a nombre base
        $nombreLimpio = basename($nombreArchivo);
        $rutaCompleta = $this->directorioDestino . '/' . $nombreLimpio;

        if (file_exists($rutaCompleta) && is_file($rutaCompleta)) {
            return unlink($rutaCompleta);
        }

        return true;
    }

    public function getDirectorioDestino(): string
    {
        return $this->directorioDestino;
    }
}
