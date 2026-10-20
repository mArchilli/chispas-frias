<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * El número de WhatsApp de ventas sale de config('tienda.whatsapp') (← WHATSAPP_NUMERO) y llega
 * al front como prop compartida de Inertia; no está escrito en ningún componente.
 */
class TiendaWhatsappTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_valor_por_defecto_es_el_numero_actual(): void
    {
        $this->assertSame('5491178886833', config('tienda.whatsapp'));
    }

    public function test_se_comparte_a_todas_las_paginas_inertia(): void
    {
        $this->get('/')
            ->assertInertia(fn (Assert $page) => $page->where('tienda.whatsapp', '5491178886833'));

        config(['tienda.whatsapp' => '5491100000000']);

        foreach (['/', route('cart.index')] as $url) {
            $this->get($url)
                ->assertInertia(fn (Assert $page) => $page->where('tienda.whatsapp', '5491100000000'));
        }
    }

    public function test_ningun_archivo_del_front_ni_del_back_tiene_el_numero_escrito(): void
    {
        $archivos = Finder::create()
            ->files()
            ->in([base_path('resources'), base_path('app'), base_path('routes')])
            ->name(['*.jsx', '*.js', '*.php']);

        $conNumero = [];

        foreach ($archivos as $archivo) {
            // El número tal cual o con formato (+54 9 11 7888-6833, 54-9-11-7888-6833…).
            if (preg_match('/549\D?11\D?7888\D?6833|7888\D?6833/', $archivo->getContents())) {
                $conNumero[] = $archivo->getRelativePathname();
            }
        }

        $this->assertSame([], $conNumero, 'El número de WhatsApp se lee de config/tienda.php, no se escribe en el código.');
    }
}
