import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import toast from 'react-hot-toast';
import AdminLayout from '@/Layouts/AdminLayout';
import { buildAddonPreviewText } from '@/utils/addons';

const inputClasses =
    'block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 transition focus:border-navy focus:outline-none focus:ring-2 focus:ring-navy/10';

export default function Edit({ addon }) {
    const { data, setData, put, errors, processing } = useForm({
        name: addon.name || '',
        description: addon.description || '',
        price: addon.price ?? '',
        requires_text: !!addon.requires_text,
        text_placeholder: addon.text_placeholder || '',
        max_characters: addon.max_characters ?? 40,
        is_active: addon.is_active,
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.addons.update', addon.id), {
            onSuccess: () => toast.success('Add-on actualizado exitosamente'),
            onError: () => toast.error('Revisá los datos del formulario'),
        });
    };

    return (
        <AdminLayout
            header={
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="min-w-0">
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <Link href={route('admin.addons.index')} className="hover:text-slate-600">
                                Add-ons
                            </Link>
                        </p>
                        <h1 className="mt-1 truncate text-xl font-semibold text-slate-900 sm:text-2xl">{addon.name}</h1>
                    </div>
                    <Link
                        href={route('admin.addons.index')}
                        className="inline-flex items-center justify-center rounded-lg border border-slate-300 px-3.5 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    >
                        Volver
                    </Link>
                </div>
            }
        >
            <Head title={`Editar ${addon.name} - Admin`} />

            <div className="mx-auto max-w-2xl space-y-4">
                {addon.en_uso && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Este add-on ya se usó en órdenes. Podés editarlo, pero no se puede eliminar: si querés
                        dejar de ofrecerlo, desactivalo.
                    </div>
                )}

                <form onSubmit={submit} className="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div className="space-y-6">
                        <div>
                            <label htmlFor="name" className="mb-1.5 block text-sm font-medium text-slate-700">
                                Nombre <span className="text-rose-500">*</span>
                            </label>
                            <input
                                id="name"
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                disabled={processing}
                                className={inputClasses}
                                maxLength={150}
                            />
                            {errors.name && <p className="mt-1.5 text-xs font-medium text-rose-600">{errors.name}</p>}
                        </div>

                        <div>
                            <label htmlFor="description" className="mb-1.5 block text-sm font-medium text-slate-700">
                                Descripción
                            </label>
                            <textarea
                                id="description"
                                rows={2}
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                disabled={processing}
                                placeholder="Detalle que ve el cliente al elegir el add-on"
                                className={inputClasses}
                            />
                            {errors.description && (
                                <p className="mt-1.5 text-xs font-medium text-rose-600">{errors.description}</p>
                            )}
                        </div>

                        <div>
                            <label htmlFor="price" className="mb-1.5 block text-sm font-medium text-slate-700">
                                Precio <span className="text-rose-500">*</span>
                            </label>
                            <div className="relative sm:max-w-[220px]">
                                <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">
                                    $
                                </span>
                                <input
                                    id="price"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={data.price}
                                    onChange={(e) => setData('price', e.target.value)}
                                    disabled={processing}
                                    className={`${inputClasses} pl-7`}
                                />
                            </div>
                            <p className="mt-1.5 text-xs text-slate-500">
                                Se puede sobrescribir por producto al asociarlo. La oferta de un producto nunca
                                descuenta este monto.
                            </p>
                            {errors.price && <p className="mt-1.5 text-xs font-medium text-rose-600">{errors.price}</p>}
                        </div>

                        <div className="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-3.5">
                            <div>
                                <p className="text-sm font-medium text-slate-900">Requiere texto del cliente</p>
                                <p className="text-xs text-slate-500">
                                    {data.requires_text
                                        ? 'El cliente escribe un texto (ej. el nombre a grabar).'
                                        : 'Add-on sin texto: sólo se agrega o no.'}
                                </p>
                            </div>
                            <label className="relative inline-flex cursor-pointer items-center">
                                <input
                                    type="checkbox"
                                    checked={data.requires_text}
                                    onChange={(e) => setData('requires_text', e.target.checked)}
                                    disabled={processing}
                                    className="peer sr-only"
                                />
                                <div className="peer h-6 w-11 rounded-full bg-slate-300 transition-colors after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-gold peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-gold/20" />
                            </label>
                        </div>

                        {data.requires_text && (
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label
                                        htmlFor="text_placeholder"
                                        className="mb-1.5 block text-sm font-medium text-slate-700"
                                    >
                                        Placeholder del campo
                                    </label>
                                    <input
                                        id="text_placeholder"
                                        type="text"
                                        value={data.text_placeholder}
                                        onChange={(e) => setData('text_placeholder', e.target.value)}
                                        disabled={processing}
                                        placeholder="Ej. Nombre a grabar"
                                        className={inputClasses}
                                        maxLength={255}
                                    />
                                    {errors.text_placeholder && (
                                        <p className="mt-1.5 text-xs font-medium text-rose-600">
                                            {errors.text_placeholder}
                                        </p>
                                    )}
                                </div>
                                <div>
                                    <label
                                        htmlFor="max_characters"
                                        className="mb-1.5 block text-sm font-medium text-slate-700"
                                    >
                                        Máx. de caracteres
                                    </label>
                                    <input
                                        id="max_characters"
                                        type="number"
                                        step="1"
                                        min="1"
                                        value={data.max_characters}
                                        onChange={(e) => setData('max_characters', e.target.value)}
                                        disabled={processing}
                                        className={inputClasses}
                                    />
                                    {errors.max_characters && (
                                        <p className="mt-1.5 text-xs font-medium text-rose-600">
                                            {errors.max_characters}
                                        </p>
                                    )}
                                </div>
                            </div>
                        )}

                        <div className="rounded-lg bg-navy/5 px-4 py-3.5">
                            <p className="text-xs font-semibold uppercase tracking-wider text-navy/60">Vista previa</p>
                            <p className="mt-1 text-sm font-medium text-navy">{buildAddonPreviewText(data)}</p>
                        </div>

                        <div className="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-3.5">
                            <div>
                                <p className="text-sm font-medium text-slate-900">Add-on activo</p>
                                <p className="text-xs text-slate-500">
                                    {data.is_active
                                        ? 'Se puede ofrecer en productos.'
                                        : 'Queda guardado pero no se puede asociar a productos.'}
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

                        {addon.products_count > 0 && (
                            <p className="text-xs text-slate-500">
                                Este add-on está asociado a {addon.products_count}{' '}
                                {addon.products_count === 1 ? 'producto' : 'productos'}.
                            </p>
                        )}
                    </div>

                    <div className="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-6">
                        <Link
                            href={route('admin.addons.index')}
                            className="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            Cancelar
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex items-center rounded-lg bg-gold px-4 py-2 text-sm font-semibold text-navy transition hover:brightness-95 disabled:opacity-50"
                        >
                            {processing ? 'Guardando...' : 'Guardar cambios'}
                        </button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
