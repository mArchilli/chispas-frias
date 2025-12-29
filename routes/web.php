<?php

use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    // Obtener productos destacados (priorizando chispas frías)
    $featuredProducts = \App\Models\Product::with(['category', 'images'])
        ->where('is_active', true)
        ->where('stock', '>', 0)
        ->orderByRaw("CASE WHEN category_id IN (SELECT id FROM categories WHERE slug = 'chispa-fria' OR parent_id IN (SELECT id FROM categories WHERE slug = 'chispa-fria')) THEN 0 ELSE 1 END")
        ->take(5)
        ->get()
        ->map(function($product) {
            $primaryImage = $product->images()->where('is_primary', true)->first() 
                           ?? $product->images()->first();
            
            return [
                'id' => $product->id,
                'title' => $product->title,
                'description' => $product->description,
                'price' => $product->price,
                'formatted_price' => '$' . number_format((float) $product->price, 2),
                'image' => $primaryImage?->path,
                'category' => $product->category->name,
            ];
        });

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
        'featuredProducts' => $featuredProducts,
    ]);
});

Route::get('/dashboard', function () {
    return redirect()->route('admin.dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Public Product Routes
Route::get('/productos', [ProductController::class, 'index'])->name('products.index');
Route::get('/productos/{product}', [ProductController::class, 'show'])->name('products.show');

// Contact Page
Route::get('/contacto', function () {
    return Inertia::render('Contact');
})->name('contact');

// Services page
Route::get('/servicios', function () {
    return Inertia::render('Services');
})->name('services');

// Servicio Chispas Frías - detalle
Route::get('/servicios/chispas', function () {
    return Inertia::render('Service-chispas');
})->name('services.chispas');

// Cart Routes (no authentication required)
Route::prefix('carrito')->name('cart.')->group(function () {
    Route::get('/', [\App\Http\Controllers\CartController::class, 'index'])->name('index');
    Route::get('/checkout', [\App\Http\Controllers\CartController::class, 'checkout'])->name('checkout');
    Route::post('/agregar', [\App\Http\Controllers\CartController::class, 'add'])->name('add');
    Route::patch('/actualizar', [\App\Http\Controllers\CartController::class, 'update'])->name('update');
    Route::delete('/eliminar', [\App\Http\Controllers\CartController::class, 'remove'])->name('remove');
    Route::delete('/vaciar', [\App\Http\Controllers\CartController::class, 'clear'])->name('clear');
    Route::get('/count', [\App\Http\Controllers\CartController::class, 'count'])->name('count');
    Route::post('/whatsapp', [\App\Http\Controllers\CartController::class, 'generateWhatsAppMessage'])->name('whatsapp');
});

// Redirect /admin to admin dashboard
Route::get('/admin', function () {
    return redirect()->route('admin.dashboard');
})->middleware(['auth', 'verified']);

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Admin Routes
Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', function () {
        $stats = [
            'categories_count' => \App\Models\Category::count(),
            'products_count' => \App\Models\Product::active()->count(),
            'products_total' => \App\Models\Product::count(),
            'out_of_stock' => \App\Models\Product::where('stock', '<=', 0)->count(),
        ];
        
        return Inertia::render('Admin/Dashboard', compact('stats'));
    })->name('dashboard');

    // Categories Management
    Route::resource('categories', AdminCategoryController::class);
    Route::patch('categories/{category}/toggle-status', [AdminCategoryController::class, 'toggleStatus'])
        ->name('categories.toggle-status');

    // Products Management
    Route::resource('products', AdminProductController::class);
    Route::patch('products/{product}/toggle-status', [AdminProductController::class, 'toggleStatus'])
        ->name('products.toggle-status');
    Route::patch('products/{product}/toggle-featured', [AdminProductController::class, 'toggleFeatured'])
        ->name('products.toggle-featured');
    Route::patch('products/{product}/images/{image}/set-primary', [AdminProductController::class, 'setPrimaryImage'])
        ->name('products.set-primary-image');
});

require __DIR__.'/auth.php';

// Ruta para página 404 personalizada
Route::fallback(function () {
    return Inertia::render('Error404');
});
