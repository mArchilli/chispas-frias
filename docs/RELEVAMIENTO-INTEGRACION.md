# Relevamiento para la integración con el "Sistema Central Rukoz"

Proyecto: **Chispas Frías** (`C:\Users\MatiasPC\Desktop\chispas-frias`), rama `main`, commit `f84da4b` (2026-09-29).
Alcance: solo lectura del código. No se corrió ningún comando que escriba (ni migraciones, ni tests, ni seeds) y no se consultó ninguna base. Lo que no pude verificar está marcado como **(no verificado)**.

> Documentos previos del repo que coinciden con lo que verifiqué en el código: `CONTEXTO-PROYECTO-chispasfrias.md` (actualizado, sin trackear en git) y `contextoproyecto.md` (más viejo, anterior a combos).

---

## 0. Resumen ejecutivo (lo que más condiciona el diseño de la API)

1. **No hay API.** No existe `routes/api.php` y `bootstrap/app.php` solo registra `web` y `console`. Sanctum está en `composer.json` pero **no está operativo** (sin `config/sanctum.php`, sin migración `personal_access_tokens`, `User` sin `HasApiTokens`).
2. **Todo pedido se cierra por WhatsApp.** No hay pago online ni Mercado Pago por API. El pedido nace en `POST /carrito/whatsapp` (`CartController::generateWhatsAppMessage`) y el stock **local** se descuenta ahí mismo, en una transacción.
3. **Un solo punto descuenta/repone stock:** `App\Services\StockService` (`descontar` al crear el pedido, `reponer` solo en `pendiente → cancelado`). Lo que se salta el servicio son las **ediciones manuales** de stock desde el ABM de productos/variantes (sin registro en `stock_movements`).
4. **Estados de pedido: solo 3** (`pendiente`, `despachado`, `cancelado`) y `despachado → pendiente` es reversible. No hay estados de venta confirmada, empaquetado ni entregado.
5. **No se guarda** vendedor que gestiona, pago, cuenta, costo de envío, transportista ni tracking. `orders.user_id` es el comprador *si estaba logueado*, **no** el vendedor.
6. **No hay mails, jobs, eventos, observers, scheduler ni webhooks.** Mail en `log`; cola `database` sin ningún job despachado.
7. `order_items` **no guarda SKU**; `products.sku` es único pero nullable, `product_variants.sku` es nullable y sin índice único, y los combos no tienen SKU. Hay que resolver el mapeo de identificadores con el central.

---

## 1. Stack y versiones

| Componente | Versión | Fuente |
|---|---|---|
| PHP | `^8.2` (local 8.2.12 ZTS, Windows); CI prueba 8.2/8.3/8.4 | `composer.json`, `php -v`, `.github/workflows/tests.yml` |
| Laravel | `laravel/framework ^12.0` → **v12.43.1** instalada | `composer.lock` |
| Inertia (server) | `inertiajs/inertia-laravel ^2.0` → v2.0.16 | `composer.lock` |
| Inertia (client) | `@inertiajs/react ^2.0.0` → 2.3.4 | `node_modules` |
| React | `^18.2.0` → 18.3.1 | `node_modules` |
| Vite | `^7.0.7` → 7.3.0 (+ `laravel-vite-plugin ^2`, `@vitejs/plugin-react`) | `node_modules` |
| Tailwind | `^3.2.1` → 3.4.19 (+ `@tailwindcss/forms`, `@tailwindcss/vite ^4` declarado) | `package.json` |
| Ziggy | `tightenco/ziggy ^2.0` → v2.6.0 (`@routes` en `app.blade.php`) | `composer.lock` |
| Sanctum | `laravel/sanctum ^4.0` → v4.2.1 (**instalado, sin usar**) | ver §11 |
| Breeze | `laravel/breeze ^2.3` (dev) → v2.3.8 (auth Inertia/React) | `composer.lock` |
| Tests | `phpunit/phpunit ^11.5.3` → 11.5.46 | `composer.lock` |
| Otros front | `framer-motion`, `quill`/`react-quill`, `react-hot-toast`, `axios`, `@headlessui/react` | `package.json` |

No hay Livewire, Horizon, Telescope, Pulse, Socialite, Mercado Pago SDK, Guzzle propio ni ningún SDK de transportista.

**Infra por defecto (`.env.example`):**

| Aspecto | Valor |
|---|---|
| Cola | `QUEUE_CONNECTION=database` (tabla `jobs` existe por el esqueleto) — **ningún código despacha jobs** |
| Cache | `CACHE_STORE=database` (la usa `Setting::get` con `Cache::rememberForever`) |
| Sesión | `SESSION_DRIVER=database` (el carrito vive en `session('cart')`) |
| Mail | `MAIL_MAILER=log` (no entrega nada; `SellerController` lo menciona explícitamente) |
| Filesystem | `FILESYSTEM_DISK=local`; las imágenes **no** usan `storage:link`, se escriben directo en `public/` |
| Scheduler | `routes/console.php` solo trae `inspire`. No hay `Schedule::` en ningún lado |
| Zona horaria | `config/app.php` → `'timezone' => 'UTC'` (las fechas del panel se muestran en UTC, no en hora argentina) |

**Pistas de hosting:**
- No hay scripts de deploy ni CI de deploy (los workflows son `tests.yml`, `issues.yml`, `pull-requests.yml`, `update-changelog.yml`).
- `.env.example` apunta las imágenes a `PRODUCT_IMAGES_PATH="/../../public_html/images/products/"` → **hosting compartido tipo cPanel donde `public_html` es el webroot** y el proyecto vive dos niveles más arriba. Deduzco que producción es hosting compartido (**inferencia, no verificado**). Implicancias: probablemente sin worker de colas permanente ni `supervisor`; solo cron.
- `.env` local tiene `APP_URL=http://chispasfrias.com` con `APP_ENV=local` (el `.env.example` dice `archillimatias.dev`).
- Repo: `https://github.com/mArchilli/chispas-frias.git`, 147 commits, rama `main`. El workflow `tests.yml` dispara en push a `master`/`*.x` y en PR, así que un push a `main` no corre CI (solo PRs y el cron nocturno).

---

## 2. Entorno local

**Cómo se levanta** (inferido de `composer.json` y de lo que mencionás: XAMPP + `php artisan serve`):

```
# MySQL de XAMPP encendido; base creada a mano y .env apuntando a ella
composer install
php artisan key:generate
php artisan migrate        # (las migraciones documentadas como PENDIENTES en dev: documents, card_payment_plans, combos, etc. — revisar con migrate:status)
npm install
php artisan serve          # http://127.0.0.1:8000
npm run dev                # Vite (laravel-vite-plugin, entrada resources/js/app.jsx); deja public/hot
```

- `composer dev` corre en paralelo `serve`, `queue:listen --tries=1`, **`pail`** y `vite`. `pail` necesita la extensión `pcntl`, que **no existe en PHP para Windows**, así que ese script probablemente falle en tu máquina; usá los comandos sueltos.
- Vite: sí, con `@vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])` en `resources/views/app.blade.php`. Producción necesita `npm run build` (`public/build` está en `.gitignore`).
- Extensiones PHP locales presentes: `pdo_mysql`, `mysqlnd`, `mysqli`, `pdo_sqlite`, `gd`, `fileinfo`.

**Variables del `.env` que importan** (solo nombres/valores no sensibles; no copié secretos):

| Variable | Valor local / nota |
|---|---|
| `APP_ENV`, `APP_DEBUG`, `APP_URL`, `APP_KEY` | `local`, debug activo, `http://chispasfrias.com` |
| `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | XAMPP |
| `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database` | requieren las tablas `sessions`, `cache`, `jobs` (vienen de las migraciones `0001_01_01_*`) |
| `MAIL_MAILER=log` | los mails irían a `storage/logs` |
| `PRODUCT_IMAGES_PATH="/images/products/"`, `VITE_PRODUCT_IMAGES_PATH` | **Ojo:** el `.env.example` trae `/../../public_html/images/products/`; si alguien copia el example tal cual en local, las subidas se escriben **fuera del repo**. El `.env` local usa `/images/products/` |
| `DOCUMENTS_PDF_PATH` | solo en `.env.example` (`docs/pdfs/`), default en `config/documents.php` |

**Servicios externos que desactivar / poner en modo prueba para trabajar en local:** **ninguno hoy.** No hay pasarela de pago, ni mailer real, ni analytics, ni transportista, ni push. Lo único "externo" es un enlace `wa.me` / `web.whatsapp.com` generado en el cliente (`resources/js/Pages/Cart/Checkout.jsx`, con el **número hardcodeado** `5491178886833`) y la fuente de Google/Bunny Fonts (`fonts.bunny.net`). Para la integración habrá que agregar variables nuevas (`CENTRAL_BASE_URL`, `CENTRAL_API_KEY`/secret del webhook, `CENTRAL_ENABLED`) y un **fake/mock del cliente HTTP del central** para dev y tests.

**Un detalle de entorno que muerde:** el código usa `env()` directo fuera de `config/` en `Admin/ProductController`, `Admin/ComboController`, `ProductImage`, `ComboImage` (`env('PRODUCT_IMAGES_PATH', ...)`, `env('VITE_PRODUCT_IMAGES_PATH', ...)`). Con `php artisan config:cache` en producción esos `env()` devuelven `null` y se usan los defaults (`/images/products/`), rompiendo el path de `public_html`. Si hoy producción no cachea la config, funciona; si se la empieza a cachear al integrar (por ejemplo para agregar `config/central.php`), hay que migrar esos usos a `config()`.

---

## 3. Catálogo

### 3.1 Tablas y campos

**`products`** (`2024_12_22_000002_create_products_table.php`, modelo `App\Models\Product`)

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint | PK |
| `title`, `description` | string, text | |
| `price` | decimal(10,2) | precio de lista |
| `sku` | string, **unique**, nullable | validación `unique:products,sku` en el ABM |
| `category_id` | FK `categories`, `restrict` | |
| `stock` | **integer NOT NULL, default 9999** | no admite null (ver §4.3) |
| `is_active`, `is_featured` | bool | |
| timestamps | | |

**`product_variants`** (color; `ProductVariant`)

| Campo | Notas |
|---|---|
| `id`, `product_id` (FK cascade) | |
| `name` (≤100), `color_hex` (7, nullable) | `unique(product_id, name)` |
| `is_custom_color` | variante "a elección del cliente" (máx. una por producto, validado en el ABM); el cliente escribe el color libre |
| `price_addon` decimal | recargo fijo por unidad |
| `stock` integer **nullable** | `null` = ilimitado |
| `sku` string nullable | **sin índice único** |
| `sort_order`, `is_active` | |

Un producto con variantes activas obliga a elegir variante en carrito y checkout. Cuando la línea tiene variante, **el stock que se descuenta es el de la variante, no el del producto** (el `products.stock` queda intacto).

**Add-ons** (personalización, no llevan stock): `addons` (`name`, `description`, `price`, `requires_text`, `text_placeholder`, `max_characters` default 40, `is_active`) y pivote `product_addon` (`product_id`, `addon_id`, `price_override` nullable, `sort_order`, `unique(product_id, addon_id)`).

**Precios por cantidad:** `product_price_tiers` (`product_id`, `cantidad_minima`, `precio_unitario`, `unique(product_id, cantidad_minima)`). Se aplica el tier de mayor `cantidad_minima` ≤ cantidad (`Product::tierAplicable`). El ABM exige `cantidad_minima >= 2`.

**Ofertas:** `product_offers` (`product_id`, `offer_price` nullable, `percentage_discount`, `tipo_descuento` (`porcentaje|fijo`), `valor_descuento`, `alcance` (`todos|especifico`), `product_price_tier_id` nullable, `start_date`, `end_date`, `is_active`). Scope `active()` mira `is_active` + fechas. La oferta descuenta **solo sobre precio de lista/tier**, nunca sobre recargos de variante/add-ons.

**Cálculo (`PricingService::calcularPrecio`)**: `precioFinalConOpciones = precioUnitarioFinal(tier + oferta) + price_addon(variante) + Σ add-ons`.

**Otros:** `categories` (`name`, `slug`, `parent_id`, `sort_order`, `is_active`; 2 niveles), `product_images` (`product_id`, `product_variant_id` nullable, `path`, `type` image/video, `is_primary`, `sort_order`), `discount_codes` (`code` único, `percentage`, `min_purchase_amount`, `usage_limit`, `usage_count`, fechas, `is_active`), `card_payment_plans` (`name`, `installments`, `surcharge_percentage`, `is_active`), `settings` (clave/valor; hoy solo `free_shipping_threshold`), `documents` (manuales internos). Existe además `cart_items` + modelo `CartItem` heredados: **no se usan** (el carrito es de sesión).

### 3.2 Combos / kits

- **`combos`**: `id`, `title`, `description`, `price` (decimal, **precio fijo**), `is_free_shipping`, `is_active`, `sort_order`. **No tiene SKU ni stock propio.**
- **`combo_items`**: `combo_id` (cascade), `product_id` (cascade), `quantity` (default 1), `sort_order`, `unique(combo_id, product_id)`. → un combo = lista de **productos con cantidad**; cada producto aparece una sola vez.
- **`combo_images`**: galería (igual que productos).
- El combo no tiene tiers ni ofertas ni recargos de variante. Si un componente tiene variantes activas, el cliente elige el color de cada uno (sin recargo).
- **Stock de un combo = el de sus componentes.** En el pedido se guarda **una sola fila** en `order_items` con `product_id = null`, `combo_id`, y un snapshot JSON `combo_selections`:

```json
[{"product_id":7,"product_variant_id":12,"quantity":2,"product_title":"...","variant_name":"Rojo",
  "variant_color_hex":"#ff0000","custom_color_text":null}]
```

`StockService::unidadesStock()` expande ese JSON en `cantidad_componente × cantidad_combo` por producto/variante. Los productos del combo conservan su propio `sku`; **el combo en sí no se puede identificar por SKU hacia el central**: hay que mapearlo por componentes.

### 3.3 Snapshot en el pedido (`order_items`)

`product_id` (set null al borrar), `combo_id` (set null), `product_variant_id` (set null), `product_title`, `variant_name`, `variant_color_hex`, `variant_price_addon`, `custom_color_text`, `addons_selected` (JSON: `addon_id`, `name`, `price`, `custom_text`), `addons_total`, `combo_selections` (JSON), `cantidad`, `precio_unitario` (final con recargos), `base_unit_price` (sin recargos), `subtotal`. **No guarda `sku` de producto ni de variante.**

---

## 4. Stock

### 4.1 `StockService` completo (`app/Services/StockService.php`, 281 líneas)

Métodos públicos:

```php
validarDisponibilidad(array $items): array   // optimista, sin lock. Devuelve faltantes ([] = ok)
descontar(Order $order): void                // all-or-nothing, dentro de DB::transaction + lockForUpdate
reponer(Order $order): void                  // idempotente (ver abajo)
```

Privados: `unidadesStock(Order)` (normaliza líneas; expande combos; ignora líneas con `product_id` null), `lockearStock()` (primero todos los productos por id ascendente, después variantes por id ascendente → evita deadlocks) y `sujetoStock()` (variante si la hay, si no producto).

- **`descontar`**: por cada unidad, lockea, **si `stock === null` (variante ilimitada) no hace nada y no registra**; si no alcanza lanza `StockInsuficienteException(productId, cantidad, stockDisponible)` (aborta toda la transacción); si alcanza resta, guarda y crea `StockMovement` con `cantidad = -N`, `motivo = orden_creada`, `stock_resultante`.
- **`reponer`**: dentro de la transacción, tras el lock, mira si ya existe un `StockMovement` con `motivo = orden_cancelada` para esa orden y, si existe, no hace nada (idempotencia). Si no, suma y registra `+N`, `orden_cancelada`. Variante `null` se saltea igual.
- **Nota:** `reponer` repone por el contenido **actual** de `order_items`. Si un producto/variante se borró (FK `set null`), esa línea se ignora (el stock de algo que ya no existe).

`stock_movements`: `product_id` (cascade), `product_variant_id` (set null), `order_id` (set null), `cantidad` (±), `motivo`, `stock_resultante`. Enum `MotivoMovimientoStock`: `orden_creada`, `orden_cancelada`, **`ajuste_manual` (definido pero no se usa en ningún lado del código; solo en `StockMovementFactory`)**.

### 4.2 Dónde se descuenta y se repone

| Evento | Archivo | Efecto en stock |
|---|---|---|
| Crear pedido (`POST /carrito/whatsapp`) | `CartController::generateWhatsAppMessage` (~L1721-1800) | `StockService::descontar($order)` dentro de la misma `DB::transaction` que crea `Order` + `OrderItem`s + uso del cupón |
| Pasar a `cancelado` desde `pendiente` | `Admin\OrderController::updateStatus` (~L203-221) | `StockService::reponer($order)` + `DiscountCodeService::reponerUso` (protegido por `orders.discount_usage_repuesto`) |
| `pendiente → despachado` | idem | **No toca stock** |
| `despachado → pendiente` | idem | **No toca stock** (no se vuelve a descontar ni a reservar) |
| `despachado → cancelado` | — | **Prohibido** por `EstadoOrden::puedeTransicionarA` ("Primero volvé la orden a pendiente") |

### 4.3 Qué significa `null`, `9999`, `0`

- `products.stock`: **NOT NULL, default 9999**. En el ABM, si se deja vacío se guarda `9999` (`Admin\ProductController::store/update`: *"Si el stock está vacío o es null, establecer 9999"*). 9999 es el "prácticamente ilimitado" del producto: se descuenta normalmente (9999 → 9998 ...) como cualquier número. **No hay un "ilimitado" real a nivel producto.**
- `product_variants.stock`: **nullable**; `null` = ilimitado y es un caso especial en todo el flujo (no valida, no descuenta, no registra movimiento).
- `0` o menos = sin stock. `isInStock()` es `stock > 0`.
- Catálogo público: `Product::inStock()` (`stock > 0`) filtra listados, destacados, ofertas y relacionados — **mira solo `products.stock`**, no las variantes. Un producto con `products.stock = 9999` y todas las variantes en 0 igual aparece en el listado (la ficha, `Products/Show.jsx`, sí calcula "Sin stock" mirando las variantes).
- Umbral de "stock bajo": `LOW_STOCK_THRESHOLD = 3` en `Admin\DashboardController` y `resources/js/utils/stock.js` (deben mantenerse sincronizados a mano).

### 4.4 ¿El checkout valida stock? Sí, en tres capas

1. **Al agregar/actualizar carrito** (`CartController::add`, `addCombo`, `update`, helper `comboFaltaStock`): chequeo optimista contra el stock de la variante (si es finita) o del producto.
   *Inconsistencia menor:* si la variante es ilimitada (`stock null`), `add()` cae al `products.stock` para decidir, mientras que `validarDisponibilidad()`/`descontar()` la tratan como ilimitada.
2. **Al confirmar el pedido**: `StockService::validarDisponibilidad()` (L~1512), antes de abrir la transacción. Si falta → `422` con `stock_insuficiente: [{product_id, product_title, cantidad_solicitada, stock_disponible}]` (shape que la UI ya sabe mostrar).
3. **Chequeo definitivo bajo lock** dentro de `descontar()` → `StockInsuficienteException` → mismo `422`.

### 4.5 Lugares que tocan stock SIN pasar por `StockService`

Búsqueda sobre `app/`, seeders y factories (`decrement`, `increment`, `->stock =`, `'stock' =>`):

| Dónde | Qué hace | Registra `StockMovement` |
|---|---|---|
| `Admin\ProductController::store` / `update` (`$product->update($validated)`, validación `'stock' => 'nullable|integer|min:0'`, L~183 y ~504) | Edición manual libre del stock del producto (vacío → 9999) | **No** |
| `Admin\ProductController::syncVariants` (L~820) vía `updateOrCreate` | Edición manual libre del stock de cada variante (vacío → `null`); además **borra** (`whereNotIn('id', ...)->delete()`) las variantes que el formulario no manda | **No** |
| `Admin\ProductController::destroy` / borrado de variantes | `stock_movements.product_id` es `cascade` → se pierde el historial al borrar el producto; en variantes `set null` | — |
| `app/Console/Commands/CreateTestData.php` | Crea 5 productos de prueba con `stock => 10` | No |
| `database/seeders/ProductsSeeder.php`, factories | Datos de prueba | No |

No hay ajuste masivo, importación, ni endpoint que modifique stock. Los demás `stock` que aparecen en controllers (`CategoryController`, `PriceController`, `ProductController` público, `ComboController`, `CartController`) **solo leen**.

---

## 5. Pedidos

### 5.1 Tablas y campos

**`orders`** (migraciones `2026_08_16_120000`, `2026_08_24_170100`, `2026_08_24_180000`, `2026_08_27_140100`, `2026_09_28_120400`)

| Grupo | Campos |
|---|---|
| Identidad | `id` (autoincremental, **sin UUID/número público**), `user_id` (nullable FK `users`, `set null`), timestamps |
| Cliente | `name`, `lastname`, `dni`, `phone`, `email` |
| Destino (envío "a sucursal", ya no a domicilio) | `province` (string, se guarda lo que manda el front; `Support\Provincias::nombre()` lo resuelve), `city`, `postal_code`. `address`, `number` (nullable desde 2026-08-24) y `between_streets` son **legacy**: el checkout ya no los pide ni envía |
| Observaciones | `observations` (≤500) |
| Estado | `estado` string default `'pendiente'` (cast a `EstadoOrden`), indexado |
| Totales | `subtotal` (productos antes del cupón), `discount_amount`, `total` (= subtotal − descuento; **no incluye envío ni recargo**) |
| Cupón | `discount_code_id` (FK set null), `discount_code` (snapshot texto), `discount_usage_repuesto` (bool) |
| Envío | `free_shipping` (bool): `true` solo si el carrito trae un combo con `is_free_shipping`. **No hay costo de envío, ni tipo de envío, ni transportista.** El envío gratis por umbral global (`settings.free_shipping_threshold`) es solo un texto en el mensaje |
| Recargo tarjeta (informativo) | `card_payment_plan_id` (FK set null), `payment_plan_name`, `payment_plan_installments`, `surcharge_percentage`, `surcharge_amount`, `total_with_surcharge` — todos null si no se eligió plan |
| Mensaje | `mensaje_whatsapp` (texto completo enviado al vendedor) |

**Medio de pago:** **no existe como campo.** Lo único relacionado es el snapshot del plan de cuotas con tarjeta (informativo, "el vendedor genera el link de Mercado Pago a mano"). Si el cliente paga por transferencia/efectivo, el pedido no lo registra.

**`order_items`**: ver §3.3.

### 5.2 Estados y transiciones (`app/Enums/EstadoOrden.php`)

```php
case Pendiente = 'pendiente'; case Despachado = 'despachado'; case Cancelado = 'cancelado';

puedeTransicionarA(): Pendiente → [Despachado, Cancelado]
                      Despachado → [Pendiente]
                      Cancelado  → []   // terminal
```

| Transición | Quién | Cómo (UI) | Efectos |
|---|---|---|---|
| (alta) → `pendiente` | **El cliente**, automáticamente, al confirmar el checkout | `POST /carrito/whatsapp` (usa el default de DB) | descuenta stock, registra uso de cupón |
| `pendiente → despachado` | **Admin o vendedor** | botón "Marcar como despachado" en `Admin/Orders/Show.jsx` | ninguno (ni stock, ni mail, ni aviso) |
| `despachado → pendiente` | Admin o vendedor | botón "Volver a pendiente" | ninguno (no vuelve a descontar) |
| `pendiente → cancelado` | Admin o vendedor | botón "Cancelar pedido" (con modal de confirmación) | **repone stock** + devuelve uso del cupón |
| `despachado → cancelado` | — | rechazado | mensaje "Primero volvé la orden a pendiente" |
| `cancelado → *` | — | rechazado | "No se puede modificar una orden cancelada" |

El cliente **nunca** cambia estados. `admin.orders.update-status` solo exige `auth`, `verified`, `can:acceder-panel-admin` (admin **y** vendedor, sin diferencias).

### 5.3 Dónde se cambia el estado

- **Un único lugar:** `Admin\OrderController::updateStatus()` (`PATCH /admin/orders/{order}/status`, `admin.orders.update-status`), L189-231. Valida con `Rule::enum(EstadoOrden::class)` + `puedeTransicionarA`, hace `$order->update(['estado' => ...])` en `DB::transaction` junto con la reposición de stock/cupón. Cualquier excepción se reporta y se devuelve un error genérico.
- No hay Observer, ni Event, ni Listener, ni Policy específica, ni `OrderService`. La lógica de transición vive en el enum + el controlador.
- `grep` de `estado` en `app/` confirma que ningún otro código escribe `orders.estado`.

### 5.4 ¿Se guarda vendedor, pago, envío, tracking?

| Dato | ¿Existe? |
|---|---|
| Vendedor que gestionó/despachó | **No.** No hay `seller_id`, `confirmed_by`, `updated_by` ni historial de estados |
| `orders.user_id` | El **comprador** si estaba logueado al comprar (`CartController` L1754 `$order->user()->associate($request->user())`); si compra un admin/vendedor/cliente logueado queda su id, el resto `null`. **Nada lo lee** y no está en `$fillable` |
| Pago (método, cuenta, monto cobrado, fecha, comprobante) | **No** |
| Envío (tipo, costo, fecha) / transportista / tracking | **No** |
| Timestamps por estado (`despachado_at`, etc.) | **No**; solo `updated_at` genérico |
| Referencia externa / ID del central | **No** |

---

## 6. Checkout (carrito → pedido)

**Carrito (sin login, en sesión).** `session('cart')` = array de líneas con `line_key` (hash SHA-256 de producto + variante + add-ons + textos + color libre; dos líneas con la misma clave suman cantidad). Dos tipos de línea: producto (`product_id, quantity, variant_id, addon_selections, custom_color_text`) y combo (`combo_id, quantity, component_selections`). `normalizeCart()` también lee el formato viejo `{product_id: cantidad}`. Estado auxiliar en sesión: `cart_discount_code` y `cart_payment_plan`.

Rutas (`routes/web.php`, prefijo `/carrito`, sin auth): `GET /`, `/resumen`, `/checkout`, `/count`; `POST /agregar`, `/agregar-combo`, `/descuento`, `/forma-pago`, `/whatsapp`; `PATCH /actualizar`; `DELETE /eliminar`, `/vaciar`, `/descuento`, `/forma-pago`.

**Flujo de `POST /carrito/whatsapp` (`CartController::generateWhatsAppMessage`, L1425-1862):**

1. Carrito no vacío.
2. `validarOpcionesObligatorias()` — color obligatorio si el producto/componente tiene variantes activas; color libre no vacío; add-ons con `requires_text` con texto. Si falla → `422` nombrando el producto.
3. `getCartItems(exigirVariante: true)` — recalcula precios server-side con `PricingService` (tiers + oferta + variante + add-ons). Si una variante/add-on se desactivó → `422`.
4. Valida `customer_data.*`: `name`, `lastname`, `dni`, `province`, `city`, `postal_code`, `phone`, `email` (email válido), `observations` opcional ≤500.
5. `StockService::validarDisponibilidad()` (los combos se expanden) → `422` con `stock_insuficiente`.
6. `subtotal`; código de descuento de la sesión (`DiscountCodeService::buscarValido` + `calcularDescuento`: % sobre el subtotal); `total = subtotal − descuento`.
7. Plan de tarjeta (`resolvePaymentPlan($total)` → `CardSurchargeService::calcular`); si el plan se desactivó, se descarta en silencio.
8. Arma el texto de WhatsApp (cliente, "Envío a Sucursal", detalle, descuento, envío gratis, TOTAL, y si hay plan el bloque "Generar link de pago en Mercado Pago").
9. **`DB::transaction`**: crea `Order` (+ `associate(user)` si hay sesión), crea los `OrderItem`, **`$stockService->descontar($order)`**, **`discountCodeService->registrarUso()`**. Captura `StockInsuficienteException`, `DiscountCodeInvalidoException` y `Throwable` (500 genérico).
10. Limpia `cart`, `cart_discount_code`, `cart_payment_plan`; responde JSON `{success, message (texto WhatsApp), subtotal, discount_amount, total, payment_plan, itemCount, order_id}`.

**Pago online:** **no.** El front (`Cart/Checkout.jsx`) abre `wa.me/<número>?text=...` (mobile) o `web.whatsapp.com/send` (desktop) con el mensaje, y el vendedor cierra la venta por chat. Si el cliente eligió tarjeta, el vendedor arma el link de Mercado Pago a mano por `total_with_surcharge`. No hay llamadas a la API de MP.

**Precios por cantidad:** `PricingService::calcularPrecio` (§3.1). El front tiene un espejo en `resources/js/utils/pricing.js` (comentado como "mirror JS de PricingService"); cualquier cambio de reglas hay que hacerlo en ambos.

**Envío:** no se calcula costo. Solo (a) `settings.free_shipping_threshold` → texto informativo en el mensaje y barra de progreso (`FreeShippingProgress.jsx`) y (b) `combos.is_free_shipping` → `orders.free_shipping = true`.

**Seguridad del endpoint:** CSRF activo (ruta `web`); el JS manda `X-CSRF-TOKEN`. No hay rate limiting específico en el checkout ni clave de idempotencia (si se repite el POST con otro carrito de sesión, se crea otro pedido).

---

## 7. Panel de administración

Layout: `resources/js/Layouts/AdminLayout.jsx`. Todas las rutas bajo `/admin`, middleware `auth, verified, can:acceder-panel-admin`. Gates en `AppServiceProvider::boot()`.

| Sección | Ruta / controlador | Admin | Vendedor |
|---|---|---|---|
| Dashboard | `admin.dashboard` / `DashboardController` | KPIs operativos + financieros + últimos pedidos | Solo KPIs operativos (categorías, productos, sin stock, stock bajo, ofertas, cupones, pedidos pendientes) |
| Categorías | `categories` | ABM completo | ABM sin borrar (`borrar-catalogo`) |
| Productos (+ variantes, escalas, add-ons, imágenes, destacado, activo) | `products` (`gestionar-productos`) | ✔ | ✘ (solo "Precios") |
| Precios | `prices.index` (solo lectura, filtros de categoría/búsqueda) | ✔ | ✔ |
| Combos | `combos` (`gestionar-productos`) | ✔ | ✘ |
| Add-ons | `addons` (`gestionar-productos`) | ✔ | ✘ |
| Ofertas | `offers` (+ `quick-offer` en producto) | ✔ | ABM sin borrar |
| Códigos de descuento | `discount-codes` | ✔ | ABM sin borrar |
| **Órdenes** | `orders.index`, `orders.show`, `orders.update-status` | ✔ + métricas | ✔ cola y acciones, **sin** métricas financieras |
| Documentos | `documents` | ABM | Solo ve los activos |
| Envío gratis (settings) | `settings` (`gestionar-configuracion`) | ✔ | ✘ |
| Planes de cuotas | `card-payment-plans` (`gestionar-configuracion`) | ✔ | ✘ |
| Vendedores | `sellers` (`gestionar-vendedores`) | ✔ | ✘ |

**Pantalla de pedidos:**
- **Listado** (`Admin/Orders/Index.jsx`, `OrderController::index`): una "cola" filtrada por **un estado a la vez** (default `pendiente`; los botones del front salen de `ESTADO_OPTIONS`, hay que actualizarlos si se agregan estados), búsqueda por nombre/apellido/DNI/email, paginado de 12, orden por fecha descendente. Cada fila: id, nombre, estado, total, cantidad de ítems, provincia/ciudad, fecha. Solo admin ve mes seleccionado + `stats` (pedidos, cancelados, ingresos, ticket promedio, producto más vendido, top provincias) + gráfico diario.
- **Detalle** (`Admin/Orders/Show.jsx`, `OrderController::show`): datos del cliente, ítems con imagen/color/add-ons/componentes de combo, subtotal, cupón, total, bloque de plan de tarjeta (`PaymentPlanCard`), `free_shipping`, `mensaje_whatsapp`, y panel lateral **"Cambiar estado"** con los botones de §5.2 (`transiciones_disponibles` calculadas en el servidor).
- **Acciones**: solo cambio de estado. No hay edición de pedido, ni notas internas, ni asignación de vendedor, ni reenvío de WhatsApp.
- **Salvedad de las métricas** (comportamiento actual): `revenue` suma `total` de todo lo no cancelado, es decir **también los pendientes** (no se distingue "vendido" de "reservado").

**Catálogo y stock:** `Admin/Products/{Index,Create,Edit,Show}.jsx`: el listado muestra stock y filtra por stock (`ProductController` L123); el stock se edita a mano en el formulario del producto y en `ProductVariantsEditor.jsx` (por variante). No hay pantalla de movimientos de stock ni de ajuste con motivo.

---

## 8. Usuarios y roles

- Tabla `users`: `id`, `name`, `email` (único), `password` (cast `hashed`), `role` (string indexado, **default `'admin'`** tanto en la migración como en `User::$attributes`), `is_active` (default true), `email_verified_at`, `remember_token`.
- Enum `RolUsuario`: `admin`, `vendedor`, `cliente`. Helpers `isAdmin()`, `isVendedor()`, `isCliente()`, `puedeAccederAlPanel()` (admin o vendedor).
- Auth: Breeze (`routes/auth.php`) con registro público habilitado → `RegisteredUserController` crea siempre `role = cliente`. `User` **no** implementa `MustVerifyEmail` (está comentado), por lo que `verified` no bloquea a nadie y no se envían mails de verificación.
- Login redirige a `admin.dashboard`; un `cliente` recibe 403 ahí (cubierto por `RolePermissionsTest`). `LoginRequest` rechaza usuarios con `is_active = false`.
- **Vendedores identificables: sí como usuarios** (`role = vendedor`), creados por el admin (`SellerController`: nombre + email, contraseña temporal generada con `Str::password(12)` que se muestra una única vez en pantalla porque no hay mail real; se puede activar/desactivar, lo que además borra sus filas de `sessions`). **Pero ningún pedido queda asociado a un vendedor** (§5.4).
- Riesgo a tener en cuenta: como el default de rol es `admin`, un `User::create()` sin `role` crea un administrador. `DatabaseSeeder` crea un admin ("Duilio") con **contraseña fija escrita en el código del seeder**; no ejecutar `db:seed` en un entorno compartido/productivo sin cambiarla.

---

## 9. Clientes

- **No hay entidad cliente.** Los datos viven en cada pedido (`name`, `lastname`, `dni`, `phone`, `email`, `province`, `city`, `postal_code`, `observations`). Mismo cliente en dos pedidos = dos copias sin vínculo (el DNI es lo único que permitiría deduplicar).
- Existen cuentas `cliente` por el registro público, pero **no tienen ninguna funcionalidad**: `/dashboard` redirige al panel admin (403 para ellos) y solo les sirve `/profile`. El pedido se asocia a ellos únicamente si estaban logueados (`user_id`), sin pantalla de "mis pedidos".
- **Qué ve el cliente después de comprar:** en `Cart/Checkout.jsx`, una pantalla de éxito ("¡Pedido listo!") con el total, el número de orden (`result.order_id`, solo en esa pantalla) y un botón "Abrir WhatsApp" para reintentar. **Nada más:** no hay página de seguimiento, ni link público al pedido, ni mail de confirmación, ni notificación de cambio de estado. La comunicación posterior es por WhatsApp, a mano.

---

## 10. Eventos, jobs, notificaciones y mails (alrededor de pedidos y stock)

- **No existen** `app/Jobs`, `app/Events`, `app/Listeners`, `app/Observers`, `app/Mail`, `app/Notifications`, ni `Schedule::` ni `Mail::`/`Notification::`/`event()`/`dispatch()` en el código de negocio.
- Únicos eventos en uso son los de Breeze: `Registered`, `Verified`, `PasswordReset`, `Lockout` (sin listeners propios, sin mail porque `User` no implementa `MustVerifyEmail`).
- `app/Models/*`: ningún `booted()`/`creating()`/`updated()`; `DatabaseSeeder` usa `WithoutModelEvents`.
- Consecuencia para el diseño: **no hay "gancho" ya armado**; los cambios de pedido/stock ocurren inline en controladores. Cualquier sincronización con el central tendrá que dispararse explícitamente desde esos puntos (o introducir eventos nuevos).
- La tabla `jobs`/`failed_jobs`/`job_batches` existe, pero **no hay evidencia de worker corriendo en producción** y hoy no haría falta. Si se usa cola para hablar con el central, hay que confirmar con el hosting si se puede dejar `queue:work` o si hay que usar `schedule:run` por cron con `queue:work --stop-when-empty`.

---

## 11. API existente, webhooks e integraciones

- **`routes/api.php`: no existe.** `bootstrap/app.php`:

```php
->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
```

  Habría que agregar `api: __DIR__.'/../routes/api.php'` (esto prefija `/api`).
- **Sanctum:** paquete instalado pero sin `config/sanctum.php` publicado, sin migración `personal_access_tokens`, `User` sin `HasApiTokens`. No sirve hoy para tokens. Para server-to-server probablemente conviene un middleware propio con secreto compartido/HMAC (no depende de usuarios ni de tablas nuevas).
- `resources/js/config/app.js` declara `API_URL: import.meta.env.VITE_API_URL || '/api'`, pero ningún código lo usa.
- **Endpoints JSON existentes** (solo del carrito, rutas `web`, con sesión + CSRF): `GET /carrito/resumen`, `/carrito/count`, y las mutaciones de `/carrito/*` que devuelven JSON cuando `expectsJson()`. No son utilizables desde un sistema externo.
- **Endpoint de salud:** `/up` (esqueleto Laravel 12).
- **Webhooks:** ninguno, entrantes ni salientes.
- **Mercado Pago:** nada en código (`CardPaymentPlanController` lo aclara en un comentario); el "link de pago" lo genera el vendedor fuera del sistema.
- **Correo:** `MAIL_MAILER=log`. **Andreani / otros transportistas:** nada. **Analytics (GA, Pixel, etc.):** nada en `app.blade.php` ni en los JS. **`config/services.php`:** solo placeholders de Postmark/Resend/SES/Slack del esqueleto.
- Ziggy expone (vía `@routes`) los nombres/URLs de **todas** las rutas, incluidas las de `/admin`, a cualquier visitante. No es un problema de seguridad por sí mismo (las rutas están protegidas), pero conviene saberlo.

---

## 12. Tests

- **Framework:** PHPUnit 11.5 (`phpunit.xml`, suites `Unit` y `Feature`). Aproximadamente **360 métodos de test** (`grep` de `public function test_` + `#[Test]`) repartidos en ~40 archivos en `tests/Feature` (incluida `Auth/` de Breeze) y 1 `ExampleTest` en `tests/Unit`.
- **Entorno de test:** `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`, `CACHE_STORE=array`, `SESSION_DRIVER=array`. Se necesita `pdo_sqlite` (presente en tu PHP). Usan `RefreshDatabase`.
- **Cómo correrlos:** `php artisan test` o `composer test` (hace `config:clear` antes). Filtrar: `php artisan test --filter=StockServiceTest`.
- **Cobertura relevante para esta integración:** `StockServiceTest`, `StockServiceVariantesTest`, `StockMovementTest`, `CartStockEnforcementTest`, `CartVariantAddonTest`, `CartWhatsAppOrderTest`, `ComboCartOrderTest`, `AdminOrderStatusUpdateTest`, `AdminOrderIndexShowTest`, `AdminOrderVendorVisibilityTest`, `AdminOrderCancelDiscountCodeTest`, `RolePermissionsTest`, `PricingService*Test`, `CartPricingIntegrationTest`, `CartPaymentPlanIntegrationTest`, `CartDiscountCodeIntegrationTest`, `PublicCatalogStockVisibilityTest`, más los de ABM del catálogo.
- **Tests de concurrencia reales contra MySQL** (`StockServiceConcurrencyTest`, `DiscountCodeConcurrencyTest` + scripts en `tests/concurrency/`): lanzan procesos PHP aparte contra una base MySQL dedicada (`chispas_frias_stock_lock_test`), que **crean la base y corren `migrate:fresh` sobre ella**; se skipean si no hay MySQL. Aviso: con XAMPP encendido y credenciales válidas **sí van a ejecutarse y escribir en MySQL** (en esa base dedicada, no en la de desarrollo).
- **Último estado conocido:** mi memoria de sesión indica suite verde (351 tests al 2026-08-27, antes de combos). **No la corrí en este relevamiento**, así que no puedo afirmar su estado actual.
- **CI:** `.github/workflows/tests.yml` (PHP 8.2/8.3/8.4, sqlite, `cp .env.example .env`, `php artisan test`) en PR y cron nocturno.
- Factories disponibles: `Addon`, `CardPaymentPlan`, `Category`, `Order`, `OrderItem`, `Product`, `ProductPriceTier`, `ProductVariant`, `StockMovement`, `User`. **No hay `ComboFactory`** aunque `Combo`/`ComboItem` usan `HasFactory`.

---

## 13. Puntos de enganche y riesgos

### (a) Reservar stock en el central al crear el pedido

**Enganche:** `CartController::generateWhatsAppMessage`. El punto natural es el mismo donde hoy se valida y descuenta:

| Paso actual | Línea aprox. | Propuesta |
|---|---|---|
| `validarDisponibilidad($stockItems)` | L1512 | Aquí se puede consultar el central (stock real) para rechazar rápido con el mismo `422 stock_insuficiente` que la UI ya renderiza |
| `DB::transaction(... descontar ...)` | L1721-1810 | Reservar acá o inmediatamente antes; ver riesgos |
| `catch (StockInsuficienteException)` | L1811 | Reutilizar para "central rechazó la reserva" (misma respuesta de UI) |

Lo que ya existe y sirve de base: `StockService::unidadesStock()` es **el único lugar que sabe expandir combos y variantes a unidades de stock** (`product_id`, `product_variant_id`, `cantidad`). Ese mismo resultado es el payload natural de la reserva. Hoy es `private`; habría que exponerlo (o extraer un `OrderStockLines`).

Recomendaciones de diseño:
- **Generar una clave de idempotencia antes de reservar** (p. ej. UUID de la orden, nuevo campo `orders.public_id`/`external_reference`) y mandarla al central. Hoy el `id` autoincremental recién existe después del `INSERT`.
- **No hacer la llamada HTTP al central dentro de la `DB::transaction` mientras están tomados los `lockForUpdate` de productos/variantes**: un timeout del central dejaría filas bloqueadas y bloquea otros checkouts. Opciones: (1) reservar en el central **antes** de la transacción local y, si la transacción local falla, **compensar** con una liberación; o (2) crear la orden local en un estado intermedio (`reserva_pendiente`) y confirmar después.
- Reserva **atómica por pedido** (todas las líneas o ninguna), igual que `descontar()`; el central tiene que soportar un lote.
- **Mapeo de identificadores:** `products.sku` (único, nullable), `product_variants.sku` (nullable, **no único**), combos sin SKU. Faltan SKUs en variantes con seguridad (la UI lo deja opcional). Antes de integrar hay que decidir si el SKU del central es obligatorio y completar/validar los datos. Conviene **snapshotear `sku` en `order_items`** (y en `combo_selections`) para no depender del catálogo vivo si se borra/edita algo.
- **Qué hacer con el stock local:** hay que decidir si `StockService::descontar()` sigue operando (como espejo/cache) o se reemplaza. Si ambos descuentan, el stock se descuenta dos veces según quién lo lea.
- **Cancelación:** `OrderController::updateStatus` (L213) llama a `reponer()`; ahí hay que **liberar la reserva en el central** (idempotente). Hoy la cancelación solo es posible desde `pendiente`, lo que encaja con "reserva sin venta confirmada".
- **Expiración de reservas:** hoy un pedido `pendiente` retiene stock indefinidamente. Si el central expira reservas, la web necesita enterarse (webhook/pull) y cancelar localmente; no hay scheduler ni worker para hacerlo.

### (b) Reemplazar "despachar" por "Confirmar venta" (pago, cuenta, envío, transportista)

**Enganche:** UI en `resources/js/Pages/Admin/Orders/Show.jsx` (botón `updateEstado('despachado')`, panel "Cambiar estado") y backend en `Admin\OrderController::updateStatus` + `EstadoOrden`.

- Hace falta una **acción nueva y distinta** (p. ej. `POST /admin/orders/{order}/confirmar-venta`) con formulario: medio de pago, cuenta/destino, monto cobrado, envío (tipo, costo), transportista, y probablemente observaciones internas. Ninguno de esos campos existe: **migración nueva** en `orders` (o tabla `order_payments`/`order_shipments`).
- **Registrar quién confirma:** agregar `confirmed_by` (FK `users`) y `confirmed_at`. **No reutilizar `orders.user_id`** (es el comprador).
- Ampliar `EstadoOrden`: algo como `pendiente` (reservado) → `venta_confirmada` → `empaquetado` → `despachado` (+ `entregado` opcional) / `cancelado`. Hoy `despachado → pendiente` es reversible: **eso contradice al central** (una venta ya creada no se "des-confirma" sin una anulación explícita). Revisar `puedeTransicionarA`, `mensajeTransicionInvalida`, `ESTADO_LABELS`/`ESTADO_BADGE_CLASSES` (`utils/orders.jsx`), `ESTADO_OPTIONS` en `Orders/Index.jsx`, los conteos del Dashboard (`pending_orders_count`) y las queries de `buildMonthStats`/`buildDailyBreakdown` (usan `!= cancelado`).
- **Reglas de transición hacia el central:** definir qué pasa si el central rechaza la creación de la venta (el pedido debe quedar `pendiente` con el error visible). La llamada es de red: no meterla dentro de una transacción larga.
- **Totales:** `orders.total` no incluye costo de envío. Al confirmar, el monto de la venta = `total` + envío (+ recargo de tarjeta si el pago fue en cuotas → `total_with_surcharge`). Definir cuál es la fuente de verdad del monto y guardar el desglose en el pedido.
- Una vez que "despachar" lo hace el central, el botón actual de la web debería desaparecer o quedar para admin como excepción documentada.

### (c) Recibir del central estados empaquetado/despachado con tracking

**Enganche (nuevo, no hay nada previo):**
1. Registrar `api` en `bootstrap/app.php` + `routes/api.php` con un endpoint como `POST /api/central/orders/{ref}/estado`.
2. **Autenticación server-to-server:** middleware propio con secreto compartido/HMAC y validación de timestamp; **no** usar Sanctum sin antes publicar su config y migrar `personal_access_tokens`. Las rutas `api` no pasan por CSRF ni sesión (bien para un webhook).
3. **Idempotencia y orden:** guardar `central_event_id` y tolerar reentregas/eventos desordenados; tabla `order_status_events` (pedido, estado, tracking, payload, fecha) para auditar. Hoy no hay historial.
4. Campos nuevos en `orders`: `central_sale_id`, `central_status`, `carrier`, `tracking_code`, `tracking_url`, `packed_at`, `dispatched_at`.
5. **Panel:** `OrderController::show` arma las props a mano → agregar los campos nuevos; `Show.jsx` para mostrar estado/tracking; `Index.jsx` para filtrar por los nuevos estados.
6. **Cliente:** hoy no ve nada. Opciones, de menor a mayor esfuerzo: (i) que el vendedor copie el tracking al WhatsApp (botón "Copiar mensaje de seguimiento" en `Show.jsx`); (ii) página pública `GET /pedido/{token}` con estado y tracking (requiere un `public_token` no adivinable en `orders`, rutas públicas y una vista Inertia nueva); (iii) mail transaccional (requiere configurar un mailer real, cola o envío síncrono, y un `Mailable`). El pedido tiene `email`, así que es viable.
7. **Hosting:** si el central no puede llamar a la web por restricciones de red/firewall, plan B es *polling* de la web hacia el central con `schedule:run` por cron (hoy no hay scheduler configurado).

### (d) Mostrar en la tienda el stock real del central

**Dónde se consume `stock` en lectura** (todo lee las columnas locales):
`ProductController` público (index, show, relacionados vía `scopeInStock`), la ruta `/` en `routes/web.php` (destacados y ofertas con `stock > 0`), `ComboController` (variantes), `CartController` (`add`, `update`, `comboFaltaStock`, `getCartItems`), `Admin\DashboardController` (sin stock / stock bajo), `Admin\ProductController`/`PriceController`/`CategoryController` (listados), y en JS `Products/Show.jsx`, `Products/Index.jsx`, `Welcome.jsx` y `utils/stock.js`.

Estrategias:
- **Menos invasiva:** mantener `products.stock` y `product_variants.stock` como **cache local sincronizada desde el central** (webhook de cambios de stock + sincronización periódica/por SKU). Todo el código sigue leyendo las mismas columnas; el catálogo no hace llamadas HTTP por request. Riesgo: latencia/desfase entre sincronizaciones.
- Más pura pero más invasiva: un servicio `CentralStock` con cache de corta vida, y reemplazar las lecturas directas. Implica tocar los ~10 puntos de arriba.
- En ambos casos hay que decidir el significado de "disponible" (`en mano − reservado`), y qué hacer con `null`/9999: hoy `9999` por defecto representa "sin control de stock"; con stock real del central, ese comodín debe desaparecer o mapearse explícitamente.
- Los formularios de admin que editan stock a mano (`Admin\ProductController` y `ProductVariantsEditor.jsx`) deberían quedar **de solo lectura** (o con motivo de ajuste) cuando el central sea la fuente, para evitar divergencias. Hoy esas ediciones no dejan `StockMovement`.
- **Catálogo con variantes:** `Product::inStock()` y los filtros de listado miran solo `products.stock`; con stock real por variante del central convendría derivar la disponibilidad del producto desde sus variantes.

### Riesgos transversales

| # | Riesgo | Dónde |
|---|---|---|
| 1 | Llamada HTTP al central dentro de una transacción con `lockForUpdate` | `CartController` L1721 |
| 2 | Dos fuentes de verdad de stock si no se define quién descuenta | `StockService` vs. central |
| 3 | Sin clave de idempotencia ni referencia externa en `orders` | `orders` |
| 4 | `despachado → pendiente` reversible; el central no lo admite | `EstadoOrden` |
| 5 | Sin auditoría: no se sabe quién cambió un estado ni cuándo (solo `updated_at`) | `OrderController::updateStatus` |
| 6 | Sin SKU snapshot en `order_items`; SKU de variante opcional y no único; combos sin SKU | modelo de datos |
| 7 | Ediciones manuales de stock sin `StockMovement` (`ajuste_manual` sin uso) | `Admin\ProductController` |
| 8 | `products.stock` con default 9999 como "ilimitado" y `variant.stock = null` como "ilimitado": semánticas distintas | `Product`, `ProductVariant` |
| 9 | `Product::inStock()` solo mira el stock del producto, no de las variantes | `scopeInStock`, `routes/web.php` |
| 10 | Métricas de ingresos cuentan pedidos `pendiente` (todavía no vendidos) como ingreso | `buildMonthStats`, `buildDailyBreakdown`, `DashboardController` |
| 11 | No hay worker/scheduler; hosting probablemente compartido | `routes/console.php`, `.env.example` |
| 12 | `env()` fuera de `config/`: se rompe si se activa `config:cache` | `ProductImage`, `ComboImage`, `Admin\ProductController`, `Admin\ComboController` |
| 13 | Zona horaria `UTC`: los timestamps que se muestran/envíen al central no son hora de Argentina | `config/app.php` |
| 14 | `orders.user_id` ambiguo (comprador logueado, no vendedor) | `CartController` L1754 |
| 15 | Rol por defecto `admin` en `users` y seeder con credencial fija | migración `add_role_to_users_table`, `DatabaseSeeder` |
| 16 | Número de WhatsApp hardcodeado en el front | `Cart/Checkout.jsx` |
| 17 | Doble implementación de reglas de precio (PHP + `utils/pricing.js`) | cualquier cambio de precios |
| 18 | Productos/variantes borrados dejan `order_items` con FK `null`; `reponer()` ignora esas líneas, y el borrado de un producto elimina sus `stock_movements` (cascade) | FKs de `order_items`, `stock_movements` |

---

## 14. Números (según seeds, factories y migraciones; **no** se consultó la base de producción)

| Concepto | Valor en el código | Fuente |
|---|---|---|
| Productos sembrados | **12** (2 bases inalámbricas, 5 chispas frías 2m×20s/3m×30s/4m×30s/5m×1s/2m con mecha, 3 kits de detonación, 2 maletines), todos con SKU (p. ej. `CF-2M-20S`, `KIT-4CF-3M-30S`, `MALETIN-8D-2C`) | `database/seeders/ProductsSeeder.php` |
| Categorías sembradas | **24** (6 principales: Chispa Fría, Fuegos Artificiales, Maquinaria, Humo, Pirotecnia, Velas + 18 subcategorías) | `CategoriesSeeder.php` |
| Planes de cuotas sembrados | 3 (1 cuota 10 %, 3 cuotas 20 %, 6 cuotas 35 %) | `CardPaymentPlanSeeder.php` |
| Variantes, combos, add-ons, pedidos sembrados | **0** (no hay seeders; se cargan por el panel) | — |
| Usuarios sembrados | 1 admin | `DatabaseSeeder.php` |
| Productos de prueba (comando) | 5 | `app/Console/Commands/CreateTestData.php` (`php artisan test:create-data`) |
| Archivos subidos presentes en el repo local | 20 en `public/images/products` (muestra de desarrollo, no refleja producción) | listado de carpeta |

**Cantidad real en producción (productos activos, variantes, combos, pedidos): no se puede saber desde el repo.** Si querés tener los números para dimensionar la API, estas consultas son de solo lectura y las podés correr vos en la base que corresponda:

```sql
SELECT (SELECT COUNT(*) FROM products)                            AS productos,
       (SELECT COUNT(*) FROM products WHERE is_active = 1)        AS productos_activos,
       (SELECT COUNT(*) FROM products WHERE sku IS NULL)          AS productos_sin_sku,
       (SELECT COUNT(*) FROM product_variants)                    AS variantes,
       (SELECT COUNT(*) FROM product_variants WHERE sku IS NULL)  AS variantes_sin_sku,
       (SELECT COUNT(*) FROM product_variants WHERE stock IS NULL) AS variantes_stock_ilimitado,
       (SELECT COUNT(*) FROM products WHERE stock = 9999)         AS productos_stock_9999,
       (SELECT COUNT(*) FROM combos)                              AS combos,
       (SELECT COUNT(*) FROM addons)                              AS addons,
       (SELECT COUNT(*) FROM orders)                              AS pedidos,
       (SELECT COUNT(*) FROM orders WHERE estado = 'pendiente')   AS pedidos_pendientes;
SELECT estado, COUNT(*) FROM orders GROUP BY estado;
```

---

## Apéndice A — Mapa de archivos clave

| Tema | Archivo |
|---|---|
| Creación de pedido + mensaje WhatsApp | `app/Http/Controllers/CartController.php` (1897 líneas; `generateWhatsAppMessage` L1425) |
| Stock | `app/Services/StockService.php`, `app/Exceptions/StockInsuficienteException.php`, `app/Models/StockMovement.php`, `app/Enums/MotivoMovimientoStock.php` |
| Precios | `app/Services/PricingService.php`, `PriceResult.php`, `CardSurchargeService.php`, `DiscountCodeService.php`, `resources/js/utils/pricing.js` |
| Estados y panel de pedidos | `app/Enums/EstadoOrden.php`, `app/Http/Controllers/Admin/OrderController.php`, `resources/js/Pages/Admin/Orders/{Index,Show}.jsx`, `resources/js/utils/orders.jsx` |
| Modelos de pedido | `app/Models/Order.php`, `OrderItem.php` |
| Catálogo | `app/Models/{Product,ProductVariant,ProductOffer,ProductPriceTier,Addon,Combo,ComboItem}.php`, `app/Http/Controllers/Admin/ProductController.php` (941 líneas) |
| Roles y permisos | `app/Enums/RolUsuario.php`, `app/Models/User.php`, `app/Providers/AppServiceProvider.php` (Gates) |
| Rutas | `routes/web.php`, `routes/auth.php`, `routes/console.php` |
| Bootstrap | `bootstrap/app.php` (sin `api`) |
| Config entorno | `.env.example`, `config/queue.php`, `config/mail.php`, `config/services.php`, `config/documents.php` |

## Apéndice B — Preguntas abiertas para definir la API

1. ¿Quién es la fuente de verdad del stock una vez integrado: el central en exclusiva (la web deja de descontar) o la web conserva un espejo?
2. ¿Cuál es la clave de mapeo entre catálogos: SKU de producto/variante? ¿Hay que completar SKUs faltantes y hacer único el de variante?
3. ¿Cómo se identifica un combo en el central: por sus componentes, o se le da un SKU propio?
4. ¿Las reservas del central expiran? Si sí, ¿cómo se entera la web y quién cancela el pedido local?
5. ¿El central recibe `total` o `total_with_surcharge`? ¿Quién calcula el costo de envío y dónde queda registrado?
6. ¿Qué estados reconoce el central y cuáles son reversibles? ¿Se mantiene `despachado → pendiente`?
7. ¿El central puede llamar a la web (webhook entrante) o hay que hacer polling? ¿Hay IP fija / restricción en el hosting?
8. ¿El cliente final debe ver el estado/tracking en la web, por mail o solo por WhatsApp?
9. ¿Se necesita guardar qué vendedor confirmó cada venta (y mostrarlo en el central)?
10. ¿Hay que migrar/convertir los pedidos históricos o la integración rige solo para pedidos nuevos?