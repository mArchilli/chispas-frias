import React, { useState } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AdminLayout from '@/Layouts/AdminLayout';
import Dropdown from '@/Components/Dropdown';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal';
import OfferDiscountFields from '@/Components/OfferDiscountFields';
import ActionIconButton from '@/Components/Admin/ActionIconButton';
import StatusDot from '@/Components/Admin/StatusDot';
import usePermissions from '@/hooks/usePermissions';
import { getProductImageUrl } from '@/utils/images';
import { isOutOfStock, isLowStock } from '@/utils/stock';
import {
    IconPlus,
    IconSearch,
    IconTag,
    IconStar,
    IconPencil,
    IconDotsVertical,
    IconPhoto,
    IconInbox,
    IconX,
    IconAlertOctagon,
} from '@/Components/Admin/Icons';

const inputClasses =
    'block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 transition focus:border-navy focus:outline-none focus:ring-2 focus:ring-navy/10';

function stockMeta(stock) {
    if (isOutOfStock(stock)) return { text: 'Sin stock', className: 'text-rose-600', title: 'Sin stock' };
    if (isLowStock(stock))
        return { text: `${stock} u.`, className: 'text-amber-600', title: `Stock bajo · ${stock} unidades` };
    return { text: `${stock} u.`, className: 'text-slate-500', title: `${stock} unidades disponibles` };
}

function ProductCard({ product, onToggleFeatured, onOfferAction, onRemoveOffer, onDelete }) {
    const { isAdmin } = usePermissions();
    const hasOffer = !!product.current_offer;
    const stock = stockMeta(product.stock);

    return (
        <div className="rounded-xl border border-slate-200 bg-white transition hover:border-slate-300 hover:shadow-sm">
            {/* overflow-hidden solo acá (no en la card entera): si no, recorta el
                menú del kebab, que es descendiente y queda "position: absolute" */}
            <Link
                href={route('admin.products.edit', product.id)}
                className="relative block aspect-square overflow-hidden rounded-t-xl bg-slate-100"
            >
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
                {product.is_featured && (
                    <span
                        className="absolute right-1.5 top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-gold text-navy shadow-sm"
                        title="Producto destacado"
                    >
                        <IconStar filled className="h-3 w-3" />
                    </span>
                )}
            </Link>

            <div className="p-2.5 sm:p-3">
                <Link href={route('admin.products.edit', product.id)}>
                    <h3 className="line-clamp-2 text-xs font-medium text-slate-900 sm:text-sm">{product.title}</h3>
                </Link>
                <p className="mt-0.5 truncate text-[11px] text-slate-400 sm:text-xs">
                    {product.category.parent_name ? `${product.category.parent_name} · ` : ''}
                    {product.category.name}
                </p>

                <div className="mt-1.5 flex items-center gap-1.5 text-[11px] sm:text-xs">
                    <StatusDot active={product.is_active} title={product.is_active ? 'Visible' : 'Oculto'} />
                    <span title={stock.title} className={stock.className}>
                        {stock.text}
                    </span>
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

                <div className="mt-2.5 flex items-center gap-0.5 border-t border-slate-100 pt-2">
                    <ActionIconButton
                        onClick={() => onOfferAction(product)}
                        icon={IconTag}
                        label={hasOffer ? 'Editar oferta' : 'Crear oferta'}
                        tone={hasOffer ? 'active' : 'default'}
                    />
                    <ActionIconButton
                        onClick={() => onToggleFeatured(product.id)}
                        icon={IconStar}
                        iconProps={{ filled: product.is_featured }}
                        label={product.is_featured ? 'Quitar destacado' : 'Destacar'}
                        tone={product.is_featured ? 'active' : 'default'}
                    />
                    <ActionIconButton href={route('admin.products.edit', product.id)} icon={IconPencil} label="Editar" />
                    <div className="ml-auto">
                        <Dropdown align="right" width="48">
                            <Dropdown.Trigger>
                                <button
                                    type="button"
                                    className="flex h-8 w-8 items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                                    title="Más acciones"
                                >
                                    <IconDotsVertical className="h-4 w-4" />
                                </button>
                            </Dropdown.Trigger>
                            <Dropdown.Content contentClasses="py-1 bg-white rounded-xl border border-slate-200 shadow-lg">
                                <Dropdown.Link href={route('admin.products.show', product.id)}>Ver detalle</Dropdown.Link>
                                {isAdmin && hasOffer && (
                                    <button
                                        type="button"
                                        onClick={() => onRemoveOffer(product)}
                                        className="block w-full px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-100"
                                    >
                                        Eliminar oferta
                                    </button>
                                )}
                                {isAdmin && (
                                    <button
                                        type="button"
                                        onClick={() => onDelete(product)}
                                        className="block w-full px-4 py-2 text-left text-sm !text-rose-600 hover:!bg-rose-50"
                                    >
                                        Eliminar producto
                                    </button>
                                )}
                            </Dropdown.Content>
                        </Dropdown>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function Index({ products, categories, filters = {} }) {
    const { isAdmin } = usePermissions();
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [productToDelete, setProductToDelete] = useState(null);
    const [isDeleting, setIsDeleting] = useState(false);

    // Estados para el modal de ofertas
    const [showOfferModal, setShowOfferModal] = useState(false);
    const [selectedProduct, setSelectedProduct] = useState(null);
    const [isProcessingOffer, setIsProcessingOffer] = useState(false);
    const [isEditMode, setIsEditMode] = useState(false);

    // Estados para el modal de eliminar oferta
    const [showDeleteOfferModal, setShowDeleteOfferModal] = useState(false);
    const [offerToDelete, setOfferToDelete] = useState(null);
    const [isDeletingOffer, setIsDeletingOffer] = useState(false);

    const searchForm = useForm({
        search: filters?.search || '',
        category: filters?.category || '',
        status: filters?.status || '',
        stock: filters?.stock || '',
    });

    const offerForm = useForm({
        tipo_descuento: 'porcentaje',
        valor_descuento: '',
        alcance: 'todos',
        product_price_tier_id: null,
        start_date: '',
        end_date: '',
        is_active: true,
    });

    const hasActiveFilters = !!(filters?.search || filters?.category || filters?.status || filters?.stock);

    const handleSearch = (e) => {
        e.preventDefault();
        searchForm.get(route('admin.products.index'), { preserveState: true, replace: true });
    };

    const clearFilters = () => {
        // No usar searchForm.get() acá: reset() programa la actualización de forma
        // asíncrona, así que un get() disparado en el mismo tick todavía ve los
        // filtros viejos (closure de React desactualizado).
        searchForm.reset();
        router.get(route('admin.products.index'), {}, { preserveState: true, replace: true });
    };

    const handleDeleteProduct = (product) => {
        setProductToDelete(product);
        setShowDeleteModal(true);
    };

    const confirmDelete = () => {
        if (!productToDelete) return;
        setIsDeleting(true);
        router.delete(route('admin.products.destroy', productToDelete.id), {
            onSuccess: () => {
                toast.success('Producto eliminado exitosamente');
                setShowDeleteModal(false);
                setProductToDelete(null);
                setIsDeleting(false);
            },
            onError: () => {
                toast.error('Error al eliminar el producto');
                setIsDeleting(false);
            },
        });
    };

    const closeDeleteModal = () => {
        if (!isDeleting) {
            setShowDeleteModal(false);
            setProductToDelete(null);
        }
    };

    const toggleFeatured = (productId) => {
        router.patch(
            route('admin.products.toggle-featured', productId),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => toast.success('Estado destacado actualizado'),
                onError: () => toast.error('Error al actualizar el estado destacado'),
            }
        );
    };

    const handleOfferAction = (product) => {
        if (product.current_offer) {
            handleEditOffer(product, product.current_offer);
            return;
        }

        setSelectedProduct(product);
        setShowOfferModal(true);
        setIsEditMode(false);
        offerForm.reset();
    };

    const handleEditOffer = (product, offer) => {
        setSelectedProduct(product);
        setShowOfferModal(true);
        setIsEditMode(true);

        const formatDateForInput = (dateString) => {
            if (!dateString) return '';
            return new Date(dateString).toISOString().slice(0, 16);
        };

        offerForm.setData({
            tipo_descuento: offer.tipo_descuento || 'porcentaje',
            valor_descuento: offer.valor_descuento ?? '',
            alcance: offer.alcance || 'todos',
            product_price_tier_id: offer.product_price_tier_id ?? null,
            start_date: formatDateForInput(offer.start_date),
            end_date: formatDateForInput(offer.end_date),
            is_active: offer.is_active,
        });
    };

    const closeOfferModal = () => {
        if (!isProcessingOffer) {
            setShowOfferModal(false);
            setSelectedProduct(null);
            setIsEditMode(false);
            offerForm.reset();
        }
    };

    const createQuickOffer = () => {
        if (!selectedProduct || !offerForm.data.valor_descuento) return;

        setIsProcessingOffer(true);

        const payload = {
            tipo_descuento: offerForm.data.tipo_descuento,
            valor_descuento: offerForm.data.valor_descuento,
            alcance: offerForm.data.alcance,
            product_price_tier_id: offerForm.data.alcance === 'especifico' ? offerForm.data.product_price_tier_id : null,
            start_date: offerForm.data.start_date || null,
            end_date: offerForm.data.end_date || null,
            is_active: offerForm.data.is_active,
        };

        const onDone = (message) => ({
            onSuccess: () => {
                toast.success(message);
                closeOfferModal();
                setIsProcessingOffer(false);
            },
            onError: () => {
                toast.error(`Error al ${isEditMode ? 'actualizar' : 'crear'} la oferta`);
                setIsProcessingOffer(false);
            },
        });

        if (isEditMode && selectedProduct.current_offer) {
            router.put(
                route('admin.products.offers.update', selectedProduct.current_offer.id),
                payload,
                onDone('Oferta actualizada exitosamente')
            );
        } else {
            router.post(
                route('admin.products.quick-offer', selectedProduct.id),
                payload,
                onDone('Oferta creada exitosamente')
            );
        }
    };

    const removeOffer = (product) => {
        setOfferToDelete(product);
        setShowDeleteOfferModal(true);
    };

    const removeOfferFromEditModal = () => {
        removeOffer(selectedProduct);
        closeOfferModal();
    };

    const confirmDeleteOffer = () => {
        if (!offerToDelete?.current_offer) return;

        setIsDeletingOffer(true);
        router.delete(route('admin.offers.destroy', offerToDelete.current_offer.id), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Oferta eliminada exitosamente');
                setShowDeleteOfferModal(false);
                setOfferToDelete(null);
                setIsDeletingOffer(false);
            },
            onError: () => {
                toast.error('Error al eliminar la oferta');
                setIsDeletingOffer(false);
            },
        });
    };

    const closeDeleteOfferModal = () => {
        if (!isDeletingOffer) {
            setShowDeleteOfferModal(false);
            setOfferToDelete(null);
        }
    };

    return (
        <AdminLayout
            header={
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                            {products?.total ?? 0} {products?.total === 1 ? 'producto' : 'productos'}
                        </p>
                        <h1 className="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">Productos</h1>
                    </div>
                    <Link
                        href={route('admin.products.create')}
                        className="inline-flex items-center gap-1.5 rounded-lg bg-gold px-3.5 py-2 text-sm font-semibold text-navy transition hover:brightness-95"
                    >
                        <IconPlus className="h-4 w-4" />
                        Nuevo producto
                    </Link>
                </div>
            }
        >
            <Head title="Productos - Admin" />

            <div className="space-y-4">
                {/* Filtros */}
                <form onSubmit={handleSearch} className="space-y-3">
                    <div className="relative">
                        <IconSearch className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            type="text"
                            placeholder="Buscar por título, SKU..."
                            value={searchForm.data.search}
                            onChange={(e) => searchForm.setData('search', e.target.value)}
                            className={`${inputClasses} pl-9`}
                        />
                    </div>
                    <div className="grid grid-cols-3 gap-2">
                        <select
                            value={searchForm.data.category}
                            onChange={(e) => searchForm.setData('category', e.target.value)}
                            className={inputClasses}
                        >
                            <option value="">Categoría</option>
                            {categories?.map((category) => (
                                <option key={category.id} value={category.id}>
                                    {category.name}
                                </option>
                            ))}
                        </select>
                        <select
                            value={searchForm.data.status}
                            onChange={(e) => searchForm.setData('status', e.target.value)}
                            className={inputClasses}
                        >
                            <option value="">Estado</option>
                            <option value="active">Activos</option>
                            <option value="inactive">Inactivos</option>
                            <option value="featured">Destacados</option>
                        </select>
                        <select
                            value={searchForm.data.stock}
                            onChange={(e) => searchForm.setData('stock', e.target.value)}
                            className={inputClasses}
                        >
                            <option value="">Stock</option>
                            <option value="in_stock">Con stock</option>
                            <option value="low_stock">Stock bajo</option>
                            <option value="out_of_stock">Sin stock</option>
                        </select>
                    </div>
                    <div className="flex gap-2">
                        <button
                            type="submit"
                            className="rounded-lg border border-slate-300 px-3.5 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            Buscar
                        </button>
                        {hasActiveFilters && (
                            <button
                                type="button"
                                onClick={clearFilters}
                                className="rounded-lg px-3.5 py-2 text-sm font-medium text-slate-500 transition hover:bg-slate-100"
                            >
                                Limpiar
                            </button>
                        )}
                    </div>
                </form>

                {/* Grid de productos */}
                {products?.data?.length > 0 ? (
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-5">
                        {products.data.map((product) => (
                            <ProductCard
                                key={product.id}
                                product={product}
                                onToggleFeatured={toggleFeatured}
                                onOfferAction={handleOfferAction}
                                onRemoveOffer={removeOffer}
                                onDelete={handleDeleteProduct}
                            />
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
                                : 'Comenzá creando tu primer producto para el catálogo.'}
                        </p>
                        {!hasActiveFilters && (
                            <Link
                                href={route('admin.products.create')}
                                className="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-gold px-4 py-2 text-sm font-semibold text-navy transition hover:brightness-95"
                            >
                                <IconPlus className="h-4 w-4" />
                                Crear primer producto
                            </Link>
                        )}
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

            <DeleteConfirmationModal
                show={showDeleteModal}
                onClose={closeDeleteModal}
                onConfirm={confirmDelete}
                title="¿Eliminar producto?"
                message="Estás a punto de eliminar el siguiente producto:"
                itemName={productToDelete?.title}
                warningMessage="Esta acción eliminará el producto y todas sus imágenes asociadas."
                confirmText="Eliminar producto"
                processing={isDeleting}
            />

            {/* Modal: crear/editar oferta */}
            {showOfferModal && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4"
                    onClick={closeOfferModal}
                >
                    <div
                        className="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-xl bg-white p-5 shadow-xl sm:p-6"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="flex items-center justify-between">
                            <h3 className="text-base font-semibold text-slate-900">
                                {isEditMode ? 'Editar oferta' : 'Crear oferta'}
                            </h3>
                            <button
                                onClick={closeOfferModal}
                                disabled={isProcessingOffer}
                                className="flex h-7 w-7 items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-100"
                            >
                                <IconX className="h-4 w-4" />
                            </button>
                        </div>

                        <div className="mt-3 rounded-lg bg-slate-50 px-3 py-2.5">
                            <p className="text-sm font-medium text-slate-900">{selectedProduct?.title}</p>
                            <p className="text-xs text-slate-500">Precio actual: {selectedProduct?.formatted_price}</p>
                        </div>

                        <div className="mt-4 space-y-4">
                            <OfferDiscountFields
                                data={offerForm.data}
                                setData={offerForm.setData}
                                errors={offerForm.errors}
                                product={selectedProduct}
                                disabled={isProcessingOffer}
                            />

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-slate-700">
                                        Fecha de inicio
                                    </label>
                                    <input
                                        type="datetime-local"
                                        className={inputClasses}
                                        value={offerForm.data.start_date}
                                        onChange={(e) => offerForm.setData('start_date', e.target.value)}
                                        disabled={isProcessingOffer}
                                    />
                                    {offerForm.errors.start_date && (
                                        <p className="mt-1 text-xs text-rose-600">{offerForm.errors.start_date}</p>
                                    )}
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-slate-700">Fecha de fin</label>
                                    <input
                                        type="datetime-local"
                                        className={inputClasses}
                                        value={offerForm.data.end_date}
                                        onChange={(e) => offerForm.setData('end_date', e.target.value)}
                                        disabled={isProcessingOffer}
                                    />
                                    {offerForm.errors.end_date && (
                                        <p className="mt-1 text-xs text-rose-600">{offerForm.errors.end_date}</p>
                                    )}
                                </div>
                            </div>
                            <p className="text-xs text-slate-500">
                                Opcional. Sin fechas, la oferta queda activa hasta que la desactives.
                            </p>

                            <label className="flex items-center gap-2 text-sm text-slate-700">
                                <input
                                    type="checkbox"
                                    checked={offerForm.data.is_active}
                                    onChange={(e) => offerForm.setData('is_active', e.target.checked)}
                                    disabled={isProcessingOffer}
                                    className="rounded border-slate-300 text-navy focus:ring-navy/30"
                                />
                                Oferta activa
                            </label>
                        </div>

                        <div className="mt-6 flex items-center gap-3">
                            {isAdmin && isEditMode && (
                                <button
                                    type="button"
                                    onClick={removeOfferFromEditModal}
                                    disabled={isProcessingOffer}
                                    className="mr-auto text-sm font-medium text-rose-600 transition hover:text-rose-700"
                                >
                                    Eliminar oferta
                                </button>
                            )}
                            <button
                                onClick={closeOfferModal}
                                disabled={isProcessingOffer}
                                className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                            >
                                Cancelar
                            </button>
                            <button
                                onClick={createQuickOffer}
                                disabled={isProcessingOffer || !offerForm.data.valor_descuento}
                                className="rounded-lg bg-gold px-4 py-2 text-sm font-semibold text-navy transition hover:brightness-95 disabled:opacity-50"
                            >
                                {isProcessingOffer
                                    ? isEditMode
                                        ? 'Actualizando...'
                                        : 'Creando...'
                                    : isEditMode
                                      ? 'Actualizar oferta'
                                      : 'Crear oferta'}
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Modal: eliminar oferta */}
            {showDeleteOfferModal && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4"
                    onClick={closeDeleteOfferModal}
                >
                    <div
                        className="w-full max-w-sm rounded-xl bg-white p-5 shadow-xl sm:p-6"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-rose-50">
                            <IconAlertOctagon className="h-5 w-5 text-rose-600" />
                        </div>
                        <h3 className="mt-3 text-base font-semibold text-slate-900">Eliminar oferta</h3>
                        <p className="mt-1 text-sm text-slate-500">
                            ¿Eliminar la oferta de{' '}
                            <span className="font-medium text-slate-700">"{offerToDelete?.title}"</span>?
                        </p>

                        {offerToDelete?.current_offer && (
                            <div className="mt-3 space-y-1 rounded-lg bg-slate-50 p-3 text-sm">
                                <div className="flex justify-between text-slate-500">
                                    <span>Precio regular</span>
                                    <span>{offerToDelete.formatted_price}</span>
                                </div>
                                <div className="flex justify-between font-medium text-slate-900">
                                    <span>Precio oferta</span>
                                    <span>{offerToDelete.current_offer.formatted_offer_price}</span>
                                </div>
                            </div>
                        )}

                        <p className="mt-3 text-xs text-slate-400">Esta acción no se puede deshacer.</p>

                        <div className="mt-5 flex justify-end gap-3">
                            <button
                                onClick={closeDeleteOfferModal}
                                disabled={isDeletingOffer}
                                className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                            >
                                Cancelar
                            </button>
                            <button
                                onClick={confirmDeleteOffer}
                                disabled={isDeletingOffer}
                                className="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-700 disabled:opacity-50"
                            >
                                {isDeletingOffer ? 'Eliminando...' : 'Eliminar oferta'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
