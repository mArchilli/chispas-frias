import { useEffect, useMemo, useState } from 'react';
import axios from 'axios';

const DEBOUNCE_MS = 250;

/**
 * Productos compatibles con lo que hay en el carrito (los elige el admin; ver SugerenciasDeCarrito). El carrito vive en el
 * servidor, así que el pedido no manda nada: se vuelve a consultar cuando cambian las líneas (no cuando cambia solo una cantidad).
 * Si el pedido falla, simplemente no hay sugerencias: nunca debe romper el carrito.
 *
 * @param {Array} items Líneas del carrito (con `line_key`).
 * @param {boolean} activo Solo consulta si es true (p. ej. el drawer, únicamente mientras está abierto).
 */
export function useSugerenciasCarrito(items, activo = true) {
    const clave = useMemo(() => (items || []).map((item) => item.line_key).join('|'), [items]);
    const [productos, setProductos] = useState([]);

    useEffect(() => {
        if (!activo || clave === '') {
            setProductos([]);
            return undefined;
        }

        const controller = new AbortController();
        const timeoutId = setTimeout(async () => {
            try {
                const { data } = await axios.get(route('cart.suggestions'), {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });
                setProductos(data.productos ?? []);
            } catch {
                // Sin red, abortado o con límite de pedidos: se conservan las sugerencias que ya había.
            }
        }, DEBOUNCE_MS);

        return () => {
            clearTimeout(timeoutId);
            controller.abort();
        };
    }, [clave, activo]);

    return productos;
}
