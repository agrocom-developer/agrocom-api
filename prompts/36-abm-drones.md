<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/drones-panel etapas=3 -->

# Tarea 36 — HU-27: administración de la flota de drones

## Por qué esta tarea

`plan_sprints.md` Sprint 7 (§178): "Como encargado, quiero administrar la
flota de drones con su modelo y volumen de carga, para planificar recargas."
Es la más simple de las cuatro HU que quedan de Sprint 7 (1,0 d, sin máquina
de estados, sin guardas complejas) — sigue a la 35 (campos y lotes) en la
progresión de complejidad del sprint, antes de personas/bases (37) y órdenes
(38, con máquina de estados).

`ope_drones` existe desde la tarea 23 (recargas), **deliberadamente mínima**:
solo `identificador` + auditoría + soft delete (docblock de
`database/migrations/2026_09_01_100013_create_ope_drones_table.php:18-22`,
dice explícito que es mínima "a propósito" hasta que exista
`Mantenimiento`/`Inventario`). El criterio de esta HU pide "modelo y volumen
de carga" y el plan menciona "volumen real por modelo (30/50/60 L)" — esa
columna no existe todavía. Esta tarea es la que la agrega.

**Los modelos Eloquent que sí tocan lo mínimo ya existen y no hay que
recrearlos**: `Dron` en
`app/Dominios/Operaciones/Infraestructura/Eloquent/Dron.php` (hoy
`$fillable = ['identificador']`). El namespace de vista `operaciones::` ya
está registrado en `OperacionesServiceProvider::boot()` — no lo toques, solo
sumá vistas bajo `Infraestructura/Http/Views/pages/drones/`.

## Qué hacer

Cargá las skills `dominio-backend`, `modelo-datos`, `panel-design-ui` y
`verificacion`. Leé `docs/diseno/guia_pantalla_panel.md` (arquetivo Listado
§6.2, Formulario §6.3).

### 1. Migración — ALTER sobre `ope_drones`

Sumá dos columnas, mismo patrón que `tarifa_ha` sobre `per_personas` (tarea
16): `modelo` string(40) nullable (texto libre, ej. "DJI Agras T30" — no
inventes un catálogo cerrado de modelos, la espec no lo pide) y `capacidad_l`
decimal(5,2) nullable, con CHECK `capacidad_l IS NULL OR capacidad_l IN (30,
50, 60)` (solo pgsql, mismo guard `DB::getDriverName() === 'pgsql'` que ya
usan las migraciones del módulo). Nullable porque los drones ya sembrados no
tienen este dato — sin default de negocio, mismo criterio que `tarifa_ha`.
Sumá `modelo`/`capacidad_l` al `$fillable` y a `casts()` de `Dron.php`
(`capacidad_l` como `decimal:2`).

### 2. Capa de aplicación — `app/Dominios/Operaciones/Aplicacion/`

`ListarDrones`, `CrearDron`, `ActualizarDron`, `EliminarDron`. Eliminar es
soft delete simple (invariante 8) — a diferencia de los lotes de la tarea 35
(donde vaciar del formulario un lote con historial se rechazaba), acá no hay
"quitar de una lista": es una acción directa sobre una fila del listado.
`ope_sesiones.dron_id` y `ope_alertas.dron_id` son `restrictOnDelete()`, pero
eso solo dispara con un `DELETE` físico que esta tarea no hace — soft delete
no lo toca. Sin guarda adicional necesaria.

### 3. HTTP — `app/Dominios/Operaciones/Infraestructura/Http/`

- `Controllers/Web/DronesController.php`: `index`, `create`, `store`, `edit`,
  `update`, `destroy`. Permiso vía `AutorizacionPanelWeb`.
- `Requests/CrearDronRequest.php` / `ActualizarDronRequest.php`:
  `identificador` requerido, máx 40 (el índice único parcial ya existe en
  BD — atrapá la violación como error de validación legible, no
  `QueryException` crudo, mismo patrón que el NIT de clientes/nombre de
  campos); `modelo` opcional, máx 40; `capacidad_l` opcional, `Rule::in([30,
  50, 60])` cuando venga.
- Vistas en `Infraestructura/Http/Views/pages/drones/`: `index.blade.php`
  (arquetipo Listado), `create.blade.php`/`edit.blade.php` (arquetipo
  Formulario, sin selects — todos los campos son texto/número).

### 4. Rutas — `routes/web.php`

Mismo grupo `auth:interno` → `rol.activo`. Nombres `panel.drones.index`,
`.create`, `.store`, `.edit`, `.update`, `.destroy`.

### 5. Permisos — `database/seeders/Catalogo/SeguridadSeeder.php`

```
operaciones.dron.ver
operaciones.dron.crear
operaciones.dron.editar
operaciones.dron.eliminar
```

Sumalos a `PERMISOS_ENCARGADO_OPERACIONES` (la HU dice "como encargado",
mismo criterio que clientes/contratos/campos).

### 6. Menú — `database/seeders/Catalogo/SecMenuSeeder.php`

El ítem `recursos.drones` ya está sembrado como botón sin link (línea 87:
`$this->item($recursos, 'recursos', 'drones', 'airplanemode_active', 1)`).
Activalo con `ruta: 'panel.drones.index', codigoPermiso:
'operaciones.dron.ver'`.

### 7. Copy — `lang/es/operaciones.php`

Ya existe (usado por trabajos/sesiones/alertas) — sumale las claves de esta
pantalla.

## Qué NO hacer

- No armes un catálogo cerrado de modelos de dron ni specs técnicas
  extendidas (autonomía, sensores, etc.) — solo `modelo` (texto libre) y
  `capacidad_l` con el CHECK de 30/50/60.
- No toques `ope_sesiones`, `ope_alertas` ni el módulo `Finanzas`.
- No crees `Mantenimiento`/`Inventario` — la migración de `ope_drones` ya
  documenta que eso es de otra HU futura.
- No implementes `forceDelete` — soft delete siempre.

## Cómo repartir las etapas

- **Etapa 1**: migración ALTER, `Aplicacion/` (cuatro casos de uso),
  `Infraestructura/Http` (controller, requests, rutas), permisos, menú.
- **Etapa 2**: las tres vistas, `lang/es/operaciones.php`.
- **Etapa 3**: tests Feature + spec visual + checklist §8.

## Criterio de aceptación

- `./bin/verify` = 0, con la etapa de Playwright.
- Test Feature (`tests/Feature/Operaciones/GestionDronesPanelTest.php` o
  similar) que cubra:
  - Alta de un dron con `identificador`, `modelo` y `capacidad_l` válidos →
    persiste.
  - `capacidad_l` fuera de {30, 50, 60} → error de validación, no persiste.
  - `identificador` duplicado (entre drones vivos) → error de validación, no
    `QueryException`.
  - Bitácora de alta, edición y baja.
  - Baja: soft delete, no aparece en `index`, 404 si se reintenta.
  - 403 para un rol sin el permiso; permiso en un rol no-activo no alcanza.
  - El ítem de menú "Drones" queda gateado por `operaciones.dron.ver`.
- Spec visual nuevo (`tests/Visual/drones.spec.ts`, mismo patrón que
  `campos.spec.ts`), claro y oscuro.
- Checklist de `docs/diseno/guia_pantalla_panel.md` §8 pasa.

## Puede tocar

`app/Dominios/Operaciones/**` (nueva migración ALTER, `Aplicacion/` y
`Infraestructura/Http/` de drones — no toques lo existente de
trabajos/sesiones/alertas/actas/reportes), `routes/web.php`,
`database/seeders/Catalogo/SeguridadSeeder.php`,
`database/seeders/Catalogo/SecMenuSeeder.php`, `lang/es/operaciones.php`,
`resources/css/pages/drones.css` (si hace falta), `tests/**`.

Fuera de alcance: cualquier otra HU de Sprint 7, cambios a `ope_sesiones` o
`ope_alertas`.
