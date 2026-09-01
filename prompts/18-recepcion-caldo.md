<!-- ciclo: critica=si turno-noche=1 descongela=tests rama=feature/recepcion-caldo etapas=4 -->

# Tarea 18 — HU-10: recepción de caldo, redefinida por CR-01

CR-01 (cerrada 1/9/2026, espec §7) redefinió esta HU: **Agrocom no prepara
ni formula el caldo** — lo recibe ya hecho del cliente y lo rocía. HU-11 y
HU-12 (cálculo de cantidades, checklist de incorporación) quedaron
eliminadas del plan. Lo que sí registra Agrocom (§7.2, literal):

> Litros recibidos: cuánto caldo entrega el cliente, cuándo y quién lo
> entregó. Litros consumidos por sesión: qué se roció efectivamente en cada
> sesión. Sobrante: cuánto quedó sin aplicar al cerrar, y que se devuelve al
> cliente.

Y el porqué (§7.3): "Agrocom puede demostrar cuántos litros recibió,
cuántos aplicó sobre qué lote y cuántos devolvió, y que la diferencia
cuadra. Sobre la composición de esos litros no opina, no calcula y no
responde." Ningún dato de producto, dosis ni receta — [[alcance-mezcla-es-del-cliente]]
si tenés esa memoria a mano, es exactamente esto.

**Es crítica**: agrega tipos de registro nuevos al motor de sync
(`SincronizarLote`, `EscrituraSincronizacion`) — igual que la tarea 13 y la
tarea 17. El PR se abre en borrador.

## Qué hacer

Cargá el skill `verificacion`, y `arquitectura` para la decisión de módulo
de abajo. Leé antes de tocar nada:

- `docs/especificacion/especificacion_funcional_tecnica.md` §7 entero
  (contexto de negocio de CR-01) y §4.3 (`trabajos`, `sesiones` — a qué se
  atan `recibido`/`consumido`/`sobrante`).
- `docs/decisiones/0011-convencion-prefijos-tabla.md` — `Mezclas` (`mez_`)
  está reservado desde siempre para esto, pero la carpeta nunca se creó
  porque dependía de recetas/productos que CR-01 eliminó.
- `app/Dominios/Sincronizacion/Aplicacion/SincronizarLote.php` y
  `app/Dominios/Operaciones/Contratos/CierreSesion.php`/`CierreTrabajo.php`
  — el patrón de DTO y de extensión del motor de sync que vas a replicar.

**Decisión de módulo, a tu cargo.** Con recetas/mezcla_items fuera de
alcance, lo que queda de `Mezclas` es puro registro de volumen atado 1:1 a
`trabajo`/`sesion` — que ya son de `Operaciones`. Evaluá si esto entra en
`Operaciones` (evita un módulo nuevo casi vacío) o si conviene crear
`Mezclas` reducido a esto (respeta el prefijo `mez_` que ADR 0011 ya
reservó, y deja el lugar preparado para cuando el negocio agregue algo más
de mezcla). Ninguna opción es obviamente correcta — documentá la que
elijas y el porqué en `runs/18.md`, mismo tratamiento que la tarea 15 le dio
a la proyección de estado de tablero.

**Diseño de datos, a tu cargo también.** La espec describe tres hechos
(`recibido` por trabajo, `consumido` por sesión, `sobrante` al cierre del
trabajo). Podés modelarlos como una tabla de eventos con `tipo` (recibido /
consumido / sobrante) o como tres tablas — o, para `consumido`/`sobrante`,
como columnas agregadas a los DTOs `CierreSesion`/`CierreTrabajo` que ya
existen (litros declarados al cerrar, mismo criterio que
`hectareas_declaradas`). Elegí lo que se sostenga mejor contra el criterio
de aceptación 4 (recibido = consumido + sobrante, recalculable) y
documentalo.

1. **Migraciones + módulo.** Lo que decidiste arriba. `DECIMAL` para
   litros (invariante 6), `uuid_cliente` `UNIQUE` en cada evento que nace en
   la app de campo (invariante 1), auditoría/soft-delete.
2. **Motor de sync.** Nuevos tipos en `ORDEN_CAUSAL` (o los campos nuevos en
   los DTOs de cierre existentes, según lo que decidiste), extensión de
   `EscrituraSincronizacion`, implementación en
   `EscrituraSincronizacionEloquent`.
3. **Cuadre.** Un cálculo/consulta que compara recibido vs.
   consumido+sobrante para un trabajo — no hace falta que bloquee nada
   (el desvío se alerta en HU-19, bandeja de alertas, sprint 5, tarea
   aparte): alcanza con que el dato quede registrado exacto y sea
   consultable. El criterio de aceptación 4 exige el cálculo correcto, no
   una regla de negocio que rechace el desajuste.
4. **Tests de integración** de lote completo (recibido + consumido +
   sobrante en trabajos/sesiones reales, reintentos, orden causal).

## Cómo repartir las etapas

1. Decisión de módulo y de esquema (documentada) + migraciones + modelos.
2. Extensión del motor de sync con los tipos/campos nuevos.
3. Cálculo de cuadre (recibido = consumido + sobrante) + tests.
4. Tests de integración de punta a punta + pulido, cascada verde.

## Qué NO hacer

- Sin recetas, productos, dosis ni `mezcla_items` — HU-11/HU-12 están
  eliminadas, ningún cálculo de composición.
- Sin recargas (batería, temperatura, retraso por caldo) — eso es HU-13,
  tarea aparte, aunque comparta la idea de "litros" con esta.
- Sin bandeja de alertas ni bloqueo por desvío — HU-19, sprint 5.
- No toques `EstadoTrabajo`/`EstadoSesion`.
- Nada de `agrocom-field`/Flutter.

## Criterio de aceptación

`./bin/verify` = 0, con tests nuevos:

1. Registrar litros recibidos por un trabajo (uno o varios eventos) queda
   persistido y es consultable.
2. Registrar litros consumidos por una sesión se asocia a la sesión
   correcta.
3. Registrar el sobrante al cierre del trabajo queda persistido.
4. Un test verifica el cuadre: para un trabajo con recibido, consumido (de
   sus sesiones) y sobrante conocidos, `recibido == consumido + sobrante`
   se puede recalcular exacto desde los registros de origen (invariante 6).
5. Reintento del mismo `uuid_cliente` en cualquiera de los tres eventos no
   duplica.

## Cierre obligatorio de cada etapa

`runs/18.estado` (`PARCIAL`/`OK`/`BLOQUEADA`). `runs/18.md` con la decisión
de módulo, la decisión de esquema, ambas con su porqué, y qué falta (HU-13
queda para una tarea aparte — decilo explícito). Al llegar a `OK`,
`runs/18.pr.md`.

## Commits

Agrupados: decisión de esquema y migraciones, extensión del motor de sync,
cálculo de cuadre, tests de integración. Español, imperativo, el porqué
antes que el qué. Sin trailer `Co-Authored-By`.
