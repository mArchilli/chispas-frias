import React from 'react';
import ReactQuill from 'react-quill';
import 'react-quill/dist/quill.snow.css';
import { quillModules, quillFormats } from '@/utils/quillConfig';

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

export function inputClasses(hasError) {
    return `block w-full rounded-lg border px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 transition focus:outline-none focus:ring-2 ${
        hasError
            ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-100'
            : 'border-slate-300 focus:border-navy focus:ring-navy/10'
    }`;
}

/**
 * Campos base compartidos entre Create y Edit de productos: título,
 * descripción (Quill), precio, stock, SKU y categoría.
 */
export default function ProductBasicFields({ data, setData, errors = {}, categories = [] }) {
    return (
        <div className="divide-y divide-slate-100">
            <div className="space-y-5 pb-6">
                <Field label="Título *" htmlFor="title" error={errors.title}>
                    <input
                        id="title"
                        type="text"
                        value={data.title}
                        onChange={(e) => setData('title', e.target.value)}
                        className={inputClasses(errors.title)}
                        placeholder="Ej. Fantasía Dorada 9 Tiros"
                        required
                    />
                </Field>

                <Field label="Descripción *" htmlFor="description" error={errors.description}>
                    <div className={`rounded-lg ${errors.description ? 'ring-1 ring-rose-300' : ''}`}>
                        <ReactQuill
                            theme="snow"
                            value={data.description}
                            onChange={(value) => setData('description', value)}
                            placeholder="Describe características, efectos, colores y duración del producto..."
                            modules={quillModules}
                            formats={quillFormats}
                            className="quill-resizable bg-white"
                        />
                    </div>
                </Field>
            </div>

            <div className="grid grid-cols-1 gap-5 py-6 sm:grid-cols-2">
                <Field label="Precio de venta *" htmlFor="price" error={errors.price}>
                    <div className="relative">
                        <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                            $
                        </span>
                        <input
                            id="price"
                            type="number"
                            min="0"
                            step="0.01"
                            value={data.price}
                            onChange={(e) => setData('price', e.target.value)}
                            className={`${inputClasses(errors.price)} pl-7`}
                            placeholder="0.00"
                            required
                        />
                    </div>
                </Field>

                <Field
                    label="Stock"
                    htmlFor="stock"
                    error={errors.stock}
                    hint="Vacío = stock ilimitado (9999 unidades)."
                >
                    <input
                        id="stock"
                        type="number"
                        min="0"
                        value={data.stock}
                        onChange={(e) => setData('stock', e.target.value)}
                        className={inputClasses(errors.stock)}
                        placeholder="Ilimitado"
                    />
                </Field>

                <Field label="SKU" htmlFor="sku" error={errors.sku} hint="Código interno, opcional.">
                    <input
                        id="sku"
                        type="text"
                        value={data.sku}
                        onChange={(e) => setData('sku', e.target.value)}
                        className={inputClasses(errors.sku)}
                        placeholder="Ej. FA-9T-001"
                    />
                </Field>

                <Field label="Categoría *" htmlFor="category_id" error={errors.category_id}>
                    <select
                        id="category_id"
                        value={data.category_id}
                        onChange={(e) => setData('category_id', e.target.value)}
                        className={inputClasses(errors.category_id)}
                        required
                    >
                        <option value="">Seleccioná una categoría</option>
                        {categories.map((category) => (
                            <option key={category.id} value={category.id}>
                                {category.name}
                            </option>
                        ))}
                    </select>
                </Field>
            </div>
        </div>
    );
}
