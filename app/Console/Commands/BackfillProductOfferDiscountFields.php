<?php

namespace App\Console\Commands;

use App\Enums\AlcanceOferta;
use App\Enums\TipoDescuento;
use App\Models\ProductOffer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackfillProductOfferDiscountFields extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'offers:backfill-discount-fields';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Completa tipo_descuento, valor_descuento y alcance en ofertas existentes a partir de offer_price';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $updated = 0;

        ProductOffer::with('product')
            ->whereNull('tipo_descuento')
            ->each(function (ProductOffer $offer) use (&$updated) {
                $valorDescuento = max(0, round((float) $offer->product->price - (float) $offer->offer_price, 2));

                $offer->forceFill([
                    'tipo_descuento' => TipoDescuento::Fijo,
                    'valor_descuento' => $valorDescuento,
                    'alcance' => AlcanceOferta::Todos,
                    'product_price_tier_id' => null,
                ])->save();

                $updated++;
            });

        $this->info("Ofertas migradas: {$updated}");
        Log::info("offers:backfill-discount-fields — ofertas migradas: {$updated}");

        return self::SUCCESS;
    }
}
