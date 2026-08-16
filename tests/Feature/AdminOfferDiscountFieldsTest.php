<?php

namespace Tests\Feature;

use App\Enums\AlcanceOferta;
use App\Enums\TipoDescuento;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\ProductPriceTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOfferDiscountFieldsTest extends TestCase
{
    use RefreshDatabase;

    // --- ProductOfferController: modal rápido desde Admin/Products/Index -----------

    public function test_quick_offer_alcance_todos_sincroniza_offer_price_con_el_precio_final(): void
    {
        $admin = User::factory()->create();
        $product = Product::factory()->create(['price' => 100]);

        $response = $this->actingAs($admin)->post(route('admin.products.quick-offer', $product), [
            'tipo_descuento' => TipoDescuento::Porcentaje->value,
            'valor_descuento' => 25,
            'alcance' => AlcanceOferta::Todos->value,
            'is_active' => true,
        ]);

        $response->assertRedirect();

        $offer = ProductOffer::firstOrFail();
        $this->assertSame(TipoDescuento::Porcentaje, $offer->tipo_descuento);
        $this->assertSame(AlcanceOferta::Todos, $offer->alcance);
        $this->assertEquals(75.0, (float) $offer->offer_price);
        $this->assertEquals(25.0, (float) $offer->percentage_discount);
    }

    public function test_quick_offer_alcance_especifico_apuntando_al_precio_base_sincroniza_offer_price(): void
    {
        $admin = User::factory()->create();
        $product = Product::factory()->create(['price' => 100]);
        ProductPriceTier::factory()->create([
            'product_id' => $product->id,
            'cantidad_minima' => 10,
            'precio_unitario' => 80,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.products.quick-offer', $product), [
            'tipo_descuento' => TipoDescuento::Fijo->value,
            'valor_descuento' => 10,
            'alcance' => AlcanceOferta::Especifico->value,
            'product_price_tier_id' => null,
            'is_active' => true,
        ]);

        $response->assertRedirect();

        $offer = ProductOffer::firstOrFail();
        $this->assertNull($offer->product_price_tier_id);
        $this->assertEquals(90.0, (float) $offer->offer_price);
        $this->assertEquals(10.0, (float) $offer->percentage_discount);
    }

    public function test_quick_offer_alcance_especifico_apuntando_a_un_tier_no_base_deja_offer_price_en_null(): void
    {
        $admin = User::factory()->create();
        $product = Product::factory()->create(['price' => 100]);
        $tier = ProductPriceTier::factory()->create([
            'product_id' => $product->id,
            'cantidad_minima' => 10,
            'precio_unitario' => 80,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.products.quick-offer', $product), [
            'tipo_descuento' => TipoDescuento::Fijo->value,
            'valor_descuento' => 20,
            'alcance' => AlcanceOferta::Especifico->value,
            'product_price_tier_id' => $tier->id,
            'is_active' => true,
        ]);

        $response->assertRedirect();

        $offer = ProductOffer::firstOrFail();
        $this->assertSame($tier->id, $offer->product_price_tier_id);
        $this->assertNull($offer->offer_price);
        $this->assertNull($offer->percentage_discount);
    }

    public function test_quick_offer_rechaza_tier_que_pertenece_a_otro_producto(): void
    {
        $admin = User::factory()->create();
        $product = Product::factory()->create(['price' => 100]);
        $otroProducto = Product::factory()->create(['price' => 50]);
        $tierAjeno = ProductPriceTier::factory()->create(['product_id' => $otroProducto->id]);

        $response = $this->actingAs($admin)->post(route('admin.products.quick-offer', $product), [
            'tipo_descuento' => TipoDescuento::Fijo->value,
            'valor_descuento' => 10,
            'alcance' => AlcanceOferta::Especifico->value,
            'product_price_tier_id' => $tierAjeno->id,
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('product_price_tier_id');
        $this->assertSame(0, ProductOffer::count());
    }

    public function test_offer_update_resincroniza_offer_price_al_pasar_de_todos_a_especifico_no_base(): void
    {
        $admin = User::factory()->create();
        $product = Product::factory()->create(['price' => 100]);
        $tier = ProductPriceTier::factory()->create([
            'product_id' => $product->id,
            'cantidad_minima' => 10,
            'precio_unitario' => 80,
        ]);

        $offer = $product->offers()->create([
            'tipo_descuento' => TipoDescuento::Porcentaje,
            'valor_descuento' => 25,
            'alcance' => AlcanceOferta::Todos,
            'offer_price' => 75,
            'percentage_discount' => 25,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.products.offers.update', $offer), [
            'tipo_descuento' => TipoDescuento::Fijo->value,
            'valor_descuento' => 15,
            'alcance' => AlcanceOferta::Especifico->value,
            'product_price_tier_id' => $tier->id,
            'is_active' => true,
        ]);

        $response->assertRedirect();

        $offer->refresh();
        $this->assertNull($offer->offer_price);
        $this->assertNull($offer->percentage_discount);
    }

    public function test_valor_descuento_porcentual_mayor_a_100_es_rechazado(): void
    {
        $admin = User::factory()->create();
        $product = Product::factory()->create(['price' => 100]);

        $response = $this->actingAs($admin)->post(route('admin.products.quick-offer', $product), [
            'tipo_descuento' => TipoDescuento::Porcentaje->value,
            'valor_descuento' => 150,
            'alcance' => AlcanceOferta::Todos->value,
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('valor_descuento');
        $this->assertSame(0, ProductOffer::count());
    }

    // --- ProductOfferAdminController: vista dedicada Admin/Offers/Index ------------

    public function test_dedicated_store_alcance_todos_sincroniza_offer_price(): void
    {
        $admin = User::factory()->create();
        $product = Product::factory()->create(['price' => 200]);

        $response = $this->actingAs($admin)->post(route('admin.offers.store'), [
            'product_id' => $product->id,
            'tipo_descuento' => TipoDescuento::Fijo->value,
            'valor_descuento' => 50,
            'alcance' => AlcanceOferta::Todos->value,
            'is_active' => true,
        ]);

        $response->assertRedirect();

        $offer = ProductOffer::firstOrFail();
        $this->assertEquals(150.0, (float) $offer->offer_price);
    }

    public function test_dedicated_store_alcance_especifico_apuntando_a_un_tier_no_base_deja_offer_price_en_null(): void
    {
        $admin = User::factory()->create();
        $product = Product::factory()->create(['price' => 100]);
        $tier = ProductPriceTier::factory()->create([
            'product_id' => $product->id,
            'cantidad_minima' => 10,
            'precio_unitario' => 80,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.offers.store'), [
            'product_id' => $product->id,
            'tipo_descuento' => TipoDescuento::Fijo->value,
            'valor_descuento' => 20,
            'alcance' => AlcanceOferta::Especifico->value,
            'product_price_tier_id' => $tier->id,
            'is_active' => true,
        ]);

        $response->assertRedirect();

        $offer = ProductOffer::firstOrFail();
        $this->assertNull($offer->offer_price);
    }

    public function test_dedicated_store_rechaza_tier_de_otro_producto(): void
    {
        $admin = User::factory()->create();
        $product = Product::factory()->create(['price' => 100]);
        $otroProducto = Product::factory()->create(['price' => 50]);
        $tierAjeno = ProductPriceTier::factory()->create(['product_id' => $otroProducto->id]);

        $response = $this->actingAs($admin)->post(route('admin.offers.store'), [
            'product_id' => $product->id,
            'tipo_descuento' => TipoDescuento::Fijo->value,
            'valor_descuento' => 10,
            'alcance' => AlcanceOferta::Especifico->value,
            'product_price_tier_id' => $tierAjeno->id,
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('product_price_tier_id');
        $this->assertSame(0, ProductOffer::count());
    }

    public function test_dedicated_update_resincroniza_offer_price(): void
    {
        $admin = User::factory()->create();
        $product = Product::factory()->create(['price' => 100]);

        $offer = $product->offers()->create([
            'tipo_descuento' => TipoDescuento::Porcentaje,
            'valor_descuento' => 10,
            'alcance' => AlcanceOferta::Todos,
            'offer_price' => 90,
            'percentage_discount' => 10,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.offers.update', $offer), [
            'tipo_descuento' => TipoDescuento::Porcentaje->value,
            'valor_descuento' => 40,
            'alcance' => AlcanceOferta::Todos->value,
            'is_active' => true,
        ]);

        $response->assertRedirect();

        $offer->refresh();
        $this->assertEquals(60.0, (float) $offer->offer_price);
        $this->assertEquals(40.0, (float) $offer->percentage_discount);
    }
}
