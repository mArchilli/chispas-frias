import React from 'react';

function Field({ label, htmlFor, error, hint, children }) {
    return (
        <div>
            <label htmlFor={htmlFor} className="block text-sm font-medium text-graphite/85">
                {label}
            </label>
            <div className="mt-1.5">{children}</div>
            {error ? (
                <p className="mt-1.5 text-xs font-medium text-navy-700">{error}</p>
            ) : (
                hint && <p className="mt-1.5 text-xs text-gray-500">{hint}</p>
            )}
        </div>
    );
}

function inputClasses(hasError) {
    return `block w-full rounded-lg border px-3 py-2 text-sm text-graphite placeholder:text-gray-500 transition focus:outline-none focus:ring-2 ${
        hasError
            ? 'border-navy-700 focus:border-ice-500 focus:ring-ice-100'
            : 'border-gray-200 focus:border-ice-500 focus:ring-ice-100'
    }`;
}

export default function CategoryForm({ data, setData, errors = {}, mainCategories = [], slug = null }) {
    return (
        <div className="divide-y divide-gray-200">
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
                            className={`${inputClasses(false)} bg-background text-gray-500`}
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
                <div className="flex items-center justify-between rounded-lg bg-background px-4 py-3.5">
                    <div>
                        <p className="text-sm font-medium text-graphite">Categoría activa</p>
                        <p className="text-xs text-gray-500">
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
                        <div className="peer h-6 w-11 rounded-full bg-gray-200 transition-colors after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-200 after:bg-surface after:transition-all after:content-[''] peer-checked:bg-ice-500 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-ice-500/20" />
                    </label>
                </div>
                {errors.is_active && <p className="mt-1.5 text-xs font-medium text-navy-700">{errors.is_active}</p>}
            </div>
        </div>
    );
}
