import React from 'react';

function Field({ label, htmlFor, error, hint, children }) {
    return (
        <div>
            <label htmlFor={htmlFor} className="block text-sm font-medium text-slate-700">
                {label}
            </label>
            <div className="mt-1.5">{children}</div>
            {error ? (
                <p className="mt-1.5 text-xs font-medium text-rose-600">{error}</p>
            ) : (
                hint && <p className="mt-1.5 text-xs text-slate-500">{hint}</p>
            )}
        </div>
    );
}

function inputClasses(hasError) {
    return `block w-full rounded-lg border px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 transition focus:outline-none focus:ring-2 ${
        hasError
            ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-100'
            : 'border-slate-300 focus:border-navy focus:ring-navy/10'
    }`;
}

export default function CategoryForm({ data, setData, errors = {}, mainCategories = [], slug = null }) {
    return (
        <div className="divide-y divide-slate-100">
            <div className="space-y-5 pb-6">
                <Field label="Nombre *" htmlFor="name" error={errors.name}>
                    <input
                        id="name"
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        className={inputClasses(errors.name)}
                        placeholder="Ej. Fuegos Artificiales"
                        required
                    />
                </Field>

                {slug && (
                    <Field label="Slug (URL)" htmlFor="slug" hint="Se genera automáticamente a partir del nombre.">
                        <input
                            id="slug"
                            type="text"
                            value={slug}
                            readOnly
                            className={`${inputClasses(false)} bg-slate-50 text-slate-500`}
                        />
                    </Field>
                )}

                <Field label="Descripción" htmlFor="description" error={errors.description} hint="Opcional, visible en la tienda.">
                    <textarea
                        id="description"
                        rows={3}
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                        className={inputClasses(errors.description)}
                        placeholder="Descripción breve de la categoría"
                    />
                </Field>
            </div>

            <div className="grid grid-cols-1 gap-5 py-6 sm:grid-cols-2">
                <Field
                    label="Categoría padre"
                    htmlFor="parent_id"
                    error={errors.parent_id}
                    hint="Vacío = categoría principal."
                >
                    <select
                        id="parent_id"
                        value={data.parent_id}
                        onChange={(e) => setData('parent_id', e.target.value)}
                        className={inputClasses(errors.parent_id)}
                    >
                        <option value="">Sin padre (principal)</option>
                        {mainCategories.map((category) => (
                            <option key={category.id} value={category.id}>
                                {category.name}
                            </option>
                        ))}
                    </select>
                </Field>

                <Field label="Orden" htmlFor="sort_order" error={errors.sort_order} hint="Menor número aparece primero.">
                    <input
                        id="sort_order"
                        type="number"
                        min="0"
                        value={data.sort_order}
                        onChange={(e) => setData('sort_order', parseInt(e.target.value, 10) || 0)}
                        className={inputClasses(errors.sort_order)}
                    />
                </Field>
            </div>

            <div className="pt-6">
                <div className="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-3.5">
                    <div>
                        <p className="text-sm font-medium text-slate-900">Categoría activa</p>
                        <p className="text-xs text-slate-500">
                            {data.is_active ? 'Visible en la tienda.' : 'Oculta para los clientes.'}
                        </p>
                    </div>
                    <label className="relative inline-flex cursor-pointer items-center">
                        <input
                            type="checkbox"
                            checked={data.is_active}
                            onChange={(e) => setData('is_active', e.target.checked)}
                            className="peer sr-only"
                        />
                        <div className="peer h-6 w-11 rounded-full bg-slate-300 transition-colors after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-gold peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-gold/20" />
                    </label>
                </div>
                {errors.is_active && <p className="mt-1.5 text-xs font-medium text-rose-600">{errors.is_active}</p>}
            </div>
        </div>
    );
}
