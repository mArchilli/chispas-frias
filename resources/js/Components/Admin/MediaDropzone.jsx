import React, { useState } from 'react';
import toast from 'react-hot-toast';
import { IconUploadCloud, IconX, IconVideo } from '@/Components/Admin/Icons';

const MAX_FILES = 10;
const MAX_SIZE = 20 * 1024 * 1024; // 20MB
const IMAGE_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
const VIDEO_TYPES = [
    'video/mp4', 'video/mov', 'video/avi', 'video/quicktime', 'video/wmv', 'video/x-ms-wmv',
    'video/flv', 'video/x-flv', 'video/webm',
];

/**
 * Selector de imágenes/videos por click o drag&drop. Las imágenes y los videos
 * se cargan y se previsualizan en secciones SEPARADAS (dos zonas distintas), pero
 * comparten un único array `files` (y `variantRefs` alineado por índice) para el
 * submit — el backend deriva el tipo por su mime como siempre.
 *
 * Opcional: si se pasa `variantOptions` ([{ value, label }]), cada archivo nuevo
 * muestra un <select> para asociarlo a una variante de color. `variantRefs` es un
 * array alineado por índice con `files` (cada valor: '' | '<variantId>' |
 * 'uid:<_uid>'); se mantiene sincronizado con `files` al agregar/quitar.
 */
export default function MediaDropzone({
    files,
    onChange,
    error,
    inputId = 'media-dropzone',
    variantOptions = null,
    variantRefs = [],
    onVariantRefsChange,
}) {
    const [dragKind, setDragKind] = useState(null);
    const dragCounter = React.useRef(0);
    const withVariants = Array.isArray(variantOptions) && variantOptions.length > 0;

    const list = files ? Array.from(files) : [];

    const addFiles = (incoming, kind) => {
        if (!incoming || incoming.length === 0) return;

        const allowed = kind === 'video' ? VIDEO_TYPES : IMAGE_TYPES;

        const validFiles = Array.from(incoming).filter((file) => {
            const isVideo = file.type.startsWith('video/');

            // Cada zona sólo acepta su tipo: evita que un video caiga en "Imágenes".
            if (kind === 'image' && isVideo) {
                toast.error(`«${file.name}» es un video: subilo en la sección Videos.`);
                return false;
            }
            if (kind === 'video' && !isVideo) {
                toast.error(`«${file.name}» es una imagen: subila en la sección Imágenes.`);
                return false;
            }
            if (!allowed.includes(file.type)) {
                toast.error(`Archivo no válido: ${file.name}`);
                return false;
            }
            if (file.size > MAX_SIZE) {
                toast.error(`Archivo muy grande (máx. 20MB): ${file.name}`);
                return false;
            }
            return true;
        });

        if (validFiles.length === 0) return;

        if (list.length + validFiles.length > MAX_FILES) {
            toast.error(`Máximo ${MAX_FILES} archivos permitidos.`);
            return;
        }

        onChange([...list, ...validFiles]);
        onVariantRefsChange?.([...(variantRefs || []).slice(0, list.length), ...validFiles.map(() => '')]);
    };

    const removeFile = (index) => {
        const updated = list.filter((_, i) => i !== index);
        onChange(updated.length > 0 ? updated : null);
        onVariantRefsChange?.((variantRefs || []).filter((_, i) => i !== index));
    };

    const setVariantRef = (index, value) => {
        const next = Array.from({ length: list.length }, (_, i) => variantRefs?.[i] ?? '');
        next[index] = value;
        onVariantRefsChange?.(next);
    };

    const handleDragEnter = (kind) => (e) => {
        e.preventDefault();
        e.stopPropagation();
        dragCounter.current += 1;
        if (e.dataTransfer.items?.length > 0) setDragKind(kind);
    };

    const handleDragLeave = (e) => {
        e.preventDefault();
        e.stopPropagation();
        dragCounter.current -= 1;
        if (dragCounter.current <= 0) setDragKind(null);
    };

    const handleDrop = (kind) => (e) => {
        e.preventDefault();
        e.stopPropagation();
        setDragKind(null);
        dragCounter.current = 0;
        addFiles(e.dataTransfer.files, kind);
        e.dataTransfer.clearData();
    };

    // Cada archivo conserva su índice real en el array combinado (para quitar /
    // asociar variante); se agrupan por tipo sólo para mostrarlos separados.
    const entries = list.map((file, index) => ({ file, index }));
    const imageEntries = entries.filter((e) => !e.file.type.startsWith('video/'));
    const videoEntries = entries.filter((e) => e.file.type.startsWith('video/'));

    const renderPreview = ({ file, index }) => {
        const isVideo = file.type.startsWith('video/');
        const url = URL.createObjectURL(file);

        return (
            <div key={index} className="space-y-1">
                <div className="group relative aspect-square overflow-hidden rounded-lg border border-gray-200">
                    {isVideo ? (
                        <video src={url} className="h-full w-full object-cover" muted />
                    ) : (
                        <img src={url} alt={file.name} className="h-full w-full object-cover" />
                    )}
                    <button
                        type="button"
                        onClick={() => removeFile(index)}
                        className="absolute right-1 top-1 flex h-5 w-5 items-center justify-center rounded-full bg-graphite/60 text-white opacity-0 transition group-hover:opacity-100"
                        title="Quitar"
                    >
                        <IconX className="h-3 w-3" />
                    </button>
                    {isVideo && (
                        <span className="absolute bottom-1 left-1 flex h-5 w-5 items-center justify-center rounded-full bg-graphite/60 text-white">
                            <IconVideo className="h-3 w-3" />
                        </span>
                    )}
                </div>
                {withVariants && (
                    <select
                        value={variantRefs?.[index] ?? ''}
                        onChange={(e) => setVariantRef(index, e.target.value)}
                        className="block w-full rounded-md border border-gray-200 px-1.5 py-1 text-[11px] text-graphite/75 focus:border-navy-900 focus:outline-none focus:ring-1 focus:ring-navy-900/10"
                        title="Color asociado"
                    >
                        <option value="">General</option>
                        {variantOptions.map((opt) => (
                            <option key={opt.value} value={opt.value}>
                                {opt.label}
                            </option>
                        ))}
                    </select>
                )}
            </div>
        );
    };

    const renderZone = (kind, label, hint, accept, groupEntries) => {
        const dragging = dragKind === kind;
        const id = `${inputId}-${kind}`;

        return (
            <div>
                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-graphite/70">{label}</p>
                <label
                    htmlFor={id}
                    onDragEnter={handleDragEnter(kind)}
                    onDragLeave={handleDragLeave}
                    onDragOver={(e) => e.preventDefault()}
                    onDrop={handleDrop(kind)}
                    className={`flex cursor-pointer flex-col items-center justify-center gap-1.5 rounded-xl border-2 border-dashed px-4 py-6 text-center transition ${
                        dragging ? 'border-navy-900 bg-navy-900/5' : 'border-gray-200 bg-background hover:border-gray-200'
                    }`}
                >
                    <IconUploadCloud className={`h-6 w-6 ${dragging ? 'text-navy-900' : 'text-gray-500'}`} />
                    <p className="text-sm text-graphite/75">
                        <span className="font-semibold text-navy-900">Elegí {kind === 'video' ? 'videos' : 'imágenes'}</span> o arrastralos acá
                    </p>
                    <p className="text-xs text-gray-500">{hint}</p>
                    <input
                        id={id}
                        type="file"
                        multiple
                        accept={accept}
                        className="sr-only"
                        onChange={(e) => addFiles(e.target.files, kind)}
                    />
                </label>

                {groupEntries.length > 0 && (
                    <div className={`mt-3 grid gap-2 ${withVariants ? 'grid-cols-2 sm:grid-cols-3' : 'grid-cols-3 sm:grid-cols-4'}`}>
                        {groupEntries.map(renderPreview)}
                    </div>
                )}
            </div>
        );
    };

    return (
        <div className="space-y-5">
            {renderZone('image', 'Imágenes', `JPG, PNG, WEBP o GIF · máx. 20MB · hasta ${MAX_FILES} archivos en total`, 'image/*', imageEntries)}
            {renderZone('video', 'Videos', 'MP4, MOV o WEBM · máx. 20MB', 'video/*', videoEntries)}
            {error && <p className="text-xs font-medium text-navy-700">{error}</p>}
        </div>
    );
}
