<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Mueve un archivo subido a una carpeta dentro de public/ probando tres
 * estrategias en cascada:
 *   1. move() nativo de Laravel (lo normal),
 *   2. copy() de PHP,
 *   3. file_get_contents()/file_put_contents() leyendo el temporal a mano.
 *
 * Es el mismo mecanismo que usa el alta/edición de imágenes de producto
 * (ver ProductController): en algunos hostings compartidos el move() falla por
 * permisos del tmp o por open_basedir, y hay que degradar a copy manual. Vive
 * acá para que el alta de PDFs de documentos (DocumentController) y cualquier
 * otro upload a public/ guarde con el mismo criterio.
 */
trait StoresPublicUploads
{
    /**
     * Deja $file en $destinoDir/$nombreArchivo (rutas del sistema de archivos,
     * $destinoDir normalmente public_path(...)). Devuelve true si alguna de las
     * estrategias lo logró, false si todas fallaron; el llamador decide si
     * saltea ese archivo o aborta la operación.
     */
    protected function moverArchivoSubidoAPublic(UploadedFile $file, string $destinoDir, string $nombreArchivo): bool
    {
        $destinoDir = rtrim($destinoDir, '/\\');

        if (! is_dir($destinoDir)) {
            mkdir($destinoDir, 0755, true);
        }

        $tempPath = $file->getRealPath();
        $targetPath = $destinoDir . DIRECTORY_SEPARATOR . $nombreArchivo;

        try {
            // Estrategia 1: move() estándar de Laravel.
            if (method_exists($file, 'move')) {
                $file->move($destinoDir, $nombreArchivo);

                return true;
            }

            // Estrategia 2: copy() de PHP como fallback.
            if ($tempPath && copy($tempPath, $targetPath)) {
                @unlink($tempPath);

                return true;
            }

            Log::error("StoresPublicUploads: no se pudo copiar el archivo a {$targetPath}");

            return false;
        } catch (\Throwable $e) {
            // Estrategia 3: leer el temporal y escribirlo a mano.
            try {
                Log::warning('StoresPublicUploads: move() falló, probando método alternativo: ' . $e->getMessage());

                $contenido = $tempPath ? file_get_contents($tempPath) : false;

                if ($contenido !== false && file_put_contents($targetPath, $contenido) !== false) {
                    @unlink($tempPath);

                    return true;
                }

                Log::error('StoresPublicUploads: el método alternativo tampoco pudo guardar el archivo');

                return false;
            } catch (\Throwable $fallback) {
                Log::error('StoresPublicUploads: todas las estrategias de guardado fallaron: ' . $fallback->getMessage());

                return false;
            }
        }
    }

    /**
     * Borra un archivo previamente guardado con moverArchivoSubidoAPublic().
     * $rutaRelativa es lo que quedó en base (sólo el nombre del archivo); se
     * ignora en silencio si es null o si el archivo ya no existe.
     */
    protected function borrarArchivoPublic(string $destinoDir, ?string $rutaRelativa): void
    {
        if (! $rutaRelativa) {
            return;
        }

        $path = rtrim($destinoDir, '/\\') . DIRECTORY_SEPARATOR . basename($rutaRelativa);

        if (is_file($path)) {
            @unlink($path);
        }
    }
}
