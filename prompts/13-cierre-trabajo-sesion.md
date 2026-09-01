<!-- ciclo: critica=si turno-noche=1 descongela=tests rama=feature/cierre-trabajo-sesion etapas=4 -->

# Tarea 13 — HU-05: cierre real de trabajo y sesión, visible en el panel

`POST /api/sync` (TE-05, tarea 09, con los hallazgos cerrados en la tarea 12)
solo sabe **abrir** trabajo y sesión. `TransicionesTrabajo`/`TransicionesSesion`
ya declaran `abierto → cerrado` como única transición permitida, pero
`MaquinaEstadosTrabajo`/`MaquinaEstadosSesion` solo tienen `abrir()` — el
propio código lo dice: *"el cierre real con hectáreas llega con HU-05"*. Esta
tarea es ese cierre, de punta a punta hasta que el jefe lo vea en el panel.

Es la parte **servidor** del esqueleto vertical de HU-05 (`plan_sprints.md`,
sprint 2). "Piloto abre trabajo y sesión" ya existe; "la cierra con hectáreas"
y "el jefe la ve en el panel" son lo que falta y lo que construye esta tarea.
No hay parte de `agrocom-field` acá: ese repo es Flutter y no entra a este
ciclo — lo único que puede demostrarse desde acá es con `POST /api/sync`
directo, como ya hacen los tests de `SincronizarLoteTest`.

Es **crítica**: toca el motor de sync y el servicio de estados, la lista de
`CLAUDE.md` que no se delega sin revisión línea por línea. Implementala
completa igual — el PR se abre en borrador.

## Qué hacer

Cargá el skill `verificacion` antes de empezar. Leé
`app/Dominios/Operaciones/` completo (siete archivos ya existen: DTOs de
apertura, máquina de estados, tabla de transiciones, `EscrituraSincronizacionEloquent`)
y `app/Dominios/Sincronizacion/Aplicacion/SincronizarLote.php` antes de tocar
nada — no reimplementes lo que ya está, extendelo.

1. **Migración**: agregá a `ope_trabajos` y `ope_sesiones` lo que el cierre
   necesita. `motivo_cierre` es de esta tarea (el docblock de la migración de
   `ope_sesiones` lo dice explícito, y el prompt de la tarea 12 lo confirma:
   *"no agregues validado_por, fecha_validacion ni motivo_cierre (son de
   HU-14/HU-05)"* — `validado_por`/`fecha_validacion` son HU-14, `motivo_cierre`
   es tuyo). Espec §4.3 da el catálogo de `sesion.motivo_cierre`: `completado`,
   `relevo_piloto`, `cambio_dron`, `falla_equipo`, `clima`, `fin_jornada`,
   `otro` — implementalo como columna simple con su `CHECK`, sin construir la
   lógica de relevo (hectárea acumulada de partida, tolerancia, trabajo
   `parcial`): eso es HU-07, sprint 3, y necesita `dron_id` que todavía no
   existe. `trabajo` no lleva motivo propio en la espec — su cierre es directo.
2. **Dominio**: agregá `cerrar()` a `MaquinaEstadosTrabajo` y
   `MaquinaEstadosSesion`, usando `TransicionesTrabajo::permitida()`/
   `TransicionesSesion::permitida()` como guarda (invariante 7 — nunca un
   `estado = ...` suelto). Cerrar algo que no está `abierto` se rechaza ahí,
   no en el controlador.
3. **Endpoint**: extendé `POST /api/sync` (o el caso de uso `SincronizarLote`)
   para aceptar el cierre. Es una mutación sobre una fila existente, no una
   fila nueva — pero igual tiene que ser idempotente ante reintento
   (invariante 1). Decidí vos el mecanismo exacto (por ejemplo: el evento de
   cierre trae su propio `uuid_cliente`, distinto del de apertura, con su
   propio índice único parcial) y documentalo en `runs/13.md` con el mismo
   nivel de detalle que la tarea 12 documentó sus hallazgos — no hay un
   patrón de "mutación idempotente" ya escrito en el repo que copiar, el que
   hay es el de "fila nueva idempotente" de la apertura. Aplicá la misma
   verificación de pertenencia que la tarea 12 le agregó a la apertura: el
   operario del token no puede cerrar un trabajo o sesión que no es suyo.
4. **Panel**: pantalla mínima nueva bajo Operaciones — lista de trabajos con
   su estado y sus sesiones, sin filtros ni detalle de evidencias (eso es
   HU-15, va después). Alcanza con que el jefe vea que algo se cerró. Seguí el
   patrón de `Distribucion/Infraestructura/Http/Controllers/Web/VersionesApkController.php`:
   `AutorizacionPanelWeb` inyectado, `abort_unless` con un permiso nuevo
   (`operaciones.trabajo.ver` o el nombre que prefieras, patrón
   `modulo.entidad.accion` de `SeguridadSeeder.php`). Agregá el permiso a
   `PERMISOS`/`PERMISOS_*` y una entrada real en `SecMenuSeeder.php` bajo
   "Operación" (hoy ese menú solo tiene "Programación" apuntando al
   dashboard genérico — dejalo, agregá al lado).

## Cómo repartir las etapas

1. Migración + `cerrar()` en la máquina de estados + tests unitarios de la
   transición (incluye el rechazo de cerrar algo no-abierto).
2. Endpoint de cierre sobre `POST /api/sync`, idempotente, con verificación
   de pertenencia + tests de replay (mismo estilo que
   `tests/Feature/Api/SincronizarLoteTest.php`).
3. Pantalla de panel (permiso + menú + Livewire) + tests de panel.
4. Test end-to-end del flujo completo (abrir → cerrar → visible en panel) y
   pulido de la cascada.

## Qué NO hacer

- No implementes la lógica de relevo de HU-07 (hectárea acumulada de partida,
  `cambio_dron` real, tolerancia, trabajo `parcial`) — solo la columna
  `motivo_cierre` como dato, sin esa lógica de negocio.
- No agregues `validado_por`/`fecha_validacion` ni ninguna cola de validación
  (HU-14, tarea siguiente).
- No construyas filtros ni detalle con evidencias en el panel (HU-15).
- No reabras los hallazgos ya cerrados de la tarea 12 ni toques
  `AperturaTrabajo`/`AperturaSesion` salvo que el cierre lo requiera.
- Nada de `agrocom-field`/Flutter.

## Criterio de aceptación

`./bin/verify` = 0, con tests nuevos que fallen si se revierte el cambio:

1. Cerrar un trabajo/sesión `abierto` lo deja `cerrado`, con `fin` seteado y
   (para sesión) `motivo_cierre` persistido.
2. Reintentar el mismo cierre (mismo evento) es idempotente: no rompe, no
   duplica, responde como éxito.
3. Cerrar algo que ya está `cerrado`, o abrir algo `cerrado`, se rechaza por
   la máquina de estados (invariante 7) — no un 500.
4. Un token de operario no puede cerrar un trabajo o sesión que no es suyo
   (mismo criterio de pertenencia que la tarea 12).
5. Test de panel: un usuario con el permiso ve el trabajo cerrado en la
   pantalla nueva; uno sin el permiso recibe 403.

## Cierre obligatorio de cada etapa

`runs/13.estado` con una palabra (`PARCIAL`/`OK`/`BLOQUEADA`). `runs/13.md`
con lo hecho y lo que falta, incluida la decisión de mecanismo de
idempotencia del punto 3. Al llegar a `OK`, `runs/13.pr.md` con título y
cuerpo del PR.

## Commits

Agrupados por pieza: migración+dominio, endpoint de cierre, pantalla de
panel, tests end-to-end. Español, imperativo, el porqué antes que el qué. Sin
trailer `Co-Authored-By`.
