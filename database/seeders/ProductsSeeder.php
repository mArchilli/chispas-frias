<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener categorías por slug para asignar productos
        $dosX20 = Category::where('slug', '2x20')->first();
        $tresX30 = Category::where('slug', '3x30')->first();
        $cuatroX30 = Category::where('slug', '4x30')->first();
        $nueveTiros = Category::where('slug', '9-tiros')->first();
        $dieciseisTiros = Category::where('slug', '16-tiros')->first();
        $treintaDosTiros = Category::where('slug', '32-tiros')->first();
        $detonadorInalambrico = Category::where('slug', 'detonador-inalambrico')->first();
        $humoVertical = Category::where('slug', 'humo-vertical')->first();
        $bengala = Category::where('slug', 'bengala')->first();
        $pote = Category::where('slug', 'pote')->first();
        $pirotecnia = Category::where('slug', 'pirotecnia')->first();
        $bengalasVelas = Category::where('slug', 'bengalas')->first();
        $sparkie = Category::where('slug', 'sparkie')->first();

        // Crear productos para Chispa Fría - 2x20
        $producto1 = Product::create([
            'title' => 'Chispa Fría Dorada 2x20',
            'description' => 'Efectos de chispas frías seguras en formato 2x20. Perfectas para bodas y eventos íntimos.',
            'price' => 28.75,
            'sku' => 'CF-2X20-001',
            'category_id' => $dosX20->id,
            'stock' => 40,
            'is_active' => true,
            'is_featured' => true
        ]);

        // Crear productos para Chispa Fría - 3x30
        $producto2 = Product::create([
            'title' => 'Chispa Fría Plateada 3x30',
            'description' => 'Efectos de chispas frías plateadas en formato 3x30. Ideal para eventos de gran escala.',
            'price' => 42.90,
            'sku' => 'CF-3X30-001',
            'category_id' => $tresX30->id,
            'stock' => 35,
            'is_active' => true,
            'is_featured' => false
        ]);

        // Crear productos para Chispa Fría - 4x30
        $producto3 = Product::create([
            'title' => 'Chispa Fría Multicolor 4x30',
            'description' => 'Impresionante despliegue de chispas multicolores en formato 4x30. Duración extendida.',
            'price' => 58.50,
            'sku' => 'CF-4X30-001',
            'category_id' => $cuatroX30->id,
            'stock' => 22,
            'is_active' => true,
            'is_featured' => true
        ]);

        // Crear productos para Fuegos Artificiales - 9 Tiros
        $producto4 = Product::create([
            'title' => 'Fantasía Dorada 9 Tiros',
            'description' => 'Espectacular batería de 9 tiros con efectos dorados y plateados. Ideal para finales de eventos.',
            'price' => 45.99,
            'sku' => 'FA-9T-001',
            'category_id' => $nueveTiros->id,
            'stock' => 25,
            'is_active' => true,
            'is_featured' => true
        ]);

        // Crear productos para Fuegos Artificiales - 16 Tiros
        $producto5 = Product::create([
            'title' => 'Espectáculo Real 16 Tiros',
            'description' => 'La batería más espectacular de nuestra colección. 16 disparos con efectos únicos y coloridos.',
            'price' => 89.99,
            'sku' => 'FA-16T-001',
            'category_id' => $dieciseisTiros->id,
            'stock' => 12,
            'is_active' => true,
            'is_featured' => true
        ]);

        // Crear productos para Fuegos Artificiales - 32 Tiros
        $producto6 = Product::create([
            'title' => 'Gran Final 32 Tiros',
            'description' => 'La batería más grande disponible. 32 tiros consecutivos para un final apoteósico.',
            'price' => 159.99,
            'sku' => 'FA-32T-001',
            'category_id' => $treintaDosTiros->id,
            'stock' => 8,
            'is_active' => true,
            'is_featured' => true
        ]);

        // Crear productos para Maquinaria - Detonador Inalámbrico
        $producto7 = Product::create([
            'title' => 'Detonador Inalámbrico Pro',
            'description' => 'Sistema de detonación inalámbrica profesional. Alcance de hasta 500 metros.',
            'price' => 299.99,
            'sku' => 'MAQ-DET-001',
            'category_id' => $detonadorInalambrico->id,
            'stock' => 15,
            'is_active' => true,
            'is_featured' => true
        ]);

        // Crear productos para Maquinaria - Humo Vertical
        $producto8 = Product::create([
            'title' => 'Máquina Humo Vertical DMX',
            'description' => 'Máquina de humo vertical con control DMX. Perfecta para espectáculos profesionales.',
            'price' => 450.00,
            'sku' => 'MAQ-HV-001',
            'category_id' => $humoVertical->id,
            'stock' => 6,
            'is_active' => true,
            'is_featured' => false
        ]);

        // Crear productos para Humo - Bengala
        $producto9 = Product::create([
            'title' => 'Bengala de Humo Azul',
            'description' => 'Bengala de humo de color azul intenso. Duración de 2 minutos.',
            'price' => 12.50,
            'sku' => 'HUM-BEN-001',
            'category_id' => $bengala->id,
            'stock' => 50,
            'is_active' => true,
            'is_featured' => false
        ]);

        // Crear productos para Humo - Pote
        $producto10 = Product::create([
            'title' => 'Pote de Humo Multicolor',
            'description' => 'Pote de humo que cambia de color durante su combustión. Efecto sorprendente.',
            'price' => 18.75,
            'sku' => 'HUM-POT-001',
            'category_id' => $pote->id,
            'stock' => 30,
            'is_active' => true,
            'is_featured' => true
        ]);

        // Crear productos para Pirotecnia
        $producto11 = Product::create([
            'title' => 'Kit Pirotécnico Profesional',
            'description' => 'Kit completo para pirotécnicos profesionales. Incluye accesorios y material de seguridad.',
            'price' => 125.00,
            'sku' => 'PIRO-KIT-001',
            'category_id' => $pirotecnia->id,
            'stock' => 10,
            'is_active' => true,
            'is_featured' => true
        ]);

        // Crear productos para Velas - Bengalas
        $producto12 = Product::create([
            'title' => 'Bengala Dorada Premium',
            'description' => 'Bengala de mano con chispas doradas de alta calidad. Duración de 60 segundos.',
            'price' => 8.90,
            'sku' => 'VEL-BEN-001',
            'category_id' => $bengalasVelas->id,
            'stock' => 60,
            'is_active' => true,
            'is_featured' => false
        ]);

        // Crear productos para Velas - Sparkie
        $producto13 = Product::create([
            'title' => 'Vela Sparkie Plateada',
            'description' => 'Vela Sparkie con efectos plateados brillantes. Ideal para celebraciones.',
            'price' => 6.50,
            'sku' => 'VEL-SPA-001',
            'category_id' => $sparkie->id,
            'stock' => 80,
            'is_active' => true,
            'is_featured' => true
        ]);

        // Crear imágenes de ejemplo para todos los productos
        $productos = [
            $producto1, $producto2, $producto3, $producto4, $producto5, $producto6,
            $producto7, $producto8, $producto9, $producto10, $producto11, $producto12, $producto13
        ];

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