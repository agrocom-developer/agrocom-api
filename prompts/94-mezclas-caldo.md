<!-- ciclo: critica=si turno-noche=1 rama=feature/mezclas-caldo etapas=5 descongela=tests -->

# Tarea 94 — HU-78: módulo Mezclas (revierte CR-01)

## Qué hacer

El dueño cambió de opinión el 13/9/2026: ahora sí quiere que el piloto
registre qué productos y en qué cantidad se cargaron en el caldo al crear
una aplicación. Esto **revierte CR-01** (cerrada el 1/9/2026, "Agrocom no
prepara la mezcla"). La nota fechada ya está puesta en
`docs/especificacion/especificacion_funcional_tecnica.md` §7 (líneas
~264-274) apuntando a que esta tarea la reescribe — leela primero, es el
contrato de lo que cambia y lo que no.

**Es crítica**: toca el motor de sync. Se integra igual a `develop` — el
ciclo NO abre el PR en borrador por ser crítica (esa política se corrigió el
2/9/2026, ver `docs/gestion/automatizacion_desarrollo.md` §5); la revisión
línea por línea es **posterior**, y el propio ciclo la anota en
`runs/revision-pendiente.txt` al abrir el PR. **No crees el PR vos mismo**
en ningún momento de la implementación — eso ya causó que las tareas 85/86
quedaran trabadas en borrador (ver la nota de deuda técnica del 14/9/2026 en
`docs/gestion/cola_tareas.md`). Dejá que `bin/ciclo` abra el PR al terminar.

Cargá los skills `verificacion`, `dominio-backend` y `modelo-datos` antes de
tocar código.

1. **Decidí el módulo dueño de las tablas nuevas**, documentando el porqué
   en un docblock de la migración (mismo criterio que usó la tarea 18 para
   decidir entre `Mezclas` y `Operaciones`). Candidatos razonables: un
   submódulo `Operaciones/Mezclas` o un módulo `Mezclas` nuevo y propio. Lo
   que importa es que quede una sola decisión escrita, no las dos mitad
   hechas.

2. **Esquema**: cabecera + detalle. La cabecera liga la mezcla a un trabajo
   (o sesión — decidilo mirando cuándo existe la información real: "al
   crear una aplicación" sugiere trabajo) por `uuid_cliente`, mismo patrón
   de idempotencia que `ope_estadias_hacienda` (invariante 1). El detalle es
   producto + cantidad + unidad. Sumale un **catálogo de productos propio**
   (tabla, no un enum embebido): la tarea 95 (HU-79, siguiente en la cola)
   lo va a reusar para filtrar por tipo sólido/líquido — no hace falta
   anticipar esa columna ahora, pero sí dejar el catálogo en una tabla fácil
   de extender.

3. **Motor de sync**: nuevo tipo de registro, mismo patrón que
   `estadia_entrada`/`estadia_salida` (HU-51, tarea 74). Tocá:
   - `app/Dominios/Sincronizacion/Aplicacion/SincronizarLote.php` — sumar el
     tipo a `ORDEN_CAUSAL` y su rama de aplicación.
   - La escritura del registro (mismo patrón que
     `app/Dominios/Operaciones/Infraestructura/EscrituraSincronizacionEloquent.php`,
     o su equivalente si elegiste otro módulo — revisá cómo resuelve
     `estadia_entrada` el `uuid_cliente`/idempotencia y calcalo).
   - `app/Dominios/Sincronizacion/Infraestructura/Http/Controllers/Api/SyncController.php`
     (anotaciones OpenAPI) y `docs/api/openapi.yaml`.

4. **Reporte técnico**: `ArmarContenidoReporteTecnico::notaMezcla()`
   (`app/Dominios/Operaciones/Aplicacion/ArmarContenidoReporteTecnico.php:195`)
   hoy imprime una nota fija de "fuera de alcance". Reemplazala por el
   listado real de productos cargados en la mezcla del trabajo. Mantené el
   nombre del método si sigue teniendo sentido, o renombralo si ya no
   describe lo que hace.

5. **Reescribí `especificacion_funcional_tecnica.md` §7`** con el alcance
   nuevo: qué transcribe el piloto (producto, cantidad, unidad — lo que
   cargó) vs. qué Agrocom sigue sin validar ni calcular (dosis correcta,
   compatibilidad entre productos, orden de incorporación, triple lavado).
   El deslinde de responsabilidad de §7.1 sigue siendo el motivo — Agrocom
   registra qué se cargó, no opina si es lo correcto. Quitá la nota fechada
   del 13/9/2026 una vez reescrita la sección (ya cumplió su función de
   apuntar acá).

## Cómo repartir las etapas

- **Etapa 1**: decisión de módulo documentada, migraciones (catálogo de
  productos, cabecera, detalle), modelos Eloquent, contrato de lectura si
  hace falta.
- **Etapa 2**: motor de sync — tipo de registro nuevo, escritura,
  resolución de `uuid_cliente`/idempotencia.
- **Etapa 3**: tests de idempotencia y replay (mismo criterio que HU-51:
  mismo lote de registros aplicado 10 veces, en orden y en desorden → una
  sola fila).
- **Etapa 4**: reporte técnico (deja de imprimir la nota fija, lista
  productos) + su test.
- **Etapa 5**: reescritura de §7 de la especificación, `openapi.yaml`,
  `./bin/verify` completo.

## Qué NO hacer

- No modelar dosis por hectárea, cálculo de producto por tanque, checklist
  secuencial de incorporación, orden de mezcla ni compatibilidad entre
  productos — CR-01 solo se revierte para "qué se cargó", no para "cómo se
  calculó". §7.1 sigue vigente en eso.
- No tocar `docs/decisiones/**` — esto es negocio y especificación, no una
  decisión de arquitectura nueva (no hay ADR que ampliar acá).
- No crear el PR vos mismo ni pasarlo por `--draft` en ningún punto — lo
  abre el ciclo al cerrar la tarea con `OK`.
- No anticipar la columna de tipo sólido/líquido del catálogo de productos
  (eso es de la tarea 95) — solo dejar la tabla lista para que se le agregue
  después sin migración destructiva.

## Criterio de aceptación

`./bin/verify` = 0, con:
- Test de idempotencia por `uuid_cliente` sobre el nuevo tipo de registro
  (replay 10 veces, en orden y en desorden, → base idéntica).
- Test de que el reporte técnico lista los productos cargados en vez de la
  nota fija de "fuera de alcance".
- `grep` de que §7 ya no describe la prohibición absoluta sin la nota de
  reversión (la nota fechada del 13/9/2026 desaparece una vez reescrita).

## Cierre de cada etapa

`runs/94.estado` con una palabra. `runs/94.md` con qué se hizo y qué falta,
concreto. Al llegar a `OK`, `runs/94.pr.md` con título + cuerpo.

Commits agrupados por función (esquema, motor de sync, reporte técnico,
especificación), en español, imperativo, explicando el porqué. Sin
`Co-Authored-By`.
