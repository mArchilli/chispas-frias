# 10.2 — El stock viene del Central

Con `INTEGRACION_STOCK=true` (y `RUKOZ_INTEGRACION=true`), el stock que muestra y valida la tienda es el **«Disponible web» del Central**. La tienda sigue leyendo `products.stock` y `product_variants.stock`; esas columnas las llena el Central (`App\Rukoz\EspejoStock`) y el admin deja de editarlas. Con el interruptor apagado todo funciona como antes.

> **En producción no se enciende `INTEGRACION_STOCK` sin `INTEGRACION_CHECKOUT`** (10.3). Hasta la 10.3 el checkout sigue descontando stock **local** (`StockService`) y el próximo evento del Central pisa ese descuento.

## La convención de «ilimitado» (`App\Support\Stock`)

| Columna | «Ilimitado» |
|---|---|
| `products.stock` (NOT NULL, default 9999) | **9999** (`Stock::ILIMITADO_PRODUCTO`) |
| `product_variants.stock` (nullable) | **NULL** (`Stock::ILIMITADO_VARIANTE`) |

`Stock::esIlimitado(?int)` (NULL o ≥ 9999) y `Stock::ilimitadoDe('producto'|'variante')` centralizan la convención; el front tiene su espejo en `resources/js/utils/stock.js` (`esStockIlimitado`).

**Es una convención de valores, no de comportamiento.** Antes de la 10.2 el código **no** trataba el 9999 como «ilimitado» en ningún lado: es un número finito que el carrito compara y que el checkout **descuenta** (9999 → 9998). Lo único «ilimitado» de verdad es el `NULL` de una variante (el carrito, `validarDisponibilidad` y `StockService` lo saltean). La 10.2 no cambió eso: solo sacó el literal 9999 de los controladores del admin y del modelo (`Product::$attributes`). Por eso, hasta la 10.3, un producto «ilimitado» vendido por el checkout local queda en 9998 hasta el próximo evento del Central (o `chispasfrias:stock-cargar`).

## Cómo se espeja (`App\Rukoz\EspejoStock`)

Escucha `StockActualizado` del paquete (lo disparan el webhook, el feed y `rukoz:sync-stock`). Se registra en `AppServiceProvider` **solo con `INTEGRACION_STOCK`**.

| Lo que informa el Central | Producto | Variante |
|---|---|---|
| un número | ese número (mínimo 0) | ese número (mínimo 0) |
| `a_pedido: true`, o `disponible: null` | **9999** | **NULL** |
| `vinculado: false` (sin vincular o `no_aplica`) | no se toca | no se toca |
| `combo` | se ignora | — |

- **Orden e idempotencia:** el paquete guarda cada ítem en `rukoz_stock` con la `version` (contador por integración que solo crece) y descarta lo que llega con versión menor; como red extra, el listener compara la versión del evento con la de `rukoz_stock` y salta el ítem si el caché ya tiene una mayor. Repetir un evento no cambia nada.
- Se escribe con `DB::table` y solo si el valor cambia: **no mueve `updated_at`** (ordena la home). No hay caché de catálogo ni de stock en la web (solo `Setting::get`), así que no hay nada que invalidar.
- **Un artículo vinculado a varios ítems** (RKZ-0009 → Cartucho PULY Dorado y Negro): el Central informa el mismo disponible para las dos variantes y las dos se actualizan.
- **Combos:** no tienen columna de stock. La web calcula su disponibilidad **a partir de los componentes** (`Combo::estaDisponible()`, y `comboFaltaStock()` en el carrito): el `combo` que informa el Central se ignora. No hace falta nada extra.
- Un producto con variantes no tiene número propio (queda `no_aplica`): lo informan sus variantes.

## Comandos y tareas programadas

- `php artisan chispasfrias:stock-cargar [--local]` — `GET /stock` → `rukoz_stock` (`Rukoz::sincronizarStock()`) y vuelca **todo** el caché a las columnas (el evento del paquete solo trae lo que cambió en el caché, no alcanza si una columna quedó atrás). Idempotente. Resumen: actualizados, sin cambios, ilimitados, sin vincular. `--local` no llama al Central.
- `php artisan chispasfrias:stock-sin-espejo` — lista productos y variantes **activos** vinculados sin fila en `rukoz_stock` o con la columna distinta del espejo. **Sale con código 1 si encuentra alguno.** Los ítems sin vincular se informan aparte (no son error). Un producto con variantes se revisa por sus variantes activas. Los combos no aplican.
- Programado (`routes/console.php`, solo con `INTEGRACION_STOCK`): `chispasfrias:stock-cargar --local` **una vez por día (03:45)**, como red de seguridad.

## Recepción de eventos: webhook y feed

| | |
|---|---|
| Webhook (vía principal) | `POST /rukoz/webhook` (`RUKOZ_WEBHOOK_PATH`). Lo registra el paquete con `RUKOZ_INTEGRACION=true`, **fuera del grupo `web`**: sin sesión ni CSRF; la autenticación es la firma (`X-Rukoz-Signature`, `RUKOZ_SECRET`, ±300 s de reloj). Se carga en la integración del Central como `webhook_url` (https, sin redirecciones, puerto 443 en producción). |
| Feed (respaldo) | `rukoz:poll` cada 5 min (cada minuto con `RUKOZ_WEBHOOK_RECIBE=false`), con solapamiento y descarte por `event_id`. |
| Reintentos | `rukoz:procesar-inbox`, cada minuto. |
| Reconciliación | `rukoz:sync-stock`, cada hora (`GET /stock` → `rukoz_stock`; vuelca al espejo lo que cambió). |

Requieren **el cron `* * * * * php artisan schedule:run`** en el hosting (en local, `php artisan schedule:work`). No hace falta cola ni worker.

**Importante:** el Central **solo emite `stock.actualizado` si la integración tiene `webhook_url`**. El feed es respaldo de un webhook que falla (URL cargada pero caída), no reemplazo de la URL: sin URL, el stock llega solo por `rukoz:sync-stock` (cada hora) o `chispasfrias:stock-cargar`. `rukoz:sync-stock` vuelca solo lo que cambió en `rukoz_stock`; si una columna quedó atrás (p. ej. por el descuento del checkout local), la corrige `stock-cargar`, no la tarea horaria.

## Admin

Con el espejo activo, el campo de stock de un producto o de una variante pasa a **solo lectura** («El stock se administra en el Sistema Central») **si el Central lo vincula** (fila vinculada en `rukoz_stock`). **El backend también lo ignora** si llega en el request (`Admin\ProductController::update` y `syncVariants`), aunque el resto del producto se guarda. Productos con variantes (`no_aplica`), ítems sin vincular o que el Central todavía no vio siguen editables. Con el interruptor apagado, todo igual que antes.

## Tienda con stock real

Hasta la 10.2 todo era ilimitado, así que esto casi no se había probado. Se revisó y se arregló:

- **`Product::inStock()` / `isInStock()` miran las variantes:** un producto con variantes **activas** está disponible si alguna lo está (stock NULL = ilimitado, o > 0); su `products.stock` propio no cuenta (con el Central el padre queda `no_aplica`, en 9999, aunque todos sus colores estén en 0). Sin variantes activas manda su propio stock. Hay un `outOfStock()` que es la negación exacta, usado por el filtro «Sin stock» y el KPI del dashboard del admin.
- **Listado `/productos`:** ya no oculta los agotados (el test `PublicCatalogStockVisibilityTest` documentaba lo contrario y se cambió): los muestra **al final**, con «Sin stock» y el botón deshabilitado. La home y los relacionados siguen sin ofrecer agotados.
- **Combos:** la ficha y el listado traen `sin_stock` (un componente sin stock deja al combo sin stock) y no se puede agregar. Además, la ficha del combo preselecciona el primer color **con stock** (antes elegía el primero aunque estuviera agotado).
- **Carrito:** no se pasa de lo disponible (variante o producto) al agregar ni al subir la cantidad. Los mensajes nombran producto y color: «No queda stock de X (Color).» / «Solo quedan N unidades de X (Color).».
- **Checkout:** rechaza con 422 nombrando producto y color («No queda stock de Pistola (Rosa). Ajustá tu carrito para continuar.»); dos colores del mismo producto sin stock se informan los dos. Se **suman** las unidades repetidas antes de comparar (el mismo color suelto y dentro de un combo). El faltante detectado bajo lock (carrera) ya no pierde la variante (`StockInsuficienteException::$variantId`). Un combo con un componente sin stock se rechaza igual.
- `stock_insuficiente[]` en la respuesta suma `product_variant_id` y `variant_name`.

## Prueba local

1. Backup de la base y del stock actual (JSON de productos y variantes).
2. `.env`: `INTEGRACION_STOCK=true`; `php artisan config:clear`.
3. `php artisan chispasfrias:stock-cargar` y `php artisan chispasfrias:stock-sin-espejo` (debe dar 0).
4. En el Central, integración → URL `http://127.0.0.1:8001/rukoz/webhook`. Un ajuste de stock llega en ~30 s (el Central publica el stock una vez por minuto).
5. `php artisan schedule:work` en la web para el feed.
