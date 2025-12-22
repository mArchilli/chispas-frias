<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear categorías principales
        $chispasFrias = Category::create([
            'name' => 'Chispa Fría',
            'description' => 'Efectos especiales seguros para eventos',
            'slug' => 'chispa-fria',
            'parent_id' => null,
            'sort_order' => 1,
            'is_active' => true
        ]);

        $fuegosArtificiales = Category::create([
            'name' => 'Fuegos Artificiales',
            'description' => 'Espectaculares fuegos artificiales para celebraciones',
            'slug' => 'fuegos-artificiales',
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

        $humo = Category::create([
            'name' => 'Humo',
            'description' => 'Productos de humo para efectos especiales',
            'slug' => 'humo',
            'parent_id' => null,
            'sort_order' => 4,
            'is_active' => true
        ]);

        $pirotecnia = Category::create([
            'name' => 'Pirotecnia',
            'description' => 'Productos pirotécnicos especializados',
            'slug' => 'pirotecnia',
            'parent_id' => null,
            'sort_order' => 5,
            'is_active' => true
        ]);

        $velas = Category::create([
            'name' => 'Velas',
            'description' => 'Velas y bengalas especiales',
            'slug' => 'velas',
            'parent_id' => null,
            'sort_order' => 6,
            'is_active' => true
        ]);

        // Crear subcategorías para Chispa Fría
        Category::create([
            'name' => '2x20',
            'description' => 'Chispas frías formato 2x20',
            'slug' => '2x20',
            'parent_id' => $chispasFrias->id,
            'sort_order' => 1,
            'is_active' => true
        ]);

        Category::create([
            'name' => '3x30',
            'description' => 'Chispas frías formato 3x30',
            'slug' => '3x30',
            'parent_id' => $chispasFrias->id,
            'sort_order' => 2,
            'is_active' => true
        ]);

        Category::create([
            'name' => '4x30',
            'description' => 'Chispas frías formato 4x30',
            'slug' => '4x30',
            'parent_id' => $chispasFrias->id,
            'sort_order' => 3,
            'is_active' => true
        ]);

        Category::create([
            'name' => '5x1',
            'description' => 'Chispas frías formato 5x1',
            'slug' => '5x1',
            'parent_id' => $chispasFrias->id,
            'sort_order' => 4,
            'is_active' => true
        ]);

        Category::create([
            'name' => 'Con mecha',
            'description' => 'Chispas frías con mecha',
            'slug' => 'con-mecha',
            'parent_id' => $chispasFrias->id,
            'sort_order' => 5,
            'is_active' => true
        ]);

        // Crear subcategorías para Fuegos Artificiales
        Category::create([
            'name' => '9 Tiros',
            'description' => 'Fuegos artificiales de 9 disparos',
            'slug' => '9-tiros',
            'parent_id' => $fuegosArtificiales->id,
            'sort_order' => 1,
            'is_active' => true
        ]);

        Category::create([
            'name' => '16 Tiros',
            'description' => 'Fuegos artificiales de 16 disparos',
            'slug' => '16-tiros',
            'parent_id' => $fuegosArtificiales->id,
            'sort_order' => 2,
            'is_active' => true
        ]);

        Category::create([
            'name' => '32 Tiros',
            'description' => 'Fuegos artificiales de 32 disparos',
            'slug' => '32-tiros',
            'parent_id' => $fuegosArtificiales->id,
            'sort_order' => 3,
            'is_active' => true
        ]);

        // Crear subcategorías para Maquinaria
        Category::create([
            'name' => 'Bastón de Mano',
            'description' => 'Bastones de mano para efectos pirotécnicos',
            'slug' => 'baston-de-mano',
            'parent_id' => $maquinaria->id,
            'sort_order' => 1,
            'is_active' => true
        ]);

        Category::create([
            'name' => 'Detonador Inalámbrico',
            'description' => 'Sistemas de detonación inalámbrica',
            'slug' => 'detonador-inalambrico',
            'parent_id' => $maquinaria->id,
            'sort_order' => 2,
            'is_active' => true
        ]);

        Category::create([
            'name' => 'Humo vertical',
            'description' => 'Máquinas de humo vertical',
            'slug' => 'humo-vertical',
            'parent_id' => $maquinaria->id,
            'sort_order' => 3,
            'is_active' => true
        ]);

        Category::create([
            'name' => 'Lanzallama',
            'description' => 'Equipos lanzallamas profesionales',
            'slug' => 'lanzallama',
            'parent_id' => $maquinaria->id,
            'sort_order' => 4,
            'is_active' => true
        ]);

        Category::create([
            'name' => 'Pistola',
            'description' => 'Pistolas para efectos especiales',
            'slug' => 'pistola',
            'parent_id' => $maquinaria->id,
            'sort_order' => 5,
            'is_active' => true
        ]);

        // Crear subcategorías para Humo
        Category::create([
            'name' => 'Bengala',
            'description' => 'Bengalas de humo',
            'slug' => 'bengala',
            'parent_id' => $humo->id,
            'sort_order' => 1,
            'is_active' => true
        ]);

        Category::create([
            'name' => 'Pote',
            'description' => 'Potes de humo',
            'slug' => 'pote',
            'parent_id' => $humo->id,
            'sort_order' => 2,
            'is_active' => true
        ]);

        Category::create([
            'name' => 'Torta',
            'description' => 'Tortas de humo',
            'slug' => 'torta',
            'parent_id' => $humo->id,
            'sort_order' => 3,
            'is_active' => true
        ]);

        // Crear subcategorías para Velas
        Category::create([
            'name' => 'Bengalas',
            'description' => 'Bengalas especiales',
            'slug' => 'bengalas',
            'parent_id' => $velas->id,
            'sort_order' => 1,
            'is_active' => true
        ]);

        Category::create([
            'name' => 'Sparkie',
            'description' => 'Velas Sparkie',
            'slug' => 'sparkie',
            'parent_id' => $velas->id,
            'sort_order' => 2,
            'is_active' => true
        ]);
    }
}