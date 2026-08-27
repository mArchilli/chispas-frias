# Contexto del proyecto — Chispas Frías

> Documento de referencia para dar contexto completo del sitio (qué es, cómo está armado, cómo funciona cada parte) a asistentes de IA (Claude) en futuras sesiones de trabajo. Generado a partir de una lectura completa del código el 2026-08-24, y revisado contra el código el 2026-08-27.
>
> Historial de actualizaciones:
> - **2026-08-24** — sistema de códigos de descuento; sistema de roles/cuentas de vendedor.
> - **2026-08-27** — "envío a sucursal" (el checkout dejó de pedir domicilio); nuevo Gate `gestionar-productos` (el ABM de productos quedó reservado a admin y el vendedor pasó a tener una vista de precios de solo lectura); rediseño de la página principal (hero/slider, Topbar de promos, Navbar nuevo); carga del catálogo real (12 productos con lista de precios) vía seeders.
> - **2026-08-27** — sistema de **variantes de color** (con stock y recargo propios) + **add-ons de personalización** (catálogo global con costo): modelo de datos, `PricingService`/`StockService`, y la capa de administración (CRUD de add-ons en `/admin/addons`; `ProductController` sincroniza variantes/add-ons/asociación medio→variante; `PriceController` los muestra de solo lectura). Catálogo público / carrito / checkout todavía NO tocados.
> - **2026-08-27** — **ficha pública de producto** con variantes/add-ons: `ProductController@show` expone las variantes y add-ons ACTIVOS del producto + `product_variant_id` por imagen; `Products/Show.jsx` suma selector de color (incluida la variante "a elección del cliente" con input de color libre + texto), galería reactiva por color, checklist de add-ons con texto obligatorio, y desglose de precio en vivo (recargo de variante + add-ons sobre el precio base/tier con oferta).
> - **2026-08-27** — **carrito / checkout con opciones**: `session('cart')` pasó de `{product_id: cantidad}` a un array de **líneas** (`line_key`, `product_id`, `quantity`, `variant_id`, `addon_selections`, `custom_color_text`). `CartController::add` acepta `variant_id`/`addon_ids[]`/`addon_texts`/`custom_color_text` y valida las opciones server-side reutilizando `PricingService::resolverVariante`/`resolverAddons`; `update`/`remove` operan por `line_key`. `GET /carrito` y `/carrito/checkout` resuelven cada línea con sus opciones (recargo de variante + total de add-ons) y la UI muestra color + add-ons con su texto y precio bajo cada ítem. El checkout snapshotea `variant_*` / `custom_color_text` / `addons_selected` / `addons_total` / `base_unit_price` en `order_items` y descuenta stock de la variante. `Products/Show.jsx` ya agrega al carrito productos con variantes/add-ons (botón gateado por `validarOpciones`). Se lee el shape viejo de sesión sin romper (carrito efímero: se degrada a líneas sin opciones).

---

## 1. Qué es el sitio

E-commerce de catálogo para **Chispas Frías**, un negocio que vende efectos especiales y pirotecnia para eventos (chispas frías / cold spark, fuegos artificiales, maquinaria de efectos, humo, pirotecnia, velas y bengalas).

El sitio muestra el catálogo público, permite armar un carrito con precios dinámicos (escalas por cantidad + ofertas) y **no cobra online**: el checkout arma un pedido (que sí queda persistido en base de datos), descuenta stock, y genera un mensaje de WhatsApp prellenado para que el cliente cierre la venta por chat con el vendedor. Aparte hay un panel de administración (`/admin`) para cargar productos, categorías, ofertas, ver y gestionar pedidos, y configurar el sitio (por ahora, el umbral de envío gratis).

## 2. Stack tecnológico

- **Backend**: Laravel 12 (PHP ^8.2)
- **Frontend**: React 18 + [Inertia.js](https://inertiajs.com/) v2 (SPA sin API REST separada: los controllers devuelven `Inertia::render(...)` con props tipadas, no JSON de una API pública)
- **Estilos**: Tailwind CSS 3 (+ `@tailwindcss/forms`)
- **Build**: Vite 7 + `laravel-vite-plugin`
- **Auth**: Laravel Breeze (stack Inertia/React) — login, registro, verificación de email, reset de contraseña
- **Sesiones/Cache/Queue**: driver `database` (tablas `sessions`, `cache`, `jobs`)
- **Base de datos**: MySQL en producción (`DB_CONNECTION=mysql`)
- **Extras JS**: `framer-motion` (animaciones), `react-quill`/`quill` (editor enriquecido para descripciones), `react-hot-toast` (notificaciones)

No hay API REST pública ni pasarela de pago integrada. Todo el negocio corre dentro de la misma app Laravel+Inertia.

## 3. Estructura de carpetas relevante

```
app/
  Http/Controllers/
    ProductController.php               # catálogo público
    CartController.php                  # carrito (sesión) + checkout + creación de orden + WhatsApp
    ProfileController.php                # perfil de usuario autenticado (Breeze)
    Auth/                                # controllers de Breeze
    Admin/
      DashboardController.php            # KPIs del panel
      CategoryController.php             # ABM de categorías (árbol 2 niveles)
      ProductController.php              # ABM de productos + imágenes/videos + escalas de precio
      ProductOfferController.php         # ofertas "inline"/modal rápido desde el producto
      ProductOfferAdminController.php    # vista dedicada de gestión de ofertas (listado, create/edit)
      OrderController.php                # listado/detalle de pedidos + cambio de estado + métricas
      SettingController.php              # configuración del sitio (umbral de envío gratis)
      DiscountCodeController.php         # ABM de códigos de descuento
      AddonController.php                # ABM del catálogo global de add-ons de personalización (solo admin)
      SellerController.php               # ABM de cuentas de vendedor (solo admin)
      PriceController.php                # listado de precios de SOLO LECTURA (única vía del vendedor para ver precios)
      Concerns/SyncsOfferDiscountFields.php  # trait compartido por los dos controllers de ofertas
  Models/
    Product.php, Category.php, ProductImage.php, ProductOffer.php, ProductPriceTier.php,
    Order.php, OrderItem.php, StockMovement.php, Setting.php, CartItem.php (sin uso), User.php,
    DiscountCode.php, ProductVariant.php (variante de color), Addon.php (add-on de personalización)
  Services/
    PricingService.php                   # resuelve precio final (tier + oferta [+ variante + add-ons]) — fuente de verdad de precios
    StockService.php                     # descuenta/repone stock (de la variante si el item la trae) con locking transaccional
    PriceResult.php                      # DTO de salida de PricingService
    DiscountCodeService.php              # valida/aplica/repone códigos de descuento — fuente de verdad de descuentos por código
  Enums/
    AlcanceOferta.php, EstadoOrden.php, MotivoMovimientoStock.php, TipoDescuento.php, RolUsuario.php
  Exceptions/
    StockInsuficienteException.php, DiscountCodeInvalidoException.php, VarianteRequeridaException.php
  Support/
    Provincias.php                       # catálogo de provincias/ciudades AR para el checkout
database/
  migrations/                            # historial de esquema
  seeders/                                # DatabaseSeeder (usuario admin demo) → CategoriesSeeder (árbol real de categorías)
                                          #   → ProductsSeeder (12 productos reales con precios/imágenes/descripciones)
resources/js/
  Pages/                                  # páginas Inertia (Products, Cart, Admin/*, Auth/*, Profile)
    Admin/DiscountCodes/                 # ABM de códigos de descuento (Index, Create, Edit)
    Admin/Addons/                        # ABM del catálogo global de add-ons (Index, Create, Edit)
    Admin/Sellers/                       # ABM de cuentas de vendedor (Index, Create, Edit)
    Admin/Prices/Index.jsx              # listado de precios de solo lectura (lo ve el vendedor en lugar de "Productos")
  Components/, Layouts/, hooks/, config/
    Components/Cart/DiscountCodeField.jsx  # input aplicar/quitar código, compartido por Cart/Index y Cart/Checkout
    Components/Cart/CartLineOptions.jsx    # color + add-ons (texto y precio) de una línea; compartido por Cart/Index y Cart/Checkout
    Components/ProductOptions.jsx       # ficha pública: selector de color (variantes) + checklist de add-ons con texto
    Components/Topbar.jsx               # cinta superior con marquesina de promos ("envíos gratis a partir de $X", etc.)
    Components/Admin/Icons.jsx          # set de íconos SVG inline del panel admin (nav de AdminLayout)
    Components/Admin/MediaDropzone.jsx  # subida de imágenes/videos del producto; opcionalmente un <select> "color asociado" por archivo
    Components/Admin/ProductVariantsEditor.jsx  # repeater de variantes de color (Create/Edit de producto)
    Components/Admin/ProductAddonsField.jsx     # checklist de add-ons del catálogo con override de precio por producto
    hooks/usePermissions.js             # lee auth.user.role compartido por Inertia (isAdmin/isVendedor/canBorrarCatalogo)
                                        #   — para "productos"/"add-ons" el front chequea isAdmin directo, no hay flag propio
  utils/
    pricing.js                           # espejo en JS de PricingService (tier + oferta [+ variante + add-ons]) — solo preview client-side
    stock.js                             # umbral de stock bajo compartido (LOW_STOCK_THRESHOLD = 3)
    orders.jsx                           # labels/badges de estado de orden
    discountCodes.js                     # vista previa en vivo del texto del descuento en Create/Edit (front)
    addons.js                            # vista previa en vivo del texto del add-on en Create/Edit (front)
    productVariants.js                   # opciones del <select> "color asociado" del gestor de imágenes
    productOptions.js                    # ficha pública: estado/validación de opciones + galería reactiva por variante
    uid.js                               # clave temporal de cliente (_uid) para filas nuevas del repeater de variantes
routes/
  web.php                                 # TODAS las rutas (público + admin), no hay routes/api.php en uso
  auth.php                                # rutas de Breeze
```

## 4. Modelo de datos

### Diagrama de relaciones

```
categories (self-referencing: parent_id → categories.id)
    └─< products (category_id)
            ├─< product_images (product_id, product_variant_id nullable → product_variants.id set null)
            ├─< product_offers (product_id) ──> product_price_tiers (product_price_tier_id, nullable)
            ├─< product_price_tiers (product_id)
            ├─< product_variants (product_id)          # variantes de color con stock y recargo propios
            ├─>< addons (via product_addon, con price_override + sort_order)   # catálogo GLOBAL de add-ons
            └─< stock_movements (product_id, product_variant_id nullable → product_variants.id set null)

orders (user_id nullable → users.id, discount_code_id nullable → discount_codes.id)
    └─< order_items (order_id, product_id nullable, product_variant_id nullable set null)
    └─< stock_movements (order_id nullable)

discount_codes
    └─< orders (discount_code_id, set null on delete)

settings (key/value genérico)

users (role, is_active)
    └─< cart_items (user_id)   # tabla existe pero NO se usa (carrito es de sesión)
```

### `categories`
Árbol de **2 niveles** (categoría principal + subcategoría), autorreferenciado por `parent_id`.

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| name | string | |
| description | text, nullable | |
| slug | string, unique | usado en URLs y filtros (`/productos?category=slug`) |
| parent_id | bigint, nullable, FK → categories.id, `onDelete cascade` | `null` = categoría principal |
| sort_order | int, default 0 | orden manual en listados |
| is_active | boolean, default true | |
| timestamps | | |

### `products`

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| title | string | |
| description | text | |
| price | decimal(10,2) | precio de lista base |
| sku | string, unique, nullable | |
| category_id | bigint, FK → categories.id, `onDelete restrict` | no se puede borrar una categoría con productos |
| stock | int, default **9999** | "stock infinito" por defecto; el stock real se descuenta en cada orden vía `StockService` |
| is_active | boolean, default true | visibilidad pública |
| is_featured | boolean, default false | usado en el home |
| timestamps | | |

Índices en `(category_id, is_active)`, `price`, `is_featured`.

### `product_price_tiers` (escalas de precio por cantidad)
Permite ofrecer precio unitario más bajo a partir de cierta cantidad comprada (ej: "de a 5 o más, $X c/u").

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| product_id | bigint, FK → products.id, `onDelete cascade` | |
| cantidad_minima | unsignedInteger | umbral de cantidad para que aplique este precio |
| precio_unitario | decimal(10,2) | precio unitario a partir de `cantidad_minima` |
| timestamps | | |

Único por `(product_id, cantidad_minima)`. `Product::tierAplicable($cantidad)` devuelve el tier de mayor `cantidad_minima` que sea `<= $cantidad`, o `null` (corresponde el precio base).

### `product_variants` (variantes de color — desde 2026-08-27)
Cada color de un producto es una **variante real**, con su propio stock y recargo. Migraciones `2026_08_27_1200xx`.

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| product_id | bigint, FK → products.id, `onDelete cascade` | |
| name | string(100) | único por `(product_id, name)` |
| color_hex | string(7), nullable | color de referencia (`#RRGGBB`). Si `is_custom_color`, es solo un ícono en el admin |
| is_custom_color | boolean, default false | "color a elección del cliente". **Máx. una por producto** (validado server-side en `ProductController`) |
| price_addon | decimal(10,2), default 0 | recargo propio, se **suma** al precio base/tier. La oferta NUNCA lo descuenta |
| stock | int, **nullable** | `null` = ilimitado (mismo criterio que `products.stock`) |
| sku | string, nullable | |
| sort_order | unsignedInteger, default 0 | |
| is_active | boolean, default true | |
| timestamps | | |

`Product::variants()` / `variantsActive()` / `hasVariants()`. `StockService` descuenta/repone del stock de la variante cuando el `order_item` trae `product_variant_id` (variante con `stock` null ⇒ no muta ni registra `StockMovement`).

### `addons` (catálogo GLOBAL de add-ons de personalización — desde 2026-08-27)
Add-ons reutilizables entre productos (ej. "grabado láser", "ambientación"). ABM en `/admin/addons` (`AddonController`, solo admin).

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| name | string(150), unique | |
| description | text, nullable | |
| price | decimal(10,2) | precio base; se puede sobrescribir por producto en el pivote |
| requires_text | boolean, default false | el cliente escribe un texto (ej. nombre a grabar) |
| text_placeholder | string, nullable | |
| max_characters | unsignedInteger, nullable, default 40 | |
| is_active | boolean, default true | |
| timestamps | | |

`Addon::haSidoUsado()` = true si aparece en algún `order_items.addons_selected` (se resuelve en PHP, no `whereJsonContains` — SQLite no hace partial-object match). Borrado bloqueado si `haSidoUsado()` (mismo criterio que `DiscountCode` con `usage_count`) y además reservado a `borrar-catalogo`.

### `product_addon` (pivote products ↔ addons)
| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| product_id | bigint, FK → products.id, `onDelete cascade` | |
| addon_id | bigint, FK → addons.id, `onDelete cascade` | |
| price_override | decimal(10,2), nullable | precio propio del add-on para ese producto (`null` = usa `addons.price`) |
| sort_order | unsignedInteger, default 0 | |
| timestamps | | |

Único por `(product_id, addon_id)`. `Product::addons()` / `addonsActive()` (con `->orderByPivot('sort_order')`).

### `product_offers` (ofertas/descuentos)
Historial de ofertas por producto. El modelo evolucionó: originalmente solo guardaba un `offer_price` fijo; ahora se define por **fórmula** (tipo + valor + alcance) y `offer_price`/`percentage_discount` quedan como **campos espejo** recalculados automáticamente (compatibilidad con vistas legacy del catálogo).

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| product_id | bigint, FK → products.id, `onDelete cascade` | |
| offer_price | decimal(10,2), **nullable** | espejo derivado — puede ser `null` si la oferta apunta a un tier no-base (ver §5) |
| percentage_discount | decimal(5,2), nullable | espejo derivado, informativo |
| tipo_descuento | enum `TipoDescuento` (`porcentaje`\|`fijo`) | fuente de verdad del descuento |
| valor_descuento | decimal(10,2) | monto o porcentaje según `tipo_descuento` |
| alcance | enum `AlcanceOferta` (`todos`\|`especifico`) | `todos` = aplica a cualquier cantidad; `especifico` = solo si el tier resuelto coincide con `product_price_tier_id` |
| product_price_tier_id | bigint, nullable, FK → product_price_tiers.id, `onDelete set null` | solo relevante si `alcance = especifico`; `null` con `alcance = especifico` = "aplica solo al precio base" (estado válido) |
| start_date / end_date | datetime, nullable | `null` = abierto |
| is_active | boolean, default true | flag manual además de las fechas |
| timestamps | | |

Una oferta vigente (`scopeActive`) requiere `is_active = true` **y** estar dentro de `[start_date, end_date]`. Crear/activar una oferta desactiva las demás ofertas activas del mismo producto (una oferta activa por producto a la vez).

### `orders` (pedidos — persisten desde 2026-08-16)
El checkout **sí crea una orden en base de datos** (a diferencia de una versión anterior del proyecto que no lo hacía).

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| user_id | bigint, nullable, FK → users.id, `set null` | la orden no requiere cuenta, es opcional |
| name, lastname, dni | string | datos del cliente |
| province, city, postal_code | string (city nullable) | localidad de retiro (ver "envío a sucursal" abajo) |
| address, number, between_streets | string, **nullable** | domicilio de entrega — **ya no se piden en el checkout** (migración `2026_08_24_180000_make_address_fields_nullable_in_orders_table`). Las columnas se conservan para no perder los datos de las órdenes viejas creadas con envío a domicilio; en órdenes nuevas quedan `null` |
| phone, email | string | contacto |
| observations | text, nullable | |
| estado | string, cast a enum `EstadoOrden`, default `pendiente` | `pendiente` \| `despachado` \| `cancelado` |
| discount_code_id | bigint, nullable, FK → discount_codes.id, `set null` | código aplicado al momento de la compra, si hubo uno |
| discount_code | string, nullable | snapshot del texto del código (sobrevive si el código se borra o cambia) |
| subtotal | decimal(10,2), nullable | total de productos antes de aplicar el descuento del código (backfill: `subtotal = total` para órdenes previas a la feature) |
| discount_amount | decimal(10,2), default 0 | monto descontado por el código |
| discount_usage_repuesto | boolean, default false | evita que `DiscountCodeService::reponerUso()` se llame dos veces sobre la misma orden al cancelarla |
| total | decimal(10,2) | `subtotal - discount_amount` |
| mensaje_whatsapp | text, nullable | copia del mensaje generado, para auditoría |
| timestamps | | |

Índice en `estado`.

### `discount_codes`
Códigos de descuento aplicables al subtotal del carrito (ver §6).

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| code | string, unique | normalizado a mayúsculas al guardar (`setCodeAttribute`), case-insensitive en la práctica |
| description | text, nullable | uso interno/admin |
| percentage | decimal(5,2) | porcentaje de descuento sobre el subtotal |
| min_purchase_amount | decimal(10,2), nullable | `null` = sin mínimo |
| usage_limit | unsignedInteger, nullable | `null` = ilimitado |
| usage_count | unsignedInteger, default 0 | se incrementa en `DiscountCodeService::registrarUso()`, se decrementa en `reponerUso()` |
| start_date / end_date | datetime, nullable | `null` = abierto, igual criterio que `product_offers` |
| is_active | boolean, default true | flag manual además de las fechas |
| timestamps | | |

Índice en `is_active`. No hay `discount_usage_repuesto` en esta tabla — ese flag vive en `orders` (ver arriba), porque es un estado por-orden ("¿esta orden ya repuso su uso?"), no del código en sí.

### `order_items`

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| order_id | bigint, FK → orders.id, `cascade` | |
| product_id | bigint, nullable, FK → products.id, `set null` | nullable para no romper el historial si se borra el producto |
| product_variant_id | bigint, nullable, FK → product_variants.id, `set null` | variante de color elegida (desde 2026-08-27) |
| product_title | string | copia del título al momento de la compra |
| variant_name / variant_color_hex / variant_price_addon | string / string / decimal(10,2) def 0 | snapshots de la variante, sobreviven a su borrado |
| custom_color_text | string, nullable | solo si la variante es `is_custom_color`: color libre pedido por el cliente |
| addons_selected | json, nullable | snapshot de los add-ons elegidos: array de `{addon_id, name, price, custom_text}` (cast `array`) |
| addons_total | decimal(10,2), default 0 | suma de los add-ons de la línea |
| cantidad | unsignedInteger | |
| precio_unitario | decimal(10,2) | precio final ya resuelto por `PricingService` — **con** recargo de variante y add-ons incluidos |
| base_unit_price | decimal(10,2), nullable | precio sin recargos (base/tier con oferta), para reportes |
| subtotal | decimal(10,2) | |
| timestamps | | |

### `stock_movements` (auditoría de stock)

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| product_id | bigint, FK → products.id, `cascade` | |
| product_variant_id | bigint, nullable, FK → product_variants.id, `set null` | `null` = el movimiento aplica al stock del producto (desde 2026-08-27) |
| order_id | bigint, nullable, FK → orders.id, `set null` | puede no venir de una orden (ajuste manual) |
| cantidad | integer | negativo = descuento, positivo = reposición |
| motivo | string, cast a enum `MotivoMovimientoStock` | `orden_creada` \| `orden_cancelada` \| `ajuste_manual` |
| stock_resultante | integer | snapshot del stock (del producto o de la variante) después del movimiento |
| timestamps | | |

### `settings` (configuración clave/valor genérica, desde 2026-08-24)

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| key | string, unique | |
| value | text, nullable | |
| timestamps | | |

Actualmente el único setting en uso es `free_shipping_threshold` (monto a partir del cual se considera "envío gratis" en el front — ver §9). `Setting::get()`/`Setting::set()` cachean el valor con `Cache::rememberForever`.

### `product_images`
Soporta **imágenes y videos** de un producto (galería + tipo).

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| product_id | bigint, FK → products.id, `cascade` | |
| product_variant_id | bigint, nullable, FK → product_variants.id, `set null` | medio asociado a un color (desde 2026-08-27); `null` = medio general |
| path | string | solo nombre de archivo (formato nuevo) o ruta completa legacy |
| alt_text | string, nullable | |
| sort_order | int, default 0 | |
| is_primary | boolean, default false | |
| type | enum('image','video'), default 'image' | |
| mime_type | string, nullable | |
| timestamps | | |

### `users`, `password_reset_tokens`, `sessions`, `cache`, `jobs`
Tablas estándar de Laravel (Breeze, sesiones/cache/cola en DB), con dos columnas propias agregadas sobre `users` (ver §12):

| Columna | Tipo | Notas |
|---|---|---|
| role | string, cast a enum `RolUsuario`, default `admin` | `admin` \| `vendedor` \| `cliente`. El default `admin` es una red de seguridad de backfill para cuentas preexistentes; el registro público lo pisa explícitamente con `cliente` |
| is_active | boolean, default true | `false` revoca el acceso de inmediato (ver `LoginRequest::authenticate`) y cierra las sesiones abiertas, sin borrar la cuenta ni su historial |

Índice en `role`.

### `cart_items` (existe pero no está en uso)
El carrito real funciona 100% con la sesión HTTP (`session('cart')`). Es carrito de invitado, no requiere login. El shape es un **array de líneas**, cada una:

```
{ line_key, product_id, quantity, variant_id (nullable),
  addon_selections: [{ addon_id, custom_text (nullable) }],
  custom_color_text (nullable) }
```

`line_key` es un hash SHA-256 estable de `product_id` + `variant_id` + add-ons (ordenados) + textos de personalización + color libre: dos líneas con el mismo `line_key` son "el mismo ítem" y suman cantidad, con `line_key` distinto conviven por separado (mismo producto, dos colores). Un producto sin opciones deja una sola línea que suma cantidad, igual que antes. `CartController` genera y valida todo esto server-side; también lee el shape viejo (`{product_id: cantidad}`) degradándolo a líneas sin opciones, para no romper un carrito abierto de antes del deploy (dato efímero, sin snapshot histórico).

## 5. Sistema de precios — `PricingService`

Pieza central del negocio: resuelve el precio unitario final para un producto y una cantidad, combinando **escalas por cantidad (tiers)** y **ofertas activas**. Vive en `app/Services/PricingService.php` y se usa en TODO lugar donde se necesita un precio real (carrito, checkout, ficha de producto, listado público, admin).

```php
public function calcularPrecio(Product $product, int $cantidad): PriceResult
{
    $tier = $product->tierAplicable($cantidad);
    $precioLista = round((float) ($tier?->precio_unitario ?? $product->price), 2);

    $offer = $this->ofertaActiva($product); // $product->currentOffer
    $ofertaAplicada = $offer && $this->ofertaAplica($offer, $tier) ? $offer : null;

    $precioUnitarioFinal = $ofertaAplicada
        ? $this->aplicarDescuento($precioLista, $ofertaAplicada)
        : $precioLista;
    // ... ahorroUnitario, ahorroPorcentaje

    return new PriceResult(/* ... */);
}
```

Reglas clave:
- **Tier aplicable**: el de mayor `cantidad_minima` que sea `<= cantidad` pedida. Si ninguno aplica, se usa `products.price`.
- **Oferta aplica** si `alcance = todos`, o si `alcance = especifico` **y** `product_price_tier_id` de la oferta coincide con el tier resuelto para esa cantidad (incluye el caso `null === null`, oferta apuntando al precio base).
- El descuento se aplica **sobre el precio de lista ya resuelto por tier**, no sobre `products.price` directamente.
- `PriceResult` es un DTO readonly con: `precioLista`, `precioUnitarioFinal`, `ofertaAplicada`, `tierAplicado`, `ahorroUnitario`, `ahorroPorcentaje`, y (opciones) `varianteAplicada`, `addonsAplicados`, `recargoVariante`, `addonsTotal`, `precioFinalConOpciones`.

**Opciones del producto (variante de color + add-ons) — desde 2026-08-27**: `calcularPrecio()` toma además `?int $varianteId = null, array $addonIds = [], bool $exigirVariante = false` (compat total con los callers de 2 args). Suma el `price_addon` de la variante y el precio efectivo de cada add-on (`price_override` del pivote, si no `addons.price`) para dar `precioFinalConOpciones`. **La oferta se calcula SIEMPRE solo sobre el precio base/tier — nunca sobre esos recargos.** Públicos `resolverVariante()`/`resolverAddons()` validan pertenencia + activo y lanzan `VarianteRequeridaException` (patrón `DiscountCodeInvalidoException`: constructor privado + named ctors); `CartController::add` los llama para validar las opciones elegidas antes de agregar la línea. `exigirVariante: true` (lo pasa `CartController::add`; el checkout hereda esa garantía porque toda línea con producto con variantes ya trae color) + producto con ≥1 variante activa y sin `varianteId` ⇒ excepción; la vidriera (ficha/catálogo) pasa `false`.

**Espejo en frontend**: `resources/js/utils/pricing.js` reimplementa la misma lógica en JS (incluidas variante + add-ons vía `calcularPrecio(product, cantidad, { varianteId, addonIds })`), pero **solo para preview instantáneo en el cliente** (pills de precio por cantidad en la ficha de producto, preview de descuento en el admin). El precio que efectivamente se cobra siempre se recalcula en el backend (agregar al carrito, actualizar cantidad, generar el pedido) — la duplicación nunca determina un monto real.

**Compatibilidad legacy**: `product_offers.offer_price` y `percentage_discount` se mantienen como campos "espejo", recalculados por `SyncsOfferDiscountFields::applyOfferDiscountMirror()` cada vez que se guarda una oferta, para no romper vistas del catálogo que todavía leen esos campos directamente (`Product::getCurrentOfferPrice()` los usa). Si la oferta es `especifico` sobre un tier no-base, `offer_price` queda `null` (no hay un precio único representable en `products`).

## 6. Códigos de descuento — `DiscountCodeService`

Segunda pieza de precio, separada de `PricingService`: un código de descuento (cupón) es un porcentaje que se aplica **sobre el subtotal ya resuelto del carrito** (después de tiers y ofertas por producto, no en reemplazo de ellos). Vive en `app/Services/DiscountCodeService.php` y es la fuente de verdad de todo lo relacionado a validar, calcular y contabilizar el uso de un código — igual que `PricingService` lo es para el precio de un producto.

```php
public function buscarValido(string $code, float $subtotal): DiscountCode
{
    $codeNormalizado = strtoupper(trim($code));
    $discountCode = DiscountCode::where('code', $codeNormalizado)->first();

    if (! $discountCode) { throw DiscountCodeInvalidoException::noExiste($codeNormalizado); }
    if (! $this->activoPorFechas($discountCode)) { throw DiscountCodeInvalidoException::inactivoOExpirado($codeNormalizado); }
    if ($discountCode->agotado()) { throw DiscountCodeInvalidoException::agotado($codeNormalizado); }
    if ($discountCode->min_purchase_amount !== null && $subtotal < (float) $discountCode->min_purchase_amount) {
        throw DiscountCodeInvalidoException::noAlcanzaMinimo(/* ... */);
    }

    return $discountCode;
}

public function calcularDescuento(DiscountCode $discountCode, float $subtotal): float
{
    return round($subtotal * ((float) $discountCode->percentage) / 100, 2);
}
```

Métodos principales:
- `buscarValido(string $code, float $subtotal)`: busca por texto (case-insensitive, vía `setCodeAttribute` que normaliza a mayúsculas) y valida vigencia (`is_active` + rango de fechas), que no esté agotado, y que el subtotal alcance `min_purchase_amount`. Lanza `DiscountCodeInvalidoException` con el motivo específico si algo falla.
- `calcularDescuento(DiscountCode $discountCode, float $subtotal)`: monto a descontar, redondeado a 2 decimales igual que `PricingService`.
- `registrarUso(DiscountCode $discountCode)`: incrementa `usage_count` dentro de una transacción con `lockForUpdate()`, revalidando bajo lock que no se haya agotado justo antes — mismo patrón que `StockService::descontar()` revalida stock bajo lock. Es lo que evita que dos checkouts concurrentes exploten un `usage_limit = 1`.
- `reponerUso(int $discountCodeId)`: decrementa `usage_count` (sin bajar de 0) al cancelar una orden, también bajo lock. La idempotencia (no reponer dos veces la misma orden) es responsabilidad de quien llama, apoyándose en `orders.discount_usage_repuesto`.

Reglas clave:
- **Se combina, no reemplaza**: el descuento por código se calcula sobre `subtotal` (la suma de los `subtotal` de cada línea del carrito, ya con tier + oferta de `PricingService` aplicados por producto), no sobre `products.price`. Un carrito puede tener a la vez ofertas por producto y un código de descuento activos.
- **Un código por carrito**: el texto del código aplicado se guarda en sesión (`cart_discount_code`, ver `CartController`), no hay acumulación de varios códigos.
- **Revalidación constante**: cada vez que se muestra el carrito/checkout, `CartController::resolveDiscountCode()` vuelve a correr `buscarValido()` contra la DB para el subtotal actual; si el código dejó de ser válido (desactivado, vencido, agotado, o el carrito bajó del mínimo) se quita silenciosamente de la sesión y el frontend avisa el motivo.
- **Locking transaccional en `registrarUso()`**: mismo espíritu que `StockService` con el stock — el chequeo optimista de `buscarValido()` antes de abrir la transacción da un error rápido, pero el chequeo definitivo bajo `lockForUpdate()` en `registrarUso()` es el que realmente protege `usage_limit` de una carrera entre dos checkouts simultáneos con el mismo código.
- **Reposición simétrica al cancelar**: `Admin/OrderController::updateStatus()`, en la transición `pendiente → cancelado`, llama a `reponerUso()` igual que llama a `StockService::reponer()` para el stock — y usa el mismo patrón de flag (`orders.discount_usage_repuesto`, análogo al `StockMovement` con `motivo = orden_cancelada`) para no reponer dos veces si la transición se reintenta.

## 7. Stock — `StockService`

`app/Services/StockService.php` maneja el descuento/reposición de stock con **locking transaccional** para evitar condiciones de carrera entre pedidos simultáneos.

- `validarDisponibilidad(array $items)`: chequeo **optimista** (sin lock), usado antes de abrir la transacción para devolver un error rápido si algo obviamente no alcanza.
- `descontar(Order $order)`: dentro de una transacción, hace `lockForUpdate()` sobre los productos de la orden (siempre en el mismo orden — `product_id` → `product_variant_id`, cada nivel asc — para evitar deadlocks entre transacciones concurrentes), revalida stock **bajo lock** (chequeo definitivo, no confía en `validarDisponibilidad`), descuenta y registra un `StockMovement` (`motivo = orden_creada`). Todo o nada: si un item falla, se aborta todo (`StockInsuficienteException`).
- `reponer(Order $order)`: repone stock al cancelar una orden. Protegido contra doble ejecución: dentro del lock, chequea si ya existe un `StockMovement` con `motivo = orden_cancelada` para esa orden antes de reponer.
- **Variantes de color (desde 2026-08-27)**: cuando un `order_item` trae `product_variant_id`, el descuento/reposición y el `StockMovement` operan sobre el stock de la **variante**, no el del producto. Una variante con `stock` null (ilimitada) no muta ni registra movimiento (mismo criterio que `products.stock`). El movimiento guarda igual `product_id` + `product_variant_id`.

## 8. Rutas principales (`routes/web.php`)

**Público**
- `GET /` — home (`Welcome.jsx`), rediseñada 2026-08-25: hero/slider (`HERO_SLIDES`, imagen `banner-hero-desktop.png`), atajos de categoría de chispa fría, `Topbar` con marquesina de promos, `Navbar` nuevo. Trae destacados (`is_featured`) y productos con oferta activa, ambos filtrando `stock > 0` y priorizando la categoría `chispa-fria`
- `GET /productos`, `GET /productos/{product}` — catálogo con filtro por categoría/subcategoría y búsqueda, detalle con "productos relacionados" (misma subcategoría → categoría padre → destacados → aleatorios, siempre completa a 3). La ficha (`Products/Show.jsx`) también renderiza, cuando el producto los tiene, el **selector de color** (variantes activas con su stock; la variante `is_custom_color` como una opción más, con input de color libre + texto descriptivo, al menos uno obligatorio), la **galería reactiva** (media propia de la variante elegida, con la general como respaldo; imágenes y video se resuelven por separado), el **checklist de add-ons** (precio efectivo = `price_override` ?? `price`, texto obligatorio si `requires_text`, respetando `max_characters`), y el **desglose de precio en vivo** (recargo de variante + total de add-ons sobre el precio base/tier con oferta, vía `pricing.js`). **Agregar al carrito** manda `variant_id`/`addon_ids`/`addon_texts`/`custom_color_text` (armados por `buildAddToCartPayload` en `utils/productOptions.js`, enviados con `axios`) y el botón queda deshabilitado hasta que `validarOpciones` dé OK. Un producto sin variantes ni add-ons se ve y se comporta igual que antes de este sistema.
- `GET /contacto`, `/servicios`, `/servicios/chispas` — páginas estáticas Inertia
- `carrito/*` — `index`, `checkout`, `add`, `update`, `remove`, `clear`, `count`, `whatsapp`, `descuento` (todas sin login). `add` toma `product_id`/`quantity` + opciones (`variant_id`, `addon_ids[]`, `addon_texts`, `custom_color_text`); `update`/`remove` toman `line_key` (ver §4 `cart_items` y §9)

**Autenticado** (`middleware auth`)
- `/profile` (editar/borrar cuenta — Breeze)

**Admin** (`middleware auth,verified,can:acceder-panel-admin`, prefijo `/admin` — ver §12 para los Gates y qué controlan)
- `/admin/dashboard` — KPIs (ver §10)
- `Route::resource('categories', ...)` + `toggle-status` (`destroy` reservado a `can:borrar-catalogo`)
- **Grupo `can:gestionar-productos` (solo admin, ver §12)**:
  - `Route::resource('products', ...)` + `toggle-status`, `toggle-featured`, `set-primary-image`. `store`/`update` sincronizan, en la **misma transacción** que el producto y sus `price_tiers`: variantes de color (`ProductController::syncVariants`, full-sync como los tiers, devuelve un mapa `_uid ⇒ id` de las nuevas), add-ons asociados (`syncAddons`, pivote `product_addon` con `price_override`), y la asociación medio→variante (`resolveVariantRef`: `''`/`null` = general, `uid:xxx` = variante nueva de este submit, numérico = variante existente)
  - Ofertas rápidas desde el producto (`ProductOfferController`: `products/{product}/offers` store/update/destroy, `offers/{offer}/toggle`, `products/{product}/quick-offer`) — el `destroy` además bajo `can:borrar-catalogo`
  - `Route::resource('addons', ...)->only([index/create/store/edit/update/destroy])` + `toggle-status` (`AddonController`; `destroy` además bajo `can:borrar-catalogo` y bloqueado si el add-on ya se usó en una orden)
- `prices` — `GET /admin/prices` (`PriceController@index`), listado de precios de **solo lectura** con los mismos filtros de categoría/búsqueda que el catálogo, ahora también con las variantes activas (recargo + stock) y add-ons activos (precio efectivo) por producto. Fuera del grupo `gestionar-productos`: lo ven admin **y vendedor**. Es la única vía del vendedor para ver precios
- Vista dedicada de ofertas: `Route::resource('offers', ...)->only([index, create, store, edit, update, destroy])` (`ProductOfferAdminController`) + `offers/{offer}/toggle-status`. **Fuera** del grupo `gestionar-productos` → el vendedor sí puede crear/editar/togglear ofertas acá (`destroy` reservado a `can:borrar-catalogo`)
- `discount-codes` — `Route::resource(...)->only([index/create/store/edit/update/destroy])` + `toggle-status` (`destroy` reservado a `can:borrar-catalogo`)
- `orders` — `orders.index` (cola operativa + métricas del mes, éstas últimas solo si `isAdmin()`), `orders.show` (detalle), `orders.update-status` = `PATCH orders/{order}/status` (cambio de estado, sin gate — vendedor puede transicionar incluida la cancelación)
- `settings` — `edit`/`update` (umbral de envío gratis), bajo `can:gestionar-configuracion` (solo admin)
- `sellers` — `Route::resource(...)->except(['show', 'destroy'])` + `toggle-status`, bajo `can:gestionar-vendedores` (solo admin)

No hay `routes/api.php` en uso; toda la comunicación front-back es vía Inertia (navegación) o `axios`/`fetch` a estas mismas rutas web (los endpoints de carrito devuelven JSON si `expectsJson()`).

## 9. Flujo de negocio: carrito → orden → WhatsApp

1. El visitante agrega productos: `POST /carrito/agregar` toma `product_id`/`quantity` + opciones (`variant_id`, `addon_ids[]`, `addon_texts`, `custom_color_text`), valida la variante y los add-ons contra el producto (pertenencia + activo, reutilizando `PricingService::resolverVariante`/`resolverAddons`; si el producto tiene variantes activas elegir una es obligatorio ya acá), chequea stock (de la variante si la línea la tiene, si no del producto) y guarda una **línea** en la sesión (ver §4 `cart_items` para el shape y `line_key`). Si ya hay una línea con el mismo `line_key` suma cantidad; si no, crea una nueva. `POST /carrito/actualizar` y `DELETE /carrito/eliminar` operan por `line_key`.
2. `GET /carrito` y `GET /carrito/checkout` resuelven cada línea con `PricingService::calcularPrecio()` según la cantidad real **y las opciones de esa línea** (variante + add-ons) — así el usuario ve precio con tier/oferta + recargo de color + costo de add-ons ya aplicados antes de confirmar. Cada ítem del carrito trae `price` (base, sin opciones), `unit_price` (con opciones), `variant_surcharge`, `addons_total`, `variant` (`{id,name,color_hex,is_custom_color,price_addon}`), `custom_color_text` y `addons` (`[{addon_id,name,price,custom_text}]`); la UI (`CartLineOptions`) muestra el color y los add-ons con su texto y precio bajo cada ítem. Una línea cuya variante/add-on dejó de estar activo se descarta silenciosamente del listado (carrito efímero). Si hay un código de descuento válido en sesión (`cart_discount_code`), ambas vistas también muestran el desglose subtotal → descuento → total (ver §6).
3. `GET /carrito/checkout` pide datos de contacto y de retiro: nombre, apellido, DNI, provincia/localidad de Argentina (desde `App\Support\Provincias`), código postal, teléfono, email, observaciones. **Ya NO pide dirección/número/entre calles**: desde 2026-08-24 el negocio solo hace *envío a sucursal*, no a domicilio (el retiro se coordina por WhatsApp). El form aclara "Solo hacemos envíos a sucursal, no a domicilio". Muestra `FreeShippingProgress` si hay un `free_shipping_threshold` configurado.
4. `POST /carrito/descuento` (`CartController::applyDiscountCode`) / `DELETE /carrito/descuento` (`removeDiscountCode`): aplican o quitan un código, validando siempre contra la DB vía `DiscountCodeService::buscarValido()`. Solo el texto del código se persiste en sesión; el monto se recalcula siempre server-side.
5. `POST /carrito/whatsapp` (`CartController::generateWhatsAppMessage`):
   - Valida los datos de contacto (obligatorios, se van a persistir en `orders`).
   - **Antes de calcular precio o tocar stock**, revalida línea por línea las opciones obligatorias (`validarOpcionesObligatorias`): producto con variantes activas sin `variant_id`, variante "a elección del cliente" sin `custom_color_text`, add-on con `requires_text` sin texto → 422 con un mensaje que nombra el producto (y el add-on) y no crea nada. Además `getCartItems()` corre acá con `exigirVariante: true` como segunda barrera (cubre la variante/add-on que se desactivó después de armar el carrito): si `PricingService` lanza, devuelve 422 en vez de descartar la línea en silencio como hacen las vistas del carrito.
   - Chequeo optimista de stock (`StockService::validarDisponibilidad`); si falta algo, devuelve 422 con el detalle.
   - Chequeo optimista del código de descuento en sesión, si hay uno (`DiscountCodeService::buscarValido()`); si dejó de ser válido, lo quita de la sesión y devuelve 422.
   - Arma el mensaje de texto (productos, **color y add-ons elegidos con su texto y costo**, precio original vs. final si hubo ahorro, precio unitario con opciones si hay recargo, subtotales, línea de código de descuento si aplica, total, datos del cliente). El color se imprime como `Color: {nombre}` para una variante fija y `Color solicitado: {custom_color_text}` para la variante "a elección del cliente"; cada add-on como `{nombre}: "{texto}"` (+ su recargo). Un ítem sin variante ni add-ons se imprime exactamente igual que antes de la mejora. El bloque de dirección se titula "📍 *Envío a Sucursal:*" y lista provincia / ciudad / código postal (sin calle ni número).
   - Dentro de una transacción: crea el `Order` + sus `OrderItem`, cada uno con el precio ya resuelto por `PricingService` y el **snapshot de las opciones** (`product_variant_id`, `variant_name`, `variant_color_hex`, `variant_price_addon`, `custom_color_text`, `addons_selected` = `[{addon_id,name,price,custom_text}]`, `addons_total`; `precio_unitario` = final CON recargos, `base_unit_price` = sin recargos). Llama a `StockService::descontar()` (chequeo definitivo bajo lock + `StockMovement`; descuenta del stock de la variante cuando el ítem la trae), y si hay código de descuento, llama a `DiscountCodeService::registrarUso()` (chequeo definitivo bajo lock — si el código se agotó por una carrera con otro checkout concurrente, aborta toda la transacción, orden y descuento de stock incluidos).
   - Si algo falla (incluida `StockInsuficienteException` o `DiscountCodeInvalidoException`), no se crea nada y se informa el error.
   - Si todo sale bien, vacía la sesión del carrito (incluido `cart_discount_code`) y devuelve `{ success, message, subtotal, discount_amount, total, order_id }`.
6. El frontend muestra una pantalla de confirmación ("¡Pedido listo!" con nº de pedido y resumen) y un botón que abre WhatsApp con el mensaje prellenado: en mobile usa el esquema nativo `whatsapp://send?phone=5491178886833&text=...`, en desktop `https://web.whatsapp.com/send?phone=5491178886833&text=...` (número hardcodeado en `Cart/Checkout.jsx`). No se usa `window.open()` automático porque los navegadores móviles lo bloquean tras un `await`; el usuario toca el botón (gesto directo). El pedido ya quedó registrado con estado `pendiente` aunque el cierre de venta sigue pasando por chat manual.

**Estados de una orden** (`EstadoOrden`): `pendiente → despachado | cancelado`; `despachado → pendiente`; `cancelado` es terminal (no transiciona a nada). Solo la transición `pendiente → cancelado` repone stock (vía `StockService::reponer`) y, si la orden tenía un código de descuento, repone su uso (vía `DiscountCodeService::reponerUso()`, protegido por `orders.discount_usage_repuesto`) — ambas gestionadas desde `Admin/OrderController::updateStatus`.

## 10. Panel de administración

- **Dashboard** (`Admin/DashboardController`): los KPIs operativos (categorías, productos, stock, ofertas activas, códigos de descuento activos, pedidos pendientes) se calculan para admin y vendedor por igual; los financieros (ingresos del mes, ticket promedio, últimos 5 pedidos con monto) son exclusivos de `isAdmin()` — para un vendedor esas queries ni se corren y las props ni se envían (ver §12).
- **Categorías**: árbol de 2 niveles, no se puede borrar una categoría con productos o subcategorías. Borrado reservado al Gate `borrar-catalogo` (solo admin).
- **Productos** (`Admin/ProductController`): ABM completo + subida de imágenes/videos (hasta 10 archivos, ≤20MB c/u; el gestor de medios permite asociar cada archivo a una variante de color) + gestión de escalas de precio (`price_tiers`), **variantes de color** (repeater `ProductVariantsEditor`: nombre, color, recargo, stock, activo, "color a elección del cliente" — máx. una por producto, validado server-side) y **add-ons asociados** (`ProductAddonsField`: checklist del catálogo global con override de precio opcional). Todo se sincroniza en la misma transacción que el producto (sync completo: se borra lo que no viene). Las variantes nuevas usan una clave temporal de cliente (`_uid`) que el backend traduce a su id real al guardar, para poder asociarles imágenes en el mismo submit. **Toda la sección es exclusiva de admin** (Gate `gestionar-productos`); el borrado además bajo `borrar-catalogo`.
- **Add-ons** (`Admin/AddonController`): ABM del **catálogo global** de add-ons de personalización (`/admin/addons`), mismo estilo que Códigos de descuento. `name` único; `destroy` bloqueado si el add-on ya apareció en alguna orden (`Addon::haSidoUsado()`, criterio análogo a `DiscountCode` con `usage_count`) y además reservado a `borrar-catalogo` — la vía recomendada para dejar de ofrecerlo es desactivarlo. **Exclusivo de admin** (grupo `gestionar-productos`); en el nav aparece junto a "Productos" (mismo criterio `isAdmin` del front).
- **Precios** (`Admin/PriceController`, solo `index`): listado de **solo lectura** de productos con su precio de lista, escalas por cantidad, oferta vigente y —desde 2026-08-27— las variantes de color activas (recargo + stock) y los add-ons activos (precio efectivo), con los mismos filtros de categoría/búsqueda del catálogo. Lo ven admin y vendedor. Es lo que ve el vendedor en el nav en lugar de "Productos", ya que no puede editar el catálogo. Sin ninguna acción de edición.
- **Ofertas**: dos puntos de entrada comparten el trait `SyncsOfferDiscountFields` (mismas reglas de validación y cálculo de campos espejo). El **modal rápido desde el producto** (`ProductOfferController`) es exclusivo de admin (grupo `gestionar-productos`). La **vista dedicada** `Admin/Offers` (`ProductOfferAdminController`) queda abierta al vendedor: puede crear/editar/togglear; muestra estado calculado (`activa` / `programada` / `expirada` / `inactiva`) y descuento promedio de las vigentes. Borrado (en ambas) reservado a `borrar-catalogo`.
- **Códigos de descuento** (`Admin/DiscountCodeController`, ver §6): listado con filtro por estado calculado (`activo`/`programado`/`expirado`/`inactivo`/`agotado`) y búsqueda por código; `code` sólo editable mientras `usage_count = 0` (una vez usado, renombrarlo confundiría el historial); borrado también bloqueado si tiene usos (`usage_count > 0`) además de estar reservado a `borrar-catalogo` — la vía recomendada para dejar de ofrecerlo es desactivarlo.
- **Vendedores** (`Admin/SellerController`, ver §12): ABM exclusivo de admin (Gate `gestionar-vendedores`) sobre cuentas `role = vendedor`. No tiene `destroy`: se reemplaza por activar/desactivar. Alta genera una contraseña temporal random que se muestra una única vez en un modal (flash de sesión `temporaryPassword`) para que el admin se la pase al vendedor a mano.
- **Pedidos** (`Admin/OrderController`): cola operativa filtrable por estado (default `pendiente`) y búsqueda por nombre/apellido/DNI/email, visible para admin y vendedor por igual (incluida la posibilidad de cambiar estado, incluida la cancelación con reposición de stock y de uso de código); detalle con transiciones de estado válidas, monto completo de la orden (subtotal, descuento, total) y el **color / add-ons elegidos de cada ítem** (snapshot del `order_item`, con el texto que escribió el cliente) para que quien despacha sepa qué preparar, visible para ambos roles; el panel de métricas de negocio navegable por mes (ingresos, ticket promedio, producto más vendido, top 3 provincias, gráfico diario de pedidos/ingresos) es exclusivo de `isAdmin()`. Nunca deja navegar a un mes futuro.
- **Configuración** (`Admin/SettingController`): por ahora solo `free_shipping_threshold`, usado por `FreeShippingProgress.jsx` en carrito y checkout para mostrar una barra de progreso hacia "envío gratis" — **es solo informativo/de marketing**, el costo de envío real siempre se coordina manualmente con el cliente por WhatsApp (no hay cálculo de envío real). Exclusivo de admin (Gate `gestionar-configuracion`).

## 11. Manejo de imágenes/videos de productos

Las imágenes/videos se suben por el form de admin (`multipart`, hasta 10 archivos, ≤20MB c/u; formatos: jpg/png/gif/webp + mp4/mov/avi/wmv/flv/webm) y se guardan en el filesystem público, **no en `storage/` de Laravel** ni en un disco S3.

- Carpeta física: `public_path(env('PRODUCT_IMAGES_PATH', '/images/products/'))`. En `.env` actual apunta fuera del proyecto (`/../../public_html/images/products/`), pensado para hosting compartido donde `public_html` es la webroot real.
- `ProductImage->path` guarda solo el nombre de archivo (formato nuevo) o una ruta completa legacy — `getUrlAttribute()` resuelve ambos casos usando `VITE_PRODUCT_IMAGES_PATH` como prefijo para el formato nuevo; `getFilesystemPath()` resuelve la ruta física para borrar el archivo.
- Al borrar una imagen o un producto, se intenta `unlink()` del archivo físico además del registro en DB.
- El upload tiene 3 estrategias en cascada (`move()` → `copy()` → `file_get_contents`/`file_put_contents`) por problemas previos de permisos/entornos, con logging extenso (`Log::info/warning/error`) en cada paso.
- **Asociación a una variante de color (desde 2026-08-27)**: `product_images.product_variant_id` (nullable). En el form, `MediaDropzone` muestra un `<select>` "color asociado" por archivo nuevo, y la grilla "Multimedia actual" uno por archivo ya subido. El front manda la referencia como `''` (general), `'<variantId>'` (variante existente) o `'uid:<_uid>'` (variante creada en el mismo submit); `ProductController::resolveVariantRef` la resuelve al id real. `store` la lee de `images_variant[]` (alineado por índice); `update` de `new_images_variant[]` y `existing_images_variant{imageId: ref}`.
- **Galería pública reactiva (desde 2026-08-27)**: `ProductController@show` incluye `product_variant_id` en cada imagen; `Products/Show.jsx` (vía `utils/productOptions.js::galeriaDeVariante`) muestra, al elegir un color, la media propia de esa variante y usa la general (`product_variant_id` null) como respaldo — imágenes y video se resuelven en buckets separados. Sin variantes elegidas (o producto sin variantes) la galería es toda la media general, igual que antes.

## 12. Autenticación y roles

Breeze estándar (login, registro, verificación de email, "recordar contraseña", confirmación de password, borrado de cuenta con reconfirmación de password), sobre el que se montó un sistema de roles simple con tres valores (`App\Enums\RolUsuario`: `admin` \| `vendedor` \| `cliente`, columna `users.role`).

**Gates** (definidos en `AppServiceProvider::boot()`):

| Gate | Quién pasa | Qué controla |
|---|---|---|
| `acceder-panel-admin` | admin, vendedor | entrada a `/admin/*` (middleware de todo el grupo de rutas admin) |
| `gestionar-productos` | solo admin | grupo de rutas de ABM de productos (`products.*`, `toggle-featured`, `set-primary-image`), **add-ons** (`addons.*`), y ofertas rápidas desde el producto (`ProductOfferController`) |
| `borrar-catalogo` | solo admin | `destroy` de categorías, productos, ofertas (ambos controllers), códigos de descuento y add-ons |
| `gestionar-configuracion` | solo admin | `/admin/settings` (edición del umbral de envío gratis) |
| `gestionar-vendedores` | solo admin | `/admin/sellers` (ABM de cuentas de vendedor) |

`User::puedeAccederAlPanel()` (`isAdmin() || isVendedor()`) es lo que respalda el primer Gate; `User::isAdmin()`, `isVendedor()`, `isCliente()` respaldan el resto.

**Qué puede hacer un vendedor**: entrar a `/admin` y a Dashboard, Categorías, **Precios** (solo lectura), Ofertas (vista dedicada), Códigos de descuento y Pedidos; crear, editar y activar/desactivar categorías, ofertas y códigos de descuento; ver y cambiar el estado de cualquier pedido — **incluida la cancelación**, con el monto completo de la orden visible.

**Qué NO puede hacer un vendedor**: gestionar productos ni add-ons — el ABM de productos, el ABM de add-ons y el modal rápido de ofertas están tras el Gate `gestionar-productos` (HTTP 403); en su lugar tiene la vista de Precios de solo lectura; borrar categorías, ofertas, códigos de descuento o add-ons (Gate `borrar-catalogo`, HTTP 403); ver las métricas financieras del Dashboard ni del listado de Pedidos (ingresos, ticket promedio, últimos pedidos, gráfico diario — esas props ni se calculan ni se envían, ver §10); entrar a Configuración ni a Vendedores (403).

**Registro y alta de cuentas**:
- El registro público (`RegisteredUserController`) siempre crea `role = cliente` — nunca se puede llegar a `admin`/`vendedor` por `/register`.
- Las cuentas de vendedor **sólo las crea un admin**, desde `/admin/sellers` (`SellerController::store`): genera una contraseña temporal aleatoria (`Str::password(12)`), marca `email_verified_at = now()` (el admin ya validó la dirección a mano) y devuelve la contraseña una única vez por flash de sesión (`temporaryPassword`) para que el admin se la pase al vendedor.
- No hay `destroy` de vendedor: `toggleStatus` desactiva la cuenta (`is_active = false`), lo que además cierra cualquier sesión abierta (`DB::table('sessions')->where('user_id', ...)->delete()`) sin borrar el historial de pedidos que gestionó.
- `LoginRequest::authenticate()` rechaza el login de una cuenta con `is_active = false` **después** de validar la contraseña (para no filtrar por timing si la cuenta existe), con logout inmediato de la sesión recién creada.

**Frontend**: `resources/js/hooks/usePermissions.js` lee `auth.user.role` (compartido por Inertia vía `HandleInertiaRequests`) y expone `{ role, isAdmin, isVendedor, canBorrarCatalogo }` para gatear qué se muestra — el backend (Gates) es la fuente real de verdad, esto sólo evita mostrar acciones que van a devolver 403. No hay flag propio para `gestionar-productos`: donde importa, el front chequea `isAdmin` directo. `AdminLayout.jsx` usa esto para: mostrar "Productos" + "Add-ons" al admin vs. "Precios" al vendedor en el nav; ocultar "Precios" (entrada extra de admin), "Envío gratis" y "Vendedores" a un vendedor; y mostrar un badge de rol (`Admin`/`Vendedor`) junto al nombre del usuario en el sidebar/dropdown.

## 13. Variables de entorno relevantes (más allá de las típicas de Laravel)

| Variable | Uso |
|---|---|
| `PRODUCT_IMAGES_PATH` | carpeta física (server-side) donde se guardan/leen imágenes y videos de productos |
| `VITE_PRODUCT_IMAGES_PATH` | prefijo de URL pública para imágenes con el "formato nuevo" (solo nombre de archivo) |
| `DB_CONNECTION` / `DB_*` | MySQL en producción |
| `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database` | todo corre sobre la misma DB, sin Redis en uso pese a estar configurado en `.env.example` |

## 14. Comandos de consola propios

- `app/Console/Commands/BackfillProductOfferDiscountFields.php` — completa `tipo_descuento`/`valor_descuento`/`alcance` en ofertas viejas a partir del `offer_price` legacy (migración del modelo de ofertas de precio fijo a fórmula).
- `app/Console/Commands/CreateTestData.php` — generación de datos de prueba.

## 15. Tests

Cobertura de Feature tests relevante (en `tests/Feature/`):
- **Pricing**: `PricingServiceTest`, `ProductPriceTierTest`, `PublicCatalogPricingTest`, `CartPricingIntegrationTest`.
- **Stock**: `StockServiceTest`, `StockServiceConcurrencyTest`, `StockMovementTest`, `CartStockEnforcementTest`, `PublicCatalogStockVisibilityTest`.
- **Ofertas**: `AdminOfferDiscountFieldsTest`, `BackfillProductOfferDiscountFieldsTest`.
- **Códigos de descuento**: `DiscountCodeServiceTest` (validación, cálculo, registro/reposición de uso), `DiscountCodeConcurrencyTest` (locking de `usage_limit` bajo checkouts concurrentes, mismo espíritu que `StockServiceConcurrencyTest`), `AdminDiscountCodeTest` (ABM en `/admin/discount-codes`), `CartDiscountCodeIntegrationTest` (aplicar/quitar/revalidar código en carrito y checkout), `CartWhatsAppDiscountCodeTest` (código de descuento en el flujo de creación de orden vía WhatsApp), `AdminOrderCancelDiscountCodeTest` (reposición de uso al cancelar una orden con código).
- **Órdenes/admin**: `OrderTest`, `AdminOrderIndexShowTest`, `AdminOrderStatusUpdateTest`, `AdminProductPriceTierSyncTest`.
- **Variantes + add-ons**: `PricingServiceOpcionesTest` (precio con variante/add-ons, oferta solo sobre base), `StockServiceVariantesTest` (stock por variante), `AdminAddonTest` (ABM en `/admin/addons`, `destroy` bloqueado si usado), `AdminProductVariantAddonSyncTest` (sync de variantes/add-ons en `store`/`update`, máx. una `is_custom_color`, `distinct` de nombre, asociación imagen→variante vía `uid:`), `AdminPriceListTest` (variantes/add-ons activos y `price_effective` en la vista de solo lectura), `PublicProductPageOptionsTest` (la ficha pública expone solo variantes/add-ons activos con recargo/stock/precio efectivo, `product_variant_id` por imagen, arrays vacíos si el producto no tiene ninguno, y el precio de entrada no incluye el recargo de variante).
- **Roles y vendedores**: `RolePermissionsTest` (los 5 Gates, acceso por rol a cada sección — `admin.prices.index` compartida; `admin.products.index` y `admin.addons.index` exclusivas de admin —, el vendedor no puede gestionar productos ni add-ons pero sí ver precios, registro público siempre `cliente`), `AdminSellerTest` (ABM de vendedores, contraseña temporal, activar/desactivar), `AdminOrderVendorVisibilityTest` (qué props financieras se ocultan a un vendedor en Dashboard y en Pedidos).
- **Carrito/WhatsApp**: `CartWhatsAppOrderTest`, `CartPricingIntegrationTest`, `CartStockEnforcementTest`, `CartVariantAddonTest` (líneas por variante/add-ons/texto, `line_key`, validación server-side de opciones en `add`, `update`/`remove` por `line_key`, snapshot de opciones en `order_items` y descuento de stock de la variante en el checkout; defensa en profundidad del checkout: aborta el pedido entero —nombrando el producto— si una línea no eligió color, la variante "a elección" no trae color libre, un add-on obligatorio llegó sin texto, o la variante/add-on se desactivó; `Color solicitado:` en el mensaje para la variante a elección).
- **Perfil**: `ProfileTest`.

También hay un harness de concurrencia dedicado en `tests/concurrency/` (bootstrap/setup/worker) para probar `StockService` bajo carga simultánea real.

## 16. Cosas a tener en cuenta / deuda técnica

- **`cart_items` (tabla + modelo) no se usa** — el carrito es 100% de sesión. Si se quisiera carrito persistente por usuario logueado, el modelo ya existe pero falta cablearlo en `CartController`.
- **El número de WhatsApp de destino está hardcodeado** en `resources/js/Pages/Cart/Checkout.jsx` (`5491178886833`), no es configurable desde `settings` ni desde `.env`.
- **Columnas de domicilio muertas en `orders`**: `address`, `number` y `between_streets` quedaron nullable y sin uso desde el cambio a "envío a sucursal"; el modelo `Order` todavía las tiene en `$fillable`, `Admin/OrderController::show()` las sigue enviando y `Admin/Orders/Show.jsx` aún las renderiza (vacías en órdenes nuevas). Si el cambio a sucursal es definitivo, conviene limpiar esos campos del detalle o quitarlos del todo.
- **Solapamiento entre `CategoriesSeeder` y `ProductsSeeder`**: el árbol "real" de categorías vive en `CategoriesSeeder`, pero `ProductsSeeder` también crea/busca categorías con `firstOrCreate` (con nombres levemente distintos, ej. "Chispas Frias" vs "Chispa Fría", y una subcategoría `4x20` que sólo existe acá). El orden de `DatabaseSeeder` (categorías → productos) hace que en un seed limpio funcione, pero son dos fuentes de verdad para lo mismo.
- **El envío gratis es solo un indicador visual**: `free_shipping_threshold` no calcula ni cobra costo de envío real; el envío efectivo siempre se coordina por WhatsApp después del pedido.
- **Credencial hardcodeada en el seeder**: `database/seeders/DatabaseSeeder.php` crea un usuario admin de demo (`chispasfrias.oficial@gmail.com`, name "Duilio") con la contraseña en texto plano dentro del código fuente. Como no pasa `role`, cae en el default `admin` (vía `User::$attributes`). Si ese seeder corrió alguna vez contra la base de producción (o el repo es público/compartido), conviene rotar esa contraseña y mover el valor a una variable de entorno o generarla al azar.
- El stock por defecto es 9999 ("infinito") pero, a diferencia de una versión anterior del proyecto, **ahora sí se descuenta de verdad** en cada orden (vía `StockService`) y se repone al cancelar — ya no es puramente decorativo.
- `Product` todavía expone `$appends` legacy (`current_price`, `discount_percentage`, etc., basados en `getCurrentOfferPrice()`) que ignoran las escalas de precio por cantidad; el catálogo público (`ProductController`) ya no los usa y arma su propio prop `pricing` con `PricingService`, pero cualquier código nuevo que toque `Product` directamente debería preferir `PricingService::calcularPrecio()` en vez de esos accessors.
- **La contraseña temporal de un vendedor nuevo se muestra una única vez**, sólo por flash de sesión (`SellerController::store`), sin ningún canal de reenvío: el proyecto no tiene un mailer real configurado (`MAIL_MAILER=log` sólo escribe en el log, no entrega correos). Si el admin cierra el modal sin copiarla o recarga la página antes de pasársela al vendedor, no hay forma de recuperarla — hay que pasar por el flujo de "olvidé mi contraseña".
- **`DiscountCode::code` sólo es editable mientras `usage_count = 0`**, y el borrado también está bloqueado si tiene usos (ver §10) — es una decisión deliberada para no romper el historial de órdenes que lo usaron, pero implica que un código "gastado" con un typo en el texto no se puede corregir; hay que desactivarlo y crear uno nuevo.
- **`CartController::add` no chequea `is_active` del producto** (nunca lo hizo): un producto desactivado que ya está en un catálogo cacheado en el cliente todavía se puede agregar. El checkout igual resuelve stock/precio y el admin ve la orden; no es un bug pero sí una diferencia con la ficha pública, que sí hace `abort(404)` para productos inactivos.
- **El carrito de sesión no valida cambios posteriores del catálogo hasta que se vuelve a mostrar**: si una variante se desactiva o borra mientras hay una línea en el carrito, la línea se descarta al renderizar `GET /carrito` (no hay aviso al usuario, es un carrito efímero). El `line_key` guardado no se re-verifica salvo en ese momento.
- **`Cart/Index.jsx` capea la cantidad de una línea con variante contra `product.stock`, no contra el stock de la variante** (ese dato no se expone en el prop del ítem). El `+` puede quedar habilitado de más; el backend (`update`) igual rechaza el exceso con 422.
