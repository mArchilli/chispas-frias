<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Las carpetas de public/ donde se guardan archivos que sube el admin (imágenes y videos de productos y combos, PDFs de Documentos).
 *
 * Dos defensas, una por si falla la otra:
 *  1. el archivo se guarda con un nombre aleatorio y una extensión que sale del CONTENIDO (`extension()`), nunca del nombre que mandó
 *     el navegador: aunque alguien suba un script con un nombre «foto.jpg» o «x.php», en disco nunca hay un .php;
 *  2. un `.htaccess` en la carpeta (`proteger()`) que solo deja servir la lista blanca de extensiones y desactiva el motor de PHP.
 *     En el hosting las carpetas son externas al repo (PRODUCT_IMAGES_PATH apunta a public_html), por eso el archivo se escribe
 *     al crear la carpeta y se repone si falta, en vez de depender del deploy.
 */
final class CarpetaDeSubidas
{
    /** Tipo de contenido detectado → extensión con la que se guarda. */
    private const EXTENSION_POR_TIPO = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
        'video/x-msvideo' => 'avi',
        'video/avi' => 'avi',
        'video/x-ms-wmv' => 'wmv',
        'video/x-flv' => 'flv',
        'video/webm' => 'webm',
        'application/pdf' => 'pdf',
    ];

    public const HTACCESS = <<<'HTACCESS'
# Carpeta de archivos subidos desde el panel. Generado por App\Support\CarpetaDeSubidas: NO se ejecuta código acá.
Options -ExecCGI -Indexes

<IfModule mod_php.c>
    php_flag engine off
</IfModule>
<IfModule mod_php7.c>
    php_flag engine off
</IfModule>
<IfModule mod_php8.c>
    php_flag engine off
</IfModule>
<IfModule mod_mime.c>
    RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phps .pht .phar
    RemoveType .php .phtml .php3 .php4 .php5 .php7 .php8 .phps .pht .phar
</IfModule>

# Lista blanca: solo se sirven imágenes, videos y PDFs con nombre «simple».
<IfModule mod_authz_core.c>
    Require all denied
    <FilesMatch "(?i)^[a-z0-9_-]+\.(jpe?g|png|gif|webp|mp4|mov|avi|wmv|flv|webm|pdf)$">
        Require all granted
    </FilesMatch>
</IfModule>
<IfModule !mod_authz_core.c>
    Order allow,deny
    <FilesMatch "(?i)^[a-z0-9_-]+\.(jpe?g|png|gif|webp|mp4|mov|avi|wmv|flv|webm|pdf)$">
        Allow from all
    </FilesMatch>
</IfModule>

<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Robots-Tag "noindex"
</IfModule>
HTACCESS;

    /** La extensión con la que guardar el archivo según su contenido real, o null si el tipo no está permitido. */
    public static function extension(UploadedFile $file): ?string
    {
        try {
            $tipo = $file->getMimeType();
        } catch (\Throwable) {
            return null;
        }

        return self::EXTENSION_POR_TIPO[strtolower((string) $tipo)] ?? null;
    }

    /** Crea la carpeta si falta y deja el `.htaccess` de arriba. Nunca lanza: si no puede escribirlo, el guardado sigue. */
    public static function proteger(string $dir): void
    {
        $dir = rtrim($dir, '/\\');

        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $archivo = $dir.DIRECTORY_SEPARATOR.'.htaccess';

        if (is_dir($dir) && ! is_file($archivo)) {
            @file_put_contents($archivo, self::HTACCESS."\n");
        }
    }
}
