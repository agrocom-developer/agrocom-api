<!-- ciclo: critica=no turno-noche=1 rama=feature/descripcion-cierre-orden etapas=1 descongela=tests -->

# Tarea 104 — HU-89: descripción de mantenimiento final al cerrar la orden

## Qué hacer

`man_ordenes_mantenimiento.descripcion` hoy es una sola columna, fijada al
abrir la orden (`OrdenesMantenimientoController::store()`), y `cerrar()`
nunca la toca. HU-89 pide una segunda descripción, propia del cierre, para
dejar constancia de qué se hizo realmente — sin pisar la de apertura.

Cargá el skill `verificacion` antes de tocar código, y `modelo-datos` antes
de escribir la migración.

1. **Migración** `database/migrations/2026_09_14_100016_add_descripcion_final_a_man_ordenes_mantenimiento_table.php`:
   columna `descripcion_final` (`text`, nullable a nivel de esquema — filas
   cerradas antes de esta tarea no la tienen; la obligatoriedad la exige el
   dominio, no un `NOT NULL` de columna, porque `up()` no puede backfillar un
   texto que nadie escribió).
2. **Dominio**: `app/Dominios/Mantenimiento/Aplicacion/MaquinaEstados/MaquinaEstadosOrdenMantenimiento.php:60`
   — `cerrar()` suma el parámetro `string $descripcionFinal`. Guarda al
   principio del método (antes de la transacción, igual que la guarda de
   transición ya existente): si `trim($descripcionFinal) === ''`, lanzá una
   excepción nueva
   `app/Dominios/Mantenimiento/Dominio/Excepciones/DescripcionFinalRequerida.php`
   (mismo molde `RuntimeException` que `TransicionOrdenMantenimientoNoPermitida`).
   Dentro de la transacción, sumá `$orden->descripcion_final = $descripcionFinal;`
   junto a `estado`/`fecha_cierre`/`gasto_id`.
3. `OrdenMantenimiento.php` (Eloquent): `descripcion_final` a `$fillable` y
   al docblock `@property`.
4. `CerrarOrdenMantenimientoRequest.php`: regla nueva
   `'descripcion_final' => ['required', 'string']`, con su mensaje en
   `messages()` (mismo estilo que los de `repuestos.*`).
5. `OrdenesMantenimientoController::cerrar()` (línea ~124): pasa
   `$request->validated('descripcion_final')` a `$maquinaEstados->cerrar()`.
6. Vista `edit.blade.php`: en el bloque de cierre (`seccion_cierre`, a partir
   de la línea ~154), sumá un `<textarea>` (mismo componente que uses para
   descripción en otros formularios del módulo, revisá `create.blade.php` de
   la propia orden) para `descripcion_final`. En el bloque de datos ya
   cerrados (líneas ~125-140), mostrala junto a `detalle_descripcion` cuando
   `$orden->descripcion_final !== null`.
7. `lang/es/mantenimiento.php`, bloque `ordenes`: clave para el campo
   (`campo_descripcion_final`/`campo_descripcion_final_ayuda`) y para su
   detalle de solo lectura (`detalle_descripcion_final`).
8. Tests: extendé el test HTTP existente del cierre de órdenes (buscá el que
   cubre `panel.ordenes-mantenimiento.cerrar`, probablemente en
   `tests/Feature/Mantenimiento/`) con el caso de éxito (cierre con
   descripción final, se persiste, no pisa `descripcion` de apertura) y el
   de rechazo (cierre sin `descripcion_final` → 422 por la regla del Request
   **y** un test unitario de `MaquinaEstadosOrdenMantenimiento::cerrar()`
   invocado directo con `''`/`'   '` → `DescripcionFinalRequerida`, sin pasar
   por HTTP, para probar que la guarda vive en el dominio y no solo en el
   Request).

## Qué NO hacer

- No toques `descripcion` (la de apertura) ni su columna — HU-89 es
  explícita en que son dos campos separados.
- No hagas `descripcion_final` `NOT NULL` a nivel de columna — rompe el
  `up()` contra filas ya cerradas. La obligatoriedad es de dominio (guarda en
  `cerrar()`) y de request (regla `required`), no de esquema.
- No toques `abrir()` ni las órdenes ya `Abierta` — el cambio es solo en el
  flujo de cierre.
- No toques `docs/decisiones/**` — no hay ADR que ampliar acá.

## Criterio de aceptación

`./bin/verify` = 0, con:
- Test de que `MaquinaEstadosOrdenMantenimiento::cerrar()` rechaza sin
  `descripcion_final` (vacío o solo espacios) con `DescripcionFinalRequerida`.
- Test de que un cierre con `descripcion_final` la persiste sin pisar
  `descripcion` de apertura.
- Test HTTP de que `POST panel.ordenes-mantenimiento.cerrar` sin
  `descripcion_final` responde 422.

## Cierre obligatorio

`runs/104.estado` con una sola palabra: `PARCIAL`/`OK`/`BLOQUEADA` (esta HU
entra en una sola etapa, pero declará `OK` solo si los tres criterios de
arriba están cubiertos). `runs/104.md` con qué se hizo y qué falta, concreto.
Al cerrar con `OK`, `runs/104.pr.md` con título en la primera línea y cuerpo
debajo.

## Commits

Agrupados por función (esquema+dominio+caso de uso en uno,
controlador+vista+idioma+tests en otro), español, imperativo, explicando el
porqué. Sin trailer `Co-Authored-By`.
