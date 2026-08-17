import React, { useState } from 'react';
import toast from 'react-hot-toast';
import { IconUploadCloud, IconX } from '@/Components/Admin/Icons';

const MAX_FILES = 10;
const MAX_SIZE = 20 * 1024 * 1024; // 20MB
const ALLOWED_TYPES = [
    'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
    'video/mp4', 'video/mov', 'video/avi', 'video/quicktime', 'video/wmv', 'video/x-ms-wmv',
    'video/flv', 'video/x-flv', 'video/webm',
];

/**
 * Selector de imágenes/videos por click o drag&drop, con preview y remoción.
 * `files` es un array de File (o null); compartido entre Create ("images")
 * y Edit ("new_images") de productos.
 */
export default function MediaDropzone({ files, onChange, error, inputId = 'media-dropzone' }) {
    const [isDragging, setIsDragging] = useState(false);
    const dragCounter = React.useRef(0);

    const addFiles = (incoming) => {
        if (!incoming || incoming.length === 0) return;

        const validFiles = Array.from(incoming).filter((file) => {
            if (!ALLOWED_TYPES.includes(file.type)) {
                toast.error(`Archivo no válido: ${file.name}`);
                return false;
            }
            if (file.size > MAX_SIZE) {
                toast.error(`Archivo muy grande (máx. 20MB): ${file.name}`);
                return false;
            }
            return true;
        });

        const current = files ? Array.from(files) : [];
        if (current.length + validFiles.length > MAX_FILES) {
            toast.error(`Máximo ${MAX_FILES} archivos permitidos.`);
            return;
        }

        onChange([...current, ...validFiles]);
    };

    const removeFile = (index) => {
        const updated = Array.from(files).filter((_, i) => i !== index);
        onChange(updated.length > 0 ? updated : null);
    };

    const handleDragEnter = (e) => {
        e.preventDefault();
        e.stopPropagation();
        dragCounter.current += 1;
        if (e.dataTransfer.items?.length > 0) setIsDragging(true);
    };

    const handleDragLeave = (e) => {
        e.preventDefault();
        e.stopPropagation();
        dragCounter.current -= 1;
        if (dragCounter.current <= 0) setIsDragging(false);
    };

    const handleDrop = (e) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);
        dragCounter.current = 0;
        addFiles(e.dataTransfer.files);
        e.dataTransfer.clearData();
    };

    return (
        <div>
            <label
                htmlFor={inputId}
                onDragEnter={handleDragEnter}
                onDragLeave={handleDragLeave}
                onDragOver={(e) => e.preventDefault()}
                onDrop={handleDrop}
                className={`flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-4 py-8 text-center transition ${
                    isDragging ? 'border-navy bg-navy/5' : 'border-slate-300 bg-slate-50 hover:border-slate-400'
                }`}
            >
                <IconUploadCloud className={`h-7 w-7 ${isDragging ? 'text-navy' : 'text-slate-400'}`} />
                <p className="text-sm text-slate-600">
                    <span className="font-semibold text-navy">Elegí archivos</span> o arrastralos acá
                </p>
                <p className="text-xs text-slate-400">Imágenes o video · máx. 20MB · hasta {MAX_FILES} archivos</p>
                <input
                    id={inputId}
                    type="file"
                    multiple
                    accept="image/*,video/*"
                    className="sr-only"
                    onChange={(e) => addFiles(e.target.files)}
                />
            </label>
            {error && <p className="mt-1.5 text-xs font-medium text-rose-600">{error}</p>}

            {files && files.length > 0 && (
                <div className="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-4">
                    {Array.from(files).map((file, index) => {
                        const isVideo = file.type.startsWith('video/');
                        const url = URL.createObjectURL(file);
                        return (
                            <div key={index} className="group relative aspect-square overflow-hidden rounded-lg border border-slate-200">
                                {isVideo ? (
                                    <video src={url} className="h-full w-full object-cover" muted />
                                ) : (
                                    <img src={url} alt={file.name} className="h-full w-full object-cover" />
                                )}
                                <button
                                    type="button"
                                    onClick={() => removeFile(index)}
                                    className="absolute right-1 top-1 flex h-5 w-5 items-center justify-center rounded-full bg-black/60 text-white opacity-0 transition group-hover:opacity-100"
                                    title="Quitar"
                                >
                                    <IconX className="h-3 w-3" />
                                </button>
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
