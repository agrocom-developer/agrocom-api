<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/coordenada-base etapas=1 -->

# Tarea 100 — HU-85: coordenada de una base

## Qué hacer

`per_bases` tiene `nombre` y `ubicacion` (texto libre). HU-85 suma
`latitud`/`longitud` estructuradas para poder ubicar la base en el mapa —
exactamente el mismo patrón que HU-76 (Propiedad, tarea 92, ya integrada,
PR #196). Replicalo: no inventes un esquema de validación nuevo, copiá el de
`com_propiedades`.

Cargá el skill `verificacion` antes de tocar código, y `modelo-datos` antes
de escribir la migración.

1. **Migración** `database/migrations/2026_09_14_100014_add_coordenada_a_per_bases_table.php`
   — mismo patrón que
   `2026_09_14_100005_add_ubicacion_estructurada_a_com_propiedades_table.php`:
   `latitud` (`decimal(9,6)`, nullable, `after('ubicacion')`), `longitud`
   (`decimal(9,6)`, nullable, `after('latitud')`); `CHECK` de rango (`latitud
   BETWEEN -90 AND 90`, `longitud BETWEEN -180 AND 180`), solo pgsql,
   nombrados `per_bases_latitud_chk`/`per_bases_longitud_chk`. `ubicacion`
   NO se toca ni se reemplaza — coexiste, igual que en propiedades.
2. `app/Dominios/Personal/Infraestructura/Eloquent/PerBase.php`: `latitud`/
   `longitud` a `$fillable`, a `casts()` (`decimal:6`) y al docblock
   `@property`.
3. `CrearBaseRequest`/`ActualizarBaseRequest`: `latitud` (`nullable`,
   `required_with:longitud`, `numeric`, `between:-90,90`), `longitud`
   (`nullable`, `required_with:latitud`, `numeric`, `between:-180,180`) —
   copiá literal el patrón de `CrearPropiedadRequest`/
   `ActualizarPropiedadRequest` (`app/Dominios/Comercial/Infraestructura/Http/Requests/`),
   incluido el mensaje dedicado para `required_with` (no el genérico de
   Laravel).
4. `app/Dominios/Personal/Aplicacion/CrearBase.php`/`ActualizarBase.php`:
   nuevos parámetros `?string $latitud, ?string $longitud`.
5. `app/Dominios/Personal/Infraestructura/Http/Controllers/Web/BasesController.php`:
   `store()`/`update()` pasan los campos nuevos.
6. `_formulario.blade.php` de bases: dos campos nuevos (`x-atoms.input`
   `type="text"` o `type="number"` con `step`, según lo que ya use
   propiedades — seguí ese precedente, no inventes uno nuevo).
7. `lang/es/personal.php`, bloque `bases`: `campo_latitud`/`campo_longitud`
   y `error_coordenada_incompleta` — mismas claves y mismo texto que
   `lang/es/comercial.php` (bloque `propiedades`), adaptado al bloque
   `bases`.
8. Tests: extendé `tests/Feature/Personal/GestionBasesPanelTest.php` (alta y
   edición con coordenada válida guardan ambos valores; coordenada fuera de
   rango se rechaza; `latitud` sin `longitud` —o viceversa— se rechaza;
   regresión de alta/edición sin coordenada, que sigue siendo válida).

## Qué NO hacer

- No toques `ubicacion` (texto libre) ni su semántica.
- No agregues pantalla de menú nueva ni mapa — esta tarea es solo el dato
  estructurado, no su visualización.
- No toques `com_propiedades` ni `man_vehiculos`/`man_generadores` — son
  otras tareas.
- No abras el PR — lo hace `bin/ciclo`. Dejá el cuerpo en `runs/100.pr.md`.

## Criterio de aceptación

- `./bin/verify` = 0.
- Test de guardado/lectura de la coordenada (alta y edición).
- Test de que una coordenada fuera de rango (±90/±180) se rechaza.

## Cierre obligatorio

`runs/100.estado` con una sola palabra (`OK` si la HU quedó entera en esta
única etapa prevista; `PARCIAL`/`BLOQUEADA` si no). `runs/100.md` con qué se
hizo y qué falta. Al cerrar con `OK`, `runs/100.pr.md` con el título del PR
en la primera línea y el cuerpo debajo.

## Commits

Agrupados por función (esquema+dominio+casos de uso; controlador+vista+
idioma+tests), español, imperativo, el porqué. Sin trailer
`Co-Authored-By`.
