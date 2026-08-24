# Contexto del proyecto — Chispas Frías

> Documento de referencia para dar contexto completo del sitio (qué es, cómo está armado, cómo funciona cada parte) a asistentes de IA (Claude) en futuras sesiones de trabajo. Generado a partir de una lectura completa del código el 2026-08-24, y actualizado el 2026-08-24 para incorporar el sistema de códigos de descuento y el sistema de roles/cuentas de vendedor agregados después de la versión original.

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
      SellerController.php               # ABM de cuentas de vendedor (solo admin)
      Concerns/SyncsOfferDiscountFields.php  # trait compartido por los dos controllers de ofertas
  Models/
    Product.php, Category.php, ProductImage.php, ProductOffer.php, ProductPriceTier.php,
    Order.php, OrderItem.php, StockMovement.php, Setting.php, CartItem.php (sin uso), User.php,
    DiscountCode.php
  Services/
    PricingService.php                   # resuelve precio final (tier + oferta) — fuente de verdad de precios
    StockService.php                     # descuenta/repone stock con locking transaccional
    PriceResult.php                      # DTO de salida de PricingService
    DiscountCodeService.php              # valida/aplica/repone códigos de descuento — fuente de verdad de descuentos por código
  Enums/
    AlcanceOferta.php, EstadoOrden.php, MotivoMovimientoStock.php, TipoDescuento.php, RolUsuario.php
  Exceptions/
    StockInsuficienteException.php, DiscountCodeInvalidoException.php
  Support/
    Provincias.php                       # catálogo de provincias/ciudades AR para el checkout
database/
  migrations/                            # historial de esquema
  seeders/                                # datos de arranque (categorías reales, admin demo)
resources/js/
  Pages/                                  # páginas Inertia (Products, Cart, Admin/*, Auth/*, Profile)
    Admin/DiscountCodes/                 # ABM de códigos de descuento (Index, Create, Edit)
    Admin/Sellers/                       # ABM de cuentas de vendedor (Index, Create, Edit)
  Components/, Layouts/, hooks/, config/
    Components/Cart/DiscountCodeField.jsx  # input aplicar/quitar código, compartido por Cart/Index y Cart/Checkout
    hooks/usePermissions.js              # lee auth.user.role compartido por Inertia (isAdmin/isVendedor/canBorrarCatalogo)
  utils/
    pricing.js                           # espejo en JS de PricingService (solo preview client-side)
    stock.js                             # umbral de stock bajo compartido (LOW_STOCK_THRESHOLD = 3)
    orders.jsx                           # labels/badges de estado de orden
    discountCodes.js                     # vista previa en vivo del texto del descuento en Create/Edit (front)
routes/
  web.php                                 # TODAS las rutas (público + admin), no hay routes/api.php en uso
  auth.php                                # rutas de Breeze
```

## 4. Modelo de datos

### Diagrama de relaciones

```
categories (self-referencing: parent_id → categories.id)
    └─< products (category_id)
            ├─< product_images (product_id)
            ├─< product_offers (product_id) ──> product_price_tiers (product_price_tier_id, nullable)
            ├─< product_price_tiers (product_id)
            └─< stock_movements (product_id)

orders (user_id nullable → users.id, discount_code_id nullable → discount_codes.id)
    └─< order_items (order_id, product_id nullable)
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
| province, city, address, number, between_streets, postal_code | string (city/between_streets nullable) | dirección de entrega |
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
| product_title | string | copia del título al momento de la compra |
| cantidad | unsignedInteger | |
| precio_unitario | decimal(10,2) | precio final ya resuelto por `PricingService` (con tier+oferta aplicados) |
| subtotal | decimal(10,2) | |
| timestamps | | |

### `stock_movements` (auditoría de stock)

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| product_id | bigint, FK → products.id, `cascade` | |
| order_id | bigint, nullable, FK → orders.id, `set null` | puede no venir de una orden (ajuste manual) |
| cantidad | integer | negativo = descuento, positivo = reposición |
| motivo | string, cast a enum `MotivoMovimientoStock` | `orden_creada` \| `orden_cancelada` \| `ajuste_manual` |
| stock_resultante | integer | snapshot del stock del producto después del movimiento |
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
El carrito real funciona 100% con la sesión HTTP (`session('cart')`, array `product_id => quantity`). Es carrito de invitado, no requiere login.

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
- `PriceResult` es un DTO readonly con: `precioLista`, `precioUnitarioFinal`, `ofertaAplicada`, `tierAplicado`, `ahorroUnitario`, `ahorroPorcentaje`.

**Espejo en frontend**: `resources/js/utils/pricing.js` reimplementa la misma lógica en JS, pero **solo para preview instantáneo en el cliente** (pills de precio por cantidad en la ficha de producto, preview de descuento en el admin). El precio que efectivamente se cobra siempre se recalcula en el backend (agregar al carrito, actualizar cantidad, generar el pedido) — la duplicación nunca determina un monto real.

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
- `descontar(Order $order)`: dentro de una transacción, hace `lockForUpdate()` sobre los productos de la orden (siempre en el mismo orden — por `product_id` — para evitar deadlocks entre transacciones concurrentes), revalida stock **bajo lock** (chequeo definitivo, no confía en `validarDisponibilidad`), descuenta y registra un `StockMovement` (`motivo = orden_creada`). Todo o nada: si un item falla, se aborta todo (`StockInsuficienteException`).
- `reponer(Order $order)`: repone stock al cancelar una orden. Protegido contra doble ejecución: dentro del lock, chequea si ya existe un `StockMovement` con `motivo = orden_cancelada` para esa orden antes de reponer.

## 8. Rutas principales (`routes/web.php`)

**Público**
- `GET /` — home, trae destacados (`is_featured`) y productos con oferta activa
- `GET /productos`, `GET /productos/{product}` — catálogo con filtro por categoría/subcategoría y búsqueda, detalle con "productos relacionados" (misma subcategoría → categoría padre → destacados → aleatorios, siempre completa a 3)
- `GET /contacto`, `/servicios`, `/servicios/chispas` — páginas estáticas Inertia
- `carrito/*` — `index`, `checkout`, `add`, `update`, `remove`, `clear`, `count`, `whatsapp` (todas sin login)

**Autenticado** (`middleware auth`)
- `/profile` (editar/borrar cuenta — Breeze)

**Admin** (`middleware auth,verified,can:acceder-panel-admin`, prefijo `/admin` — ver §12 para los Gates y qué controlan)
- `/admin/dashboard` — KPIs (ver §10)
- `Route::resource('categories', ...)` + `toggle-status` (`destroy` reservado a `can:borrar-catalogo`)
- `Route::resource('products', ...)` + `toggle-status`, `toggle-featured`, `set-primary-image` (`destroy` reservado a `can:borrar-catalogo`)
- Ofertas: modal rápido desde el producto (`ProductOfferController`: store/update/destroy/toggle/quick-offer) y vista dedicada `Route::resource('offers', ...)` (`ProductOfferAdminController`) + `toggle-status` (ambos `destroy` reservados a `can:borrar-catalogo`)
- `discount-codes` — `Route::resource(...)` (index/create/store/edit/update/destroy) + `toggle-status` (`destroy` reservado a `can:borrar-catalogo`)
- `orders` — `index` (cola operativa + métricas del mes, éstas últimas solo si `isAdmin()`), `show` (detalle), `orders/{order}/status` (cambio de estado, sin gate — vendedor puede transicionar incluida la cancelación)
- `settings` — `edit`/`update` (umbral de envío gratis), bajo `can:gestionar-configuracion` (solo admin)
- `sellers` — `Route::resource(...)->except(['show', 'destroy'])` + `toggle-status`, bajo `can:gestionar-vendedores` (solo admin)

No hay `routes/api.php` en uso; toda la comunicación front-back es vía Inertia (navegación) o `axios`/`fetch` a estas mismas rutas web (los endpoints de carrito devuelven JSON si `expectsJson()`).

## 9. Flujo de negocio: carrito → orden → WhatsApp

1. El visitante agrega productos: `POST /carrito/agregar` valida stock contra `products.stock` y guarda `{product_id: cantidad}` en la sesión.
2. `GET /carrito` y `GET /carrito/checkout` resuelven cada línea con `PricingService::calcularPrecio()` según la cantidad real (no un precio plano por producto) — así el usuario ve el precio con tier/oferta ya aplicados antes de confirmar. Si hay un código de descuento válido en sesión (`cart_discount_code`), ambas vistas también muestran el desglose subtotal → descuento → total (ver §6).
3. `GET /carrito/checkout` pide datos de envío (nombre, DNI, dirección, provincia/localidad de Argentina desde `App\Support\Provincias`, teléfono, email, observaciones). Muestra `FreeShippingProgress` si hay un `free_shipping_threshold` configurado.
4. `POST /carrito/descuento` (`CartController::applyDiscountCode`) / `DELETE /carrito/descuento` (`removeDiscountCode`): aplican o quitan un código, validando siempre contra la DB vía `DiscountCodeService::buscarValido()`. Solo el texto del código se persiste en sesión; el monto se recalcula siempre server-side.
5. `POST /carrito/whatsapp` (`CartController::generateWhatsAppMessage`):
   - Valida los datos de contacto (obligatorios, se van a persistir en `orders`).
   - Chequeo optimista de stock (`StockService::validarDisponibilidad`); si falta algo, devuelve 422 con el detalle.
   - Chequeo optimista del código de descuento en sesión, si hay uno (`DiscountCodeService::buscarValido()`); si dejó de ser válido, lo quita de la sesión y devuelve 422.
   - Arma el mensaje de texto (productos, precio original vs. final si hubo ahorro, subtotales, línea de código de descuento si aplica, total, datos del cliente).
   - Dentro de una transacción: crea el `Order` + sus `OrderItem` (con el precio ya resuelto por `PricingService`), llama a `StockService::descontar()` (chequeo definitivo bajo lock + `StockMovement`), y si hay código de descuento, llama a `DiscountCodeService::registrarUso()` (chequeo definitivo bajo lock — si el código se agotó por una carrera con otro checkout concurrente, aborta toda la transacción, orden y descuento de stock incluidos).
   - Si algo falla (incluida `StockInsuficienteException` o `DiscountCodeInvalidoException`), no se crea nada y se informa el error.
   - Si todo sale bien, vacía la sesión del carrito (incluido `cart_discount_code`) y devuelve `{ success, message, subtotal, discount_amount, total, order_id }`.
6. El frontend redirige al usuario a `https://wa.me/5491178886833?text=...` con el mensaje prellenado (número de WhatsApp hardcodeado en `Cart/Checkout.jsx`). El pedido queda registrado en el sistema con estado `pendiente`, aunque el cierre de venta sigue pasando por chat manual.

**Estados de una orden** (`EstadoOrden`): `pendiente → despachado | cancelado`; `despachado → pendiente`; `cancelado` es terminal (no transiciona a nada). Solo la transición `pendiente → cancelado` repone stock (vía `StockService::reponer`) y, si la orden tenía un código de descuento, repone su uso (vía `DiscountCodeService::reponerUso()`, protegido por `orders.discount_usage_repuesto`) — ambas gestionadas desde `Admin/OrderController::updateStatus`.

## 10. Panel de administración

- **Dashboard** (`Admin/DashboardController`): los KPIs operativos (categorías, productos, stock, ofertas activas, códigos de descuento activos, pedidos pendientes) se calculan para admin y vendedor por igual; los financieros (ingresos del mes, ticket promedio, últimos 5 pedidos con monto) son exclusivos de `isAdmin()` — para un vendedor esas queries ni se corren y las props ni se envían (ver §12).
- **Categorías**: árbol de 2 niveles, no se puede borrar una categoría con productos o subcategorías. Borrado reservado al Gate `borrar-catalogo` (solo admin).
- **Productos**: ABM completo + subida de imágenes/videos (hasta 10 archivos, ≤20MB c/u) + gestión de escalas de precio (`price_tiers`, sync completo en cada guardado: borra las que no vienen, crea/actualiza el resto). Borrado reservado a `borrar-catalogo`.
- **Ofertas**: dos puntos de entrada (modal rápido desde el producto, y vista dedicada `Admin/Offers`) comparten el trait `SyncsOfferDiscountFields` para las mismas reglas de validación y el mismo cálculo de campos espejo. El listado dedicado muestra estado calculado (`activa` / `programada` / `expirada` / `inactiva`) y descuento promedio de las vigentes. Borrado reservado a `borrar-catalogo`.
- **Códigos de descuento** (`Admin/DiscountCodeController`, ver §6): listado con filtro por estado calculado (`activo`/`programado`/`expirado`/`inactivo`/`agotado`) y búsqueda por código; `code` sólo editable mientras `usage_count = 0` (una vez usado, renombrarlo confundiría el historial); borrado también bloqueado si tiene usos (`usage_count > 0`) además de estar reservado a `borrar-catalogo` — la vía recomendada para dejar de ofrecerlo es desactivarlo.
- **Vendedores** (`Admin/SellerController`, ver §12): ABM exclusivo de admin (Gate `gestionar-vendedores`) sobre cuentas `role = vendedor`. No tiene `destroy`: se reemplaza por activar/desactivar. Alta genera una contraseña temporal random que se muestra una única vez en un modal (flash de sesión `temporaryPassword`) para que el admin se la pase al vendedor a mano.
- **Pedidos** (`Admin/OrderController`): cola operativa filtrable por estado (default `pendiente`) y búsqueda por nombre/apellido/DNI/email, visible para admin y vendedor por igual (incluida la posibilidad de cambiar estado, incluida la cancelación con reposición de stock y de uso de código); detalle con transiciones de estado válidas y monto completo de la orden (subtotal, descuento, total) visible para ambos roles; el panel de métricas de negocio navegable por mes (ingresos, ticket promedio, producto más vendido, top 3 provincias, gráfico diario de pedidos/ingresos) es exclusivo de `isAdmin()`. Nunca deja navegar a un mes futuro.
- **Configuración** (`Admin/SettingController`): por ahora solo `free_shipping_threshold`, usado por `FreeShippingProgress.jsx` en carrito y checkout para mostrar una barra de progreso hacia "envío gratis" — **es solo informativo/de marketing**, el costo de envío real siempre se coordina manualmente con el cliente por WhatsApp (no hay cálculo de envío real). Exclusivo de admin (Gate `gestionar-configuracion`).

## 11. Manejo de imágenes/videos de productos

Las imágenes/videos se suben por el form de admin (`multipart`, hasta 10 archivos, ≤20MB c/u; formatos: jpg/png/gif/webp + mp4/mov/avi/wmv/flv/webm) y se guardan en el filesystem público, **no en `storage/` de Laravel** ni en un disco S3.

- Carpeta física: `public_path(env('PRODUCT_IMAGES_PATH', '/images/products/'))`. En `.env` actual apunta fuera del proyecto (`/../../public_html/images/products/`), pensado para hosting compartido donde `public_html` es la webroot real.
- `ProductImage->path` guarda solo el nombre de archivo (formato nuevo) o una ruta completa legacy — `getUrlAttribute()` resuelve ambos casos usando `VITE_PRODUCT_IMAGES_PATH` como prefijo para el formato nuevo; `getFilesystemPath()` resuelve la ruta física para borrar el archivo.
- Al borrar una imagen o un producto, se intenta `unlink()` del archivo físico además del registro en DB.
- El upload tiene 3 estrategias en cascada (`move()` → `copy()` → `file_get_contents`/`file_put_contents`) por problemas previos de permisos/entornos, con logging extenso (`Log::info/warning/error`) en cada paso.

## 12. Autenticación y roles

Breeze estándar (login, registro, verificación de email, "recordar contraseña", confirmación de password, borrado de cuenta con reconfirmación de password), sobre el que se montó un sistema de roles simple con tres valores (`App\Enums\RolUsuario`: `admin` \| `vendedor` \| `cliente`, columna `users.role`).

**Gates** (definidos en `AppServiceProvider::boot()`):

| Gate | Quién pasa | Qué controla |
|---|---|---|
| `acceder-panel-admin` | admin, vendedor | entrada a `/admin/*` (middleware de todo el grupo de rutas admin) |
| `borrar-catalogo` | solo admin | `destroy` de categorías, productos, ofertas (ambos controllers) y códigos de descuento |
| `gestionar-configuracion` | solo admin | `/admin/settings` (edición del umbral de envío gratis) |
| `gestionar-vendedores` | solo admin | `/admin/sellers` (ABM de cuentas de vendedor) |

`User::puedeAccederAlPanel()` (`isAdmin() || isVendedor()`) es lo que respalda el primer Gate; `User::isAdmin()`, `isVendedor()`, `isCliente()` respaldan el resto.

**Qué puede hacer un vendedor**: entrar a `/admin` y a Dashboard, Categorías, Productos, Ofertas, Códigos de descuento y Pedidos; crear, editar y activar/desactivar en las cuatro secciones de catálogo; ver y cambiar el estado de cualquier pedido — **incluida la cancelación**, con el monto completo de la orden visible.

**Qué NO puede hacer un vendedor**: borrar categorías, productos, ofertas o códigos de descuento (Gate `borrar-catalogo`, HTTP 403); ver las métricas financieras del Dashboard ni del listado de Pedidos (ingresos, ticket promedio, últimos pedidos, gráfico diario — esas props ni se calculan ni se envían, ver §10); entrar a Configuración ni a Vendedores (403).

**Registro y alta de cuentas**:
- El registro público (`RegisteredUserController`) siempre crea `role = cliente` — nunca se puede llegar a `admin`/`vendedor` por `/register`.
- Las cuentas de vendedor **sólo las crea un admin**, desde `/admin/sellers` (`SellerController::store`): genera una contraseña temporal aleatoria (`Str::password(12)`), marca `email_verified_at = now()` (el admin ya validó la dirección a mano) y devuelve la contraseña una única vez por flash de sesión (`temporaryPassword`) para que el admin se la pase al vendedor.
- No hay `destroy` de vendedor: `toggleStatus` desactiva la cuenta (`is_active = false`), lo que además cierra cualquier sesión abierta (`DB::table('sessions')->where('user_id', ...)->delete()`) sin borrar el historial de pedidos que gestionó.
- `LoginRequest::authenticate()` rechaza el login de una cuenta con `is_active = false` **después** de validar la contraseña (para no filtrar por timing si la cuenta existe), con logout inmediato de la sesión recién creada.

**Frontend**: `resources/js/hooks/usePermissions.js` lee `auth.user.role` (compartido por Inertia vía `HandleInertiaRequests`) y expone `{ role, isAdmin, isVendedor, canBorrarCatalogo }` para gatear qué se muestra — el backend (Gates) es la fuente real de verdad, esto sólo evita mostrar acciones que van a devolver 403. `AdminLayout.jsx` usa esto para ocultar "Envío gratis" y "Vendedores" del nav a un vendedor, y muestra un badge de rol (`Admin`/`Vendedor`) junto al nombre del usuario en el sidebar/dropdown.

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
- **Roles y vendedores**: `RolePermissionsTest` (los 4 Gates, acceso por rol a cada sección, registro público siempre `cliente`), `AdminSellerTest` (ABM de vendedores, contraseña temporal, activar/desactivar), `AdminOrderVendorVisibilityTest` (qué props financieras se ocultan a un vendedor en Dashboard y en Pedidos).
- **Carrito/WhatsApp**: `CartWhatsAppOrderTest`.
- **Perfil**: `ProfileTest`.

También hay un harness de concurrencia dedicado en `tests/concurrency/` (bootstrap/setup/worker) para probar `StockService` bajo carga simultánea real.

## 16. Cosas a tener en cuenta / deuda técnica

- **`cart_items` (tabla + modelo) no se usa** — el carrito es 100% de sesión. Si se quisiera carrito persistente por usuario logueado, el modelo ya existe pero falta cablearlo en `CartController`.
- **El número de WhatsApp de destino está hardcodeado** en `resources/js/Pages/Cart/Checkout.jsx` (`5491178886833`), no es configurable desde `settings` ni desde `.env`.
- **El envío gratis es solo un indicador visual**: `free_shipping_threshold` no calcula ni cobra costo de envío real; el envío efectivo siempre se coordina por WhatsApp después del pedido.
- **Credencial hardcodeada en el seeder**: `database/seeders/DatabaseSeeder.php` crea un usuario admin de demo con contraseña en texto plano dentro del código fuente. Si ese seeder corrió alguna vez contra la base de producción (o el repo es público/compartido), conviene rotar esa contraseña y mover el valor a una variable de entorno o generarla al azar.
- El stock por defecto es 9999 ("infinito") pero, a diferencia de una versión anterior del proyecto, **ahora sí se descuenta de verdad** en cada orden (vía `StockService`) y se repone al cancelar — ya no es puramente decorativo.
- `Product` todavía expone `$appends` legacy (`current_price`, `discount_percentage`, etc., basados en `getCurrentOfferPrice()`) que ignoran las escalas de precio por cantidad; el catálogo público (`ProductController`) ya no los usa y arma su propio prop `pricing` con `PricingService`, pero cualquier código nuevo que toque `Product` directamente debería preferir `PricingService::calcularPrecio()` en vez de esos accessors.
- **La contraseña temporal de un vendedor nuevo se muestra una única vez**, sólo por flash de sesión (`SellerController::store`), sin ningún canal de reenvío: el proyecto no tiene un mailer real configurado (`MAIL_MAILER=log` sólo escribe en el log, no entrega correos). Si el admin cierra el modal sin copiarla o recarga la página antes de pasársela al vendedor, no hay forma de recuperarla — hay que pasar por el flujo de "olvidé mi contraseña".
- **`DiscountCode::code` sólo es editable mientras `usage_count = 0`**, y el borrado también está bloqueado si tiene usos (ver §10) — es una decisión deliberada para no romper el historial de órdenes que lo usaron, pero implica que un código "gastado" con un typo en el texto no se puede corregir; hay que desactivarlo y crear uno nuevo.
