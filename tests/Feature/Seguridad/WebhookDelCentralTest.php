<?php

namespace Tests\Feature\Seguridad;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Rukoz\CoreClient\Firma\FirmaRukoz;
use Rukoz\CoreClient\Inbox\EventoInbox;
use Tests\TestCase;

/**
 * El webhook entrante del Central (POST /rukoz/webhook), de punta a punta con la firma REAL del paquete (S.2 · B.1):
 *  - la firma se calcula sobre el BODY CRUDO: re-serializar el mismo JSON con otros bytes ya no vale;
 *  - se rechaza por la ventana de tiempo (±300 s), por key equivocada, por path distinto y por secreto desconocido;
 *  - durante una rotación acepta el secreto actual y el anterior;
 *  - un rechazo no guarda nada y lo que se loguea no trae el contenido ni los datos del cliente;
 *  - sin sesión ni CSRF (no pasa por el grupo web).
 */
class WebhookDelCentralTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'rk_0123456789abcdef0123456789abcdef';

    private const SECRETO = 'secreto-actual-de-prueba';

    private ?string $envAnterior = null;

    protected function setUp(): void
    {
        // La integración se enciende ANTES de arrancar la app: el paquete registra la ruta del webhook al bootear.
        $this->envAnterior = getenv('RUKOZ_INTEGRACION') === false ? null : (string) getenv('RUKOZ_INTEGRACION');
        putenv('RUKOZ_INTEGRACION=true');
        $_ENV['RUKOZ_INTEGRACION'] = $_SERVER['RUKOZ_INTEGRACION'] = 'true';

        parent::setUp();

        config([
            'rukoz.habilitada' => true,
            'rukoz.url' => 'https://central.ejemplo.test',
            'rukoz.api_key' => self::KEY,
            'rukoz.secret' => self::SECRETO,
            'rukoz.secret_anterior' => null,
            'rukoz.log_channel' => 'rukoz_prueba',
            'logging.channels.rukoz_prueba' => ['driver' => 'monolog', 'handler' => TestHandler::class],
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->envAnterior === null ? putenv('RUKOZ_INTEGRACION') : putenv('RUKOZ_INTEGRACION='.$this->envAnterior);
        unset($_ENV['RUKOZ_INTEGRACION'], $_SERVER['RUKOZ_INTEGRACION']);
    }

    /** Un evento con datos de cliente para comprobar que NO terminan en los logs. */
    private function cuerpo(string $id = 'evt-0001'): string
    {
        return json_encode([
            'id' => $id,
            'evento' => 'ping',
            'secuencia' => 1,
            'creado_at' => now()->toIso8601String(),
            'datos' => ['cliente' => ['nombre' => 'Juan Pérez', 'email' => 'juan.perez@ejemplo.com', 'dni' => '30123456', 'telefono' => '+54 9 11 5555-6666', 'direccion' => 'Calle Falsa 123']],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @param  array<string, string|null>  $cabeceras */
    private function enviar(string $cuerpo, array $cabeceras = [], string $uri = '/rukoz/webhook', string $secreto = self::SECRETO, ?int $timestamp = null, string $pathFirmado = '/rukoz/webhook')
    {
        $timestamp ??= now()->getTimestamp();

        $server = array_merge([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RUKOZ_KEY' => self::KEY,
            'HTTP_X_RUKOZ_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_RUKOZ_SIGNATURE' => FirmaRukoz::firmar($secreto, $timestamp, 'POST', $pathFirmado, $cuerpo),
        ], $cabeceras);

        return $this->call('POST', $uri, [], [], [], array_filter($server, fn ($v) => $v !== null), $cuerpo);
    }

    private function registros(): array
    {
        $handler = Log::channel('rukoz_prueba')->getLogger()->getHandlers()[0];

        return $handler->getRecords();
    }

    public function test_una_firma_valida_sobre_el_body_crudo_se_acepta_y_se_guarda(): void
    {
        $this->enviar($this->cuerpo())->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(1, EventoInbox::count());
    }

    public function test_el_mismo_evento_dos_veces_no_se_duplica(): void
    {
        $this->enviar($this->cuerpo())->assertOk();
        $this->enviar($this->cuerpo())->assertOk()->assertJson(['ok' => true, 'duplicado' => true]);

        $this->assertSame(1, EventoInbox::count());
    }

    public function test_la_firma_es_sobre_los_bytes_crudos_el_mismo_json_re_serializado_no_vale(): void
    {
        $original = $this->cuerpo();
        $firmaDelOriginal = FirmaRukoz::firmar(self::SECRETO, now()->getTimestamp(), 'POST', '/rukoz/webhook', $original);

        // Mismo contenido semántico, otros bytes (espacios y orden de claves).
        $reserializado = json_encode(array_reverse(json_decode($original, true), true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertNotSame($original, $reserializado);

        $this->enviar($reserializado, ['HTTP_X_RUKOZ_SIGNATURE' => $firmaDelOriginal])
            ->assertStatus(401)->assertExactJson(['ok' => false, 'codigo' => 'FIRMA_INVALIDA']);

        $this->assertSame(0, EventoInbox::count());
    }

    public function test_un_byte_cambiado_en_el_body_invalida_la_firma(): void
    {
        $cuerpo = $this->cuerpo();
        $firma = FirmaRukoz::firmar(self::SECRETO, now()->getTimestamp(), 'POST', '/rukoz/webhook', $cuerpo);

        $this->enviar(str_replace('Juan', 'Jose', $cuerpo), ['HTTP_X_RUKOZ_SIGNATURE' => $firma])->assertStatus(401);

        $this->assertSame(0, EventoInbox::count());
    }

    public function test_rechaza_fuera_de_la_ventana_de_tiempo_y_acepta_dentro(): void
    {
        $this->enviar($this->cuerpo('viejo'), timestamp: now()->getTimestamp() - 301)
            ->assertStatus(401)->assertExactJson(['ok' => false, 'codigo' => 'TIMESTAMP_VENCIDO']);

        $this->enviar($this->cuerpo('futuro'), timestamp: now()->getTimestamp() + 301)
            ->assertStatus(401)->assertJson(['codigo' => 'TIMESTAMP_VENCIDO']);

        $this->enviar($this->cuerpo('justo'), timestamp: now()->getTimestamp() - 290)->assertOk();

        $this->assertSame(1, EventoInbox::count());
    }

    public function test_un_replay_capturado_hace_rato_no_sirve_aunque_la_firma_sea_autentica(): void
    {
        $cuerpo = $this->cuerpo('capturado');
        $hace10min = now()->getTimestamp() - 600;

        $this->enviar($cuerpo, timestamp: $hace10min)->assertStatus(401)->assertJson(['codigo' => 'TIMESTAMP_VENCIDO']);
    }

    public function test_rechaza_key_equivocada_secreto_equivocado_y_path_distinto(): void
    {
        $this->enviar($this->cuerpo(), ['HTTP_X_RUKOZ_KEY' => 'rk_otra'])->assertStatus(401);
        $this->enviar($this->cuerpo(), secreto: 'secreto-de-un-atacante')->assertStatus(401);
        // La firma incluye el path con query: una firma hecha para otro path no vale acá.
        $this->enviar($this->cuerpo(), pathFirmado: '/api/v1/pedidos')->assertStatus(401);
        $this->enviar($this->cuerpo(), uri: '/rukoz/webhook?x=1')->assertStatus(401);
        // Sin cabeceras de firma.
        $this->enviar($this->cuerpo(), ['HTTP_X_RUKOZ_SIGNATURE' => null, 'HTTP_X_RUKOZ_KEY' => null, 'HTTP_X_RUKOZ_TIMESTAMP' => null])->assertStatus(401);

        $this->assertSame(0, EventoInbox::count());
    }

    public function test_sin_credenciales_configuradas_no_autentica_nada(): void
    {
        config(['rukoz.secret' => null, 'rukoz.secret_anterior' => null]);

        $this->enviar($this->cuerpo(), secreto: '')->assertStatus(401);
    }

    public function test_durante_la_rotacion_acepta_el_secreto_actual_y_el_anterior_y_despues_solo_el_nuevo(): void
    {
        config(['rukoz.secret' => 'secreto-NUEVO', 'rukoz.secret_anterior' => 'secreto-VIEJO']);

        $this->enviar($this->cuerpo('con-nuevo'), secreto: 'secreto-NUEVO')->assertOk();
        $this->enviar($this->cuerpo('con-viejo'), secreto: 'secreto-VIEJO')->assertOk();
        $this->enviar($this->cuerpo('con-otro'), secreto: 'secreto-ajeno')->assertStatus(401);

        // Pasadas las 24 h se borra RUKOZ_SECRET_ANTERIOR: el viejo deja de servir.
        config(['rukoz.secret_anterior' => null]);
        $this->enviar($this->cuerpo('viejo-tarde'), secreto: 'secreto-VIEJO')->assertStatus(401);

        $this->assertSame(2, EventoInbox::count());
    }

    public function test_rechaza_un_body_demasiado_grande_sin_leerlo(): void
    {
        config(['rukoz.webhook.max_bytes' => 1024]);

        $this->enviar(str_repeat('A', 2048))->assertStatus(413);

        $this->assertSame(0, EventoInbox::count());
    }

    public function test_no_pasa_por_sesion_ni_csrf_y_solo_acepta_post(): void
    {
        $ruta = app('router')->getRoutes()->getByName('rukoz.webhook');

        $this->assertNotContains('web', $ruta->gatherMiddleware());
        $this->assertSame(['POST'], $ruta->methods());

        // Un GET cae en el 404 de la tienda: el endpoint no revela nada.
        $this->get('/rukoz/webhook')->assertNotFound();
    }

    public function test_los_logs_de_un_rechazo_y_de_un_evento_aceptado_no_traen_datos_del_cliente(): void
    {
        $this->enviar($this->cuerpo('log-1'), secreto: 'otro')->assertStatus(401);
        $this->enviar($this->cuerpo('log-2'))->assertOk();
        $this->enviar($this->cuerpo('log-3'), timestamp: now()->getTimestamp() - 1000)->assertStatus(401);

        $volcado = json_encode(array_map(fn ($r) => ['m' => $r->message, 'c' => $r->context, 'x' => $r->extra], $this->registros()), JSON_UNESCAPED_UNICODE);

        $this->assertNotEmpty($this->registros());

        foreach (['Juan', 'Pérez', 'juan.perez@ejemplo.com', '30123456', '5555-6666', 'Calle Falsa', self::SECRETO, self::KEY] as $dato) {
            $this->assertStringNotContainsString($dato, (string) $volcado, "El log contiene «{$dato}»");
        }
    }

    public function test_responde_rapido(): void
    {
        $inicio = microtime(true);

        $this->enviar($this->cuerpo('rapido'))->assertOk();

        // En el hosting la base es otra, pero sin llamadas externas ni trabajo pesado el receptor tiene que contestar en una fracción de segundo.
        $this->assertLessThan(1.0, microtime(true) - $inicio);
    }
}
