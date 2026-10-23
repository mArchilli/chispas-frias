<?php

namespace Tests\Feature\Seguridad;

use App\Enums\RolUsuario;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\HtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HtmlSanitizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_conserva_lo_que_genera_quill(): void
    {
        $html = '<h2>Título</h2><p><strong>Negrita</strong> <em>cursiva</em> <u>sub</u> <s>tachado</s></p>'
            .'<ol><li data-list="ordered">uno</li></ol><ul><li data-list="bullet">dos</li></ul>'
            .'<p><span style="color: rgb(230, 0, 0);">rojo</span> <span style="background-color: #ffff00;">fondo</span></p>'
            .'<p class="ql-align-center ql-indent-1">centrado ñandú áéíóú</p>';

        $this->assertSame($html, HtmlSanitizer::limpiar($html));
    }

    public function test_los_links_seguros_se_conservan_con_rel_noopener(): void
    {
        $limpio = HtmlSanitizer::limpiar('<a href="https://ejemplo.com/x?a=1&amp;b=2" onclick="x()">link</a>');

        $this->assertStringContainsString('href="https://ejemplo.com/x?a=1&amp;b=2"', $limpio);
        $this->assertStringContainsString('rel="noopener noreferrer"', $limpio);
        $this->assertStringNotContainsString('onclick', $limpio);
    }

    #[DataProvider('ataques')]
    public function test_neutraliza_los_vectores_de_xss(string $entrada, array $noDebeContener): void
    {
        $limpio = HtmlSanitizer::limpiar($entrada);

        foreach ($noDebeContener as $fragmento) {
            $this->assertStringNotContainsStringIgnoringCase($fragmento, $limpio, "Quedó «{$fragmento}» en: {$limpio}");
        }
    }

    public static function ataques(): array
    {
        return [
            'script' => ['<p>hola</p><script>alert(1)</script>', ['<script', 'alert(1)']],
            'img onerror' => ['<img src=x onerror="alert(1)">', ['<img', 'onerror', 'alert']],
            'svg onload' => ['<svg onload=alert(1)><circle/></svg>', ['<svg', 'onload', 'alert']],
            'iframe' => ['<iframe src="https://evil.com"></iframe>', ['<iframe', 'evil.com']],
            'javascript href' => ['<a href="javascript:alert(1)">x</a>', ['javascript:', 'href']],
            'javascript href con tab y entidades' => ['<a href="jav&#x09;ascript:alert(1)">x</a>', ['javascript', 'href']],
            'javascript href con mayúsculas' => ['<a href="JaVaScRiPt:alert(1)">x</a>', ['script:', 'href']],
            'data href' => ['<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>', ['data:', 'href']],
            'vbscript href' => ['<a href="vbscript:msgbox(1)">x</a>', ['vbscript', 'href']],
            'on* en p' => ['<p onmouseover="alert(1)" style="color:red">x</p>', ['onmouseover', 'alert']],
            'style con url' => ['<span style="background-color: url(javascript:alert(1))">x</span>', ['url(', 'javascript']],
            'style con expression' => ['<span style="color: expression(alert(1))">x</span>', ['expression']],
            'style con position' => ['<span style="position:fixed;top:0;left:0;color:red">x</span>', ['position', 'fixed']],
            'style tag' => ['<style>body{display:none}</style><p>x</p>', ['<style', 'display']],
            'form' => ['<form action="https://evil.com"><input name=a></form>', ['<form', 'evil.com', '<input']],
            'comentario condicional' => ['<!--[if IE]><script>alert(1)</script><![endif]-->', ['<script', 'alert']],
            'etiqueta rota' => ['<scr<script>ipt>alert(1)</scr</script>ipt>', ['<script']],
            'clase no ql' => ['<p class="fixed inset-0 z-50">x</p>', ['fixed', 'inset-0']],
            'meta refresh' => ['<meta http-equiv="refresh" content="0;url=https://evil.com">', ['<meta', 'evil.com']],
            'math' => ['<math><mi xlink:href="javascript:alert(1)">x</mi></math>', ['javascript', 'alert']],
        ];
    }

    public function test_texto_sin_etiquetas_ni_entidades_queda_igual_y_null_se_respeta(): void
    {
        $this->assertSame('Solo texto, sin HTML.', HtmlSanitizer::limpiar('Solo texto, sin HTML.'));
        $this->assertNull(HtmlSanitizer::limpiar(null));
        $this->assertSame('', HtmlSanitizer::limpiar(''));
    }

    public function test_la_descripcion_del_producto_se_limpia_al_guardar_y_al_leer(): void
    {
        $categoria = Category::factory()->create();

        $producto = Product::create([
            'title' => 'Chispero',
            'description' => '<p>ok</p><script>alert(1)</script><img src=x onerror=alert(2)>',
            'price' => 100,
            'sku' => 'SEG-1',
            'category_id' => $categoria->id,
            'is_active' => true,
        ]);

        // Lo que quedó en la base ya está limpio...
        $this->assertSame('<p>ok</p>', $producto->getRawOriginal('description'));

        // ...y si una fila vieja o escrita a mano trae HTML sucio, el modelo igual lo limpia al leer.
        DB::table('products')->where('id', $producto->id)->update(['description' => '<p>ok</p><script>alert(1)</script>']);

        $this->assertSame('<p>ok</p>', $producto->fresh()->description);
    }

    public function test_el_admin_no_puede_guardar_js_en_la_descripcion(): void
    {
        $admin = User::factory()->create(['role' => RolUsuario::Admin]);
        $categoria = Category::factory()->create();
        $producto = Product::factory()->create(['category_id' => $categoria->id, 'description' => '<p>x</p>']);

        $this->actingAs($admin)->put(route('admin.products.update', $producto), [
            'title' => 'Con XSS',
            'description' => '<p>ok</p><img src=x onerror=alert(1)><a href="javascript:alert(2)">x</a>',
            'price' => 100,
            'sku' => $producto->sku,
            'category_id' => $categoria->id,
            'stock' => 10,
            'is_active' => true,
        ]);

        $guardada = $producto->fresh()->getRawOriginal('description');

        // Que el update realmente se aplicó (no quedó la descripción vieja por un error de validación).
        $this->assertStringContainsString('<p>ok</p>', $guardada);
        $this->assertStringNotContainsString('onerror', $guardada);
        $this->assertStringNotContainsString('javascript:', $guardada);
    }
}
