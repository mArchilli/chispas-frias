<?php

use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\ComboController as AdminComboController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\SellerController as AdminSellerController;
use App\Http\Controllers\ComboController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (\App\Support\ProductData $productData) {
    $relations = ['category.parent', 'images', 'currentOffer', 'priceTiers', 'variantsActive', 'addonsActive'];

    $featuredProducts = \App\Models\Product::with($relations)
        ->active()
        ->inStock()
        ->where('is_featured', true)
        ->orderByRaw("CASE WHEN category_id IN (SELECT id FROM categories WHERE slug = 'chispa-fria' OR parent_id IN (SELECT id FROM categories WHERE slug = 'chispa-fria')) THEN 0 ELSE 1 END")
        ->take(5)
        ->get()
        ->map(fn (\App\Models\Product $product) => $productData->detail($product));

    $offerProducts = \App\Models\Product::with($relations)
        ->active()
        ->inStock()
        ->whereHas('offers', fn ($query) => $query->where('is_active', true))
        ->orderBy('created_at', 'desc')
        ->take(5)
        ->get()
        ->map(fn (\App\Models\Product $product) => $productData->detail($product));

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
        'featuredProducts' => $featuredProducts,
        'offerProducts' => $offerProducts,
    ]);
});

Route::get('/dashboard', function () {
    return redirect()->route('admin.dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// robots.txt y sitemap.xml con la URL de APP_URL (no hay archivos estáticos con el dominio)
Route::get('/robots.txt', [\App\Http\Controllers\SeoController::class, 'robots'])->name('seo.robots');
Route::get('/sitemap.xml', [\App\Http\Controllers\SeoController::class, 'sitemap'])->name('seo.sitemap');

// Avisos de la Content-Security-Policy (modo solo-reportar). Sin CSRF (lo manda el navegador), con límite por IP.
Route::post('/csp-report', \App\Http\Controllers\CspReportController::class)
    ->middleware('throttle:csp-report')
    ->name('csp.report');

// Public Product Routes
Route::get('/productos', [ProductController::class, 'index'])->name('products.index');
Route::get('/productos/{product}', [ProductController::class, 'show'])->name('products.show');

// Public Combo Routes (ficha de cada combo)
Route::get('/combos/{combo}', [ComboController::class, 'show'])->name('combos.show');

// Contact Page
Route::get('/contacto', function () {
    return Inertia::render('Contact');
})->name('contact');

// Services page
Route::redirect('/servicios', '/#servicios')->name('services');

// Servicio Chispas Frías - detalle
Route::get('/servicios/chispas', function () {
    return Inertia::render('Service-chispas');
})->name('services.chispas');

// Cart Routes (no authentication required)
Route::prefix('carrito')->name('cart.')->group(function () {
    Route::get('/', [\App\Http\Controllers\CartController::class, 'index'])->name('index');
    Route::get('/resumen', [\App\Http\Controllers\CartController::class, 'preview'])->name('preview');
    Route::get('/sugerencias', [\App\Http\Controllers\CartController::class, 'sugerencias'])->middleware('throttle:sugerencias-carrito')->name('suggestions');
    Route::get('/checkout', [\App\Http\Controllers\CartController::class, 'checkout'])->name('checkout');
    Route::post('/agregar', [\App\Http\Controllers\CartController::class, 'add'])->name('add');
    Route::post('/agregar-combo', [\App\Http\Controllers\CartController::class, 'addCombo'])->name('combo.add');
    Route::patch('/actualizar', [\App\Http\Controllers\CartController::class, 'update'])->name('update');
    Route::delete('/eliminar', [\App\Http\Controllers\CartController::class, 'remove'])->name('remove');
    Route::delete('/vaciar', [\App\Http\Controllers\CartController::class, 'clear'])->name('clear');
    Route::get('/count', [\App\Http\Controllers\CartController::class, 'count'])->name('count');
    // Límites por IP configurables en config/tienda.php → limites (ver AppServiceProvider).
    Route::post('/descuento', [\App\Http\Controllers\CartController::class, 'applyDiscountCode'])->middleware('throttle:validar-cupon')->name('discount.apply');
    Route::delete('/descuento', [\App\Http\Controllers\CartController::class, 'removeDiscountCode'])->name('discount.remove');
    // (La forma de pago ya no se elige en la web: las cuotas con tarjeta son solo información y el medio se coordina por WhatsApp.)
    Route::post('/whatsapp', [\App\Http\Controllers\CartController::class, 'generateWhatsAppMessage'])->middleware('throttle:checkout')->name('whatsapp');
});

// Redirect /admin to admin dashboard
Route::get('/admin', function () {
    return redirect()->route('admin.dashboard');
})->middleware(['auth', 'verified', 'can:acceder-panel-admin']);

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Admin Routes
Route::middleware(['auth', 'verified', 'can:acceder-panel-admin', \App\Http\Middleware\CerrarSesionPorInactividad::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Categories Management (borrar reservado a admin, ver Gate 'borrar-catalogo')
    Route::resource('categories', AdminCategoryController::class)
        ->middlewareFor('destroy', 'can:borrar-catalogo');
    Route::patch('categories/{category}/toggle-status', [AdminCategoryController::class, 'toggleStatus'])
        ->name('categories.toggle-status');

    // Products Management (solo admin, ver Gate 'gestionar-productos'; el
    // vendedor sólo ve precios, vía admin.prices.index más abajo)
    Route::middleware('can:gestionar-productos')->group(function () {
        Route::resource('products', AdminProductController::class);
        Route::patch('products/{product}/toggle-status', [AdminProductController::class, 'toggleStatus'])
            ->name('products.toggle-status');
        Route::patch('products/{product}/toggle-featured', [AdminProductController::class, 'toggleFeatured'])
            ->name('products.toggle-featured');
        Route::patch('products/{product}/images/{image}/set-primary', [AdminProductController::class, 'setPrimaryImage'])
            ->name('products.set-primary-image');

        // Product Offers Management
        Route::post('products/{product}/offers', [\App\Http\Controllers\Admin\ProductOfferController::class, 'store'])
            ->name('products.offers.store');
        Route::put('offers/{offer}', [\App\Http\Controllers\Admin\ProductOfferController::class, 'update'])
            ->name('products.offers.update');
        // Borrar la oferta rápida de un producto (modal) reservado a admin, ver Gate 'borrar-catalogo'
        Route::delete('products/{product}/offers', [\App\Http\Controllers\Admin\ProductOfferController::class, 'destroy'])
            ->name('products.offers.destroy')
            ->middleware('can:borrar-catalogo');
        Route::patch('offers/{offer}/toggle', [\App\Http\Controllers\Admin\ProductOfferController::class, 'toggle'])
            ->name('offers.toggle');
        Route::post('products/{product}/quick-offer', [\App\Http\Controllers\Admin\ProductOfferController::class, 'quickOffer'])
            ->name('products.quick-offer');

        // Add-ons Management (catálogo global de personalización; borrar reservado
        // a admin vía Gate 'borrar-catalogo', y bloqueado si el add-on ya se usó
        // en alguna orden — ver AddonController::destroy)
        Route::resource('addons', \App\Http\Controllers\Admin\AddonController::class)
            ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
            ->middlewareFor('destroy', 'can:borrar-catalogo');
        Route::patch('addons/{addon}/toggle-status', [\App\Http\Controllers\Admin\AddonController::class, 'toggleStatus'])
            ->name('addons.toggle-status');

        // Combos Management (paquetes de productos a precio fijo; borrar reservado
        // a admin, ver Gate 'borrar-catalogo')
        Route::resource('combos', AdminComboController::class)
            ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
            ->middlewareFor('destroy', 'can:borrar-catalogo');
        Route::patch('combos/{combo}/toggle-status', [AdminComboController::class, 'toggleStatus'])
            ->name('combos.toggle-status');
        Route::patch('combos/{combo}/images/{image}/set-primary', [AdminComboController::class, 'setPrimaryImage'])
            ->name('combos.set-primary-image');
    });

    // Prices (solo lectura): listado de productos y precios, con los mismos
    // filtros de categoría/búsqueda del catálogo. Accesible para admin y
    // vendedor; es la única vía que tiene el vendedor para ver precios, ya
    // que la gestión de productos quedó reservada a admin (arriba).
    Route::get('prices', [\App\Http\Controllers\Admin\PriceController::class, 'index'])
        ->name('prices.index');

    // Dedicated Offers Management (borrar reservado a admin, ver Gate 'borrar-catalogo')
    Route::resource('offers', \App\Http\Controllers\Admin\ProductOfferAdminController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->middlewareFor('destroy', 'can:borrar-catalogo');
    Route::post('offers/{offer}/toggle-status', [\App\Http\Controllers\Admin\ProductOfferAdminController::class, 'toggleStatus'])
        ->name('offers.toggle-status');

    // Discount Codes Management (borrar reservado a admin, ver Gate 'borrar-catalogo')
    Route::resource('discount-codes', \App\Http\Controllers\Admin\DiscountCodeController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->middlewareFor('destroy', 'can:borrar-catalogo');
    Route::patch('discount-codes/{discount_code}/toggle-status', [\App\Http\Controllers\Admin\DiscountCodeController::class, 'toggleStatus'])
        ->name('discount-codes.toggle-status');

    // Orders Management
    Route::get('orders', [\App\Http\Controllers\Admin\OrderController::class, 'index'])
        ->name('orders.index');
    Route::get('orders/{order}', [\App\Http\Controllers\Admin\OrderController::class, 'show'])
        ->name('orders.show');
    Route::patch('orders/{order}/status', [\App\Http\Controllers\Admin\OrderController::class, 'updateStatus'])
        ->name('orders.update-status');
    // Checkout mínimo — completar/corregir los datos del cliente desde la ficha del pedido (con reserva, PUT al Central con las líneas actuales).
    Route::patch('orders/{order}/cliente', [\App\Http\Controllers\Admin\OrderController::class, 'updateCliente'])
        ->name('orders.update-cliente');

    // 10.4 — Confirmar la venta en el Sistema Central (admin y vendedor). `confirmation` devuelve los datos del modal (cuentas,
    // formas de envío, vendedores) para que la ficha del pedido no dependa del Central; `confirm` crea la venta.
    Route::get('orders/{order}/confirmation', [\App\Http\Controllers\Admin\OrderConfirmationController::class, 'datos'])
        ->name('orders.confirmation');
    Route::post('orders/{order}/confirm', [\App\Http\Controllers\Admin\OrderConfirmationController::class, 'confirmar'])
        ->name('orders.confirm');

    // Settings (solo admin, ver Gate 'gestionar-configuracion')
    Route::middleware('can:gestionar-configuracion')->group(function () {
        Route::get('settings', [\App\Http\Controllers\Admin\SettingController::class, 'edit'])
            ->name('settings.edit');
        Route::patch('settings', [\App\Http\Controllers\Admin\SettingController::class, 'update'])
            ->name('settings.update');

        // Planes de pago con tarjeta de crédito — catálogo de recargos 100%
        // informativos (este proyecto no cobra online). El borrado queda
        // bloqueado si el plan ya fue usado en una orden, ver
        // CardPaymentPlanController::destroy.
        Route::resource('card-payment-plans', \App\Http\Controllers\Admin\CardPaymentPlanController::class)
            ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
        Route::patch('card-payment-plans/{card_payment_plan}/toggle-status', [\App\Http\Controllers\Admin\CardPaymentPlanController::class, 'toggleStatus'])
            ->name('card-payment-plans.toggle-status');
    });

    // Sellers Management (solo admin, ver Gate 'gestionar-vendedores')
    Route::middleware('can:gestionar-vendedores')->group(function () {
        Route::resource('sellers', AdminSellerController::class)->except(['show', 'destroy']);
        Route::patch('sellers/{seller}/toggle-status', [AdminSellerController::class, 'toggleStatus'])
            ->name('sellers.toggle-status');
    });

    // Documents Management (manuales/instructivos para vendedores). El listado
    // es visible para admin y vendedor (el vendedor sólo ve los activos, ver
    // DocumentController@index); el ABM completo es sólo admin, ver Gate
    // 'gestionar-documentos'.
    Route::get('documents', [\App\Http\Controllers\Admin\DocumentController::class, 'index'])
        ->name('documents.index');
    Route::middleware('can:gestionar-documentos')->group(function () {
        Route::resource('documents', \App\Http\Controllers\Admin\DocumentController::class)
            ->except(['index', 'show']);
        Route::patch('documents/{document}/toggle-status', [\App\Http\Controllers\Admin\DocumentController::class, 'toggleStatus'])
            ->name('documents.toggle-status');
    });
});

require __DIR__.'/auth.php';

// Ruta para página 404 personalizada
Route::fallback(function () {
    // Con el status 404 de verdad (no un 200 «soft»): los buscadores no indexan la página y quien sondea rutas ve un 404 real.
    return Inertia::render('Error404')->toResponse(request())->setStatusCode(404);
});
