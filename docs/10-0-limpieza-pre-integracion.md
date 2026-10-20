# Fase 10.0 — Limpieza previa a la integración con el Sistema Central Rukoz

Rama `limpieza-pre-integracion` (desde `main` = `363588b`). Tag de respaldo: `produccion-pre-integracion` (sobre `363588b`,
confirmado por Matias como el commit que está en producción).

Este paso **no integra nada con el Central**. Son arreglos que la web necesita igual y que se pueden subir sin cambiar
cómo funciona la tienda, salvo donde se indica (métricas, zona horaria del panel, rol por defecto, límite de requests).

## Qué cambió, por tarea

| # | Tarea | Resultado |
|---|---|---|
| 1 | `env()` fuera de config | Nuevo `config/tienda.php` (`imagenes.ruta` ← `PRODUCT_IMAGES_PATH`, `imagenes.url` ← `VITE_PRODUCT_IMAGES_PATH`). `ProductImage`, `ComboImage`, `Admin\ProductController` y `Admin\ComboController` usan `config()`. Test que corre `config:cache` real en un subproceso (caché en archivo aparte, sin `.env`) y verifica que los valores llegan; otro test falla si aparece `env(` en `app/`, `routes/`, `bootstrap/`, `database/` o `resources/views`. |
| 2 | WhatsApp hardcodeado | `WHATSAPP_NUMERO` → `config('tienda.whatsapp')` (default: el número actual), compartido por Inertia (`props.tienda.whatsapp`). Helper `resources/js/utils/whatsapp.js`. Se sacó el número de 8 archivos del front (Checkout, Footer, WhatsAppButton, Contact, Error404, Products/Show, Welcome ×3). Un test falla si el número vuelve a aparecer escrito en `app/`, `routes/` o `resources/`. |
| 3 | Rol por defecto | Migración `…_change_users_role_default_to_cliente` (solo cambia el default) + `User::$attributes`. `UserFactory` es `cliente` por defecto, con estados `admin()` y `vendedor()` (los tests lo piden explícito). `DatabaseSeeder` no corre en producción y llama a `UserSeeder`, que lee `SEED_ADMIN_EMAIL` / `SEED_ADMIN_PASSWORD` de `config/seeders.php` (sin contraseña ⇒ genera una aleatoria y la muestra una vez; no pisa un usuario existente). Registro público y alta de vendedor ya asignaban el rol explícito. No hay login social. |
| 4 | Zona horaria | `app.timezone` sigue en `UTC`, sin convertir datos. Nuevo `config('app.display_timezone')` (`APP_DISPLAY_TIMEZONE`, default `America/Argentina/Buenos_Aires`) y `App\Support\ZonaHoraria` (back) / `resources/js/utils/fecha.js` (front). Los `created_at`/`updated_at` que muestra el panel salen en hora argentina; el filtro por mes de órdenes, el gráfico diario y el KPI del dashboard usan días y meses argentinos (corte a las 03:00 UTC). Se corrigió además que `?month=YYYY-MM` desbordaba al mes siguiente si se pedía un día 29–31. El mensaje de WhatsApp, los mails y las exportaciones no llevan fechas hoy. |
| 5 | Métricas | **Ventas = pedidos `despachado`** (`EstadoOrden::deVenta()`): es lo más parecido a una venta cerrada que existe hoy (solo hay 3 estados y no hay pago ni confirmación). **Pendientes** = monto y cantidad aparte (`EstadoOrden::pendientes()`). **Cancelados** se cuentan pero nunca suman monto. Ingresos, ticket promedio y «producto más vendido» salen solo de las ventas; «destinos» cuenta lo no cancelado. Un solo servicio (`ResumenDePedidos`) para el panel de órdenes y el dashboard. UI actualizada (Ventas / Pendientes / Ticket promedio (ventas) / Cancelados). |
| 6 | Cupones | Migración `…_add_discount_usage_timestamps_to_orders_table`: `discount_usage_consumido_at` y `discount_usage_liberado_at` (nullable, sin tocar filas). `DiscountCodeService::consumirUso(Order)` y `liberarUso(Order)` son idempotentes (marca de tiempo leída con el pedido lockeado). El checkout consume con `consumirUso` y la cancelación libera con `liberarUso`. Los pedidos viejos conservan `discount_usage_repuesto`, que sigue contando como «ya liberado». |
| 7 | Límite de requests | Limitadores `checkout` (POST `/carrito/whatsapp`, 10/min por IP) y `validar-cupon` (POST `/carrito/descuento`, 30/min por IP), configurables (`THROTTLE_CHECKOUT_POR_MINUTO`, `THROTTLE_VALIDAR_CUPON_POR_MINUTO`). Al superarlos: 429 con `{success:false, message}` y `Retry-After` (el front ya muestra `message` en un toast), o redirect con aviso en una navegación normal. |
| 8 | Checkout | `App\Services\CalculadoraPedido` (+ DTO `CalculoPedido`): ítems con precios, escalas, ofertas, color con recargo, add-ons, combos, cupón validado **sin consumirlo**, plan de tarjeta, envío gratis por combo y unidades de stock. No escribe en la base ni en la sesión (test lo verifica contando las sentencias que no son `SELECT`). Dos etapas (`calcularItems` → `aplicarDescuentoYPlan`) porque el checkout intercala la validación del cliente y la del stock. El controller conserva el orden exacto de respuestas de error. |
| 9 | Cron | `routes/console.php` importa `Schedule` y documenta cómo registrar tareas; no hay ninguna registrada (`schedule:list` → «No scheduled tasks»). |
| 10 | Dependencias | Ver más abajo. |

## Tests de caracterización del checkout

`tests/Feature/CheckoutCaracterizacionTest.php` (24 tests) se escribió **antes** del refactor contra el `CartController`
original y dio exactamente lo mismo después. Fija con valores literales calculados a mano:

- producto simple (pedido, ítem, stock, movimiento de stock y el **texto completo** del mensaje de WhatsApp);
- varias líneas de productos distintos (subtotal con decimales, stock por producto);
- escala de precio + oferta porcentual + color con recargo + add-on con texto (precios unitario/base, snapshot del add-on, stock de la variante y no el del producto, bloque del mensaje);
- oferta de monto fijo; color «a elección del cliente» con color libre y variante de stock ilimitado (no baja stock ni registra movimiento);
- combo sin colores a elegir (precio fijo, snapshot de componentes, stock = cantidad del componente × combos);
- combo con elección de color y envío gratis (el recargo del color no se suma, stock de la variante elegida);
- combo + producto suelto + cupón + plan de 3 cuotas (descuento, recargo, total con recargo, valor de cuota, consumo del cupón, texto del bloque de Mercado Pago, sesión limpia);
- envío gratis por umbral (solo informativo en el mensaje) y plan de un pago;
- rechazos: carrito vacío, color obligatorio, color libre vacío, add-on con texto obligatorio, variante desactivada, datos del cliente inválidos, stock insuficiente (producto y componente de combo, con el detalle exacto), cupón bajo el mínimo (se quita de la sesión) o agotado, plan desactivado (no aborta), producto borrado (se descarta), carrito con el formato viejo.

`tests/Feature/CalculadoraPedidoTest.php` (5) fija la pureza del servicio.

## Migraciones y variables nuevas

Migraciones (ambas aditivas / de default):
1. `2026_10_19_120000_change_users_role_default_to_cliente` — `users.role` default `'cliente'`.
2. `2026_10_19_120100_add_discount_usage_timestamps_to_orders_table` — dos columnas `timestamp` nullable en `orders`.

Variables de `.env` (todas opcionales, con default; están en `.env.example`, comentadas): `WHATSAPP_NUMERO`,
`THROTTLE_CHECKOUT_POR_MINUTO`, `THROTTLE_VALIDAR_CUPON_POR_MINUTO`, `APP_DISPLAY_TIMEZONE`, `SEED_ADMIN_EMAIL`,
`SEED_ADMIN_PASSWORD`, `SEED_ADMIN_NOMBRE` (esas tres, solo desarrollo).

## Dependencias

- `composer update` dentro de las restricciones actuales (`composer.json` intacto): `composer audit` pasó de **48 advisories en 15 paquetes** (guzzle, psr7, laravel/framework 12.43.1, league/commonmark, symfony/*, phpunit, psysh…) a **0**. Laravel 12.43.1 → 12.70.0, Inertia 2.0.16 → 2.0.28, Sanctum 4.2.1 → 4.3.3, Ziggy 2.6.0 → 2.6.5.
  - `vlucas/phpdotenv` queda en **5.6.2**: la 5.7.0 trae un archivo `tests/fixtures/env/nul.env` que no se puede extraer en Windows (`nul` es nombre reservado) y rompe `composer install` en las máquinas de desarrollo. Se fijó solo para esta corrida (`--with vlucas/phpdotenv:5.6.2`); sin advisories conocidos en 5.6.2.
- `npm audit fix` sin `--force` (`package.json` intacto, solo cambió `package-lock.json`): de **25** vulnerabilidades (2 críticas, 16 altas) a **9** (5 altas, 4 moderadas). Quedan, todas por salto mayor:
  - `tailwindcss` 3.4 → 4 (arrastra `braces`, `micromatch`, `fast-glob`, `chokidar`, `postcss-nested`, `postcss-selector-parser`): **solo compilación**, no llega a producción (el CSS ya viene generado en `public/build`).
  - `quill` 2.0.3 / `react-quill`: **corre en el navegador del admin** (editor de descripciones). Hay XSS en la función de exportar HTML de Quill, que esta web no usa; el «arreglo» que propone npm es bajar a `quill@2.0.2` (que es un cambio de mayor/regresión), así que no se tocó.
- `npm run build` y `php artisan test` en verde con las versiones nuevas.

## Para subir a producción (Hostinger, por SSH)

1. Backup de la base de producción y del `.env` actual.
2. Mergear la rama a `main` (decisión de Matias) y en el servidor: `git pull`.
3. `composer2 install --no-dev --optimize-autoloader` (el `composer.lock` cambió; confirmar que el PHP del hosting sea ≥ 8.2).
4. `php artisan migrate --force` (2 migraciones, ~instantáneas).
5. `.env` de producción: no hace falta agregar nada (todo tiene default). Si se quiere, `WHATSAPP_NUMERO`, `THROTTLE_*`, `APP_DISPLAY_TIMEZONE`. **Revisar** que `PRODUCT_IMAGES_PATH` y `VITE_PRODUCT_IMAGES_PATH` estén bien: ahora sí se respetan con `config:cache`.
6. En local: `npm run build`; subir `public/build` a `laravel/public` y a `public_html` (como indica la guía de deploy).
7. `php artisan optimize:clear` y, si se desea, `php artisan config:cache && php artisan route:cache`.
8. Verificar: portada y botones de WhatsApp (número correcto), una compra de prueba completa (y el link de WhatsApp), aplicar un cupón, panel → Órdenes (Ventas / Pendientes, fechas en hora argentina), dashboard, login de admin y de vendedor, que las imágenes de productos carguen y que `request()->ip()` sea la IP real del visitante (límite de requests por IP).

Rollback: `git checkout produccion-pre-integracion` + `composer2 install` + `php artisan migrate:rollback --step=2` (los `down()` restauran el default `admin` y quitan las dos columnas).
