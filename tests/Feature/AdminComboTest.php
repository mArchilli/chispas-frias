<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminComboTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_combo_with_products(): void
    {
        $admin = User::factory()->create();
        $category = Category::factory()->create();
        $productA = Product::factory()->for($category)->create();
        $productB = Product::factory()->for($category)->create();

        $this->actingAs($admin)->post(route('admin.combos.store'), [
            'title' => 'Combo Test',
            'description' => 'Descripción',
            'price' => 7500,
            'is_free_shipping' => '1',
            'is_active' => '1',
            'items' => [
                ['product_id' => $productA->id, 'quantity' => 1],
                ['product_id' => $productB->id, 'quantity' => 3],
            ],
        ])->assertRedirect(route('admin.combos.index'));

        $combo = Combo::first();
        $this->assertNotNull($combo);
        $this->assertSame('Combo Test', $combo->title);
        $this->assertEquals(7500.0, (float) $combo->price);
        $this->assertTrue($combo->is_free_shipping);
        $this->assertSame(2, $combo->items()->count());
        $this->assertSame(3, $combo->items()->where('product_id', $productB->id)->first()->quantity);
    }

    public function test_creating_a_combo_requires_at_least_one_product(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.combos.store'), [
            'title' => 'Sin productos',
            'price' => 1000,
            'items' => [],
        ])->assertSessionHasErrors('items');

        $this->assertSame(0, Combo::count());
    }

    public function test_admin_can_update_a_combo_and_sync_items(): void
    {
        $admin = User::factory()->create();
        $category = Category::factory()->create();
        $productA = Product::factory()->for($category)->create();
        $productB = Product::factory()->for($category)->create();

        $combo = Combo::create(['title' => 'Viejo', 'price' => 1000, 'is_active' => true]);
        $combo->items()->create(['product_id' => $productA->id, 'quantity' => 1]);

        $this->actingAs($admin)->put(route('admin.combos.update', $combo), [
            'title' => 'Nuevo',
            'price' => 2000,
            'is_free_shipping' => '0',
            'is_active' => '1',
            'items' => [
                ['product_id' => $productB->id, 'quantity' => 2],
            ],
        ])->assertRedirect(route('admin.combos.index'));

        $combo->refresh();
        $this->assertSame('Nuevo', $combo->title);
        $this->assertEquals(2000.0, (float) $combo->price);
        // Full-sync: productA se quita, queda sólo productB.
        $this->assertSame(1, $combo->items()->count());
        $this->assertSame($productB->id, $combo->items()->first()->product_id);
    }

    public function test_toggle_status_flips_active(): void
    {
        $admin = User::factory()->create();
        $combo = Combo::create(['title' => 'C', 'price' => 100, 'is_active' => true]);

        $this->actingAs($admin)->patch(route('admin.combos.toggle-status', $combo))->assertRedirect();

        $this->assertFalse($combo->fresh()->is_active);
    }

    public function test_combo_appears_in_public_catalog_and_has_a_public_page(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create();
        $combo = Combo::create(['title' => 'Combo Público', 'price' => 3000, 'is_active' => true]);
        $combo->items()->create(['product_id' => $product->id, 'quantity' => 1]);

        $this->get(route('combos.show', $combo))->assertOk();

        $this->get(route('products.index'))->assertInertia(
            fn ($page) => $page->component('Products/Index')->has('combos', 1)
        );
    }

    public function test_inactive_combo_is_hidden_from_public_page(): void
    {
        $combo = Combo::create(['title' => 'Oculto', 'price' => 3000, 'is_active' => false]);

        $this->get(route('combos.show', $combo))->assertNotFound();
    }
}
