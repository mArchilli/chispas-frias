<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Console\Command;

class CreateTestData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:create-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create test data for products and offers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Verificar si hay categorías
        $category = Category::first();
        if (!$category) {
            $this->error('No hay categorías. Crea al menos una categoría primero.');
            return;
        }

        // Crear productos de prueba si no existen
        if (Product::count() === 0) {
            $this->info('Creando productos de prueba...');
            
            for ($i = 1; $i <= 5; $i++) {
                $product = Product::create([
                    'title' => "Producto de Prueba $i",
                    'description' => "Descripción del producto $i para testing.",
                    'price' => 100 + ($i * 10),
                    'sku' => "TEST-$i",
                    'category_id' => $category->id,
                    'stock' => 10,
                    'is_active' => true,
                    'is_featured' => $i <= 2, // Los primeros 2 son destacados
                ]);

                // Crear oferta para algunos productos
                if ($i >= 3) {
                    $product->offers()->create([
                        'offer_price' => $product->price * 0.8, // 20% descuento
                        'is_active' => true,
                    ]);
                    $this->info("Oferta creada para {$product->title}");
                }

                $this->info("Producto creado: {$product->title}");
            }
        }

        // Mostrar estadísticas
        $totalProducts = Product::where('is_active', true)->count();
        $featuredProducts = Product::where('is_active', true)->where('is_featured', true)->count();
        $offerProducts = Product::where('is_active', true)->whereHas('offers', function($q) { 
            $q->where('is_active', true); 
        })->count();

        $this->info("=== ESTADÍSTICAS ===");
        $this->info("Total productos activos: $totalProducts");
        $this->info("Productos destacados: $featuredProducts");
        $this->info("Productos con ofertas: $offerProducts");
    }
}
