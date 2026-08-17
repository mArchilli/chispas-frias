import React, { useMemo, useRef, useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AdminLayout from '@/Layouts/AdminLayout';
import OfferDiscountFields from '@/Components/OfferDiscountFields';
import { getProductImageUrl } from '@/utils/images';
import { IconSearch, IconPhoto, IconCheck, IconChevronLeft, IconChevronRight } from '@/Components/Admin/Icons';

const inputClasses =
    'block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 transition focus:border-navy focus:outline-none focus:ring-2 focus:ring-navy/10';

function todayDateTimeLocal() {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T00:00`;
}

function ProductPicker({ products, categories, selectedId, onSelect, disabled }) {
    const [search, setSearch] = useState('');
    const [categoryId, setCategoryId] = useState('');
    const scrollerRef = useRef(null);

    const filtered = useMemo(() => {
        const term = search.trim().toLowerCase();
        return products.filter((product) => {
            const matchesSearch = !term || product.title.toLowerCase().includes(term);
            const matchesCategory = !categoryId || String(product.category?.id) === categoryId;
            return matchesSearch && matchesCategory;
        });
    }, [products, search, categoryId]);

    const scroll = (direction) => {
        scrollerRef.current?.scrollBy({ left: direction * 300, behavior: 'smooth' });
    };

    return (
        <div>
            <div className="flex flex-col gap-2 sm:flex-row">
                <div className="relative flex-1">
                    <IconSearch className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        type="text"
                        placeholder="Buscar producto..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        disabled={disabled}
                        className={`${inputClasses} pl-9`}
                    />
                </div>
                <select
                    value={categoryId}
                    onChange={(e) => setCategoryId(e.target.value)}
                    disabled={disabled}
                    className={`${inputClasses} sm:w-56`}
                >
                    <option value="">Todas las categorías</option>
                    {categories.map((category) => (
                        <option key={category.id} value={category.id}>
                            {category.name}
                        </option>
                    ))}
                </select>
            </div>

            <div className="relative mt-3">
                {filtered.length > 0 && (
                    <>
                        <button
                            type="button"
                            onClick={() => scroll(-1)}
                            className="absolute left-0 top-1/2 z-10 hidden h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 shadow-sm transition hover:text-navy sm:flex"
                        >
                            <IconChevronLeft className="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            onClick={() => scroll(1)}
                            className="absolute right-0 top-1/2 z-10 hidden h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 shadow-sm transition hover:text-navy sm:flex"
                        >
                            <IconChevronRight className="h-4 w-4" />
                        </button>
                    </>
                )}

                <div
                    ref={scrollerRef}
                    className="flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-smooth pb-1 sm:px-10"
                >
                    {filtered.map((product) => {
                        const selected = String(product.id) === String(selectedId);
                        const imageUrl = getProductImageUrl(product.primary_image);

                        return (
                            <button
                                key={product.id}
                                type="button"
                                onClick={() => onSelect(product.id)}
                                disabled={disabled}
                                className={`group relative w-32 flex-shrink-0 snap-start rounded-xl border-2 p-2 text-left transition sm:w-36 ${
                                    selected
                                        ? 'border-navy bg-navy/5'
                                        : 'border-transparent bg-white ring-1 ring-slate-200 hover:ring-slate-300'
                                } ${disabled ? 'pointer-events-none opacity-50' : ''}`}
                            >
                                <div className="relative aspect-square overflow-hidden rounded-lg bg-slate-100">
                                    {imageUrl ? (
                                        <img
                                            src={imageUrl}
                                            alt={product.title}
                                            loading="lazy"
                                            className="h-full w-full object-cover"
                                        />
                                    ) : (
                                        <div className="flex h-full w-full items-center justify-center">
                                            <IconPhoto className="h-6 w-6 text-slate-300" />
                                        </div>
                                    )}
                                    {product.has_active_offer && (
                                        <span className="absolute left-1 top-1 rounded-full bg-rose-600 px-1.5 py-0.5 text-[9px] font-bold leading-none text-white shadow-sm">
                                            OFERTA
                                        </span>
                                    )}
                                    {selected && (
                                        <span className="absolute right-1 top-1 flex h-5 w-5 items-center justify-center rounded-full bg-navy text-white shadow-sm">
                                            <IconCheck className="h-3 w-3" strokeWidth={3} />
                                        </span>
                                    )}
                                </div>
                                <p className="mt-1.5 line-clamp-2 text-xs font-medium text-slate-900">
                                    {product.title}
                                </p>
                                <p className="text-xs text-slate-500">${product.price.toLocaleString('es-AR')}</p>
                            </button>
                        );
                    })}

                    {filtered.length === 0 && (
                        <p className="w-full py-8 text-center text-sm text-slate-500">
                            No se encontraron productos con esos filtros.
                        </p>
                    )}
                </div>
            </div>
        </div>
    );
}

export default function Create({ products = [], categories = [] }) {
    const { data, setData, post, errors, processing } = useForm({
        product_id: '',
        tipo_descuento: 'porcentaje',
        valor_descuento: '',
        alcance: 'todos',
        product_price_tier_id: null,
        start_date: todayDateTimeLocal(),
        end_date: '',
        is_active: true,
    });

    const selectedProduct = products.find((p) => String(p.id) === String(data.product_id)) || null;

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.offers.store'), {
            onSuccess: () => toast.success('Oferta creada exitosamente'),
            onError: () => toast.error('Revisá los datos del formulario'),
        });
    };

    return (
        <AdminLayout
            header={
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <Link href={route('admin.offers.index')} className="hover:text-slate-600">
                                Ofertas
                            </Link>
                        </p>
                        <h1 className="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">Nueva oferta</h1>
                    </div>
                    <Link
                        href={route('admin.offers.index')}
                        className="inline-flex items-center justify-center rounded-lg border border-slate-300 px-3.5 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    >
                        Cancelar
                    </Link>
                </div>
            }
        >
            <Head title="Nueva oferta - Admin" />

            <div className="mx-auto max-w-4xl">
                <form onSubmit={submit} className="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div className="space-y-6">
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-slate-700">
                                Producto <span className="text-rose-500">*</span>
                            </label>
                            <ProductPicker
                                products={products}
                                categories={categories}
                                selectedId={data.product_id}
                                onSelect={(id) => setData('product_id', id)}
                                disabled={processing}
                            />
                            {errors.product_id && (
                                <p className="mt-1.5 text-xs font-medium text-rose-600">{errors.product_id}</p>
                            )}
                            {selectedProduct?.has_active_offer && (
                                <p className="mt-1.5 text-xs text-amber-600">
                                    Este producto ya tiene una oferta activa: crear una nueva la reemplaza.
                                </p>
                            )}
                        </div>

                        {selectedProduct ? (
                            <OfferDiscountFields
                                data={data}
                                setData={setData}
                                errors={errors}
                                product={selectedProduct}
                                disabled={processing}
                            />
                        ) : (
                            <p className="text-sm text-slate-500">
                                Seleccioná un producto para configurar el descuento.
                            </p>
                        )}

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label
                                    htmlFor="start_date"
                                    className="mb-1.5 block text-sm font-medium text-slate-700"
                                >
                                    Fecha de inicio
                                </label>
                                <input
                                    id="start_date"
                                    type="datetime-local"
                                    value={data.start_date}
                                    onChange={(e) => setData('start_date', e.target.value)}
                                    disabled={processing}
                                    className={inputClasses}
                                />
                                {errors.start_date && (
                                    <p className="mt-1.5 text-xs font-medium text-rose-600">{errors.start_date}</p>
                                )}
                            </div>
                            <div>
                                <label htmlFor="end_date" className="mb-1.5 block text-sm font-medium text-slate-700">
                                    Fecha de fin
                                </label>
                                <input
                                    id="end_date"
                                    type="datetime-local"
                                    value={data.end_date}
                                    onChange={(e) => setData('end_date', e.target.value)}
                                    disabled={processing}
                                    className={inputClasses}
                                />
                                {errors.end_date && (
                                    <p className="mt-1.5 text-xs font-medium text-rose-600">{errors.end_date}</p>
                                )}
                            </div>
                        </div>
                        <p className="-mt-3 text-xs text-slate-500">
                            La oferta arranca hoy por defecto. Dejá "Fecha de fin" vacía para que quede activa hasta
                            que la desactives.
                        </p>

                        <div className="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-3.5">
                            <div>
                                <p className="text-sm font-medium text-slate-900">Oferta activa</p>
                                <p className="text-xs text-slate-500">
                                    {data.is_active
                                        ? 'Se aplica de inmediato (según las fechas configuradas).'
                                        : 'Queda guardada pero sin aplicar.'}
                                </p>
                            </div>
                            <label className="relative inline-flex cursor-pointer items-center">
                                <input
                                    type="checkbox"
                                    checked={data.is_active}
                                    onChange={(e) => setData('is_active', e.target.checked)}
                                    disabled={processing}
                                    className="peer sr-only"
                                />
                                <div className="peer h-6 w-11 rounded-full bg-slate-300 transition-colors after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-gold peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-gold/20" />
                            </label>
                        </div>
                    </div>

                    <div className="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-6">
                        <Link
                            href={route('admin.offers.index')}
                            className="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            Cancelar
                        </Link>
                        <button
                            type="submit"
                            disabled={processing || !selectedProduct}
                            className="inline-flex items-center rounded-lg bg-gold px-4 py-2 text-sm font-semibold text-navy transition hover:brightness-95 disabled:opacity-50"
                        >
                            {processing ? 'Guardando...' : 'Crear oferta'}
                        </button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
