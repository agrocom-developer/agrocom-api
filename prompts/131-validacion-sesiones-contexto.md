<!-- ciclo: critica=no turno-noche=1 rama=feature/validacion-sesiones-contexto etapas=1 -->

# Tarea 131 — la cola de Validación de sesiones no da referencia de qué se está validando

Hallazgo del dueño (22/9/2026), mirando `/panel/sesiones/validacion`: la fila
de cada sesión pendiente muestra `#{{ sesion->id }}`, `#{{ trabajo_id }}` y
"Piloto #:id" — solo códigos crudos, sin ningún dato que diga de qué trabajo
o de qué piloto se trata. Para decidir "validar" o "rechazar" a ciegas no
alcanza; el pedido es que la fila enlace al detalle real (el trabajo YA tiene
una ficha completa desde la tarea 124: lote, cuadrilla, orden, cliente) y
muestre el nombre del piloto, no su id.

Cargá los skills `dominio-backend`, `panel-design-ui` y `redaccion-neutra`.
Leé `runs/124.md` y `runs/125.md` antes de escribir nada (ver el punto 1).

## Antes de escribir código

1. El mismo problema existe HOY en `trabajos/show.blade.php`
   (`col_piloto` → `operaciones.trabajos.sesion_piloto` → `'Piloto #:id'`,
   línea ~240): es la misma deuda en dos pantallas. Mirá si la tarea 124
   (Detalle de Trabajo) ya resolvió el nombre real del piloto en su tabla de
   sesiones. Si sí, reusá exactamente ese mecanismo acá (no inventes uno
   nuevo). Si sigue mostrando `Piloto #:id`, resolvelo una sola vez para las
   dos pantallas: un contrato de lectura en `Personal/Contratos/` que
   devuelva `nombre_completo` por `persona_id` (mismo patrón cruzado de
   módulos que `LecturaCuadrillasPorRecurso` — nunca un modelo `PerPersona`
   directo desde `Operaciones`, invariante de arquitectura modular), y
   actualizá las dos vistas.

## Qué hacer

En `app/Dominios/Operaciones/Infraestructura/Http/Views/pages/sesiones/validacion.blade.php`
y `ValidacionSesionesController::index()`:

1. Columna "Piloto": nombre real (con el contrato de lectura del punto
   anterior), no `Piloto #:id`. Si la persona fue borrada/no se encuentra,
   fallback al id crudo — mismo criterio que `$etiquetasBase` en otras
   pantallas (`#id` cuando no hay etiqueta).
2. Columna "Trabajo" (`#{{ trabajo_id }}`): convertila en enlace a
   `route('panel.trabajos.detalle', $sesion->trabajo_id)` cuando el usuario
   tenga `operaciones.trabajo.ver` (gate server-side también, no solo
   ocultar el link — mismo criterio que `$puedeVerTrabajos` en
   `ordenes/index.blade.php`). Si no tiene el permiso, texto plano.
3. Si podés traer el lote del trabajo sin una consulta N+1 por fila (un
   `with()`/`join` sobre `Sesion::query()`, que hoy no precarga nada), sumá
   un subtítulo chico con el lote debajo del código de trabajo — no es
   obligatorio si no hay una forma barata de traerlo; no inventes una
   consulta cara para esto.
4. `index()` sigue sin cambiar la condición del `where` (invariante 3/4): es
   solo qué se PINTA de cada fila, no cuáles filas entran a la cola.

## Qué NO hacer

No toques `ValidarSesion`/`RechazarSesion` ni la policy de invariante 4 (el
piloto sigue sin ver su propia fila con acciones). No agregues una columna de
"Lote"/"Cliente" con un `with()` que dispare N+1 (una precarga o nada). No
uses `{{ }}` para pasar texto a un componente (`EscapePropsBladeTest`).

## Criterio de aceptación

- `./bin/verify` = 0.
- `grep -c "sesion_piloto', \['id'" app/Dominios/Operaciones/Infraestructura/Http/Views/pages/sesiones/validacion.blade.php` = 0.
- Prueba en navegador: la cola muestra un nombre de piloto real (no "Piloto
  #N") y el código de trabajo es un enlace que abre su ficha
  (`/panel/trabajos/detalle/{trabajo}`) con lote, cuadrilla y orden a la
  vista. Capturas en `runs/131-capturas/` (ruta absoluta), claro y oscuro.

## Cierre obligatorio de cada etapa

`runs/131.estado`, `runs/131.md`, y al `OK` `runs/131.pr.md`. Commits por
función, en español, imperativo, sin `Co-Authored-By`.
