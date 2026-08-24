<?php

namespace Tests\Feature;

use App\Models\DiscountCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminDiscountCodeTest extends TestCase
{
    use RefreshDatabase;

    private function crearCodigo(array $overrides = []): DiscountCode
    {
        return DiscountCode::create(array_merge([
            'code' => 'CODE' . random_int(100000, 999999),
            'description' => null,
            'percentage' => 10,
            'min_purchase_amount' => null,
            'usage_limit' => null,
            'usage_count' => 0,
            'start_date' => null,
            'end_date' => null,
            'is_active' => true,
        ], $overrides));
    }

    // --- index / listado y filtros --------------------------------------------------

    public function test_index_requires_authentication(): void
    {
        $response = $this->get(route('admin.discount-codes.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_index_lists_discount_codes_most_recent_first(): void
    {
        $admin = User::factory()->create();

        // 'created_at' no es mass-assignable (no está en $fillable), así que se
        // fuerza después de crear, igual que necesitaría cualquier código creado
        // por DiscountCode::create() directo (a diferencia de Order::factory(),
        // que sí puede pisarlo porque las factories corren dentro de Model::unguarded()).
        $older = $this->crearCodigo(['code' => 'VIEJO']);
        $older->forceFill(['created_at' => now()->subDay()])->save();

        $newer = $this->crearCodigo(['code' => 'NUEVO']);
        $newer->forceFill(['created_at' => now()])->save();

        $response = $this->actingAs($admin)->get(route('admin.discount-codes.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/DiscountCodes/Index')
            ->where('discountCodes.data.0.id', $newer->id)
            ->where('discountCodes.data.1.id', $older->id)
        );
    }

    public function test_index_filters_by_search_term(): void
    {
        $admin = User::factory()->create();

        $match = $this->crearCodigo(['code' => 'VERANO2026']);
        $this->crearCodigo(['code' => 'INVIERNO2026']);

        $response = $this->actingAs($admin)->get(route('admin.discount-codes.index', ['search' => 'verano']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('discountCodes.data', 1)
            ->where('discountCodes.data.0.id', $match->id)
        );
    }

    public function test_index_filters_by_status_inactivo(): void
    {
        $admin = User::factory()->create();

        $inactivo = $this->crearCodigo(['code' => 'INACTIVO', 'is_active' => false]);
        $this->crearCodigo(['code' => 'ACTIVO', 'is_active' => true]);

        $response = $this->actingAs($admin)->get(route('admin.discount-codes.index', ['status' => 'inactivo']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('discountCodes.data', 1)
            ->where('discountCodes.data.0.id', $inactivo->id)
            ->where('discountCodes.data.0.status', 'inactivo')
        );
    }

    public function test_index_filters_by_status_agotado(): void
    {
        $admin = User::factory()->create();

        $agotado = $this->crearCodigo(['code' => 'AGOTADO', 'usage_limit' => 5, 'usage_count' => 5]);
        $this->crearCodigo(['code' => 'CONCUPO', 'usage_limit' => 5, 'usage_count' => 2]);

        $response = $this->actingAs($admin)->get(route('admin.discount-codes.index', ['status' => 'agotado']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('discountCodes.data', 1)
            ->where('discountCodes.data.0.id', $agotado->id)
            ->where('discountCodes.data.0.status', 'agotado')
        );
    }

    public function test_index_filters_by_status_activo(): void
    {
        $admin = User::factory()->create();

        $activo = $this->crearCodigo(['code' => 'ACTIVO']);
        $this->crearCodigo(['code' => 'INACTIVO', 'is_active' => false]);
        $this->crearCodigo(['code' => 'AGOTADO', 'usage_limit' => 1, 'usage_count' => 1]);

        $response = $this->actingAs($admin)->get(route('admin.discount-codes.index', ['status' => 'activo']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('discountCodes.data', 1)
            ->where('discountCodes.data.0.id', $activo->id)
        );
    }

    // --- store -----------------------------------------------------------------

    public function test_store_creates_a_discount_code(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.discount-codes.store'), [
            'code' => 'verano2026',
            'description' => 'Promo de verano',
            'percentage' => 15,
            'min_purchase_amount' => 500,
            'usage_limit' => 100,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.discount-codes.index'));

        $discountCode = DiscountCode::firstOrFail();
        // El code se normaliza a mayúsculas al guardarse (setCodeAttribute).
        $this->assertSame('VERANO2026', $discountCode->code);
        $this->assertEquals(15.0, (float) $discountCode->percentage);
        $this->assertEquals(500.0, (float) $discountCode->min_purchase_amount);
        $this->assertSame(100, $discountCode->usage_limit);
        $this->assertSame(0, $discountCode->usage_count);
    }

    public function test_store_rejects_a_duplicate_code(): void
    {
        $admin = User::factory()->create();
        $this->crearCodigo(['code' => 'VERANO2026']);

        $response = $this->actingAs($admin)->post(route('admin.discount-codes.store'), [
            'code' => 'verano2026',
            'percentage' => 10,
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertSame(1, DiscountCode::count());
    }

    public function test_store_rejects_percentage_out_of_range(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.discount-codes.store'), [
            'code' => 'INVALIDO',
            'percentage' => 150,
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('percentage');
        $this->assertSame(0, DiscountCode::count());
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->post(route('admin.discount-codes.store'), [
            'code' => 'VERANO2026',
            'percentage' => 10,
        ]);

        $response->assertRedirect(route('login'));
        $this->assertSame(0, DiscountCode::count());
    }

    // --- update ------------------------------------------------------------------

    public function test_update_edits_a_discount_code(): void
    {
        $admin = User::factory()->create();
        $discountCode = $this->crearCodigo(['code' => 'VIEJO', 'percentage' => 10]);

        $response = $this->actingAs($admin)->put(route('admin.discount-codes.update', $discountCode), [
            'code' => 'nuevo',
            'percentage' => 20,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.discount-codes.index'));

        $discountCode->refresh();
        $this->assertSame('NUEVO', $discountCode->code);
        $this->assertEquals(20.0, (float) $discountCode->percentage);
    }

    public function test_update_rejects_code_change_once_the_code_has_been_used(): void
    {
        $admin = User::factory()->create();
        $discountCode = $this->crearCodigo(['code' => 'USADO', 'usage_count' => 3]);

        $response = $this->actingAs($admin)->put(route('admin.discount-codes.update', $discountCode), [
            'code' => 'nuevocodigo',
            'percentage' => 20,
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('code');

        $discountCode->refresh();
        $this->assertSame('USADO', $discountCode->code);
        $this->assertEquals(10.0, (float) $discountCode->percentage);
    }

    public function test_update_allows_other_fields_when_used_as_long_as_the_code_text_stays_the_same(): void
    {
        $admin = User::factory()->create();
        $discountCode = $this->crearCodigo(['code' => 'USADO', 'usage_count' => 3, 'percentage' => 10]);

        $response = $this->actingAs($admin)->put(route('admin.discount-codes.update', $discountCode), [
            'code' => 'usado',
            'percentage' => 30,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.discount-codes.index'));

        $discountCode->refresh();
        $this->assertSame('USADO', $discountCode->code);
        $this->assertEquals(30.0, (float) $discountCode->percentage);
    }

    // --- toggle-status -------------------------------------------------------------

    public function test_toggle_status_flips_is_active(): void
    {
        $admin = User::factory()->create();
        $discountCode = $this->crearCodigo(['is_active' => true]);

        $response = $this->actingAs($admin)->patch(route('admin.discount-codes.toggle-status', $discountCode));

        $response->assertRedirect();
        $this->assertFalse($discountCode->fresh()->is_active);

        $response = $this->actingAs($admin)->patch(route('admin.discount-codes.toggle-status', $discountCode));

        $response->assertRedirect();
        $this->assertTrue($discountCode->fresh()->is_active);
    }

    // --- destroy -----------------------------------------------------------------

    public function test_destroy_deletes_a_discount_code_without_usage(): void
    {
        $admin = User::factory()->create();
        $discountCode = $this->crearCodigo(['usage_count' => 0]);

        $response = $this->actingAs($admin)->delete(route('admin.discount-codes.destroy', $discountCode));

        $response->assertRedirect(route('admin.discount-codes.index'));
        $this->assertSame(0, DiscountCode::count());
    }

    public function test_destroy_blocks_deletion_of_a_discount_code_that_has_been_used(): void
    {
        $admin = User::factory()->create();
        $discountCode = $this->crearCodigo(['usage_count' => 2]);

        $response = $this->actingAs($admin)->delete(route('admin.discount-codes.destroy', $discountCode));

        $response->assertSessionHasErrors('error');
        $this->assertSame(1, DiscountCode::count());
        $this->assertNotNull($discountCode->fresh());
    }
}
