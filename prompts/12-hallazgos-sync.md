<!-- ciclo: critica=si turno-noche=0 descongela=tests rama=feature/hallazgos-sync etapas=2 -->

# Tarea 12 — los dos hallazgos de la revisión del motor de sync

El PR #46 (TE-05, `POST /api/sync`) se integró a `develop` el 1/9/2026. La
revisión línea por línea que `CLAUDE.md` exige para el motor de sync se hizo
**después** del merge, sobre `develop`, y encontró dos cosas que hay que
cerrar antes de que HU-16 (devengo automático al validar) las vuelva caras.

No estás reimplementando el sync. Está bien hecho: la idempotencia por
violación del índice único parcial, la transacción por registro, el orden
causal fijo trabajo→sesión, el `DECIMAL` en hectáreas, la bitácora y las
transiciones por máquina de estados se quedan **exactamente como están**.
Leé `app/Dominios/Sincronizacion/` y `app/Dominios/Operaciones/` antes de
tocar nada.

## Hallazgo 1 — `hectareas_declaradas` es el único campo que nadie valida

`AperturaTrabajo::intentarDesdeArreglo()` y `AperturaSesion::intentarDesdeArreglo()`
validan la forma de todos los campos del registro menos uno:
`hectareas_declaradas`, que se castea directo con `(string) $datos[...]`.

Es contradictorio con el propio docblock de `AperturaTrabajo`, que explica que
ese método existe justamente para que "un campo con el tipo equivocado no
termine en un `TypeError` que tumbe el request entero". El campo que quedó
afuera es el de la **invariante 6** de `CLAUDE.md` — hectáreas, `DECIMAL`.

Qué pasa hoy:

- `hectareas_declaradas: []` → `(string) []` emite "Array to string
  conversion" y produce `"Array"`.
- `hectareas_declaradas: "abc"` → en Postgres la inserción falla y el registro
  sale `rechazado`, pero con el motivo genérico "referencia o dato inválido",
  que no dice nada útil al cliente. **En SQLite —el motor de los tests— se
  guarda como 0**, así que ningún test actual puede detectarlo.
- `hectareas_declaradas: "-5"` → solo lo frena el `CHECK` de Postgres, que no
  existe en SQLite.

**Qué hacer**: validar `hectareas_declaradas` en los dos DTO con el mismo
criterio que el resto de los campos — numérico y no negativo, aceptando entero,
float o string numérica, con `'0'` cuando viene ausente. Un valor inválido
devuelve `null` desde `intentarDesdeArreglo()` y el registro sale `rechazado`
con motivo propio, sin frenar el resto del lote.

No uses `float` en ningún punto del camino (invariante 6): la validación
comprueba la forma, el valor sigue viajando como string hasta el `DECIMAL`.

## Hallazgo 2 — `POST /api/sync` no verifica que los ids sean del operario

El endpoint acepta `orden_id`, `lote_id`, `piloto_id` y `auxiliar_id` tal como
vienen del cliente. La única barrera es que la FK exista: nada comprueba que
esa orden, ese lote o esa persona tengan algo que ver con el operario dueño
del token que firma el request.

Tres líneas más arriba, en el mismo `routes/api.php`, está escrito lo
contrario para los endpoints vecinos: *"el scoping vive en los casos de uso
(consultan desde la relación del usuario del token, nunca desde la tabla
global): un id ajeno devuelve 404, igual que en el portal del cliente
(invariante 5)"*. `POST /api/sync` no lo aplica, y el prompt de la tarea 09 no
declaró ese recorte en ninguna parte.

La consecuencia concreta: **un token de dispositivo puede abrir una sesión
atribuida a otro piloto**. Hoy no cobra nadie, así que no hay daño; cuando
HU-16 genere el devengo al validar la sesión, `piloto_id` es exactamente el
campo que decide quién cobra. El agujero tiene que estar cerrado antes de esa
HU, no después.

**Qué hacer**: el contrato de escritura verifica pertenencia antes de aplicar.
Un `orden_id`, `lote_id`, `piloto_id` o `auxiliar_id` que el operario del
token no puede tocar produce `rechazado` con motivo — nunca un `aplicado`, y
nunca un 500.

Decidí vos el criterio exacto de "puede tocar" mirando cómo lo resuelven los
casos de uso vecinos (`GET /api/ordenes` y `GET /api/sync/catalogo` ya
consultan desde la relación del usuario del token): seguí ese mismo camino en
vez de inventar uno nuevo. Si el criterio correcto para alguno de los cuatro
ids no se puede decidir con lo que hay en el repo, implementá los que sí y
dejá escrito en `runs/12.md` cuál quedó afuera y por qué — no lo adivines.

## Alcance

Solo eso. **No** agregues `validado_por`, `fecha_validacion` ni
`motivo_cierre` (son de HU-14/HU-05), no toques la máquina de estados, no
extiendas el sync a `mezcla`/`recarga`/`incidencia`/`acta`, y no modifiques
las migraciones existentes salvo que la verificación de pertenencia necesite
un índice — si lo necesita, va en una migración nueva.

## Criterio de aceptación

`./bin/verify` = 0, con tests nuevos que fallen si se revierte el cambio:

1. Un registro con `hectareas_declaradas` no numérica sale `rechazado` con su
   motivo, y el resto del lote se aplica igual.
2. Un registro con `hectareas_declaradas` negativa sale `rechazado`.
3. Un `trabajo` cuyo `lote_id` no corresponde al operario del token sale
   `rechazado`, y no se crea la fila.
4. Una `sesion` cuyo `piloto_id` es de otra persona ajena al operario del
   token sale `rechazado`, y no se crea la fila.
5. Los tests que ya existen del PR #46 siguen en verde **sin editarlos** —
   `tests/` está congelado salvo para agregar los casos nuevos.

## Commits

Uno por pieza coherente: la validación de hectáreas en los DTO, la
verificación de pertenencia en el contrato de escritura, los tests. En
español, imperativo, explicando el porqué. Sin trailer `Co-Authored-By`.
