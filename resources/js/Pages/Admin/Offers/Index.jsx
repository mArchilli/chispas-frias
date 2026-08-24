import React, { useState } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AdminLayout from '@/Layouts/AdminLayout';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal';
import ActionIconButton from '@/Components/Admin/ActionIconButton';
import usePermissions from '@/hooks/usePermissions';
import { getProductImageUrl } from '@/utils/images';
import {
    IconPlus,
    IconSearch,
    IconTag,
    IconClock,
    IconAlertOctagon,
    IconPercent,
    IconPencil,
    IconTrash,
    IconEye,
    IconEyeOff,
    IconInbox,
    IconPhoto,
} from '@/Components/Admin/Icons';

const inputClasses =
    'block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 transition focus:border-navy focus:outline-none focus:ring-2 focus:ring-navy/10';

const STATUS_META = {
    activa: { label: 'Activa', badge: 'bg-emerald-50 text-emerald-700' },
    programada: { label: 'Programada', badge: 'bg-amber-50 text-amber-700' },
    expirada: { label: 'Expirada', badge: 'bg-rose-50 text-rose-700' },
    inactiva: { label: 'Inactiva', badge: 'bg-slate-100 text-slate-500' },
};

const TONE_CLASSES = {
    gold: 'bg-gold text-navy',
    amber: 'bg-amber-50 text-amber-600',
    rose: 'bg-rose-50 text-rose-600',
    neutral: 'bg-slate-100 text-slate-600',
};

function formatDate(date) {
    if (!date) return null;
    // timeZone: 'UTC' es intencional: el server (APP_TIMEZONE=UTC) guarda tal
    // cual el datetime-local que se tipeó en el form, sin conversión de huso
    // horario. Formatear en la zona local del navegador correría la fecha un
    // día para cualquier huso detrás de UTC (ej. Argentina) — ver start_date
    // por defecto en Offers/Create.
    return new Date(date).toLocaleDateString('es-AR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        timeZone: 'UTC',
    });
}

function MetricCard({ label, value, icon: Icon, tone = 'neutral' }) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-4">
            <span className={`flex h-9 w-9 items-center justify-center rounded-lg ${TONE_CLASSES[tone]}`}>
                <Icon className="h-5 w-5" />
            </span>
            <p className="mt-3 text-2xl font-semibold tabular-nums text-slate-900">{value}</p>
            <p className="text-xs font-medium text-slate-500">{label}</p>
        </div>
    );
}

function OfferCard({ offer, onToggleStatus, onDelete, togglingId }) {
    const { isAdmin } = usePermissions();
    const status = STATUS_META[offer.status] ?? STATUS_META.inactiva;
    const imageUrl = getProductImageUrl(offer.product.primary_image);

    return (
        <div className="rounded-xl border border-slate-200 bg-white transition hover:border-slate-300 hover:shadow-sm">
            <div className="flex items-start gap-3 p-4">
                <div className="flex h-12 w-12 flex-shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100">
                    {imageUrl ? (
                        <img src={imageUrl} alt={offer.product.title} className="h-full w-full object-cover" />
                    ) : (
                        <IconPhoto className="h-5 w-5 text-slate-300" />
                    )}
                </div>
                <div className="min-w-0 flex-1">
                    <div className="flex items-start justify-between gap-2">
                        <h3 className="truncate text-sm font-semibold text-slate-900">{offer.product.title}</h3>
                        <span
                            className={`inline-flex flex-shrink-0 items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ${status.badge}`}
                        >
                            {status.label}
                        </span>
                    </div>

                    <div className="mt-1.5 flex items-baseline gap-1.5">
                        {offer.offer_price !== null ? (
                            <>
                                <span className="text-base font-semibold text-slate-900">
                                    {offer.formatted_offer_price}
                                </span>
                                <span className="text-xs text-slate-400 line-through">
                                    {offer.product.formatted_price}
                                </span>
                            </>
                        ) : (
                            <span className="text-xs text-slate-500">No afecta el precio base</span>
                        )}
                        {offer.percentage_discount != null && (
                            <span className="ml-auto text-sm font-semibold text-rose-600">
                                -{Math.round(offer.percentage_discount)}%
                            </span>
                        )}
                    </div>
                </div>
            </div>

            <div className="space-y-1.5 border-t border-slate-100 px-4 py-3 text-xs text-slate-500">
                <div className="flex justify-between">
                    <span>Alcance</span>
                    <span className="text-slate-700">
                        {offer.alcance === 'todos'
                            ? 'Todos los niveles'
                            : offer.price_tier
                              ? `${offer.price_tier.cantidad_minima}+ unidades`
                              : 'Precio base'}
                    </span>
                </div>
                {(offer.start_date || offer.end_date) && (
                    <div className="flex justify-between">
                        <span>Vigencia</span>
                        <span className="text-slate-700">
                            {offer.start_date ? formatDate(offer.start_date) : 'Ahora'}
                            {' → '}
                            {offer.end_date ? formatDate(offer.end_date) : 'Sin fin'}
                        </span>
                    </div>
                )}
            </div>

            <div className="flex items-center gap-0.5 border-t border-slate-100 px-2 py-1.5">
                <ActionIconButton
                    onClick={() => onToggleStatus(offer)}
                    icon={offer.is_active ? IconEye : IconEyeOff}
                    label={offer.is_active ? 'Desactivar' : 'Activar'}
                    tone={offer.is_active ? 'active' : 'default'}
                    disabled={togglingId === offer.id}
                />
                <ActionIconButton href={route('admin.offers.edit', offer.id)} icon={IconPencil} label="Editar" />
                {isAdmin && (
                    <ActionIconButton onClick={() => onDelete(offer)} icon={IconTrash} label="Eliminar" tone="danger" />
                )}
            </div>
        </div>
    );
}

export default function OffersIndex({ offers, stats = {}, filters = {} }) {
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [offerToDelete, setOfferToDelete] = useState(null);
    const [isDeleting, setIsDeleting] = useState(false);
    const [togglingId, setTogglingId] = useState(null);

    const searchForm = useForm({ search: filters?.search || '', status: filters?.status || '' });

    const hasActiveFilters = !!(filters?.search || filters?.status);

    const handleSearch = (e) => {
        e.preventDefault();
        searchForm.get(route('admin.offers.index'), { preserveState: true, replace: true });
    };

    const clearFilters = () => {
        // No usar searchForm.get() acá: reset() programa la actualización de forma
        // asíncrona, así que un get() disparado en el mismo tick todavía ve los
        // filtros viejos (closure de React desactualizado). Ver Products/Index.
        searchForm.reset();
        router.get(route('admin.offers.index'), {}, { preserveState: true, replace: true });
    };

    const handleToggleStatus = (offer) => {
        setTogglingId(offer.id);
        router.post(
            route('admin.offers.toggle-status', offer.id),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => toast.success(offer.is_active ? 'Oferta desactivada' : 'Oferta activada'),
                onError: () => toast.error('Error al actualizar el estado'),
                onFinish: () => setTogglingId(null),
            }
        );
    };

    const handleDelete = (offer) => {
        setOfferToDelete(offer);
        setShowDeleteModal(true);
    };

    const confirmDelete = () => {
        if (!offerToDelete) return;
        setIsDeleting(true);
        router.delete(route('admin.offers.destroy', offerToDelete.id), {
            onSuccess: () => {
                toast.success('Oferta eliminada exitosamente');
                setShowDeleteModal(false);
                setOfferToDelete(null);
                setIsDeleting(false);
            },
            onError: () => {
                toast.error('Error al eliminar la oferta');
                setIsDeleting(false);
            },
        });
    };

    const closeDeleteModal = () => {
        if (!isDeleting) {
            setShowDeleteModal(false);
            setOfferToDelete(null);
        }
    };

    return (
        <AdminLayout
            header={
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                            {offers?.total ?? 0} {offers?.total === 1 ? 'oferta' : 'ofertas'}
                        </p>
                        <h1 className="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">Ofertas</h1>
                    </div>
                    <Link
                        href={route('admin.offers.create')}
                        className="inline-flex items-center gap-1.5 rounded-lg bg-gold px-3.5 py-2 text-sm font-semibold text-navy transition hover:brightness-95"
                    >
                        <IconPlus className="h-4 w-4" />
                        Nueva oferta
                    </Link>
                </div>
            }
        >
            <Head title="Ofertas - Admin" />

            <div className="space-y-6">
                {/* Métricas */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
                    <MetricCard label="Activas" value={stats.active ?? 0} icon={IconTag} tone="gold" />
                    <MetricCard label="Programadas" value={stats.scheduled ?? 0} icon={IconClock} tone="amber" />
                    <MetricCard label="Expiradas" value={stats.expired ?? 0} icon={IconAlertOctagon} tone="rose" />
                    <MetricCard
                        label="Descuento promedio"
                        value={stats.avg_discount != null ? `${stats.avg_discount}%` : '—'}
                        icon={IconPercent}
                        tone="neutral"
                    />
                </div>

                {/* Filtros */}
                <form onSubmit={handleSearch} className="space-y-3">
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <div className="relative flex-1">
                            <IconSearch className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                placeholder="Buscar por producto..."
                                value={searchForm.data.search}
                                onChange={(e) => searchForm.setData('search', e.target.value)}
                                className={`${inputClasses} pl-9`}
                            />
                        </div>
                        <select
                            value={searchForm.data.status}
                            onChange={(e) => searchForm.setData('status', e.target.value)}
                            className={`${inputClasses} sm:max-w-[180px]`}
                        >
                            <option value="">Todos los estados</option>
                            <option value="activa">Activas</option>
                            <option value="programada">Programadas</option>
                            <option value="expirada">Expiradas</option>
                            <option value="inactiva">Inactivas</option>
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

                {/* Grid de ofertas */}
                {offers?.data?.length > 0 ? (
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-3">
                        {offers.data.map((offer) => (
                            <OfferCard
                                key={offer.id}
                                offer={offer}
                                onToggleStatus={handleToggleStatus}
                                onDelete={handleDelete}
                                togglingId={togglingId}
                            />
                        ))}
                    </div>
                ) : (
                    <div className="rounded-xl border border-slate-200 bg-white py-16 text-center">
                        <IconInbox className="mx-auto h-8 w-8 text-slate-300" />
                        <h3 className="mt-3 text-sm font-medium text-slate-900">
                            {hasActiveFilters ? 'No se encontraron resultados' : 'No hay ofertas creadas'}
                        </h3>
                        <p className="mt-1 text-sm text-slate-500">
                            {hasActiveFilters
                                ? 'Probá ajustar los filtros aplicados.'
                                : 'Comenzá creando tu primera oferta para los productos.'}
                        </p>
                        {!hasActiveFilters && (
                            <Link
                                href={route('admin.offers.create')}
                                className="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-gold px-4 py-2 text-sm font-semibold text-navy transition hover:brightness-95"
                            >
                                <IconPlus className="h-4 w-4" />
                                Crear primera oferta
                            </Link>
                        )}
                    </div>
                )}

                {/* Paginación */}
                {offers?.data?.length > 0 && offers?.links?.length > 3 && (
                    <div className="flex flex-col items-center justify-between gap-3 border-t border-slate-200 pt-4 sm:flex-row">
                        <p className="text-sm text-slate-500">
                            Mostrando <span className="font-medium text-slate-700">{offers?.from || 0}</span>–
                            <span className="font-medium text-slate-700">{offers?.to || 0}</span> de{' '}
                            <span className="font-medium text-slate-700">{offers?.total || 0}</span>
                        </p>
                        <nav className="flex flex-wrap items-center gap-1">
                            {offers.links.map((link, index) =>
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
                title="¿Eliminar oferta?"
                message="Estás a punto de eliminar la oferta de:"
                itemName={offerToDelete?.product?.title}
                warningMessage="Esta acción no se puede deshacer."
                confirmText="Eliminar oferta"
                processing={isDeleting}
            />
        </AdminLayout>
    );
}
