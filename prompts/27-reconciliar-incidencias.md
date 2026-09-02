<!-- ciclo: critica=si turno-noche=0 descongela=tests rama=feature/incidencias-sesion etapas=3 -->

# Tarea 27 — reconciliar `feature/incidencias-sesion` con `develop`

## Esto no es una HU nueva: es una integración

La rama `feature/incidencias-sesion` tiene **HU-08 (incidencias de sesión con
foto obligatoria) implementada, verificada y aprobada** — 5 commits, tarea 22,
PR #59. `runs/22.md` la cierra con "Falta: nada". No empieces de cero, no
rehagas la HU, no borres nada de lo que hay.

Quedó sin integrar por un error de proceso, no por un problema del código: la
política del PR en borrador para tareas críticas se cambió el 1/9/2026 en
`CLAUDE.md` y en `bin/ciclo`, pero `automatizacion_desarrollo.md` §5 siguió
documentando la vieja hasta el 2/9. La sesión de implementación leyó ese
documento y abrió el PR en borrador por su cuenta, así que se quedó esperando
una revisión previa que ya no correspondía. Mientras tanto `develop` avanzó
cuatro tareas y la rama quedó con conflictos.

Tu trabajo es **integrar `develop` en esa rama resolviendo los conflictos**, y
nada más.

## Los conflictos

Siete archivos. Cinco son del motor de sync, que es exactamente lo que
`CLAUDE.md` marca como "no delegar sin revisión línea por línea" — de ahí que
esta tarea sea `critica=si`:

- `app/Dominios/Operaciones/Contratos/EscrituraSincronizacion.php`
- `app/Dominios/Operaciones/Infraestructura/EscrituraSincronizacionEloquent.php`
- `app/Dominios/Sincronizacion/Aplicacion/SincronizarLote.php`
- `app/Dominios/Sincronizacion/Infraestructura/Http/Controllers/Api/SyncController.php`
- `tests/Feature/EscrituraSincronizacionTest.php`
- `docs/api/openapi.yaml` — regenerado, no se resuelve a mano
- `docs/gestion/cola_tareas.md` — documentación

La causa es la misma en los cinco primeros: entre la tarea 22 y hoy, las
tareas 18 (recepción de caldo), 19 (evidencias), 20 (relevo de piloto), 21
(cierre de lote) y 23 (recargas) **también** extendieron el contrato de
escritura, el caso de uso del lote y el controlador, cada una agregando su
tipo de registro. Son extensiones paralelas del mismo punto: casi todo debería
resolverse **conservando ambos lados**, no eligiendo uno.

## Cómo resolverlo

1. Leé primero qué agregó cada tarea: `git log --oneline` de `develop` desde
   el punto en que se separó la rama, y el diff de la rama contra ese punto.
   Entendé las dos formas antes de tocar un marcador.
2. Resolvé conservando **las dos** extensiones donde sean aditivas: cada tipo
   de registro del sync (`incidencia`, `recarga`, `caldo`, `evidencia`,
   `relevo`…) tiene que seguir existiendo después del merge. Perder uno es un
   fallo silencioso: el lote lo respondería como "tipo desconocido".
3. `docs/api/openapi.yaml` **no se edita a mano**: se regenera con
   `composer openapi` después de resolver el código.
4. `docs/gestion/cola_tareas.md`: gana la versión de `develop`.
5. El orden causal de `SincronizarLote` es la parte delicada. Si las dos ramas
   tocaron la constante de orden, el resultado tiene que dejar a `trabajo`
   antes que `sesion`, y a `sesion` antes que todo lo que la referencia
   (incidencias, recargas, caldo, evidencias). Verificá esa cadena
   explícitamente en vez de asumirla.

## Qué NO hacer

No rehagas HU-08. No cambies el alcance de las incidencias. No toques las
migraciones existentes. No "simplifiques" el motor de sync aprovechando el
viaje: cualquier cambio que no sea resolver un conflicto es alcance de más y
un hallazgo del verificador.

Si un conflicto no se puede resolver conservando ambos lados —porque las dos
ramas cambiaron la misma línea con intenciones incompatibles— no adivines:
resolvé el resto, dejá ese caso escrito en `runs/27.md` con las dos versiones
y por qué chocan, y declará la tarea `BLOQUEADA`.

## Criterio de aceptación

`./bin/verify` = 0 sobre la rama ya mergeada, y además:

1. Los tests que ya existen —los de HU-08 y los de las tareas 18 a 23— pasan
   todos **sin editarlos**. `tests/` está congelado: si un test falla, el
   problema está en tu resolución del conflicto, no en el test.
2. Un test que envíe un lote con **un registro de cada tipo** que el sync
   acepta hoy, y verifique que todos salen `aplicado`. Es la prueba de que el
   merge no perdió ninguna extensión — agregalo si no existe uno equivalente.
3. `composer openapi` no deja diferencias sin commitear.

## Commits

Uno por pieza coherente: la resolución del contrato y su implementación, la
del caso de uso y el controlador, la del test, y el OpenAPI regenerado. En
español, imperativo, explicando **qué chocaba y por qué se resolvió así** —
que es lo único que un merge conflictivo deja como rastro útil. Sin trailer
`Co-Authored-By`.
