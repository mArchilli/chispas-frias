<?php

use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return redirect()->route('admin.dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

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
