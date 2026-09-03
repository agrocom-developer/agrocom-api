<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/devengos-panel etapas=3 -->

# Tarea 40 — HU-28: devengos por período

## Por qué esta tarea

`plan_sprints.md` Sprint 8 (§191): "Como piloto o auxiliar, quiero ver mis
devengos por período, para saber qué voy a cobrar antes de que cierre el
mes." Sprint 7 (catálogos) está entero integrado — esta abre Sprint 8 ("la
gente cobra a fin de mes"). `fin_devengos_personal` existe, migrada y
poblada por `GenerarDevengosSesion` (HU-16, tarea 16) desde el 1/9/2026: hay
datos reales, falta la pantalla.

**El módulo `Finanzas` hoy es puro backend, sin una sola pantalla.** Solo
existen `Aplicacion/GenerarDevengosSesion.php`,
`Dominio/Excepciones/PersonaSinTarifaHa.php`,
`Infraestructura/Eloquent/DevengoPersonal.php` y
`Infraestructura/FinanzasServiceProvider.php` (que solo registra el listener
de `SesionValidada`, sin `View::addNamespace`). Armás el primer `Http/` de
Finanzas desde cero, mismo patrón que estableció la tarea 37 en `Personal`.

**Piloto y auxiliar hoy no tienen ningún acceso al panel web.** Confirmado
en `SeguridadSeeder.php`: el comentario de `piloto` dice literal "ejecuta
desde `agrocom-field`, sin acceso al panel"; `auxiliar` se siembra "sin
permisos de seguridad ni de panel". Esta HU les da su primer permiso de
panel — no es un error de alcance, es lo que el criterio de aceptación pide
("como piloto o auxiliar, quiero ver..."). No confundas esto con la app de
campo (`agrocom-field`, Flutter, fuera de este repo por regla permanente de
`cola_tareas.md`): esta es una pantalla del panel Blade, con el piloto/
auxiliar como usuario del panel igual que cualquier otro rol, autenticado
con su `sec_user` (username/password) existente.

**El scoping por identidad ("un operario ve solo lo suyo") no tiene
precedente en el panel.** Todo lo que existe hoy filtra por permiso/rol
(`AutorizacionPanelWeb::tienePermiso()`), nunca por "esto es mío". Sí existe
la pieza que necesitás: `AutorizacionPanelWeb::personaId($request)` ya
resuelve la `persona_id` del usuario de panel autenticado (la usa HU-14 para
la policy validador≠piloto) — reusala, no dupliques esa resolución.

## Qué hacer

Cargá las skills `dominio-backend`, `panel-design-ui` y `verificacion`.

### 1. `FinanzasServiceProvider::boot()`

Agregale `View::addNamespace('finanzas', app_path('Dominios/Finanzas/
Infraestructura/Http/Views'))`, sin tocar el listener de `SesionValidada`
que ya registra.

### 2. Query de lectura — `app/Dominios/Finanzas/Aplicacion/ListarDevengosPersona.php`

Filtra `DevengoPersonal` por `persona_id` y por período (mes calendario:
`whereBetween('fecha', [inicio, fin])`, parámetro `periodo` formato
`YYYY-MM`, default mes actual). Devolvé también el total sumado en
`DECIMAL` (invariante 6: sumá con `Brick\Math\BigDecimal`, no `array_sum`
sobre floats — mismo criterio que `GenerarDevengosSesion::calcularMonto()`).

### 3. HTTP — `app/Dominios/Finanzas/Infraestructura/Http/Controllers/Web/DevengosController.php`

Dos acciones:

- `index(Request $request)`: resuelve `personaId =
  $autorizacion->personaId($request)`; si es `null` (cuenta sin persona
  operativa asociada, p. ej. un rol solo administrativo), `abort(404)` —
  no hay "lo suyo" que mostrar. Si no, redirigí a
  `route('panel.devengos.show', $personaId)`.
- `show(Request $request, int $persona)`: **acá va el 404 de acceso
  cruzado** — si `$persona !== $autorizacion->personaId($request)`,
  `abort(404)`, sin importar qué permiso tenga el actor (el criterio de
  aceptación es "un operario ve solo lo suyo", no "lo suyo y lo de quien
  tenga más permiso"). Si coincide, lista vía `ListarDevengosPersona` con el
  filtro de `periodo` de la query string.

Un solo permiso, `finanzas.devengo.ver`, gatea ambas acciones (sin él,
403 antes de llegar al 404 de identidad).

### 4. Vista — `Infraestructura/Http/Views/pages/devengos/show.blade.php`

Tabla de devengos del período (fecha, hectáreas, tarifa, monto) + total, y
un selector de período (mes/año, puede ser tan simple como dos `<select>`
o un `<input type="month">` nativo). Sin arquetipo Listado completo (no hay
alta/edición/baja acá) — es una tabla de solo lectura, más cercana al
arquetipo que ya usa `tests/Visual/organizacion.spec.ts` para paneles de
consulta que al Listado con acciones.

### 5. Rutas — `routes/web.php`

`panel.devengos.index` (redirige) y `panel.devengos.show/{persona}`. Mismo
grupo `auth:interno` → `rol.activo`.

### 6. Permisos — `SeguridadSeeder.php`

Agregá `'finanzas.devengo.ver' => 'Ver los propios devengos por período'` a
`PERMISOS`. Sumalo a `PERMISOS_PILOTO`. `auxiliar` hoy se asigna sin ninguna
constante de permisos (comentario explícito "sin permisos de seguridad ni
de panel" en `run()`) — creá `PERMISOS_AUXILIAR = ['finanzas.devengo.ver']`
y asignala en `run()`, reemplazando ese comentario. `encargado_operaciones`
no necesita este permiso (la HU no le pide ver devengos ajenos — eso, si
hace falta, es una HU futura de reporte, no esta).

### 7. Menú — `SecMenuSeeder.php`

El ítem "devengos" ya está sembrado (grupo financiero) sin `ruta` ni
`codigoPermiso` — activalo con `ruta: 'panel.devengos.index',
codigoPermiso: 'finanzas.devengo.ver'`.

### 8. Copy — `lang/es/finanzas.php`

No existe — creálo.

## Qué NO hacer

- No le des al encargado ni a `jefe_campo` acceso a devengos ajenos en esta
  tarea — el criterio de aceptación es estrictamente "lo suyo".
- No toques `GenerarDevengosSesion` ni la migración de
  `fin_devengos_personal` — el modelo de datos y la generación ya están
  completos.
- No sumes con floats ni con `array_sum()` sobre strings decimales sin
  pasar por `BigDecimal` — invariante 6, literal.
- No confundas esta pantalla con la app de campo — es panel Blade, no
  Flutter.

## Cómo repartir las etapas

- **Etapa 1**: `boot()`, `ListarDevengosPersona`, controller, rutas,
  permisos, menú.
- **Etapa 2**: vista + `lang/es/finanzas.php`.
- **Etapa 3**: tests Feature + spec visual + checklist §8.

## Criterio de aceptación

- `./bin/verify` = 0, con Playwright.
- Test Feature (`tests/Feature/Finanzas/DevengosPersonalPanelTest.php`)
  cubriendo:
  - Un piloto ve sus propios devengos del período, con el total exacto
    (usá el mismo caso de `hectareas`/`tarifa_ha` con decimales de la tarea
    16 para confirmar que el total no arrastra error de redondeo).
  - **Acceso cruzado**: piloto A pide `panel.devengos.show` con la
    `persona_id` de piloto B → 404.
  - Filtro por período: un devengo de otro mes no aparece en el listado del
    mes actual.
  - 403 sin el permiso `finanzas.devengo.ver`.
  - Auxiliar también puede acceder (el permiso quedó en `PERMISOS_AUXILIAR`,
    no solo en piloto).
  - El ítem de menú "Devengos" queda gateado por el permiso.
- Spec visual (`tests/Visual/devengos.spec.ts`), claro y oscuro.
- Checklist de `docs/diseno/guia_pantalla_panel.md` §8 pasa.

## Puede tocar

`app/Dominios/Finanzas/**`, `routes/web.php`,
`database/seeders/Catalogo/SeguridadSeeder.php`,
`database/seeders/Catalogo/SecMenuSeeder.php`, `lang/es/finanzas.php`
(nuevo), `tests/**`.

Fuera de alcance: cualquier cambio a `fin_devengos_personal` o a
`GenerarDevengosSesion`, la tabla `fin_anticipos` (tarea siguiente).
