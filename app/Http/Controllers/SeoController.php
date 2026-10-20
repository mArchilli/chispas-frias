<?php

namespace App\Http\Controllers;

use App\Models\Combo;
use App\Models\Product;
use Illuminate\Http\Response;

/**
 * robots.txt y sitemap.xml generados con la URL canónica de la tienda (APP_URL → config('app.url')),
 * para que ningún dominio quede escrito en un archivo estático.
 */
class SeoController extends Controller
{
    public function robots(): Response
    {
        $texto = implode("\n", [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /carrito',
            'Disallow: /checkout',
            'Disallow: /perfil',
            'Disallow: /dashboard',
            '',
            'Sitemap: '.self::urlBase().'/sitemap.xml',
            '',
        ]);

        return response($texto, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        $base = self::urlBase();

        $urls = collect(['/', '/productos', '/contacto', '/servicios/chispas'])
            ->map(fn (string $ruta) => ['loc' => $base.$ruta, 'lastmod' => null]);

        $urls = $urls
            ->concat(
                Product::query()->active()->get(['id', 'updated_at'])
                    ->map(fn (Product $producto) => [
                        'loc' => $base.'/productos/'.$producto->getKey(),
                        'lastmod' => $producto->updated_at?->toAtomString(),
                    ])
            )
            ->concat(
                Combo::query()->active()->get(['id', 'updated_at'])
                    ->map(fn (Combo $combo) => [
                        'loc' => $base.'/combos/'.$combo->getKey(),
                        'lastmod' => $combo->updated_at?->toAtomString(),
                    ])
            );

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $url) {
            $xml .= '  <url><loc>'.e($url['loc']).'</loc>'
                .($url['lastmod'] ? '<lastmod>'.e($url['lastmod']).'</lastmod>' : '')
                ."</url>\n";
        }

        $xml .= '</urlset>'."\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private static function urlBase(): string
    {
        return rtrim((string) config('app.url'), '/');
    }
}
