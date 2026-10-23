<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Limpia el HTML que escribe el admin en el editor (Quill) con una LISTA BLANCA de etiquetas y atributos. Se aplica al guardar
 * (Product::description) y otra vez al mostrar, porque la ficha pública lo pinta con dangerouslySetInnerHTML.
 *
 * Qué conserva lo que genera Quill con la barra de `resources/js/utils/quillConfig.js` (títulos, negrita/cursiva/subrayado/tachado,
 * listas, color y fondo, links) y nada más:
 *  - etiquetas: p, br, h1-h3, strong/b, em/i, u, s/strike, ul, ol, li, a, span, blockquote;
 *  - atributos: `href` (solo http, https, mailto y tel) en `a`, con `rel="noopener noreferrer"`; `style` solo con `color` y
 *    `background-color` de valores simples (hex, rgb(), nombre); `class` solo `ql-*`; `data-list` de las listas de Quill 2.
 *  - todo lo demás se descarta: `script`, `style`, `iframe`, `img`, `svg`, `on*=`, `javascript:`, `data:`, etc. Las etiquetas no
 *    permitidas se desarman pero su texto queda; las de contenido peligroso (script, style…) se eliminan con todo su contenido.
 */
class HtmlSanitizer
{
    private const ETIQUETAS = ['p', 'br', 'h1', 'h2', 'h3', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'ul', 'ol', 'li', 'a', 'span', 'blockquote'];

    /** Se borran con todo lo que tienen adentro. */
    private const ELIMINAR_CON_CONTENIDO = ['script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template', 'svg', 'math', 'form', 'textarea', 'select', 'button', 'link', 'meta', 'base', 'title', 'head'];

    private const ESQUEMAS_LINK = ['http', 'https', 'mailto', 'tel'];

    public static function limpiar(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html;
        }

        // Sin etiquetas ni entidades que interpretar no hay nada que limpiar.
        if (! str_contains($html, '<') && ! str_contains($html, '&')) {
            return $html;
        }

        $anterior = libxml_use_internal_errors(true);

        $documento = new DOMDocument('1.0', 'UTF-8');
        // El prefijo <?xml fuerza UTF-8; el contenedor evita que libxml agregue <html><body> alrededor.
        $documento->loadHTML('<?xml encoding="UTF-8"><div id="__raiz">'.$html.'</div>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);

        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        $raiz = $documento->getElementById('__raiz');

        if (! $raiz instanceof DOMElement) {
            return trim(strip_tags($html));
        }

        self::limpiarHijos($raiz);

        $salida = '';
        foreach ($raiz->childNodes as $hijo) {
            $salida .= $documento->saveHTML($hijo);
        }

        return $salida;
    }

    private static function limpiarHijos(DOMNode $padre): void
    {
        // Se copia la lista: se modifica el árbol mientras se recorre.
        foreach (iterator_to_array($padre->childNodes) as $nodo) {
            if (! $nodo instanceof DOMElement) {
                // Texto: se deja; comentarios, instrucciones de proceso y CDATA: se sacan.
                if ($nodo->nodeType !== XML_TEXT_NODE) {
                    $padre->removeChild($nodo);
                }

                continue;
            }

            $etiqueta = strtolower($nodo->nodeName);

            if (in_array($etiqueta, self::ELIMINAR_CON_CONTENIDO, true)) {
                $padre->removeChild($nodo);

                continue;
            }

            self::limpiarHijos($nodo);

            if (! in_array($etiqueta, self::ETIQUETAS, true)) {
                // Se desarma la etiqueta pero se conserva lo de adentro (ya limpio).
                while ($nodo->firstChild) {
                    $padre->insertBefore($nodo->firstChild, $nodo);
                }
                $padre->removeChild($nodo);

                continue;
            }

            self::limpiarAtributos($nodo, $etiqueta);
        }
    }

    private static function limpiarAtributos(DOMElement $elemento, string $etiqueta): void
    {
        foreach (iterator_to_array($elemento->attributes) as $atributo) {
            $nombre = strtolower($atributo->nodeName);
            $valor = $atributo->nodeValue ?? '';

            $permitido = match (true) {
                $etiqueta === 'a' && $nombre === 'href' => self::linkSeguro($valor),
                $nombre === 'style' => self::estiloSeguro($valor) !== null,
                $nombre === 'class' => self::claseSegura($valor) !== null,
                $etiqueta === 'li' && $nombre === 'data-list' => in_array($valor, ['ordered', 'bullet', 'checked', 'unchecked'], true),
                default => false,
            };

            if (! $permitido) {
                $elemento->removeAttribute($atributo->nodeName);

                continue;
            }

            if ($nombre === 'style') {
                $elemento->setAttribute('style', self::estiloSeguro($valor));
            } elseif ($nombre === 'class') {
                $elemento->setAttribute('class', self::claseSegura($valor));
            }
        }

        if ($etiqueta === 'a') {
            if ($elemento->hasAttribute('href')) {
                $elemento->setAttribute('rel', 'noopener noreferrer');
                $elemento->setAttribute('target', '_blank');
            } else {
                $elemento->removeAttribute('rel');
                $elemento->removeAttribute('target');
            }
        }
    }

    private static function linkSeguro(string $href): bool
    {
        // Los caracteres de control y los espacios que algunos navegadores ignoran dentro del esquema ("java\tscript:") se sacan antes de mirar.
        $limpio = preg_replace('/[\x00-\x20\x7f]+/', '', html_entity_decode($href, ENT_QUOTES | ENT_HTML5)) ?? '';

        if ($limpio === '') {
            return false;
        }

        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $limpio, $m)) {
            return in_array(strtolower($m[1]), self::ESQUEMAS_LINK, true);
        }

        // Sin esquema: relativo (/ruta, #ancla, ruta). "//host" también es válido y va a http(s).
        return true;
    }

    /** @return string|null el style reducido a las propiedades permitidas, o null si no queda ninguna. */
    private static function estiloSeguro(string $style): ?string
    {
        $validas = [];

        foreach (explode(';', $style) as $declaracion) {
            if (! str_contains($declaracion, ':')) {
                continue;
            }

            [$propiedad, $valor] = array_map('trim', explode(':', $declaracion, 2));
            $propiedad = strtolower($propiedad);

            if (! in_array($propiedad, ['color', 'background-color'], true)) {
                continue;
            }

            // hex, rgb()/rgba() con números, o una palabra (red, transparent). Nada de url(), expression(), var(), comillas ni barras.
            $esColor = preg_match('/^(#[0-9a-f]{3,8}|rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*(0|1|0?\.\d+)\s*)?\)|[a-z]{3,20})$/i', $valor) === 1;

            if ($esColor) {
                $validas[] = $propiedad.': '.$valor;
            }
        }

        return $validas === [] ? null : implode('; ', $validas).';';
    }

    /** @return string|null las clases `ql-*` (alineación, sangría, tamaño de Quill), o null si no hay ninguna. */
    private static function claseSegura(string $clases): ?string
    {
        $validas = array_filter(
            preg_split('/\s+/', trim($clases)) ?: [],
            fn (string $clase) => preg_match('/^ql-[a-z0-9\-]{1,40}$/', $clase) === 1,
        );

        return $validas === [] ? null : implode(' ', $validas);
    }
}
