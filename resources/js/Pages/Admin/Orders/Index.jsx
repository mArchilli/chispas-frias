import React, { useState } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { estadoLabel, estadoBadgeClasses } from '@/utils/orders';
import {
    IconSearch,
    IconTrendingUp,
    IconBox,
    IconMapPin,
    IconChevronLeft,
    IconChevronRight,
    IconArrowRight,
    IconInbox,
} from '@/Components/Admin/Icons';

const inputClasses =
    'block w-full rounded-lg border border-gray-200 px-3 py-2 text-sm text-graphite placeholder:text-gray-500 transition focus:border-ice-500 focus:outline-none focus:ring-2 focus:ring-ice-100';

const ESTADO_OPTIONS = [
    { value: 'pendiente', label: 'Pendiente' },
    { value: 'despachado', label: 'Despachado' },
    { value: 'cancelado', label: 'Cancelado' },
];

function splitDate(isoDate) {
    // isoDate: 'YYYY-MM-DD'. Se parsea a mano (sin `new Date`) para no arrastrar
    // el bug de huso horario que ya nos mordió en Offers: el server guarda en
    // UTC y convertir a la hora local del navegador corre la fecha un día.
    const [year, month, day] = isoDate.split('-');
    return { year, month, day };
}

function DailyBreakdownChart({ days, monthLabel }) {
    const [activeDay, setActiveDay] = useState(null);
    const maxCount = Math.max(1, ...days.map((d) => d.orders_count));
    const totalOrders = days.reduce((sum, d) => sum + d.orders_count, 0);
    const totalRevenue = days.reduce((sum, d) => sum + d.revenue, 0);

    const tickDays = [1, 5, 10, 15, 20, 25, days.length].filter(
        (d, i, arr) => d <= days.length && arr.indexOf(d) === i
    );

    const active = activeDay != null ? days.find((d) => d.day === activeDay) : null;

    return (
        <div onMouseLeave={() => setActiveDay(null)}>
            <div className="flex h-32 items-end gap-[3px] sm:gap-1">
                {days.map((d) => {
                    const heightPct = (d.orders_count / maxCount) * 100;
                    const isActive = activeDay === d.day;
                    return (
                        <button
                            key={d.date}
                            type="button"
                            onMouseEnter={() => setActiveDay(d.day)}
                            onFocus={() => setActiveDay(d.day)}
                            onClick={() => setActiveDay(isActive ? null : d.day)}
                            className="group flex h-full flex-1 flex-col justify-end outline-none"
                        >
                            <span
                                className={`w-full rounded-t transition-colors ${
                                    isActive
                                        ? 'bg-ice-500'
                                        : d.orders_count > 0
                                          ? 'bg-storefront/70 group-hover:bg-storefront group-focus:bg-storefront'
                                          : 'bg-gray-200'
                                }`}
                                style={{
                                    height: d.orders_count > 0 ? `${Math.max(heightPct, 6)}%` : '2px',
                                }}
                            />
                        </button>
                    );
                })}
            </div>
            <div className="mt-1.5 flex justify-between text-[10px] text-gray-500">
                {tickDays.map((d) => (
                    <span key={d}>{d}</span>
                ))}
            </div>
            <p className="mt-3 text-sm text-graphite/75">
                {active ? (
                    <>
                        <span className="font-semibold text-graphite">
                            {splitDate(active.date).day}/{splitDate(active.date).month}
                        </span>{' '}
                        — {active.orders_count} {active.orders_count === 1 ? 'pedido' : 'pedidos'} ·{' '}
                        {active.formatted_revenue}
                    </>
                ) : (
                    <>
                        <span className="font-semibold text-graphite">Total {monthLabel}</span> — {totalOrders}{' '}
                        {totalOrders === 1 ? 'pedido' : 'pedidos'} · $
                        {totalRevenue.toLocaleString('es-AR')}
                    </>
                )}
            </p>
        </div>
    );
}

function OrderCard({ order }) {
    return (
        <Link
            href={route('admin.orders.show', order.id)}
            className="flex flex-col gap-3 rounded-xl border border-gray-200 bg-surface p-4 transition hover:border-gray-200 hover:shadow-sm"
        >
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <p className="truncate text-sm font-semibold text-graphite">
                        {order.name} {order.lastname}
                    </p>
                    <p className="text-xs text-gray-500">{order.created_at}</p>
                </div>
                <span
                    className={`inline-flex flex-shrink-0 items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ${estadoBadgeClasses(order.estado)}`}
                >
                    {estadoLabel(order.estado)}
                </span>
            </div>

            <div className="flex items-center justify-between text-xs text-gray-500">
                <span>
                    {order.items_count} {order.items_count === 1 ? 'producto' : 'productos'}
                </span>
                <span className="truncate">
                    {order.province}
                    {order.city ? ` · ${order.city}` : ''}
                </span>
            </div>

            <div className="flex items-center justify-between border-t border-gray-200 pt-3">
                <span className="text-base font-semibold text-graphite">{order.formatted_total}</span>
                <span className="inline-flex items-center gap-1 text-xs font-medium text-gray-500">
                    Ver detalle
                    <IconArrowRight className="h-3.5 w-3.5" />
                </span>
            </div>
        </Link>
    );
}

export default function Index({ orders, filters = {}, stats, dailyBreakdown, month }) {
    // stats/dailyBreakdown/month sólo viajan para admin (ver
    // OrderController::index): un vendedor ve la cola operativa igual, pero
    // sin el panel de métricas de negocio, así que estas props ni llegan.
    const hasMetrics = !!month;
    const searchForm = useForm({ search: filters?.search || '' });

    const navigate = (overrides = {}) => {
        router.get(
            route('admin.orders.index'),
            {
                search: searchForm.data.search,
                estado: filters.estado,
                month: month?.value,
                ...overrides,
            },
            { preserveState: true, replace: true, preserveScroll: true }
        );
    };

    const handleSearch = (e) => {
        e.preventDefault();
        navigate({ search: searchForm.data.search });
    };

    const clearSearch = () => {
        searchForm.setData('search', '');
        navigate({ search: '' });
    };

    return (
        <AdminLayout
            header={
                <div>
                    <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                        {orders?.total ?? 0} {orders?.total === 1 ? 'pedido' : 'pedidos'}
                    </p>
                    <h1 className="mt-1 text-xl font-semibold text-graphite sm:text-2xl">Órdenes</h1>
                </div>
            }
        >
            <Head title="Órdenes - Admin" />

            <div className="space-y-6">
                {/* Panel de métricas de negocio: sólo llega para admin (ver
                    OrderController::index) — un vendedor nunca recibe stats/
                    dailyBreakdown/month, así que esta sección ni se intenta mostrar. */}
                {hasMetrics && (
                    <>
                        {/* Navegador de mes */}
                        <div className="flex items-center justify-between">
                            <h2 className="text-sm font-semibold text-graphite">Métricas del mes</h2>
                            <div className="flex items-center gap-1">
                                <button
                                    type="button"
                                    title="Mes anterior"
                                    onClick={() => navigate({ month: month.prev })}
                                    className="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition hover:bg-background"
                                >
                                    <IconChevronLeft className="h-4 w-4" />
                                </button>
                                <span className="min-w-[9rem] text-center text-sm font-medium text-graphite/85">
                                    {month.label}
                                </span>
                                <button
                                    type="button"
                                    title="Mes siguiente"
                                    onClick={() => month.can_go_next && navigate({ month: month.next })}
                                    disabled={!month.can_go_next}
                                    className="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition hover:bg-background disabled:pointer-events-none disabled:opacity-30"
                                >
                                    <IconChevronRight className="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        {/* Resumen del mes */}
                        <div className="rounded-2xl bg-navy-900 px-5 py-6 sm:px-8 sm:py-7">
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-medium text-white/50">Resumen · {month.label}</h3>
                                <IconTrendingUp className="h-5 w-5 text-ice-500" />
                            </div>
                            <div className="mt-5 grid grid-cols-2 gap-y-5 divide-y divide-surface/10 sm:grid-cols-4 sm:gap-y-0 sm:divide-y-0 sm:divide-x sm:divide-surface/10">
                                <div className="sm:px-6 sm:first:pl-0">
                                    <p className="text-2xl font-bold text-ice-500 sm:text-3xl">
                                        {stats.formatted_revenue ?? '$0'}
                                    </p>
                                    <p className="mt-1 text-xs text-white/50">Ingresos</p>
                                </div>
                                <div className="pt-5 sm:px-6 sm:pt-0">
                                    <p className="text-2xl font-bold text-white sm:text-3xl">{stats.orders_count ?? 0}</p>
                                    <p className="mt-1 text-xs text-white/50">Pedidos</p>
                                </div>
                                <div className="pt-5 sm:px-6 sm:pt-0">
                                    <p className="text-2xl font-bold text-white sm:text-3xl">
                                        {stats.formatted_avg_order_value ?? '$0'}
                                    </p>
                                    <p className="mt-1 text-xs text-white/50">Ticket promedio</p>
                                </div>
                                <div className="pt-5 sm:px-6 sm:pt-0 sm:last:pr-0">
                                    <p className="text-2xl font-bold text-white sm:text-3xl">
                                        {stats.cancelled_count ?? 0}
                                    </p>
                                    <p className="mt-1 text-xs text-white/50">Cancelados</p>
                                </div>
                            </div>
                        </div>

                        {/* Producto más vendido / destino más solicitado */}
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4">
                            <div className="rounded-xl border border-gray-200 bg-surface p-4">
                                <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-ice-500 text-navy-900">
                                    <IconBox className="h-5 w-5" />
                                </span>
                                <p className="mt-3 text-xs font-medium text-gray-500">Producto más vendido</p>
                                {stats.top_product ? (
                                    <>
                                        <p className="mt-0.5 truncate text-base font-semibold text-graphite">
                                            {stats.top_product.title}
                                        </p>
                                        <p className="text-xs text-gray-500">
                                            {stats.top_product.quantity} unidades vendidas
                                        </p>
                                    </>
                                ) : (
                                    <p className="mt-0.5 text-sm text-gray-500">Sin ventas este mes</p>
                                )}
                            </div>

                            <div className="rounded-xl border border-gray-200 bg-surface p-4">
                                <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-ice-100 text-graphite/75">
                                    <IconMapPin className="h-5 w-5" />
                                </span>
                                <p className="mt-3 text-xs font-medium text-gray-500">Destinos más solicitados</p>
                                {stats.top_locations?.length > 0 ? (
                                    <ul className="mt-1 space-y-0.5">
                                        {stats.top_locations.map((loc, i) => (
                                            <li key={i} className="flex items-center justify-between text-sm">
                                                <span className="truncate font-medium text-graphite">
                                                    {loc.province}
                                                </span>
                                                <span className="flex-shrink-0 text-xs text-gray-500">
                                                    {loc.count} {loc.count === 1 ? 'pedido' : 'pedidos'}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                ) : (
                                    <p className="mt-0.5 text-sm text-gray-500">Sin pedidos este mes</p>
                                )}
                            </div>
                        </div>

                        {/* Pedidos por día */}
                        <div className="rounded-xl border border-gray-200 bg-surface p-4 sm:p-5">
                            <h3 className="text-sm font-semibold text-graphite">Pedidos por día</h3>
                            <p className="text-xs text-gray-500">Pasá el cursor o tocá una barra para ver el detalle.</p>
                            <div className="mt-4">
                                <DailyBreakdownChart days={dailyBreakdown} monthLabel={month.label} />
                            </div>
                        </div>
                    </>
                )}

                {/* Cola operativa: filtro por estado + búsqueda (igual para admin y vendedor) */}
                <div className="space-y-3 pt-2">
                    <div className="grid grid-cols-3 gap-2 sm:max-w-md">
                        {ESTADO_OPTIONS.map((opt) => {
                            const active = filters.estado === opt.value;
                            return (
                                <button
                                    key={opt.value}
                                    type="button"
                                    onClick={() => navigate({ estado: opt.value })}
                                    className={`rounded-lg border px-3 py-2 text-sm font-medium transition ${
                                        active
                                            ? 'border-storefront bg-storefront text-white'
                                            : 'border-gray-200 text-graphite/85 hover:border-gray-200 hover:bg-background'
                                    }`}
                                >
                                    {opt.label}
                                </button>
                            );
                        })}
                    </div>

                    <form onSubmit={handleSearch} className="flex gap-2">
                        <div className="relative flex-1 sm:max-w-xs">
                            <IconSearch className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500" />
                            <input
                                type="text"
                                placeholder="Buscar por nombre, DNI o email..."
                                value={searchForm.data.search}
                                onChange={(e) => searchForm.setData('search', e.target.value)}
                                className={`${inputClasses} pl-9`}
                            />
                        </div>
                        <button
                            type="submit"
                            className="rounded-lg border border-gray-200 px-3.5 py-2 text-sm font-medium text-graphite/85 transition hover:bg-background"
                        >
                            Buscar
                        </button>
                        {filters.search && (
                            <button
                                type="button"
                                onClick={clearSearch}
                                className="rounded-lg px-3.5 py-2 text-sm font-medium text-gray-500 transition hover:bg-ice-100"
                            >
                                Limpiar
                            </button>
                        )}
                    </form>
                </div>

                {/* Grid de órdenes */}
                {orders?.data?.length > 0 ? (
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-3">
                        {orders.data.map((order) => (
                            <OrderCard key={order.id} order={order} />
                        ))}
                    </div>
                ) : (
                    <div className="rounded-xl border border-gray-200 bg-surface py-16 text-center">
                        <IconInbox className="mx-auto h-8 w-8 text-gray-500/70" />
                        <h3 className="mt-3 text-sm font-medium text-graphite">No hay pedidos</h3>
                        <p className="mt-1 text-sm text-gray-500">
                            No se encontraron pedidos {estadoLabel(filters.estado).toLowerCase()}s con esos filtros.
                        </p>
                    </div>
                )}

                {/* Paginación */}
                {orders?.data?.length > 0 && orders?.links?.length > 3 && (
                    <div className="flex flex-col items-center justify-between gap-3 border-t border-gray-200 pt-4 sm:flex-row">
                        <p className="text-sm text-gray-500">
                            Mostrando <span className="font-medium text-graphite/85">{orders?.from || 0}</span>–
                            <span className="font-medium text-graphite/85">{orders?.to || 0}</span> de{' '}
                            <span className="font-medium text-graphite/85">{orders?.total || 0}</span>
                        </p>
                        <nav className="flex flex-wrap items-center gap-1">
                            {orders.links.map((link, index) =>
                                link.url ? (
                                    <Link
                                        key={index}
                                        href={link.url}
                                        preserveScroll
                                        className={`flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-sm font-medium transition ${
                                            link.active ? 'bg-storefront text-white' : 'text-graphite/75 hover:bg-ice-100'
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : (
                                    <span
                                        key={index}
                                        className="flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-sm text-gray-500/70"
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
