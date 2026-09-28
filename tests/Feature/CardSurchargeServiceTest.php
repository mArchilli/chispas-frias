<?php

namespace Tests\Feature;

use App\Models\CardPaymentPlan;
use App\Services\CardSurchargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardSurchargeServiceTest extends TestCase
{
    use RefreshDatabase;

    private CardSurchargeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CardSurchargeService::class);
    }

    private function plan(array $overrides = []): CardPaymentPlan
    {
        return CardPaymentPlan::create(array_merge([
            'name' => 'Plan',
            'installments' => 3,
            'surcharge_percentage' => 20,
            'sort_order' => 0,
            'is_active' => true,
        ], $overrides));
    }

    public function test_calcula_el_recargo_como_cargo_unico_sobre_el_total(): void
    {
        $plan = $this->plan(['installments' => 3, 'surcharge_percentage' => 20]);

        $resultado = $this->service->calcular(10000, $plan);

        $this->assertSame(2000.0, $resultado['surcharge_amount']);
        $this->assertSame(12000.0, $resultado['total_with_surcharge']);
        $this->assertSame(4000.0, $resultado['installment_amount']);
    }

    public function test_una_sola_cuota_no_divide_el_total(): void
    {
        $plan = $this->plan(['installments' => 1, 'surcharge_percentage' => 10]);

        $resultado = $this->service->calcular(10000, $plan);

        $this->assertSame(1000.0, $resultado['surcharge_amount']);
        $this->assertSame(11000.0, $resultado['total_with_surcharge']);
        $this->assertSame(11000.0, $resultado['installment_amount']);
    }

    public function test_seis_cuotas_con_recargo_del_35_por_ciento(): void
    {
        $plan = $this->plan(['installments' => 6, 'surcharge_percentage' => 35]);

        $resultado = $this->service->calcular(10000, $plan);

        $this->assertSame(3500.0, $resultado['surcharge_amount']);
        $this->assertSame(13500.0, $resultado['total_with_surcharge']);
        $this->assertSame(2250.0, $resultado['installment_amount']);
    }

    public function test_redondea_todos_los_montos_a_dos_decimales(): void
    {
        $plan = $this->plan(['installments' => 3, 'surcharge_percentage' => 20]);

        $resultado = $this->service->calcular(99.99, $plan);

        // 99.99 * 20% = 19.998 -> 20.00 ; total 119.99 ; 119.99 / 3 = 39.9966 -> 40.00
        $this->assertSame(20.0, $resultado['surcharge_amount']);
        $this->assertSame(119.99, $resultado['total_with_surcharge']);
        $this->assertSame(40.0, $resultado['installment_amount']);
    }

    public function test_recargo_cero_deja_el_total_intacto(): void
    {
        $plan = $this->plan(['installments' => 2, 'surcharge_percentage' => 0]);

        $resultado = $this->service->calcular(5000, $plan);

        $this->assertSame(0.0, $resultado['surcharge_amount']);
        $this->assertSame(5000.0, $resultado['total_with_surcharge']);
        $this->assertSame(2500.0, $resultado['installment_amount']);
    }
}
