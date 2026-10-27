const money = (value) => Number(value || 0).toLocaleString('es-AR');

/**
 * Avisos de una línea del carrito (página, drawer y checkout), todos calculados en el servidor:
 *
 *  - agotada (`stock_info.sin_stock`): la línea conserva su cantidad y el cliente tiene que sacarla para poder continuar;
 *  - ajustada (`stock_info.aviso`): el servidor bajó la cantidad a lo que queda disponible;
 *  - escala (`siguiente_escala`): «Sumá N más y pagás $X c/u» cuando el próximo escalón de precio por cantidad baja el precio.
 *
 * `compacto` achica el texto para el drawer. No dibuja nada si la línea no tiene ningún aviso.
 */
export default function AvisosDeLinea({ item, compacto = false, mostrarEscala = true, className = '' }) {
    const stock = item.stock_info || {};
    const escala = item.siguiente_escala || null;
    const texto = compacto ? 'text-[11px]' : 'text-xs';

    if (!stock.sin_stock && !stock.aviso && !(mostrarEscala && escala)) {
        return null;
    }

    return (
        <div className={`space-y-1.5 ${className}`}>
            {stock.sin_stock && (
                <p role="alert" className={`rounded-lg bg-rose-50 px-2.5 py-1.5 font-semibold leading-snug text-rose-700 ${texto}`}>
                    Sin stock. Sacalo del carrito para poder continuar.
                </p>
            )}

            {!stock.sin_stock && stock.aviso && (
                <p role="status" className={`rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1.5 font-semibold leading-snug text-amber-900 ${texto}`}>
                    Bajamos la cantidad de {stock.aviso.de} a {stock.aviso.a}: es lo que queda disponible.
                </p>
            )}

            {!stock.sin_stock && mostrarEscala && escala && (
                <p className={`inline-flex rounded-full bg-ice-100 px-2.5 py-1 font-semibold leading-snug text-navy-700 ${texto}`}>
                    Sumá {escala.faltan} más y pagás ${money(escala.precio_unitario)} c/u
                </p>
            )}
        </div>
    );
}

/** ¿Hay alguna línea agotada? Bloquea el envío del pedido hasta que el cliente la saque. */
export const hayLineasSinStock = (items) => (items || []).some((item) => item.stock_info?.sin_stock);
