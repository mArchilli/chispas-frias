<?php

/*
|--------------------------------------------------------------------------
| Seguridad (auditoría S.2)
|--------------------------------------------------------------------------
|
| Todo se lee del .env, así se ajusta sin tocar código (con `config:cache` hay que volver a cachear).
| Qué hace cada cosa y cómo se prueba: docs/SEGURIDAD.md.
|
*/

$lista = fn (string $variable): array => array_values(array_filter(array_map('trim', explode(',', (string) env($variable, '')))));

return [

    /*
    | Content-Security-Policy.
    |   report-only (por defecto): el navegador NO bloquea nada, solo avisa (a /csp-report, que lo deja en storage/logs/seguridad.log).
    |   enforce: bloquea lo que no esté permitido. Se pasa a este modo recién después de un tiempo sin avisos en report-only.
    |   off: no manda la cabecera.
    |
    | Las listas `extra_*` suman orígenes (separados por coma) a la política sin tocar código: por ejemplo, si más adelante se agrega
    | Google Analytics, un píxel de Meta o Mercado Pago. Hoy la tienda no carga nada de terceros salvo la fuente de fonts.bunny.net.
    */
    'csp' => [
        'modo' => env('SEGURIDAD_CSP', 'report-only'),
        'reportar_a' => env('SEGURIDAD_CSP_REPORTAR_A', '/csp-report'),
        'extra_script' => $lista('SEGURIDAD_CSP_EXTRA_SCRIPT'),
        'extra_style' => $lista('SEGURIDAD_CSP_EXTRA_STYLE'),
        'extra_img' => $lista('SEGURIDAD_CSP_EXTRA_IMG'),
        'extra_font' => $lista('SEGURIDAD_CSP_EXTRA_FONT'),
        'extra_connect' => $lista('SEGURIDAD_CSP_EXTRA_CONNECT'),
        'extra_frame' => $lista('SEGURIDAD_CSP_EXTRA_FRAME'),
    ],

    /*
    | HSTS: solo se manda si el request llegó por HTTPS. Sin `includeSubDomains` a propósito: el dominio puede tener subdominios
    | (correo, panel del hosting) que todavía no sirven HTTPS. Para sumarlo: SEGURIDAD_HSTS_SUBDOMINIOS=true.
    */
    'hsts' => [
        'segundos' => (int) env('SEGURIDAD_HSTS_SEGUNDOS', 31536000),
        'subdominios' => (bool) env('SEGURIDAD_HSTS_SUBDOMINIOS', false),
    ],

    /*
    | Minutos de inactividad tras los cuales se cierra la sesión de un admin o un vendedor (0 = sin límite adicional al de
    | SESSION_LIFETIME). El contador se renueva con cada request al panel.
    */
    'admin_inactividad_minutos' => (int) env('SEGURIDAD_ADMIN_INACTIVIDAD_MINUTOS', 60),

    /*
    | Largo mínimo de la contraseña de admin y vendedores (las de clientes usan Password::defaults()).
    */
    'password_staff_minimo' => (int) env('SEGURIDAD_PASSWORD_STAFF_MINIMO', 10),

    /*
    | Límites por minuto y por IP de las rutas de autenticación y del reporte de la CSP (los del checkout y los cupones están en
    | config/tienda.php).
    */
    'limites' => [
        'login_por_minuto' => (int) env('THROTTLE_LOGIN_POR_MINUTO', 20),
        'registro_por_minuto' => (int) env('THROTTLE_REGISTRO_POR_MINUTO', 5),
        'recuperar_password_por_minuto' => (int) env('THROTTLE_RECUPERAR_PASSWORD_POR_MINUTO', 5),
        'csp_report_por_minuto' => (int) env('THROTTLE_CSP_REPORT_POR_MINUTO', 30),
    ],
];
