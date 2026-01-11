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
        
        #region Producto 1: 2 Bases Inalambricas + 1 Control Remoto
        // Buscar o crear la categoría "Maquinaria"
        $maquinariaCategory = Category::firstOrCreate(
            ['name' => 'Maquinaria'],
            [
                'slug' => 'maquinaria',
                'description' => 'Equipos y maquinaria para fuegos artificiales',
                'is_active' => true,
                'sort_order' => 1
            ]
        );

        // Buscar o crear la subcategoría "Detonador Inalambrico"
        $detonadorSubcategory = Category::firstOrCreate(
            [
                'name' => 'Detonador Inalambrico',
                'parent_id' => $maquinariaCategory->id
            ],
            [
                'slug' => 'detonador-inalambrico',
                'description' => 'Sistemas de detonación inalámbrica para fuegos artificiales',
                'is_active' => true,
                'sort_order' => 1
            ]
        );

        // Crear el producto
        $product = Product::create([
            'title' => '2 Bases Inalambricas + 1 Control Remoto',
            'description' => "<p>El sistema de detonadores para fuegos artificiales es una herramienta profesional pensada para garantizar máxima seguridad, precisión y comodidad en la ejecución de espectáculos pirotécnicos. Este completo set incluye:</p>
<ul>
<li>2 Bases Inalambricas ( Cada base lleva 2 pilas AA incluidas)</li>
<li>1 Control Remoto( pila a23 incluida)</li>
<li><strong>NO INCLUYE Maletín</strong></li>
</ul>
<p>Ideal para eventos masivos, festivales, recitales, shows privados o celebraciones importantes, este sistema permite un control absoluto sobre los fuegos artificiales, reduciendo riesgos y mejorando la presentación final. Su diseño compacto, resistente y fácil de usar lo convierte en una opción indispensable para pirotécnicos, organizadores de eventos y entusiastas que buscan seguridad, eficiencia y espectacularidad en cada lanzamiento.</p>
<p><em>*Imágenes a modo ilustrativo</em></p>",
            'price' => 150000.00,
            'sku' => 'DETON-2B-1C',
            'category_id' => $detonadorSubcategory->id,
            'is_active' => true,
            'is_featured' => true
        ]);

        // Crear la imagen del producto
        ProductImage::create([
            'product_id' => $product->id,
            'path' => '2-bases-inalambricas-1control-remoto.png',
            'alt_text' => '2 Bases Inalambricas + 1 Control Remoto',
            'sort_order' => 1,
            'is_primary' => true,
            'type' => 'image',
            'mime_type' => 'image/png'
        ]);

        #endregion

        #region Producto 2: 4 Bases Inalambricas + 1 Control Remoto
        // Crear el segundo producto
        $product2 = Product::create([
            'title' => '4 Bases Inalambricas + 1 Control Remoto',
            'description' => "<p>El sistema de detonadores para fuegos artificiales es una herramienta profesional pensada para garantizar máxima seguridad, precisión y comodidad en la ejecución de espectáculos pirotécnicos. Este completo set incluye:</p>
<ul>
<li>4 Bases Inalambricas ( Cada base lleva 2 pilas AA incluidas)</li>
<li>1 Control Remoto( pila a23 incluida)</li>
<li><strong>NO INCLUYE Maletín</strong></li>
</ul>
<p>Ideal para eventos masivos, festivales, recitales, shows privados o celebraciones importantes, este sistema permite un control absoluto sobre los fuegos artificiales, reduciendo riesgos y mejorando la presentación final. Su diseño compacto, resistente y fácil de usar lo convierte en una opción indispensable para pirotécnicos, organizadores de eventos y entusiastas que buscan seguridad, eficiencia y espectacularidad en cada lanzamiento.</p>
<p><em>*Imágenes a modo ilustrativo</em></p>",
            'price' => 241900.00,
            'sku' => 'DETON-4B-1C',
            'category_id' => $detonadorSubcategory->id,
            'is_active' => true,
            'is_featured' => true
        ]);

        // Crear la imagen del segundo producto
        ProductImage::create([
            'product_id' => $product2->id,
            'path' => '4-bases-inalambricas-1control-remoto.png',
            'alt_text' => '4 Bases Inalambricas + 1 Control Remoto',
            'sort_order' => 1,
            'is_primary' => true,
            'type' => 'image',
            'mime_type' => 'image/png'
        ]);

        #endregion

        #region Producto 3: Chispa Fria 2mts x 20seg
        // Buscar o crear la categoría "Chispas Frias"
        $chispasFriasCategory = Category::firstOrCreate(
            ['name' => 'Chispas Frias'],
            [
                'slug' => 'chispas-frias',
                'description' => 'Chispas de fuego frío para eventos',
                'is_active' => true,
                'sort_order' => 2
            ]
        );

        // Buscar o crear la subcategoría "2x20"
        $chispas2x20Subcategory = Category::firstOrCreate(
            [
                'slug' => '2x20',
            ],
            [
                'name' => '2x20',
                'parent_id' => $chispasFriasCategory->id,
                'description' => 'Chispas frías de 2 metros por 20 segundos',
                'is_active' => true,
                'sort_order' => 1
            ]
        );

        // Crear el producto
        $product3 = Product::create([
            'title' => 'Chispa Fria 2mts x 20seg',
            'description' => "<p><strong>Chispas de Fuego Frio 2mts x 20 seg (Precio Unitario)</strong></p>
<ul>
<li>Seguras y de excelente calidad</li>
<li>Ideal para cumpleaños, festejos, casamientos, 15 años y todo tipo de eventos!</li>
<li>Aptas para uso interior y exterior</li>
</ul>
<p><strong>DURACION:</strong> 20 Segundos</p>
<p><strong>ALTURA:</strong> 2 metros</p>
<p><strong>COLOR:</strong> Plateado</p>
<p><strong>NOTA:</strong> Precio de Cartucho de Chispas. NO INCLUYEN conexion.</p>
<p><strong>Modo de USO:</strong></p>
<ol>
<li>Empalme de los dos extremos del iniciador con una extension de cable y realizar el contacto con 1 pila 9V o con un Transformador 12v</li>
<li>Conexion a Bases Inalambricas a Control Remoto</li>
<li>Conexion a Pistola de Chispas/Bastones de Chispa.</li>
</ol>
<p><em>*Imágenes a modo ilustrativo</em></p>",
            'price' => 6490.00,
            'sku' => 'CF-2M-20S',
            'category_id' => $chispas2x20Subcategory->id,
            'is_active' => true,
            'is_featured' => true
        ]);

        // Crear la imagen del producto
        ProductImage::create([
            'product_id' => $product3->id,
            'path' => 'chispa-fria-2mts-x-20-seg.jpg',
            'alt_text' => 'Chispa Fria 2mts x 20seg',
            'sort_order' => 1,
            'is_primary' => true,
            'type' => 'image',
            'mime_type' => 'image/jpeg'
        ]);

        #endregion

        #region Producto 4: Chispa Fria 3mts x 30seg
        // Buscar o crear la subcategoría "3x30"
        $chispas3x30Subcategory = Category::firstOrCreate(
            [
                'slug' => '3x30',
            ],
            [
                'name' => '3x30',
                'parent_id' => $chispasFriasCategory->id,
                'description' => 'Chispas frías de 3 metros por 30 segundos',
                'is_active' => true,
                'sort_order' => 2
            ]
        );

        // Crear el producto
        $product4 = Product::create([
            'title' => 'Chispa Fria 3mts x 30seg',
            'description' => "<p><strong>Chispas de Fuego Frio 3mts x 30 seg (Precio Unitario)</strong></p>
<ul>
<li>Seguras y de excelente calidad</li>
<li>Ideal para cumpleaños, festejos, casamientos, 15 años y todo tipo de eventos!</li>
<li>Aptas para uso interior y exterior</li>
</ul>
<p><strong>DURACION:</strong> 30 Segundos</p>
<p><strong>ALTURA:</strong> 3 metros</p>
<p><strong>COLOR:</strong> Plateado</p>
<p><strong>NOTA:</strong> Precio de Cartucho de Chispas. NO INCLUYEN conexion.</p>
<p><strong>Modo de USO:</strong></p>
<ol>
<li>Empalme de los dos extremos del iniciador con una extension de cable y realizar el contacto con 1 pila 9V o con un Transformador 12v</li>
<li>Conexion a Bases Inalambricas a Control Remoto</li>
<li>Conexion a Pistola de Chispas/Bastones de Chispa.</li>
</ol>
<p><em>*Imágenes a modo ilustrativo</em></p>",
            'price' => 10304.00,
            'sku' => 'CF-3M-30S',
            'category_id' => $chispas3x30Subcategory->id,
            'is_active' => true,
            'is_featured' => true
        ]);

        // Crear la imagen del producto
        ProductImage::create([
            'product_id' => $product4->id,
            'path' => 'chispa-fria-3mts-x30seg.png',
            'alt_text' => 'Chispa Fria 3mts x 30seg',
            'sort_order' => 1,
            'is_primary' => true,
            'type' => 'image',
            'mime_type' => 'image/png'
        ]);

        #endregion

        #region Producto 5: Chispa Fria 4mts x 30seg
        // Buscar o crear la subcategoría "4x30"
        $chispas4x30Subcategory = Category::firstOrCreate(
            [
                'slug' => '4x30',
            ],
            [
                'name' => '4x30',
                'parent_id' => $chispasFriasCategory->id,
                'description' => 'Chispas frías de 4 metros por 30 segundos',
                'is_active' => true,
                'sort_order' => 3
            ]
        );

        // Crear el producto
        $product5 = Product::create([
            'title' => 'Chispa Fria 4mts x 30seg',
            'description' => "<p><strong>Chispas de Fuego Frio 4mts x 30 seg (Precio Unitario)</strong></p>
<ul>
<li>Seguras y de excelente calidad</li>
<li>Ideal para cumpleaños, festejos, casamientos, 15 años y todo tipo de eventos!</li>
<li>Aptas para uso interior y exterior</li>
</ul>
<p><strong>DURACION:</strong> 30 Segundos</p>
<p><strong>ALTURA:</strong> 4 metros</p>
<p><strong>COLOR:</strong> Plateado</p>
<p><strong>NOTA:</strong> Precio de Cartucho de Chispas. NO INCLUYEN conexion.</p>
<p><strong>Modo de USO:</strong></p>
<ol>
<li>Empalme de los dos extremos del iniciador con una extension de cable y realizar el contacto con 1 pila 9V o con un Transformador 12v</li>
<li>Conexion a Bases Inalambricas a Control Remoto</li>
<li>Conexion a Pistola de Chispas/Bastones de Chispa.</li>
</ol>
<p><em>*Imágenes a modo ilustrativo</em></p>",
            'price' => 11760.00,
            'sku' => 'CF-4M-30S',
            'category_id' => $chispas4x30Subcategory->id,
            'is_active' => true,
            'is_featured' => true
        ]);

        // Crear la imagen del producto
        ProductImage::create([
            'product_id' => $product5->id,
            'path' => 'chispa-fria-4mts-x30seg.png',
            'alt_text' => 'Chispa Fria 4mts x 30seg',
            'sort_order' => 1,
            'is_primary' => true,
            'type' => 'image',
            'mime_type' => 'image/png'
        ]);
        
        #endregion

        #region Producto 6: Chispa Fria 5mts x 1seg
        // Buscar o crear la subcategoría "5x1"
        $chispas5x1Subcategory = Category::firstOrCreate(
            [
                'slug' => '5x1',
            ],
            [
                'name' => '5x1',
                'parent_id' => $chispasFriasCategory->id,
                'description' => 'Chispas frías de 5 metros por 1 segundo',
                'is_active' => true,
                'sort_order' => 4
            ]
        );

        // Crear el producto
        $product6 = Product::create([
            'title' => 'Chispa Fria 5mts x 1seg',
            'description' => "<p><strong>Chispas de Fuego Frio 5mts x 1 seg (Precio Unitario)</strong></p>
                <ul>
                <li>Seguras y de excelente calidad</li>
                <li>Ideal para cumpleaños, festejos, casamientos, 15 años y todo tipo de eventos!</li>
                <li>Aptas para uso exterior</li>
                </ul>
                <p><strong>DURACION:</strong> 1 Segundos</p>
                <p><strong>ALTURA:</strong> 5 metros</p>
                <p><strong>COLOR:</strong> Plateado</p>
                <p><strong>NOTA:</strong> Precio de Cartucho de Chispas. NO INCLUYEN conexion.</p>
                <p><strong>Modo de USO:</strong></p>
                <ol>
                <li>Empalme de los dos extremos del iniciador con una extension de cable y realizar el contacto con 1 pila 9V o con un Transformador 12v</li>
                <li>Conexion a Bases Inalambricas a Control Remoto</li>
                <li>Conexion a Pistola de Chispas/Bastones de Chispa.</li>
                </ol>
<p><em>*Imágenes a modo ilustrativo</em></p>",
            'price' => 10304.00,
            'sku' => 'CF-5M-1S',
            'category_id' => $chispas5x1Subcategory->id,
            'is_active' => true,
            'is_featured' => true
        ]);

        // Crear la imagen del producto
        ProductImage::create([
            'product_id' => $product6->id,
            'path' => 'chispa-fria-5mts-x1seg.png',
            'alt_text' => 'Chispa Fria 5mts x 1seg',
            'sort_order' => 1,
            'is_primary' => true,
            'type' => 'image',
            'mime_type' => 'image/png'
        ]);
        
        #endregion

        #region Producto 7: Chispa Fria 2mts x 20seg con mecha
        // Crear el producto (usa la subcategoría 2x20 que ya existe)
        $product7 = Product::create([
            'title' => 'Chispa Fria 2mts x 20seg con mecha',
            'description' => "<p><strong>Chispas de Fuego Frio 2mts x 20 seg con Mecha (Precio Unitario)</strong></p>
                <ul>
                <li>Seguras y de excelente calidad</li>
                <li>Ideal para cumpleaños, festejos, casamientos, 15 años y todo tipo de eventos!</li>
                <li>Aptas para uso interior y exterior</li>
                </ul>
                <p><strong>DURACION:</strong> 20 Segundos</p>
                <p><strong>ALTURA:</strong> 2 metros</p>
                <p><strong>COLOR:</strong> Plateado</p>
                <p><strong>NOTA:</strong> Precio de Cartucho de Chispas. Incluye la mecha para el encendido</p>
                <p><strong>Modo de USO:</strong></p>
                <ol>
                <li>Encender la mecha de la chispa fria y Alejarse.</li>
                </ol>
<p><em>*Imágenes a modo ilustrativo</em></p>",
            'price' => 2800.00,
            'sku' => 'CF-2M-20S-M',
            'category_id' => $chispas2x20Subcategory->id,
            'is_active' => true,
            'is_featured' => true
        ]);

        // Crear la imagen del producto
        ProductImage::create([
            'product_id' => $product7->id,
            'path' => 'chispafria-2m-x20seg-mecha.jpg',
            'alt_text' => 'Chispa Fria 2mts x 20seg con mecha',
            'sort_order' => 1,
            'is_primary' => true,
            'type' => 'image',
            'mime_type' => 'image/jpeg'
        ]);

        #endregion

        #region Producto 8: Kit Detonacion 2 chispas 2mts x 20seg
        // Crear el producto (usa la subcategoría 2x20 que ya existe)
        $product8 = Product::create([
            'title' => 'Kit Detonacion 2 Chispas 2mts x 20seg',
            'description' => "<p><strong>El Kit de Detonación de 2 Chispas 2mts x 20seg</strong></p>
                <p>Es un conjunto completo y práctico diseñado para el montaje y encendido seguro de fuegos artificiales. Incluye todos los elementos esenciales, ya <strong>CONECTADO Y LISTO</strong> para su uso. Incluido las 2 chispas. 2 bases de madera, 3mts de cable entre Chispas y 5mts mas hasta el TRANSFORMADOR.</p>
                <p>Este sistema es ideal tanto para eventos profesionales como celebraciones privadas, ya que facilita la sincronización de varios artefactos pirotécnicos, logrando un espectáculo visual coordinado y de alto impacto. Su diseño simple, funcional y seguro permite una instalación rápida, reduciendo el riesgo y garantizando resultados impresionantes.</p>
                <p>Perfecto para bodas, aniversarios, shows y festivales, el Kit de Detonación brinda la combinación ideal de seguridad, eficiencia y potencia, asegurando un encendido impecable y un espectáculo inolvidable.</p>
<p><em>*Imágenes a modo ilustrativo</em></p>",
            'price' => 45000.00,
            'sku' => 'KIT-2CF-2M-20S',
            'category_id' => $chispas2x20Subcategory->id,
            'is_active' => true,
            'is_featured' => true
        ]);

        // Crear la imagen del producto
        ProductImage::create([
            'product_id' => $product8->id,
            'path' => 'kit-detonacion-2-chispas-2mts-x20seg.png',
            'alt_text' => 'Kit Detonacion 2 Chispas 2mts x 20seg',
            'sort_order' => 1,
            'is_primary' => true,
            'type' => 'image',
            'mime_type' => 'image/png'
        ]);

        #endregion

        #region Producto 9: Kit Detonacion 4 chispas 2mts x 20seg
        // Buscar o crear la subcategoría "4x20"
        $chispas4x20Subcategory = Category::firstOrCreate(
            [
                'slug' => '4x20',
            ],
            [
                'name' => '4x20',
                'parent_id' => $chispasFriasCategory->id,
                'description' => 'Chispas frías de 4 metros por 20 segundos',
                'is_active' => true,
                'sort_order' => 5
            ]
        );

        // Crear el producto
        $product9 = Product::create([
            'title' => 'Kit Detonación 4 Chispas 2mts x 20 segundos',
            'description' => "<p><strong>El Kit de Detonación de 4 Chispas 2mts x 20seg</strong></p>
                <p>Es un conjunto completo y práctico diseñado para el montaje y encendido seguro de fuegos artificiales. Incluye todos los elementos esenciales, ya <strong>CONECTADO Y LISTO</strong> para su uso. Incluido las 4 chispas. 4 bases de madera, 5mts de cable entre Chispas y 5mts mas hasta el TRANSFORMADOR.</p>
                <p>Este sistema es ideal tanto para eventos profesionales como celebraciones privadas, ya que facilita la sincronización de varios artefactos pirotécnicos, logrando un espectáculo visual coordinado y de alto impacto. Su diseño simple, funcional y seguro permite una instalación rápida, reduciendo el riesgo y garantizando resultados impresionantes.</p>
                <p>Perfecto para bodas, aniversarios, shows y festivales, el Kit de Detonación brinda la combinación ideal de seguridad, eficiencia y potencia, asegurando un encendido impecable y un espectáculo inolvidable.</p>
<p><em>*Imágenes a modo ilustrativo</em></p>",
            'price' => 80000.00,
            'sku' => 'KIT-4CF-2M-20S',
            'category_id' => $chispas4x20Subcategory->id,
            'is_active' => true,
            'is_featured' => true
        ]);

        // Crear la imagen del producto
        ProductImage::create([
            'product_id' => $product9->id,
            'path' => 'kit-detonacion-4-chispas-2mts-x20seg.png',
            'alt_text' => 'Kit Detonación 4 Chispas 2mts x 20 segundos',
            'sort_order' => 1,
            'is_primary' => true,
            'type' => 'image',
            'mime_type' => 'image/png'
        ]);

        #endregion

        #region Producto 10: Kit Detonacion 4 chispas 3mts x 30seg
        // Crear el producto (usa la subcategoría 3x30 que ya existe)
        $product10 = Product::create([
            'title' => 'Kit Detonacion 4 Chispas 3mts x 30seg',
            'description' => "<p><strong>El Kit de Detonación de 4 Chispas 3mts x 30seg</strong></p>
                <p>Es un conjunto completo y práctico diseñado para el montaje y encendido seguro de fuegos artificiales. Incluye todos los elementos esenciales <strong>PREPARADO Y LISTO PARA EL USO</strong>. Incluye las 4 chispas, 4 bases de madera, 5 mts de cable entre chispas y 5mts hasta el TRANSFORMADOR</p>
                <p>Este sistema es ideal tanto para eventos profesionales como celebraciones privadas, ya que facilita la sincronización de varios artefactos pirotécnicos, logrando un espectáculo visual coordinado y de alto impacto. Su diseño simple, funcional y seguro permite una instalación rápida, reduciendo el riesgo y garantizando resultados impresionantes.</p>
                <p>Perfecto para bodas, aniversarios, shows y festivales, el Kit de Detonación brinda la combinación ideal de seguridad, eficiencia y potencia, asegurando un encendido impecable y un espectáculo inolvidable.</p>
<p><em>*Imágenes a modo ilustrativo</em></p>",
            'price' => 90000.00,
            'sku' => 'KIT-4CF-3M-30S',
            'category_id' => $chispas3x30Subcategory->id,
            'is_active' => true,
            'is_featured' => true
        ]);

        // Crear la imagen del producto
        ProductImage::create([
            'product_id' => $product10->id,
            'path' => 'kit-detonacion-4-chispas-3mts-x30seg.png',
            'alt_text' => 'Kit Detonacion 4 Chispas 3mts x 30seg',
            'sort_order' => 1,
            'is_primary' => true,
            'type' => 'image',
            'mime_type' => 'image/png'
        ]);

        #endregion

        #region Producto 11: Maletin 6 Bases Inalambricas + 2 Control Remoto
        // Crear el producto (usa la subcategoría Detonador Inalambrico que ya existe)
        $product11 = Product::create([
            'title' => 'Maletin 6 Bases Inalambricas + 2 Control Remoto',
            'description' => "<p>El sistema de detonadores para fuegos artificiales es una herramienta profesional pensada para garantizar máxima seguridad, precisión y comodidad en la ejecución de espectáculos pirotécnicos. Este completo set incluye:</p>
                <ul>
                <li>6 Bases Inalámbricas ( Cada base lleva 2 pilas AA incluidas)</li>
                <li>2 Controles Remotos ( 1 Control con un pulsador, 1 Control con 4 pulsadores, Pilas a23 Incluidas)</li>
                <li><strong>6 Chispas frias 2mts x 20seg de REGALO</strong></li>
                <li>Maletín rígido y acolchado que facilita su transporte y protección.</li>
                </ul>
                <p>Ideal para eventos masivos, festivales, recitales, shows privados o celebraciones importantes, este sistema permite un control absoluto sobre los fuegos artificiales, reduciendo riesgos y mejorando la presentación final. Su diseño compacto, resistente y fácil de usar lo convierte en una opción indispensable para pirotécnicos, organizadores de eventos y entusiastas que buscan seguridad, eficiencia y espectacularidad en cada lanzamiento.</p>
<p><em>*Imágenes a modo ilustrativo</em></p>",
            'price' => 293500.00,
            'sku' => 'MALETIN-6B-2C',
            'category_id' => $detonadorSubcategory->id,
            'is_active' => true,
            'is_featured' => true
        ]);

        // Crear la imagen del producto
        ProductImage::create([
            'product_id' => $product11->id,
            'path' => 'maletin-6-bases-inalambricas-2control-remoto.png',
            'alt_text' => 'Maletin 6 Bases Inalambricas + 2 Control Remoto',
            'sort_order' => 1,
            'is_primary' => true,
            'type' => 'image',
            'mime_type' => 'image/png'
        ]);

        #endregion

        #region Producto 12: Maletin 8 Detonadores Inalambricos + 2 Control Remoto
        // Crear el producto (usa la subcategoría Detonador Inalambrico que ya existe)
        $product12 = Product::create([
            'title' => 'Maletin 8 Detonadores Inalambricos + 2 Controles',
            'description' => "<p>Este conjunto de detonadores para fuegos artificiales está diseñado para ofrecer precisión, seguridad y control total en cada espectáculo pirotécnico. El kit incluye:</p>
                <ul>
                <li>8 Detonadores Inalambricos organizados en cuatro módulos numerados (A, B, C y D)</li>
                <li>2 Controles Remotos</li>
                <li>Maletín rígido y acolchado que facilita su transporte y protección</li>
                </ul>
                <p>Permiten activar múltiples efectos a distancia con total sincronización, ideales para eventos profesionales, celebraciones y shows coordinados.</p>
<p><em>*Imágenes a modo ilustrativo</em></p>",
            'price' => 360600.00,
            'sku' => 'MALETIN-8D-2C',
            'category_id' => $detonadorSubcategory->id,
            'is_active' => true,
            'is_featured' => true
        ]);

        // Crear la imagen del producto
        ProductImage::create([
            'product_id' => $product12->id,
            'path' => 'maletin-8-detonadores-inalambrico-2controles.png',
            'alt_text' => 'Maletin 8 Detonadores Inalambricos + 2 Controles',
            'sort_order' => 1,
            'is_primary' => true,
            'type' => 'image',
            'mime_type' => 'image/png'
        ]);

        #endregion

    }
}