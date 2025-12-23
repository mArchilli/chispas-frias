<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    /**
     * Display a listing of products.
     */
    public function index(Request $request): Response
    {
        $search = $request->get('search');
        $categoryFilter = $request->get('category');
        $statusFilter = $request->get('status');
        $stockFilter = $request->get('stock');

        $query = Product::with(['category.parent', 'images']);

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($categoryFilter) {
            $query->where('category_id', $categoryFilter);
        }

        if ($statusFilter !== null) {
            if ($statusFilter === 'active') {
                $query->where('is_active', true);
            } elseif ($statusFilter === 'inactive') {
                $query->where('is_active', false);
            } elseif ($statusFilter === 'featured') {
                $query->where('is_featured', true);
            }
        }

        if ($stockFilter !== null) {
            if ($stockFilter === 'in_stock') {
                $query->where('stock', '>', 0);
            } elseif ($stockFilter === 'out_of_stock') {
                $query->where('stock', '<=', 0);
            }
        }

        $products = $query->orderByDesc('created_at')
            ->paginate(15)
            ->through(function ($product) {
                return [
                    'id' => $product->id,
                    'title' => $product->title,
                    'description' => $product->description,
                    'price' => $product->price,
                    'formatted_price' => $product->formatted_price,
                    'sku' => $product->sku,
                    'stock' => $product->stock,
                    'category' => [
                        'id' => $product->category->id,
                        'name' => $product->category->name,
                        'parent_name' => $product->category->parent?->name
                    ],
                    'primary_image' => $product->primaryImage()?->path,
                    'images_count' => $product->images->count(),
                    'is_active' => $product->is_active,
                    'is_featured' => $product->is_featured,
                    'in_stock' => $product->isInStock(),
                    'created_at' => $product->created_at->format('d/m/Y H:i'),
                    'updated_at' => $product->updated_at->format('d/m/Y H:i'),
                ];
            });

        $categories = Category::active()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'categories' => $categories,
            'filters' => [
                'search' => $search ?? '',
                'category' => $categoryFilter ?? '',
                'status' => $statusFilter ?? '',
                'stock' => $stockFilter ?? '',
            ]
        ]);
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(): Response
    {
        $categories = Category::with('parent')
            ->active()
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->parent 
                        ? $category->parent->name . ' → ' . $category->name
                        : $category->name,
                    'is_subcategory' => !is_null($category->parent_id)
                ];
            });

        return Inertia::render('Admin/Products/Create', [
            'categories' => $categories
        ]);
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        Log::info('=== STORE METHOD CALLED ===');
        Log::info('Request method: ' . $request->method());
        Log::info('Content type: ' . $request->header('Content-Type'));
        Log::info('Has files: ' . ($request->hasFile('images') ? 'YES' : 'NO'));
        Log::info('All files in request: ', $request->allFiles());
        Log::info('All input data: ', $request->all());
        
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0|max:999999.99',
            'sku' => 'nullable|string|max:255|unique:products,sku',
            'category_id' => 'required|exists:categories,id',
            'stock' => 'required|integer|min:0',
            'is_active' => 'nullable',
            'is_featured' => 'nullable',
            'images' => 'nullable|array|max:10',
            'images.*' => 'file|mimes:jpeg,png,jpg,gif,webp,mp4,mov,avi,wmv,flv,webm|max:20480'
        ]);

        // Convert checkbox values properly
        $validated['is_active'] = $request->input('is_active', '0') === '1';
        $validated['is_featured'] = $request->input('is_featured', '0') === '1';

        $product = Product::create($validated);

        // Handle image uploads
        if ($request->hasFile('images')) {
            $files = $request->file('images');
            Log::info('IMAGES RECEIVED: ' . count($files) . ' files');
            
            $imagesPath = 'images/products';
            $fullPath = public_path($imagesPath);
            
            // Crear directorio si no existe
            if (!file_exists($fullPath)) {
                mkdir($fullPath, 0755, true);
            }
            
            foreach ($files as $index => $file) {
                // Verificar que el archivo sea válido y esté disponible
                if (!$file || !$file->isValid()) {
                    Log::warning("File at index {$index} is not valid or not available");
                    continue;
                }
                
                // Verificar que el archivo temporal existe
                $tempPath = $file->getRealPath();
                if (!$tempPath || !file_exists($tempPath)) {
                    Log::error("Temporary file does not exist: " . ($tempPath ?: 'path is null'));
                    continue;
                }
                
                // IMPORTANTE: Obtener MIME type ANTES de mover el archivo
                try {
                    $mimeType = $file->getMimeType();
                } catch (\Exception $e) {
                    // Fallback: usar extensión para determinar MIME type
                    $extension = strtolower($file->getClientOriginalExtension());
                    $mimeType = match($extension) {
                        'jpg', 'jpeg' => 'image/jpeg',
                        'png' => 'image/png',
                        'gif' => 'image/gif',
                        'webp' => 'image/webp',
                        'mp4' => 'video/mp4',
                        'mov' => 'video/quicktime',
                        'avi' => 'video/x-msvideo',
                        'wmv' => 'video/x-ms-wmv',
                        'flv' => 'video/x-flv',
                        'webm' => 'video/webm',
                        default => 'application/octet-stream'
                    };
                    Log::warning("Could not get MIME type from file, using extension-based fallback: {$mimeType}");
                }
                $type = strpos($mimeType, 'video') !== false ? 'video' : 'image';
                
                // Generar nombre único para el archivo
                $extension = $file->getClientOriginalExtension();
                $fileName = time() . '_' . $index . '_' . uniqid() . '.' . $extension;
                
                try {
                    // Intentar mover el archivo usando el método nativo primero
                    $targetPath = $fullPath . DIRECTORY_SEPARATOR . $fileName;
                    
                    // Método 1: Usar move (método estándar de Laravel)
                    if (method_exists($file, 'move')) {
                        $file->move($fullPath, $fileName);
                        $relativePath = '/' . $imagesPath . '/' . $fileName;
                        Log::info("File moved successfully using move() to: {$relativePath}");
                    }
                    // Método 2: Usar copy como fallback
                    else if (copy($tempPath, $targetPath)) {
                        $relativePath = '/' . $imagesPath . '/' . $fileName;
                        Log::info("File copied successfully to: {$relativePath}");
                        
                        // Limpiar archivo temporal
                        @unlink($tempPath);
                    } else {
                        Log::error("Failed to copy file from {$tempPath} to {$targetPath}");
                        continue;
                    }
                } catch (\Exception $e) {
                    // Método 3: Fallback usando file_get_contents/file_put_contents
                    try {
                        Log::warning("Standard move failed, trying alternative method: " . $e->getMessage());
                        
                        $fileContent = file_get_contents($tempPath);
                        if ($fileContent !== false) {
                            $targetPath = $fullPath . DIRECTORY_SEPARATOR . $fileName;
                            if (file_put_contents($targetPath, $fileContent)) {
                                $relativePath = '/' . $imagesPath . '/' . $fileName;
                                Log::info("File saved using alternative method to: {$relativePath}");
                                
                                // Limpiar archivo temporal
                                @unlink($tempPath);
                            } else {
                                Log::error("Failed to save file using alternative method");
                                continue;
                            }
                        } else {
                            Log::error("Could not read temporary file content");
                            continue;
                        }
                    } catch (\Exception $fallbackException) {
                        Log::error("All file handling methods failed: " . $fallbackException->getMessage());
                        continue;
                    }
                }
                
                try {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'path' => $relativePath,
                        'alt_text' => $product->title,
                        'sort_order' => $index + 1,
                        'is_primary' => $index === 0,
                        'type' => $type,
                        'mime_type' => $mimeType
                    ]);
                    
                    Log::info("ProductImage created successfully");
                } catch (\Exception $e) {
                    Log::error("Error creating ProductImage: " . $e->getMessage());
                    continue;
                }
            }
        } else {
            Log::info('NO IMAGES RECEIVED IN REQUEST');
        }

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Producto creado exitosamente.');
    }

    /**
     * Display the specified product.
     */
    public function show(Product $product): Response
    {
        $product->load(['category.parent', 'images' => function($query) {
            $query->orderBy('sort_order');
        }]);

        $productData = [
            'id' => $product->id,
            'title' => $product->title,
            'description' => $product->description,
            'price' => $product->price,
            'formatted_price' => $product->formatted_price,
            'sku' => $product->sku,
            'stock' => $product->stock,
            'category' => [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug,
                'parent' => $product->category->parent ? [
                    'id' => $product->category->parent->id,
                    'name' => $product->category->parent->name,
                    'slug' => $product->category->parent->slug
                ] : null
            ],
            'images' => $product->images->map(function ($image) {
                return [
                    'id' => $image->id,
                    'path' => $image->path,
                    'url' => $image->url,
                    'alt_text' => $image->alt_text,
                    'sort_order' => $image->sort_order,
                    'is_primary' => $image->is_primary,
                    'type' => $image->type,
                    'mime_type' => $image->mime_type
                ];
            }),
            'is_active' => $product->is_active,
            'is_featured' => $product->is_featured,
            'in_stock' => $product->isInStock(),
            'created_at' => $product->created_at->format('d/m/Y H:i'),
            'updated_at' => $product->updated_at->format('d/m/Y H:i'),
        ];

        return Inertia::render('Admin/Products/Show', [
            'product' => $productData
        ]);
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Product $product): Response
    {
        $product->load('images');

        $categories = Category::with('parent')
            ->active()
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->parent 
                        ? $category->parent->name . ' → ' . $category->name
                        : $category->name,
                    'is_subcategory' => !is_null($category->parent_id)
                ];
            });

        $productData = [
            'id' => $product->id,
            'title' => $product->title,
            'description' => $product->description,
            'price' => $product->price,
            'sku' => $product->sku,
            'category_id' => $product->category_id,
            'stock' => $product->stock,
            'is_active' => $product->is_active,
            'is_featured' => $product->is_featured,
            'images' => $product->images->map(function ($image) {
                return [
                    'id' => $image->id,
                    'path' => $image->path,
                    'url' => $image->url,
                    'alt_text' => $image->alt_text,
                    'sort_order' => $image->sort_order,
                    'is_primary' => $image->is_primary,
                    'type' => $image->type,
                    'mime_type' => $image->mime_type
                ];
            })
        ];

        return Inertia::render('Admin/Products/Edit', [
            'product' => $productData,
            'categories' => $categories
        ]);
    }

    /**
     * Update the specified product in storage.
     */
    public function update(Request $request, Product $product): RedirectResponse
    {
        Log::info('=== UPDATE METHOD CALLED ===');
        Log::info('Request method: ' . $request->method());
        Log::info('Has new_images files: ' . ($request->hasFile('new_images') ? 'YES' : 'NO'));
        Log::info('All files in request: ', $request->allFiles());
        Log::info('All input data: ', $request->all());
        
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0|max:999999.99',
            'sku' => 'nullable|string|max:255|unique:products,sku,' . $product->id,
            'category_id' => 'required|exists:categories,id',
            'stock' => 'required|integer|min:0',
            'is_active' => 'nullable',
            'is_featured' => 'nullable',
            'new_images' => 'nullable|array|max:10',
            'new_images.*' => 'file|mimes:jpeg,png,jpg,gif,webp,mp4,mov,avi,wmv,flv,webm|max:20480',
            'remove_images' => 'nullable|array',
            'remove_images.*' => 'integer|exists:product_images,id'
        ]);

        // Convert checkbox values properly
        $validated['is_active'] = $request->input('is_active', '0') === '1';
        $validated['is_featured'] = $request->input('is_featured', '0') === '1';

        // Remove specified images
        if ($request->has('remove_images')) {
            $imagesToRemove = ProductImage::whereIn('id', $request->remove_images)
                ->where('product_id', $product->id)
                ->get();
            
            foreach ($imagesToRemove as $image) {
                // Eliminar archivo físico del public disk
                if ($image->path && file_exists(public_path($image->path))) {
                    unlink(public_path($image->path));
                }
                $image->delete();
            }
        }

        // Add new images
        if ($request->hasFile('new_images')) {
            $files = $request->file('new_images');
            Log::info('NEW IMAGES RECEIVED: ' . count($files) . ' files');
            
            $existingImagesCount = $product->images()->count();
            $imagesPath = 'images/products';
            $fullPath = public_path($imagesPath);
            
            // Crear directorio si no existe
            if (!file_exists($fullPath)) {
                mkdir($fullPath, 0755, true);
            }
            
            foreach ($files as $index => $file) {
                // Verificar que el archivo sea válido y esté disponible
                if (!$file || !$file->isValid()) {
                    Log::warning("File at index {$index} is not valid or not available");
                    continue;
                }
                
                // Verificar que el archivo temporal existe
                $tempPath = $file->getRealPath();
                if (!$tempPath || !file_exists($tempPath)) {
                    Log::error("Temporary file does not exist: " . ($tempPath ?: 'path is null'));
                    continue;
                }
                
                // IMPORTANTE: Obtener MIME type ANTES de mover el archivo
                try {
                    $mimeType = $file->getMimeType();
                } catch (\Exception $e) {
                    // Fallback: usar extensión para determinar MIME type
                    $extension = strtolower($file->getClientOriginalExtension());
                    $mimeType = match($extension) {
                        'jpg', 'jpeg' => 'image/jpeg',
                        'png' => 'image/png',
                        'gif' => 'image/gif',
                        'webp' => 'image/webp',
                        'mp4' => 'video/mp4',
                        'mov' => 'video/quicktime',
                        'avi' => 'video/x-msvideo',
                        'wmv' => 'video/x-ms-wmv',
                        'flv' => 'video/x-flv',
                        'webm' => 'video/webm',
                        default => 'application/octet-stream'
                    };
                    Log::warning("Could not get MIME type from file, using extension-based fallback: {$mimeType}");
                }
                $type = strpos($mimeType, 'video') !== false ? 'video' : 'image';
                
                // Generar nombre único para el archivo
                $extension = $file->getClientOriginalExtension();
                $fileName = time() . '_' . ($existingImagesCount + $index) . '_' . uniqid() . '.' . $extension;
                
                try {
                    // Intentar mover el archivo usando el método nativo primero
                    $targetPath = $fullPath . DIRECTORY_SEPARATOR . $fileName;
                    
                    // Método 1: Usar move (método estándar de Laravel)
                    if (method_exists($file, 'move')) {
                        $file->move($fullPath, $fileName);
                        $relativePath = '/' . $imagesPath . '/' . $fileName;
                        Log::info("File moved successfully using move() to: {$relativePath}");
                    }
                    // Método 2: Usar copy como fallback
                    else if (copy($tempPath, $targetPath)) {
                        $relativePath = '/' . $imagesPath . '/' . $fileName;
                        Log::info("File copied successfully to: {$relativePath}");
                        
                        // Limpiar archivo temporal
                        @unlink($tempPath);
                    } else {
                        Log::error("Failed to copy file from {$tempPath} to {$targetPath}");
                        continue;
                    }
                } catch (\Exception $e) {
                    // Método 3: Fallback usando file_get_contents/file_put_contents
                    try {
                        Log::warning("Standard move failed, trying alternative method: " . $e->getMessage());
                        
                        $fileContent = file_get_contents($tempPath);
                        if ($fileContent !== false) {
                            $targetPath = $fullPath . DIRECTORY_SEPARATOR . $fileName;
                            if (file_put_contents($targetPath, $fileContent)) {
                                $relativePath = '/' . $imagesPath . '/' . $fileName;
                                Log::info("File saved using alternative method to: {$relativePath}");
                                
                                // Limpiar archivo temporal
                                @unlink($tempPath);
                            } else {
                                Log::error("Failed to save file using alternative method");
                                continue;
                            }
                        } else {
                            Log::error("Could not read temporary file content");
                            continue;
                        }
                    } catch (\Exception $fallbackException) {
                        Log::error("All file handling methods failed: " . $fallbackException->getMessage());
                        continue;
                    }
                }
                
                try {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'path' => $relativePath,
                        'alt_text' => $validated['title'],
                        'sort_order' => $existingImagesCount + $index + 1,
                        'is_primary' => $existingImagesCount === 0 && $index === 0,
                        'type' => $type,
                        'mime_type' => $mimeType
                    ]);
                    
                    Log::info("ProductImage created successfully");
                } catch (\Exception $e) {
                    Log::error("Error creating ProductImage: " . $e->getMessage());
                    continue;
                }
            }
        } else {
            Log::info('NO NEW IMAGES RECEIVED IN REQUEST');
        }

        // Update product
        $product->update($validated);

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Producto actualizado exitosamente.');
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(Product $product): RedirectResponse
    {
        // Delete associated images
        foreach ($product->images as $image) {
            // Eliminar archivo físico del public disk
            if ($image->path && file_exists(public_path($image->path))) {
                unlink(public_path($image->path));
            }
            $image->delete();
        }

        $product->delete();

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Producto eliminado exitosamente.');
    }

    /**
     * Toggle product status
     */
    public function toggleStatus(Product $product): RedirectResponse
    {
        $product->update(['is_active' => !$product->is_active]);

        $status = $product->is_active ? 'activado' : 'desactivado';
        
        return back()->with('success', "Producto {$status} exitosamente.");
    }

    /**
     * Toggle featured status
     */
    public function toggleFeatured(Product $product): RedirectResponse
    {
        $product->update(['is_featured' => !$product->is_featured]);

        $status = $product->is_featured ? 'marcado como destacado' : 'desmarcado como destacado';
        
        return back()->with('success', "Producto {$status} exitosamente.");
    }

    /**
     * Set primary image
     */
    public function setPrimaryImage(Product $product, ProductImage $image): RedirectResponse
    {
        if ($image->product_id !== $product->id) {
            return back()->withErrors(['image' => 'La imagen no pertenece a este producto.']);
        }

        $image->setPrimary();

        return back()->with('success', 'Imagen principal actualizada exitosamente.');
    }
}