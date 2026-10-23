<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Recibe los avisos de la Content-Security-Policy (`report-uri`) mientras la política está en modo «solo reportar» y los deja en
 * storage/logs/seguridad.log, para decidir si se puede pasar a `enforce` sin romper nada.
 *
 * Es público (lo llama el navegador del visitante) y no lleva CSRF, así que es lo más chico posible: solo POST, con límite por IP
 * (config/seguridad.php → limites.csp_report_por_minuto), cuerpo de hasta 4 KB y se guarda únicamente QUÉ directiva se violó y
 * contra qué origen, sin la query de las URLs (puede traer datos) ni el contenido del cuerpo.
 */
class CspReportController extends Controller
{
    private const MAXIMO_BYTES = 4096;

    public function __invoke(Request $request): Response
    {
        $cuerpo = $request->getContent();

        if (strlen($cuerpo) <= self::MAXIMO_BYTES) {
            $datos = json_decode($cuerpo, true);
            $reporte = is_array($datos) ? ($datos['csp-report'] ?? null) : null;

            if (is_array($reporte)) {
                Log::channel('seguridad')->warning('CSP: violación reportada', [
                    'directiva' => $this->texto($reporte['effective-directive'] ?? $reporte['violated-directive'] ?? null, 60),
                    'bloqueado' => $this->origen($reporte['blocked-uri'] ?? null),
                    'pagina' => $this->ruta($reporte['document-uri'] ?? null),
                ]);
            }
        }

        return response()->noContent();
    }

    private function texto(mixed $valor, int $largo): ?string
    {
        return is_string($valor) ? mb_substr(preg_replace('/[^\x20-\x7e]/', '', $valor) ?? '', 0, $largo) : null;
    }

    /** `https://sitio.com/x?y=1` → `https://sitio.com`; `inline`, `eval`, `data` se dejan tal cual. */
    private function origen(mixed $uri): ?string
    {
        if (! is_string($uri) || $uri === '') {
            return null;
        }

        $partes = parse_url($uri);

        if (! is_array($partes) || ! isset($partes['host'])) {
            return $this->texto($uri, 20);
        }

        return $this->texto(($partes['scheme'] ?? '').'://'.$partes['host'], 120);
    }

    /** Solo la ruta de la página, sin query ni fragmento. */
    private function ruta(mixed $uri): ?string
    {
        return is_string($uri) ? $this->texto(parse_url($uri, PHP_URL_PATH) ?: '/', 120) : null;
    }
}
