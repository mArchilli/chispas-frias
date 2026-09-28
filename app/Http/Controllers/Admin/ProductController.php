<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Models\Product;
use App\Models\Category;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

        $query = Product::with(['category.parent', 'images', 'currentOffer', 'priceTiers']);

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
            } elseif ($stockFilter === 'low_stock') {
                // Debe coincidir con LOW_STOCK_THRESHOLD en resources/js/utils/stock.js
                $query->where('stock', '>', 0)->where('stock', '<=', 3);
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
                    'price_tiers' => $product->priceTiers->map(fn ($tier) => [
                        'id' => $tier->id,
                        'cantidad_minima' => $tier->cantidad_minima,
                        'precio_unitario' => (float) $tier->precio_unitario,
                    ])->values(),
                    'current_offer' => $product->currentOffer ? [
                        'id' => $product->currentOffer->id,
                        'offer_price' => $product->currentOffer->offer_price,
                        'percentage_discount' => $product->currentOffer->percentage_discount,
                        'tipo_descuento' => $product->currentOffer->tipo_descuento?->value,
                        'valor_descuento' => $product->currentOffer->valor_descuento,
                        'alcance' => $product->currentOffer->alcance?->value,
                        'product_price_tier_id' => $product->currentOffer->product_price_tier_id,
                        'start_date' => $product->currentOffer->start_date,
                        'end_date' => $product->currentOffer->end_date,
                        'is_active' => $product->currentOffer->is_active,
                        'formatted_offer_price' => $product->currentOffer->offer_price !== null
                            ? '$' . number_format($product->currentOffer->offer_price, 0, ',', '.')
                            : null,
                    ] : null,
                    'has_active_offer' => $product->hasActiveOffer(),
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
            'categories' => $categories,
            'addons' => $this->addonsCatalog(),
        ]);
    }

    /**
     * Catálogo global de add-ons para el checklist de asociación en el form de
     * producto. Se mandan todos (activos e inactivos) con su flag para que el
     * front pueda marcar los inactivos y dejar tildar uno ya asociado que se
     * desactivó después.
     */
    private function addonsCatalog()
    {
        return Addon::orderBy('name')->get(['id', 'name', 'price', 'requires_text', 'is_active']);
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
            'price' => 'required|numeric|min:0|max:99999999.99',
            'sku' => 'nullable|string|max:255|unique:products,sku',
            'category_id' => 'required|exists:categories,id',
            'stock' => 'nullable|integer|min:0',
            'is_active' => 'nullable',
            'is_featured' => 'nullable',
            'images' => 'nullable|array|max:10',
            'images.*' => 'file|mimes:jpeg,png,jpg,gif,webp,mp4,mov,avi,wmv,flv,webm|max:20480',
            'images_variant' => 'nullable|array',
            'images_variant.*' => 'nullable|string|max:64',
            'price_tiers' => 'nullable|array',
            'price_tiers.*.cantidad_minima' => 'required_with:price_tiers|integer|min:2|distinct',
            'price_tiers.*.precio_unitario' => 'required_with:price_tiers|numeric|min:0.01',
            ...$this->variantAndAddonRules(),
        ]);

        $this->assertUnaSolaVarianteCustomColor($request);

        // Convert checkbox values properly
        $validated['is_active'] = $request->input('is_active', '0') === '1';
        $validated['is_featured'] = $request->input('is_featured', '0') === '1';

        // Si el stock está vacío o es null, establecer 9999
        if (!isset($validated['stock']) || $validated['stock'] === '' || $validated['stock'] === null) {
            $validated['stock'] = 9999;
        }

        $priceTiers = $validated['price_tiers'] ?? [];
        $variants = $validated['variants'] ?? [];
        $addons = $validated['addons'] ?? [];
        unset($validated['price_tiers'], $validated['variants'], $validated['addons'], $validated['images_variant']);

        ['product' => $product, 'variantIdByUid' => $variantIdByUid] = DB::transaction(
            function () use ($validated, $request, $priceTiers, $variants, $addons) {
                $product = Product::create($validated);

                if ($request->has('price_tiers')) {
                    $this->syncPriceTiers($product, $priceTiers);
                }

                $variantIdByUid = $this->syncVariants($product, $variants);
                $this->syncAddons($product, $addons);

                return ['product' => $product, 'variantIdByUid' => $variantIdByUid];
            }
        );

        // Handle image uploads
        if ($request->hasFile('images')) {
            $files = $request->file('images');
            Log::info('IMAGES RECEIVED: ' . count($files) . ' files');

            // Usar la variable de entorno para determinar dónde guardar las imágenes
            $productImagesPath = ltrim(env('PRODUCT_IMAGES_PATH', '/images/products/'), '/');
            $fullPath = public_path($productImagesPath);

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
                        $relativePath = $fileName; // Guardar solo el nombre del archivo
                        Log::info("File moved successfully using move() to: {$fullPath}/{$relativePath}");
                    }
                    // Método 2: Usar copy como fallback
                    else if (copy($tempPath, $targetPath)) {
                        $relativePath = $fileName; // Guardar solo el nombre del archivo
                        Log::info("File copied successfully to: {$fullPath}/{$relativePath}");
                        
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
                                $relativePath = $fileName; // Guardar solo el nombre del archivo
                                Log::info("File saved using alternative method to: {$fullPath}/{$relativePath}");
                                
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
                        'product_variant_id' => $this->resolveVariantRef(
                            $request->input("images_variant.$index"), $variantIdByUid, $product
                        ),
                        'path' => $relativePath, // Solo el nombre del archivo
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
        $product->load(['images', 'priceTiers', 'variants', 'addons']);

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
                    'product_variant_id' => $image->product_variant_id,
                    'path' => $image->path,
                    'url' => $image->url,
                    'alt_text' => $image->alt_text,
                    'sort_order' => $image->sort_order,
                    'is_primary' => $image->is_primary,
                    'type' => $image->type,
                    'mime_type' => $image->mime_type
                ];
            }),
            'price_tiers' => $product->priceTiers->map(fn ($tier) => [
                'id' => $tier->id,
                'cantidad_minima' => $tier->cantidad_minima,
                'precio_unitario' => (float) $tier->precio_unitario,
            ]),
            'variants' => $product->variants->map(fn ($variant) => [
                'id' => $variant->id,
                'name' => $variant->name,
                'color_hex' => $variant->color_hex,
                'is_custom_color' => $variant->is_custom_color,
                'price_addon' => (float) $variant->price_addon,
                'stock' => $variant->stock,
                'sku' => $variant->sku,
                'sort_order' => $variant->sort_order,
                'is_active' => $variant->is_active,
            ])->values(),
            'addons' => $product->addons->map(fn ($addon) => [
                'id' => $addon->id,
                'price_override' => $addon->pivot->price_override !== null
                    ? (float) $addon->pivot->price_override
                    : null,
            ])->values(),
        ];

        return Inertia::render('Admin/Products/Edit', [
            'product' => $productData,
            'categories' => $categories,
            'addons' => $this->addonsCatalog(),
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
            'price' => 'required|numeric|min:0|max:99999999.99',
            'sku' => 'nullable|string|max:255|unique:products,sku,' . $product->id,
            'category_id' => 'required|exists:categories,id',
            'stock' => 'nullable|integer|min:0',
            'is_active' => 'nullable',
            'is_featured' => 'nullable',
            'new_images' => 'nullable|array|max:10',
            'new_images.*' => 'file|mimes:jpeg,png,jpg,gif,webp,mp4,mov,avi,wmv,flv,webm|max:20480',
            'new_images_variant' => 'nullable|array',
            'new_images_variant.*' => 'nullable|string|max:64',
            'existing_images_variant' => 'nullable|array',
            'existing_images_variant.*' => 'nullable|string|max:64',
            'remove_images' => 'nullable|array',
            'remove_images.*' => 'integer|exists:product_images,id',
            'price_tiers' => 'nullable|array',
            'price_tiers.*.id' => [
                'nullable',
                'integer',
                Rule::exists('product_price_tiers', 'id')->where('product_id', $product->id),
            ],
            'price_tiers.*.cantidad_minima' => 'required_with:price_tiers|integer|min:2|distinct',
            'price_tiers.*.precio_unitario' => 'required_with:price_tiers|numeric|min:0.01',
            ...$this->variantAndAddonRules($product),
        ]);

        $this->assertUnaSolaVarianteCustomColor($request);

        // Convert checkbox values properly
        $validated['is_active'] = $request->input('is_active', '0') === '1';
        $validated['is_featured'] = $request->input('is_featured', '0') === '1';

        // Si el stock está vacío o es null, establecer 9999
        if (!isset($validated['stock']) || $validated['stock'] === '' || $validated['stock'] === null) {
            $validated['stock'] = 9999;
        }

        // Producto + escalas + variantes + add-ons se sincronizan en una sola
        // transacción; el mapa uid⇒id de las variantes nuevas se usa después para
        // asociarles imágenes (el I/O de archivos queda fuera de la transacción).
        $priceTiers = $validated['price_tiers'] ?? [];
        $variants = $validated['variants'] ?? [];
        $addons = $validated['addons'] ?? [];
        unset($validated['price_tiers'], $validated['variants'], $validated['addons']);

        $variantIdByUid = DB::transaction(function () use ($product, $validated, $request, $priceTiers, $variants, $addons) {
            $product->update($validated);

            if ($request->has('price_tiers')) {
                $this->syncPriceTiers($product, $priceTiers);
            }

            $map = $this->syncVariants($product, $variants);
            $this->syncAddons($product, $addons);

            return $map;
        });

        // Remove specified images
        if ($request->has('remove_images')) {
            /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProductImage> $imagesToRemove */
            $imagesToRemove = ProductImage::whereIn('id', $request->remove_images)
                ->where('product_id', $product->id)
                ->get();

            foreach ($imagesToRemove as $image) {
                // Eliminar archivo físico usando la ruta de entorno configurada
                $filesystemPath = $image->getFilesystemPath();
                if ($image->path && file_exists($filesystemPath)) {
                    unlink($filesystemPath);
                }
                $image->delete();
            }
        }

        // Add new images
        if ($request->hasFile('new_images')) {
            $files = $request->file('new_images');
            Log::info('NEW IMAGES RECEIVED: ' . count($files) . ' files');

            $existingImagesCount = $product->images()->count();
            // Usar la variable de entorno para determinar dónde guardar las imágenes
            $productImagesPath = ltrim(env('PRODUCT_IMAGES_PATH', '/images/products/'), '/');
            $fullPath = public_path($productImagesPath);

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
                        $relativePath = $fileName; // Guardar solo el nombre del archivo
                        Log::info("File moved successfully using move() to: {$fullPath}/{$relativePath}");
                    }
                    // Método 2: Usar copy como fallback
                    else if (copy($tempPath, $targetPath)) {
                        $relativePath = $fileName; // Guardar solo el nombre del archivo
                        Log::info("File copied successfully to: {$fullPath}/{$relativePath}");
                        
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
                                $relativePath = $fileName; // Guardar solo el nombre del archivo
                                Log::info("File saved using alternative method to: {$fullPath}/{$relativePath}");
                                
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
                        'product_variant_id' => $this->resolveVariantRef(
                            $request->input("new_images_variant.$index"), $variantIdByUid, $product
                        ),
                        'path' => $relativePath, // Solo el nombre del archivo
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

        // Reasignar (o quitar) la variante asociada a imágenes ya existentes.
        $removed = collect($request->input('remove_images', []))->map(fn ($id) => (int) $id)->all();
        foreach ((array) $request->input('existing_images_variant', []) as $imageId => $ref) {
            if (in_array((int) $imageId, $removed, true)) {
                continue;
            }
            $image = $product->images()->whereKey($imageId)->first();
            if ($image) {
                $image->update([
                    'product_variant_id' => $this->resolveVariantRef((string) $ref, $variantIdByUid, $product),
                ]);
            }
        }

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Producto actualizado exitosamente.');
    }

    /**
     * Sincroniza las escalas de precio por cantidad de un producto contra el
     * array recibido del form: borra las que ya no vienen y crea/actualiza el
     * resto. `id` ausente = fila nueva (updateOrCreate con id=null nunca
     * matchea una fila existente, así que siempre crea).
     */
    private function syncPriceTiers(Product $product, array $tiers): void
    {
        $incomingIds = collect($tiers)->pluck('id')->filter()->all();

        $product->priceTiers()->whereNotIn('id', $incomingIds)->delete();

        foreach ($tiers as $tier) {
            $product->priceTiers()->updateOrCreate(
                ['id' => $tier['id'] ?? null],
                [
                    'cantidad_minima' => $tier['cantidad_minima'],
                    'precio_unitario' => $tier['precio_unitario'],
                ]
            );
        }
    }

    /**
     * Reglas de validación de variantes de color y add-ons asociados, comunes a
     * store y update. En update se pasa el producto para acotar `variants.*.id`
     * a las variantes propias.
     */
    private function variantAndAddonRules(?Product $product = null): array
    {
        $variantIdRule = ['nullable', 'integer'];
        if ($product) {
            $variantIdRule[] = Rule::exists('product_variants', 'id')->where('product_id', $product->id);
        }

        return [
            'variants' => 'nullable|array|max:30',
            'variants.*._uid' => 'nullable|string|max:64',
            'variants.*.id' => $variantIdRule,
            'variants.*.name' => 'required_with:variants|string|max:100|distinct:ignore_case',
            'variants.*.color_hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'variants.*.is_custom_color' => 'boolean',
            'variants.*.price_addon' => 'nullable|numeric|min:0|max:99999999.99',
            'variants.*.stock' => 'nullable|integer|min:0',
            'variants.*.sku' => 'nullable|string|max:255',
            'variants.*.is_active' => 'boolean',
            'addons' => 'nullable|array',
            'addons.*.id' => ['required', 'integer', Rule::exists('addons', 'id')],
            'addons.*.price_override' => 'nullable|numeric|min:0|max:99999999.99',
        ];
    }

    /**
     * Como mucho una variante por producto puede marcarse "color a elección del
     * cliente" (is_custom_color). Se chequea sobre el input crudo porque los
     * booleanos vienen como '1'/'0' por FormData.
     */
    private function assertUnaSolaVarianteCustomColor(Request $request): void
    {
        $customColor = collect($request->input('variants', []))
            ->filter(fn ($v) => $this->toBool($v['is_custom_color'] ?? false))
            ->count();

        if ($customColor > 1) {
            throw ValidationException::withMessages([
                'variants' => 'Sólo una variante puede marcarse como "color a elección del cliente".',
            ]);
        }
    }

    /**
     * Sincroniza las variantes de color del producto contra el array del form,
     * mismo criterio de full-sync que syncPriceTiers: borra las que no vienen y
     * crea/actualiza el resto. Devuelve el mapa `_uid ⇒ id` de las variantes
     * (clave temporal del cliente ⇒ id real recién creado), para poder asociarles
     * imágenes en el mismo submit.
     *
     * @return array<string, int>
     */
    private function syncVariants(Product $product, array $variants): array
    {
        $incomingIds = collect($variants)->pluck('id')->filter()->all();

        $product->variants()->whereNotIn('id', $incomingIds)->delete();

        $idByUid = [];

        foreach (array_values($variants) as $i => $v) {
            $variant = $product->variants()->updateOrCreate(
                ['id' => $v['id'] ?? null],
                [
                    'name' => $v['name'],
                    'color_hex' => ($v['color_hex'] ?? '') ?: null,
                    'is_custom_color' => $this->toBool($v['is_custom_color'] ?? false),
                    'price_addon' => ($v['price_addon'] ?? '') !== '' && ($v['price_addon'] ?? null) !== null
                        ? $v['price_addon']
                        : 0,
                    'stock' => ($v['stock'] ?? '') === '' || ($v['stock'] ?? null) === null
                        ? null
                        : (int) $v['stock'],
                    'sku' => ($v['sku'] ?? '') ?: null,
                    'sort_order' => $i,
                    'is_active' => $this->toBool($v['is_active'] ?? true),
                ]
            );

            if (! empty($v['_uid'])) {
                $idByUid[$v['_uid']] = $variant->id;
            }
        }

        return $idByUid;
    }

    /**
     * Sincroniza los add-ons ofrecidos por el producto (pivote product_addon) con
     * la lista tildada en el form: price_override propio (null = usa addons.price)
     * y sort_order por el orden de la lista.
     */
    private function syncAddons(Product $product, array $addons): void
    {
        $payload = [];

        foreach (array_values($addons) as $i => $a) {
            $override = $a['price_override'] ?? '';
            $payload[(int) $a['id']] = [
                'price_override' => $override === '' || $override === null ? null : $override,
                'sort_order' => $i,
            ];
        }

        $product->addons()->sync($payload);
    }

    /**
     * Traduce la referencia de variante que manda el gestor de imágenes a un
     * product_variant_id real: '' / null ⇒ medio general; 'uid:xxx' ⇒ variante
     * recién creada en este submit (vía el mapa de syncVariants); numérico ⇒ id
     * de una variante existente, validado contra el producto.
     */
    private function resolveVariantRef(?string $ref, array $idByUid, Product $product): ?int
    {
        if (! $ref) {
            return null;
        }

        if (str_starts_with($ref, 'uid:')) {
            return $idByUid[substr($ref, 4)] ?? null;
        }

        $id = (int) $ref;

        return $id > 0 && $product->variants()->whereKey($id)->exists() ? $id : null;
    }

    private function toBool(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'on'], true);
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(Product $product): RedirectResponse
    {
        // Delete associated images
        foreach ($product->images as $image) {
            // Eliminar archivo físico usando la ruta de entorno configurada
            $filesystemPath = $image->getFilesystemPath();
            if ($image->path && file_exists($filesystemPath)) {
                unlink($filesystemPath);
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