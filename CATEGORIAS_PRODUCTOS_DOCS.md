# Sistema de Categorías y Productos - Documentación

## Estructura de Tablas

### Categories
- `id`: Primary key
- `name`: Nombre de la categoría
- `description`: Descripción (nullable)
- `slug`: URL amigable (unique)
- `parent_id`: Referencia a categoría padre (nullable)
- `sort_order`: Orden de visualización
- `is_active`: Estado activo/inactivo
- `timestamps`

### Products
- `id`: Primary key
- `title`: Título del producto
- `description`: Descripción del producto
- `price`: Precio (decimal 10,2)
- `sku`: Código único del producto (nullable)
- `category_id`: Referencia a categoría
- `stock`: Cantidad en stock
- `is_active`: Estado activo/inactivo
- `is_featured`: Producto destacado
- `timestamps`

### Product Images
- `id`: Primary key
- `product_id`: Referencia al producto
- `path`: Ruta de la imagen
- `alt_text`: Texto alternativo (nullable)
- `sort_order`: Orden de visualización
- `is_primary`: Imagen principal
- `timestamps`

## Ejemplos de Uso

### 1. Obtener categorías principales con subcategorías

```php
use App\Models\Category;

// Categorías principales activas con subcategorías
$mainCategories = Category::main()
    ->active()
    ->with(['activeChildren'])
    ->orderBy('sort_order')
    ->get();

foreach ($mainCategories as $category) {
    echo "Categoría: " . $category->name . "\n";
    foreach ($category->activeChildren as $subcategory) {
        echo "  → " . $subcategory->name . "\n";
    }
}
```

### 2. Obtener todas las subcategorías

```php
// Todas las subcategorías agrupadas por categoría padre
$subcategories = Category::subcategories()
    ->active()
    ->with('parent')
    ->get()
    ->groupBy('parent.name');

foreach ($subcategories as $parentName => $subs) {
    echo "Subcategorías de {$parentName}:\n";
    foreach ($subs as $subcategory) {
        echo "  - " . $subcategory->name . "\n";
    }
}
```

### 3. Obtener producto con categoría y categoría padre

```php
use App\Models\Product;

$product = Product::with(['category.parent', 'images'])
    ->find(1);

if ($product) {
    echo "Producto: " . $product->title . "\n";
    echo "Categoría: " . $product->category->name . "\n";
    
    if ($product->category->parent) {
        echo "Categoría Padre: " . $product->category->parent->name . "\n";
        echo "Ruta: " . $product->category->full_path . "\n";
    }
    
    echo "Imagen principal: " . $product->primaryImage()?->url . "\n";
}
```

### 4. Obtener productos por categoría (incluyendo subcategorías)

```php
// Productos de una categoría principal (incluyendo subcategorías)
$category = Category::where('slug', 'fuegos-artificiales')->first();

if ($category->isMain()) {
    $products = Product::whereHas('category', function($query) use ($category) {
        $query->where('id', $category->id)
              ->orWhere('parent_id', $category->id);
    })
    ->active()
    ->with(['images', 'category.parent'])
    ->get();
} else {
    $products = $category->activeProducts()->with('images')->get();
}
```

### 5. Búsqueda de productos

```php
// Búsqueda con filtros
$searchTerm = "fantasía";
$categoryId = 1; // Fuegos Artificiales
$minPrice = 20;
$maxPrice = 100;

$products = Product::query()
    ->with(['category.parent', 'images'])
    ->active()
    ->where(function($q) use ($searchTerm) {
        $q->where('title', 'LIKE', "%{$searchTerm}%")
          ->orWhere('description', 'LIKE', "%{$searchTerm}%");
    })
    ->whereHas('category', function($q) use ($categoryId) {
        $q->where('id', $categoryId)
          ->orWhere('parent_id', $categoryId);
    })
    ->whereBetween('price', [$minPrice, $maxPrice])
    ->orderBy('title')
    ->get();
```

### 6. Productos destacados por categoría

```php
$featuredByCategory = Category::main()
    ->active()
    ->with([
        'activeProducts' => function($query) {
            $query->featured()
                  ->with('images')
                  ->limit(4);
        }
    ])
    ->get()
    ->filter(function($category) {
        return $category->activeProducts->count() > 0;
    });

foreach ($featuredByCategory as $category) {
    echo "Destacados en {$category->name}:\n";
    foreach ($category->activeProducts as $product) {
        echo "  - {$product->title} ({$product->formatted_price})\n";
    }
}
```

### 7. Estadísticas de categorías

```php
$stats = Category::main()
    ->active()
    ->withCount([
        'children as subcategories_count',
        'products as direct_products_count'
    ])
    ->get()
    ->map(function($category) {
        // Productos en subcategorías
        $subcategoryProducts = Product::whereHas('category', function($query) use ($category) {
            $query->where('parent_id', $category->id);
        })->count();
        
        $category->total_products = $category->direct_products_count + $subcategoryProducts;
        return $category;
    });

foreach ($stats as $category) {
    echo "{$category->name}:\n";
    echo "  Subcategorías: {$category->subcategories_count}\n";
    echo "  Productos directos: {$category->direct_products_count}\n";
    echo "  Productos totales: {$category->total_products}\n\n";
}
```

### 8. Trabajar con imágenes

```php
$product = Product::with('images')->first();

// Obtener imagen principal
$primaryImage = $product->primaryImage();

// Cambiar imagen principal
$newPrimaryImage = $product->images()->find(2);
$newPrimaryImage->setPrimary();

// Agregar nueva imagen
$product->images()->create([
    'path' => 'products/nueva-imagen.jpg',
    'alt_text' => 'Nueva imagen del producto',
    'sort_order' => $product->images()->count() + 1,
    'is_primary' => false
]);
```

## Comandos Artisan útiles

```bash
# Ejecutar migraciones
php artisan migrate

# Ejecutar seeders
php artisan db:seed --class=CategoriesAndProductsSeeder

# Refrescar base de datos con seeders
php artisan migrate:refresh --seed

# Crear nueva migración
php artisan make:migration add_column_to_products_table

# Crear nuevo modelo
php artisan make:model NewModel -m

# Crear factory
php artisan make:factory ProductFactory
```

## Datos de Ejemplo Creados

### Categorías Principales:
1. **Fuegos Artificiales** (slug: fuegos-artificiales)
   - 9 Tiros (slug: 9-tiros)
   - 16 Tiros (slug: 16-tiros)

2. **Chispas Frías** (slug: chispas-frias)
   - 2x20 (slug: 2x20)
   - 3x20 (slug: 3x20)

3. **Maquinaria** (slug: maquinaria)

### Productos de Ejemplo:
- Fantasía Dorada 9 Tiros ($45.99)
- Lluvia de Estrellas 9 Tiros ($52.50)
- Espectáculo Real 16 Tiros ($89.99)
- Chispa Fría Dorada 2x20 ($28.75)
- Chispa Fría Plateada 3x20 ($38.90)
- Máquina de Humo Profesional ($299.99)

Cada producto incluye:
- Al menos 2 imágenes (principal y secundaria)
- Productos destacados tienen una imagen adicional
- Stock configurado
- SKUs únicos