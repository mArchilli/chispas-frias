<?php

namespace Tests\Feature;

use App\Enums\AlcanceOferta;
use App\Enums\TipoDescuento;
use App\Models\DiscountCode;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\User;
use App\Support\ZonaHoraria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Las vigencias de ofertas y cupones (start_date / end_date) que tipea el admin son HORA ARGENTINA:
 * se guardan en UTC (+3 h) y vencen a la hora argentina que se tipeó, no 3 horas antes.
 */
class VigenciaHoraArgentinaTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Instante dado en UTC (los instantes de estos tests se escriben siempre en UTC, que es lo que guarda la base). */
    private function utc(string $fecha): Carbon
    {
        return Carbon::parse($fecha, 'UTC');
    }

    public function test_deEntrada_interpreta_lo_tipeado_como_hora_argentina_y_lo_lleva_a_utc(): void
    {
        $fecha = ZonaHoraria::deEntrada('2026-10-31T23:59');

        $this->assertSame('2026-11-01 02:59:00', $fecha->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $fecha->getTimezone()->getName());

        $this->assertNull(ZonaHoraria::deEntrada(null));
        $this->assertNull(ZonaHoraria::deEntrada('  '));

        // Un valor que ya trae su desfase se respeta.
        $this->assertSame('2026-11-01 02:59:00', ZonaHoraria::deEntrada('2026-11-01T02:59:00Z')->format('Y-m-d H:i:s'));
    }

    public function test_una_oferta_hasta_el_31_10_2359_vence_a_las_2359_hora_argentina(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['price' => 100]);

        $this->actingAs($admin)->post(route('admin.offers.store'), [
            'product_id' => $product->id,
            'tipo_descuento' => TipoDescuento::Porcentaje->value,
            'valor_descuento' => 10,
            'alcance' => AlcanceOferta::Todos->value,
            'start_date' => '2026-10-01T00:00',
            'end_date' => '2026-10-31T23:59',
            'is_active' => true,
        ])->assertRedirect();

        // En la base queda el instante real en UTC.
        $this->assertSame('2026-10-01 03:00:00', DB::table('product_offers')->value('start_date'));
        $this->assertSame('2026-11-01 02:59:00', DB::table('product_offers')->value('end_date'));

        $oferta = ProductOffer::firstOrFail();

        // 31/10 23:58:59 hora argentina (= 02:58:59 UTC del 1/11): sigue vigente.
        Carbon::setTestNow($this->utc('2026-11-01 02:58:59'));
        $this->assertTrue($oferta->isCurrentlyActive());
        $this->assertSame(1, ProductOffer::active()->count());

        // 31/10 23:59:00 hora argentina: todavía es el último minuto.
        Carbon::setTestNow($this->utc('2026-11-01 02:59:00'));
        $this->assertTrue($oferta->isCurrentlyActive());

        // 1/11 00:00:00 hora argentina: vencida. (Antes del arreglo vencía a las 20:59 del 31/10.)
        Carbon::setTestNow($this->utc('2026-11-01 03:00:00'));
        $this->assertFalse($oferta->isCurrentlyActive());
        $this->assertSame(0, ProductOffer::active()->count());
    }

    public function test_una_oferta_hasta_las_2359_sigue_vigente_a_las_2100_argentinas(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['price' => 100]);

        $this->actingAs($admin)->post(route('admin.products.quick-offer', $product), [
            'tipo_descuento' => TipoDescuento::Porcentaje->value,
            'valor_descuento' => 10,
            'alcance' => AlcanceOferta::Todos->value,
            'end_date' => '2026-10-31T23:59',
            'is_active' => true,
        ])->assertRedirect();

        // 31/10 21:00 hora argentina = 00:00 UTC del 1/11: con la vigencia guardada "tal cual" ya estaría vencida.
        Carbon::setTestNow($this->utc('2026-11-01 00:00:00'));

        $this->assertSame(1, ProductOffer::active()->count());
    }

    public function test_actualizar_una_oferta_tambien_interpreta_hora_argentina(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['price' => 100]);
        $oferta = $product->offers()->create([
            'tipo_descuento' => TipoDescuento::Porcentaje->value,
            'valor_descuento' => 10,
            'alcance' => AlcanceOferta::Todos->value,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->put(route('admin.offers.update', $oferta), [
            'tipo_descuento' => TipoDescuento::Porcentaje->value,
            'valor_descuento' => 10,
            'alcance' => AlcanceOferta::Todos->value,
            'start_date' => '2026-12-01T08:30',
            'end_date' => '2026-12-31T23:59',
            'is_active' => true,
        ])->assertRedirect();

        $this->assertSame('2026-12-01 11:30:00', DB::table('product_offers')->where('id', $oferta->id)->value('start_date'));
        $this->assertSame('2027-01-01 02:59:00', DB::table('product_offers')->where('id', $oferta->id)->value('end_date'));
    }

    public function test_el_formulario_de_edicion_recibe_el_instante_real_en_utc(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['price' => 100]);
        $oferta = $product->offers()->create([
            'tipo_descuento' => TipoDescuento::Porcentaje->value,
            'valor_descuento' => 10,
            'alcance' => AlcanceOferta::Todos->value,
            'end_date' => $this->utc('2026-11-01 02:59:00'),
            'is_active' => true,
        ]);

        // El front lo convierte a hora argentina para el input (utils/fecha.js, aValorDeInput): "2026-10-31T23:59".
        $this->actingAs($admin)->get(route('admin.offers.edit', $oferta))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Offers/Edit')
                ->where('offer.end_date', '2026-11-01T02:59:00.000000Z'));
    }

    public function test_un_cupon_hasta_el_31_10_2359_vence_a_las_2359_hora_argentina(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.discount-codes.store'), [
            'code' => 'OCTUBRE',
            'percentage' => 10,
            'start_date' => '2026-10-01T00:00',
            'end_date' => '2026-10-31T23:59',
            'is_active' => true,
        ])->assertRedirect();

        $this->assertSame('2026-10-01 03:00:00', DB::table('discount_codes')->value('start_date'));
        $this->assertSame('2026-11-01 02:59:00', DB::table('discount_codes')->value('end_date'));

        $cupon = DiscountCode::firstOrFail();

        Carbon::setTestNow($this->utc('2026-11-01 02:59:00')); // 31/10 23:59 ART
        $this->assertTrue($cupon->vigente());
        $this->assertSame(1, DiscountCode::active()->count());

        Carbon::setTestNow($this->utc('2026-11-01 03:00:00')); // 1/11 00:00 ART
        $this->assertFalse($cupon->vigente());
        $this->assertSame(0, DiscountCode::active()->count());
    }

    public function test_actualizar_un_cupon_interpreta_hora_argentina_y_sin_fechas_queda_en_null(): void
    {
        $admin = User::factory()->admin()->create();
        $cupon = DiscountCode::create([
            'code' => 'VIEJO', 'percentage' => 10, 'is_active' => true,
            'start_date' => $this->utc('2026-01-01 00:00:00'), 'end_date' => $this->utc('2026-01-31 00:00:00'),
        ]);

        $this->actingAs($admin)->put(route('admin.discount-codes.update', $cupon), [
            'code' => 'VIEJO',
            'percentage' => 10,
            'start_date' => '2026-06-01T10:00',
            'end_date' => null,
            'is_active' => true,
        ])->assertRedirect();

        $this->assertSame('2026-06-01 13:00:00', DB::table('discount_codes')->value('start_date'));
        $this->assertNull(DB::table('discount_codes')->value('end_date'));
    }

    public function test_la_migracion_de_datos_suma_el_desfase_una_vez_y_respeta_los_null(): void
    {
        $producto = Product::factory()->create();
        $ahora = now();

        DB::table('product_offers')->insert([
            ['id' => 1, 'product_id' => $producto->id, 'tipo_descuento' => 'porcentaje', 'valor_descuento' => 10,
                'alcance' => 'todos', 'percentage_discount' => 10, 'start_date' => '2026-10-01 00:00:00',
                'end_date' => '2026-10-31 23:59:00', 'is_active' => 1, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['id' => 2, 'product_id' => $producto->id, 'tipo_descuento' => 'porcentaje', 'valor_descuento' => 10,
                'alcance' => 'todos', 'percentage_discount' => 10, 'start_date' => null,
                'end_date' => null, 'is_active' => 0, 'created_at' => $ahora, 'updated_at' => $ahora],
        ]);
        DB::table('discount_codes')->insert([
            'id' => 1, 'code' => 'X', 'percentage' => 5, 'start_date' => null, 'end_date' => '2026-12-31 23:59:00',
            'is_active' => 1, 'created_at' => $ahora, 'updated_at' => $ahora,
        ]);

        $migracion = require database_path('migrations/2026_10_19_130000_corregir_vigencias_a_utc.php');
        $migracion->up();

        $this->assertSame('2026-10-01 03:00:00', DB::table('product_offers')->where('id', 1)->value('start_date'));
        $this->assertSame('2026-11-01 02:59:00', DB::table('product_offers')->where('id', 1)->value('end_date'));
        $this->assertNull(DB::table('product_offers')->where('id', 2)->value('start_date'));
        $this->assertNull(DB::table('product_offers')->where('id', 2)->value('end_date'));
        $this->assertNull(DB::table('discount_codes')->where('id', 1)->value('start_date'));
        $this->assertSame('2027-01-01 02:59:00', DB::table('discount_codes')->where('id', 1)->value('end_date'));

        // El rollback deja los datos como estaban.
        $migracion->down();

        $this->assertSame('2026-10-31 23:59:00', DB::table('product_offers')->where('id', 1)->value('end_date'));
        $this->assertSame('2026-12-31 23:59:00', DB::table('discount_codes')->where('id', 1)->value('end_date'));
    }
}
