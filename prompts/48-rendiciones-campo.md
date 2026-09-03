<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/rendiciones-campo etapas=4 -->

# Tarea 48 — HU-34: rendiciones de campo

## Por qué esta tarea

`plan_sprints.md` Sprint 10 (§219): "Como jefe de campo, quiero rendir lo
que gasté en campo y que el encargado lo apruebe, para reponer el fondo."
CA esencial: rendición con detalle e ítems; estados `abierta → presentada →
aprobada`; el aprobador nunca es quien rinde. Depende de que `fin_gastos`
(tarea 47, HU-33) ya esté en `develop` — el "detalle e ítems" de una
rendición son gastos ya cargados.

**No es crítica**: es un flujo administrativo de aprobación, no un listener
que genera dinero de por sí (no desembolsa nada — solo cierra el ciclo de
"esto ya se gastó y quedó respaldado").

**Módulo dueño: `Finanzas`**, junto a `fin_gastos`.

## Nombres de estado: seguí el plan de sprints, no la especificación literal

La especificación (`especificacion_funcional_tecnica.md`, ~línea 147) usa
`pendiente / procesada / rechazada` para el estado de rendición. El CA
esencial de esta HU en `plan_sprints.md` usa `abierta → presentada →
aprobada`. Son la misma idea con nombres distintos — **seguí los nombres
del plan de sprints**, es la fuente del alcance de esta tarea (igual
criterio que otras tareas del ciclo: el plan de sprints manda sobre el
alcance ejecutable, la especificación es la referencia funcional de fondo,
no el vocabulario literal a copiar cuando difieren).

## Qué hacer

Cargá las skills `modelo-datos`, `dominio-backend`, `panel-design-ui` y
`verificacion`.

### 1. Modelo de datos

- `fin_rendiciones`: `id, base_id` (FK `per_bases`), `jefe_campo_id` (FK
  `per_personas`, entero plano sin `belongsTo` cross-módulo — mismo
  criterio que `Anticipo.persona_id`), `fecha DATE, descripcion`,
  `monto DECIMAL` (= suma de los gastos asociados, recalculado al
  presentar — nunca editable a mano), `estado` (enum
  `EstadoRendicion`: `abierta`/`presentada`/`aprobada`, con `CHECK` en
  Postgres — mismo patrón que `EstadoContrato`/`EstadoPlanilla`),
  `aprobado_por` (nullable, FK `per_personas` plana, se completa al
  aprobar), auditoría, soft delete.
- `ALTER TABLE fin_gastos ADD rendicion_id` (FK `fin_rendiciones`,
  nullable) — un gasto sin rendición sigue siendo válido (gasto general no
  rendido todavía). Migración nueva, no toques la migración de la tarea
  47.

### 2. Máquina de estados — mismo patrón que sesión/contrato/planilla

`Aplicacion/MaquinaEstados/MaquinaEstadosRendicion.php` +
`Dominio/MaquinaEstados/TransicionesRendicion.php` (tabla de transiciones
permitidas + guardas, invariante 7 de `CLAUDE.md` — nunca un `estado = ...`
suelto en el controlador).

- `abierta → presentada`: guarda "al menos un gasto asociado" (una
  rendición sin ítems no se puede presentar).
- `presentada → aprobada`: guarda "el aprobador no es el `jefe_campo_id` de
  esa rendición" — **a nivel de persona, no de rol** (invariante 4 de
  `CLAUDE.md`, mismo mecanismo que "validador ≠ piloto" de HU-14). Mirá
  `Operaciones/Dominio/PoliticaValidacionSesion.php` y la excepción
  `PilotoNoPuedeDecidirSuPropiaSesion` como referencia directa del patrón —
  esta guarda es su espejo para rendiciones. Al aprobar, recalculá `monto`
  desde la suma real de gastos asociados (no confíes en un valor
  persistido antes) y persistilo como snapshot final.

### 3. Caso de uso y HTTP

`Finanzas/Aplicacion/`: `CrearRendicion` (abre en `abierta`, con
`base_id`/`jefe_campo_id`/`descripcion`), `AsociarGastoARendicion` (setea
`rendicion_id` en un gasto propio, sin rendición ya asignada), `PresentarRendicion`,
`AprobarRendicion`. `RendicionesController@index/create/store/presentar/aprobar`.

Permisos `finanzas.rendicion.ver`/`.crear`/`.presentar`/`.aprobar` en
`PERMISOS_ENCARGADO_OPERACIONES` salvo que decidas que `.aprobar` merece
quedar fuera como hizo `finanzas.planilla.aprobar` (exclusivo del dueño) —
la historia dice "el encargado lo aprueba", así que `.aprobar` sí va en
`PERMISOS_ENCARGADO_OPERACIONES` a diferencia de planilla; la guarda real
de "el aprobador nunca es quien rinde" ya la resuelve la máquina de estados
por persona, no el permiso.

Activá el ítem **`rendiciones`** ya sembrado como "botón sin link" en el
grupo `financiero` de `SecMenuSeeder.php` (línea ~111) — no crees uno
nuevo.

## Qué NO hacer

- No copies los nombres de estado de la especificación
  (`pendiente/procesada/rechazada`) — usá los del plan de sprints.
- No permitas asociar a una rendición un gasto que ya pertenece a otra
  rendición viva.
- No dejes aprobar una rendición sin ítems, ni presentar una ya presentada
  o aprobada — todo pasa por `MaquinaEstadosRendicion`.
- No implementes `fondos_caja` ni el saldo del fondo — no está en el CA
  esencial de esta HU, y `fin_rendiciones` no necesita esa tabla para
  cumplir su propio criterio.
- No calcules `monto` con floats.

## Cómo repartir las etapas

- **Etapa 1**: migraciones (`fin_rendiciones`, `ALTER fin_gastos`),
  modelo, `EstadoRendicion`, `TransicionesRendicion`.
- **Etapa 2**: `MaquinaEstadosRendicion`, casos de uso.
- **Etapa 3**: controller, rutas, permisos, menú, vistas + copy.
- **Etapa 4**: tests Feature + spec visual + checklist §8.

## Criterio de aceptación

- `./bin/verify` = 0, con Playwright.
- Test Feature (`tests/Feature/Finanzas/RendicionesPanelTest.php`)
  cubriendo: transición `abierta → presentada → aprobada` válida; rechazo
  de `presentada → aprobada` cuando el aprobador es el mismo
  `jefe_campo_id` que la rindió (aunque tenga el permiso); rechazo de
  presentar una rendición sin ningún gasto asociado; `monto` exacto = suma
  de los gastos asociados, recalculado al aprobar, sin error flotante;
  transición inválida rechazada (p. ej. `abierta → aprobada` directo); 403
  sin el permiso; bitácora en cada transición.
- Spec visual (`tests/Visual/rendiciones.spec.ts`), claro y oscuro.
- Checklist de `docs/diseno/guia_pantalla_panel.md` §8 pasa.

## Puede tocar

`app/Dominios/Finanzas/**` (incluida la migración `ALTER` de
`fin_gastos`), migraciones nuevas, `routes/web.php`,
`database/seeders/Catalogo/SeguridadSeeder.php`,
`database/seeders/Catalogo/SecMenuSeeder.php`, `lang/es/finanzas.php`,
`tests/**`.

Fuera de alcance: `fondos_caja`, combustible (HU-35), cualquier cambio a la
migración original de `fin_gastos` de la tarea 47 (solo `ALTER`, no la
reescribas).

---

**Nota de la planificación**: esta tarea asume que la 47 (HU-33, gastos) ya
está integrada en `develop` cuando arranque — el orden de `runs/cola.txt`
lo garantiza. Si por algo excepcional no lo está, es `BLOQUEADA`, no
adivines el esquema de `fin_gastos`.
