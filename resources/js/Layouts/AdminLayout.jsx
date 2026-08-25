import React from 'react';
import { Link, usePage } from '@inertiajs/react';
import Dropdown from '@/Components/Dropdown';
import usePermissions from '@/hooks/usePermissions';
import {
    IconHome,
    IconLayers,
    IconBox,
    IconTag,
    IconTicket,
    IconClipboard,
    IconTruck,
    IconUsers,
    IconCurrencyDollar,
    IconChevronsLeft,
    IconChevronsRight,
    IconGlobe,
    IconUser,
    IconLogout,
} from '@/Components/Admin/Icons';

const ROLE_LABELS = { admin: 'Admin', vendedor: 'Vendedor' };

export default function AdminLayout({ children, header = null }) {
    const { auth } = usePage().props;
    const currentPath = usePage().url;
    const { isAdmin, role } = usePermissions();
    const roleLabel = ROLE_LABELS[role] ?? null;

    const NAV_ITEMS = [
        { name: 'Dashboard', short: 'Inicio', href: route('admin.dashboard', undefined, false), icon: IconHome },
        { name: 'Categorías', short: 'Categorías', href: route('admin.categories.index', undefined, false), icon: IconLayers },
        // Productos es exclusivo de admin (Gate 'gestionar-productos'): el
        // vendedor sólo ve precios, sin poder editar el catálogo.
        ...(isAdmin
            ? [{ name: 'Productos', short: 'Productos', href: route('admin.products.index', undefined, false), icon: IconBox }]
            : [{ name: 'Precios', short: 'Precios', href: route('admin.prices.index', undefined, false), icon: IconCurrencyDollar }]),
        { name: 'Ofertas', short: 'Ofertas', href: route('admin.offers.index', undefined, false), icon: IconTag },
        { name: 'Códigos de descuento', short: 'Cupones', href: route('admin.discount-codes.index', undefined, false), icon: IconTicket },
        { name: 'Órdenes', short: 'Órdenes', href: route('admin.orders.index', undefined, false), icon: IconClipboard },
        // Configuración y Vendedores son exclusivos de admin (Gates
        // 'gestionar-configuracion' y 'gestionar-vendedores').
        ...(isAdmin
            ? [
                  { name: 'Precios', short: 'Precios', href: route('admin.prices.index', undefined, false), icon: IconCurrencyDollar },
                  { name: 'Envío gratis', short: 'Envío', href: route('admin.settings.edit', undefined, false), icon: IconTruck },
                  { name: 'Vendedores', short: 'Vendedores', href: route('admin.sellers.index', undefined, false), icon: IconUsers },
              ]
            : []),
    ];

    const [sidebarCollapsed, setSidebarCollapsed] = React.useState(() => {
        if (typeof window !== 'undefined') {
            const saved = localStorage.getItem('sidebarCollapsed');
            return saved ? JSON.parse(saved) : false;
        }
        return false;
    });

    React.useEffect(() => {
        localStorage.setItem('sidebarCollapsed', JSON.stringify(sidebarCollapsed));
    }, [sidebarCollapsed]);

    const navigation = NAV_ITEMS.map((item) => ({
        ...item,
        current: currentPath.startsWith(item.href),
    }));

    const initial = auth?.user?.name ? auth.user.name.charAt(0).toUpperCase() : 'U';

    return (
        <div className="min-h-screen bg-slate-50">
            {/* Desktop sidebar */}
            <div
                className={`hidden md:fixed md:inset-y-0 md:flex md:flex-col transition-[width] duration-300 ${
                    sidebarCollapsed ? 'md:w-[76px]' : 'md:w-64'
                }`}
            >
                <div className="flex min-h-0 flex-1 flex-col bg-navy">
                    {/* Brand */}
                    <div className="flex h-16 flex-shrink-0 items-center justify-between border-b border-white/10 px-4">
                        <Link href={route('admin.dashboard', undefined, false)} className="flex items-center gap-2 overflow-hidden">
                            <span className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-gold text-sm font-bold text-navy">
                                CF
                            </span>
                            {!sidebarCollapsed && (
                                <span className="truncate text-sm font-semibold tracking-wide text-white">
                                    Chispas Frías
                                </span>
                            )}
                        </Link>
                    </div>

                    {/* Navigation */}
                    <nav className="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                        {navigation.map((item) => (
                            <Link
                                key={item.name}
                                href={item.href}
                                title={sidebarCollapsed ? item.name : undefined}
                                className={`group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors ${
                                    item.current
                                        ? 'bg-gold/15 text-gold'
                                        : 'text-white/60 hover:bg-white/5 hover:text-white'
                                } ${sidebarCollapsed ? 'justify-center' : ''}`}
                            >
                                <item.icon className="h-5 w-5 flex-shrink-0" />
                                {!sidebarCollapsed && <span className="truncate">{item.name}</span>}
                            </Link>
                        ))}
                    </nav>

                    {/* Collapse toggle */}
                    <div className="px-3 pb-2">
                        <button
                            onClick={() => setSidebarCollapsed((v) => !v)}
                            className={`flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-white/50 transition-colors hover:bg-white/5 hover:text-white ${
                                sidebarCollapsed ? 'justify-center' : ''
                            }`}
                        >
                            {sidebarCollapsed ? (
                                <IconChevronsRight className="h-5 w-5 flex-shrink-0" />
                            ) : (
                                <>
                                    <IconChevronsLeft className="h-5 w-5 flex-shrink-0" />
                                    <span>Contraer</span>
                                </>
                            )}
                        </button>
                    </div>

                    {/* User section */}
                    <div className="flex-shrink-0 space-y-1 border-t border-white/10 px-3 py-3">
                        <Link
                            href="/"
                            target="_blank"
                            title={sidebarCollapsed ? 'Ver Sitio' : undefined}
                            className={`flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-white/60 transition-colors hover:bg-white/5 hover:text-white ${
                                sidebarCollapsed ? 'justify-center' : ''
                            }`}
                        >
                            <IconGlobe className="h-5 w-5 flex-shrink-0" />
                            {!sidebarCollapsed && 'Ver Sitio'}
                        </Link>

                        <Link
                            href={route('profile.edit', undefined, false)}
                            title={sidebarCollapsed ? 'Mi Perfil' : undefined}
                            className={`flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-white/60 transition-colors hover:bg-white/5 hover:text-white ${
                                sidebarCollapsed ? 'justify-center' : ''
                            }`}
                        >
                            <span className="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-white/10 text-[11px] font-semibold text-white">
                                {initial}
                            </span>
                            {!sidebarCollapsed && (
                                <span className="flex min-w-0 flex-1 items-center gap-1.5">
                                    <span className="truncate">{auth?.user?.name || 'Mi Perfil'}</span>
                                    {roleLabel && (
                                        <span className="flex-shrink-0 rounded-full bg-white/10 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white/70">
                                            {roleLabel}
                                        </span>
                                    )}
                                </span>
                            )}
                        </Link>

                        <Link
                            href={route('logout', undefined, false)}
                            method="post"
                            as="button"
                            title={sidebarCollapsed ? 'Cerrar Sesión' : undefined}
                            className={`flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium text-white/60 transition-colors hover:bg-red-500/10 hover:text-red-300 ${
                                sidebarCollapsed ? 'justify-center' : ''
                            }`}
                        >
                            <IconLogout className="h-5 w-5 flex-shrink-0" />
                            {!sidebarCollapsed && 'Cerrar Sesión'}
                        </Link>
                    </div>
                </div>
            </div>

            {/* Main column */}
            <div className={`flex flex-col transition-[padding] duration-300 ${sidebarCollapsed ? 'md:pl-[76px]' : 'md:pl-64'}`}>
                {/* Mobile top bar */}
                <div className="sticky top-0 z-30 flex h-14 flex-shrink-0 items-center justify-between border-b border-white/10 bg-navy px-4 md:hidden">
                    <Link href={route('admin.dashboard', undefined, false)} className="flex items-center gap-2">
                        <span className="flex h-7 w-7 items-center justify-center rounded-md bg-gold text-xs font-bold text-navy">
                            CF
                        </span>
                        <span className="text-sm font-semibold text-white">Chispas Frías</span>
                    </Link>

                    <Dropdown align="right" width="56">
                        <Dropdown.Trigger>
                            <button className="flex h-8 w-8 items-center justify-center rounded-full bg-white/10 text-xs font-semibold text-white">
                                {initial}
                            </button>
                        </Dropdown.Trigger>
                        <Dropdown.Content contentClasses="py-1 bg-white rounded-xl border border-slate-200 shadow-lg">
                            <div className="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
                                <span className="truncate text-sm font-medium text-slate-900">
                                    {auth?.user?.name}
                                </span>
                                {roleLabel && (
                                    <span className="ml-auto flex-shrink-0 rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                        {roleLabel}
                                    </span>
                                )}
                            </div>
                            <Dropdown.Link href={route('profile.edit', undefined, false)} className="flex items-center gap-2.5">
                                <IconUser className="h-4 w-4 text-slate-400" />
                                Mi Perfil
                            </Dropdown.Link>
                            <Dropdown.Link href="/" target="_blank" className="flex items-center gap-2.5">
                                <IconGlobe className="h-4 w-4 text-slate-400" />
                                Ver Sitio
                            </Dropdown.Link>
                            <Dropdown.Link
                                href={route('logout', undefined, false)}
                                method="post"
                                as="button"
                                className="flex items-center gap-2.5 !text-red-600 hover:!bg-red-50"
                            >
                                <IconLogout className="h-4 w-4 text-red-400" />
                                Cerrar Sesión
                            </Dropdown.Link>
                        </Dropdown.Content>
                    </Dropdown>
                </div>

                <main className="flex-1">
                    {header && (
                        <div className="border-b border-slate-200 bg-white">
                            <div className="px-4 py-5 sm:px-6 lg:px-8 lg:py-6">{header}</div>
                        </div>
                    )}

                    <div className="px-4 pb-24 pt-6 sm:px-6 lg:px-8 lg:pb-8 lg:pt-8">{children}</div>
                </main>
            </div>

            {/* Mobile bottom tab bar */}
            <nav className="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 backdrop-blur md:hidden">
                <div className="flex">
                    {navigation.map((item) => (
                        <Link
                            key={item.name}
                            href={item.href}
                            className="flex flex-1 flex-col items-center justify-center gap-1 py-2 text-[11px] font-medium"
                        >
                            <span
                                className={`flex h-7 w-10 items-center justify-center rounded-lg transition-colors ${
                                    item.current ? 'bg-gold/15 text-gold' : 'text-slate-400'
                                }`}
                            >
                                <item.icon className="h-5 w-5" />
                            </span>
                            <span className={item.current ? 'text-gold' : 'text-slate-500'}>{item.short}</span>
                        </Link>
                    ))}
                </div>
                <div className="h-[env(safe-area-inset-bottom)]" />
            </nav>
        </div>
    );
}
