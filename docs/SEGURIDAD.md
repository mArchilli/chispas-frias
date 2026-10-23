# Seguridad de la web Chispas Frías

Qué protege la web, cómo, cómo se comprueba y qué hacer cuando algo sale mal. Es la referencia de la auditoría **S.2** (octubre de 2026, rama `seguridad-s2`). Lo que hay que
hacer en el servidor el día del deploy está en [`PUESTA-EN-MARCHA-CHISPAS-FRIAS.md`](PUESTA-EN-MARCHA-CHISPAS-FRIAS.md) → **Paso 4b**. La política de privacidad (propuesta, sin publicar)
está en [`PRIVACIDAD-PROPUESTA.md`](PRIVACIDAD-PROPUESTA.md). El Sistema Central tiene su propio documento: `docs/SEGURIDAD.md` de `sistema-rukoz`.

La web es una **tienda pública** (se compra sin cuenta: el pedido sale por el checkout y se sigue por WhatsApp) en un **hosting compartido de Hostinger**, con un **panel** para admin y vendedores,
integrada con el Sistema Central. Guarda datos de clientes (nombre, teléfono, DNI, mail, dirección), pedidos y montos. Prioridades, en este orden:

1. **Que nadie pueda ejecutar código en el servidor ni publicar contenido que corra en el navegador de los visitantes** (subidas, HTML del editor).
2. **Datos de clientes**: que no se lean desde afuera, que no queden en logs, que el panel los muestre solo a quien corresponde.
3. **Integridad de pedidos y stock**: lo que viene del cliente nunca decide precios, totales, estados ni campos de la integración.
4. **Resistencia a abuso**: fuerza bruta, bots, enumeración de cuentas.

## Índice

1. [Mapa de lo que hay](#mapa-de-lo-que-hay)
2. [Quién puede qué (matriz rol × ruta)](#quién-puede-qué)
3. [Autenticación, contraseñas y sesiones](#autenticación-contraseñas-y-sesiones)
4. [Cabeceras, CSP y `.htaccess`](#cabeceras-csp-y-htaccess)
5. [HTML del editor (XSS)](#html-del-editor-xss)
6. [Subidas de archivos y los PDFs de Documentos](#subidas-de-archivos-y-los-pdfs-de-documentos)
7. [Inyección SQL, CSRF, asignación masiva, redirecciones, exportaciones](#otras-superficies)
8. [Integración con el Sistema Central](#integración-con-el-sistema-central) · [Rotación de secretos](#rotación-de-secretos-paso-a-paso)
9. [Datos personales: relevamiento](#datos-personales-relevamiento)
10. [Qué hacer ante un incidente](#qué-hacer-ante-un-incidente)
11. [Propuestas pendientes (decide Matias)](#propuestas-pendientes)
12. [Cómo se comprueba: tests y comandos](#cómo-se-comprueba)
13. [Registro de la auditoría S.2](#registro-de-la-auditoría-s2)

---

## Mapa de lo que hay

| Capa | Qué hace | Dónde |
|---|---|---|
| Acceso | `auth` + Gate `acceder-panel-admin` en todo `/admin`; Gates propios para lo que es solo de admin; un test falla si una ruta nueva del panel no está clasificada | `routes/web.php`, `AppServiceProvider::boot`, `MatrizDeAccesoTest` |
| Sesiones | Base de datos, 120 min; cierre por inactividad en el panel (60 min, configurable); se cierran al cambiar contraseña / rol o desactivar la cuenta | `CerrarSesionPorInactividad`, `EnsureUserIsActive`, `AuthenticateSession`, `SesionesService`, `User::booted` |
| Abuso | Throttle por IP en login, registro, recuperar contraseña, checkout, cupones y reporte de CSP; bloqueo por email+IP en el login | `AppServiceProvider::definirLimitesDeRequests`, `LoginRequest`, `config/seguridad.php`, `config/tienda.php` |
| Navegador | CSP con nonce (modo **solo reportar**), HSTS, `X-Frame-Options`, `nosniff`, `Referrer-Policy`, `Permissions-Policy` | `SecurityHeaders`, `public/.htaccess` |
| HTML | Lista blanca de etiquetas y atributos, en el servidor, al guardar y al leer | `App\Support\HtmlSanitizer`, `Product::description` |
| Subidas | Extensión según el contenido, nombre aleatorio, `.htaccess` sin PHP y con lista blanca de extensiones | `App\Support\CarpetaDeSubidas`, trait `StoresPublicUploads` |
| Archivos sensibles | `.htaccess` que bloquea ocultos, carpetas internas, respaldos y logs; comando que lo verifica desde afuera | `public/.htaccess`, `chispasfrias:seguridad-verificar` |
| Integración | Webhook firmado sobre el body crudo (±300 s), rotación de secretos, TLS exigido, log del paquete sin payload | paquete `rukoz/core-client`, `SeguridadDelCentral`, `IntegracionEstado` |
| Logs | Filtro que tapa mails y números largos (DNI, teléfonos, tarjetas) en `laravel.log` | `App\Logging\FormateadorSinDatosPersonales` |
| Errores | `APP_DEBUG=false` en producción: páginas genéricas, sin trazas; 404 real | `config/app.php`, `routes/web.php` (fallback), `ErroresYDatosExpuestosTest` |

---

## Quién puede qué

Cuatro actores: **invitado** (cualquiera, sin cuenta: compra sin registrarse), **cliente** (cuenta registrada: hoy no tiene ninguna pantalla propia salvo su perfil), **vendedor** y **admin**.
El rol está en `users.role` (`cliente` por defecto; admin y vendedor solo se crean desde el panel o por consola) y las cuentas se pueden desactivar (`users.is_active`).

| Área | Invitado | Cliente | Vendedor | Admin |
|---|:-:|:-:|:-:|:-:|
| Tienda pública: catálogo, ficha, carrito, checkout, cupones | ✓ | ✓ | ✓ | ✓ |
| Perfil propio (`/profile`: nombre, mail, contraseña, borrar cuenta) | · | ✓ | ✓ | ✓ |
| Panel: dashboard, pedidos (listado, ficha, cambiar estado, **confirmar venta**), precios | · | · | ✓ | ✓ |
| Panel: métricas financieras del dashboard y de pedidos | · | · | · | ✓ |
| Categorías, ofertas, códigos de descuento: ver / crear / editar | · | · | ✓ | ✓ |
| Categorías, ofertas, códigos de descuento: **borrar** (Gate `borrar-catalogo`) | · | · | · | ✓ |
| Productos, add-ons, combos: todo (Gate `gestionar-productos`) | · | · | · | ✓ |
| Configuración y planes de pago con tarjeta (Gate `gestionar-configuracion`) | · | · | · | ✓ |
| Vendedores: alta, edición, activar / desactivar (Gate `gestionar-vendedores`) | · | · | · | ✓ |
| Documentos: ver los activos | · | · | ✓ | ✓ |
| Documentos: alta, edición, baja, activar (Gate `gestionar-documentos`) | · | · | · | ✓ |
| `/rukoz/webhook` | solo el Central, con firma | | | |
| `/csp-report` | el navegador del visitante (sin sesión; límite por IP) | | | |

**Cómo se mantiene.** `tests/Feature/Seguridad/MatrizDeAccesoTest.php` tiene la tabla de arriba en código: cada ruta `admin.*` tiene que declarar `auth` + `can:acceder-panel-admin`,
las que son solo de admin llevan su Gate, y **una ruta nueva sin clasificar hace fallar el test** (hay que decidir quién la puede usar). Además, con usuarios reales: el cliente recibe 403 en todo `/admin`,
el vendedor 403 en todo lo que es de admin (sin borrar nada), el invitado va al login.

**IDOR / «mis pedidos».** No existe una pantalla de pedidos para el cliente: no hay ninguna ruta pública con un id de pedido (un test lo vigila: si algún día se agrega «mis pedidos», hay que revisar que un cliente no pueda leer el
de otro cambiando el id). El carrito vive en la sesión del visitante, no en un id. El panel (pedidos, ficha, confirmar) es de admin y vendedor; **el vendedor ve todos los pedidos** (con DNI, teléfono y dirección), no solo los suyos: es la operación
actual (despacha cualquiera), pero es una decisión para revisar si el equipo crece (ver [Propuestas](#propuestas-pendientes)).

---

## Autenticación, contraseñas y sesiones

| Qué | Cómo |
|---|---|
| Hash de contraseñas | bcrypt (`BCRYPT_ROUNDS=12`), cast `hashed` de Eloquent. Nunca texto plano. La contraseña temporal de un vendedor nuevo se muestra una vez al admin y no se guarda. |
| Política | **Admin y vendedor:** mínimo 10 caracteres, mayúsculas + minúsculas + números (`SEGURIDAD_PASSWORD_STAFF_MINIMO`); se aplica al cambiarla en el perfil y al restablecerla por mail (`PoliticaDePassword`). **Cliente:** la de siempre (8 caracteres) para no cambiar lo que ve quien se registra. |
| Recuperación | Token de un solo uso con vencimiento de 60 min (`config/auth.php` → `passwords.users.expire`), un pedido por minuto por cuenta (`throttle`). La pantalla responde **lo mismo exista o no el mail** («Si el correo está registrado, te enviamos un enlace…»). |
| Login | Error genérico igual para «mail inexistente» y «clave incorrecta». Bloqueo de 5 intentos por mail+IP (`LoginRequest`) y **20 por minuto por IP** (`THROTTLE_LOGIN_POR_MINUTO`). Regenera el id de sesión al entrar. |
| Registro | 5 por minuto por IP (`THROTTLE_REGISTRO_POR_MINUTO`). Siempre crea un **cliente** (el rol, `is_active` y `rukoz_*` no se leen del request: un test lo prueba). **El registro sigue abierto:** ver [Propuestas](#propuestas-pendientes) (bots). |
| Cuentas desactivadas | No pueden entrar y, si tenían la sesión abierta, el siguiente request las saca (`EnsureUserIsActive`), con cualquier driver de sesión. |
| Cerrar sesiones | Al **cambiar la contraseña** se cierran todas las demás sesiones de la cuenta (la actual sigue); al **cambiarse el rol** o **desactivar la cuenta**, todas. Se cierran borrando sus filas de `sessions` (driver `database`) y, además, `AuthenticateSession` invalida cualquier sesión cuyo hash de contraseña ya no coincida (sirve con cualquier driver). |
| Inactividad | En `/admin`, 60 minutos sin pedir nada cierran la sesión (`SEGURIDAD_ADMIN_INACTIVIDAD_MINUTOS`; `0` = sin límite adicional). La sesión general dura `SESSION_LIFETIME=120`. |
| Cookie | `HttpOnly`, `SameSite=Lax`; **`SESSION_SECURE_COOKIE=true` en producción** (la verifica `chispasfrias:seguridad-verificar`). Recomendado también `SESSION_ENCRYPT=true`. |

**Lo que NO cubre:** no hay 2FA (fuera de esta etapa). El mensaje de registro «el correo ya está en uso» sí permite saber si un mail tiene cuenta (es el comportamiento estándar de un registro abierto; la salida es cerrar o proteger el registro, ver Propuestas).

---

## Cabeceras, CSP y `.htaccess`

### Qué manda la app (`SecurityHeaders`, todas las respuestas)

| Cabecera | Valor |
|---|---|
| `Content-Security-Policy-Report-Only` | ver abajo (**modo solo reportar**: no bloquea nada) |
| `Strict-Transport-Security` | `max-age=31536000`, solo si la petición llegó por HTTPS; **sin** `includeSubDomains` ni `preload` (el dominio puede tener subdominios sin HTTPS: correo, panel del hosting). `SEGURIDAD_HSTS_SUBDOMINIOS=true` lo agrega. |
| `X-Frame-Options` | `DENY` (y `frame-ancestors 'none'` en la CSP) |
| `X-Content-Type-Options` | `nosniff` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | cámara, micrófono, geolocalización, pagos, USB, etc. desactivados |
| `Cross-Origin-Opener-Policy` | `same-origin` |
| `X-Robots-Tag` | `noindex` en `/admin`, login, registro, recuperar contraseña y perfil |

### La CSP, relevada contra lo que usa la tienda

Se revisó todo lo que carga el front antes de escribirla: el JS y CSS propios (Vite, con nonce), imágenes y videos propios (`public_html/images`, `/videos`), la fuente **Poppins de `fonts.bunny.net`** (hoja de estilos y archivos de fuente), el script
inline de **Ziggy** (`@routes`, con nonce) y los `<script type="application/ld+json">` de SEO (no son ejecutables, la CSP no los toca). **No hay** analytics, píxeles, Mercado Pago, iframes ni videos incrustados. WhatsApp
(`wa.me`, `api.whatsapp.com`) e Instagram son **enlaces** (navegaciones): la CSP no los limita.

```
default-src 'self'
script-src  'self' 'nonce-…'
style-src   'self' 'unsafe-inline' https://fonts.bunny.net   ← 'unsafe-inline' solo para estilos: React escribe atributos style (no admiten nonce)
img-src     'self' data: blob:        font-src 'self' data: https://fonts.bunny.net
connect-src 'self'                    media-src 'self' blob:        worker-src 'self' blob:        manifest-src 'self'
object-src 'none'   frame-src 'none'   frame-ancestors 'none'   base-uri 'self'   form-action 'self'
+ upgrade-insecure-requests (si es HTTPS) + report-uri /csp-report
```

El JavaScript inline sin nonce y el `eval` **no** están permitidos. Si más adelante se suma algo de terceros (Google Analytics, un píxel de Meta, Mercado Pago…) **no se toca código**: se agrega el origen en el `.env`
(`SEGURIDAD_CSP_EXTRA_SCRIPT`, `_STYLE`, `_IMG`, `_FONT`, `_CONNECT`, `_FRAME`, separados por coma).

### Modo solo reportar → modo bloqueo

`SEGURIDAD_CSP` = `report-only` (por defecto) | `enforce` | `off`. En `report-only` el navegador avisa de cada violación a `POST /csp-report`, que la deja en `storage/logs/seguridad.log` **sin la query de las URLs ni el contenido** (límite 30/min por IP, cuerpo de hasta 4 KB).

**Para pasar a `enforce`** (decisión de Matias, recomendada después de ~2 semanas):

1. `grep -c "CSP:" storage/logs/seguridad.log` y `tail` del archivo: tiene que estar **vacío o solo con extensiones del navegador** (esquemas `chrome-extension`, `moz-extension`, `safari-extension`).
2. Recorrer la tienda con la consola abierta: inicio, catálogo, ficha de producto con y sin variantes, combo, carrito, checkout, el panel (Productos con el editor, Pedidos, Documentos).
3. **Confirmar que el hosting no guarda en caché páginas HTML**: el nonce cambia en cada petición; una página cacheada traería un nonce viejo y, en `enforce`, el navegador bloquearía los scripts. (Hoy el caché del hosting es de estáticos.)
4. Cambiar `SEGURIDAD_CSP=enforce`, `php artisan config:cache`, volver a recorrer. Marcha atrás inmediata: `SEGURIDAD_CSP=report-only` + `config:cache`.

### `public/.htaccess` (hosting compartido)

Lleva, además del bloque de Laravel, **dos bloques propios**: «Archivos que nunca se sirven» (ocultos como `.env`/`.git`, las carpetas internas `app|bootstrap|config|database|resources|routes|storage|tests|vendor|node_modules`, `composer.*`, `package*.json`, `artisan`, respaldos y volcados: `.sql .zip .tar .gz .bak .old .log .ini .yml .sqlite…`) y «Cabeceras» (las mismas, para lo estático: imágenes, videos, JS, CSS).
HSTS **no** está en el `.htaccess` a propósito (lo manda la app; es la única cabecera que, mal escrita para el servidor, tira todo el sitio).

> ⚠️ En el hosting el `.htaccess` real es el de **`public_html/`**, que puede traer reglas propias (por ejemplo forzar HTTPS). **No copiar este archivo encima:** pegar los dos bloques en el de `public_html`. El procedimiento está en el Paso 4b de la guía.

`ArchivosSensiblesTest` prueba esas reglas con sus propias expresiones contra ~50 rutas que **deben** bloquearse y ~30 que **deben** servirse, y `chispasfrias:seguridad-verificar --url=…` las prueba contra el sitio real.

---

## HTML del editor (XSS)

La descripción de los productos se escribe con un editor (Quill) y la ficha pública la pinta con `dangerouslySetInnerHTML`. **Antes** de esta auditoría nada la limpiaba: un admin (o alguien con su sesión) podía guardar `<img onerror=…>` y
ese JavaScript corría en el navegador de **cada visitante** y de otros admins.

Ahora `App\Support\HtmlSanitizer` aplica una **lista blanca**, en el servidor, **al guardar y al leer** (`Product::description` es un atributo que limpia en los dos sentidos, así también quedan limpias las filas viejas):

- Etiquetas permitidas: `p br h1 h2 h3 strong b em i u s strike ul ol li a span blockquote`. Las de contenido peligroso (`script style iframe object embed svg math form button meta link…`) se borran **con su contenido**; el resto se «desarma» conservando el texto.
- Atributos: `href` solo `http`, `https`, `mailto`, `tel` o relativo (se normalizan tabulaciones y entidades antes de mirar el esquema: `jav&#9;ascript:` no pasa), con `rel="noopener noreferrer" target="_blank"`; `style` solo `color` y `background-color` con valores simples (sin `url()`, `expression`, `var`); `class` solo `ql-*`; `data-list` de Quill. Todo `on*` se descarta.
- Los otros textos (nombres, notas, observaciones, colores a elección, textos de add-ons, mensaje de WhatsApp) **se muestran como texto** (React escapa) y no hay `{!! !!}` en las vistas: se revisó cada `dangerouslySetInnerHTML` del código (los demás son las etiquetas de paginación que genera el servidor: `« Anterior`, números, `Siguiente »`).

`HtmlSanitizerTest` prueba 20 vectores de XSS (script, `img onerror`, `svg onload`, `javascript:` con mayúsculas/tabs/entidades, `data:`, `vbscript:`, `style` con `url()`/`expression`/`position:fixed`, formularios, comentarios condicionales, etiquetas rotas, MathML…) y que lo que genera Quill se conserva.

---

## Subidas de archivos y los PDFs de Documentos

**Qué se sube:** fotos y videos de productos y combos (admin), y los PDFs de la sección Documentos (admin). Se guardan **dentro de `public/`** (en el hosting, `public_html/images/products` y `public_html/docs/pdfs`).

**Qué se corrigió.** La extensión del archivo guardado salía del *nombre que mandaba el cliente* (`getClientOriginalExtension()`), y si el servidor no podía leer el tipo se lo deducía de esa misma extensión. Y las carpetas no tenían ningún `.htaccess`. Ahora:

1. **El tipo sale del contenido** (`finfo`) y la extensión sale de ese tipo (`CarpetaDeSubidas::extension`): jpg, png, gif, webp, mp4, mov, avi, wmv, flv, webm y pdf. Cualquier otro tipo se descarta. El nombre original nunca se usa.
2. **Nombre aleatorio** (`<timestamp>_<n>_<12 caracteres>.<ext>`; los PDFs, 32 caracteres).
3. **Tamaño máximo:** 20 MB por archivo, hasta 10 por producto (las reglas de validación de siempre).
4. **`.htaccess` en cada carpeta de subidas** (se escribe solo al crear la carpeta o al subir, y `chispasfrias:seguridad-verificar --reparar` lo repone): `php_flag engine off`, `RemoveHandler` de las extensiones PHP, y **lista blanca** (todo denegado salvo imágenes, videos y PDF con nombre «simple»). Aun si un archivo ejecutable llegara a esa carpeta, el servidor no lo ejecuta ni lo sirve.
5. **Nada ejecutable en `public/`:** el comando lo busca (`.php .phtml .phar .sh .py…` en las subidas; `.env`, `.sql`, `.zip`, `composer.json`… en toda la raíz web; carpetas `docs/test-*`) y, con `--url`, **crea un PHP inofensivo en la carpeta de imágenes, lo pide por HTTP y lo borra** para confirmar que el servidor no lo ejecuta.
6. Se sacó el log de `$request->all()` y `$request->allFiles()` que escribía `ProductController` en cada alta y edición.

### Los PDFs de Documentos: qué son y si son públicos

Son los **manuales e instructivos internos para vendedores** (cómo usar el panel, cómo armar un pedido, etc.). Se ven en el panel (admin y vendedor; el vendedor solo los *activos*). **No están pensados para el público**, pero hoy
se guardan en `public_html/docs/pdfs/` y su URL (`https://…/docs/pdfs/documento_<fecha>_<nombre>.pdf`) responde a **cualquiera que la tenga, sin login**, aunque el documento esté desactivado.

**Mitigado ahora:** el nombre pasó de 8 a 32 caracteres aleatorios (no se adivina), el `.htaccess` de la carpeta manda `X-Robots-Tag: noindex` y la carpeta no se lista. Los PDFs ya subidos conservan su nombre corto.

**Propuesta (no aplicada, decide Matias):** servirlos con autorización. Guardarlos en `storage/app/private/documentos/`, una ruta `GET /admin/documents/{document}/archivo` con el Gate del panel (y que el vendedor solo vea los activos) que los entregue con `Content-Disposition`, y migrar los existentes (copiar el archivo y vaciar `public_html/docs/pdfs`). Es un cambio chico (~1 hora) pero toca el
flujo de los vendedores y los links ya compartidos dejarían de andar; por eso no se hace sin el OK.

---

## Otras superficies

| Tema | Estado |
|---|---|
| **Inyección SQL** | Toda consulta usa el query builder / Eloquent con parámetros ligados. Las únicas consultas crudas (`whereRaw`, `orderByRaw`, `selectRaw`: 8) usan constantes de la clase y columnas fijas, sin datos del request; `InyeccionSqlTest` falla si aparece una nueva sin revisar. No hay orden ni columnas dinámicas desde el request. Los buscadores usan `where(col, 'like', valor)` ligado (los comodines `%` y `_` del usuario no se escapan: solo afectan el resultado de su propia búsqueda). |
| **CSRF** | Todas las rutas que modifican están en el grupo `web` (token CSRF), salvo `/rukoz/webhook` (firma HMAC) y `/csp-report` (solo escribe un renglón en un log). Un test recorre todas las rutas y falla si aparece otra sin CSRF. Ningún `GET` modifica datos. |
| **Asignación masiva** | El registro, el perfil y el checkout leen **campos explícitos**: no aceptan `role`, `is_active`, precios, totales, descuentos, estados ni `rukoz_*` / `confirmado_at` (los escribe solo el código del Central con `forceFill`; no están en `$fillable` de `User` ni de `Order`). El checkout **recalcula** precios, descuento, recargo y total en el servidor; nunca toma un total del navegador. Tests: `AutenticacionSeguraTest`, `CheckoutCaracterizacionTest`. |
| **Redirecciones abiertas** | Ninguna redirección usa un parámetro del request; las únicas «a donde iba» son `redirect()->intended()` de Laravel, que guarda una URL del propio sitio. |
| **Exportaciones** | La web no exporta pedidos ni clientes (ni CSV ni Excel). Si se agrega, hay que limitarlo a admin y prefijar con `'` las celdas que empiecen con `= + - @` (inyección de fórmulas). |
| **Descubrimiento de rutas** | Ziggy (`@routes`) publica los nombres de las rutas, también las del panel. No es un secreto (la autorización es del servidor) y filtrarlas por usuario exigiría recargar el front al loguearse; se acepta. Se quitó `storage.local` (la ruta firmada del disco local de Laravel) con `serve=false`. |
| **404** | La página «no existe» devuelve ahora un 404 real (antes un 200 «soft»): los buscadores no la indexan y quien sondea rutas ve el código verdadero. |

---

## Integración con el Sistema Central

### Webhook entrante (`POST /rukoz/webhook`, paquete `rukoz/core-client`)

- Va **fuera del grupo `web`** (sin sesión ni CSRF): la autenticación es la firma.
- Verifica, en este orden y **antes de guardar o parsear nada**: tope de tamaño (1 MB, `413`), `X-Rukoz-Key`, `X-Rukoz-Timestamp` dentro de **±300 s** (`TIMESTAMP_VENCIDO`) y `X-Rukoz-Signature` = HMAC-SHA256 sobre `timestamp \n POST \n path+query \n sha256(body crudo)`, aceptando el secreto actual **y el anterior** (rotación). Un `401` no guarda nada.
- La firma es sobre los **bytes crudos**: el mismo JSON re-serializado con otro orden o espacios ya no vale. Un replay capturado se rechaza por la ventana de tiempo, y el mismo `event_id` nunca se procesa dos veces (`duplicado`).
- Responde rápido: guarda el evento (`rukoz_inbox`), lo procesa en el mismo request (sin llamadas externas) y, si falla, igual contesta `200` (el cron lo reintenta). Si no puede guardar (base caída) devuelve `5xx` y el Central reintenta.
- **No loguea el payload**: la bitácora del paquete registra método, path **sin query**, código, duración, IP del rechazo y `event_id`; nunca el body, los headers ni el secreto.
- Cubierto de punta a punta por `WebhookDelCentralTest` (13 casos, con la firma real del paquete): firma válida, body re-serializado, un byte cambiado, ventana de tiempo, replay, key/secreto/path equivocados, rotación, body enorme, sin sesión ni CSRF, y que **ni un rechazo ni un evento aceptado dejan datos del cliente en el log**.

### Secretos

`RUKOZ_API_KEY` y `RUKOZ_SECRET` (y `RUKOZ_SECRET_ANTERIOR` durante una rotación) viven **solo en el `.env`** del servidor (permisos `600`/`640`, fuera de `public_html`): no están en el repo, no llegan al front ni a los logs, y el `.env` no se versiona. `chispasfrias:seguridad-verificar` avisa si falta alguno o si quedó un secreto anterior cargado.

### Rotación de secretos, paso a paso

El Central acepta **el secreto actual y el anterior durante 24 horas**. Los webhooks que firma el Central salen, desde que se rota, con el secreto **nuevo**; por eso la web tiene que aceptar los dos hasta terminar.

1. **En el Central** (admin): Integraciones → *Web Chispas Frías* → **Rotar secreto**. Se muestra el secreto nuevo **una sola vez**: copiarlo.
2. **En la web** (SSH): `nano .env` y
   ```
   RUKOZ_SECRET_ANTERIOR=<el secreto que había en RUKOZ_SECRET>
   RUKOZ_SECRET=<el secreto nuevo>
   ```
   Después `php artisan config:cache`. (Si hay `config:cache` y no se vuelve a correr, la web sigue con el secreto viejo.)
3. **Verificar:** `php artisan rukoz:ping` (tiene que decir OK: la web ya firma con el nuevo) y `php artisan chispasfrias:integracion-estado`. Hacer un cambio de estado de prueba en el Central y comprobar que llega (`php artisan rukoz:inbox`).
4. **Dentro de las 24 h:** borrar `RUKOZ_SECRET_ANTERIOR` del `.env` y `php artisan config:cache`. Hasta entonces el secreto viejo sigue siendo válido: si la rotación fue por una **filtración**, no esperar, borrarlo apenas el ping dé OK.
5. Anotar fecha y motivo. Rotar de forma preventiva cada 6–12 meses y **siempre** que se sospeche de una filtración, se vaya alguien con acceso al servidor o se haya visto el `.env` en un lugar público.

La `RUKOZ_API_KEY` se cambia igual desde el Central (regenerar clave) y se actualiza en el `.env` + `config:cache`.

### TLS

La URL del Central tiene que ser **`https`** en producción: la API key viaja en cada request y el cuerpo lleva datos de clientes. `php artisan rukoz:ping` y `php artisan chispasfrias:integracion-estado` **avisan** (en rojo, con código de salida 1 en el segundo) si `RUKOZ_CENTRAL_URL` no es https; se tolera `http://localhost` solo fuera de producción (para probar contra un Central local). `chispasfrias:seguridad-verificar` lo chequea también.

### Qué datos del cliente viajan al Central

Solo lo que el Central necesita para registrar la venta y despachar: **nombre y apellido, teléfono, DNI, mail, dirección (calle y número), localidad, provincia, código postal y las observaciones** del pedido (más, al principio de las observaciones, si califica para envío gratis y el código de cupón usado) y los ítems con su precio. Viajan en el **cuerpo** del `POST /pedidos`, firmado y por HTTPS; **nunca en la URL** ni en la query (los paths llevan el número de pedido, p. ej. `/pedidos/CF-000123/cancelar`). No se manda la contraseña ni nada de la cuenta del cliente (no hay) ni datos de pago (la web no cobra online).

### Logs

`storage/logs/laravel.log` pasa por un filtro (`FormateadorSinDatosPersonales`, el mismo del Central) que **tapa mails y números largos** (DNI, CUIT, teléfonos con separadores, tarjetas) aunque se cuelen en una línea, por ejemplo en el SQL de una excepción de base de datos con los valores. El código de la web no loguea clientes: el log de `ProductController` que volcaba todo el request se eliminó; los logs de la integración llevan ids de pedido, no personas. `storage/logs/seguridad.log` solo recibe los avisos de la CSP.

Lo que **sí** queda guardado con datos del cliente por diseño del paquete (idempotencia y reintentos), en la base, no en logs: `rukoz_requests.body` (el pedido que se mandó), `rukoz_compensaciones.payload` y `rukoz_inbox.payload` (el evento recibido). Se purgan con `rukoz:limpiar` (diario a las 04:00): requests completados 30 días, fallidos 90; inbox procesado 30, descartado 90; payload de compensaciones 30 y filas 90.

---

## Datos personales: relevamiento

**Quién es «cliente» para la web:** quien compra (sin cuenta) y, opcionalmente, quien se registra. El comprador deja sus datos en el checkout; se guardan en `orders`.

| Dónde (tabla.columna) | Qué datos | Para qué | Cuánto tiempo | Quién lo ve |
|---|---|---|---|---|
| `orders.name, lastname, dni, phone, email` | identidad y contacto del comprador | gestionar y despachar el pedido; el Central factura y coordina el envío | **sin límite hoy** (pedidos históricos) | admin y vendedor (panel → Pedidos); el Central |
| `orders.province, city, address, number, between_streets, postal_code` | domicilio de entrega (hoy el checkout pide provincia, ciudad y CP; calle y número quedan vacíos si no se piden) | envío | ídem | ídem |
| `orders.observations` | texto libre del comprador | indicaciones del pedido | ídem | ídem |
| `orders.mensaje_whatsapp` | copia del mensaje del pedido (repite nombre, DNI, teléfono, mail, provincia, ciudad, CP, observaciones y el detalle) | el cliente lo envía por WhatsApp; queda como respaldo | ídem | admin y vendedor (ficha del pedido) |
| `order_items.custom_color_text`, `addons_selected`, `combo_selections` | colores y textos de personalización que escribe el comprador (pueden llevar nombres) | preparar el pedido | ídem | admin y vendedor; el Central (campo `nota`) |
| `orders.user_id` | cuenta del comprador, si estaba logueado | vincular el pedido a la cuenta | ídem (se pone en `null` si se borra la cuenta) | admin / vendedor |
| `users.name, email, password` | cuenta de cliente, vendedor o admin; la contraseña, **hasheada con bcrypt** | acceso (el cliente no tiene hoy nada que hacer al entrar) | hasta que se borra la cuenta (el cliente puede borrarla desde su perfil) | el propio usuario; admin (lista de vendedores) |
| `users.rukoz_vendedor_id` | id del vendedor en el Central | confirmar ventas con el vendedor correcto | idem | admin |
| `password_reset_tokens.email, token` | mail y token **hasheado** | recuperar contraseña | el token vence a los 60 min | nadie (solo el sistema) |
| `sessions.ip_address, user_agent, payload` | IP, navegador y la sesión (**el carrito**: puede traer nombres de personalización) | mantener la sesión y el carrito | 120 min de inactividad (se purga por lotería) | nadie (solo el sistema) |
| `rukoz_requests.body`, `rukoz_compensaciones.payload`, `rukoz_inbox.payload` | pedido enviado / evento recibido (datos del cliente del pedido) | reintentos e idempotencia con el Central | 30–90 días (ver arriba) | nadie (base) |
| `cart_items` | usuario, producto, cantidad | tabla heredada: el carrito vive en la sesión, **no se usa** | — | — |
| `storage/logs/laravel.log` | sin datos personales (filtro de enmascarado) | diagnóstico | rotación del hosting | quien tenga acceso al servidor |

**Terceros que reciben datos:** el **Sistema Central del grupo** (todos los datos del pedido, para facturar y despachar), el **transportista** (nombre, teléfono, dirección, a través del Central), **WhatsApp** (el cliente envía el pedido desde su propio teléfono: Meta lo procesa) y el **hosting** (Hostinger, donde viven la base y los archivos). No hay procesador de pagos (la web no cobra online: el pago se coordina por WhatsApp / transferencia), ni analytics ni publicidad.

**Observaciones del relevamiento:**

- Los pedidos se guardan **sin plazo**. Como respaldo contable conviene conservar los datos de la operación (hasta 10 años por las normas comerciales y fiscales), pero **no hace falta** conservar el domicilio, el teléfono y el mail del comprador tanto tiempo: el Central anonimiza los pedidos web cerrados a los 90 días. Ver [Propuestas](#propuestas-pendientes).
- El **vendedor ve todos los pedidos con DNI, teléfono y dirección**.
- Las cuentas de cliente no tienen hoy ninguna función, pero se siguen guardando (nombre, mail y contraseña hasheada).
- Contraseñas: bcrypt; los tokens de recuperación vencen a los 60 minutos y se guardan hasheados.

---

## Qué hacer ante un incidente

**Primero contener, después investigar, después rotar.** Anotar la hora de cada paso.

| Situación | Pasos |
|---|---|
| **Una cuenta comprometida** (contraseña filtrada, sesión robada) | 1. Admin → Vendedores → **Desactivar** (cierra sus sesiones al instante; para un admin, cambiarle la contraseña desde la base o la consola: `php artisan tinker` → `User::find(ID)->update(['password' => Hash::make('…')])` cierra todas sus sesiones). 2. Revisar qué hizo (pedidos tocados, productos, descripciones: ver si hay HTML raro) y revertir a mano. 3. Restablecer la contraseña con la política (10+ caracteres) y reactivar. |
| **Se sospecha de un secreto de la integración** | Rotar (ver [paso a paso](#rotación-de-secretos-paso-a-paso)); si es grave, no esperar las 24 h: borrar `RUKOZ_SECRET_ANTERIOR` apenas el ping dé OK. En el Central, **desactivar** la integración corta la API de esta web al instante. Revisar el log del Central (`/api` y webhooks). |
| **Se filtró la base, un backup o el `.env`** | Rotar **todo**, en este orden: 1) **contraseña de la base** (hPanel → Bases de datos) y actualizar `DB_PASSWORD`; 2) **`APP_KEY`** (`php artisan key:generate --force` + `config:cache`; invalida sesiones y cualquier valor cifrado: acá no hay datos cifrados de negocio, solo las cookies); 3) **mail** (`MAIL_PASSWORD`); 4) **`RUKOZ_API_KEY` y `RUKOZ_SECRET`** (en el Central, sin esperar la gracia); 5) cerrar todas las sesiones: `php artisan tinker` → `DB::table('sessions')->truncate();`; 6) **forzar el cambio de contraseña** de admin y vendedores (los hashes bcrypt se pueden atacar offline); 7) avisar según corresponda (ver abajo). |
| **Apareció un archivo raro en `public_html`** (un `.php` que nadie subió, un `.env`, un `.zip`) | 1. **No abrirlo.** 2. `php artisan chispasfrias:seguridad-verificar --url=…` para ver el alcance. 3. Descargar una copia para analizar y **borrarlo del servidor**. 4. Asumir que hubo acceso: revisar `storage/logs`, las fechas de modificación (`find public_html -newermt '2026-…' -type f`) y rotar todo (fila anterior). 5. Revisar si la carpeta de subidas tiene su `.htaccess`. |
| **Contenido raro en la web** (un script en una ficha de producto) | Editar el producto (la descripción se limpia al guardar), revisar quién tocó el catálogo, y tratar la cuenta que lo hizo como comprometida (primera fila). |
| **Muchos intentos de login / registros** | Mirar `storage/logs/laravel.log` y `sessions`; bajar `THROTTLE_*_POR_MINUTO`; si es un bot de registro, aplicar la protección propuesta (honeypot / captcha) o cerrar el registro. |
| **Fuga de datos de clientes** | Ley 25.326: avisar a los afectados y al responsable del grupo; evaluar la comunicación a la **AAIP** (Agencia de Acceso a la Información Pública) con asesoramiento legal. Conservar los logs y la línea de tiempo. |

---

## Propuestas pendientes

Cosas que **no se aplicaron** porque afectan lo que ve o hace el cliente, o son decisión de negocio. Cada una tiene su costo.

| # | Propuesta | Por qué | Costo / riesgo |
|---|---|---|---|
| 1 | **Protección anti-bots del registro** (honeypot: un campo oculto que un humano no llena; o un captcha liviano tipo Turnstile/hCaptcha). Alternativa: **cerrar el registro de clientes**: hoy un cliente registrado no tiene ninguna pantalla útil. | El registro es abierto y sin verificación de mail: un bot puede crear miles de cuentas. Con throttle (5/min/IP) se frena pero no se impide. | Honeypot: 20 líneas, invisible. Captcha: depende de un tercero (y suma su dominio a la CSP). Cerrar el registro: cambia lo que ve quien llega a `/register`. |
| 2 | **Servir los PDFs de Documentos con autorización** (guardarlos en `storage/app/private`, ruta con Gate). | Hoy son manuales internos con URL pública (ver [arriba](#los-pdfs-de-documentos-qué-son-y-si-son-públicos)). | ~1 h + migrar los existentes; los links ya compartidos dejan de andar. |
| 3 | **CSP en modo bloqueo** (`SEGURIDAD_CSP=enforce`). | Hoy solo reporta. | Riesgo bajo si no hay avisos; condición: que el hosting no cachee HTML. Procedimiento en [Cabeceras](#modo-solo-reportar--modo-bloqueo). |
| 4 | **Política de privacidad publicada** y enlace en el pie / el checkout. | Se piden datos personales (Ley 25.326, art. 6 y 14). El texto base está en [`PRIVACIDAD-PROPUESTA.md`](PRIVACIDAD-PROPUESTA.md): **que lo revise un abogado**. | Una página nueva y un enlace. |
| 5 | **Plazo de conservación de los datos del comprador** (anonimizar nombre, DNI, teléfono, mail, dirección y observaciones de los pedidos cerrados a los N meses, como hace el Central a los 90 días), conservando montos y productos. | Minimización: hoy se guardan para siempre. | Un comando programado + una decisión de negocio (cuántos meses; el Central ya tiene el pedido). |
| 6 | **Vendedor: ver solo lo que necesita** (ocultar DNI y dirección completos en el listado, o limitar los pedidos a los suyos). | Hoy cualquier vendedor ve todos los pedidos con todos los datos. | Cambia la operación (cualquiera despacha cualquier pedido). |
| 7 | **Pedir la contraseña actual al cambiar el mail** del perfil. | Un atacante con una sesión robada podría cambiar el mail y recuperar la cuenta. Hoy exige contraseña solo para borrar la cuenta y para cambiarla. | Un campo más en el formulario de perfil. |
| 8 | **No permitir que se borre la cuenta del último admin / de un vendedor** desde `/profile` (el borrado de cuentas del panel es solo desactivar). | Un vendedor o el único admin puede borrarse a sí mismo. | Una regla en `ProfileController::destroy`. |
| 9 | **2FA para admin** (TOTP). | El panel concentra datos de clientes. | Medio día; el Central tiene el diseño. |
| 10 | **`SESSION_ENCRYPT=true`** y mail SMTP real. | La sesión guarda el carrito; el mail de «olvidé mi contraseña» hoy depende de `MAIL_MAILER` (en `log` no se entrega). | Es solo `.env`; invalida las sesiones abiertas. |

---

## Cómo se comprueba

**Tests** (`php artisan test`; la suite completa corre sobre SQLite en memoria, no toca la base real):

| Archivo | Qué prueba |
|---|---|
| `Seguridad/MatrizDeAccesoTest` | matriz rol × ruta (estática y con usuarios reales), IDOR, CSRF en todas las rutas que modifican |
| `Seguridad/AutenticacionSeguraTest` | throttles, mensajes que no revelan cuentas, sesiones (regenerar, desactivar, inactividad, cambio de contraseña), política de contraseñas, asignación masiva |
| `Seguridad/CabecerasDeSeguridadTest` | cabeceras, CSP (modos, nonce, orígenes extra), HSTS, `/csp-report` |
| `Seguridad/HtmlSanitizerTest` | 20 vectores de XSS y lo que debe conservarse |
| `Seguridad/SubidasSegurasTest` | tipo por contenido, nombre aleatorio, scripts disfrazados, `.htaccess`, PDFs |
| `Seguridad/ArchivosSensiblesTest` | reglas del `.htaccess` contra ~80 rutas, el comando `seguridad-verificar`, TLS del Central |
| `Seguridad/WebhookDelCentralTest` | webhook de punta a punta con la firma real |
| `Seguridad/InyeccionSqlTest` | lista de consultas crudas revisadas, búsquedas y filtros con SQL |
| `Seguridad/ErroresYDatosExpuestosTest` | sin trazas con `APP_DEBUG=false`, campos del usuario que llegan al front |

**Comandos en el servidor:**

```bash
php artisan chispasfrias:seguridad-verificar --url=https://<el dominio>   # configuración, archivos expuestos y pruebas desde afuera (código 1 si algo está mal)
php artisan chispasfrias:seguridad-verificar --reparar                    # escribe el .htaccess de las carpetas de subidas si falta
php artisan chispasfrias:integracion-estado                               # incluye el chequeo de TLS hacia el Central
php artisan rukoz:ping                                                    # avisa si la URL del Central no es https
```

**Chequeos periódicos:** mensual, `chispasfrias:seguridad-verificar --url=…`; revisar `storage/logs/seguridad.log` (avisos de la CSP) y los usuarios admin/vendedor activos; `composer audit` y `npm audit` antes de cada deploy; rotar los secretos de la integración cada 6–12 meses.

---

## Registro de la auditoría S.2

Resumen de lo encontrado (lo detallado de cada punto está en las secciones de arriba), por gravedad.

| Gravedad | Hallazgo | Estado |
|---|---|---|
| **Alta** | XSS almacenado: la descripción HTML del producto (Quill) se pintaba con `dangerouslySetInnerHTML` sin ninguna limpieza; el JS corría en el navegador de los visitantes y de otros admins | **Corregido** (`HtmlSanitizer`, al guardar y al leer) |
| **Alta** | Subidas: la extensión del archivo guardado venía del nombre del cliente (y, sin lectura de tipo, el MIME se deducía de la extensión); las carpetas de subidas, dentro de `public_html`, no tenían `.htaccess` que impidiera ejecutar PHP | **Corregido** (extensión por contenido, nombre aleatorio, `.htaccess` con lista blanca, comando que lo prueba) |
| **Media** | Los PDFs de Documentos (manuales internos) son públicos por URL, con nombre de 8 caracteres | **Mitigado** (32 caracteres, `noindex`); servirlos con autorización queda como **propuesta** |
| **Media** | «Olvidé mi contraseña» revelaba si un mail tiene cuenta (mensaje distinto y límite visible) | **Corregido** |
| **Media** | Sin límite por IP en login, registro y recuperación | **Corregido** (throttles configurables) |
| **Media** | Una cuenta desactivada con la sesión abierta seguía adentro con drivers distintos de `database`; cambiar la contraseña no cerraba las otras sesiones; sin vencimiento por inactividad en el panel; sin política de contraseñas para admin/vendedor | **Corregido** |
| **Media** | Sin ninguna cabecera de seguridad ni CSP | **Corregido** (CSP en modo solo reportar; el bloqueo es **propuesta**) |
| **Baja** | `/storage/{path}` (disco local con `serve`) registrada y publicada por Ziggy | **Corregido** (`serve=false`) |
| **Baja** | El front recibía el modelo completo del usuario (`is_active`, `rukoz_vendedor_id`…) | **Corregido** (solo `id, name, email, role, email_verified_at`) |
| **Baja** | La página «no existe» respondía 200 | **Corregido** (404 real) |
| **Baja** | Logs con el request completo de productos; sin filtro de datos personales en el log | **Corregido** |
| **Baja** | El `.htaccess` no bloqueaba `.env`, `.git`, respaldos, logs ni carpetas internas | **Corregido** (bloques nuevos; en el hosting hay que pegarlos en `public_html/.htaccess`) |
| **Baja** | La URL del Central podía ser `http` sin ningún aviso | **Corregido** (aviso en `rukoz:ping`, `integracion-estado` y el comando de seguridad) |
| **Info** | Registro abierto sin función para el cliente; vendedor ve todos los pedidos; pedidos sin plazo de conservación; cambio de mail sin contraseña; Ziggy publica nombres de rutas | **Propuestas** / aceptado |
| **OK** | Autorización por Gates, CSRF, asignación masiva, SQL, redirecciones, webhook (firma sobre body crudo, ventana, rotación), contraseñas con bcrypt, tokens con vencimiento, checkout que recalcula todo en el servidor | **Sin hallazgos** (se agregaron tests que lo fijan) |
