<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TipoDocumento;
use App\Http\Controllers\Admin\Concerns\StoresPublicUploads;
use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    use StoresPublicUploads;

    /**
     * Listado de documentos (manuales / instructivos) para vendedores.
     *
     * Accesible para cualquiera que pase 'acceder-panel-admin' (admin y
     * vendedor). El vendedor sólo ve los activos — misma visibilidad reducida
     * que ya se aplica en otras vistas del panel; el admin ve todos y puede
     * gestionarlos (Gate 'gestionar-documentos', enforced en las rutas).
     */
    public function index(Request $request): Response
    {
        $puedeGestionar = $request->user()->isAdmin();

        $documents = Document::query()
            ->when(! $puedeGestionar, fn ($query) => $query->active())
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(fn (Document $document) => $this->transform($document));

        return Inertia::render('Admin/Documents/Index', [
            'documents' => $documents,
            'canManage' => $puedeGestionar,
        ]);
    }

    /**
     * Formulario de alta.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Documents/Create', [
            'tipos' => $this->tiposDocumento(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $esLink = $data['type'] === TipoDocumento::Link->value;

        Document::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'url' => $esLink ? $data['url'] : null,
            'path' => $esLink ? null : $this->guardarPdf($request->file('file')),
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.documents.index')->with('success', 'Documento creado exitosamente.');
    }

    /**
     * Formulario de edición.
     */
    public function edit(Document $document): Response
    {
        return Inertia::render('Admin/Documents/Edit', [
            'document' => $this->transform($document),
            'tipos' => $this->tiposDocumento(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * El archivo físico viejo se borra cuando el PDF se reemplaza por otro o
     * cuando el documento pasa de tipo pdf a tipo link.
     */
    public function update(Request $request, Document $document): RedirectResponse
    {
        $data = $this->validated($request, $document);
        $esLink = $data['type'] === TipoDocumento::Link->value;

        $path = $document->path;

        if ($esLink) {
            if ($document->path) {
                $this->borrarPdf($document->path);
            }
            $path = null;
        } elseif ($request->hasFile('file')) {
            $path = $this->guardarPdf($request->file('file'));
            if ($document->path) {
                $this->borrarPdf($document->path);
            }
        }

        $document->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'url' => $esLink ? $data['url'] : null,
            'path' => $path,
            'sort_order' => $data['sort_order'] ?? $document->sort_order,
            'is_active' => $request->boolean('is_active', $document->is_active),
        ]);

        return redirect()->route('admin.documents.index')->with('success', 'Documento actualizado exitosamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Document $document): RedirectResponse
    {
        if ($document->type === TipoDocumento::Pdf && $document->path) {
            $this->borrarPdf($document->path);
        }

        $document->delete();

        return redirect()->route('admin.documents.index')->with('success', 'Documento eliminado exitosamente.');
    }

    /**
     * Toggle document status
     */
    public function toggleStatus(Document $document): RedirectResponse
    {
        $document->update(['is_active' => ! $document->is_active]);

        $estado = $document->is_active ? 'activado' : 'desactivado';

        return back()->with('success', "Documento {$estado} exitosamente.");
    }

    /**
     * Reglas de validación compartidas por store/update. En pdf el archivo es
     * obligatorio salvo que el documento ya tenga uno cargado (edición sin
     * reemplazar); en link se exige la URL y no se sube nada.
     */
    private function validated(Request $request, ?Document $document = null): array
    {
        $fileRequerido = $request->input('type') === TipoDocumento::Pdf->value
            && ! ($document?->path);

        return $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:2000',
            'type' => ['required', Rule::enum(TipoDocumento::class)],
            'url' => ['nullable', 'required_if:type,link', 'url', 'max:2000'],
            'file' => [$fileRequerido ? 'required' : 'nullable', 'file', 'mimetypes:application/pdf', 'max:20480'],
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);
    }

    /**
     * Sube el PDF a public_path(config('documents.pdf_path')) reutilizando el
     * mecanismo de 3 estrategias en cascada (trait StoresPublicUploads) y
     * devuelve el nombre del archivo para guardar en documents.path.
     */
    private function guardarPdf(UploadedFile $file): string
    {
        $nombre = 'documento_' . now()->timestamp . '_' . Str::random(8) . '.pdf';

        if (! $this->moverArchivoSubidoAPublic($file, $this->pdfDir(), $nombre)) {
            throw ValidationException::withMessages([
                'file' => 'No se pudo guardar el archivo PDF. Intentá nuevamente.',
            ]);
        }

        return $nombre;
    }

    private function borrarPdf(string $path): void
    {
        $this->borrarArchivoPublic($this->pdfDir(), $path);
    }

    /**
     * Carpeta física donde viven los PDFs, dentro de public/.
     */
    private function pdfDir(): string
    {
        return rtrim(public_path(ltrim((string) config('documents.pdf_path'), '/')), DIRECTORY_SEPARATOR);
    }

    /**
     * URL pública del PDF de un documento (null si es tipo link o no tiene archivo).
     */
    private function fileUrl(Document $document): ?string
    {
        if ($document->type !== TipoDocumento::Pdf || ! $document->path) {
            return null;
        }

        return asset(trim((string) config('documents.pdf_path'), '/') . '/' . $document->path);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function tiposDocumento(): array
    {
        return collect(TipoDocumento::cases())
            ->map(fn (TipoDocumento $tipo) => ['value' => $tipo->value, 'label' => $tipo->label()])
            ->all();
    }

    private function transform(Document $document): array
    {
        return [
            'id' => $document->id,
            'title' => $document->title,
            'description' => $document->description,
            'type' => $document->type->value,
            'type_label' => $document->type->label(),
            'url' => $document->url,
            'path' => $document->path,
            'file_url' => $this->fileUrl($document),
            'sort_order' => $document->sort_order,
            'is_active' => $document->is_active,
        ];
    }
}
