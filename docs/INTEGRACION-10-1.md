# 10.1 — Base real, ajustes de la 10.0, paquete y catálogo

Rama `integracion-10-1` (desde `limpieza-pre-integracion`, ab5a53b). Dominio canónico: **`chispasfrias.com`**.

## Interruptores

`RUKOZ_INTEGRACION` (paquete `rukoz/core-client`) habilita la comunicación; cada `INTEGRACION_*` (`config/integracion.php`) habilita una parte de la web. **Todo apagado por defecto, en `.env.example` (comentado), en `phpunit.xml` y en producción.**

| Variable | Etapa | Qué habilita |
|---|---|---|
| `INTEGRACION_CATALOGO` | 10.1 | Mandar productos, variantes y combos al Central (completo y al guardar) |
| `INTEGRACION_STOCK` | 10.2 | Espejar el stock del Central |
| `INTEGRACION_CHECKOUT` | 10.3 | Reservar en el Central al crear un pedido |
| `INTEGRACION_CONFIRMACION` | 10.4 | Confirmar la venta y recibir estados |

Sin `INTEGRACION_CATALOGO` no se registra el `ProveedorCatalogo` (el paquete tampoco programa el sync diario) y guardar en el admin no llama al Central.

## Paquete

`composer.json`: repositorio `vcs` → `https://github.com/mArchilli/rukoz-core-client.git`, `rukoz/core-client: ^0.1.2`. En `composer.lock` figura la **0.1.2** (`5f265fc`), con `dist` en `api.github.com`; sin rutas locales (un test lo verifica). Las tablas `rukoz_*` (6) se migran con `php artisan migrate`. El webhook `POST /rukoz/webhook` lo registra el paquete **solo con `RUKOZ_INTEGRACION=true`**, fuera del grupo `web` (sin sesión ni CSRF); no hace falta `routes/api.php`.

## Ajustes que dejó la 10.0

- **Dominio**: ya no hay dominios escritos. `APP_URL` → `props.tienda.url` (Inertia) para el JSON-LD de `Welcome.jsx` y `Products/Show.jsx`; `robots.txt` y `sitemap.xml` los genera `SeoController` con `config('app.url')` (se borró `public/robots.txt`). El `sitemap.xml` **no existía** (el robots lo anunciaba igual): ahora lista inicio, catálogo, contacto, servicio, y productos y combos activos. Un test falla si `chispasfrias.com*` reaparece en `app/`, `routes/`, `resources/`, `config/`, `database/`, `public/`.
- **Vigencias de ofertas y cupones**: lo que tipea el admin (`datetime-local`) es hora argentina. `ZonaHoraria::deEntrada()` lo pasa a UTC al guardar (3 controllers); el front lo muestra y rellena el formulario en hora argentina (`utils/fecha.js`: `formatearFecha`, `aValorDeInput`, `inicioDeHoyParaInput`). Antes se guardaba «tal cual» y las pantallas leían con `timeZone: 'UTC'`, así que «hasta 31/10 23:59» vencía a las 20:59 argentinas. También: la validación `after_or_equal:today` de la oferta rápida usa el día argentino.
- **Migración de datos** `2026_10_19_130000_corregir_vigencias_a_utc`: suma 3 h (el desfase de Argentina, calculado) a `product_offers.start_date/end_date` y `discount_codes.start_date/end_date`; los NULL no se tocan; `down()` resta. Corre una sola vez (tabla `migrations`). **Filas corregidas en la copia real: 0** (la copia no tiene ofertas ni cupones); en la base de desarrollo tampoco hay. Test: oferta y cupón «hasta 31/10 23:59» siguen vigentes a las 23:59:59 argentinas y vencen a las 00:00.
- **Tests de concurrencia (MySQL real)**: opt-in. Grupo `mysql` + `RUN_MYSQL_LOCK_TEST=1`; si no, se saltean. Siempre sobre bases propias (`chispas_frias_*_lock_test`), nunca la de trabajo:

  ```
  RUN_MYSQL_LOCK_TEST=1 php artisan test --group=mysql
  ```

## `App\Rukoz\CatalogoDeChispasFrias`

- **Producto sin variantes** → ítem producto. **Con variantes** → el producto con **todas** sus variantes (activas o no, cada una con su `activo`); en el Central se vincula cada variante.
- **Precio**: el de lista (`products.price`; una variante, ese precio + `price_addon`). **Sin** escalas por cantidad, ofertas, cupones ni recargo de tarjeta: es informativo para el Central.
- **Stock no viaja.** El 9999 de los productos y el NULL (ilimitado) de las variantes son tema de la 10.2.
- **SKU**: `products.sku` y `product_variants.sku` si están cargados (null si están vacíos). El combo no tiene.
- **Variante «a elección»** (`is_custom_color`): una variante más (con su recargo en el precio); en el Central va al genérico «color a elegir».
- **Combos** (`combo_items` = producto + cantidad, único por combo; el cliente elige color de cada componente que tenga variantes activas):
  - sin ningún componente con variantes activas → `tipo: fijo`, un ítem `producto` por componente con su cantidad (si un ítem se repitiera se suman);
  - con alguno → `tipo: seleccion`, **un grupo por componente**, con la cantidad del componente: componente sin variantes = grupo de una opción (el producto); con variantes = todas las variantes **activas** como opciones (incluida la «a elección»). Nombre del grupo: `«{producto} (#{combo_items.id})»` (único y estable).
- **Inactivos** → `activo: false` (una variante activa de un producto inactivo va inactiva). **Borrados**: no existe borrado lógico, así que el proveedor no puede emitirlos; `SincronizacionDeCatalogo` avisa al borrar un producto, una variante quitada o un combo (ítem con `activo: false`, armado antes de borrar) y el sync completo desactiva en el Central todo lo que no manda. Un combo sin componentes (se le borraron todos los productos) no se manda: el Central lo rechazaría.

Ejemplos (el producto es real, de la copia de producción; la copia no tiene combos, los combos son de ejemplo):

```json
{"id":"16","nombre":"Pistola lanza-chispas","sku":null,"precio":104500,"activo":true,
 "variantes":[{"id":"3","nombre":"Rosa","sku":null,"precio":104500,"activo":true},
              {"id":"4","nombre":"Celeste","sku":null,"precio":104500,"activo":true},
              {"id":"5","nombre":"Negra","sku":null,"precio":104500,"activo":true},
              {"id":"6","nombre":"Dorada","sku":null,"precio":104500,"activo":true},
              {"id":"7","nombre":"Combinada","sku":null,"precio":104500,"activo":true},
              {"id":"8","nombre":"Otro color","sku":null,"precio":119500,"activo":true}]}
```

```json
{"id":"1","nombre":"Kit boda","precio":150000,"activo":true,"tipo":"fijo","items":[
  {"tipo":"producto","id":"15","cantidad":1},{"tipo":"producto","id":"14","cantidad":10}]}
```

```json
{"id":"2","nombre":"Pistola + cartuchos","precio":120000,"activo":true,"tipo":"seleccion","grupos":[
  {"nombre":"Pistola lanza-chispas (#3)","cantidad":1,"opciones":[
     {"tipo":"variante","id":"3"},{"tipo":"variante","id":"4"},{"tipo":"variante","id":"5"},
     {"tipo":"variante","id":"6"},{"tipo":"variante","id":"7"},{"tipo":"variante","id":"8"}]},
  {"nombre":"Chispas Frias 2mts x 20segs cartucho (#4)","cantidad":4,"opciones":[{"tipo":"producto","id":"14"}]}]}
```

## Sincronización

- **Completa**: `php artisan rukoz:sync-catalogo` (el paquete la programa a diario, 03:30, si el proveedor está registrado).
- **Parcial, al guardar en el admin**, fuera de la transacción: producto (alta, edición, activar/desactivar, borrar, variantes quitadas) y combo (alta, edición, activar/desactivar, borrar). Guardar un producto reenvía también los combos que lo contienen (cambian sus opciones), después del producto. Marcar «destacado» no toca el catálogo. Con el Central caído el paquete deja los ids diferidos (`rukoz_estado.catalogo_parcial_pendiente`); cualquier otra falla se reporta y el guardado sigue.
- **Contrato**: `tests/Support/ContratoCatalogoCentral.php` valida el request armado contra el fixture oficial del Central (`tests/fixtures/rukoz/request-catalogo-sync*.json`, copiado del paquete e idéntico al de Chisperío) y las reglas del `CatalogoSyncRequest` del Central.

## Copia local de producción

- Export: `C:\Users\MatiasPC\Desktop\backups\chispasfrias-produccion-20261019.sql` (no está en el repo). Base **`chispasfrias_prod_copia`** en el MySQL de XAMPP; la de desarrollo `chispas-fria` no se tocó. Backup posterior a la importación: `backups\chispasfrias_prod_copia-20261019-despues-de-importar.sql`.
- `.env` local: `DB_DATABASE=chispasfrias_prod_copia`. **Para volver a desarrollo**: `DB_DATABASE=chispas-fria` y `php artisan config:clear`.

## Prueba real contra el Central

Pasos: en el Central crear la integración «Web Chispas Frías (local)» (empresa Chispas Frías, punto Buenos Aires de esa empresa, activa, sin webhook), copiar clave y secreto **solo** al `.env` de la web, y en la web:

```
RUKOZ_INTEGRACION=true  RUKOZ_CENTRAL_URL=http://localhost:8000  RUKOZ_API_KEY=…  RUKOZ_SECRET=…
INTEGRACION_CATALOGO=true  SESSION_COOKIE=chispasfrias_session
php artisan config:clear && php artisan serve --host=127.0.0.1 --port=8001
php artisan rukoz:ping && php artisan rukoz:sync-catalogo
```

Luego, en el Central: Integraciones → Productos web (verificar y **no vincular** hasta aprobar los vínculos del informe), y guardar un producto desde el admin de la web: el log de requests de la integración muestra un `POST /catalogo/sync` parcial.

### Resultado (10.1b, 2026-10-19)

Hecha contra el Central en `http://localhost:8010` (la web en `127.0.0.1:8001`; el 8000 lo ocupa otro `serve` de esta misma web). Integración «Web Chispas Frías (local)» (id 2, empresa Chispas Frías, punto `BA` = Buenos Aires id 4, sin webhook). `rukoz:ping` OK (~460 ms); `rukoz:sync-catalogo`: 39 creados (13 productos + 26 variantes), 34 sin vincular, 0 combos incompletos; guardado parcial de un producto sin cambios: `POST /api/v1/catalogo/sync` 200 en el log del Central. Vínculos aplicados con `VinculoProductoWebService::vincular()`: ver [VINCULOS-APROBADOS-CHISPAS-FRIAS.md](VINCULOS-APROBADOS-CHISPAS-FRIAS.md). Re-sync posterior: 39 sin cambios, `sin_vincular: 0`.
