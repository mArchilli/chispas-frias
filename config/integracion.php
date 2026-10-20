<?php

/*
|--------------------------------------------------------------------------
| Integración con el Sistema Central Rukoz — interruptores de la web
|--------------------------------------------------------------------------
|
| El paquete rukoz/core-client se enciende con RUKOZ_INTEGRACION (config/rukoz.php) y
| solo habilita la COMUNICACIÓN con el Central. Cada interruptor de acá habilita una
| parte de la web, por etapa, para poder probar una sin tocar las demás (p. ej. el
| catálogo en local sin tocar el checkout). Todos apagados por defecto: en producción
| quedan en false hasta la puesta en marcha.
|
|   catalogo      10.1  mandar el catálogo (productos, variantes y combos) al Central
|   stock         10.2  espejar el stock que informa el Central
|   checkout      10.3  reservar el stock en el Central al crear un pedido (exige `stock`)
|   confirmacion  10.4  confirmar la venta desde el admin y recibir los estados del Central
|
| `checkout` solo vale con `stock` y RUKOZ_INTEGRACION encendidos (App\Rukoz\CheckoutCentral::activo()):
| si no, el checkout NO usa el Central, queda un warning en el log y `chispasfrias:integracion-estado` lo informa.
| `confirmacion` existe para que la 10.4 lo use sin cambiar la forma de configurarlo.
|
*/

return [
    'catalogo' => (bool) env('INTEGRACION_CATALOGO', false),
    'stock' => (bool) env('INTEGRACION_STOCK', false),
    'checkout' => (bool) env('INTEGRACION_CHECKOUT', false),
    'confirmacion' => (bool) env('INTEGRACION_CONFIRMACION', false),

    /*
    | 10.3 — qué pasa si el Central no responde al reservar (caído, timeout o circuito abierto).
    | false (por defecto): NO se vende sin reserva. El pedido no se crea y el cliente ve un mensaje amable con el botón de
    | WhatsApp y su carrito armado en el texto. true: el pedido se crea igual, sin `rukoz_ref`, con el stock local de
    | siempre; el Central nunca se entera de esa venta (hay que cargarla a mano). Ver
    | App\Rukoz\CheckoutCentral::permiteVenderSinReserva().
    */
    'checkout_sin_reserva' => (bool) env('INTEGRACION_CHECKOUT_SIN_RESERVA', false),

    /*
    | 10.3 — días que un pedido con reserva puede quedar sin confirmar antes de cancelarse solo
    | (`chispasfrias:cancelar-reservas-viejas`). Las reservas del Central NO vencen: si la web no las cancela, el stock
    | queda apartado para siempre.
    */
    'cancelar_dias' => (int) env('PEDIDOS_CANCELAR_DIAS', 3),

    // 10.3 — durante cuántos segundos un pedido ya creado frena a un doble envío idéntico (doble clic, reintento del navegador).
    'ventana_doble_envio' => (int) env('CHECKOUT_VENTANA_DOBLE_ENVIO', 120),

    /*
    | 10.4 — Confirmar la venta desde el admin.
    |
    | `referencias_cache`: segundos que el modal «Confirmar venta» reutiliza la lista de cuentas, medios de pago, formas de envío,
    | transportistas y vendedores del Central (encima del caché por `hash` del paquete: evita hasta la consulta liviana).
    |
    | `referencias_respaldo`: si el Central no contesta `GET /referencias`, el modal usa esta lista (JSON en una sola variable,
    | con las mismas claves que la respuesta del Central: cuentas[{codigo,nombre}], medios_pago[{codigo,nombre}],
    | formas_envio[{id,nombre,es_retiro,...}], transportistas[{id,nombre}]). Vacía por defecto: sin Central y sin respaldo no se
    | puede armar el formulario y no se confirma nada.
    |
    | `forma_envio_id` / `transportista_id`: valores que el modal preselecciona (opcional); si no, la primera forma de envío que no
    | sea retiro y el transportista que sugiere el Central.
    |
    | El punto de atención no se manda: esta web tiene uno solo y rige el predeterminado de la integración en el Central.
    */
    'referencias_cache' => (int) env('INTEGRACION_REFERENCIAS_CACHE', 300),
    'referencias_respaldo' => json_decode((string) env('INTEGRACION_REFERENCIAS_RESPALDO', '{}'), true) ?: [],
    'forma_envio_id' => env('INTEGRACION_FORMA_ENVIO_ID') !== null ? (int) env('INTEGRACION_FORMA_ENVIO_ID') : null,
    'transportista_id' => env('INTEGRACION_TRANSPORTISTA_ID') !== null ? (int) env('INTEGRACION_TRANSPORTISTA_ID') : null,
];
