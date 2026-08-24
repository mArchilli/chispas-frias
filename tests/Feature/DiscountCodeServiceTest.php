<?php

namespace Tests\Feature;

use App\Exceptions\DiscountCodeInvalidoException;
use App\Models\DiscountCode;
use App\Services\DiscountCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DiscountCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    private DiscountCodeService $discountCodeService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->discountCodeService = app(DiscountCodeService::class);
    }

    private function crearCodigo(array $overrides = []): DiscountCode
    {
        return DiscountCode::create(array_merge([
            'code' => strtoupper(Str::random(10)),
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

    // --- buscarValido ----------------------------------------------------------

    public function test_buscar_valido_acepta_un_codigo_vigente(): void
    {
        $discountCode = $this->crearCodigo(['code' => 'VERANO2026']);

        $resultado = $this->discountCodeService->buscarValido('VERANO2026', 1000);

        $this->assertTrue($resultado->is($discountCode));
    }

    public function test_buscar_valido_es_case_insensitive_y_hace_trim(): void
    {
        $discountCode = $this->crearCodigo(['code' => 'VERANO2026']);

        $resultado = $this->discountCodeService->buscarValido('  verano2026  ', 1000);

        $this->assertTrue($resultado->is($discountCode));
    }

    public function test_buscar_valido_rechaza_un_codigo_inexistente(): void
    {
        $this->expectException(DiscountCodeInvalidoException::class);
        $this->expectExceptionMessage('no existe');

        $this->discountCodeService->buscarValido('NOEXISTE', 1000);
    }

    public function test_buscar_valido_rechaza_un_codigo_inactivo(): void
    {
        $this->crearCodigo(['code' => 'INACTIVO', 'is_active' => false]);

        $this->expectException(DiscountCodeInvalidoException::class);
        $this->expectExceptionMessage('no está vigente');

        $this->discountCodeService->buscarValido('INACTIVO', 1000);
    }

    public function test_buscar_valido_rechaza_un_codigo_que_todavia_no_empezo(): void
    {
        $this->crearCodigo([
            'code' => 'FUTURO',
            'start_date' => now()->addDays(5),
        ]);

        $this->expectException(DiscountCodeInvalidoException::class);
        $this->expectExceptionMessage('no está vigente');

        $this->discountCodeService->buscarValido('FUTURO', 1000);
    }

    public function test_buscar_valido_rechaza_un_codigo_ya_vencido(): void
    {
        $this->crearCodigo([
            'code' => 'VENCIDO',
            'start_date' => now()->subDays(10),
            'end_date' => now()->subDay(),
        ]);

        $this->expectException(DiscountCodeInvalidoException::class);
        $this->expectExceptionMessage('no está vigente');

        $this->discountCodeService->buscarValido('VENCIDO', 1000);
    }

    public function test_buscar_valido_rechaza_un_codigo_agotado(): void
    {
        $this->crearCodigo([
            'code' => 'AGOTADO',
            'usage_limit' => 5,
            'usage_count' => 5,
        ]);

        $this->expectException(DiscountCodeInvalidoException::class);
        $this->expectExceptionMessage('límite de usos');

        $this->discountCodeService->buscarValido('AGOTADO', 1000);
    }

    public function test_buscar_valido_rechaza_un_codigo_cuando_el_subtotal_no_alcanza_el_minimo(): void
    {
        $this->crearCodigo([
            'code' => 'MINIMO100',
            'min_purchase_amount' => 100,
        ]);

        $this->expectException(DiscountCodeInvalidoException::class);
        $this->expectExceptionMessage('compra mínima');

        $this->discountCodeService->buscarValido('MINIMO100', 99.99);
    }

    public function test_buscar_valido_acepta_un_codigo_cuando_el_subtotal_alcanza_justo_el_minimo(): void
    {
        $discountCode = $this->crearCodigo([
            'code' => 'MINIMO100',
            'min_purchase_amount' => 100,
        ]);

        $resultado = $this->discountCodeService->buscarValido('MINIMO100', 100);

        $this->assertTrue($resultado->is($discountCode));
    }

    // --- calcularDescuento -------------------------------------------------------

    public function test_calcular_descuento_aplica_el_porcentaje_sobre_el_subtotal(): void
    {
        $discountCode = $this->crearCodigo(['percentage' => 10]);

        $monto = $this->discountCodeService->calcularDescuento($discountCode, 1000);

        $this->assertSame(100.0, $monto);
    }

    public function test_calcular_descuento_con_otro_porcentaje_y_subtotal(): void
    {
        $discountCode = $this->crearCodigo(['percentage' => 25]);

        $monto = $this->discountCodeService->calcularDescuento($discountCode, 250);

        $this->assertSame(62.5, $monto);
    }

    public function test_calcular_descuento_redondea_a_dos_decimales(): void
    {
        $discountCode = $this->crearCodigo(['percentage' => 15]);

        $monto = $this->discountCodeService->calcularDescuento($discountCode, 33.33);

        $this->assertSame(5.0, $monto);
    }

    public function test_calcular_descuento_con_porcentaje_decimal_redondea_correctamente(): void
    {
        $discountCode = $this->crearCodigo(['percentage' => 12.5]);

        $monto = $this->discountCodeService->calcularDescuento($discountCode, 99.99);

        // 99.99 * 12.5 / 100 = 12.49875 -> redondeado a 12.5
        $this->assertSame(12.5, $monto);
    }

    // --- registrarUso --------------------------------------------------------------

    public function test_registrar_uso_incrementa_usage_count(): void
    {
        $discountCode = $this->crearCodigo(['usage_count' => 3]);

        $this->discountCodeService->registrarUso($discountCode);

        $discountCode->refresh();
        $this->assertSame(4, $discountCode->usage_count);
    }

    public function test_registrar_uso_falla_si_el_codigo_se_agoto_justo_antes_del_lock(): void
    {
        $discountCode = $this->crearCodigo(['usage_limit' => 1, 'usage_count' => 0]);

        // Simula una carrera: otra transacción concurrente ya consumió el único uso
        // disponible entre que este proceso leyó el código (buscarValido, en
        // memoria con usage_count=0) y que registrarUso() toma el lock.
        DB::table('discount_codes')->where('id', $discountCode->id)->update(['usage_count' => 1]);

        $this->expectException(DiscountCodeInvalidoException::class);
        $this->expectExceptionMessage('límite de usos');

        try {
            $this->discountCodeService->registrarUso($discountCode);
        } finally {
            $this->assertSame(1, DB::table('discount_codes')->where('id', $discountCode->id)->value('usage_count'));
        }
    }

    // --- reponerUso ------------------------------------------------------------

    public function test_reponer_uso_decrementa_usage_count(): void
    {
        $discountCode = $this->crearCodigo(['usage_count' => 3]);

        $this->discountCodeService->reponerUso($discountCode->id);

        $discountCode->refresh();
        $this->assertSame(2, $discountCode->usage_count);
    }

    public function test_reponer_uso_no_baja_de_cero(): void
    {
        $discountCode = $this->crearCodigo(['usage_count' => 0]);

        $this->discountCodeService->reponerUso($discountCode->id);

        $discountCode->refresh();
        $this->assertSame(0, $discountCode->usage_count);
    }
}
