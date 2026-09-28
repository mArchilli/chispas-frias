import { useEffect, useMemo, useRef, useState } from 'react';
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/react';
import { getProductImageUrl } from '@/utils/images';
import { galeriaDeVariante } from '@/utils/productOptions';

const isVideo = (media) => media?.type === 'video' || media?.mime_type?.startsWith('video/');
const mediaUrl = (media) => getProductImageUrl(media?.url || media?.path);

function GalleryIcon({ name, className = 'h-5 w-5' }) {
    const paths = {
        expand: 'M8 3H3v5m13-5h5v5M3 16v5h5m13-5v5h-5',
        close: 'M6 6l12 12M6 18L18 6',
        previous: 'M15 18l-6-6 6-6',
        next: 'M9 6l6 6-6 6',
        plus: 'M12 5v14M5 12h14',
        minus: 'M5 12h14',
        image: 'M4 16l5-5 4 4 3-3 4 4M8 7h.01M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z',
    };

    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth={1.7} strokeLinecap="round" strokeLinejoin="round" viewBox="0 0 24 24" aria-hidden="true">
            <path d={paths[name]} />
        </svg>
    );
}

function Media({ media, title, thumbnail = false, paused = false }) {
    const [failed, setFailed] = useState(false);
    const videoRef = useRef(null);
    const src = mediaUrl(media);

    useEffect(() => {
        if (paused) videoRef.current?.pause();
    }, [paused]);

    if (!src || failed) {
        return (
            <div className="flex h-full w-full flex-col items-center justify-center gap-3 bg-ice-50 text-navy-700">
                <GalleryIcon name="image" className={thumbnail ? 'h-6 w-6' : 'h-12 w-12'} />
                {!thumbnail && <span className="text-sm">Imagen no disponible</span>}
            </div>
        );
    }

    if (isVideo(media)) {
        return (
            <div className="relative h-full w-full">
                <video ref={videoRef} src={src} className="h-full w-full object-contain" controls={!thumbnail} muted playsInline preload="metadata" onError={() => setFailed(true)} aria-label={thumbnail ? undefined : `Video de ${title}`} aria-hidden={thumbnail || undefined}>
                    Tu navegador no soporta el video.
                </video>
                {thumbnail && (
                    <span className="pointer-events-none absolute inset-0 flex items-center justify-center bg-navy-900/25">
                        <svg className="h-7 w-7 text-white" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z" /></svg>
                    </span>
                )}
            </div>
        );
    }

    return <img src={src} alt={thumbnail ? '' : (media.alt_text || title)} className="h-full w-full object-contain" decoding="async" loading={thumbnail ? 'lazy' : 'eager'} onError={() => setFailed(true)} />;
}

const iconButton = 'inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-storefront text-storefront transition hover:bg-storefront hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-storefront focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-35';

export default function ProductGallery({ product, variantId = null }) {
    const media = useMemo(() => {
        const items = galeriaDeVariante(product.images || [], variantId).filter((item) => mediaUrl(item));
        return variantId == null
            ? items.sort((a, b) => Number(Boolean(b.is_primary)) - Number(Boolean(a.is_primary)) || (a.sort_order ?? 0) - (b.sort_order ?? 0))
            : items;
    }, [product.images, variantId]);
    const [selectedIndex, setSelectedIndex] = useState(0);
    const [open, setOpen] = useState(false);
    const [zoom, setZoom] = useState(1);
    const selected = media[selectedIndex] || media[0];

    useEffect(() => {
        setSelectedIndex(0);
        setZoom(1);
        setOpen(false);
    }, [variantId]);

    const select = (index) => {
        setSelectedIndex((index + media.length) % media.length);
        setZoom(1);
    };

    const close = () => {
        setOpen(false);
        setZoom(1);
    };

    return (
        <section aria-label={`Galería de ${product.title}`} className="min-w-0 rounded-[1.75rem] border border-gray-200 bg-surface p-3 text-navy-900 shadow-card sm:p-4">
            <div className="relative aspect-square max-h-[560px] overflow-hidden rounded-[1.35rem] bg-surface">
                {!selected ? (
                    <Media title={product.title} />
                ) : isVideo(selected) ? (
                    <>
                        <Media key={mediaUrl(selected)} media={selected} title={product.title} paused={open} />
                        <button type="button" onClick={() => setOpen(true)} className="absolute right-3 top-3 rounded-full border border-navy-900 bg-white p-3 text-navy-900 shadow-soft hover:bg-ice-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-900" aria-label="Ampliar video">
                            <GalleryIcon name="expand" />
                        </button>
                    </>
                ) : (
                    <button type="button" onClick={() => setOpen(true)} className="group block h-full w-full cursor-zoom-in rounded-[1.35rem] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-navy-900" aria-label={`Ampliar imagen de ${product.title}`}>
                        <Media key={mediaUrl(selected)} media={selected} title={product.title} />
                        <span className="pointer-events-none absolute bottom-3 right-3 flex h-11 w-11 items-center justify-center rounded-full border border-storefront bg-white text-storefront shadow-soft transition group-hover:bg-storefront group-hover:text-white">
                            <GalleryIcon name="expand" />
                        </span>
                    </button>
                )}
            </div>

            {media.length > 1 && (
                <div className="mt-3 flex gap-2 overflow-x-auto p-1" role="group" aria-label="Elegir imagen o video">
                    {media.map((item, index) => (
                        <button
                            key={item.id || mediaUrl(item)}
                            type="button"
                            onClick={() => select(index)}
                            aria-label={`Ver ${isVideo(item) ? 'video' : 'imagen'} ${index + 1} de ${media.length}`}
                            aria-pressed={selectedIndex === index}
                            className={`h-20 w-20 shrink-0 overflow-hidden rounded-2xl border-2 bg-surface p-1 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-900 focus-visible:ring-offset-2 ${selectedIndex === index ? 'border-navy-900' : 'border-gray-200 hover:border-navy-700'}`}
                        >
                            <Media media={item} title={product.title} thumbnail />
                        </button>
                    ))}
                </div>
            )}
            {selected && <p className="px-2 pb-1 pt-3 text-center text-xs text-navy-900/70">{isVideo(selected) ? 'Reproducí el video para ver el producto en acción.' : 'Tocá la imagen para verla en detalle.'}</p>}

            <Dialog open={open} onClose={close} className="relative z-[70]" onKeyDown={(event) => {
                if (isVideo(selected) || zoom > 1 || media.length < 2) return;
                if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
                    event.preventDefault();
                    select(selectedIndex + (event.key === 'ArrowLeft' ? -1 : 1));
                }
            }}>
                <div className="fixed inset-0 bg-navy-900/80" aria-hidden="true" />
                <div className="fixed inset-0 flex items-center justify-center p-3 sm:p-6">
                    <DialogPanel className="flex h-[88dvh] max-h-[900px] w-full max-w-5xl flex-col overflow-hidden rounded-[1.75rem] border border-gray-200 bg-surface text-navy-900 shadow-card">
                        <div className="flex items-center justify-between gap-3 p-4 sm:px-6">
                            <DialogTitle className="uppercase line-clamp-2 min-w-0 text-sm font-semibold sm:text-base">{product.title}</DialogTitle>
                            <button type="button" onClick={close} className={iconButton} aria-label="Cerrar galería" data-autofocus>
                                <GalleryIcon name="close" />
                            </button>
                        </div>
                        <div key={`${selectedIndex}-${open}`} className="min-h-0 flex-1 overflow-auto overscroll-contain bg-surface" tabIndex={zoom > 1 ? 0 : undefined} aria-label={zoom > 1 ? 'Imagen ampliada; desplazate para ver los detalles' : undefined}>
                            <div style={{ width: `${zoom * 100}%`, height: `${zoom * 100}%` }}>
                                <Media key={mediaUrl(selected)} media={selected} title={product.title} />
                            </div>
                        </div>
                        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 p-3 sm:px-6">
                            <div className="flex items-center gap-2">
                                {media.length > 1 && <button type="button" onClick={() => select(selectedIndex - 1)} className={iconButton} aria-label="Ver anterior"><GalleryIcon name="previous" /></button>}
                                <span className="min-w-12 text-center text-sm tabular-nums" aria-live="polite">{selectedIndex + 1} / {media.length}</span>
                                {media.length > 1 && <button type="button" onClick={() => select(selectedIndex + 1)} className={iconButton} aria-label="Ver siguiente"><GalleryIcon name="next" /></button>}
                            </div>
                            {!isVideo(selected) && (
                                <div className="flex items-center gap-2">
                                    <button type="button" onClick={() => setZoom((value) => Math.max(1, value - 0.5))} disabled={zoom === 1} className={iconButton} aria-label="Reducir imagen"><GalleryIcon name="minus" /></button>
                                    <span className="min-w-12 text-center text-sm tabular-nums" aria-live="polite">{zoom * 100}%</span>
                                    <button type="button" onClick={() => setZoom((value) => Math.min(3, value + 0.5))} disabled={zoom === 3} className={iconButton} aria-label="Aumentar imagen"><GalleryIcon name="plus" /></button>
                                </div>
                            )}
                        </div>
                    </DialogPanel>
                </div>
            </Dialog>
        </section>
    );
}
