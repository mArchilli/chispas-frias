import React from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import StatusDot from '@/Components/Admin/StatusDot';
import { getProductImageUrl } from '@/utils/images';
import { IconSearch, IconPhoto, IconInbox, IconChevronLeft } from '@/Components/Admin/Icons';

const inputClasses =
    'block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 transition focus:border-navy focus:outline-none focus:ring-2 focus:ring-navy/10';

function PriceCard({ product }) {
    const hasOffer = !!product.current_offer;

    return (
        <div className="rounded-xl border border-slate-200 bg-white transition hover:border-slate-300 hover:shadow-sm">
            <div className="relative aspect-square overflow-hidden rounded-t-xl bg-slate-100">
                {product.primary_image ? (
                    <img
                        src={getProductImageUrl(product.primary_image)}
                        alt={product.title}
                        loading="lazy"
                        className={`h-full w-full object-cover ${!product.is_active ? 'opacity-50' : ''}`}
                    />
                ) : (
                    <div className="flex h-full w-full items-center justify-center text-slate-300">
                        <IconPhoto className="h-8 w-8" />
                    </div>
                )}

                {hasOffer && (
                    <span
                        className="absolute left-1.5 top-1.5 rounded-full bg-rose-600 px-1.5 py-0.5 text-[10px] font-bold leading-none text-white shadow-sm"
                        title={`Oferta activa${
                            product.current_offer.percentage_discount
                                ? ` · -${Math.round(product.current_offer.percentage_discount)}%`
                                : ''
                        }`}
                    >
                        {product.current_offer.percentage_discount
                            ? `-${Math.round(product.current_offer.percentage_discount)}%`
                            : 'OFERTA'}
                    </span>
                )}
            </div>

            <div className="p-2.5 sm:p-3">
                <h3 className="line-clamp-2 text-xs font-medium text-slate-900 sm:text-sm">{product.title}</h3>
                <p className="mt-0.5 truncate text-[11px] text-slate-400 sm:text-xs">
                    {product.category.parent_name ? `${product.category.parent_name} · ` : ''}
                    {product.category.name}
                    {product.sku ? ` · SKU ${product.sku}` : ''}
                </p>

                <div className="mt-1.5 flex items-center gap-1.5 text-[11px] sm:text-xs">
                    <StatusDot active={product.is_active} title={product.is_active ? 'Visible' : 'Oculto'} />
                    <span className="text-slate-500">{product.is_active ? 'Visible' : 'Oculto'}</span>
                </div>

                <div className="mt-1.5 flex items-baseline gap-1.5">
                    {hasOffer && product.current_offer.formatted_offer_price ? (
                        <>
                            <span className="text-sm font-semibold text-slate-900 sm:text-base">
                                {product.current_offer.formatted_offer_price}
                            </span>
                            <span className="text-[11px] text-slate-400 line-through">{product.formatted_price}</span>
                        </>
                    ) : (
                        <span className="text-sm font-semibold text-slate-900 sm:text-base">
                            {product.formatted_price}
                        </span>
                    )}
                </div>

                {product.price_tiers?.length > 0 && (
                    <div className="mt-2 space-y-0.5 border-t border-slate-100 pt-2">
                        {product.price_tiers.map((tier) => (
                            <div key={tier.id} className="flex items-center justify-between text-[11px] text-slate-500">
                                <span>{tier.cantidad_minima}+ un.</span>
                                <span className="font-medium text-slate-600">
                                    ${new Intl.NumberFormat('es-AR').format(tier.precio_unitario)}
                                </span>
                            </div>
                        ))}
                    </div>
                )}

                {product.variants?.length > 0 && (
                    <div className="mt-2 border-t border-slate-100 pt-2">
                        <p className="mb-1 text-[10px] font-semibold uppercase tracking-wide text-slate-400">Colores</p>
                        <div className="space-y-0.5">
                            {product.variants.map((variant) => (
                                <div
                                    key={variant.id}
                                    className="flex items-center justify-between gap-1.5 text-[11px] text-slate-500"
                                >
                                    <span className="flex min-w-0 items-center gap-1">
                                        <span
                                            className="h-2.5 w-2.5 flex-shrink-0 rounded-full border border-slate-200"
                                            style={{ backgroundColor: variant.color_hex || '#e2e8f0' }}
                                        />
                                        <span className="truncate">{variant.name}</span>
                                    </span>
                                    <span className="flex flex-shrink-0 items-center gap-1.5 font-medium text-slate-600">
                                        {variant.price_addon > 0 && (
                                            <span>+${new Intl.NumberFormat('es-AR').format(variant.price_addon)}</span>
                                        )}
                                        <span className="text-slate-400">
                                            {variant.stock === null ? '∞' : `${variant.stock} u.`}
                                        </span>
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {product.addons?.length > 0 && (
                    <div className="mt-2 border-t border-slate-100 pt-2">
                        <p className="mb-1 text-[10px] font-semibold uppercase tracking-wide text-slate-400">Add-ons</p>
                        <div className="space-y-0.5">
                            {product.addons.map((addon) => (
                                <div
                                    key={addon.id}
                                    className="flex items-center justify-between gap-1.5 text-[11px] text-slate-500"
                                >
                                    <span className="truncate">{addon.name}</span>
                                    <span className="flex-shrink-0 font-medium text-slate-600">
                                        +${new Intl.NumberFormat('es-AR').format(addon.price_effective)}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

export default function Index({ products, categories, selectedMainCategory, selectedSubcategories, filters = {} }) {
    const searchForm = useForm({
        search: filters?.search || '',
        category: filters?.category || '',
    });

    const hasActiveFilters = !!(filters?.search || filters?.category);

    const navigate = (overrides = {}) => {
        router.get(
            route('admin.prices.index'),
            {
                search: searchForm.data.search,
                category: searchForm.data.category,
                ...overrides,
            },
            { preserveState: true, replace: true }
        );
    };

    const handleSearch = (e) => {
        e.preventDefault();
        navigate({ search: searchForm.data.search });
    };

    const handleCategoryFilter = (categoryId) => {
        searchForm.setData('category', categoryId);
        navigate({ category: categoryId });
    };

    const goBackToMainCategories = () => handleCategoryFilter('');

    const clearFilters = () => {
        searchForm.reset();
        navigate({ search: '', category: '' });
    };

    return (
        <AdminLayout
            header={
                <div>
                    <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                        {products?.total ?? 0} {products?.total === 1 ? 'producto' : 'productos'}
                    </p>
                    <h1 className="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">Precios</h1>
                </div>
            }
        >
            <Head title="Precios - Admin" />

            <div className="space-y-4">
                {/* Búsqueda */}
                <form onSubmit={handleSearch} className="relative">
                    <IconSearch className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        type="text"
                        placeholder="Buscar por título, SKU..."
                        value={searchForm.data.search}
                        onChange={(e) => searchForm.setData('search', e.target.value)}
                        className={`${inputClasses} pl-9`}
                    />
                </form>

                {/* Filtro de categorías: botonera de dos niveles, igual criterio que el
                    catálogo público (ver ProductController@index) — primero categorías
                    principales; al elegir una, se listan sus subcategorías. */}
                <div className="flex flex-wrap items-center gap-2">
                    {selectedMainCategory ? (
                        <>
                            <button
                                type="button"
                                onClick={goBackToMainCategories}
                                className="flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
                            >
                                <IconChevronLeft className="h-4 w-4" />
                                Categorías
                            </button>

                            <span className="rounded-lg bg-navy/10 px-3.5 py-1.5 text-sm font-medium text-navy">
                                {selectedMainCategory.name}
                            </span>

                            <button
                                type="button"
                                onClick={() => handleCategoryFilter(String(selectedMainCategory.id))}
                                className={`rounded-lg px-3.5 py-1.5 text-sm font-medium transition ${
                                    String(searchForm.data.category) === String(selectedMainCategory.id)
                                        ? 'bg-navy text-white'
                                        : 'border border-slate-300 text-slate-700 hover:bg-slate-50'
                                }`}
                            >
                                Todas las {selectedMainCategory.name}
                            </button>

                            {selectedSubcategories?.map((subcategory) => (
                                <button
                                    key={subcategory.id}
                                    type="button"
                                    onClick={() => handleCategoryFilter(String(subcategory.id))}
                                    className={`rounded-lg px-3.5 py-1.5 text-sm font-medium transition ${
                                        String(searchForm.data.category) === String(subcategory.id)
                                            ? 'bg-navy text-white'
                                            : 'border border-slate-300 text-slate-700 hover:bg-slate-50'
                                    }`}
                                >
                                    {subcategory.name}
                                </button>
                            ))}
                        </>
                    ) : (
                        <>
                            <button
                                type="button"
                                onClick={() => handleCategoryFilter('')}
                                className={`rounded-lg px-3.5 py-1.5 text-sm font-medium transition ${
                                    !searchForm.data.category
                                        ? 'bg-navy text-white'
                                        : 'border border-slate-300 text-slate-700 hover:bg-slate-50'
                                }`}
                            >
                                Todas
                            </button>
                            {categories?.map((category) => (
                                <button
                                    key={category.id}
                                    type="button"
                                    onClick={() => handleCategoryFilter(String(category.id))}
                                    className="rounded-lg border border-slate-300 px-3.5 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                                >
                                    {category.name}
                                    {category.children?.length > 0 && (
                                        <span className="ml-1 text-xs text-slate-400">
                                            ({category.children.length})
                                        </span>
                                    )}
                                </button>
                            ))}
                        </>
                    )}

                    {hasActiveFilters && (
                        <button
                            type="button"
                            onClick={clearFilters}
                            className="rounded-lg px-3.5 py-1.5 text-sm font-medium text-slate-500 transition hover:bg-slate-100"
                        >
                            Limpiar
                        </button>
                    )}
                </div>

                {/* Grid de precios */}
                {products?.data?.length > 0 ? (
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-5">
                        {products.data.map((product) => (
                            <PriceCard key={product.id} product={product} />
                        ))}
                    </div>
                ) : (
                    <div className="rounded-xl border border-slate-200 bg-white py-16 text-center">
                        <IconInbox className="mx-auto h-8 w-8 text-slate-300" />
                        <h3 className="mt-3 text-sm font-medium text-slate-900">
                            {hasActiveFilters ? 'No se encontraron resultados' : 'No hay productos creados'}
                        </h3>
                        <p className="mt-1 text-sm text-slate-500">
                            {hasActiveFilters
                                ? 'Probá ajustar los filtros aplicados.'
                                : 'Todavía no hay productos cargados en el catálogo.'}
                        </p>
                    </div>
                )}

                {/* Paginación */}
                {products?.data?.length > 0 && products?.links?.length > 3 && (
                    <div className="flex flex-col items-center justify-between gap-3 border-t border-slate-200 pt-4 sm:flex-row">
                        <p className="text-sm text-slate-500">
                            Mostrando <span className="font-medium text-slate-700">{products?.from || 0}</span>–
                            <span className="font-medium text-slate-700">{products?.to || 0}</span> de{' '}
                            <span className="font-medium text-slate-700">{products?.total || 0}</span>
                        </p>
                        <nav className="flex flex-wrap items-center gap-1">
                            {products.links.map((link, index) =>
                                link.url ? (
                                    <Link
                                        key={index}
                                        href={link.url}
                                        preserveScroll
                                        className={`flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-sm font-medium transition ${
                                            link.active ? 'bg-navy text-white' : 'text-slate-600 hover:bg-slate-100'
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : (
                                    <span
                                        key={index}
                                        className="flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-sm text-slate-300"
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                )
                            )}
                        </nav>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
