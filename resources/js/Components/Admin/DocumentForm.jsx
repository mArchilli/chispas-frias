import React from 'react';
import { IconExternalLink, IconFileText } from '@/Components/Admin/Icons';

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

/**
 * Formulario compartido por Create/Edit de Documentos.
 *
 * El campo `type` (Enlace / PDF) decide qué campo se muestra: `url` para enlace,
 * `file` para PDF. `currentFileUrl` (sólo en edición) muestra el PDF ya cargado
 * para no obligar a re-subirlo si no cambió.
 */
export default function DocumentForm({ data, setData, errors = {}, tipos = [], currentFileUrl = null }) {
    const esLink = data.type === 'link';

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
                        placeholder="Ej. Manual de armado de gazebo"
                        maxLength={150}
                        required
                    />
                </Field>

                <Field
                    label="Descripción"
                    htmlFor="description"
                    error={errors.description}
                    hint="Opcional. Una línea que aclare de qué se trata."
                >
                    <textarea
                        id="description"
                        rows={3}
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                        className={inputClasses(errors.description)}
                        placeholder="Breve detalle del documento"
                    />
                </Field>
            </div>

            <div className="space-y-5 py-6">
                <Field label="Tipo *" htmlFor="type" error={errors.type}>
                    <div className="grid grid-cols-2 gap-3">
                        {tipos.map((tipo) => {
                            const activo = data.type === tipo.value;
                            const Icon = tipo.value === 'link' ? IconExternalLink : IconFileText;
                            return (
                                <button
                                    key={tipo.value}
                                    type="button"
                                    onClick={() => setData('type', tipo.value)}
                                    className={`flex items-center gap-2 rounded-lg border px-3 py-2.5 text-sm font-medium transition ${
                                        activo
                                            ? 'border-navy bg-navy/5 text-navy'
                                            : 'border-slate-300 text-slate-600 hover:bg-slate-50'
                                    }`}
                                >
                                    <Icon className="h-4 w-4 flex-shrink-0" />
                                    {tipo.label}
                                </button>
                            );
                        })}
                    </div>
                </Field>

                {esLink ? (
                    <Field
                        label="Enlace *"
                        htmlFor="url"
                        error={errors.url}
                        hint="URL completa, incluyendo https://"
                    >
                        <input
                            id="url"
                            type="url"
                            value={data.url || ''}
                            onChange={(e) => setData('url', e.target.value)}
                            className={inputClasses(errors.url)}
                            placeholder="https://drive.google.com/..."
                            maxLength={2000}
                        />
                    </Field>
                ) : (
                    <Field
                        label={currentFileUrl ? 'Reemplazar PDF' : 'Archivo PDF *'}
                        htmlFor="file"
                        error={errors.file}
                        hint={
                            currentFileUrl
                                ? 'Dejá vacío para conservar el PDF actual. Máx. 20 MB.'
                                : 'Sólo PDF, hasta 20 MB.'
                        }
                    >
                        {currentFileUrl && (
                            <a
                                href={currentFileUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="mb-2 inline-flex items-center gap-1.5 text-xs font-medium text-navy hover:underline"
                            >
                                <IconFileText className="h-3.5 w-3.5" />
                                Ver PDF actual
                            </a>
                        )}
                        <input
                            id="file"
                            type="file"
                            accept="application/pdf"
                            onChange={(e) => setData('file', e.target.files[0] || null)}
                            className="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-navy/5 file:px-3 file:py-2 file:text-sm file:font-medium file:text-navy hover:file:bg-navy/10"
                        />
                        {data.file && (
                            <p className="mt-1.5 text-xs text-slate-500">Seleccionado: {data.file.name}</p>
                        )}
                    </Field>
                )}
            </div>

            <div className="space-y-5 py-6">
                <Field label="Orden" htmlFor="sort_order" error={errors.sort_order} hint="Menor número aparece primero.">
                    <input
                        id="sort_order"
                        type="number"
                        min="0"
                        value={data.sort_order}
                        onChange={(e) => setData('sort_order', parseInt(e.target.value, 10) || 0)}
                        className={`${inputClasses(errors.sort_order)} sm:max-w-[160px]`}
                    />
                </Field>
            </div>

            <div className="pt-6">
                <div className="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-3.5">
                    <div>
                        <p className="text-sm font-medium text-slate-900">Documento activo</p>
                        <p className="text-xs text-slate-500">
                            {data.is_active
                                ? 'Visible para los vendedores.'
                                : 'Oculto: sólo lo ve el admin, no aparece en el listado del vendedor.'}
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
