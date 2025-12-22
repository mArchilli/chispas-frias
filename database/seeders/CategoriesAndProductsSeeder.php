<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategoriesAndProductsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear categorías principales
        $fuegosArtificiales = Category::create([
            'name' => 'Fuegos Artificiales',
            'description' => 'Espectaculares fuegos artificiales para celebraciones',
            'slug' => 'fuegos-artificiales',
            'parent_id' => null,
            'sort_order' => 1,
            'is_active' => true
        ]);

        $chispasFrias = Category::create([
            'name' => 'Chispas Frías',
            'description' => 'Efectos especiales seguros para eventos',
            'slug' => 'chispas-frias',
            'parent_id' => null,
            'sort_order' => 2,
            'is_active' => true
        ]);

        $maquinaria = Category::create([
            'name' => 'Maquinaria',
            'description' => 'Equipamiento profesional para eventos',
            'slug' => 'maquinaria',
            'parent_id' => null,
            'sort_order' => 3,
            'is_active' => true
        ]);

        // Crear subcategorías para Fuegos Artificiales
        $nueveTiros = Category::create([
            'name' => '9 Tiros',
            'description' => 'Fuegos artificiales de 9 disparos',
            'slug' => '9-tiros',
            'parent_id' => $fuegosArtificiales->id,
            'sort_order' => 1,
            'is_active' => true
        ]);

        $dieciseisTiros = Category::create([
            'name' => '16 Tiros',
            'description' => 'Fuegos artificiales de 16 disparos',
            'slug' => '16-tiros',
            'parent_id' => $fuegosArtificiales->id,
            'sort_order' => 2,
            'is_active' => true
        ]);

        // Crear subcategorías para Chispas Frías
        $dosX20 = Category::create([
            'name' => '2x20',
            'description' => 'Chispas frías formato 2x20',
            'slug' => '2x20',
            'parent_id' => $chispasFrias->id,
            'sort_order' => 1,
            'is_active' => true
        ]);

        $tresX20 = Category::create([
            'name' => '3x20',
            'description' => 'Chispas frías formato 3x20',
            'slug' => '3x20',
            'parent_id' => $chispasFrias->id,
            'sort_order' => 2,
            'is_active' => true
        ]);

        // Crear productos para 9 Tiros
        $producto1 = Product::create([
            'title' => 'Fantasía Dorada 9 Tiros',
            'description' => 'Espectacular batería de 9 tiros con efectos dorados y plateados. Ideal para finales de eventos.',
            'price' => 45.99,
            'sku' => 'FA-9T-001',
            'category_id' => $nueveTiros->id,
            'stock' => 25,
            'is_active' => true,
            'is_featured' => true
        ]);

        $producto2 = Product::create([
            'title' => 'Lluvia de Estrellas 9 Tiros',
            'description' => 'Batería con efectos de lluvia de estrellas multicolores. Duración aproximada 30 segundos.',
            'price' => 52.50,
            'sku' => 'FA-9T-002',
            'category_id' => $nueveTiros->id,
            'stock' => 18,
            'is_active' => true,
            'is_featured' => false
        ]);

        // Crear productos para 16 Tiros
        $producto3 = Product::create([
            'title' => 'Espectáculo Real 16 Tiros',
            'description' => 'La batería más espectacular de nuestra colección. 16 disparos con efectos únicos y coloridos.',
            'price' => 89.99,
            'sku' => 'FA-16T-001',
            'category_id' => $dieciseisTiros->id,
            'stock' => 12,
            'is_active' => true,
            'is_featured' => true
        ]);

        // Crear productos para Chispas Frías 2x20
        $producto4 = Product::create([
            'title' => 'Chispa Fría Dorada 2x20',
            'description' => 'Efectos de chispas frías seguras en formato 2x20. Perfectas para bodas y eventos íntimos.',
            'price' => 28.75,
            'sku' => 'CF-2X20-001',
            'category_id' => $dosX20->id,
            'stock' => 40,
            'is_active' => true,
            'is_featured' => true
        ]);

        // Crear productos para Chispas Frías 3x20
        $producto5 = Product::create([
            'title' => 'Chispa Fría Plateada 3x20',
            'description' => 'Efectos de chispas frías plateadas en formato 3x20. Ideal para eventos de gran escala.',
            'price' => 38.90,
            'sku' => 'CF-3X20-001',
            'category_id' => $tresX20->id,
            'stock' => 35,
            'is_active' => true,
            'is_featured' => false
        ]);

        // Crear producto para Maquinaria
        $producto6 = Product::create([
            'title' => 'Máquina de Humo Profesional',
            'description' => 'Máquina de humo de alta capacidad para eventos profesionales. Incluye mando a distancia.',
            'price' => 299.99,
            'sku' => 'MAQ-001',
            'category_id' => $maquinaria->id,
            'stock' => 8,
            'is_active' => true,
            'is_featured' => true
        ]);

        // Crear imágenes de ejemplo para algunos productos
        $productos = [$producto1, $producto2, $producto3, $producto4, $producto5, $producto6];

        foreach ($productos as $index => $producto) {
            // Imagen principal
            ProductImage::create([
                'product_id' => $producto->id,
                'path' => "products/product-{$producto->id}-main.jpg",
                'alt_text' => "Imagen principal de {$producto->title}",
                'sort_order' => 1,
                'is_primary' => true
            ]);

            // Imagen secundaria
            ProductImage::create([
                'product_id' => $producto->id,
                'path' => "products/product-{$producto->id}-secondary.jpg",
                'alt_text' => "Imagen secundaria de {$producto->title}",
                'sort_order' => 2,
                'is_primary' => false
            ]);

            // Algunas imágenes adicionales para productos destacados
            if ($producto->is_featured) {
                ProductImage::create([
                    'product_id' => $producto->id,
                    'path' => "products/product-{$producto->id}-detail.jpg",
                    'alt_text' => "Detalle de {$producto->title}",
                    'sort_order' => 3,
                    'is_primary' => false
                ]);
            }
        }
    }
}