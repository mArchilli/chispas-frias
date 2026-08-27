<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\Addon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminPriceListTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_incluye_solo_variantes_y_addons_activos(): void
    {
        $vendedor = User::factory()->create(['role' => RolUsuario::Vendedor]);
        $product = Product::factory()->create(['title' => 'Producto con opciones']);

        ProductVariant::factory()->create([
            'product_id' => $product->id, 'name' => 'Rojo', 'price_addon' => 300, 'stock' => 12, 'is_active' => true,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product->id, 'name' => 'Oculto', 'is_active' => false,
        ]);

        $activo = Addon::create(['name' => 'Grabado', 'price' => 500, 'is_active' => true]);
        $inactivo = Addon::create(['name' => 'Viejo', 'price' => 100, 'is_active' => false]);
        $product->addons()->attach($activo->id, ['price_override' => 650, 'sort_order' => 0]);
        $product->addons()->attach($inactivo->id, ['sort_order' => 1]);

        $this->actingAs($vendedor)->get(route('admin.prices.index'))->assertInertia(
            fn (Assert $page) => $page
                ->component('Admin/Prices/Index')
                ->where('products.data.0.variants', fn ($variants) => count($variants) === 1
                    && $variants[0]['name'] === 'Rojo'
                    && (float) $variants[0]['price_addon'] === 300.0
                    && $variants[0]['stock'] === 12)
                ->where('products.data.0.addons', fn ($addons) => count($addons) === 1
                    && $addons[0]['name'] === 'Grabado'
                    && (float) $addons[0]['price_effective'] === 650.0)
        );
    }

    public function test_index_addon_sin_override_usa_precio_base(): void
    {
        $admin = User::factory()->create();
        $product = Product::factory()->create();
        $addon = Addon::create(['name' => 'Ambientacion', 'price' => 900, 'is_active' => true]);
        $product->addons()->attach($addon->id, ['sort_order' => 0]);

        $this->actingAs($admin)->get(route('admin.prices.index'))->assertInertia(
            fn (Assert $page) => $page
                ->where('products.data.0.addons.0.price_effective', fn ($p) => (float) $p === 900.0)
        );
    }

    public function test_index_variante_con_stock_null_es_ilimitada(): void
    {
        $admin = User::factory()->create();
        $product = Product::factory()->create();
        ProductVariant::factory()->stockIlimitado()->create([
            'product_id' => $product->id, 'name' => 'Libre', 'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('admin.prices.index'))->assertInertia(
            fn (Assert $page) => $page->where('products.data.0.variants.0.stock', null)
        );
    }
}
