<?php

namespace Database\Seeders;

use App\Models\CardPaymentPlan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CardPaymentPlanSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Planes actuales del negocio: recargo único e informativo sobre el total
     * del pedido, según la cantidad de cuotas de la tarjeta de crédito.
     */
    public function run(): void
    {
        $plans = [
            ['name' => '1 cuota', 'installments' => 1, 'surcharge_percentage' => 10.00, 'sort_order' => 1],
            ['name' => '3 cuotas', 'installments' => 3, 'surcharge_percentage' => 20.00, 'sort_order' => 2],
            ['name' => '6 cuotas', 'installments' => 6, 'surcharge_percentage' => 35.00, 'sort_order' => 3],
        ];

        foreach ($plans as $plan) {
            CardPaymentPlan::firstOrCreate(['name' => $plan['name']], $plan);
        }
    }
}
