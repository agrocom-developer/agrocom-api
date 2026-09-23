<!-- ciclo: critica=no turno-noche=1 rama=feature/dashboard-encargado-operaciones etapas=3 -->

# Tarea 136 — dashboard del Encargado de Operaciones en 3 tabs propias

Requiere la tarea 135 integrada en `develop` (el mecanismo de agrupamiento
por rol en `ArmarDashboard`/`dashboard.blade.php`); si no está, `runs/136.estado`
= `BLOQUEADA`. Leé `docs/gestion/plan_dashboard_notificaciones_por_rol.md`
§5 y el `runs/135.md` de la tarea que abre el mecanismo — usá el mismo lugar
y la misma forma (tabla rol → tabs → secciones) que esa tarea dejó, no
inventes una segunda. Cargá los skills `panel-design-ui`, `dominio-backend`
y `verificacion`.

## Qué hacer

Agrupá el rol `encargado_operaciones` en 3 tabs:

1. **Estados de las aplicaciones**: distinguí de entrada si "aplicaciones"
   es la Orden de Aplicación (`ope_ordenes_aplicacion`, máquina de estados
   propia, ADR 0022) o la sesión de vuelo (`DistribucionSesiones` ya
   existente, agrupa sesiones por estado). Son datos distintos — probablemente
   la tab quiere el primero (distribución de órdenes de aplicación por
   estado), que hoy no tiene sección. Si no existe ya un método de lectura
   para eso en `LecturaPanelOperaciones`, agregalo ahí (el módulo dueño de
   la tabla), no en `ArmarDashboard`.
2. **Proceso de los trabajos**: `ColaValidacion` (sesiones por validar) más
   `Pausas` (causas y minutos del mes) — ambas ya existen como secciones,
   agruparlas en una tab es el trabajo, no construir contenido nuevo.
3. **Resumen de vuelos** con selector diario/semanal/mensual:
   `HectareasPorDia` (`operaciones->hectareasPorDia($dias)`) hoy solo sirve
   una ventana fija en días. Extendé `LecturaPanelOperaciones` para aceptar
   una granularidad (día/semana/mes) en vez de escribir tres métodos
   separados — reusá el query, cambiá el `GROUP BY`.

## Qué NO hacer

No toques el agrupamiento de Dueño (135) ni de ningún otro rol. No cambies
la máquina de estados de la Orden de Aplicación (ADR 0022) — esta tarea solo
lee y muestra su distribución, no agrega transiciones nuevas.

## Criterio de aceptación

- `./bin/verify` = 0.
- Un usuario con rol activo `encargado_operaciones` ve exactamente esas 3
  tabs con contenido correcto; el selector de granularidad del resumen de
  vuelos cambia el agregado sin recargar la página completa (o con recarga,
  a tu criterio, pero probado en los tres casos).
- Playwright (`runs/136-navegador.cjs`) con el encargado demo, las 3
  granularidades del resumen de vuelos, claro y oscuro.

## Cierre obligatorio de cada etapa

`runs/136.estado`, `runs/136.md`, y al `OK` `runs/136.pr.md`. Commits por
función, en español, imperativo, sin `Co-Authored-By`.
