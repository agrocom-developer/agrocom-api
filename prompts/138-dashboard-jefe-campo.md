<!-- ciclo: critica=no turno-noche=1 rama=feature/dashboard-jefe-campo etapas=3 -->

# Tarea 138 — dashboard del Jefe de Campo en 3 tabs propias

Requiere la 135 integrada. Leé `docs/gestion/plan_dashboard_notificaciones_por_rol.md`
§5. Cargá los skills `panel-design-ui`, `dominio-backend` y `verificacion`.

## Qué hacer

Agrupá el rol `jefe_campo` en 3 tabs:

1. **Recursos que se ocupan o faltan**: `Stock` (`stockBajoMinimo`) ya
   existe como sección — es la mitad de esta tab. La otra mitad ("qué se
   ocupa") no tiene sección hoy: mirá `EquipoPersonaPanel`/`LecturaEquipoTrabajo`
   para armar qué equipos/cuadrillas están asignados ahora mismo.
2. **Órdenes de trabajo con sus cuadrillas**: no existe sección hoy.
   Necesitás un contrato de lectura (en `Operaciones`, dueño de
   `ope_ordenes_aplicacion`/trabajos) que devuelva las OT agrupadas por
   cuadrilla asignada — mirá el patrón de `diasEnHacienda()` en
   `ArmarDashboard` para cómo ya cruza Operaciones (cuentas) con Personal
   (nombres de cuadrilla, vía `LecturaEquipoTrabajo->porIds()`).
3. **Estado de todas las órdenes de aplicación, con haciendas y
   equipamiento**: cruza Operaciones (estado de la OA) con Mantenimiento
   (estado del dron/batería/vehículo asignado). Investigá qué contrato de
   lectura ya expone `Mantenimiento` (buscá `Contratos/` de ese módulo) antes
   de escribir uno nuevo.

## Qué NO hacer

No toques el agrupamiento de ningún otro rol. No agregues acciones de
escritura a estas tabs (asignar, reasignar, dar de baja): el dashboard es de
lectura, las acciones viven en sus pantallas propias (`/panel/ordenes`,
`/panel/cuadrillas`, etc.) — un link "ver más" que lleve ahí está bien, un
botón que mute algo desde acá no.

## Criterio de aceptación

- `./bin/verify` = 0.
- Un jefe de campo demo ve exactamente esas 3 tabs con datos reales cruzando
  los módulos que corresponde.
- Playwright (`runs/138-navegador.cjs`), claro y oscuro.

## Cierre obligatorio de cada etapa

`runs/138.estado`, `runs/138.md`, y al `OK` `runs/138.pr.md`. Commits por
función, en español, imperativo, sin `Co-Authored-By`.
