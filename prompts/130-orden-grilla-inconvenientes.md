<!-- ciclo: critica=no turno-noche=1 rama=feature/orden-grilla-inconvenientes etapas=1 -->

# Tarea 130 — la tarjeta de Órdenes de aplicación no corta el título en tableta

Hallazgo del dueño (22/9/2026, con captura de `?vista=grilla` en tableta):
cuando una orden tiene el badge "Con inconvenientes" (ADR 0022, informativo)
además del badge de estado, el título del cliente se corta en columnas de una
palabra ("Agrícola / San / Marcos / S.R.L. — / Contrato / #1") mientras el
resto de la cabecera queda vacío. Además, el badge usa el mismo tono
`warning` que estados como "Pausada" — no se distingue de un vistazo — y su
lugar (al lado del badge de estado) es, según el dueño, "propio de los
estados": quiere que "Con inconvenientes" baje a una línea propia debajo del
título, en `danger`, para que se note.

Cargá los skills `panel-design-ui` y `verificacion`.

## La causa (ya diagnosticada, no la reinvestigues)

`app/Dominios/Operaciones/Infraestructura/Http/Views/pages/ordenes/_orden-card.blade.php`
pone el badge de estado y el de "Con inconvenientes" juntos dentro de
`.ag-ordenes__estados` (`resources/css/pages/ordenes.css` línea ~85), que es
hijo de `.ag-ordenes-card__head` (flex row) junto a `.ag-ordenes-card__identidad`
(`flex: 1; min-width: 0`, línea ~161 — ahí vive el título,
`.ag-ordenes-card__cliente`). Con dos badges, `.ag-ordenes__estados` reclama un
ancho de contenido grande (aunque sus propios badges puedan bajar de línea
entre sí, `flex-wrap: wrap`, eso no libera ancho a su hermano); como
`.ag-ordenes-card__identidad` tiene `min-width: 0`, es la que cede casi todo
el ancho y el título queda en una columna angosta. Con un solo badge (o
ninguno) no pasa — por eso en la captura las tarjetas con una sola línea de
"Vigente"/"Pausada"/"Cancelada" se ven bien y las que además tienen "Con
inconvenientes" no.

## Qué hacer

1. En `_orden-card.blade.php`: sacá el badge de "Con inconvenientes" de
   `.ag-ordenes__estados` (que queda con SOLO el badge de estado — así el
   título vuelve a tener todo el ancho disponible) y ponelo en una línea
   nueva, debajo de `.ag-ordenes-card__cliente`, dentro de
   `.ag-ordenes-card__identidad`. Variant del badge: `danger` (no `alert` —
   ese tono es para estados terminales más severos, ver `badge.css`; acá
   sigue siendo informativo, no un estado).
2. CSS nuevo en `resources/css/pages/ordenes.css` para esa línea (un
   `margin-top` chico, sin tocar el resto de `.ag-ordenes-card__identidad`);
   token, no color literal (invariante 11 de `CLAUDE.md`).
3. Mismo cambio de color (a `danger`) en las otras dos apariciones del mismo
   badge, por consistencia (es el mismo semántico en las tres pantallas):
   `ordenes/index.blade.php` (columna Estado de la fila) y
   `ordenes/show.blade.php` (`x-slot:chip` de la cabecera). En esas dos NO
   hace falta reubicarlo — ahí no reproduce el bug del título (la fila de
   tabla tiene columna propia; el chip de la ficha no compite con un título
   de ancho variable) — solo cambiá `variant="warning"` por `variant="danger"`.
4. Actualizá el comentario de `.ag-ordenes__estados` en `ordenes.css` (dice
   "dos badges en la misma celda o cabecera de tarjeta": ya no aplica a la
   tarjeta) y el docblock de `_orden-card.blade.php`.

## Qué NO hacer

No toques `ope_ordenes_aplicacion` ni `ResumenDeOrdenes` (es puro dato ya
calculado, `$tieneInconvenientes`). No cambies el ADR 0022 (no decide esto:
solo dice que el badge es informativo). No agregues una columna nueva a la
tabla del listado.

## Criterio de aceptación

- `./bin/verify` = 0.
- `grep -n "warning.*badge_inconvenientes\|badge_inconvenientes.*warning" -r app/Dominios/Operaciones` no debe encontrar ninguna de las tres vistas con `variant="warning"` en ese badge (las tres en `danger`).
- Prueba en navegador (`?vista=grilla`, ancho de tableta ~600-700px real del
  contenedor — usar el `@container` de `.ag-ordenes`, no el viewport): una
  orden con estado + "Con inconvenientes" muestra el título completo, sin
  cortarse palabra por palabra; el badge de estado queda solo en su línea y
  "Con inconvenientes" aparece debajo del título, en rojo. Capturas en
  `runs/130-capturas/` (ruta absoluta), claro y oscuro.

## Cierre obligatorio de cada etapa

`runs/130.estado`, `runs/130.md`, y al `OK` `runs/130.pr.md`. Commits por
función, en español, imperativo, sin `Co-Authored-By`.
