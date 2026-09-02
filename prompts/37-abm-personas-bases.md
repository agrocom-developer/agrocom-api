<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/personas-bases-panel etapas=4 -->

# Tarea 37 — HU-26: administración de personas y bases

## Por qué esta tarea

`plan_sprints.md` Sprint 7 (§177): "Como encargado, quiero administrar
personas y bases, con su rol operativo y tarifa, para que los devengos salgan
con el dato correcto." `per_personas` y `per_bases` existen, migradas y
auditadas, desde TE-03 (y `tarifa_ha` desde la tarea 16) — no hay modelo de
datos nuevo, es pantalla.

A diferencia de clientes/contratos/campos (33-35, mismo módulo `Comercial`
con su andamiaje ya armado), el módulo `Personal` hoy **solo tiene lecturas**:
`Contratos/LecturaPersonas.php`, `Contratos/LecturaTarifaPersona.php`,
`Contratos/PersonaCatalogo.php`, los modelos Eloquent `PerPersona`/`PerBase`,
y sus implementaciones de lectura. **No existe `Aplicacion/` con casos de uso
de escritura ni `Infraestructura/Http/`** — armás el andamiaje del módulo
desde cero, mismo patrón que estableció la tarea 33 en `Comercial`
(`PersonalServiceProvider::boot()` no existe todavía: sumale el
`View::addNamespace('personal', app_path('Dominios/Personal/Infraestructura/
Http/Views'))`, mismo que `OperacionesServiceProvider::boot()`).

**Personas y bases son dos ABMs independientes**, no uno anidado como
lote-en-campo (tarea 35): una base es un catálogo simple (nombre, ubicación)
y una persona la referencia por `base_id` (FK nullable), pero cada una tiene
su propia pantalla de listado/alta/edición. No las anides.

**El devengo ya congela su propia copia de `tarifa_ha` al generarse** —
confirmado en `app/Dominios/Finanzas/Aplicacion/GenerarDevengosSesion.php:
67-89`: `DevengoPersonal::create([...'tarifa_ha' => $tarifaHa, 'monto' =>
...])` copia el valor en ese momento, no lo relee de `per_personas` después.
Esto significa que el caso de uso `ActualizarPersona` **no necesita ninguna
guarda especial** contra devengos históricos — el criterio de aceptación del
plan ("test de que cambiar la tarifa no altera devengos ya generados") es un
test que **confirma un comportamiento que el sistema ya tiene por diseño**,
no una guarda nueva que escribir.

## Qué hacer

Cargá las skills `dominio-backend`, `panel-design-ui` y `verificacion`. Leé
`docs/diseno/guia_pantalla_panel.md` (arquetipo Listado §6.2, Formulario
§6.3).

### 1. Service provider — `app/Dominios/Personal/Infraestructura/PersonalServiceProvider.php`

Agregale el método `boot()` con `View::addNamespace('personal', ...)`, mismo
patrón que `OperacionesServiceProvider`.

### 2. Capa de aplicación — `app/Dominios/Personal/Aplicacion/`

Dos familias de casos de uso: `ListarBases`/`CrearBase`/`ActualizarBase`/
`EliminarBase` y `ListarPersonas`/`CrearPersona`/`ActualizarPersona`/
`EliminarPersona`. Ambas con soft delete (invariante 8), sin guarda especial
(ninguna FK `restrictOnDelete` apunta a `per_bases`/`per_personas` desde otro
módulo hoy salvo lo que ya cubre el soft delete).

### 3. HTTP — `app/Dominios/Personal/Infraestructura/Http/`

- `Controllers/Web/BasesController.php` y `Controllers/Web/PersonasController.php`:
  `index`, `create`, `store`, `edit`, `update`, `destroy` cada uno. Permiso
  vía `AutorizacionPanelWeb`.
- `Requests/CrearBaseRequest.php`/`ActualizarBaseRequest.php`: `nombre`
  requerido máx 100, `ubicacion` nullable máx 200.
- `Requests/CrearPersonaRequest.php`/`ActualizarPersonaRequest.php`:
  `nombre` requerido máx 150, `rol` requerido, `Rule::enum(RolOperativoPersona
  ::class)`, `base_id` nullable + `exists:per_bases,id` (entre vivas),
  `tarifa_ha` nullable numérico `>= 0`, `activo` boolean.
- Vistas en `Infraestructura/Http/Views/pages/bases/` y
  `Infraestructura/Http/Views/pages/personas/`: `index.blade.php` (arquetipo
  Listado) y `create.blade.php`/`edit.blade.php` (arquetipo Formulario) para
  cada una. El formulario de persona usa un `<select>` nativo para `rol` (los
  5 valores de `RolOperativoPersona`) y otro para `base_id` (opcional, "Sin
  base asignada" como opción vacía) — no hay átomo `select` en el catálogo,
  no inventes uno (mismo criterio que contratos/campos).

### 4. Rutas — `routes/web.php`

Mismo grupo `auth:interno` → `rol.activo`. Nombres `panel.bases.index`,
`.create`, `.store`, `.edit`, `.update`, `.destroy` y los mismos seis para
`panel.personas.*`.

### 5. Permisos — `database/seeders/Catalogo/SeguridadSeeder.php`

```
personal.base.ver
personal.base.crear
personal.base.editar
personal.base.eliminar
personal.persona.ver
personal.persona.crear
personal.persona.editar
personal.persona.eliminar
```

Sumalos a `PERMISOS_ENCARGADO_OPERACIONES`.

### 6. Menú — `database/seeders/Catalogo/SecMenuSeeder.php`

Los ítems ya están sembrados como botones sin link: línea 90
(`$this->item($recursos, 'recursos', 'bases', 'home_work', 4)`) y línea 91
(`$this->item($recursos, 'recursos', 'personas', 'badge', 5)`). Activalos con
`ruta: 'panel.bases.index', codigoPermiso: 'personal.base.ver'` y `ruta:
'panel.personas.index', codigoPermiso: 'personal.persona.ver'`.

### 7. Copy — `lang/es/personal.php`

No existe — creálo, mismo formato que `lang/es/comercial.php`.

## Qué NO hacer

- No anides el formulario de "Personas" dentro del de "Bases" ni viceversa —
  son dos ABMs independientes.
- No le agregues a `PerPersona`/`PerBase` ninguna columna nueva — el modelo
  de datos ya está completo (incluida `tarifa_ha` desde la tarea 16).
- No toques `app/Dominios/Finanzas/Aplicacion/GenerarDevengosSesion.php` ni
  ningún archivo de `Finanzas` — el test de esta tarea **verifica** que el
  devengo ya congela su tarifa, no cambia cómo se genera.
- No agregues ninguna guarda que impida editar `tarifa_ha` de una persona con
  devengos existentes — está confirmado que no hace falta (ver "Por qué").
- No implementes `forceDelete` en ningún caso de uso.

## Cómo repartir las etapas

- **Etapa 1**: `PersonalServiceProvider::boot()`, `Aplicacion/` y
  `Infraestructura/Http` de **Bases** (controller, requests, rutas), permiso
  y menú de bases.
- **Etapa 2**: `Aplicacion/` y `Infraestructura/Http` de **Personas**,
  permiso y menú de personas.
- **Etapa 3**: las cuatro vistas sobre el arquetipo, `lang/es/personal.php`.
- **Etapa 4**: tests Feature (incluido el de congelamiento de devengo) +
  spec visual + checklist §8.

## Criterio de aceptación

- `./bin/verify` = 0, con la etapa de Playwright.
- Test Feature (`tests/Feature/Personal/GestionBasesPanelTest.php` y
  `GestionPersonasPanelTest.php`, o un único archivo si lo preferís) que
  cubra:
  - Alta/edición/baja de una base y de una persona → persiste, bitácora
    presente en las tres operaciones.
  - Baja: soft delete, no aparece en `index`, 404 si se reintenta.
  - `rol` fuera del enum → error de validación.
  - `base_id` inexistente o de una base borrada → error de validación.
  - **Congelamiento de devengo**: generá una sesión validada con
    `GenerarDevengosSesion` (o el flujo completo, reutilizando el patrón de
    test de la tarea 16) para una persona con `tarifa_ha = X`; después,
    editá esa persona a `tarifa_ha = Y` vía `ActualizarPersona`; releé el
    `DevengoPersonal` ya generado y confirmá que su `tarifa_ha`/`monto`
    siguen calculados sobre `X`, no sobre `Y`.
  - 403 para un rol sin el permiso correspondiente (bases y personas por
    separado); permiso en un rol no-activo no alcanza.
  - Los ítems de menú "Bases" y "Personas" quedan gateados por sus permisos.
- Spec visual nuevo (`tests/Visual/bases.spec.ts` y
  `tests/Visual/personas.spec.ts`), claro y oscuro.
- Checklist de `docs/diseno/guia_pantalla_panel.md` §8 pasa.

## Puede tocar

`app/Dominios/Personal/**`, `routes/web.php`,
`database/seeders/Catalogo/SeguridadSeeder.php`,
`database/seeders/Catalogo/SecMenuSeeder.php`, `lang/es/personal.php`
(nuevo), `resources/css/pages/bases.css`/`personas.css` (si hace falta),
`tests/**`.

Fuera de alcance: `app/Dominios/Finanzas/**`, cualquier otra HU de Sprint 7,
cambios al esquema de `per_personas`/`per_bases`.
