<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\User;
use App\Rukoz\CatalogoDeChispasFrias;
use App\Rukoz\CheckoutCentral;
use App\Rukoz\EspejoStock;
use App\Rukoz\EstadoDesdeCentral;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Rukoz\CoreClient\Compensaciones\Compensacion;
use Rukoz\CoreClient\Contratos\ProveedorCatalogo;
use Rukoz\CoreClient\Eventos\CompensacionResuelta;
use Rukoz\CoreClient\Eventos\PedidoActualizado;
use Rukoz\CoreClient\Eventos\StockActualizado;

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

        // En producción con APP_URL https, todas las URLs que genera la app (assets, rutas, links de mails) salen con https aunque el
        // servidor detrás del hosting no informe el esquema.
        if ($this->app->isProduction() && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        $this->definirLimitesDeRequests();

        // El stock del Central se espeja en las columnas de la web solo con INTEGRACION_STOCK (10.2). Se registra a mano
        // y no en app/Listeners para que Laravel no lo descubra solo y escuche con el interruptor apagado.
        if (config('integracion.stock')) {
            Event::listen(StockActualizado::class, [EspejoStock::class, 'alActualizarse']);
        }

        // 10.4 — Los estados que informa el Central (confirmado, empaquetado, despachado, retirado, anulado, reserva liberada, pago y
        // envío actualizados) se reflejan en el pedido de la web solo con INTEGRACION_CONFIRMACION. `PedidoActualizado` es el evento
        // genérico del paquete: se dispara con todo cambio de estado de un pedido, ya con la regla de `version` aplicada. Se registra
        // a mano (no en app/Listeners) para que Laravel no lo descubra solo y escuche con el interruptor apagado.
        if (config('integracion.confirmacion')) {
            Event::listen(PedidoActualizado::class, [EstadoDesdeCentral::class, 'alActualizarse']);
        }

        // 10.3 — Combinación inválida (checkout sin stock o sin integración): el checkout no usa el Central y queda el aviso.
        CheckoutCentral::advertir();

        // 10.3 — Cuando el paquete logra cancelar en el Central una reserva que había quedado en compensaciones, el pedido
        // local (ya cancelado) pasa de `cancelacion_pendiente` a `cancelado`. Corre dentro de la transacción del paquete: solo
        // una escritura local. Se registra siempre: el evento solo existe con la integración encendida y la escritura solo
        // toca pedidos que están esperando.
        Event::listen(CompensacionResuelta::class, function (CompensacionResuelta $evento) {
            if ($evento->tipo !== Compensacion::CANCELAR) {
                return;
            }

            Order::query()
                ->where('rukoz_ref', $evento->ref)
                ->where('rukoz_estado', Order::RESERVA_CANCELACION_PENDIENTE)
                ->update(['rukoz_estado' => Order::RESERVA_CANCELADA]);
        });

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

        // Autenticación: por IP, además del límite por email+IP que ya tiene LoginRequest. El mensaje es el 429 estándar.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(config('seguridad.limites.login_por_minuto'))->by($request->ip()));
        RateLimiter::for('registro', fn (Request $request) => Limit::perMinute(config('seguridad.limites.registro_por_minuto'))->by($request->ip()));
        RateLimiter::for('recuperar-password', fn (Request $request) => Limit::perMinute(config('seguridad.limites.recuperar_password_por_minuto'))->by($request->ip()));
        RateLimiter::for('csp-report', fn (Request $request) => Limit::perMinute(config('seguridad.limites.csp_report_por_minuto'))->by($request->ip()));

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
