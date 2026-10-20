# 10.3 — El checkout reserva en el Central

Con `INTEGRACION_CHECKOUT=true` un pedido de la tienda **reserva el stock en el Central antes de guardarse** y la web **deja de descontar stock local** para ese pedido. La confirmación de la venta es de la 10.4: acá el pedido queda **reservado**. Con el interruptor apagado todo funciona exactamente como antes (stock local, una sola transacción; los 24 tests de caracterización de la 10.0 siguen pasando sin tocarlos).

Referencia de diseño: Chisperío 11.3 (`docs/INTEGRACION-11-3.md`). Acá cambia la nomenclatura (`orders`/`order_items`, `CartController::generateWhatsAppMessage` en vez de un `PedidoController::store`) y el contrato validado es el **0.1.2**.

## Regla de encendido

`INTEGRACION_CHECKOUT` exige `INTEGRACION_STOCK` y `RUKOZ_INTEGRACION` (sin el espejo la web reservaría contra un stock que no ve). Con una combinación inválida:

- el checkout **no** usa el Central (sigue con stock local);
- al arrancar queda un `warning` en el log (a lo sumo uno por hora, `CheckoutCentral::advertir()`);
- `php artisan chispasfrias:integracion-estado` lo informa y sale con código 1.

`php artisan chispasfrias:integracion-estado` muestra los interruptores, el ping (y el circuito), los ítems activos sin espejo, los pedidos con reserva abierta y las **reservas viejas** (más de `PEDIDOS_CANCELAR_DIAS`), las compensaciones pendientes (por tipo), los pedidos cancelados esperando al Central y las compensaciones fallidas. **Código 1** si: combinación inválida, el Central no responde o el circuito está abierto, hay ítems sin espejo (con el checkout pedido), hay reservas viejas sin cancelar o hay compensaciones fallidas.

## El checkout, paso a paso (`CartController::generateWhatsAppMessage`)

1. Validaciones de siempre (opciones obligatorias, datos del cliente, chequeo contra el espejo: dan el mensaje rápido y no golpean al Central de más).
2. `CalculadoraPedido` (`calcularItems` + `aplicarDescuentoYPlan`): precios, cupón **validado sin consumir**, totales y plan de tarjeta. Se arma el mensaje de WhatsApp.
3. Se arma el pedido para el Central (`App\Rukoz\PedidoParaCentral`) con modelos en memoria.
4. **Referencia externa** `rukoz_ref` = `CHF-` + ULID (≤100, sin `/`), generada **antes** de reservar y guardada en `orders.rukoz_ref` (única). Es la clave de idempotencia (ver «Doble envío»).
5. `Rukoz::reservar(ref, payload, transaccionLocal)` — HTTP, **afuera de toda transacción**.
6. Reserva OK → **transacción local** (sin HTTP): la orden con `rukoz_ref`, `rukoz_version` y `rukoz_estado = reservado` (y `created_at` = el `creado_at` que recibió el Central), los ítems y `DiscountCodeService::consumirUso` (idempotente; si el cupón se agotó en el medio lanza, se deshace todo y el paquete cancela la reserva). **No** se llama a `StockService::descontar`.
7. Si lo local falla después de reservar → el paquete llama a `cancelarReserva`; si esa cancelación también falla queda en `rukoz_compensaciones` (tipo `cancelar`) y `rukoz:compensaciones` la reintenta (24 h). El cliente ve «No pudimos registrar tu pedido» (500) y su carrito sigue en la sesión.

`StockService::descontar` y `reponer` **ignoran** las órdenes con `rukoz_ref` (guarda dentro del servicio: ningún otro camino puede tocar el stock local de una orden del Central).

### Qué se manda (`POST /pedidos`, contrato 0.1.2)

Payload real de la prueba local (cliente «PRUEBA 10.3»; datos personales ofuscados):

```json
{
  "cliente": {
    "nombre": "P*** 10.3", "telefono": "11*****000", "dni": "***", "email": "***",
    "localidad": "La Plata", "provincia": "Buenos Aires", "cp": "1900",
    "observaciones": "Pedido de prueba 10.3 - no preparar"
  },
  "items": [
    { "tipo": "producto", "id": "22", "nombre": "Copa del mundo PULY®", "cantidad": 1, "precio_unitario": 115500 },
    { "tipo": "variante", "id": "8", "nombre": "Pistola lanza-chispas (Otro color)", "cantidad": 1, "precio_unitario": 129500,
      "nota": "Color: Turquesa PRUEBA · Nombre o marca personalizado: PRUEBA 10.3" }
  ],
  "total": 245000,
  "moneda": "ARS",
  "creado_at": "2026-10-20T02:05:40+00:00",
  "external_ref": "CHF-01M5BGXA5FCNED0CGX9GX4CP5S"
}
```

- **Producto o variante**: `tipo:id` + cantidad + `precio_unitario` **final por unidad** (escala por cantidad, oferta, recargo de la variante y add-ons; sin el cupón). Un producto con variantes activas **siempre** va como `variante`.
- **Combo fijo** (ningún componente tiene variantes activas): `tipo: combo`, sin `selecciones`. **Combo con elección o mixto**: `selecciones` con **un grupo por componente de la receta**, con el mismo nombre que informa el catálogo (`CatalogoDeChispasFrias::nombreDeGrupo`: «{producto} (#{combo_items.id})»); la opción es la variante elegida (o el producto, si ese componente no tiene colores) con la cantidad por combo. Sale del snapshot `order_items.combo_selections`; como `unique(combo_id, product_id)`, el producto del snapshot identifica la línea de la receta. La regla «¿es de selección?» es la **misma función** del catálogo (`CatalogoDeChispasFrias::requiereSeleccionDeColor`).
- **`nota`** (≤500 caracteres, solo si hay algo, **nunca** en el log). Es parte del contenido del pedido para el Central:
  - producto/variante: `Color: {custom_color_text}` y `{add-on}: {texto}` por cada add-on con texto, separados por ` · `;
  - combo: el color a elección de cada componente, `Pistola: Color: Turquesa` (los colores de la lista ya viajan como la variante de la selección; solo se aclara lo que el Central no puede saber).
- **Cliente**: nombre y apellido juntos; la provincia con su nombre (no el slug); se recortan los topes del Central (DNI 30, CP 10, teléfono 60…).
- **Punto**: **no se manda** (la web tiene uno solo; el Central usa el predeterminado de la integración).

### Cupón

El cupón **no se reparte entre las líneas**. Las líneas viajan a su precio final y el `total` viaja con el descuento ya restado: el Central toma `líneas − total` como descuento al confirmar (API.md, «El descuento no se manda dos veces»). **Al confirmar (10.4) no hay que mandar `descuento`.** El código viaja además en `cliente.observaciones` («Cupón: DIEZ») para quien atiende.

### Envío

La API no acepta envío en la reserva (no es una línea ni suma al `total`). Si el pedido califica para envío gratis —por un combo con envío gratis (`orders.free_shipping`) **o** por haber llegado al umbral de la tienda (`free_shipping_threshold`, que en la web es solo informativo y no se guarda)— va **«Envío gratis»** al principio de `cliente.observaciones`, seguido del cupón y de las observaciones del cliente. El costo del envío se informa al confirmar (10.4).

### Ítems «no aplica»

Son los **productos con variantes activas** (5 en el catálogo real: Pistola lanza-chispas, Baston lanza-chispa PULY®, Cartucho PULY® Chispa fría, Bengala de humo de mano y Pote de humo). En el Central tienen vínculo `no_aplica` porque **no tienen número propio**: se vincula y se pide cada variante. Por eso nunca viajan como línea (`tipo: producto` daría `ITEM_NO_VINCULADO`; el validador de contrato de los tests lo rechaza): lo que se compra es siempre una variante, y el checkout ya exige elegirla.

## Si el Central no deja reservar

| Situación | Qué pasa |
|---|---|
| **Sin stock** (`SinStockException`) | 422 con el **mismo shape de siempre** (`success:false`, `message`, `stock_insuficiente[]`) y el mensaje nombrando producto y color: «Solo quedan 1 unidad de Pistola lanza-chispas (Negra) (pediste 2). Ajustá tu carrito para continuar.». El carrito no se pierde. No se creó pedido, reserva ni consumo de cupón. Se **relee el stock de esos ítems** (`GET /stock?tipo=&ids[]=`, `EspejoStock::refrescar`) y se vuelca al espejo, aunque la columna local estuviera atrasada. |
| **Central caído, timeout o circuito abierto** | **No se crea el pedido.** 503 con `central_no_disponible: true`, el texto «No pudimos confirmar el stock en este momento. Probá de nuevo en unos minutos o escribinos por WhatsApp.» y `whatsapp_message` (el carrito armado). `Cart/Checkout.jsx` muestra el aviso con el botón «Escribinos por WhatsApp». Un timeout deja una compensación `crear_dudoso` (el Central pudo haber reservado): el reintento del cliente con el mismo carrito reutiliza la referencia, **adopta** la reserva y anula esa compensación; si no reintenta, el cron la resuelve. |
| **Ítem sin vincular / selección inválida / validación** | 422 con un mensaje general («Uno de los productos no está disponible por el momento. Escribinos por WhatsApp…») y el detalle técnico solo en el log (código, sin datos del cliente). |
| **Cupón agotado en el medio** | La transacción local lanza, el paquete cancela la reserva y el cliente ve el mensaje del cupón (se lo quita del carrito). |

### La decisión «sin Central no se vende»

Es la decisión por defecto y vive en **una función**: `App\Rukoz\CheckoutCentral::permiteVenderSinReserva()` (variable `INTEGRACION_CHECKOUT_SIN_RESERVA`, `false` por defecto). Con `true`, si el Central no responde el pedido se crea igual **sin `rukoz_ref`**, con stock local como antes; el Central no se entera de esa venta (hay que cargarla a mano) y el próximo evento de stock pisa el descuento local. No se recomienda.

## Doble envío

La referencia se guarda en el **caché** (no en la sesión: dos requests de un doble clic leen la sesión antes de que ninguno la escriba), bajo la huella del contenido del pedido (cliente + líneas + notas + totales; sin `external_ref` ni `creado_at`).

- Mismo contenido → misma `rukoz_ref` y mismo `creado_at` (el Central compara el body byte a byte: otro `creado_at` sería `PEDIDO_YA_EXISTE`).
- Un lock del caché por huella serializa dos requests simultáneos; si el pedido de ese intento ya existe, se devuelve **el mismo** `order_id` sin reservar de nuevo.
- Tras crearse, el intento se conserva `CHECKOUT_VENTANA_DOBLE_ENVIO` segundos (120); pasado ese tiempo, repetir la misma compra es un pedido nuevo.
- Si el Central rechaza (sin stock, validación) o falla lo local, el intento se olvida: un reenvío arma uno nuevo.
- `orders.rukoz_ref` es único: una segunda inserción con la misma referencia falla (y el paquete cancela, nunca duplica).

Detalle conocido: el segundo envío vuelve a validar el cupón **antes** de llegar a la idempotencia; con un cupón de un solo uso ya consumido por el primer envío, el segundo responde el mensaje del cupón en vez del mismo pedido. El front deshabilita el botón mientras se envía, así que solo pasaría con dos pestañas.

## El admin con un pedido reservado

**Qué permite hoy el admin**: listar, ver y **cambiar el estado** (`pendiente → despachado | cancelado`, `despachado → pendiente`). **No hay edición de ítems ni de cantidades**, así que **no se agregó** edición ni `editarReserva` (un test, `test_el_admin_no_tiene_edicion_de_items`, fija que las únicas rutas son `index`, `show` y `update-status`; si algún día se agrega edición habrá que mandar el contenido completo con `Rukoz::editarReserva` y no guardar si el Central rechaza).

- **Cancelar** (`Admin\OrderController::updateStatus` → `App\Rukoz\ReservaDePedido::cancelar`): primero `Rukoz::cancelarReserva` (HTTP, sin transacción), después una transacción local con `liberarUso` del cupón (idempotente) y el estado. **No** se repone stock local. Si el Central no responde, la orden queda `cancelado` con `rukoz_estado = cancelacion_pendiente`, la cancelación queda en las compensaciones del paquete y, al resolverse (`CompensacionResuelta`), un listener la pasa a `cancelado`. Una venta ya confirmada en el Central (`ANULAR_DESDE_CENTRAL`) no se cancela desde la web (error en pantalla, la orden no cambia). La pantalla muestra el motivo real en el toast y un cartel si la liberación quedó pendiente.
- **«Despachado»** sigue siendo **local** hasta la 10.4 y para estos pedidos no toca stock ni llama al Central (despachar y volver a pendiente son solo estado). **En la 10.4 «Confirmar venta» lo reemplaza** para los pedidos con `rukoz_ref`.
- **Pedidos viejos (sin `rukoz_ref`)** siguen con el flujo de hoy (stock local) hasta terminar, aunque el interruptor esté encendido: cancelarlos repone stock local y no llama al Central. Tests de los dos casos en `PedidoConReservaAdminTest`.

## Cancelación automática

`php artisan chispasfrias:cancelar-reservas-viejas [--dry-run] [--dias=N]`, programado **cada hora** (`routes/console.php`, solo con `RUKOZ_INTEGRACION`). Cancela como el admin las órdenes con `rukoz_ref`, **pendientes** (no despachadas), con la reserva viva y con **más de** `PEDIDOS_CANCELAR_DIAS` (3) días: exactamente 3 días todavía no, un segundo después sí (`created_at < now() − N días`). Motivo: «Cancelado automáticamente (3 días sin confirmar)». Las órdenes sin `rukoz_ref` no se tocan nunca. `--dry-run` lista sin cancelar ni llamar al Central; sale con código 1 si alguna cancelación falla (por ejemplo, una venta ya confirmada).

**Vencimiento en el Central**: las reservas **no vencen**; el Central solo avisa a sus administradores de los pedidos reservados hace más de N días y **no libera nada solo**. Para no recibir un aviso redundante antes de que la web cancele, la integración de Chispas Frías en el Central tiene **`dias_aviso_reserva = 4`** (3 de la web + 1 de margen); el aviso solo debería aparecer si la web no pudo cancelar. *(Se dejó en 4 en la integración 2 de la base local del Central; en el Central de producción hay que cargarlo desde Configuración → Integraciones.)*

## Migración, variables, comandos y tareas

- Migración aditiva `2026_10_20_120000_add_rukoz_reserva_to_orders_table`: `orders.rukoz_ref` (única, nullable), `rukoz_version`, `rukoz_estado` (`reservado` | `cancelado` | `cancelacion_pendiente`) y `motivo_cancelacion`. Las órdenes existentes quedan en `NULL`.
- Variables (todas apagadas/por defecto en producción): `INTEGRACION_CHECKOUT`, `INTEGRACION_CHECKOUT_SIN_RESERVA` (false), `PEDIDOS_CANCELAR_DIAS` (3), `CHECKOUT_VENTANA_DOBLE_ENVIO` (120).
- Comandos nuevos: `chispasfrias:integracion-estado`, `chispasfrias:cancelar-reservas-viejas`.
- Tareas: `chispasfrias:cancelar-reservas-viejas` cada hora (además de `chispasfrias:stock-cargar --local` a las 03:45 y las `rukoz:*` del paquete). **Hace falta el cron de `schedule:run`**.
- Cambios laterales: `EspejoStock::refrescar()`, `CatalogoDeChispasFrias::requiereSeleccionDeColor` pasó a público, `App\Support\Stock::mensajeFaltante()` (el texto de «No queda stock de X (Color)» ahora está en un solo lugar) y `CartController` separa `atributosDeOrden` / `atributosDeItems` / `crearOrdenLocal` / `crearOrdenConReserva` (el flujo local quedó idéntico).

## Tests

`tests/Feature/Rukoz/`: `CheckoutConReservaTest` (32), `PedidoConReservaAdminTest` (10), `CancelarReservasViejasTest` (11), `IntegracionEstadoTest` (12), `ContratoPedidoCentralTest` (6). Usan el cliente **real** del paquete (idempotencia, circuito, compensaciones) contra `tests/Support/CentralDePrueba`, un Central falso con estado detrás de `Http::fake` que valida **cada body** contra el contrato **0.1.2** (`tests/Support/ContratoPedidoCentral`: reglas de `PedidoWebRequest`, claves permitidas según los fixtures de `tests/fixtures/rukoz/` —copias de los ejemplos del paquete, y un test falla si se desactualizan— y existencia/forma contra el catálogo real de `CatalogoDeChispasFrias`, incluidos grupos y cantidades de las selecciones). Los combos se prueban con datos de test (la base real no tiene).

## Prueba local

1. Backup de la base y `php artisan migrate`.
2. `.env`: `INTEGRACION_CHECKOUT=true` (con `INTEGRACION_STOCK` y `RUKOZ_INTEGRACION`); `php artisan config:clear`.
3. `php artisan chispasfrias:integracion-estado` (ping OK, 0 ítems sin espejo).
4. Hacer un pedido en la tienda y mirarlo en el Central (*Configuración → Integraciones → Pedidos web*); cancelarlo desde el admin; el disponible del Central baja al reservar y vuelve al cancelar (el espejo de la web se entera en ~1 min).
