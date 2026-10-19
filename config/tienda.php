<?php

/*
|--------------------------------------------------------------------------
| Configuración de la tienda
|--------------------------------------------------------------------------
|
| Valores propios de Chispas Frías que antes estaban escritos en el código o leídos con
| env() desde controllers y modelos. env() solo se puede usar en archivos de config/: con
| `php artisan config:cache` (lo normal en producción) devuelve null en cualquier otro lado.
| El resto de la aplicación lee estos valores con config('tienda.…').
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp de ventas
    |--------------------------------------------------------------------------
    |
    | Número (solo dígitos, con código de país: 54 9 + área + número) al que se envía
    | el pedido y que usan los botones de contacto. Lo comparte Inertia con el front
    | (HandleInertiaRequests → props.tienda.whatsapp).
    |
    */

    'whatsapp' => env('WHATSAPP_NUMERO', '5491178886833'),

    /*
    |--------------------------------------------------------------------------
    | Imágenes de productos y combos
    |--------------------------------------------------------------------------
    |
    | 'ruta': carpeta dentro de public/ donde se guardan y se leen los archivos
    |         (PRODUCT_IMAGES_PATH). En el hosting puede apuntar a public_html.
    | 'url':  prefijo web con el que se arma la URL pública de un archivo guardado solo
    |         por nombre (VITE_PRODUCT_IMAGES_PATH, la misma variable que usa el front
    |         compilado con Vite).
    |
    */

    'imagenes' => [
        'ruta' => env('PRODUCT_IMAGES_PATH', '/images/products/'),
        'url' => env('VITE_PRODUCT_IMAGES_PATH', '/images/products/'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Límite de requests (throttle) de los endpoints públicos
    |--------------------------------------------------------------------------
    |
    | Cuántas peticiones por minuto, por IP, acepta cada endpoint antes de responder 429.
    | Se definen como limitadores con nombre en AppServiceProvider y se aplican con
    | `throttle:checkout` y `throttle:validar-cupon` en routes/web.php.
    |
    | Un cliente real hace un puñado de intentos por minuto (confirmar el pedido, probar un
    | código). Subilos si varias personas comparten la misma IP y empiezan a toparse con
    | el límite.
    |
    */

    'limites' => [
        // POST /carrito/whatsapp — crea un pedido y descuenta stock.
        'checkout_por_minuto' => (int) env('THROTTLE_CHECKOUT_POR_MINUTO', 10),

        // POST /carrito/descuento — evita enumerar códigos de descuento.
        'validar_cupon_por_minuto' => (int) env('THROTTLE_VALIDAR_CUPON_POR_MINUTO', 30),

        // Lo que ve el cliente al superar el límite.
        'mensaje' => 'Estás haciendo muchos intentos seguidos. Esperá un minuto y probá de nuevo.',
    ],

];
