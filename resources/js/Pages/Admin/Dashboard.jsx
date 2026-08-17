import React from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { estadoLabel, estadoBadgeClasses } from '@/utils/orders';
import {
    IconLayers,
    IconBox,
    IconTag,
    IconClipboard,
    IconAlertTriangle,
    IconAlertOctagon,
    IconClock,
    IconTrendingUp,
    IconArrowRight,
    IconPlus,
    IconGlobe,
    IconInbox,
} from '@/Components/Admin/Icons';

function getGreeting() {
    const hour = new Date().getHours();
    if (hour < 12) return 'Buenos días';
    if (hour < 20) return 'Buenas tardes';
    return 'Buenas noches';
}

const TONE_CLASSES = {
    neutral: 'bg-slate-100 text-slate-600',
    gold: 'bg-gold text-navy',
    amber: 'bg-amber-50 text-amber-600',
    rose: 'bg-rose-50 text-rose-600',
};

function KpiCard({ href, label, value, sub, icon: Icon, tone = 'neutral' }) {
    return (
        <Link
            href={href}
            className="group flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-4 transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-sm"
        >
            <div className="flex items-start justify-between">
                <span className={`flex h-9 w-9 items-center justify-center rounded-lg ${TONE_CLASSES[tone]}`}>
                    <Icon className="h-5 w-5" />
                </span>
                <IconArrowRight className="h-4 w-4 text-slate-300 opacity-0 transition group-hover:translate-x-0.5 group-hover:opacity-100" />
            </div>
            <div className="mt-4">
                <p className="text-2xl font-semibold tabular-nums text-slate-900">{value}</p>
                <p className="text-xs font-medium text-slate-500">{label}</p>
                {sub && <p className="mt-0.5 text-[11px] text-slate-400">{sub}</p>}
            </div>
        </Link>
    );
}

export default function Dashboard({ stats = {}, recentOrders = [] }) {
    const { auth } = usePage().props;
    const firstName = auth?.user?.name?.split(' ')[0];

    const kpis = [
        {
            label: 'Categorías',
            value: stats.categories_count ?? 0,
            href: route('admin.categories.index'),
            icon: IconLayers,
            tone: 'neutral',
        },
        {
            label: 'Productos activos',
            value: stats.products_count ?? 0,
            sub: stats.products_total ? `${stats.products_total} en total` : undefined,
            href: route('admin.products.index'),
            icon: IconBox,
            tone: 'neutral',
        },
        {
            label: 'Ofertas activas',
            value: stats.offers_count ?? 0,
            href: route('admin.offers.index'),
            icon: IconTag,
            tone: 'gold',
        },
        {
            label: 'Stock bajo',
            value: stats.low_stock ?? 0,
            href: `${route('admin.products.index')}?stock=low_stock`,
            icon: IconAlertTriangle,
            tone: 'amber',
        },
        {
            label: 'Sin stock',
            value: stats.out_of_stock ?? 0,
            href: `${route('admin.products.index')}?stock=out_of_stock`,
            icon: IconAlertOctagon,
            tone: 'rose',
        },
        {
            label: 'Pedidos pendientes',
            value: stats.pending_orders_count ?? 0,
            href: `${route('admin.orders.index')}?estado=pendiente`,
            icon: IconClock,
            tone: 'amber',
        },
    ];

    const sections = [
        {
            name: 'Categorías',
            description: 'Organizá el catálogo por rubros',
            href: route('admin.categories.index'),
            icon: IconLayers,
        },
        {
            name: 'Productos',
            description: 'Cargá, editá y controlá el stock',
            href: route('admin.products.index'),
            icon: IconBox,
        },
        {
            name: 'Ofertas',
            description: 'Descuentos y promociones vigentes',
            href: route('admin.offers.index'),
            icon: IconTag,
        },
        {
            name: 'Órdenes',
            description: 'Pedidos realizados desde la tienda',
            href: route('admin.orders.index'),
            icon: IconClipboard,
        },
    ];

    return (
        <AdminLayout
            header={
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                            {getGreeting()}
                            {firstName ? `, ${firstName}` : ''}
                        </p>
                        <h1 className="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl">
                            Panel de Administración
                        </h1>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Link
                            href={route('admin.products.create')}
                            className="inline-flex items-center gap-1.5 rounded-lg bg-gold px-3.5 py-2 text-sm font-semibold text-navy transition hover:brightness-95"
                        >
                            <IconPlus className="h-4 w-4" />
                            Nuevo producto
                        </Link>
                        <Link
                            href="/"
                            target="_blank"
                            className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3.5 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            <IconGlobe className="h-4 w-4" />
                            Ver sitio
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="Dashboard - Admin" />

            <div className="space-y-6 lg:space-y-8">
                {/* Resumen del mes */}
                <div className="rounded-2xl bg-navy px-5 py-6 sm:px-8 sm:py-7">
                    <div className="flex items-center justify-between">
                        <h2 className="text-sm font-medium text-white/50">Resumen del mes</h2>
                        <IconTrendingUp className="h-5 w-5 text-gold" />
                    </div>
                    <div className="mt-5 grid grid-cols-1 divide-y divide-white/10 sm:grid-cols-3 sm:divide-y-0 sm:divide-x sm:divide-white/10">
                        <div className="py-4 first:pt-0 sm:px-6 sm:py-0 sm:first:pl-0 sm:last:pr-0">
                            <p className="text-2xl font-bold text-gold sm:text-3xl">
                                {stats.formatted_revenue_month ?? '$0'}
                            </p>
                            <p className="mt-1 text-xs text-white/50">Ingresos del mes</p>
                        </div>
                        <div className="py-4 sm:px-6 sm:py-0">
                            <p className="text-2xl font-bold text-white sm:text-3xl">{stats.orders_month_count ?? 0}</p>
                            <p className="mt-1 text-xs text-white/50">Pedidos del mes</p>
                        </div>
                        <div className="py-4 last:pb-0 sm:px-6 sm:py-0">
                            <p className="text-2xl font-bold text-white sm:text-3xl">
                                {stats.formatted_avg_order_month ?? '$0'}
                            </p>
                            <p className="mt-1 text-xs text-white/50">Ticket promedio</p>
                        </div>
                    </div>
                </div>

                {/* Métricas */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-6">
                    {kpis.map((kpi) => (
                        <KpiCard key={kpi.label} {...kpi} />
                    ))}
                </div>

                {/* Accesos rápidos */}
                <div>
                    <h2 className="mb-3 text-sm font-semibold text-slate-900">Accesos rápidos</h2>
                    <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                        {sections.map((section) => (
                            <Link
                                key={section.name}
                                href={section.href}
                                className="group flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-4 transition hover:border-slate-300 hover:shadow-sm"
                            >
                                <span className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-navy/5 text-navy">
                                    <section.icon className="h-5 w-5" />
                                </span>
                                <div className="min-w-0">
                                    <p className="flex items-center gap-1 text-sm font-semibold text-slate-900">
                                        {section.name}
                                        <IconArrowRight className="h-3.5 w-3.5 text-slate-300 opacity-0 transition group-hover:translate-x-0.5 group-hover:opacity-100" />
                                    </p>
                                    <p className="mt-0.5 text-xs text-slate-500">{section.description}</p>
                                </div>
                            </Link>
                        ))}
                    </div>
                </div>

                {/* Pedidos recientes */}
                <div className="rounded-xl border border-slate-200 bg-white">
                    <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                        <h2 className="text-sm font-semibold text-slate-900">Pedidos recientes</h2>
                        <Link
                            href={route('admin.orders.index')}
                            className="inline-flex items-center gap-1 text-xs font-medium text-slate-500 transition hover:text-navy"
                        >
                            Ver todas
                            <IconArrowRight className="h-3.5 w-3.5" />
                        </Link>
                    </div>

                    {recentOrders.length === 0 ? (
                        <div className="flex flex-col items-center gap-2 px-5 py-10 text-center">
                            <IconInbox className="h-8 w-8 text-slate-300" />
                            <p className="text-sm text-slate-500">Todavía no hay pedidos.</p>
                        </div>
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {recentOrders.map((order) => (
                                <li key={order.id}>
                                    <Link
                                        href={route('admin.orders.show', order.id)}
                                        className="flex items-center justify-between gap-3 px-5 py-3.5 transition hover:bg-slate-50"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium text-slate-900">
                                                {order.name} {order.lastname}
                                            </p>
                                            <p className="text-xs text-slate-400">{order.created_at}</p>
                                        </div>
                                        <div className="flex flex-shrink-0 items-center gap-3">
                                            <span
                                                className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${estadoBadgeClasses(order.estado)}`}
                                            >
                                                {estadoLabel(order.estado)}
                                            </span>
                                            <span className="text-sm font-semibold tabular-nums text-slate-900">
                                                {order.formatted_total}
                                            </span>
                                        </div>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
