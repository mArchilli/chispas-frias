<?php

namespace App\Providers;

use App\Models\User;
use App\Rukoz\CatalogoDeChispasFrias;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Rukoz\CoreClient\Contratos\ProveedorCatalogo;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Sin el interruptor del catálogo no se registra el proveedor: el paquete tampoco programa el sync diario.
        if (config('integracion.catalogo')) {
            $this->app->bind(ProveedorCatalogo::class, CatalogoDeChispasFrias::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        $this->definirLimitesDeRequests();

        // Controla el acceso de entrada al panel /admin (admin y vendedor pasan, cliente no).
        Gate::define('acceder-panel-admin', fn (User $user) => $user->puedeAccederAlPanel());

        // Controla el acceso a la vista/edición de configuración general del sitio.
        Gate::define('gestionar-configuracion', fn (User $user) => $user->isAdmin());

        // Controla el ABM de cuentas de vendedores.
        Gate::define('gestionar-vendedores', fn (User $user) => $user->isAdmin());

        // Controla el borrado de catálogo (productos, categorías, ofertas, códigos de descuento).
        Gate::define('borrar-catalogo', fn (User $user) => $user->isAdmin());

        // Controla el ABM de productos (alta, edición, listado). El vendedor solo
        // ve precios (Gate implícito vía 'acceder-panel-admin' + admin.prices.*).
        Gate::define('gestionar-productos', fn (User $user) => $user->isAdmin());

        // Controla el ABM de documentos (manuales/instructivos para vendedores).
        // El vendedor sólo ve el listado de activos, vía 'acceder-panel-admin'.
        Gate::define('gestionar-documentos', fn (User $user) => $user->isAdmin());
    }

    /**
     * Límites de requests de los endpoints públicos que escriben o permiten adivinar datos:
     * confirmar el pedido (crea un pedido y descuenta stock) y probar códigos de descuento.
     * Cuántas peticiones por minuto, por IP, sale de config/tienda.php (se lee en cada request,
     * así se puede ajustar sin tocar código). Al superarlo el cliente recibe un mensaje amable
     * (JSON para el front, redirect con aviso para una navegación normal) en vez de un 429 pelado.
     */
    private function definirLimitesDeRequests(): void
    {
        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(config('tienda.limites.checkout_por_minuto'))
            ->by($request->ip())
            ->response(fn (Request $request, array $headers) => $this->respuestaLimiteSuperado($request, $headers)));

        RateLimiter::for('validar-cupon', fn (Request $request) => Limit::perMinute(config('tienda.limites.validar_cupon_por_minuto'))
            ->by($request->ip())
            ->response(fn (Request $request, array $headers) => $this->respuestaLimiteSuperado($request, $headers)));
    }

    /**
     * @param  array<string, mixed>  $headers  Retry-After, X-RateLimit-* que arma el middleware throttle.
     */
    private function respuestaLimiteSuperado(Request $request, array $headers)
    {
        $mensaje = config('tienda.limites.mensaje');

        // El checkout manda JSON con fetch (sin cabecera Accept), por eso se mira también el cuerpo.
        if ($request->expectsJson() || $request->isJson()) {
            return response()->json([
                'success' => false,
                'message' => $mensaje,
                'retry_after' => isset($headers['Retry-After']) ? (int) $headers['Retry-After'] : null,
            ], 429, $headers);
        }

        return back()->with('error', $mensaje);
    }
}
