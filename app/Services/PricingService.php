<?php

namespace App\Services;

use App\Enums\AlcanceOferta;
use App\Enums\TipoDescuento;
use App\Exceptions\VarianteRequeridaException;
use App\Models\Addon;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\ProductPriceTier;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

class PricingService
{
    /**
     * Resuelve el precio unitario para un producto y una cantidad dada, combinando
     * la escala de precios por cantidad (tiers) con la oferta activa, si corresponde
     * aplicarla según su alcance.
     *
     * Opcionalmente resuelve también las opciones del producto: la variante de
     * color elegida (`$varianteId`, con su recargo) y los add-ons de personalización
     * (`$addonIds`, cada uno con su costo). El descuento de la oferta se calcula
     * SIEMPRE solo sobre el precio de lista/tier — nunca sobre esos recargos.
     *
     * Lanza VarianteRequeridaException si `$varianteId`/`$addonIds` no pertenecen al
     * producto o no están activos, o si `$exigirVariante` es true, el producto tiene
     * variantes activas y no vino `$varianteId`.
     *
     * @param  array<int, int>  $addonIds
     *
     * @throws VarianteRequeridaException
     */
    public function calcularPrecio(
        Product $product,
        int $cantidad,
        ?int $varianteId = null,
        array $addonIds = [],
        bool $exigirVariante = false,
    ): PriceResult {
        $tier = $product->tierAplicable($cantidad);
        $precioLista = round((float) ($tier?->precio_unitario ?? $product->price), 2);

        $offer = $this->ofertaActiva($product);
        $ofertaAplicada = $offer && $this->ofertaAplica($offer, $tier) ? $offer : null;

        $precioUnitarioFinal = $ofertaAplicada
            ? $this->aplicarDescuento($precioLista, $ofertaAplicada)
            : $precioLista;

        $ahorroUnitario = round(max(0, $precioLista - $precioUnitarioFinal), 2);
        $ahorroPorcentaje = $precioLista > 0
            ? round(($ahorroUnitario / $precioLista) * 100, 2)
            : 0.0;

        $varianteAplicada = $this->resolverVariante($product, $varianteId, $exigirVariante);
        $addonsAplicados = $this->resolverAddons($product, $addonIds);

        $recargoVariante = $varianteAplicada
            ? round((float) $varianteAplicada->price_addon, 2)
            : 0.0;
        $addonsTotal = round(
            array_reduce($addonsAplicados, fn (float $acc, Addon $addon) => $acc + $this->precioAddon($addon), 0.0),
            2
        );

        return new PriceResult(
            precioLista: $precioLista,
            precioUnitarioFinal: $precioUnitarioFinal,
            ofertaAplicada: $ofertaAplicada,
            tierAplicado: $tier,
            ahorroUnitario: $ahorroUnitario,
            ahorroPorcentaje: $ahorroPorcentaje,
            varianteAplicada: $varianteAplicada,
            addonsAplicados: $addonsAplicados,
            recargoVariante: $recargoVariante,
            addonsTotal: $addonsTotal,
            precioFinalConOpciones: round($precioUnitarioFinal + $recargoVariante + $addonsTotal, 2),
        );
    }

    /**
     * Reusa `currentOffer` si ya viene eager-loaded en el producto (evita el N+1 de
     * getCurrentOfferPrice()/getCurrentPrice()). Si no viene cargada, el propio
     * getter de Eloquent la resuelve y cachea en el modelo — como máximo una query.
     */
    private function ofertaActiva(Product $product): ?ProductOffer
    {
        return $product->currentOffer;
    }

    /**
     * La oferta aplica a todo el catálogo, o solo si el tier al que apunta coincide
     * con el tier resuelto para esta cantidad (null === null cubre "ambos apuntan al
     * precio base").
     */
    private function ofertaAplica(ProductOffer $offer, ?ProductPriceTier $tier): bool
    {
        if ($offer->alcance === AlcanceOferta::Todos) {
            return true;
        }

        return $offer->product_price_tier_id === $tier?->id;
    }

    private function aplicarDescuento(float $precioLista, ProductOffer $offer): float
    {
        $valor = (float) $offer->valor_descuento;

        $precio = $offer->tipo_descuento === TipoDescuento::Porcentaje
            ? $precioLista * (1 - $valor / 100)
            : $precioLista - $valor;

        return round(max(0, $precio), 2);
    }

    /**
     * Resuelve la variante de color elegida, validando que pertenezca al producto
     * y esté activa. A diferencia del precio de vidriera (ficha pública, catálogo),
     * que pasa `$exigirVariante = false` para poder mostrar un precio antes de que
     * el cliente elija color, el checkout lo pasa en true: ahí, si el producto
     * tiene variantes activas, elegir una es obligatorio.
     *
     * Público para que el carrito (CartController::add) valide la variante elegida
     * contra el producto con exactamente el mismo criterio antes de agregar la línea.
     *
     * @throws VarianteRequeridaException
     */
    public function resolverVariante(Product $product, ?int $varianteId, bool $exigirVariante = false): ?ProductVariant
    {
        if ($varianteId === null) {
            if ($exigirVariante && $this->variantesActivas($product)->isNotEmpty()) {
                throw VarianteRequeridaException::faltante($product->id);
            }

            return null;
        }

        $variante = $this->variantesActivas($product)->firstWhere('id', $varianteId);

        if (! $variante) {
            throw VarianteRequeridaException::varianteNoDisponible($product->id, $varianteId);
        }

        return $variante;
    }

    /**
     * Resuelve los add-ons elegidos, validando que cada id sea un add-on activo
     * ofrecido por el producto. Devuelve los modelos en el orden en que vinieron
     * los ids (ignorando duplicados).
     *
     * Público por el mismo motivo que `resolverVariante`: el carrito valida los
     * add-ons elegidos contra el producto antes de agregar la línea.
     *
     * @param  array<int, int>  $addonIds
     * @return array<int, Addon>
     *
     * @throws VarianteRequeridaException
     */
    public function resolverAddons(Product $product, array $addonIds): array
    {
        $addonIds = array_values(array_unique(array_filter(
            array_map('intval', $addonIds),
            fn (int $id) => $id > 0,
        )));

        if ($addonIds === []) {
            return [];
        }

        $disponibles = $this->addonsActivos($product)->keyBy('id');

        return array_map(function (int $addonId) use ($product, $disponibles): Addon {
            $addon = $disponibles->get($addonId);

            if (! $addon) {
                throw VarianteRequeridaException::addonNoDisponible($product->id, $addonId);
            }

            return $addon;
        }, $addonIds);
    }

    /**
     * Precio efectivo de un add-on para este producto: `price_override` del pivote
     * si lo hay, si no el `price` global del catálogo.
     */
    private function precioAddon(Addon $addon): float
    {
        $override = $addon->pivot?->price_override;

        return round((float) ($override ?? $addon->price), 2);
    }

    /**
     * Variantes activas del producto. Resuelve en memoria si `variants` ya viene
     * eager-loaded (catálogo); si no, usa el accessor `variantsActive` que lazy-carga
     * y cachea en el modelo — como máximo una query, mismo criterio que `ofertaActiva`.
     *
     * @return Collection<int, ProductVariant>
     */
    private function variantesActivas(Product $product): Collection
    {
        if ($product->relationLoaded('variants')) {
            return $product->variants->where('is_active', true)->values();
        }

        return $product->variantsActive;
    }

    /**
     * Add-ons activos ofrecidos por el producto. Mismo criterio de eager-load /
     * lazy-load-cacheado que `variantesActivas`.
     *
     * @return Collection<int, Addon>
     */
    private function addonsActivos(Product $product): Collection
    {
        if ($product->relationLoaded('addons')) {
            return $product->addons->where('is_active', true)->values();
        }

        return $product->addonsActive;
    }
}
