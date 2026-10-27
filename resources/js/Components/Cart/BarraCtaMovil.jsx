const money = (value) => Number(value || 0).toLocaleString('es-AR');

/**
 * Espacio que la página tiene que dejar al final para que la barra fija no tape el último contenido (solo < lg, que es cuando
 * la barra se dibuja). Mide la barra más el área segura del dispositivo.
 */
export function EspacioBarraCtaMovil() {
    return <div className="h-[calc(7rem+env(safe-area-inset-bottom))] lg:hidden" aria-hidden="true" />;
}

/**
 * Barra fija al pie de la pantalla en mobile (< lg): el total y la acción principal (pasar al checkout / enviar el pedido) siempre
 * a la vista, sin tener que bajar hasta el final del resumen. Respeta el área segura del dispositivo (barra de gestos de iOS). A
 * partir de lg no se dibuja: ahí el resumen es lateral y su botón ya queda a la vista.
 *
 * `children` es el botón (o link) principal; `nota`, un aviso corto arriba del total (p. ej. por qué el botón está bloqueado).
 */
export default function BarraCtaMovil({ total, nota = null, children }) {
    return (
        <div
            className="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-surface/95 px-4 pt-3 text-navy-900 shadow-[0_-12px_30px_rgba(10,31,68,0.12)] backdrop-blur lg:hidden"
            style={{ paddingBottom: 'max(0.75rem, env(safe-area-inset-bottom))' }}
        >
            {nota && <p role="status" className="mb-2 text-center text-[11px] font-semibold leading-snug text-rose-700">{nota}</p>}
            <div className="flex items-center gap-4">
                <div className="shrink-0">
                    <p className="text-[10px] font-semibold uppercase tracking-wider text-navy-900/60">Total</p>
                    <p className="text-xl font-bold leading-tight">${money(total)}</p>
                </div>
                <div className="min-w-0 flex-1">{children}</div>
            </div>
        </div>
    );
}
