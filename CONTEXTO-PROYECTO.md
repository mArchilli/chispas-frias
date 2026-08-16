# Contexto del proyecto — Chispas Frías

> Documento de referencia interna sobre cómo está armado el backend, la base de datos y el flujo de negocio del sitio. Última actualización: 2026-08-16.

## 1. Qué es el sitio

E-commerce de catálogo para **Chispas Frías** (efectos especiales y pirotecnia para eventos: chispas frías, fuegos artificiales, maquinaria de efectos, humo, pirotecnia, velas/bengalas). El sitio muestra el catálogo, permite armar un carrito y **no cobra online**: el "checkout" arma un pedido con los datos del cliente y genera un mensaje de WhatsApp para que el pedido se cierre por chat. Aparte hay un panel de administración para cargar productos, categorías y ofertas.

## 2. Stack tecnológico

- **Backend**: Laravel 12 (PHP ^8.2)
- **Frontend**: React 18 + [Inertia.js](https://inertiajs.com/) v2 (SPA sin API REST separada: los controllers devuelven `Inertia::render(...)` con props tipadas, no JSON de una API)
- **Estilos**: Tailwind CSS 3 (+ `@tailwindcss/forms`)
- **Build**: Vite 7 + `laravel-vite-plugin`
- **Auth**: Laravel Breeze (stack Inertia/React) — login, registro, verificación de email, reset de contraseña
- **Sesiones/Cache/Queue**: driver `database` (tablas `sessions`, `cache`, `jobs`)
- **Base de datos**: MySQL en producción (`DB_CONNECTION=mysql`), SQLite por defecto en `config/database.php` si no hay `.env`
- **Extras JS**: `framer-motion` (animaciones), `react-quill` (editor de texto enriquecido, probablemente para descripciones), `react-hot-toast` (notificaciones)

No hay API REST pública ni pasarela de pago integrada. Todo el negocio corre dentro de la misma app Laravel+Inertia.

## 3. Estructura de carpetas relevante

```
app/
  Http/Controllers/
    ProductController.php          # catálogo público
    CartController.php             # carrito (basado en sesión) + checkout + WhatsApp
    ProfileController.php          # perfil de usuario autenticado
    Auth/                          # controllers de Breeze
    Admin/
      ProductController.php        # ABM de productos + imágenes/videos
      CategoryController.php       # ABM de categorías (árbol 2 niveles)
      ProductOfferController.php   # ofertas/descuentos "inline" desde el producto
      ProductOfferAdminController.php # vista dedicada de gestión de ofertas
  Models/
    Product.php, Category.php, ProductImage.php, ProductOffer.php, CartItem.php, User.php
database/
  migrations/                      # historial de esquema
  seeders/                         # datos de arranque (categorías reales, admin demo)
resources/js/
  Pages/                           # páginas Inertia (Products, Cart, Admin/*, Auth/*, Profile)
  Components/, Layouts/, hooks/, utils/, config/
routes/
  web.php                          # TODAS las rutas (público + admin), no hay routes/api.php en uso
  auth.php                         # rutas de Breeze
```

## 4. Modelo de datos

### Diagrama de relaciones

```
categories (self-referencing: parent_id → categories.id)
    └─< products (category_id)
            ├─< product_images (product_id)
            └─< product_offers (product_id)

users
    └─< cart_items (user_id)   # tabla existe pero NO se usa (ver sección 6)
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

Categorías principales seedeadas: **Chispa Fría** (subcategorías 2x20, 3x30, 4x30, 5x1, Con mecha), **Fuegos Artificiales** (9/16/32 Tiros), **Maquinaria** (Bastón de Mano, Detonador Inalámbrico, Humo vertical, Lanzallama, Pistola), **Humo** (Bengala, Pote, Torta), **Pirotecnia** (sin subcategorías seedeadas), **Velas** (Bengalas, Sparkie).

### `products`

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| title | string | |
| description | text | |
| price | decimal(10,2) | precio de lista |
| sku | string, unique, nullable | |
| category_id | bigint, FK → categories.id, `onDelete restrict` | no se puede borrar una categoría con productos |
| stock | int, default **9999** | el negocio no gestiona stock real: 9999 es "stock infinito" por defecto |
| is_active | boolean, default true | visibilidad pública |
| is_featured | boolean, default false | usado en el home |
| timestamps | | |

Índices en `(category_id, is_active)`, `price`, `is_featured`.

El modelo `Product` agrega atributos calculados (`appends`) que viajan siempre en el JSON/props de Inertia: `current_price`, `formatted_current_price`, `formatted_offer_price`, `discount_percentage`, `has_active_offer` — todos derivados de la oferta activa (ver `product_offers`), no de columnas propias.

### `product_images`
Soporta **imágenes y videos** de un producto (galería + tipo).

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| product_id | bigint, FK → products.id, `onDelete cascade` | |
| path | string | ver "Manejo de archivos" abajo — formato legacy vs. nuevo |
| alt_text | string, nullable | |
| sort_order | int, default 0 | |
| is_primary | boolean, default false | imagen/video principal mostrado en listados |
| type | enum('image','video'), default 'image' | agregado en migración posterior |
| mime_type | string, nullable | agregado junto con `type` |
| timestamps | | |

### `product_offers`
Historial de ofertas/descuentos por producto (no hay columna de oferta en `products`; el precio de oferta vigente se calcula por consulta).

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| product_id | bigint, FK → products.id, `onDelete cascade` | |
| offer_price | decimal(10,2) | precio final durante la oferta |
| percentage_discount | decimal(5,2), nullable | agregado en migración posterior, informativo |
| start_date | datetime, nullable | `null` = ya empezó |
| end_date | datetime, nullable | `null` = sin fecha de fin |
| is_active | boolean, default true | flag manual además de las fechas |
| timestamps | | |

Una oferta se considera **vigente** (`scopeActive`) si `is_active = true` **y** la fecha actual está dentro de `[start_date, end_date]` (con nulls abiertos). El admin puede tener varias ofertas históricas por producto, pero la lógica de creación/activación siempre desactiva las demás ofertas activas del mismo producto antes de crear/activar una nueva (una oferta activa por producto a la vez).

### `users`, `password_reset_tokens`, `sessions`, `cache`, `jobs`
Tablas estándar de Laravel (autenticación Breeze, sesiones en DB, cache en DB, cola en DB). `users` no tiene roles/permisos: **cualquier usuario autenticado y verificado accede al panel `/admin`** (ver sección de seguridad).

### `cart_items` (existe pero no está en uso)
Migración y modelo (`user_id`, `product_id`, `quantity`, único por `user_id+product_id`) están creados, pero **el carrito real funciona 100% con la sesión HTTP** (`session('cart')`, un array `product_id => quantity`), no con esta tabla. Es carrito de invitado: no requiere login, se pierde si se limpia la sesión.

## 5. Rutas principales (`routes/web.php`)

**Público**
- `GET /` — home, trae destacados (`is_featured`) y productos con oferta activa
- `GET /productos`, `GET /productos/{product}` — catálogo con filtro por categoría/subcategoría y búsqueda por texto, detalle con "productos relacionados" (misma subcategoría → categoría padre → destacados → aleatorios, siempre completa a 3)
- `GET /contacto`, `/servicios`, `/servicios/chispas` — páginas estáticas Inertia
- `carrito/*` — `index`, `checkout`, `add`, `update`, `remove`, `clear`, `count`, `whatsapp` (todas sin login)

**Autenticado** (`middleware auth`)
- `/profile` (editar/borrar cuenta — Breeze)

**Admin** (`middleware auth,verified`, prefijo `/admin`, sin control de rol)
- `/admin/dashboard` — contadores (categorías, productos activos/totales, sin stock)
- `Route::resource('categories', ...)` + `toggle-status`
- `Route::resource('products', ...)` + `toggle-status`, `toggle-featured`, `set-primary-image`
- Ofertas: crear/editar/eliminar/activar-desactivar inline desde el producto (`ProductOfferController`) y una vista dedicada `Route::resource('offers', ...)` (`ProductOfferAdminController`)

No hay `routes/api.php` en uso; toda la comunicación front-back es vía Inertia (navegación) o `axios` a estas mismas rutas web (los endpoints de carrito devuelven JSON si `expectsJson()`).

## 6. Flujo de negocio: carrito → WhatsApp (sin pasarela de pago)

1. El visitante agrega productos: `POST /carrito/agregar` valida stock contra `products.stock` y guarda `{product_id: cantidad}` en la sesión.
2. `GET /carrito/checkout` pide datos de envío (nombre, DNI, dirección, provincia/localidad de Argentina — hardcodeadas en `CartController::checkout()`, teléfono, email, observaciones).
3. `POST /carrito/whatsapp` arma un mensaje de texto formateado (productos, precio original vs. oferta, subtotales, total, datos del cliente) y **vacía la sesión del carrito**. El front redirige al usuario a WhatsApp con ese mensaje prellenado para cerrar la venta por chat.

No se persiste ningún pedido en base de datos: no existe tabla `orders`. El "historial de ventas" no vive en el sistema.

## 7. Manejo de imágenes/videos de productos

Las imágenes/videos se suben por el form de admin (`multipart`, hasta 10 archivos, ≤20MB c/u; formatos: jpg/png/gif/webp + mp4/mov/avi/wmv/flv/webm) y se guardan en el filesystem público, **no en `storage/` de Laravel** ni en un disco S3.

- Carpeta física: `public_path(env('PRODUCT_IMAGES_PATH', '/images/products/'))`. En `.env` actual apunta fuera del proyecto (`/../../public_html/images/products/`), pensado para hosting compartido donde `public_html` es la webroot real.
- `ProductImage->path` guarda solo el nombre de archivo (formato nuevo) o una ruta completa legacy (`/images/products/archivo.jpg`) — `getUrlAttribute()` resuelve ambos casos usando `VITE_PRODUCT_IMAGES_PATH` como prefijo para el formato nuevo.
- Al borrar una imagen o un producto, se intenta `unlink()` del archivo físico además del registro en DB.
- El upload tiene 3 estrategias en cascada (`move()` → `copy()` → `file_get_contents`/`file_put_contents`) por problemas previos de permisos/entornos, con logging extenso (`Log::info/warning/error`) en cada paso.

## 8. Autenticación y roles

Breeze estándar (login, registro, verificación de email, "recordar contraseña", confirmación de password). **No hay tabla ni columna de roles** — el gate para entrar a `/admin/*` es únicamente `auth` + `verified` (cualquier usuario registrado y con email verificado entra al panel). Si se necesita restringir el panel a ciertos usuarios, hoy no hay mecanismo para eso.

## 9. Variables de entorno relevantes (más allá de las típicas de Laravel)

| Variable | Uso |
|---|---|
| `PRODUCT_IMAGES_PATH` | carpeta física (server-side) donde se guardan/leen imágenes y videos de productos |
| `VITE_PRODUCT_IMAGES_PATH` | prefijo de URL pública para imágenes con el "formato nuevo" (solo nombre de archivo) |
| `DB_CONNECTION` / `DB_*` | MySQL en producción |
| `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database` | todo corre sobre la misma DB, sin Redis en uso pese a estar configurado |

## 10. Cosas a tener en cuenta / deuda técnica

- **`cart_items` (tabla + modelo) no se usa** — el carrito es 100% de sesión. Si en algún momento se quiere carrito persistente por usuario logueado, ya está el modelo pero falta cablearlo en `CartController`.
- **No hay control de roles/permisos** en el panel admin; cualquier cuenta verificada tiene acceso total (crear/editar/borrar productos, categorías, ofertas).
- **No hay tabla de pedidos**: el flujo de compra termina en un mensaje de WhatsApp, no queda registro de ventas en el sistema.
- **Credencial hardcodeada en el seeder**: `database/seeders/DatabaseSeeder.php` crea un usuario admin de demo con contraseña en texto plano dentro del código fuente. Si ese seeder corrió alguna vez contra la base de producción (o el repo es público/compartido), conviene rotar esa contraseña y mover el valor a una variable de entorno o generarla al azar.
- El stock es en la práctica decorativo (default 9999): el negocio no maneja inventario real, solo usa `stock <= 0` para marcar "sin stock" manualmente si hace falta.
