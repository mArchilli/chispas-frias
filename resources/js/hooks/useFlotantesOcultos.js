import { useEffect, useState } from 'react';

const MARCA = '[data-oculta-flotantes]';

/**
 * Los botones flotantes (carrito y WhatsApp) quedan fijos sobre el borde derecho y, en mobile, tapan el «+» de un selector de
 * cantidad cuando éste pasa por detrás. Devuelve true mientras algún elemento marcado con `data-oculta-flotantes` esté a la vista,
 * para que los botones se retiren (solo < lg: las clases `max-lg:` están en cada botón, no aquí, porque Tailwind solo escanea los .jsx) y vuelvan al salir del selector.
 * Observa también los elementos que aparecen después (una línea nueva del carrito, otra pantalla de Inertia).
 */
export function useFlotantesOcultos() {
    const [ocultos, setOcultos] = useState(false);

    useEffect(() => {
        if (typeof IntersectionObserver === 'undefined') return undefined;

        const visibles = new Set();
        const observados = new WeakSet();
        const io = new IntersectionObserver((entradas) => {
            entradas.forEach((e) => (e.isIntersecting ? visibles.add(e.target) : visibles.delete(e.target)));
            setOcultos(visibles.size > 0);
        });

        const observar = () => {
            document.querySelectorAll(MARCA).forEach((el) => {
                if (!observados.has(el)) {
                    observados.add(el);
                    io.observe(el);
                }
            });
            // Un elemento que se quitó del DOM sale de la lista (el observer ya no lo notifica).
            visibles.forEach((el) => {
                if (!el.isConnected) visibles.delete(el);
            });
            setOcultos(visibles.size > 0);
        };

        observar();
        const mo = new MutationObserver(observar);
        mo.observe(document.body, { childList: true, subtree: true });

        return () => {
            mo.disconnect();
            io.disconnect();
        };
    }, []);

    return ocultos;
}

