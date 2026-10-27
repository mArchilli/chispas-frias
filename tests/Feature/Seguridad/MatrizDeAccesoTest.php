<?php

namespace Tests\Feature\Seguridad;

use App\Enums\RolUsuario;
use App\Models\Category;
use App\Models\Combo;
use App\Models\DiscountCode;
use App\Models\Document;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RutaLaravel;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * La matriz rol × ruta del panel (S.2 · A.1), en dos capas:
 *
 *  1. ESTÁTICA: cada ruta `admin.*` declara `auth` + `can:acceder-panel-admin`, y la que es solo de admin lleva su Gate. La lista de
 *     abajo es la fuente de verdad: una ruta nueva que no esté clasificada hace fallar el test (hay que decidir quién la puede usar).
 *  2. DINÁMICA: con usuarios reales, el cliente no entra a nada de /admin y el vendedor no hace lo que es solo de admin.
 */
class MatrizDeAccesoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Rutas `admin.*` que pueden usar admin Y vendedor (todo lo demás exige un Gate de admin). Prefijos de nombre.
     */
    private const COMPARTIDAS = [
        'admin.dashboard',
        'admin.prices.index',
        'admin.orders.index',
        'admin.orders.show',
        'admin.orders.update-status',
        'admin.orders.update-cliente',
        'admin.orders.confirmation',
        'admin.orders.confirm',
        'admin.documents.index',
        // categorías, ofertas y códigos de descuento: el vendedor opera, pero borrar es de admin (Gate borrar-catalogo)
        'admin.categories.',
        'admin.offers.',
        'admin.discount-codes.',
    ];

    /** Rutas de admin que exigen un Gate propio, además de entrar al panel. */
    private const GATES_DE_ADMIN = [
        'gestionar-productos' => ['admin.products.', 'admin.addons.', 'admin.combos.'],
        'gestionar-configuracion' => ['admin.settings.', 'admin.card-payment-plans.'],
        'gestionar-vendedores' => ['admin.sellers.'],
        'gestionar-documentos' => ['admin.documents.create', 'admin.documents.store', 'admin.documents.edit', 'admin.documents.update', 'admin.documents.destroy', 'admin.documents.toggle-status'],
    ];

    /** @return list<RutaLaravel> */
    private function rutasDelPanel(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RutaLaravel $r) => str_starts_with($r->uri(), 'admin'))
            ->values()
            ->all();
    }

    private function empiezaCon(string $nombre, array $prefijos): bool
    {
        foreach ($prefijos as $p) {
            if (str_ends_with($p, '.') ? str_starts_with($nombre, $p) : $nombre === $p) {
                return true;
            }
        }

        return false;
    }

    public function test_toda_ruta_del_panel_exige_login_y_acceso_al_panel(): void
    {
        $this->assertNotEmpty($this->rutasDelPanel());

        foreach ($this->rutasDelPanel() as $ruta) {
            $middleware = $ruta->gatherMiddleware();

            $this->assertContains('auth', $middleware, "{$ruta->uri()} no exige login");
            $this->assertContains('can:acceder-panel-admin', $middleware, "{$ruta->uri()} no exige acceso al panel");
        }
    }

    public function test_toda_ruta_del_panel_esta_clasificada_en_la_matriz(): void
    {
        $clasificadas = array_merge(self::COMPARTIDAS, ...array_values(self::GATES_DE_ADMIN));

        foreach ($this->rutasDelPanel() as $ruta) {
            $nombre = (string) $ruta->getName();

            // El redirect GET /admin no tiene nombre: igual exige login + panel (test de arriba).
            if ($nombre === '') {
                continue;
            }

            $this->assertTrue(
                $this->empiezaCon($nombre, $clasificadas),
                "La ruta «{$nombre}» no está en la matriz de MatrizDeAccesoTest: decidí si es compartida o solo de admin."
            );
        }
    }

    public function test_las_rutas_solo_de_admin_llevan_su_gate(): void
    {
        foreach ($this->rutasDelPanel() as $ruta) {
            $nombre = (string) $ruta->getName();

            foreach (self::GATES_DE_ADMIN as $gate => $prefijos) {
                if ($this->empiezaCon($nombre, $prefijos)) {
                    $this->assertContains("can:{$gate}", $ruta->gatherMiddleware(), "{$nombre} debería exigir el Gate {$gate}");
                }
            }
        }
    }

    public function test_todo_borrado_de_catalogo_exige_el_gate_de_admin(): void
    {
        foreach ($this->rutasDelPanel() as $ruta) {
            $nombre = (string) $ruta->getName();

            if (in_array('DELETE', $ruta->methods(), true) && str_ends_with($nombre, '.destroy') && ! str_starts_with($nombre, 'admin.sellers.')) {
                $exigeAdmin = collect($ruta->gatherMiddleware())->contains(fn ($m) => in_array($m, [
                    'can:borrar-catalogo', 'can:gestionar-productos', 'can:gestionar-configuracion', 'can:gestionar-documentos',
                ], true));

                $this->assertTrue($exigeAdmin, "{$nombre} permite borrar a un vendedor");
            }
        }
    }

    public function test_las_rutas_que_modifican_no_son_get(): void
    {
        // Ningún GET del panel puede cambiar datos: los verbos que modifican pasan por CSRF (grupo web).
        foreach ($this->rutasDelPanel() as $ruta) {
            $nombre = (string) $ruta->getName();

            if (preg_match('/\.(store|update|destroy|toggle|toggle-status|toggle-featured|set-primary|update-status|confirm|quick-offer)$/', $nombre)) {
                $this->assertNotContains('GET', $ruta->methods(), "{$nombre} modifica datos pero responde a GET");
            }
        }
    }

    public function test_todas_las_rutas_que_modifican_pasan_por_csrf_salvo_el_webhook_y_el_reporte_de_csp(): void
    {
        foreach (Route::getRoutes()->getRoutes() as $ruta) {
            $modifica = array_intersect($ruta->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== [];

            if (! $modifica) {
                continue;
            }

            $conCsrf = in_array('web', $ruta->gatherMiddleware(), true);

            if (! $conCsrf) {
                $this->assertContains($ruta->uri(), ['csp-report', 'rukoz/webhook'], "{$ruta->uri()} modifica y no pasa por el grupo web (sin CSRF)");
            }
        }

        // Y el reporte de CSP es la única excepción explícita de CSRF en la app (el webhook ni pasa por el grupo web).
        $propiedad = new \ReflectionProperty(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class, 'neverVerify');
        $this->assertSame(['csp-report'], $propiedad->getValue());
    }

    // --- dinámica ------------------------------------------------------------------------------------------------------------

    public function test_un_invitado_es_redirigido_al_login_en_todo_el_panel(): void
    {
        foreach (['admin/dashboard', 'admin/orders', 'admin/products', 'admin/sellers', 'admin/settings', 'admin/documents', 'admin'] as $uri) {
            $this->get("/$uri")->assertRedirect(route('login'));
        }

        $this->post('/admin/sellers', [])->assertRedirect(route('login'));
        $this->patch('/admin/orders/1/status', ['estado' => 'cancelado'])->assertRedirect(route('login'));
    }

    public function test_un_cliente_logueado_no_entra_a_ninguna_ruta_del_panel(): void
    {
        $cliente = User::factory()->create();
        $producto = Product::factory()->create();
        $orden = Order::factory()->create();

        $this->actingAs($cliente);

        foreach (collect($this->rutasDelPanel())->filter(fn ($r) => in_array('GET', $r->methods(), true) && $r->parameterNames() === []) as $ruta) {
            $this->get('/'.$ruta->uri())->assertForbidden();
        }

        $this->get("/admin/orders/{$orden->id}")->assertForbidden();
        $this->patch("/admin/orders/{$orden->id}/status", ['estado' => 'cancelado'])->assertForbidden();
        $this->post("/admin/orders/{$orden->id}/confirm", [])->assertForbidden();
        $this->patch("/admin/orders/{$orden->id}/cliente", ['name' => 'X'])->assertForbidden();
        $this->get("/admin/products/{$producto->id}/edit")->assertForbidden();
        $this->delete("/admin/products/{$producto->id}")->assertForbidden();
        $this->post('/admin/sellers', ['name' => 'X', 'email' => 'x@ejemplo.com'])->assertForbidden();
        $this->patch('/admin/settings', ['free_shipping_threshold' => 1])->assertForbidden();
    }

    public function test_un_vendedor_no_hace_nada_que_sea_solo_de_admin(): void
    {
        $vendedor = User::factory()->vendedor()->create();
        $otroVendedor = User::factory()->vendedor()->create();
        $producto = Product::factory()->create();
        $categoria = Category::factory()->create();
        $combo = Combo::create(['title' => 'C', 'price' => 1, 'is_active' => true]);
        $codigo = DiscountCode::create(['code' => 'X', 'percentage' => 10, 'is_active' => true]);
        $documento = Document::create(['title' => 'D', 'type' => 'link', 'url' => 'https://ejemplo.com', 'is_active' => true]);

        $this->actingAs($vendedor);

        // Solo admin: catálogo, configuración, vendedores, documentos (ABM).
        foreach (['/admin/products', '/admin/products/create', '/admin/addons', '/admin/combos', '/admin/settings', '/admin/card-payment-plans', '/admin/sellers', '/admin/sellers/create', '/admin/documents/create'] as $uri) {
            $this->get($uri)->assertForbidden();
        }

        $this->get("/admin/products/{$producto->id}/edit")->assertForbidden();
        $this->put("/admin/products/{$producto->id}", ['title' => 'Hackeado'])->assertForbidden();
        $this->delete("/admin/products/{$producto->id}")->assertForbidden();
        $this->patch("/admin/products/{$producto->id}/toggle-status")->assertForbidden();
        $this->delete("/admin/combos/{$combo->id}")->assertForbidden();
        $this->patch('/admin/settings', ['free_shipping_threshold' => 0])->assertForbidden();
        $this->post('/admin/sellers', ['name' => 'Nuevo', 'email' => 'nuevo@ejemplo.com'])->assertForbidden();
        $this->patch("/admin/sellers/{$otroVendedor->id}/toggle-status")->assertForbidden();
        $this->put("/admin/sellers/{$otroVendedor->id}", ['name' => 'X', 'email' => 'x@ejemplo.com'])->assertForbidden();
        $this->post('/admin/documents', ['title' => 'Nuevo', 'type' => 'link', 'url' => 'https://x.com'])->assertForbidden();
        $this->delete("/admin/documents/{$documento->id}")->assertForbidden();
        $this->patch("/admin/documents/{$documento->id}/toggle-status")->assertForbidden();

        // Operativo pero sin borrar: categorías, ofertas y códigos.
        $this->delete("/admin/categories/{$categoria->id}")->assertForbidden();
        $this->delete("/admin/discount-codes/{$codigo->id}")->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $producto->id, 'title' => $producto->title]);
        $this->assertDatabaseHas('categories', ['id' => $categoria->id]);
        $this->assertDatabaseHas('discount_codes', ['id' => $codigo->id]);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_un_vendedor_si_entra_a_lo_operativo(): void
    {
        $vendedor = User::factory()->vendedor()->create();

        $this->actingAs($vendedor);

        foreach (['/admin/dashboard', '/admin/orders', '/admin/prices', '/admin/categories', '/admin/offers', '/admin/discount-codes', '/admin/documents'] as $uri) {
            $this->get($uri)->assertOk();
        }
    }

    // --- IDOR / datos de otros -----------------------------------------------------------------------------------------------

    public function test_un_cliente_no_ve_pedidos_de_otros_cambiando_el_id(): void
    {
        $cliente = User::factory()->create();
        $ajeno = User::factory()->create();
        $orden = Order::factory()->create(['user_id' => $ajeno->id, 'email' => 'ajeno@ejemplo.com', 'dni' => '30111222']);

        $this->actingAs($cliente);

        foreach (["/admin/orders/{$orden->id}", "/admin/orders/{$orden->id}/confirmation", '/admin/orders', "/orders/{$orden->id}", "/pedidos/{$orden->id}", "/mis-pedidos/{$orden->id}", "/profile/orders/{$orden->id}"] as $uri) {
            $respuesta = $this->get($uri);

            // Lo que importa: o no hay acceso, o es la página 404; en ningún caso hay datos del pedido ajeno.
            $this->assertContains($respuesta->status(), [403, 404, 405], "$uri devolvió {$respuesta->status()}");
            $this->assertStringNotContainsString('ajeno@ejemplo.com', (string) $respuesta->getContent());
            $this->assertStringNotContainsString('30111222', (string) $respuesta->getContent());
        }
    }

    public function test_no_existe_ninguna_ruta_publica_que_exponga_pedidos_de_clientes(): void
    {
        // La web no tiene «mis pedidos»: el cliente compra sin cuenta y sigue por WhatsApp. Si algún día se agrega, este test obliga a
        // revisar el IDOR (que un cliente no pueda leer el pedido de otro cambiando el id).
        foreach (Route::getRoutes()->getRoutes() as $ruta) {
            $uri = $ruta->uri();

            if (str_starts_with($uri, 'admin') || str_starts_with($uri, 'carrito')) {
                continue;
            }

            $this->assertDoesNotMatchRegularExpression('#(order|pedido|orden)#i', $uri, "Ruta pública nueva que parece de pedidos: {$uri}");
        }
    }

    public function test_el_carrito_es_de_la_sesion_y_no_de_un_id_que_se_pueda_cambiar(): void
    {
        $producto = Product::factory()->create(['is_active' => true]);

        $a = $this->flushSession()->withSession(['cart' => [['line_key' => 'k1', 'product_id' => $producto->id, 'quantity' => 2, 'variant_id' => null, 'addon_selections' => [], 'custom_color_text' => null]]]);
        $a->get(route('cart.count'))->assertOk();

        // Otra sesión (otro visitante) no ve ni toca el carrito de la primera.
        $this->flushSession()->get(route('cart.count'))->assertOk()->assertJsonPath('count', 0);
    }

    public function test_la_ficha_publica_no_muestra_lo_inactivo(): void
    {
        $producto = Product::factory()->create(['is_active' => false]);
        $combo = Combo::create(['title' => 'Oculto', 'price' => 1, 'is_active' => false]);

        $this->get(route('products.show', $producto))->assertNotFound();
        $this->get(route('combos.show', $combo))->assertNotFound();
    }

    public function test_la_oferta_de_un_admin_no_la_pueden_editar_un_cliente(): void
    {
        $cliente = User::factory()->create();
        $producto = Product::factory()->create();
        $oferta = ProductOffer::create(['product_id' => $producto->id, 'is_active' => true, 'tipo_descuento' => 'porcentaje', 'valor_descuento' => 10, 'alcance' => 'todos']);

        $this->actingAs($cliente)->put("/admin/offers/{$oferta->id}", ['is_active' => false])->assertForbidden();
        $this->actingAs($cliente)->patch("/admin/offers/{$oferta->id}/toggle")->assertForbidden();

        $this->assertTrue((bool) $oferta->fresh()->is_active);
        $this->assertSame(RolUsuario::Cliente, $cliente->fresh()->role);
    }
}
