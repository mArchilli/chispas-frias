# Vínculos aprobados — Web Chispas Frías → Sistema Central

Aprobados por Matias el 2026-10-19 y aplicados en el central de prueba (integración «Web Chispas Frías (local)»: 34 vinculados, 5 `no_aplica`, 0 sin vincular; re-sync desde la web con `sin_vincular: 0`).

**En producción se rehacen a mano** en Integraciones → Web Chispas Frías → Productos web, después del primer `rukoz:sync-catalogo`. Los ids de la web pueden diferir de los locales: identificá por **nombre** (y color). Los ids de abajo son los de la copia local de producción (`chispasfrias_prod_copia`) y se repiten entre producto y variante (`producto:15` ≠ `variante:15`). **Antes de aplicar, mostrar la tabla final a Matias.**

## Decisiones particulares
| Web | Central |
|---|---|
| Chispas 2mts x 20segs (producto:14) | 2x20 Volcán (RKZ-0001) |
| Bastón lanza-chispa largo (producto:18) | Bastón porta chispa (RKZ-0047) |
| Pistola «Combinada» (variante:7) | Pistola Dual (RKZ-0040) |
| Pistola «Otro color» (variante:8) | Pistola Personalizada (simple) (RKZ-0043); el color va en la nota del ítem. Igual que en Chisperío: no hace falta un genérico |
| Bengala de humo {color} (variante:13 a 20) | Bengala {color} (RKZ-0011 a 0020, por color) |
| Pote de humo {color} (variante:21 a 28) | Pote {color} (RKZ-0025 a 0034, por color) |
| Cartucho PULY® Dorado (variante:11) y Negro (variante:12) | **los dos a RKZ-0009** (2x20 Puly; comparten el disponible, igual que en Chisperío) |

## Productos sin variantes
| Web | Central |
|---|---|
| producto:22 Copa del mundo PULY® | RKZ-0046 Copa del mundo |
| producto:25 AK-47 Lanza-chispas | RKZ-0035 AK 47 |
| producto:15 Detonador inalámbrico recargable | RKZ-0052 Detonador recargable (por unidad) |
| producto:17 Detonador inalámbrico a pila AA | RKZ-0051 Detonador a pila (por unidad) |
| producto:26 Chispa Fría 2x10 Cienfuegos | RKZ-0005 Cienfuegos |
| producto:19 Chispas frías con mecha cartucho | RKZ-0010 2x20 Mecha |
| producto:14 Chispas Frías 2mts x 20segs cartucho | RKZ-0001 2x20 Volcán |
| producto:18 Bastón lanza-chispa largo | RKZ-0047 Bastón porta chispa |

## Variantes
| Web | Central |
|---|---|
| Pistola lanza-chispas: v3 Rosa / v4 Celeste / v5 Negra / v6 Dorada | RKZ-0042 Pink / 0039 Celeste / 0036 Black / 0041 Golden |
| Pistola: v7 Combinada | RKZ-0040 Pistola Dual |
| Pistola: v8 Otro color | RKZ-0043 Pistola Personalizada (simple) |
| Bastón PULY®: v9 Negro / v10 Dorado | RKZ-0049 / RKZ-0048 |
| Cartucho PULY®: v11 Dorado / v12 Negro | RKZ-0009 / RKZ-0009 |
| Bengala de humo: v13 Celeste / v14 Rojo / v15 Violeta / v16 Naranja / v17 Rosa / v18 Verde / v19 Blanco / v20 Amarillo | RKZ-0014 / 0017 / 0020 / 0015 / 0018 / 0019 / 0013 / 0011 |
| Pote de humo: v21 Amarillo / v22 Blanco / v23 Negro / v24 Verde / v25 Naranja / v26 Rosa / v27 Celeste / v28 Azul | RKZ-0025 / 0027 / 0030 / 0033 / 0029 / 0032 / 0028 / 0026 |

## no_aplica (5)
Son los productos padre con variantes: Pistola lanza-chispas (16), Bastón lanza-chispa PULY® (20), Cartucho PULY® Chispa fría (21), Bengala de humo de mano (23) y Pote de humo (24). Lo que se compra siempre es una variante. El central los marca solo (`no_aplica`) al sincronizar; no se vinculan.

## Observaciones
- Bengala: el central tiene también Azul (RKZ-0012) y Negro (RKZ-0016) sin variante en la web. Pote: el central tiene Rojo (RKZ-0031) y Violeta (RKZ-0034) sin variante en la web.
- Pistolas Boca/River (RKZ-0037/0038) existen en el central y no tienen variante en la web.
- La web no tiene combos en la copia de producción.

## Precios
La web manda **sus propios precios** (`products.price`, y para una variante ese precio + `price_addon`); la lista de precios del central es solo referencia. Ya está decidido (decisión 5 de la Etapa 2), así que las diferencias de precio entre la web y el central **no bloquean** nada ni se corrigen en el vínculo.
