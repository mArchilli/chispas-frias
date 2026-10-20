# 10.4 — Confirmar la venta, retención, estados y métricas

Con `INTEGRACION_CONFIRMACION=true` (más `RUKOZ_INTEGRACION`; los pedidos con reserva existen solo con `INTEGRACION_CHECKOUT`), el admin o el vendedor confirma la venta de un pedido reservado en el Central y la web refleja los estados que el Central informa. Con el interruptor apagado, o en un pedido viejo (sin `rukoz_ref`), todo funciona como antes.

Referencia de diseño: Chisperío 11.4 / 11.4b (`docs/INTEGRACION-11-4*.md`). Acá cambia la nomenclatura (`orders`, `EstadoOrden`, `Admin\OrderController`) y **no hay punto de atención** (esta web tiene uno solo: no se manda `punto`). Contrato validado: **0.1.2**.

## Decisiones

- Confirma el vendedor o el admin, **con o sin pago**. Sin pago la venta queda con saldo en el Central.
- Al confirmar, el vendedor elige **«Despachar solo con pago completo»** (retención).
- Después de confirmar, el pedido es de **solo lectura** en la web. Las correcciones se hacen en el Central (anular y rehacer).
- Empaquetado y despacho se hacen en el Central; la web muestra el estado que le llega.
- Comisión: las ventas web comisionan por el canal Web, configurado en el Central. Acá no hay nada que hacer.

## «Confirmar venta» (`App\Rukoz\ConfirmacionDeVenta`)

Botón en la ficha del pedido (`Orders/Show`) para los pedidos `pendiente` con reserva `reservado` (`Order::puedeConfirmarse()`); **reemplaza** a «Marcar como despachado» para esos pedidos (los viejos siguen igual). El modal (`Components/Admin/ConfirmarVentaModal.jsx`) pide sus datos al abrirse (`GET admin/orders/{id}/confirmation`, así la ficha no depende del Central): resumen con las notas por ítem, forma de envío / transportista / costo cobrado / envío gratis, **pagos** (ninguno, uno o varios: cuenta, medio, monto; nunca más que el total), el **saldo** («Queda con saldo $X en el central»), la casilla **«Despachar solo con pago completo»** (deshabilitada si no queda saldo) y el **vendedor** (el usuario logueado; un admin puede elegir otro).

### Qué se manda (`POST /pedidos/{ref}/confirmar`, contrato 0.1.2)

Payload real de la prueba (Pedido A, sin pagos, envío $5.000, retención; email ofuscado):

```json
{
  "vendedor": { "id": "2", "email": "l***@prueba.com", "nombre": "Lucas" },
  "pagos": [],
  "envio": { "forma_envio_id": 1, "costo_cobrado": 5000, "es_gratis": false, "transportista_id": 1 },
  "solo_con_pago_completo": true,
  "confirmado_at": "2026-10-19T23:32:02-03:00"
}
```

Pedido B (dos pagos): `"pagos": [ { "cuenta": "DIGITAL-CHISPERIO", "medio": "transferencia", "monto": 60000, "fecha": "…" }, { "cuenta": "EFECTIVO", "medio": "efectivo", "monto": 30000, "fecha": "…" } ]`.

- **No se manda `descuento`** (el del cupón ya viaja en el `total` del pedido, 10.3), **ni `punto`**.
- La venta queda en `productos − cupón + envío cobrado` (A: 119.500 + 5.000 = 124.500).
- `solo_con_pago_completo` solo viaja en `true` si **queda saldo** (el backend lo controla aunque la casilla venga tildada).
- **Idempotencia**: un lock de caché por pedido serializa el doble clic (el segundo ve el pedido ya confirmado y no llama al Central: «ya estaba confirmado»); `orders.confirmando_at` se fija **una vez** antes del primer intento y es el `confirmado_at` y la `fecha` de los pagos, así un reintento tras un timeout lleva los mismos bytes y el Central devuelve la misma venta.
- **Nada a medias**: Central caído / timeout / circuito → «no se confirmó y el pedido no cambió»; rechazo (`CONFIRMACION_RECHAZADA`, `VALIDACION`…) → el mensaje del Central y el pedido sin cambios; `PEDIDO_YA_CONFIRMADO`/`PEDIDO_CANCELADO` → se reconcilia con `Rukoz::actualizarPedidoDesdeCentral`. Si el Central confirma y falla el guardado local, queda un `critical` en el log y el estado llega igual por el evento.
- Al éxito: `estado = confirmado`, `confirmado_at`, `rukoz_numero_venta`, total/pagado/saldo, `envio_costo_cobrado`, `confirmado_por`, `rukoz_version`.

### El vendedor (`App\Rukoz\VendedorParaCentral`)

La API recibe `vendedor.id` (obligatorio) + `email`/`nombre`. Mapeo web → Central: columna **`users.rukoz_vendedor_id`**:

- se edita en **Admin → Vendedores → Editar** («Vendedor en el Sistema Central», solo con la confirmación encendida; único entre usuarios, sugiere los ya asignados en el Central) o con `php artisan chispasfrias:vendedor-mapear {email|id} [vendedor] [--quitar] [--listar]` (sin `vendedor` usa el id del usuario). **El admin no figura en el ABM de vendedores: se mapea con el comando.**
- **Sin mapeo no se confirma**: el mensaje explica cómo vincularlo y no se llama al Central.
- Con mapeo pero sin asignar en el Central: `VENDEDOR_NO_MAPEADO` → «El vendedor todavía no está habilitado en el Sistema Central; ya se avisó a administración…». El Central lo deja pendiente en Integraciones → Vendedores.

## Solo lectura

`Order::puedeTransicionarA()` y `Order::estaConfirmado()` mandan en el backend (no solo la UI): un pedido confirmado (o empaquetado/despachado/entregado/anulado) no admite **ningún** cambio de estado — cancelar, volver a pendiente o despachar — y responde **«Para anular, hacelo en el Sistema Central.»** sin llamar al Central. `chispasfrias:cancelar-reservas-viejas` no lo toca (`confirmado_at` null). No hay edición de ítems en esta web.

## Estados que llegan del Central (`App\Rukoz\EstadoDesdeCentral`)

Listener del evento genérico `PedidoActualizado` del paquete, registrado en `AppServiceProvider` **solo con `INTEGRACION_CONFIRMACION`**. El paquete ya aplica la regla de `version` sobre `rukoz_pedidos`; el listener la repite sobre `orders.rukoz_version` (descarta una versión **menor**, reaplica una igual: idempotente, en orden). Sin HTTP; una referencia desconocida se ignora; un pedido cancelado en la web no se reabre.

| Central (`estado`) | `EstadoOrden` | Notas |
|---|---|---|
| reservado / requiere_revision | sigue Pendiente | solo versión |
| confirmado | **Confirmado** (nuevo) | también al revertir (`estado_revertido`): las fechas del estado dejado vuelven a `null` |
| empaquetado | **Empaquetado** (nuevo) | |
| despachado | Despachado | `despachado_at`, transportista, número y URL de seguimiento |
| retirado | **Entregado** (nuevo) | `retirado` es el equivalente de «entregado» en el Central |
| anulado | **Anulado** (nuevo) | motivo en `motivo_cancelacion`, `rukoz_anulado_at`; **libera el cupón** (idempotente) |
| cancelado (`reserva_liberada`) | Cancelado | motivo del Central; libera el cupón |

`pago_actualizado` refresca pagado, saldo y `rukoz_retenido_por_pago` (queda `false` al completarse el pago); `envio_actualizado` refresca transportista, número y URL de seguimiento. El stock lo devuelve el Central y llega por `stock.actualizado` (10.2): la web nunca descontó stock local de estos pedidos.

### La ficha

Estado, número de venta, total / pagado / saldo, envío cobrado, **«Retenido hasta completar el pago»** mientras `rukoz_retenido_por_pago`, el **seguimiento** (transportista + número + enlace «Seguir el envío», solo si la URL es http/https) y las fechas de cada estado. Esta web **no tiene una vista del pedido para el cliente** (el pedido se manda por WhatsApp): el seguimiento queda solo en el admin.

## Métricas

`Order::ventas()` / `Order::ventasEntre($desde,$hasta)` distinguen el origen:

- **Con `rukoz_ref`**: cuentan confirmado, empaquetado, despachado y entregado **con `confirmado_at`**, en el día/mes de la confirmación. Anulados, cancelados y reservas sin confirmar no suman (las reservas van a «pendientes»; un `despachado` manual de la 10.3 sin confirmar tampoco es venta).
- **Sin `rukoz_ref`** (viejos): despachados, por su fecha de creación, como hoy.

`ResumenDePedidos` (listado de órdenes y dashboard), el producto más vendido y el desglose diario usan esas ventas. El monto de venta es `orders.total` (productos − cupón, sin envío), igual que antes. «Cancelados» cuenta cancelados + anulados. Días y meses son **argentinos** (borde de medianoche testeado en `MetricasConCentralTest`).

## Migración, variables y archivos

- Migración aditiva `2026_10_21_120000_add_confirmacion_de_venta_to_orders_table`: `orders.confirmado_at` (índice), `confirmando_at`, `confirmado_por`, `rukoz_numero_venta`, `rukoz_total_venta`, `rukoz_pagado`, `rukoz_saldo`, `rukoz_retenido_por_pago`, `envio_costo_cobrado`, `rukoz_transportista`, `rukoz_numero_seguimiento`, `rukoz_url_seguimiento`, `rukoz_empaquetado_at`, `despachado_at`, `rukoz_retirado_at`, `rukoz_anulado_at`; `users.rukoz_vendedor_id`. Todo nullable. **Backup antes de migrar.**
- Variables: `INTEGRACION_CONFIRMACION` (ya existía), `INTEGRACION_REFERENCIAS_CACHE` (300), `INTEGRACION_REFERENCIAS_RESPALDO` (`{}`), `INTEGRACION_FORMA_ENVIO_ID`, `INTEGRACION_TRANSPORTISTA_ID`. **En producción todo apagado.**
- Comando nuevo: `chispasfrias:vendedor-mapear`. `chispasfrias:integracion-estado` suma el bloque «Ventas (10.4)» (ventas por estado, con saldo, usuarios sin mapeo, listener registrado; error si falta el listener o si la confirmación está encendida sin checkout).
- Archivos: `app/Rukoz/{ConfirmacionDeVenta,EstadoDesdeCentral,VendedorParaCentral,ReferenciasDelCentral}.php`, `Admin\OrderConfirmationController`, `Components/Admin/ConfirmarVentaModal.jsx`, `Order::{estaConfirmado,puedeConfirmarse,puedeTransicionarA,scopeVentas,scopeVentasEntre}`.
- Hace falta el webhook (o `rukoz:poll`, cada 5 min) para que los estados lleguen.

## Tests

`tests/Feature/Rukoz/`: `ConfirmarVentaTest` (29), `PedidoConfirmadoSoloLecturaTest` (12), `EstadosDesdeCentralTest` (21), `MetricasConCentralTest` (9), `VendedorMapeoTest` (9), más el bloque de ventas de `IntegracionEstadoTest`. Usan el cliente **real** del paquete contra `tests/Support/CentralDePrueba` (ahora con `GET /referencias`, `POST /pedidos/{ref}/confirmar`, `GET /pedidos/{ref}` y transiciones desde el Central) y **cada body de `confirmar` se valida** contra `tests/Support/ContratoConfirmacionCentral` (0.1.2). Los eventos entran por el camino real (inbox → versión → `PedidoActualizado`) con los fixtures oficiales de `tests/fixtures/rukoz/` (copias del paquete; un test falla si se desactualizan).

## Prueba local (2026-10-19/20)

Ver el informe de la fase. Pedidos de prueba (#3 A, #4 B, #5 C) quedaron en la base local `chispasfrias_prod_copia` y las ventas CFR-000001/2/3 en el Central local.
