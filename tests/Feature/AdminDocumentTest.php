<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Enums\TipoDocumento;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminDocumentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Carpeta física de PDFs aislada por corrida, dentro de public/. El trait
     * StoresPublicUploads escribe siempre bajo public_path(), así que no se
     * puede fakear con Storage: se limpia a mano en tearDown.
     */
    private string $pdfPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdfPath = 'docs/test-' . uniqid();
        config(['documents.pdf_path' => $this->pdfPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(public_path($this->pdfPath));

        parent::tearDown();
    }

    private function crearDocumento(array $overrides = []): Document
    {
        return Document::create(array_merge([
            'title' => 'Documento ' . random_int(100000, 999999),
            'description' => null,
            'type' => TipoDocumento::Link,
            'url' => 'https://example.com/manual',
            'path' => null,
            'sort_order' => 0,
            'is_active' => true,
        ], $overrides));
    }

    private function pdfPathAbsoluto(string $filename): string
    {
        return public_path($this->pdfPath . '/' . $filename);
    }

    private function fakePdf(): UploadedFile
    {
        return UploadedFile::fake()->create('manual.pdf', 200, 'application/pdf');
    }

    // --- index: visibilidad por rol ---------------------------------------------

    public function test_el_admin_ve_documentos_activos_e_inactivos(): void
    {
        $admin = User::factory()->create();
        $activo = $this->crearDocumento(['title' => 'Activo', 'is_active' => true]);
        $inactivo = $this->crearDocumento(['title' => 'Inactivo', 'is_active' => false]);

        $this->actingAs($admin)->get(route('admin.documents.index'))->assertInertia(
            fn (Assert $page) => $page
                ->component('Admin/Documents/Index')
                ->where('canManage', true)
                ->has('documents', 2)
                ->where('documents.0.id', $activo->id)
                ->where('documents.1.id', $inactivo->id)
        );
    }

    public function test_el_vendedor_solo_ve_los_documentos_activos(): void
    {
        $vendedor = User::factory()->create(['role' => RolUsuario::Vendedor]);
        $activo = $this->crearDocumento(['title' => 'Visible', 'is_active' => true]);
        $this->crearDocumento(['title' => 'Oculto', 'is_active' => false]);

        $this->actingAs($vendedor)->get(route('admin.documents.index'))->assertInertia(
            fn (Assert $page) => $page
                ->component('Admin/Documents/Index')
                ->where('canManage', false)
                ->has('documents', 1)
                ->where('documents.0.id', $activo->id)
        );
    }

    public function test_desactivar_un_documento_lo_oculta_del_vendedor_sin_borrarlo(): void
    {
        $admin = User::factory()->create();
        $vendedor = User::factory()->create(['role' => RolUsuario::Vendedor]);
        $documento = $this->crearDocumento(['is_active' => true]);

        $this->actingAs($admin)->patch(route('admin.documents.toggle-status', $documento))->assertRedirect();

        // Sigue existiendo en base...
        $this->assertDatabaseHas('documents', ['id' => $documento->id, 'is_active' => false]);

        // ...pero el vendedor ya no lo ve.
        $this->actingAs($vendedor)->get(route('admin.documents.index'))->assertInertia(
            fn (Assert $page) => $page->has('documents', 0)
        );

        // ...y el admin sí.
        $this->actingAs($admin)->get(route('admin.documents.index'))->assertInertia(
            fn (Assert $page) => $page->has('documents', 1)->where('documents.0.is_active', false)
        );
    }

    // --- vendedor: sin acceso a la gestión --------------------------------------

    public function test_un_vendedor_no_puede_acceder_a_ninguna_ruta_de_gestion_de_documentos(): void
    {
        $vendedor = User::factory()->create(['role' => RolUsuario::Vendedor]);
        $documento = $this->crearDocumento();

        $this->actingAs($vendedor)->get(route('admin.documents.create'))->assertForbidden();
        $this->actingAs($vendedor)->post(route('admin.documents.store'), [
            'title' => 'Intento',
            'type' => 'link',
            'url' => 'https://example.com/x',
        ])->assertForbidden();
        $this->actingAs($vendedor)->get(route('admin.documents.edit', $documento))->assertForbidden();
        $this->actingAs($vendedor)->put(route('admin.documents.update', $documento), [
            'title' => 'Hackeado',
            'type' => 'link',
            'url' => 'https://example.com/x',
        ])->assertForbidden();
        $this->actingAs($vendedor)->delete(route('admin.documents.destroy', $documento))->assertForbidden();
        $this->actingAs($vendedor)->patch(route('admin.documents.toggle-status', $documento))->assertForbidden();

        $this->assertDatabaseMissing('documents', ['title' => 'Intento']);
        $this->assertDatabaseMissing('documents', ['title' => 'Hackeado']);
        $this->assertDatabaseHas('documents', ['id' => $documento->id]);
    }

    public function test_un_cliente_no_puede_ni_ver_el_listado(): void
    {
        $cliente = User::factory()->create(['role' => RolUsuario::Cliente]);

        $this->actingAs($cliente)->get(route('admin.documents.index'))->assertForbidden();
    }

    // --- admin: ABM completo ---------------------------------------------------

    public function test_admin_puede_crear_un_documento_tipo_link(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.documents.store'), [
            'title' => 'Instructivo de venta',
            'description' => 'Cómo cerrar una venta',
            'type' => 'link',
            'url' => 'https://example.com/instructivo',
            'sort_order' => 3,
            'is_active' => true,
        ])->assertRedirect(route('admin.documents.index'));

        $documento = Document::firstOrFail();
        $this->assertSame('Instructivo de venta', $documento->title);
        $this->assertSame(TipoDocumento::Link, $documento->type);
        $this->assertSame('https://example.com/instructivo', $documento->url);
        $this->assertNull($documento->path);
        $this->assertSame(3, $documento->sort_order);
    }

    public function test_admin_puede_crear_un_documento_tipo_pdf_y_se_guarda_el_archivo(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.documents.store'), [
            'title' => 'Manual PDF',
            'type' => 'pdf',
            'file' => $this->fakePdf(),
            'is_active' => true,
        ])->assertRedirect(route('admin.documents.index'));

        $documento = Document::firstOrFail();
        $this->assertSame(TipoDocumento::Pdf, $documento->type);
        $this->assertNull($documento->url);
        $this->assertNotNull($documento->path);
        $this->assertFileExists($this->pdfPathAbsoluto($documento->path));
    }

    public function test_crear_pdf_sin_archivo_falla_la_validacion(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.documents.store'), [
            'title' => 'Sin archivo',
            'type' => 'pdf',
            'is_active' => true,
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, Document::count());
    }

    public function test_crear_link_sin_url_falla_la_validacion(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.documents.store'), [
            'title' => 'Sin url',
            'type' => 'link',
            'is_active' => true,
        ])->assertSessionHasErrors('url');

        $this->assertSame(0, Document::count());
    }

    public function test_admin_puede_editar_un_documento(): void
    {
        $admin = User::factory()->create();
        $documento = $this->crearDocumento(['title' => 'Viejo', 'url' => 'https://example.com/viejo']);

        $this->actingAs($admin)->put(route('admin.documents.update', $documento), [
            'title' => 'Nuevo',
            'type' => 'link',
            'url' => 'https://example.com/nuevo',
            'sort_order' => 5,
            'is_active' => true,
        ])->assertRedirect(route('admin.documents.index'));

        $documento->refresh();
        $this->assertSame('Nuevo', $documento->title);
        $this->assertSame('https://example.com/nuevo', $documento->url);
        $this->assertSame(5, $documento->sort_order);
    }

    public function test_admin_puede_activar_y_desactivar_un_documento(): void
    {
        $admin = User::factory()->create();
        $documento = $this->crearDocumento(['is_active' => true]);

        $this->actingAs($admin)->patch(route('admin.documents.toggle-status', $documento))->assertRedirect();
        $this->assertFalse($documento->fresh()->is_active);

        $this->actingAs($admin)->patch(route('admin.documents.toggle-status', $documento))->assertRedirect();
        $this->assertTrue($documento->fresh()->is_active);
    }

    // --- borrado del archivo físico ------------------------------------------------

    public function test_cambiar_un_pdf_a_link_borra_el_archivo_fisico_viejo(): void
    {
        $admin = User::factory()->create();

        // Alta como PDF.
        $this->actingAs($admin)->post(route('admin.documents.store'), [
            'title' => 'Pasa a link',
            'type' => 'pdf',
            'file' => $this->fakePdf(),
            'is_active' => true,
        ])->assertRedirect();

        $documento = Document::firstOrFail();
        $pathViejo = $this->pdfPathAbsoluto($documento->path);
        $this->assertFileExists($pathViejo);

        // Update a tipo link.
        $this->actingAs($admin)->put(route('admin.documents.update', $documento), [
            'title' => 'Pasa a link',
            'type' => 'link',
            'url' => 'https://example.com/ahora-es-link',
            'is_active' => true,
        ])->assertRedirect(route('admin.documents.index'));

        $documento->refresh();
        $this->assertSame(TipoDocumento::Link, $documento->type);
        $this->assertNull($documento->path);
        $this->assertSame('https://example.com/ahora-es-link', $documento->url);
        $this->assertFileDoesNotExist($pathViejo);
    }

    public function test_reemplazar_el_pdf_borra_el_archivo_anterior(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.documents.store'), [
            'title' => 'Con reemplazo',
            'type' => 'pdf',
            'file' => $this->fakePdf(),
            'is_active' => true,
        ])->assertRedirect();

        $documento = Document::firstOrFail();
        $pathViejo = $this->pdfPathAbsoluto($documento->path);
        $this->assertFileExists($pathViejo);

        $this->actingAs($admin)->put(route('admin.documents.update', $documento), [
            'title' => 'Con reemplazo',
            'type' => 'pdf',
            'file' => $this->fakePdf(),
            'is_active' => true,
        ])->assertRedirect();

        $documento->refresh();
        $pathNuevo = $this->pdfPathAbsoluto($documento->path);

        $this->assertFileDoesNotExist($pathViejo);
        $this->assertFileExists($pathNuevo);
        $this->assertNotSame($pathViejo, $pathNuevo);
    }

    public function test_borrar_un_documento_pdf_elimina_el_archivo(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.documents.store'), [
            'title' => 'A borrar',
            'type' => 'pdf',
            'file' => $this->fakePdf(),
            'is_active' => true,
        ])->assertRedirect();

        $documento = Document::firstOrFail();
        $path = $this->pdfPathAbsoluto($documento->path);
        $this->assertFileExists($path);

        $this->actingAs($admin)->delete(route('admin.documents.destroy', $documento))
            ->assertRedirect(route('admin.documents.index'));

        $this->assertSame(0, Document::count());
        $this->assertFileDoesNotExist($path);
    }
}
