<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

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
}
