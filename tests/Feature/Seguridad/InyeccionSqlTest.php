<?php

namespace Tests\Feature\Seguridad;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Inyección SQL (S.2 · A.4). Toda consulta usa el query builder / Eloquent con parámetros ligados. Las únicas consultas «crudas»
 * (`whereRaw`, `orderByRaw`, `selectRaw`…) son las de esta lista, revisadas una por una: ninguna concatena datos del request.
 * Una consulta cruda nueva hace fallar el test hasta que alguien la revise y la sume acá.
 */
class InyeccionSqlTest extends TestCase
{
    use RefreshDatabase;

    /** archivo (relativo a la raíz) → cantidad de consultas crudas revisadas */
    private const REVISADAS = [
        // CASE WHEN ... con una constante de la clase (Product::sqlDisponible()) y un slug fijo: sin datos del request.
        'routes/web.php' => 1,
        'app/Models/Product.php' => 2,
        'app/Http/Controllers/ProductController.php' => 1,
        // SUM / COUNT sobre columnas fijas.
        'app/Http/Controllers/Admin/OrderController.php' => 2,
        'app/Services/ResumenDePedidos.php' => 2,
    ];

    public function test_las_unicas_consultas_crudas_son_las_revisadas(): void
    {
        // Solo app/ y routes/ (donde llegan datos del request). Las migraciones corren SQL fijo, sin datos de nadie.
        $archivos = Finder::create()->files()->in([base_path('app'), base_path('routes')])->name('*.php');
        $encontradas = [];

        foreach ($archivos as $archivo) {
            $cantidad = preg_match_all('/(whereRaw|orderByRaw|selectRaw|havingRaw|groupByRaw|fromRaw|joinRaw|DB::raw|DB::select|DB::statement|DB::unprepared|DB::insert|DB::update|DB::delete)\s*\(/', $archivo->getContents());

            if ($cantidad > 0) {
                $encontradas[str_replace(DIRECTORY_SEPARATOR, '/', substr($archivo->getPathname(), strlen(base_path()) + 1))] = $cantidad;
            }
        }

        ksort($encontradas);
        $esperadas = self::REVISADAS;
        ksort($esperadas);

        $this->assertSame($esperadas, $encontradas, 'Hay una consulta SQL cruda nueva (o una menos): revisala y actualizá la lista.');
    }

    public function test_un_texto_de_busqueda_con_sql_se_trata_como_texto(): void
    {
        $categoria = Category::factory()->create();
        $producto = Product::factory()->create(['title' => 'Chispero', 'is_active' => true, 'category_id' => $categoria->id]);

        foreach (["' OR '1'='1", "'; DROP TABLE products; --", '%', '" UNION SELECT * FROM users --', '\\'] as $busqueda) {
            $this->get(route('products.index', ['search' => $busqueda]))->assertOk();
        }

        $this->assertDatabaseHas('products', ['id' => $producto->id]);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_los_filtros_del_admin_con_sql_no_rompen_ni_filtran(): void
    {
        $admin = \App\Models\User::factory()->admin()->create();

        foreach (['admin.orders.index', 'admin.products.index', 'admin.categories.index', 'admin.prices.index', 'admin.offers.index', 'admin.discount-codes.index', 'admin.combos.index', 'admin.addons.index'] as $ruta) {
            $this->actingAs($admin)->get(route($ruta, ['search' => "' OR 1=1 --", 'estado' => "x'; DROP TABLE users;--", 'category' => "1 OR 1=1", 'status' => "' OR '1'='1", 'month' => "2026-10' OR '1'='1"]))
                ->assertStatus(200);
        }

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
