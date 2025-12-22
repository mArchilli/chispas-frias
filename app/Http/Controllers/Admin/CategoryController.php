<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories.
     */
    public function index(Request $request): Response
    {
        $search = $request->get('search');
        $parentFilter = $request->get('parent');

        $query = Category::with(['parent', 'children'])
            ->withCount(['children']);

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($parentFilter !== null) {
            if ($parentFilter === 'main') {
                $query->whereNull('parent_id');
            } elseif ($parentFilter === 'sub') {
                $query->whereNotNull('parent_id');
            }
        }

        $categories = $query->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->through(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'parent' => $category->parent ? [
                        'id' => $category->parent->id,
                        'name' => $category->parent->name
                    ] : null,
                    'children_count' => $category->children_count,
                    'products_count' => $this->getTotalProductsCount($category),
                    'sort_order' => $category->sort_order,
                    'is_active' => $category->is_active,
                    'created_at' => $category->created_at->format('d/m/Y H:i'),
                    'updated_at' => $category->updated_at->format('d/m/Y H:i'),
                ];
            });

        $mainCategories = Category::main()->active()->orderBy('sort_order')->get(['id', 'name']);

        return Inertia::render('Admin/Categories/Index', [
            'categories' => $categories,
            'mainCategories' => $mainCategories,
            'filters' => [
                'search' => $search ?? '',
                'parent' => $parentFilter ?? '',
            ]
        ]);
    }

    /**
     * Show the form for creating a new category.
     */
    public function create(): Response
    {
        $mainCategories = Category::main()->active()->orderBy('sort_order')->get(['id', 'name']);

        return Inertia::render('Admin/Categories/Create', [
            'mainCategories' => $mainCategories
        ]);
    }

    /**
     * Store a newly created category in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'parent_id' => 'nullable|exists:categories,id',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean'
        ]);

        // Generate slug from name
        $validated['slug'] = Str::slug($validated['name']);

        // Ensure unique slug
        $originalSlug = $validated['slug'];
        $counter = 1;
        while (Category::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $originalSlug . '-' . $counter;
            $counter++;
        }

        Category::create($validated);

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Categoría creada exitosamente.');
    }

    /**
     * Display the specified category.
     */
    public function show(Category $category): Response
    {
        $category->load(['parent', 'children.products', 'products.images']);

        $categoryData = [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'parent' => $category->parent ? [
                'id' => $category->parent->id,
                'name' => $category->parent->name,
                'slug' => $category->parent->slug
            ] : null,
            'children' => $category->children->map(function ($child) {
                return [
                    'id' => $child->id,
                    'name' => $child->name,
                    'slug' => $child->slug,
                    'products_count' => $child->products->count(),
                    'is_active' => $child->is_active
                ];
            }),
            'products' => $category->products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'title' => $product->title,
                    'price' => $product->price,
                    'stock' => $product->stock,
                    'is_active' => $product->is_active,
                    'primary_image' => $product->primaryImage()?->path
                ];
            }),
            'sort_order' => $category->sort_order,
            'is_active' => $category->is_active,
            'created_at' => $category->created_at->format('d/m/Y H:i'),
            'updated_at' => $category->updated_at->format('d/m/Y H:i'),
        ];

        return Inertia::render('Admin/Categories/Show', [
            'category' => $categoryData
        ]);
    }

    /**
     * Show the form for editing the specified category.
     */
    public function edit(Category $category): Response
    {
        $mainCategories = Category::main()
            ->active()
            ->where('id', '!=', $category->id) // Exclude current category
            ->orderBy('sort_order')
            ->get(['id', 'name']);

        $categoryData = [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'parent_id' => $category->parent_id,
            'sort_order' => $category->sort_order,
            'is_active' => $category->is_active,
        ];

        return Inertia::render('Admin/Categories/Edit', [
            'category' => $categoryData,
            'mainCategories' => $mainCategories
        ]);
    }

    /**
     * Update the specified category in storage.
     */
    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'parent_id' => 'nullable|exists:categories,id',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean'
        ]);

        // Prevent category from being its own parent
        if (isset($validated['parent_id']) && $validated['parent_id'] == $category->id) {
            return back()->withErrors(['parent_id' => 'Una categoría no puede ser padre de sí misma.']);
        }

        // Prevent circular relationships
        if (isset($validated['parent_id'])) {
            $potentialParent = Category::find($validated['parent_id']);
            if ($potentialParent && $potentialParent->parent_id == $category->id) {
                return back()->withErrors(['parent_id' => 'Esta selección crearía una relación circular.']);
            }
        }

        // Generate new slug if name changed
        if ($validated['name'] !== $category->name) {
            $validated['slug'] = Str::slug($validated['name']);
            
            // Ensure unique slug (excluding current category)
            $originalSlug = $validated['slug'];
            $counter = 1;
            while (Category::where('slug', $validated['slug'])->where('id', '!=', $category->id)->exists()) {
                $validated['slug'] = $originalSlug . '-' . $counter;
                $counter++;
            }
        }

        $category->update($validated);

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Categoría actualizada exitosamente.');
    }

    /**
     * Remove the specified category from storage.
     */
    public function destroy(Category $category): RedirectResponse
    {
        // Check if category has products
        if ($category->products()->count() > 0) {
            return back()->withErrors(['delete' => 'No se puede eliminar una categoría que tiene productos asociados.']);
        }

        // Check if category has children
        if ($category->children()->count() > 0) {
            return back()->withErrors(['delete' => 'No se puede eliminar una categoría que tiene subcategorías.']);
        }

        $category->delete();

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Categoría eliminada exitosamente.');
    }

    /**
     * Toggle category status
     */
    public function toggleStatus(Category $category): RedirectResponse
    {
        $category->update(['is_active' => !$category->is_active]);

        $status = $category->is_active ? 'activada' : 'desactivada';
        
        return back()->with('success', "Categoría {$status} exitosamente.");
    }

    /**
     * Calculate total products count including subcategories
     */
    private function getTotalProductsCount(Category $category): int
    {
        // Count direct products
        $totalProducts = $category->products()->count();
        
        // If it's a main category, add products from subcategories
        if (!$category->parent_id) {
            $subcategories = $category->children;
            foreach ($subcategories as $subcategory) {
                $totalProducts += $subcategory->products()->count();
            }
        }
        
        return $totalProducts;
    }
}