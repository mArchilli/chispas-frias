# Puesta en marcha de la integración — Chispas Frías (10.5)

> ⚠️ **Chispas Frías está en DonWeb (panel Ferozo), sin SSH: deploy por FTP y tareas programadas cada 5 minutos. Las secciones que asumen SSH se reescriben en el paso de deploy.**

Guía para el día en que la web de producción (`https://chispasfrias.com`, hosting compartido de DonWeb, panel Ferozo) empiece a hablar con el Sistema Central (`https://sistema.rukoz.com.ar`, dominio provisorio y configurable con `RUKOZ_CENTRAL_URL`).

- **Versión desplegada hoy:** tag `produccion-pre-integracion` (`363588b`). **Versión a desplegar:** `main` (tag `integracion-local-ok`, merge `4d959a8`, más el commit de esta guía).
- **Paquete:** `rukoz/core-client` `^0.1.2` (queda en `0.1.2` en `composer.lock`), desde el repo privado `github.com/mArchilli/rukoz-core-client`.
- Esta guía **no se ejecutó nunca contra producción**. Lo que dice del hosting sale del relevamiento y de las notas de deploy (`docs/10-0-limpieza-pre-integracion.md`): la Parte 1 sirve justamente para confirmarlo. Los datos de la base de producción (13 productos, 26 variantes, 0 pedidos, 2 usuarios) salen de la copia del **2026-10-19**: se vuelven a contar el día del deploy.

Cómo leerla:

0. **Revisión de seguridad previa.** Cinco minutos: que no haya un `.env` ni carpetas de prueba servidas desde `public_html`. (La auditoría S.2 sumó el comando `php artisan chispasfrias:seguridad-verificar --url=…`, que hace esta revisión y más; ver `SEGURIDAD.md` y el Paso 4b.)
1. **Parte 1 — Chequeo del hosting.** Se hace días antes, sin tocar nada (solo comandos de lectura; los pocos que escriben están marcados como «prueba» y se borran).
2. **Parte 2 — El día del deploy.** Once pasos, en orden (más el **Paso 4b — Seguridad del día del deploy**, de la auditoría S.2). Cada uno termina en algo que se puede verificar.
3. **Anexos.** Token de GitHub, tabla de variables y preguntas abiertas.

**Qué cambia respecto de Chisperío** (para no copiar a ciegas):

- Una sola sede: no hay puntos `BA`/`CBA`. El pedido no manda `punto`; rige el punto predeterminado de la integración en el Central.
- El modelo se llama `Order` (tabla `orders`), no `Pedido`; los comandos propios son `chispasfrias:*`.
- Hay **siete** migraciones nuevas (cinco de la integración y dos del checkout y el carrito; más las del paquete), y una de ellas **corrige datos** (vigencias de ofertas y cupones): ver paso 4 y paso 10.
- `public/robots.txt` se borró del repo (ahora lo sirve una ruta): hay que borrar el `robots.txt` viejo de `public_html` (paso 4).
- Hoy no hay combos ni pedidos en producción: el riesgo del paso 3 es bajo, pero se verifica.

---

## Revisión de seguridad previa

**Hacela antes de la Parte 1, con el administrador de archivos de Hostinger o por SSH. Es solo lectura, salvo que encuentres algo.**

**Por qué:** en el entorno local los tests de documentos (`AdminDocumentTest`) dejaban carpetas `public/docs/test-*`, y en una de ellas apareció una copia de un `.env` de producción (de fines de septiembre; no la generó ningún test ni script del repo, así que se copió a mano). Lo que está dentro de `public/` (en el hosting, `public_html/`) lo puede servir el servidor web. Ya se corrigió en el repo y se limpió la copia local; falta confirmar que **el hosting** no tiene nada parecido, porque ese `.env` tiene la contraseña de la base, la `APP_KEY` y las credenciales del mail.

1. **¿Existe `public_html/docs/` con carpetas `test-*`?** Los tests nunca se corrieron en el hosting, así que no debería haberlas. Lo único legítimo ahí adentro es `public_html/docs/pdfs/` (los PDFs que sube el panel de Documentos).

   ```bash
   ls -la ~/public_html/docs/
   find ~/public_html -type d -name "test-*"
   ```

2. **¿Hay algún `.env` (o copia: `.env.bak`, `.env.old`, `.env.production`…) dentro de `public_html`?** El único `.env` válido está en la carpeta del proyecto (`<PROYECTO>`, hermana de `public_html`), nunca dentro de `public_html`.

   ```bash
   find ~/public_html -name ".env*"
   ```

   El resultado tiene que ser **vacío**. (Con el administrador de archivos: activar «mostrar archivos ocultos» y buscar `.env` en `public_html`.)

3. **Probar por URL que no se sirva.** Estas direcciones tienen que devolver **403 o 404**, nunca el contenido del archivo (y, si encontraste el punto 1 o 2, probá también la ruta exacta del archivo hallado):

   ```bash
   curl -s -o /dev/null -w "%{http_code}\n" https://chispasfrias.com/.env
   curl -s -o /dev/null -w "%{http_code}\n" https://chispasfrias.com/docs/.env
   curl -s -o /dev/null -w "%{http_code}\n" https://chispasfrias.com/docs/test-XXXX/.env   # solo si existe una carpeta test-*
   ```

   Un `200` significa que el archivo se está sirviendo: tratalo como filtración (punto siguiente).

4. **Si encontrás un `.env` expuesto (o servido por URL):**
   1. **Borrarlo** de `public_html` (y cualquier carpeta `test-*`) de inmediato.
   2. **Rotar todo lo que contenga**, porque desde ese momento hay que darlo por conocido: la **contraseña de la base** (cambiarla en hPanel → Bases de datos y actualizar `DB_PASSWORD` en el `.env` real del proyecto), la **`APP_KEY`** (`php artisan key:generate`; ojo: invalida las sesiones y lo que esté cifrado con la clave anterior), la **contraseña del mail** (`MAIL_PASSWORD`) y **cualquier token o clave** (`RUKOZ_API_KEY`, `RUKOZ_SECRET`, el token de GitHub del Anexo A, si estaban en ese archivo).
   3. Revisar el log de accesos del hosting (hPanel → Estadísticas / Logs) por pedidos a esa ruta.
   4. Después de rotar, `php artisan config:clear && php artisan config:cache` y comprobar que la web funciona.

5. **Prevención.** Dejar el proyecto con la raíz del documento apuntando a `public_html` y el `.env` fuera de ella (como ya está según las notas de deploy), y no guardar copias del `.env` en ninguna carpeta pública.

---

## Parte 1 — Chequeo del hosting (solo lectura)

Todo por SSH. El proyecto vive en una carpeta hermana de `public_html` (según las notas de deploy se llama `laravel`; acá `<PROYECTO>`). Anotá cada resultado: las dudas abiertas están en el Anexo C.

### 1.1 PHP

Laravel 12 y el paquete piden **PHP ≥ 8.2** (`composer.json`: `"php": "^8.2"`). Hay que mirar **dos** PHP: el de la línea de comandos (artisan, cron, composer) y el de la web.

| Qué | Cómo verificarlo | Esperado |
|---|---|---|
| PHP de la CLI | `php -v` | 8.2 o superior |
| Otros PHP instalados | `ls /usr/bin/php* /opt/alt/php*/usr/bin/php 2>/dev/null` | si el `php` por defecto es viejo, anotá la ruta del 8.2+ (se usa en el cron y en todos los comandos) |
| PHP de la web | hPanel → *Avanzado → Configuración de PHP*; o `curl -sI https://chispasfrias.com \| grep -i x-powered-by` (puede no mostrarse) | 8.2 o superior, **igual** al de la CLI |
| Extensiones | `php -m` | ver abajo |
| Límites | `php -i \| grep -E "memory_limit\|max_execution_time\|upload_max_filesize\|post_max_size"` | el admin sube fotos y videos de productos y PDFs de documentos: `upload_max_filesize`/`post_max_size` no deberían ser menores que hoy |

Extensiones que usa la app: `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `iconv`, `json`, `libxml`, `mbstring`, `openssl`, `pcre`, `pdo`, **`pdo_mysql`**, `session`, `tokenizer`, `xml`, `xmlwriter`. Atajo:

```bash
for e in ctype curl dom fileinfo filter hash iconv json libxml mbstring openssl pcre pdo pdo_mysql session tokenizer xml xmlwriter; do php -m | grep -qix "$e" && echo "ok   $e" || echo "FALTA $e"; done
```

Con el proyecto ya instalado, además: `composer2 check-platform-reqs --no-dev`.

Si la web y la CLI usan PHP distinto, **la web importa para el sitio y la CLI para cron, artisan y composer**: las dos tienen que cumplir. (El `composer.lock` de esta versión trae Laravel 12.70, que exige 8.2.)

### 1.2 Composer y git

```bash
composer2 --version        # en las notas de deploy el comando es composer2
which composer composer2
git --version
```

Si no hay Composer: instalarlo en la cuenta (sin root) con el instalador oficial, dejando `composer.phar` en `~/` y llamándolo con `php composer.phar …`. Hace falta **git** y salida a GitHub (1.4): el paquete se baja de ahí.

### 1.3 Cron: ¿cada minuto?

El scheduler de Laravel necesita **un solo cron**:

```
* * * * * /ruta/al/php /ruta/al/<PROYECTO>/artisan schedule:run >> /dev/null 2>&1
```

**Qué verificar (hPanel → Avanzado → Cron Jobs):**

1. ¿Admite «cada minuto» (`* * * * *`)? Si el campo es libre, ¿acepta `* * * * *` y `*/5 * * * *`? **No lo asumimos: lo confirma quien tenga el panel.** Algunos planes restringen el intervalo mínimo.
2. Probarlo sin riesgo con un cron de prueba: `* * * * * date >> $HOME/cron-prueba.log` → esperar 3 minutos, `cat $HOME/cron-prueba.log` (una línea por minuto). **Borrar el cron y el archivo después.**
3. Ruta del PHP en el cron: la del 8.2+ que anotaste en 1.1 (el `php` del cron puede no ser el de la shell: escribir `which php; php -v` en el log de prueba).

**Qué depende del cron** (con la integración encendida):

| Tarea | Frecuencia | De dónde sale | Para qué sirve |
|---|---|---|---|
| `rukoz:procesar-inbox` | cada 1 min | paquete | reintenta los eventos del Central que fallaron en el webhook |
| `rukoz:poll` | cada 5 min (1 min si `RUKOZ_WEBHOOK_RECIBE=false`) | paquete | **respaldo del webhook**: lee el feed de eventos |
| `rukoz:compensaciones` | cada 5 min | paquete | reintenta cancelar una reserva que no se pudo cancelar en el momento |
| `rukoz:revisar-pedidos` | cada 10 min | paquete | pedidos «requiere revisión» |
| `rukoz:revisar-pedidos --reconciliar` | cada hora | paquete | compara pedidos activos con el Central |
| `rukoz:sync-stock` | cada hora | paquete | reconcilia el caché de stock |
| `rukoz:sync-catalogo` | diaria (si `INTEGRACION_CATALOGO`) | paquete | sync completo del catálogo |
| `rukoz:limpiar` | diaria | paquete | purga de tablas `rukoz_*` |
| `chispasfrias:cancelar-reservas-viejas` | cada hora | **web** (`routes/console.php`) | cancela reservas sin confirmar hace más de `PEDIDOS_CANCELAR_DIAS` (3); **sin esto el stock queda apartado para siempre** |
| `chispasfrias:stock-cargar --local` | diaria 03:45 | **web** | red de seguridad del espejo de stock |

`php artisan schedule:list` muestra lo programado. Con la integración apagada no hay ninguna tarea del paquete ni de la web (las dos de la web tienen `when(...)`).

**Si el cron mínimo es de 5 minutos** (`*/5 * * * *`): todo funciona; solo cambia la demora de los *respaldos* (`procesar-inbox` pasa de 1 a 5 min; el primer reintento de `compensaciones` también). Las tareas horarias (minuto `:00`) y las diarias (03:45, etc.) corren igual, porque `*/5` pasa por esos minutos: una tarea con hora fija solo corre si el cron cae **exactamente** en ese minuto. Con un cron cada 15 min se pierde alguna diaria que no caiga en múltiplo de 15 (`stock-cargar --local`, 03:45, sí cae); con uno cada 30 min o cada hora se pierden tareas: no es recomendable, y habría que reprogramar las horas. Un cron de más de 5 min deja al **webhook como única vía rápida**.

| Vía | Rol |
|---|---|
| **Webhook** (`POST /rukoz/webhook`) | **Principal.** El Central avisa en el momento; la web guarda el evento en `rukoz_inbox` y lo procesa en el mismo request. No depende del cron. |
| `rukoz:procesar-inbox` | Respaldo del webhook: reintenta lo que falló al procesar. |
| `rukoz:poll` (feed) | Respaldo si el webhook no llegó (hosting caído, firewall). Recupera eventos perdidos por secuencia. |
| `rukoz:sync-stock` (hora) y `chispasfrias:stock-cargar --local` (noche) | Reconciliación de stock. |
| `rukoz:revisar-pedidos --reconciliar` (hora) | Reconciliación de estados de pedidos. |

> **Regla firme:** si el hosting **no permite ningún cron razonable**, no se enciende `INTEGRACION_CHECKOUT` ni `INTEGRACION_CONFIRMACION`: el stock apartado no se libera y los respaldos no corren. No hay worker de cola ni hace falta (1.8).

### 1.4 Salida a internet hacia el Central y a GitHub

```bash
# El Central (provisorio; si todavía no existe, probar con cualquier HTTPS para ver si la salida funciona)
curl -sS -o /dev/null -w "%{http_code} %{time_total}s\n" https://sistema.rukoz.com.ar/
curl -sS -o /dev/null -w "%{http_code}\n" https://example.com/      # control

# GitHub (composer y el paquete privado)
curl -sS -o /dev/null -w "%{http_code}\n" https://github.com/
curl -sS -o /dev/null -w "%{http_code}\n" https://api.github.com/
```

Cualquier código HTTP (`200`/`301`/`302`/`404`) significa que **hay salida**. Un error de resolución, de conexión o `SSL certificate problem` se resuelve con el soporte del hosting **antes** del deploy. Probar también desde PHP (puede tener reglas distintas que el `curl` de la shell):

```bash
php -r 'var_dump(file_get_contents("https://api.github.com/", false, stream_context_create(["http"=>["header"=>"User-Agent: x"]])) !== false);'
```

La prueba real hacia el Central es `php artisan rukoz:ping` (paso 5).

### 1.5 El webhook entrante

El Central le manda a la web `POST https://chispasfrias.com/rukoz/webhook` (el path es `RUKOZ_WEBHOOK_PATH`, por defecto `/rukoz/webhook`). **No admite redirecciones.**

**Dominio canónico.** Averiguar cuál queda **después** de las redirecciones actuales (raíz o `www`):

```bash
for u in http://chispasfrias.com/ http://www.chispasfrias.com/ https://chispasfrias.com/ https://www.chispasfrias.com/; do
  curl -sS -o /dev/null -L -w "$u  ->  %{url_effective}  (%{http_code}, %{num_redirects} redirecciones)\n" "$u"
done
```

La URL que se carga en la integración del Central es la que **termina sin redirecciones** y con `https`. Se espera `https://chispasfrias.com/rukoz/webhook` (el dominio canónico decidido por Matias, solo en `APP_URL`), **pero no se da por sentado**: si `https://chispasfrias.com/` redirige a `www`, la URL del webhook es la de `www`. Probar que la ruta exacta no redirige:

```bash
curl -sS -o /dev/null -w "%{http_code} -> %{redirect_url}\n" -X POST -H "Content-Type: application/json" -d '{}' https://chispasfrias.com/rukoz/webhook
```

Qué significa lo que devuelve (la ruta existe **solo con `RUKOZ_INTEGRACION=true` y `RUKOZ_WEBHOOK_RECIBE=true`**):

| Código | Con la integración apagada (hoy, antes del deploy) | Con la integración encendida |
|---|---|---|
| `404` (página de error de la web) | **normal**: la ruta no está registrada | algo anda mal: el paquete no cargó |
| `401` con JSON `{"ok":false,"codigo":"…"}` | — | **correcto**: la petición llegó a Laravel y rechazó la firma |
| `301`/`302` | **malo**: redirección (www ↔ raíz, http → https) | **malo**: el Central no sigue redirecciones |
| `403`/`406`/`5xx` con HTML del servidor, sin JSON | **malo**: lo bloquea el servidor (ModSecurity/WAF, `.htaccess`, firewall) | **malo** |
| `405` | **malo**: alguna regla filtra los `POST` | **malo** |

Antes del deploy el único resultado válido es `404` de la web; si ya se ve `301`/`403`/`405`, hay que resolverlo con el hosting antes de seguir. Dónde mirar: el `.htaccess` de `public_html` (¡es el real! el de `public/` del repo es el estándar de Laravel y **no** fuerza HTTPS ni `www`/raíz; el de `public_html` puede tener reglas propias del hosting) y hPanel → *Seguridad* (WAF / ModSecurity / bloqueo de IPs).

**Qué NO bloquear:** el `POST` sin sesión ni CSRF a `/rukoz/webhook` (lo registra el paquete fuera del grupo `web`).

### 1.6 Dónde está el proyecto y cómo se despliega hoy

**Hoy** (según las notas de deploy, *a confirmar*): el repo se clona en una carpeta hermana de `public_html` (`laravel`); se corre `composer2 install` **en el servidor**; el contenido de `public/` vive en `public_html`, con un `index.php` editado a mano para apuntar al `vendor` y a `bootstrap/app.php` del proyecto; el build de Vite se genera **en local** y se copia a **dos carpetas**: `<PROYECTO>/public/build` y `public_html/build`. Las imágenes de productos se escriben en `public_html/images/products` (`PRODUCT_IMAGES_PATH="/../../public_html/images/products/"`, relativo a `<PROYECTO>/public`).

```bash
cd <PROYECTO>
git log --oneline -1 && git status --short | head      # ¿es un clon con git? ¿qué commit corre? ¿cambios locales?
git describe --tags --always                            # debería decir produccion-pre-integracion (o 363588b)
ls -la vendor | head -3; ls vendor/composer/installed.json
ls ../public_html | head -30; grep -n "vendor\|bootstrap" ../public_html/index.php
ls -d ../public_html/build ../<PROYECTO>/public/build 2>&1
ls ../public_html/robots.txt 2>&1                       # si existe, hay que borrarlo en el paso 4
grep -E "^(PRODUCT_IMAGES_PATH|VITE_PRODUCT_IMAGES_PATH|DOCUMENTS_PDF_PATH)=" .env
ls -d ../public_html/images/products ../public_html/docs 2>&1
```

Qué se anota: si el proyecto realmente es hermano de `public_html` (de eso depende `PRODUCT_IMAGES_PATH`), si hay cambios locales en el servidor (un `git status` sucio **frena** el deploy: se resuelve antes), y si el `.htaccess` y el `index.php` de `public_html` son los de siempre.

> ⚠️ **Nunca copiar `public/` entero a `public_html`** — solo `public/build`. En la máquina de desarrollo `public/docs/` tiene carpetas de pruebas con archivos `.env` sueltos (ignoradas por git, no viajan en el repo, pero sí en una copia a mano).

**Mañana:** `composer2 install --no-dev -o` **por SSH**. Como el paquete es un repo **privado**, hace falta un token de solo lectura en la cuenta del hosting (Anexo A), guardado en `~/.composer/auth.json` (fuera del repo). `composer.lock` fija versión y hashes: el servidor instala exactamente lo que se probó.

### 1.7 Zona horaria y fechas

```bash
grep -E "^(APP_TIMEZONE|APP_DISPLAY_TIMEZONE|APP_ENV|APP_DEBUG|APP_URL)=" <PROYECTO>/.env
php artisan tinker --execute="echo config('app.timezone').' / '.config('app.display_timezone').' / '.now()->toDateTimeString().PHP_EOL;"
date
```

Lo **guardado** usa `APP_TIMEZONE` (`UTC` por defecto, **no se cambia**); lo que se **muestra** usa `APP_DISPLAY_TIMEZONE` (por defecto `America/Argentina/Buenos_Aires`). El Central recibe fechas ISO 8601 con offset. Atención con el reloj del servidor: la firma del Central tolera 300 s de diferencia.

Esto se conecta con la migración de vigencias (paso 4): desde la 10.0 lo que tipea el admin en una oferta o cupón es hora argentina y se guarda en UTC.

### 1.8 Permisos, `.env` y cola

```bash
cd <PROYECTO>
ls -ld storage storage/app storage/framework storage/logs bootstrap/cache
test -w storage/logs && test -w storage/framework/cache && test -w bootstrap/cache && echo "escribible" || echo "SIN PERMISO"
ls -la .env && stat -c '%a' .env                        # 600 o 640; nunca 666/777
grep -E "^(QUEUE_CONNECTION|CACHE_STORE|SESSION_DRIVER|LOG_CHANNEL|LOG_LEVEL)=" .env
```

`storage/` y `bootstrap/cache` tienen que ser escribibles por el usuario que corre **la web y el cron** (en compartido es la misma cuenta; alcanza con `chmod -R u+rwX storage bootstrap/cache`). Y `public_html/images/products` y `public_html/docs` tienen que ser escribibles (el admin sube fotos y PDFs ahí).

**Cola:** el paquete usa tablas propias (`rukoz_inbox`, `rukoz_compensaciones`, …) procesadas por el scheduler, **no** la cola de Laravel. **No hace falta ningún worker** (en compartido no hay Supervisor). Verificar que no hay nada encolado: `php artisan tinker --execute="echo DB::table('jobs')->count().' / '.DB::table('failed_jobs')->count().PHP_EOL;"` → `0 / 0` (si no existen esas tablas, no importa).

### 1.9 La IP real del visitante (throttle)

El checkout (`POST /carrito/whatsapp`, 10/min) y la validación de cupones (30/min) limitan **por IP** (`$request->ip()`). Si el hosting pone un proxy o CDN delante (Cloudflare, el CDN de Hostinger), Laravel puede ver **una sola IP para todos** y todos los clientes comparten el límite. `bootstrap/app.php` **no** configura `trustProxies`.

Prueba (escribe un archivo temporal; se borra enseguida):

```bash
cat > ../public_html/ip-prueba.php <<'PHP'
<?php header('Content-Type: text/plain');
foreach (['REMOTE_ADDR','HTTP_X_FORWARDED_FOR','HTTP_CF_CONNECTING_IP','HTTP_X_REAL_IP'] as $k) echo $k.' = '.($_SERVER[$k] ?? '-')."\n";
PHP
curl -s https://chispasfrias.com/ip-prueba.php; echo; curl -s https://ifconfig.me; echo
rm ../public_html/ip-prueba.php
```

- Si `REMOTE_ADDR` es **tu IP pública** (la que da `ifconfig.me` desde tu casa, probando desde afuera del servidor): está bien, no hay que tocar nada.
- Si `REMOTE_ADDR` es otra y la tuya aparece en `X-Forwarded-For`/`CF-Connecting-IP`: hay un proxy. Hay que cambiar el código (`$middleware->trustProxies(...)` en `bootstrap/app.php`) o subir `THROTTLE_CHECKOUT_POR_MINUTO` — **es una decisión previa al deploy**, no un cambio del día.

(Corré el `curl` de `ifconfig.me` desde tu computadora, no desde el servidor, para comparar con tu IP real.)

### 1.10 Resumen de la Parte 1: lo que alguien con acceso tiene que contestar

| # | Pregunta | Dónde |
|---|---|---|
| 1 | ¿PHP de la **CLI** y de la **web** ≥ 8.2? ¿Están las extensiones? | 1.1 |
| 2 | ¿Hay `composer2` y git? | 1.2 |
| 3 | ¿El cron admite cada minuto? Si no, ¿cuál es el mínimo? | 1.3 |
| 4 | ¿El servidor sale a `sistema.rukoz.com.ar` y a GitHub? | 1.4 |
| 5 | ¿Dominio canónico (raíz o `www`) sin redirecciones? ¿El `POST` a `/rukoz/webhook` pasa sin bloqueos? | 1.5 |
| 6 | ¿Dónde está el proyecto? ¿Hay `robots.txt` en `public_html`? ¿Qué commit corre? | 1.6 |
| 7 | ¿Reloj del servidor y zona horaria? | 1.7 |
| 8 | ¿Permisos de `storage/`, `bootstrap/cache` y `public_html/images`? | 1.8 |
| 9 | ¿`REMOTE_ADDR` es la IP real del visitante? | 1.9 |

---

## Parte 2 — El día del deploy

Orden obligatorio. Si un paso falla, **no se sigue**: se resuelve o se vuelve atrás (paso 10).

Convenciones: `<PROYECTO>` = carpeta del proyecto en el servidor; todos los `php artisan` se corren ahí con el PHP 8.2+ de la CLI. Los valores entre `<…>` los pone quien despliega. **Nada de esto se ejecutó nunca en producción.**

### Paso 1 — Antes de tocar la web

Condiciones que tienen que estar cumplidas **en el Central de producción** (`https://sistema.rukoz.com.ar`):

- [ ] El Central está en producción con los **datos reales**: artículos, precios, cuentas, medios de pago, formas de envío, transportistas, y el **stock real** (si no, la web arranca con todo en 0: paso 7).
- [ ] La integración **«Web Chispas Frías»** está creada: empresa Chispas Frías, activa, **`dias_aviso_reserva` = 4** (la web cancela a los `PEDIDOS_CANCELAR_DIAS` = 3; el aviso del Central va un día después, como red), **punto de atención predeterminado** definido (la web no manda `punto`; en el Central local es «Buenos Aires»), y el **webhook** cargado: `https://chispasfrias.com/rukoz/webhook` (o la URL de `www` si ese resultó ser el canónico en 1.5; https, sin redirecciones). La **API key** y el **secreto** se muestran **una sola vez**: copiarlos a un gestor de contraseñas.
- [ ] El **vendedor** real (y el admin, si también confirma ventas) está asignado a la integración (Integraciones → Vendedores).
- [ ] El **transportista** real existe con su **URL de seguimiento** (es lo que la web muestra cuando el Central despacha) y hay una **forma de envío** que no sea retiro.
- [ ] La Parte 1 está completa y sin pendientes (PHP, cron, salida, webhook, dominio, IP real).
- [ ] Hay **fecha y hora acordadas**: de baja actividad, con alguien que pueda confirmar pedidos desde el admin durante la prueba.

### Paso 2 — Backup completo (sin backup no se sigue)

Antes de cualquier cambio, guardado **fuera del servidor**:

```bash
cd <PROYECTO>
mkdir -p ~/backups && STAMP=$(date +%Y%m%d-%H%M)

# Base de datos (credenciales del .env)
DB=$(grep ^DB_DATABASE= .env | cut -d= -f2-); DU=$(grep ^DB_USERNAME= .env | cut -d= -f2-); DH=$(grep ^DB_HOST= .env | cut -d= -f2-)
mysqldump -h "$DH" -u "$DU" -p --single-transaction --routines --triggers "$DB" | gzip > ~/backups/chispasfrias-db-$STAMP.sql.gz

# Archivos: proyecto (con .env) + public_html (incluye imágenes, videos y PDFs subidos)
tar --exclude='<PROYECTO>/storage/logs/*' -czf ~/backups/chispasfrias-archivos-$STAMP.tar.gz ../<PROYECTO> ../public_html
ls -lh ~/backups
```

- Verificar que **no están vacíos** (`gzip -t` en el `.sql.gz`; `tar -tzf … | head`) y **bajarlos** a una máquina local (hPanel o `scp`). Las imágenes y videos de productos viven en `public_html` y **no están en git**.
- hPanel → *Copias de seguridad* sirve de segunda red, **no** reemplaza esto.
- Anotar el commit actual del servidor (`git describe --tags --always`) — es el punto de retorno del paso 10.
- Guardar, además, el estado de los datos que toca el deploy (sirve para el paso 10):
  ```bash
  php artisan tinker --execute="foreach (['product_offers','discount_codes'] as \$t) { foreach (DB::table(\$t)->get(['id','start_date','end_date']) as \$r) echo \$t.' #'.\$r->id.'  '.\$r->start_date.' -> '.\$r->end_date.PHP_EOL; }" | tee ~/backups/vigencias-antes-$STAMP.txt
  ```

### Paso 3 — Pedidos pendientes de la web vieja

Los pedidos que ya existen **no tienen `rukoz_ref`**: el Central no los conoce y nunca los va a conocer. Siguen con el **flujo viejo** (en el admin: «Marcar como despachado» / «Cancelar»; nada de eso toca el Central). Contarlos **antes** de migrar:

```bash
php artisan tinker --execute="foreach (App\Models\Order::query()->selectRaw('estado, count(*) c')->groupBy('estado')->get() as \$r) echo \$r->estado.': '.\$r->c.PHP_EOL;"
```

(La copia de producción del 2026-10-19 tenía **0 pedidos**: si el día del deploy sigue en 0, este paso se cumple solo.)

Recomendación: **cerrar todos los pendientes el día anterior** (despachar o cancelar). Dos razones:

1. Un pedido viejo cancelado **después** de encender la integración repone el stock en la **columna local** (`StockService::reponer`), que desde el paso 7 es un espejo del Central: lo repuesto no existe en el Central y se pisa en el próximo evento.
2. Un pedido viejo despachado después del cambio **no descuenta** nada del Central: la mercadería ya comprometida tiene que estar descontada de lo que se cargó en el Central (paso 1).

Si quedan pendientes que **no se pueden cerrar**: anotar su detalle (productos y cantidades) y restar esa mercadería del stock disponible web en el Central antes del paso 7. Decide Matias (Anexo C).

### Paso 4 — Deploy de `main` con todo apagado

Objetivo: que la tienda quede **igual que hoy**, con el código nuevo y las tablas nuevas. Nada de integración.

1. **Modo mantenimiento (opcional pero recomendable):** `php artisan down --retry=60` (al final: `php artisan up`).
2. **Token de GitHub** del hosting en su `auth.json` (Anexo A): uno **propio, de solo lectura**.
3. **Código:**
   ```bash
   cd <PROYECTO>
   git status --short                                  # tiene que estar vacío
   git fetch --tags origin
   git checkout main && git pull --ff-only origin main # o el tag que se acuerde
   git describe --tags --always
   ```
4. **`.env`** (a mano, `nano .env`) — **todo apagado**. `RUKOZ_INTEGRACION=false`, `INTEGRACION_CATALOGO=false`, `INTEGRACION_STOCK=false`, `INTEGRACION_CHECKOUT=false`, `INTEGRACION_CONFIRMACION=false`. Revisar que sigan bien `APP_URL=https://chispasfrias.com`, `APP_ENV=production`, `APP_DEBUG=false`, `PRODUCT_IMAGES_PATH`, `VITE_PRODUCT_IMAGES_PATH`, `DOCUMENTS_PDF_PATH` (desde la 10.0 se respetan con `config:cache`). **No cambiar `APP_TIMEZONE`.**
5. **Dependencias:**
   ```bash
   composer2 install --no-dev -o --no-interaction      # --no-interaction: falla en vez de pedir credenciales si falta el token
   php artisan about | head -20                        # PHP, entorno production, debug off
   composer2 show rukoz/core-client | grep -E "versions|source|dist"   # 0.1.2
   ```
6. **Migraciones.** Antes, mirar qué va a correr y contar las vigencias (ver el cuadro): `php artisan migrate --status | tail -15`. Con el backup del paso 2 hecho:
   ```bash
   php artisan migrate --force
   ```
   **Migraciones nuevas respecto de `produccion-pre-integracion`** (7 de la web, todas con `down()`):

   | Migración | Qué hace | Tipo |
   |---|---|---|
   | `2026_10_19_120000_change_users_role_default_to_cliente` | `users.role`: el default pasa de `admin` a `cliente`. No toca filas. | esquema (default) |
   | `2026_10_19_120100_add_discount_usage_timestamps_to_orders_table` | `orders.discount_usage_consumido_at` y `…_liberado_at`, nullable. | esquema, aditiva |
   | `2026_10_19_130000_corregir_vigencias_a_utc` | **Corrige datos, una sola vez:** suma **3 h** a `start_date`/`end_date` de `product_offers` y `discount_codes` (los `NULL` no se tocan). Antes se guardaba la hora argentina tipeada como si fuera UTC; desde la 10.0 el back guarda en UTC. | **datos** |
   | `2026_10_20_120000_add_rukoz_reserva_to_orders_table` | `orders.rukoz_ref` (única), `rukoz_version`, `rukoz_estado`, `motivo_cancelacion`. Todas nullable. | esquema, aditiva |
   | `2026_10_21_120000_add_confirmacion_de_venta_to_orders_table` | 16 columnas de confirmación/seguimiento en `orders` (`confirmado_at`, `rukoz_numero_venta`, `rukoz_saldo`, …) y `users.rukoz_vendedor_id`. Todas nullable. | esquema, aditiva |
   | `2026_10_26_120000_checkout_minimo_en_orders` | CHK-2b: columna nueva `orders.tipo_entrega` (envío / retiro en oficina) y `lastname`, `dni`, `province`, `postal_code`, `email` pasan a admitir NULL (el checkout ya no los pide; el DNI, la localidad y el CP se completan al confirmar la venta). No toca filas. Su `down()` falla si ya hay pedidos con esos campos vacíos: la vuelta atrás es apagar la funcionalidad, no revertirla (paso 10). | esquema, relaja columnas |
   | `2026_10_27_120000_create_product_compatible_table` | CHK-2c: tabla `product_compatible` (productos compatibles que se ofrecen en el carrito) y `products.sugerir_en_carrito`. | esquema, aditiva |

   Más las del **paquete** (se cargan aunque la integración esté apagada, está bien): `rukoz_requests`, `rukoz_inbox`, `rukoz_estado`, `rukoz_pedidos`, `rukoz_stock`, `rukoz_compensaciones` y la columna `revisado_at` de `rukoz_pedidos` (7 archivos).

   > ⚠️ **La de vigencias no se repite ni se corre «por las dudas»:** cada ejecución suma 3 h otra vez. `migrate --force` la corre una sola vez porque queda registrada; no hacer `migrate:rollback` + `migrate` en el medio. La copia del 2026-10-19 tenía **0 ofertas y 0 cupones**, así que probablemente no cambie ninguna fila: confirmarlo con el archivo `vigencias-antes-*.txt` del paso 2 (si está vacío, no hay nada que corregir y el paso 10 se simplifica).
7. **Front y cachés:**
   - `npm run build` **en local**, y copiar `public/build` a `<PROYECTO>/public/build` **y** a `public_html/build` (las dos).
   - **Borrar el `robots.txt` viejo de `public_html`** (`rm ../public_html/robots.txt`): desde la 10.0 `/robots.txt` y `/sitemap.xml` los sirve la app con la URL de `APP_URL`; un archivo estático los tapa.
   - ```bash
     php artisan config:cache && php artisan route:cache && php artisan view:cache
     ```
   - Comprobar que no se rompió el `index.php` de `public_html` (apunta al `vendor` y `bootstrap` del proyecto).
8. `php artisan up` si se había puesto en mantenimiento.

**Verificar que la tienda funciona igual que antes (obligatorio):**

- [ ] `https://chispasfrias.com/` carga; `/productos` lista productos con fotos, precios y stock. **Las imágenes cargan** (confirma `PRODUCT_IMAGES_PATH` con la config cacheada).
- [ ] Un producto con variantes, uno sin variantes y (si hay) un combo se abren; el carrito suma bien; los botones de WhatsApp tienen el número correcto.
- [ ] `https://chispasfrias.com/robots.txt` responde con el contenido de la app y `https://chispasfrias.com/sitemap.xml` con URLs de `chispasfrias.com`.
- [ ] Un **pedido de prueba completo** hasta el mensaje de WhatsApp, y cancelarlo después. Debe crearse en el flujo **viejo**: descuenta stock local si es finito y **no** llama al Central.
- [ ] El admin carga (pedidos, productos, usuarios, ofertas, cupones, documentos); «Marcar como despachado» y «Cancelar» funcionan sobre el pedido de prueba.
- [ ] `php artisan schedule:list` — sin tareas de integración (están apagadas).
- [ ] `tail -n 50 storage/logs/laravel.log` — sin errores nuevos.
- [ ] `php artisan chispasfrias:integracion-estado` — todo «apagado», sin errores.
- [ ] El cron de `schedule:run` está instalado y corriendo (log del cron o un `schedule:run` a mano).

**Verificar el checkout, el carrito y las escalas (CHK-2b y CHK-2c, obligatorio):**

- [ ] `php artisan migrate:status` muestra corridas `checkout_minimo_en_orders` y `create_product_compatible_table`.
- [ ] **Checkout de envío:** pide solo nombre y apellido, WhatsApp y provincia; el selector tiene **24 provincias** y dice «Solo hacemos envíos a sucursal, no a domicilio». **Retiro en oficina** no pide provincia. «No soy yo · borrar mis datos» limpia el formulario.
- [ ] **Escalas por producto:** con una bengala (o un producto con colores y escalas) sumar 10 de un color y 10 de otro: cruzan el escalón de 20 y el total es el mismo en carrito, checkout, orden del admin y, con la integración encendida, en la reserva y en la venta del Central. «Sumá N más y pagás $X c/u» promete exactamente ese precio; quitar una línea vuelve al escalón anterior.
- [ ] **Stock en el carrito:** una línea por encima del stock se ajusta con aviso («Bajamos la cantidad de 3 a 2…»); una línea agotada («Sin stock») bloquea «Continuar pedido» y el envío.
- [ ] **Compatibles:** en Admin → Productos → Editar, «Productos compatibles» (y «Sugerir siempre en el carrito» si corresponde). Con un producto con compatibles en el carrito aparece «Compatible con tu pedido»; los que tienen colores dicen «Elegir color» y llevan a la ficha.
- [ ] **Mobile (celular real):** la barra «Total / Continuar pedido» queda fija y visible sin scroll, respeta el área segura del iPhone, y los botones flotantes (carrito y WhatsApp) se retiran mientras se ve un selector de cantidad. La información de cuotas («hasta N cuotas con tarjeta») es solo informativa.
- [ ] `https://chispasfrias.com/` sin desborde horizontal a 390 px y sin avisos de CSP (Content-Security-Policy-Report-Only: `storage/logs/seguridad.log` no recibe reportes propios).

Si algo no es igual: **paso 10**, no avanzar.

### Paso 4b — Seguridad del día del deploy (S.2)

Se hace **justo después del Paso 4** (con la tienda ya andando como antes) y **antes de encender nada del Central**. Qué es cada cosa y por qué: [`SEGURIDAD.md`](SEGURIDAD.md). Cada punto termina en algo que se puede ver.

1. **`.env` de producción** (`nano .env`; después `chmod 600 .env`). Revisar estas variables (las nuevas son opcionales y tienen un valor seguro por defecto):

   | Variable | Valor | Por qué |
   |---|---|---|
   | `APP_ENV` / `APP_DEBUG` | `production` / **`false`** | con `true` una página de error muestra el código, las variables y las credenciales |
   | `APP_URL` | `https://chispasfrias.com` | también fuerza `https` en las URLs que genera la app |
   | `SESSION_SECURE_COOKIE` | **`true`** | la cookie de sesión solo viaja por HTTPS |
   | `SESSION_ENCRYPT` | `true` (recomendado) | la sesión guarda el carrito; **cierra las sesiones abiertas** (se vuelve a entrar) |
   | `MAIL_MAILER` y `MAIL_*` | **SMTP real** (no `log`) | si no, «olvidé mi contraseña» no entrega nada |
   | `LOG_LEVEL` | `warning` o `error` (no `debug`) | menos ruido y menos datos en el log |
   | `SEGURIDAD_CSP` | `report-only` (por defecto) | la CSP avisa pero no bloquea; ver el paso 8 |
   | `SEGURIDAD_ADMIN_INACTIVIDAD_MINUTOS` | `60` (por defecto) | el panel cierra la sesión tras 60 min sin actividad |
   | `THROTTLE_LOGIN_POR_MINUTO`, `THROTTLE_REGISTRO_POR_MINUTO`, `THROTTLE_RECUPERAR_PASSWORD_POR_MINUTO` | `20` / `5` / `5` (por defecto) | dependen de la IP real del visitante: **ver 1.9** |

   Después: `php artisan config:cache`.

2. **`.htaccess` de `public_html`** (el real; ver «Qué cambia» en `SEGURIDAD.md → Cabeceras, CSP y .htaccess`). **No copiar el archivo del repo encima.**
   1. Backup **fuera** de `public_html`: `cp ../public_html/.htaccess ~/backups/htaccess-$(date +%F)` (una copia dentro de `public_html` sería un archivo más para filtrar).
   2. Abrir el `public/.htaccess` del repo y pegar en el de `public_html`, **arriba de todo lo demás**, los dos bloques `# --- Archivos que nunca se sirven` y `# --- Cabeceras`. Si el de `public_html` ya tiene `RewriteEngine On`, se puede quitar de nuestro bloque; si ya tiene reglas de HTTPS, que queden **antes**.
   3. Probar que la web sigue cargando (inicio, una ficha, el carrito, una imagen) y que no hay un `500`. **Si da `500`:** volver al backup (`cp ~/backups/htaccess-… ../public_html/.htaccess`), anotar el mensaje de `error_log` y avisar: casi siempre es una directiva que el hosting no admite.
   4. Comprobar las cabeceras (la app las manda también, el `.htaccess` las agrega a lo estático):
      ```bash
      curl -sI https://chispasfrias.com/ | grep -iE "strict-transport|x-frame|x-content-type|referrer-policy|permissions-policy|content-security-policy"
      curl -sI https://chispasfrias.com/images/chispas-frias-logo.png | grep -iE "x-frame|x-content-type"
      ```

3. **Subidas sin PHP.** Con el código nuevo la carpeta se protege sola en la primera subida, pero las que ya existen hay que repararlas una vez:
   ```bash
   php artisan chispasfrias:seguridad-verificar --reparar
   ls -la ../public_html/images/products/.htaccess ../public_html/docs/pdfs/.htaccess
   ```
   **Luego abrir una ficha de producto y comprobar que las fotos siguen cargando.** Si las fotos dan error `500`/`403` es que el hosting no admite alguna directiva del `.htaccess` de esa carpeta: borrarlo (`rm ../public_html/images/products/.htaccess`), avisar, y recordar que sin él la protección queda en la extensión por contenido.

4. **La verificación completa, desde el servidor, probando de afuera:**
   ```bash
   php artisan chispasfrias:seguridad-verificar --url=https://chispasfrias.com
   ```
   Tiene que terminar con **«Todo en orden»**. Qué mira: `APP_ENV`/`APP_DEBUG`/`APP_KEY`/`APP_URL`, cookie segura, mail real, TLS del Central; que no haya `.env`, `.sql`, `.zip`, `composer.json` ni carpetas `docs/test-*` en la raíz web; que no haya PHP en las subidas; y, **pidiéndolas por HTTP**, que `/.env`, `/.git/config`, `/storage/logs/laravel.log`, `/vendor/autoload.php`, `/composer.json`, los respaldos, etc. den 403/404, y que **un PHP puesto en la carpeta de imágenes no se ejecute** (lo crea y lo borra solo). Todo lo que diga `MAL` se resuelve antes de seguir.

5. **Revisión del hosting** (hPanel; la hace quien tenga acceso, marcar cada una):
   - [ ] **Forzar HTTPS** activado (hPanel → Seguridad → SSL → *Forzar HTTPS*) y el certificado vigente. `curl -sI http://chispasfrias.com/ | head -3` redirige (301) a `https://`.
   - [ ] La **raíz del documento** del dominio es `public_html` y el proyecto (con el `.env`) está **fuera**, como hermano. `ls ../public_html/.env` → no existe.
   - [ ] **Listado de directorios apagado** (`Options -Indexes`, ya está en el `.htaccess`): `curl -s https://chispasfrias.com/images/ | head` no muestra una lista de archivos.
   - [ ] **PHP:** versión con soporte (8.2+), `display_errors` apagado (hPanel → Avanzado → Configuración de PHP) y, si el panel lo ofrece, `expose_php` apagado.
   - [ ] **WAF / ModSecurity** del hosting activado (hPanel → Seguridad), sin reglas que bloqueen `POST /rukoz/webhook` (ver 1.5).
   - [ ] **Base de datos:** acceso remoto de MySQL **desactivado** (o limitado a tu IP) y el usuario de la web con permisos solo sobre su base.
   - [ ] **Backups** automáticos de Hostinger activos, **más** el backup propio del Paso 2 guardado fuera del servidor.
   - [ ] **2FA** activada en la cuenta de Hostinger y en la de GitHub (la que tiene el token del paquete); sin claves SSH que ya no se usen.
   - [ ] Permisos: `.env` en `600`/`640`; `storage/` y `bootstrap/cache` escribibles pero **no** `777`; sin `phpinfo`, `info.php`, `adminer`, `phpmyadmin` ni archivos de prueba en `public_html` (`ls ../public_html`).

6. **Contraseñas del panel.** La política nueva (10 caracteres, mayúsculas, minúsculas y números) se exige **cuando se cambia** la contraseña, no retroactivamente. Cada admin y vendedor entra, va a **Perfil → Contraseña** y la cambia (así también se cierran las sesiones viejas). Los vendedores nuevos nacen con una contraseña temporal fuerte que se muestra una sola vez.

7. **Recuperar contraseña con mail real:** pedirlo para tu cuenta, que llegue el mail, que el enlace funcione y que, al pedirlo para un mail inexistente, la pantalla diga exactamente lo mismo.

8. **CSP en modo solo reportar.** Recorrer la tienda y el panel (inicio, catálogo, ficha con y sin variantes, combo, carrito, checkout, y en el panel Productos con el editor, Pedidos y Documentos) con la consola del navegador abierta. Los avisos de la CSP quedan en `storage/logs/seguridad.log`:
   ```bash
   tail -n 20 storage/logs/seguridad.log     # lo esperable: vacío, o extensiones del navegador (chrome-extension://…)
   ```
   Si aparece algo de la propia tienda (un origen que falta), se suma por `.env` (`SEGURIDAD_CSP_EXTRA_*`). **El paso a `enforce` es una decisión posterior** (después de ~2 semanas sin avisos y confirmando que el hosting no cachea HTML): procedimiento en `SEGURIDAD.md`.

9. **Webhook y secretos** (cuando se llegue al Paso 5): `RUKOZ_CENTRAL_URL` con `https://` (`php artisan rukoz:ping` y `chispasfrias:integracion-estado` avisan en rojo si no); `RUKOZ_API_KEY`/`RUKOZ_SECRET` solo en el `.env`; `RUKOZ_SECRET_ANTERIOR` **vacío** salvo durante una rotación. El procedimiento de rotación está en `SEGURIDAD.md → Rotación de secretos, paso a paso`.

10. **Anotar** en el cuaderno de deploy: fecha, resultado de `seguridad-verificar` (pegar la salida), quién revisó el hosting y qué quedó pendiente.

**Verificar (obligatorio antes del Paso 5):**

- [ ] `php artisan chispasfrias:seguridad-verificar --url=https://chispasfrias.com` → «Todo en orden».
- [ ] La tienda y el panel funcionan igual que antes (inicio, ficha con fotos, carrito, pedido de prueba, panel de pedidos).
- [ ] `curl -sI https://chispasfrias.com/` muestra HSTS, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` y `Content-Security-Policy-Report-Only`.
- [ ] Un error provocado a propósito (`/productos/999999`) muestra la página «no existe», **sin** trazas ni rutas del servidor.
- [ ] Todos los admin y vendedores cambiaron su contraseña.

Si algo de esto rompe la tienda: restaurar el `.htaccess` de `public_html` del backup y `SEGURIDAD_CSP=off` + `config:cache`; la tienda vuelve a como estaba. No avanzar al Paso 5 hasta resolverlo.

### Paso 5 — Variables del Central y `rukoz:ping`

Con la tienda verificada, **recién ahora** se configura la conexión (todavía sin encender nada de la web). En `.env`:

```
RUKOZ_CENTRAL_URL=https://sistema.rukoz.com.ar
RUKOZ_API_KEY=<API key de la integración «Web Chispas Frías»>
RUKOZ_SECRET=<secreto de la integración>
RUKOZ_WEBHOOK_RECIBE=true                 # false solo si el chequeo 1.5 dio que no se pueden recibir webhooks (el feed pasa a leerse cada minuto)
APP_URL=https://chispasfrias.com

# Valores que el modal «Confirmar venta» preselecciona (ids del Central; ver `php artisan rukoz:referencias`).
INTEGRACION_FORMA_ENVIO_ID=<id>
INTEGRACION_TRANSPORTISTA_ID=<id>
```

Después de editar: `php artisan config:cache` y

```bash
php artisan rukoz:ping            # → «Conexión con el Central OK»
php artisan rukoz:referencias     # cuentas, medios de pago, formas de envío, transportistas, vendedores
```

- `rukoz:ping` **tiene que dar OK** antes de seguir. Si falla: 1.4 (salida), URL, clave/secreto (copiados sin espacios), reloj del servidor (la firma tolera 300 s).
- De `rukoz:referencias` salen los ids de forma de envío y transportista para el `.env`.

Apagada, `/rukoz/webhook` no existe: se prueba en el paso 6, al encender `RUKOZ_INTEGRACION`.

### Paso 6 — Catálogo

```
RUKOZ_INTEGRACION=true
INTEGRACION_CATALOGO=true
```

```bash
php artisan config:cache
php artisan rukoz:sync-catalogo       # resumen: enviados, sin vincular
```

Con `RUKOZ_INTEGRACION=true` el paquete empieza a programar sus tareas (necesitan el cron del paso 4).

**En el Central** (*Integraciones → Web Chispas Frías → Productos web*): **rehacer los vínculos** según [`VINCULOS-APROBADOS-CHISPAS-FRIAS.md`](VINCULOS-APROBADOS-CHISPAS-FRIAS.md), **por nombre** (y color), no por id.

> ⚠️ Los ids de productos y variantes de **producción** no tienen por qué coincidir con los de la copia local con la que se aprobó la tabla. **Antes de aplicar, se le muestra a Matias el dry-run** (la tabla resultante producción-por-nombre → artículo del Central), y se aplica recién con su OK. La tabla aprobada se aplica tal cual, no se reinterpreta.
>
> Resultado esperado (central de prueba, sobre una copia de producción de 13 productos y 26 variantes): **34 vinculados + 5 `no_aplica`** (los productos padre con variantes, que el Central marca solo) = 39, **0 sin vincular**. Dos puntos especiales: los dos cartuchos PULY® (Dorado y Negro) van al **mismo** artículo (RKZ-0009) y comparten disponible; y la pistola «Otro color» va a un artículo simple (Pistola Personalizada, RKZ-0043) con el color en la nota del ítem. Si en producción los números difieren, revisar el catálogo antes de seguir.

Después:

```bash
php artisan rukoz:sync-catalogo       # repetir: tiene que dar 0 sin vincular
```

Criterio para seguir: **«sin vincular = 0»**. Verificar el webhook ahora que la ruta existe (1.5):
`curl -sS -o /dev/null -w "%{http_code}\n" -X POST -H "Content-Type: application/json" -d '{}' https://chispasfrias.com/rukoz/webhook` → **`401`**.

### Paso 7 — Stock + checkout + confirmación, juntos

`INTEGRACION_STOCK`, `INTEGRACION_CHECKOUT` e `INTEGRACION_CONFIRMACION` se encienden **juntos y en este orden**. **Nunca `INTEGRACION_STOCK` solo en producción:** la tienda mostraría el stock del Central pero el checkout seguiría descontando el local, que el próximo evento pisa.

1. **Cargar el espejo** (con `INTEGRACION_STOCK=true`; todavía no checkout ni confirmación):
   ```bash
   # .env: INTEGRACION_STOCK=true
   php artisan config:cache
   php artisan chispasfrias:stock-cargar       # GET /stock → rukoz_stock → columnas de stock. Resumen: actualizados, sin cambios, ilimitados, sin vincular
   php artisan chispasfrias:stock-sin-espejo   # tiene que dar 0 y salir con código 0
   ```
   `stock-sin-espejo` en **0** es la condición para seguir (ítems activos sin fila en `rukoz_stock` o con stock distinto). Si hay alguno, se vincula en el Central y se repite.
   > Este paso **pisa el stock de la tienda**: hoy los 13 productos tienen 9999 y las variantes `NULL` (ilimitado). Desde acá el stock mostrado es el **«Disponible web» del Central**. Revisar la tienda con ojos de cliente: lo que antes se podía pedir y ahora dice «sin stock» es lo que está en 0 en el Central.
2. Encender el resto:
   ```
   INTEGRACION_CHECKOUT=true
   INTEGRACION_CONFIRMACION=true
   ```
   ```bash
   php artisan config:cache
   php artisan chispasfrias:integracion-estado
   ```
   `INTEGRACION_CHECKOUT_SIN_RESERVA` queda en `false` (si el Central no responde **no se vende sin reserva**: el cliente ve el aviso con el botón de WhatsApp).
3. **`chispasfrias:integracion-estado` tiene que dar todo OK:** interruptores coherentes (el checkout exige stock y `RUKOZ_INTEGRACION`), ping, circuito cerrado, ítems sin espejo = 0, listener de estados registrado, reservas abiertas = 0, compensaciones pendientes y fallidas = 0. Sale con código 1 si algo está mal.
4. `php artisan rukoz:estado` — URL del Central, último sync de catálogo, cursor del feed, «Último webhook recibido» (dice `nunca` hasta el primer evento), inbox sin fallidos.

### Paso 8 — Mapeo de los vendedores reales

Cada usuario que vaya a **confirmar ventas** necesita el vendedor del Central con el que se presenta. Sin mapeo no se confirma (la web ni llama al Central). Producción tiene 2 usuarios (1 admin, 1 vendedor) según la copia del 2026-10-19.

```bash
php artisan chispasfrias:vendedor-mapear --listar                       # usuarios, su mapeo y los vendedores asignados en el Central
php artisan chispasfrias:vendedor-mapear <email|id> <id-vendedor-central>
```

También se puede desde *Admin → Vendedores → Editar*; el usuario admin con id 1 **solo por comando**. El `<id-vendedor-central>` sale de *Integraciones → Vendedores* y de `rukoz:referencias`. Un id **sin asignar** en el Central da `VENDEDOR_NO_MAPEADO` en el primer intento: el Central lo deja pendiente para administración. Verificar con `--listar` que los vendedores reales quedaron mapeados **y** asignados.

### Paso 9 — Un pedido real chico, de punta a punta

Un pedido de verdad (bajo valor, de un producto con stock en el Central), hecho en **producción** por alguien del equipo:

1. [ ] **Anotar** el disponible web del artículo en el Central **antes**.
2. [ ] En la tienda: agregar el producto, checkout, **«Finalizar por WhatsApp»**. Se crea sin error.
3. [ ] **Reserva:** el pedido aparece en el admin con `rukoz_ref` (`CFR-…`) y estado «reservado»; en el Central aparece **reservado** y el disponible web bajó lo pedido. (`chispasfrias:integracion-estado` → «reservas abiertas: 1».)
4. [ ] **Confirmar:** en el admin abrir el pedido → **«Confirmar venta»** → forma de envío, transportista, costo de envío cobrado, pagos (con o sin pago) y vendedor. La venta existe en el Central con número y el pedido pasa a solo lectura (número de venta, total, pagado, saldo). Probar «Despachar solo con pago completo» solo si la prueba deja saldo.
5. [ ] **Empaquetar y despachar en el Central:** el estado en la web **cambia solo** (webhook; si no llega, el feed lo trae en ≤ 5 min), con transportista y número/enlace de seguimiento.
6. [ ] `php artisan rukoz:estado`: «Último webhook recibido» con la hora de la prueba (si dice `nunca`, el webhook **no entró** y se vive del feed: volver a 1.5), inbox fallidos = 0.
7. [ ] Un **segundo pedido que se cancele** desde el admin antes de confirmar: la reserva se libera en el Central y el disponible vuelve a lo que estaba.
8. [ ] Las métricas del admin cuentan la venta confirmada (en el día de la confirmación) y no la reserva ni la cancelada.
9. [ ] El stock mostrado en la tienda coincide con el del Central después de cada paso (≤ 1–2 min con webhook).

Si algún paso falla: **no se deja corriendo**. Se apagan los interruptores (paso 10) y se diagnostica con `storage/logs/laravel.log`, `rukoz:estado` y el log de requests de la integración en el Central.

#### Paso 9b — La prueba del pedido real, desde un celular (envío y retiro)

Se hace **con un celular real** (iPhone y, si se puede, Android), en producción, y con el WhatsApp **real** de Chispas Frías: el mensaje que se abre es el de verdad, así que o se envía a propósito (avisando al equipo de que es una prueba) o se cierra sin enviar. Dos pedidos de bajo valor, de un producto con stock en el Central:

1. [ ] **Pedido de envío.** En el celular: agregar el producto, ir al checkout, elegir «Envío», nombre, WhatsApp y provincia, y enviar. Se abre WhatsApp con «Pedido #N», «Envío (provincia)» y el detalle; el pedido aparece en el admin como **reservado** y en el Central como reservado (ver el paso 9, punto 3).
2. [ ] **Pedido de retiro.** Lo mismo con «Retiro en oficina» (sin provincia): el mensaje dice «Retiro en oficina · Buenos Aires».
3. [ ] **Confirmar el de envío, con recargo.** En el admin: pedido → «Confirmar venta». Como es un envío con transportista, el modal pide **DNI, localidad y código postal**: completarlos y que el botón se habilite (se guardan en el Central antes de confirmar). Agregar un pago, elegir en «Calcular con un plan de tarjeta» un plan (por ejemplo 3 cuotas) para cargar el **recargo del medio de pago** y confirmar.
4. [ ] **Verificar en el Central:** la venta (`CFR-…`) existe con el total del pedido; el **recargo** figura aparte del total; y en **Finanzas → Caja** (libro de la cuenta del pago) aparecen **dos filas** de esa venta: «Cobro · cliente» por el monto pagado y «Recargo del medio de pago» por el recargo.
5. [ ] **Confirmar el de retiro, sin datos ni recargo:** el modal dice «Es un retiro en oficina: no hace falta DNI, localidad ni código postal», no pide recargo y se confirma sin pagos (queda con saldo) o con el pago completo.
6. [ ] **Editar datos desde la tarjeta** (en un pedido todavía reservado): «Editar datos» → completar DNI, email y localidad → «Guardar datos». Los ítems y el total no cambian y la versión de la reserva en el Central sube en uno.
7. [ ] **Central caído (opcional, con aviso):** si se puede apagar el Central unos minutos, un checkout devuelve el mensaje «No pudimos confirmar el stock en este momento…» con el botón de WhatsApp, y **no** se crea el pedido (`rukoz:compensaciones` resuelve la reserva dudosa).
8. [ ] Los dos pedidos de prueba quedan **cancelados o anulados** al terminar, para que el stock vuelva a lo que estaba.

### Paso 10 — Vuelta atrás

**Nivel 1 — Apagar los interruptores (sin código ni migraciones).** La web vuelve al flujo local:

```
INTEGRACION_CONFIRMACION=false
INTEGRACION_CHECKOUT=false
INTEGRACION_STOCK=false
INTEGRACION_CATALOGO=false
RUKOZ_INTEGRACION=false
```
```bash
php artisan config:cache
```

Con `INTEGRACION_CHECKOUT=false` los pedidos nuevos vuelven al **stock local**. Con el espejo apagado el stock de las columnas queda con los **últimos valores que vinieron del Central**: revisarlo y corregirlo a mano en el admin antes de volver a vender (los productos venían con 9999 y las variantes con `NULL` = ilimitado).

**Los pedidos creados mientras estuvo encendido** (los que tienen `rukoz_ref`):

| Situación | Qué hacer |
|---|---|
| **Reservado, sin confirmar** | La reserva sigue apartando stock en el Central. Cancelarlo **desde el admin con la integración encendida** (libera la reserva), o cancelar en el Central. Con la integración apagada `chispasfrias:cancelar-reservas-viejas` **no** corre. |
| **Confirmado** (venta en el Central) | Existe en el Central y se gestiona **allá**. En la web queda de solo lectura; sin webhook, el estado no se actualiza más. |
| **Pedidos nuevos durante la vuelta atrás** | Sin `rukoz_ref`: flujo viejo, stock local. El Central no se entera: cargarlos a mano si hace falta. |

**Nivel 2 — Volver al código anterior (último recurso).**

```bash
cd <PROYECTO>
php artisan down
git checkout produccion-pre-integracion
composer2 install --no-dev -o --no-interaction
# restaurar public/build y public_html/build de la versión anterior (del backup del paso 2)
# reponer public_html/robots.txt del backup si se quiere el estático anterior
php artisan config:clear && php artisan route:clear && php artisan view:clear
php artisan up
```

- **No se hace `migrate:rollback`.** Las migraciones de esquema son aditivas (columnas y tablas nuevas, nullable) y el código viejo las ignora; la base **no se toca**. Revertir la base solo con el backup del paso 2 y **solo si hay un daño real** (se pierde todo lo creado desde el backup).
- **Cuidado con la migración de datos de vigencias** (`corregir_vigencias_a_utc`): el código viejo guarda y lee las vigencias «tal cual se tipearon» (hora argentina como si fuera UTC). Al volver atrás las filas que la migración corrió **+3 h** quedan 3 h tarde: una oferta «hasta 23:59» vencería a las 02:59 del día siguiente. Y lo que se cargue con el código nuevo después del deploy también está en UTC real. Qué hacer según el caso:
  - **No había ofertas ni cupones con fecha** (`vigencias-antes-*.txt` vacío, que es lo esperado): no pasa nada, no hay nada que corregir.
  - **Había y se vuelve atrás de inmediato**: restaurar las fechas del archivo `vigencias-antes-*.txt` o restar 3 h solo a las filas existentes: `UPDATE product_offers SET start_date = start_date - INTERVAL 3 HOUR, end_date = end_date - INTERVAL 3 HOUR;` y lo mismo con `discount_codes`. Los `NULL` quedan `NULL`.
  - **Se vuelve atrás después de días y se crearon ofertas/cupones nuevos:** esas ya están en UTC; **no** restarles 3 h a ciegas, revisarlas una por una en el admin.
  - En cualquier caso: **nunca correr dos veces** esa migración (ni `migrate:rollback` seguido de `migrate`).
- Los pedidos creados con la integración encendida **quedan en la base** con sus columnas `rukoz_*` y se ven como pedidos normales para el código viejo, que no sabe de reservas ni de confirmación: tratarlos como en el Nivel 1.
### Paso 11 — Monitoreo de los primeros días

Al menos dos veces por día la primera semana, y después de cualquier cambio de configuración.

| Qué mirar | Cómo | Normal / alarma |
|---|---|---|
| Estado general | `php artisan chispasfrias:integracion-estado` | todo OK; **alarma**: ping fallido, circuito abierto, ítems sin espejo, listener sin registrar |
| Estado del paquete | `php artisan rukoz:estado` | «Último webhook recibido» reciente; inbox fallidos = 0; circuito cerrado |
| Eventos con problemas | `php artisan rukoz:inbox --estado=fallido` | cualquier fallido: ver motivo, corregir y reprocesar con `rukoz:inbox --reintentar=<event_id>` |
| Log de la web | `tail -n 200 storage/logs/laravel.log \| grep -iE "critical\|error\|rukoz\|integracion"` | **alarma**: `critical` (se confirmó en el Central y falló el guardado local; la venta existe allá y el estado llega por evento o `rukoz:revisar-pedidos --reconciliar`); warning de «combinación inválida de interruptores» |
| Reservas sin confirmar | admin → Pedidos (pendientes); `php artisan chispasfrias:cancelar-reservas-viejas --dry-run` | las que pasan los 3 días las cancela solo (cada hora) |
| Stock | `php artisan chispasfrias:stock-sin-espejo` (debe dar 0) | distinto de 0: producto nuevo o cambiado sin vincular en el Central |
| Catálogo | `php artisan rukoz:sync-catalogo` o «Último sync de catálogo» en `rukoz:estado` | un producto nuevo «sin vincular» no vende hasta vincularlo |
| Webhooks con error **en el Central** | *Integraciones → Web Chispas Frías → Webhooks / Requests* | entregas 4xx/5xx o con reintentos: normalmente 1.5 (redirección, WAF, certificado) |
| Throttle | quejas de «Estás haciendo muchos intentos seguidos» | si hay varias: revisar 1.9 (¿todos comparten una IP?) |
| Cron | «Última lectura del feed» en `rukoz:estado` | más de 10 min: el cron se cayó |
| Ventas | comparar los pedidos confirmados del admin contra el Central | tienen que coincidir (los viejos sin `rukoz_ref` no cuentan) |

Después de una semana sin alarmas, la guía queda como referencia.

---

## Anexo A — Token de GitHub para el paquete privado

`rukoz/core-client` es un repositorio **privado**: Composer necesita un token para bajarlo, en desarrollo y en el hosting. **Token fine-grained, de solo lectura y solo para ese repo, propio del hosting.**

1. GitHub (cuenta `mArchilli`) → *Settings → Developer settings → Personal access tokens → **Fine-grained tokens** → Generate new token*.
2. **Resource owner:** `mArchilli`. **Expiration:** 90 días (hay que renovarlo).
3. **Repository access → Only select repositories** → `rukoz-core-client`.
4. **Permissions → Repository permissions → Contents: Read-only** (*Metadata: Read-only* se agrega solo). **Nada más.**
5. Copiar el token (`github_pat_…`; se ve **una sola vez**).
6. Cargarlo **en la cuenta del hosting**, fuera del repo:
   ```bash
   composer2 config --global github-oauth.github.com <token>     # queda en ~/.composer/auth.json (o ~/.config/composer/auth.json)
   ```

Reglas:

- **El token nunca va al repo.** `auth.json` está en `.gitignore` (`/auth.json`); no se escribe en `composer.json` ni se pasa por línea de comandos en un script versionado. No dejar `auth.json` dentro de `<PROYECTO>`.
- **Uno por lugar** (desarrollo y hosting): se revocan por separado. Si se filtra o se cambia de equipo: revocarlo en GitHub y generar otro.
- Verificar el acceso sin instalar nada: `git ls-remote --tags https://github.com/mArchilli/rukoz-core-client.git` (con credenciales) o `composer2 show -a rukoz/core-client` tiene que listar `v0.1.2`.
- Ojo: el `composer.lock` guarda el paquete con `source` por SSH (`git@github.com:mArchilli/rukoz-core-client.git`) y `dist` por HTTPS (`api.github.com/…/zipball/…`). Con `preferred-install: dist` (lo que trae `composer.json`) Composer baja el zip por HTTPS con el token y no necesita clave SSH. Si apareciera *Permission denied (publickey)*, es que cayó al `source`: se resuelve con `composer2 install --no-dev -o --prefer-dist`. Conviene probarlo antes del día del deploy, en una carpeta de prueba del hosting.
- En el hosting instalar siempre con `--no-interaction`: si falta el token falla en vez de quedarse esperando.

## Anexo B — Referencia rápida de las variables

| Variable | Valor en producción | Cuándo |
|---|---|---|
| `APP_URL` | `https://chispasfrias.com` | paso 4/5 (revisar) |
| `APP_TIMEZONE` | **no tocar** (`UTC` por defecto) | — |
| `APP_DISPLAY_TIMEZONE` | opcional (`America/Argentina/Buenos_Aires` por defecto) | — |
| `PRODUCT_IMAGES_PATH`, `VITE_PRODUCT_IMAGES_PATH` | los de hoy (`/../../public_html/images/products/`) | paso 4 (revisar) |
| `DOCUMENTS_PDF_PATH` | el de hoy (`docs/pdfs/`) | paso 4 (revisar) |
| `WHATSAPP_NUMERO` | opcional (tiene default) | — |
| `THROTTLE_CHECKOUT_POR_MINUTO` / `THROTTLE_VALIDAR_CUPON_POR_MINUTO` | opcionales (10 / 30) | 1.9 |
| `THROTTLE_SUGERENCIAS_POR_MINUTO` | opcional (60): productos compatibles que se ofrecen en el carrito | CHK-2c |
| `APP_ENV`, `APP_DEBUG` | `production`, **`false`** | paso 4b |
| `SESSION_SECURE_COOKIE`, `SESSION_ENCRYPT` | `true`, `true` | paso 4b |
| `SEGURIDAD_CSP` | `report-only` (default) → `enforce` más adelante; `off` apaga la CSP | paso 4b / 8 |
| `SEGURIDAD_CSP_EXTRA_SCRIPT`, `_STYLE`, `_IMG`, `_FONT`, `_CONNECT`, `_FRAME` | opcionales: orígenes extra de la CSP, separados por coma (analytics, píxeles, Mercado Pago…) | si se suma un tercero |
| `SEGURIDAD_ADMIN_INACTIVIDAD_MINUTOS` | `60` (default; `0` = sin límite adicional) | paso 4b |
| `SEGURIDAD_PASSWORD_STAFF_MINIMO` | `10` (default) | paso 4b |
| `SEGURIDAD_HSTS_SEGUNDOS`, `SEGURIDAD_HSTS_SUBDOMINIOS` | `31536000`, `false` (default) | paso 4b |
| `THROTTLE_LOGIN_POR_MINUTO`, `THROTTLE_REGISTRO_POR_MINUTO`, `THROTTLE_RECUPERAR_PASSWORD_POR_MINUTO`, `THROTTLE_CSP_REPORT_POR_MINUTO` | `20`, `5`, `5`, `30` (defaults); por IP, ver 1.9 | paso 4b |
| `RUKOZ_INTEGRACION` | `false` → `true` | paso 4 → paso 6 |
| `RUKOZ_SECRET_ANTERIOR` | **vacío**; solo durante una rotación de secretos (24 h) | `SEGURIDAD.md` |
| `RUKOZ_CENTRAL_URL` | `https://sistema.rukoz.com.ar` (provisorio) | paso 5 |
| `RUKOZ_API_KEY`, `RUKOZ_SECRET` | los de la integración «Web Chispas Frías» | paso 5 |
| `RUKOZ_WEBHOOK_RECIBE` | `true` (o `false` si el webhook no entra) | paso 5 |
| `INTEGRACION_CATALOGO` | `false` → `true` | paso 4 → paso 6 |
| `INTEGRACION_STOCK` | `false` → `true` | paso 7 |
| `INTEGRACION_CHECKOUT` | `false` → `true` | paso 7 |
| `INTEGRACION_CONFIRMACION` | `false` → `true` | paso 7 |
| `INTEGRACION_CHECKOUT_SIN_RESERVA` | `false` | siempre |
| `PEDIDOS_CANCELAR_DIAS` | `3` (default) | — |
| `INTEGRACION_FORMA_ENVIO_ID`, `INTEGRACION_TRANSPORTISTA_ID` | ids del Central (`rukoz:referencias`) | paso 5 |
| `INTEGRACION_REFERENCIAS_RESPALDO` | opcional (JSON), si el modal tiene que funcionar con el Central caído | opcional |
| `SEED_ADMIN_*` | **no usar en producción** (los seeders no corren en producción) | — |

`dias_aviso_reserva` (= 4) **no es una variable de la web**: se carga en la integración, en el Central.

Los comandos útiles: `rukoz:ping`, `rukoz:referencias`, `rukoz:estado`, `rukoz:inbox`, `rukoz:sync-catalogo`, `rukoz:sync-stock`, `rukoz:circuito [--reset]`, `rukoz:compensaciones`, `rukoz:revisar-pedidos [--reconciliar]` (paquete) y `chispasfrias:integracion-estado`, `chispasfrias:stock-cargar [--local]`, `chispasfrias:stock-sin-espejo`, `chispasfrias:vendedor-mapear`, `chispasfrias:cancelar-reservas-viejas [--dry-run]` (web).

---

## Anexo C — Preguntas abiertas (las contesta Matias antes del día)

1. **PHP del hosting:** ¿CLI y web están en 8.2 o superior? ¿Están las extensiones? (1.1). Si la CLI trae un PHP viejo, ¿cuál es la ruta del 8.2+ para el cron y los comandos?
2. **Cron:** ¿Hostinger deja correr `schedule:run` cada minuto en este plan? Si no, ¿cuál es el mínimo? (1.3). Con un mínimo de más de 5 min no se enciende checkout ni confirmación.
3. **`www` o raíz:** ¿cuál es el dominio canónico que no redirige, `chispasfrias.com` o `www.chispasfrias.com`? De eso depende la URL del webhook (1.5). Se asume la raíz.
4. **Pedidos pendientes de la web vieja:** ¿cuántos hay el día anterior y se cierran? Si no, ¿se resta su mercadería del stock del Central? (paso 3). La copia de producción del 2026-10-19 tenía 0.
5. **IP real:** ¿hay un proxy/CDN delante del hosting? Si sí, ¿se agrega `trustProxies` antes del deploy o se sube el límite del checkout? (1.9).
6. **Token de GitHub:** ¿uno solo o uno por lugar (desarrollo y hosting)? Se recomienda uno por lugar (Anexo A). ¿Quién lo crea y lo carga en el hosting?
7. **Central de producción:** ¿ya está en el VPS con los datos y el stock reales, y con qué dominio definitivo? (`sistema.rukoz.com.ar` es provisorio.) ¿Quién crea la integración «Web Chispas Frías» y el transportista con su URL de seguimiento?
8. **Vendedores:** ¿quiénes confirman ventas (el vendedor real, el admin, ambos) y qué id tiene cada uno en el Central? (paso 8).
