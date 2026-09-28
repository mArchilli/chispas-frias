<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminAddonTest extends TestCase
{
    use RefreshDatabase;

    private function crearAddon(array $overrides = []): Addon
    {
        return Addon::create(array_merge([
            'name' => 'Addon ' . random_int(100000, 999999),
            'description' => null,
            'price' => 500,
            'requires_text' => false,
            'text_placeholder' => null,
            'max_characters' => 40,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * Marca $addon como usado creando una orden con un order_item cuyo
     * addons_selected lo referencia (mismo shape que producirá el checkout).
     */
    private function marcarComoUsado(Addon $addon): void
    {
        OrderItem::factory()->for(Order::factory())->create([
            'addons_selected' => [
                ['addon_id' => $addon->id, 'name' => $addon->name, 'price' => (float) $addon->price],
            ],
        ]);
    }

    // --- index / listado y filtros --------------------------------------------------

    public function test_index_requires_authentication(): void
    {
        $this->get(route('admin.addons.index'))->assertRedirect(route('login'));
    }

    public function test_index_lists_addons_alphabetically(): void
    {
        $admin = User::factory()->create();
        $this->crearAddon(['name' => 'Zocalo']);
        $this->crearAddon(['name' => 'Ambientacion']);

        $this->actingAs($admin)->get(route('admin.addons.index'))->assertInertia(
            fn (Assert $page) => $page
                ->component('Admin/Addons/Index')
                ->where('addons.data.0.name', 'Ambientacion')
                ->where('addons.data.1.name', 'Zocalo')
        );
    }

    public function test_index_filters_by_search_and_status(): void
    {
        $admin = User::factory()->create();
        $match = $this->crearAddon(['name' => 'Grabado laser', 'is_active' => true]);
        $this->crearAddon(['name' => 'Grabado laser inactivo', 'is_active' => false]);
        $this->crearAddon(['name' => 'Otra cosa', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.addons.index', ['search' => 'grabado', 'status' => 'activo']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('addons.data', 1)
                ->where('addons.data.0.id', $match->id));
    }

    public function test_index_marks_addons_used_in_orders(): void
    {
        $admin = User::factory()->create();
        $usado = $this->crearAddon(['name' => 'Usado']);
        $libre = $this->crearAddon(['name' => 'Libre']);
        $this->marcarComoUsado($usado);

        $this->actingAs($admin)->get(route('admin.addons.index'))->assertInertia(
            fn (Assert $page) => $page
                ->where('addons.data.0.id', $libre->id)->where('addons.data.0.en_uso', false)
                ->where('addons.data.1.id', $usado->id)->where('addons.data.1.en_uso', true)
        );
    }

    // --- store -----------------------------------------------------------------

    public function test_store_creates_an_addon(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.addons.store'), [
            'name' => 'Grabado láser',
            'description' => 'Grabado del nombre',
            'price' => 1500,
            'requires_text' => true,
            'text_placeholder' => 'Nombre a grabar',
            'max_characters' => 30,
            'is_active' => true,
        ])->assertRedirect(route('admin.addons.index'));

        $addon = Addon::firstOrFail();
        $this->assertSame('Grabado láser', $addon->name);
        $this->assertEquals(1500.0, (float) $addon->price);
        $this->assertTrue($addon->requires_text);
        $this->assertSame(30, $addon->max_characters);
    }

    public function test_store_rejects_a_duplicate_name(): void
    {
        $admin = User::factory()->create();
        $this->crearAddon(['name' => 'Grabado']);

        $this->actingAs($admin)->post(route('admin.addons.store'), [
            'name' => 'Grabado',
            'price' => 100,
            'is_active' => true,
        ])->assertSessionHasErrors('name');

        $this->assertSame(1, Addon::count());
    }

    public function test_store_rejects_a_negative_price(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.addons.store'), [
            'name' => 'Invalido',
            'price' => -5,
            'is_active' => true,
        ])->assertSessionHasErrors('price');

        $this->assertSame(0, Addon::count());
    }

    // --- update ------------------------------------------------------------------

    public function test_update_edits_an_addon(): void
    {
        $admin = User::factory()->create();
        $addon = $this->crearAddon(['name' => 'Viejo', 'price' => 100]);

        $this->actingAs($admin)->put(route('admin.addons.update', $addon), [
            'name' => 'Nuevo',
            'price' => 250,
            'requires_text' => false,
            'is_active' => true,
        ])->assertRedirect(route('admin.addons.index'));

        $addon->refresh();
        $this->assertSame('Nuevo', $addon->name);
        $this->assertEquals(250.0, (float) $addon->price);
    }

    public function test_update_rejects_a_name_already_taken_by_another_addon(): void
    {
        $admin = User::factory()->create();
        $this->crearAddon(['name' => 'Ocupado']);
        $addon = $this->crearAddon(['name' => 'Editable']);

        $this->actingAs($admin)->put(route('admin.addons.update', $addon), [
            'name' => 'Ocupado',
            'price' => 100,
            'is_active' => true,
        ])->assertSessionHasErrors('name');

        $this->assertSame('Editable', $addon->fresh()->name);
    }

    // --- toggle-status -------------------------------------------------------------

    public function test_toggle_status_flips_is_active(): void
    {
        $admin = User::factory()->create();
        $addon = $this->crearAddon(['is_active' => true]);

        $this->actingAs($admin)->patch(route('admin.addons.toggle-status', $addon))->assertRedirect();
        $this->assertFalse($addon->fresh()->is_active);

        $this->actingAs($admin)->patch(route('admin.addons.toggle-status', $addon))->assertRedirect();
        $this->assertTrue($addon->fresh()->is_active);
    }

    // --- destroy -----------------------------------------------------------------

    public function test_destroy_deletes_an_unused_addon(): void
    {
        $admin = User::factory()->create();
        $addon = $this->crearAddon();

        $this->actingAs($admin)->delete(route('admin.addons.destroy', $addon))
            ->assertRedirect(route('admin.addons.index'));

        $this->assertSame(0, Addon::count());
    }

    public function test_destroy_blocks_an_addon_used_in_an_order(): void
    {
        $admin = User::factory()->create();
        $addon = $this->crearAddon();
        $this->marcarComoUsado($addon);

        $this->actingAs($admin)->delete(route('admin.addons.destroy', $addon))
            ->assertSessionHasErrors('error');

        $this->assertNotNull($addon->fresh());
    }

    public function test_destroy_detaches_addon_from_products_when_deleted(): void
    {
        $admin = User::factory()->create();
        $addon = $this->crearAddon();
        $product = Product::factory()->create();
        $product->addons()->attach($addon->id, ['sort_order' => 0]);

        $this->actingAs($admin)->delete(route('admin.addons.destroy', $addon))->assertRedirect();

        $this->assertDatabaseMissing('product_addon', ['addon_id' => $addon->id]);
    }
}
