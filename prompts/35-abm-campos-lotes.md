<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/campos-lotes-panel etapas=4 -->

# Tarea 35 — HU-24: administración de campos y sus lotes

## Por qué esta tarea y por qué después de la 33/34

`plan_sprints.md` Sprint 7 (§175): "Como encargado, quiero administrar campos
y sus lotes con superficie y geometría, para que el piloto vea el lote
correcto." `com_campos` y `com_lotes` existen, migradas y auditadas, desde
TE-03 — no hay modelo de datos nuevo, es la tercera pantalla ABM del panel.

Esta tarea asume que la 33 (HU-22, clientes) y la 34 (HU-23, contratos) ya
están integradas en `develop` y reutiliza el patrón que establecieron:
`Comercial/Aplicacion/`, `Comercial/Infraestructura/Http/`,
`ComercialServiceProvider::boot()` con el namespace de vista `comercial::`,
`AutorizacionPanelWeb` para permiso + cáscara, Controller + Form Request +
Blade sin Livewire, arquetipo formulario de la tarea 31. No repitas esas
decisiones explicándolas de nuevo — leé `prompts/33-abm-clientes.md` si
necesitás el detalle exacto. **Si por algún motivo 33 o 34 todavía no están
en `develop` cuando arranques**, vas a tener que crear `Aplicacion/` e
`Infraestructura/Http/` de `Comercial` desde cero: hoy (2/9/2026) el módulo
solo tiene `Contratos/`, `Dominio/` e `Infraestructura/Eloquent/`. Mirá antes
de asumir que el andamiaje ya existe.

**Los modelos Eloquent ya existen y no hay que tocarlos**:
`Campo` (`cliente_id`, `nombre`, `ubicacion`, relación `lotes(): HasMany`) y
`Lote` (`campo_id`, `codigo`, `hectareas` con cast `decimal:2`, `geometria`
con cast `array`, `restricciones`), ambos en
`app/Dominios/Comercial/Infraestructura/Eloquent/`. `Cliente::campos()` ya
apunta a `Campo`.

## Qué hacer

Cargá las skills `dominio-backend`, `panel-design-ui` y `verificacion`. Leé
`docs/diseno/guia_pantalla_panel.md` (arquetipo Listado §6.2, Formulario
§6.3).

### 1. Capa de aplicación — `app/Dominios/Comercial/Aplicacion/`

Casos de uso: `ListarCampos`, `CrearCampo`, `ActualizarCampo`, `EliminarCampo`
(nombres exactos a tu criterio si ya hay una convención distinta establecida
por la 33/34 — seguí esa). Un campo se crea/edita **con sus lotes en la misma
operación** (mismo patrón que clientes+contactos de la tarea 33: una sección
de lotes repetible dentro del formulario del campo, no una pantalla ABM
separada — el menú ya está sembrado así, ver punto 5). `CrearCampo`/
`ActualizarCampo` hacen el upsert de campo + lotes en una transacción
(`DB::transaction`).

**Eliminar un campo o un lote** es soft delete (invariante 8). `com_lotes` y
`com_campos` tienen columnas `created_by`/`updated_by` con FK real a
`sec_user` (tarea 30) y `restrictOnDelete()` desde `ope_ordenes_aplicacion` y
`ope_trabajos` hacia `com_lotes.id` — ese constraint solo dispara con un
`DELETE` físico, que esta tarea no hace. Aun así, decidí (y documentá la
decisión en el docblock del caso de uso) si eliminar un lote con órdenes o
trabajos asociados debería rechazarse a nivel de negocio con un mensaje claro
en vez de dejarlo soft-deleted con historial colgando — es razonable que sí,
pero es una decisión de esta tarea, no una regla ya escrita en la
especificación.

### 2. HTTP — `app/Dominios/Comercial/Infraestructura/Http/`

- `Controllers/Web/CamposController.php`: `index`, `create`, `store`, `edit`,
  `update`, `destroy`. Permiso vía `AutorizacionPanelWeb`, sin cálculo de
  negocio en el controller.
- `Requests/CrearCampoRequest.php` / `ActualizarCampoRequest.php`: validan
  `cliente_id` (existe, activo), `nombre` (requerido, máx 150), `ubicacion`
  (nullable, máx 255), y el array de lotes (`codigo` requerido máx 50,
  `hectareas` numérico > 0, `geometria` opcional, `restricciones` opcional).
  El nombre único por cliente y el código único por campo ya son índices
  parciales en BD (`com_campos_nombre_unico`, `com_lotes_codigo_unico`) —
  atrapá la violación y devolvé un error de validación legible, no un
  `QueryException` crudo (mismo patrón que el NIT de la tarea 33).
- **Geometría**: `com_lotes.geometria` es `jsonb` nullable, GeoJSON de tipo
  `Polygon` (decisión ya tomada en el docblock de la migración —
  "se guarda y se dibuja, no se consulta espacialmente, sin PostGIS en v1,
  ADR 0001"). **No hay librería de mapas en `package.json` y no la agregues**:
  el campo es opcional y se captura como texto (un `<textarea>` con el JSON
  crudo), validado en el Form Request como JSON bien formado con `type` ===
  `"Polygon"` y `coordinates` como array — no valides la geometría completa
  contra el spec de GeoJSON, alcanza con esa forma mínima. Un mapa
  interactivo de dibujo es una HU de UX propia, no el alcance de esta tarea.
- Vistas en `Infraestructura/Http/Views/pages/campos/`: `index.blade.php`
  (arquetipo Listado, columna de hectáreas totales del campo — suma de sus
  lotes — si el dato ya está cargado, no una query N+1 nueva por fila),
  `create.blade.php`/`edit.blade.php` (arquetipo Formulario: sección "Datos
  del campo" con el `<select>` nativo de cliente — no hay átomo `select` en
  el catálogo, no inventes uno, mismo criterio que la tarea 34 — y sección
  "Lotes" con filas dinámicas, reutilizando el mecanismo JS que armó la tarea
  33 para contactos si quedó como pieza reusable).

### 3. Rutas — `routes/web.php`

Mismo grupo `auth:interno` → `rol.activo`. Nombres `panel.campos.index`,
`.create`, `.store`, `.edit`, `.update`, `.destroy`.

### 4. Permisos — `database/seeders/Catalogo/SeguridadSeeder.php`

```
comercial.campo.ver
comercial.campo.crear
comercial.campo.editar
comercial.campo.eliminar
```

**No crees códigos `comercial.lote.*` separados** — los lotes se gestionan
dentro del formulario del campo, mismo criterio que los contactos de la tarea
33 (que no tuvieron permiso propio). Sumalos a
`PERMISOS_ENCARGADO_OPERACIONES`.

### 5. Menú — `database/seeders/Catalogo/SecMenuSeeder.php`

El ítem `comercial.campos` ya está sembrado como botón sin link (línea 74:
`$this->item($comercial, 'comercial', 'campos', 'map', 3)`). Activalo con
`ruta: 'panel.campos.index', codigoPermiso: 'comercial.campo.ver'`. No hay
ítem de menú separado para "lotes" — no lo crees.

### 6. Copy — `lang/es/comercial.php`

Ya existe desde la tarea 33: sumale las claves de esta pantalla.

## Qué NO hacer

- No agregues una librería de mapas ni un widget de dibujo de geometría — el
  campo es un `<textarea>` de GeoJSON validado como forma mínima, nada más.
- No crees permisos `comercial.lote.*` separados de `comercial.campo.*`.
- No toques `com_clientes`/`com_contratos` ni sus pantallas.
- No le agregues a `Campo`/`Lote` ninguna relación o columna nueva — el
  modelo de datos ya está completo desde TE-03.
- No implementes `forceDelete` en ningún caso de uso — soft delete siempre
  (invariante 8).

## Cómo repartir las etapas

- **Etapa 1**: `Aplicacion/` (los cuatro casos de uso), `Infraestructura/Http`
  (controller, requests, rutas), permisos, menú.
- **Etapa 2**: las tres vistas sobre el arquetipo, lotes dinámicos,
  `lang/es/comercial.php`.
- **Etapa 3**: tests Feature (ver criterio de aceptación).
- **Etapa 4**: margen — spec visual, checklist §8 de la guía, verificación en
  navegador en los dos temas.

## Criterio de aceptación

- `./bin/verify` = 0, con la etapa de Playwright.
- Test Feature (`tests/Feature/Comercial/GestionCamposPanelTest.php` o
  similar), siguiendo el patrón de asserts de la tarea 33, que cubra:
  - Alta de un campo con al menos un lote → 302/200 + registro en BD.
  - `hectareas <= 0` en un lote → error de validación, no persiste.
  - Geometría mal formada (no JSON, o sin `type`/`coordinates`) → error de
    validación; geometría ausente (`null`) → válida (es opcional).
  - Bitácora de alta, edición y baja (`Bitacora::query()->where('tabla',
    'com_campos')...`, mismo patrón que la tarea 33).
  - Baja: soft delete, no aparece en `index`, 404 si se reintenta.
  - Eliminar un lote con una orden o trabajo asociado: el comportamiento que
    decidiste en el punto 1, con su test.
  - Nombre de campo duplicado para el mismo cliente, o código de lote
    duplicado para el mismo campo → error de validación, no `QueryException`.
  - 403 para un rol sin el permiso; permiso en un rol no-activo no alcanza.
  - El ítem de menú "Campos" queda gateado por `comercial.campo.ver`.
- Spec visual nuevo (`tests/Visual/campos.spec.ts`, mismo patrón que
  `organizacion.spec.ts` y el de la tarea 33), claro y oscuro.
- Checklist de `docs/diseno/guia_pantalla_panel.md` §8 pasa.

## Puede tocar

`app/Dominios/Comercial/**`, `routes/web.php`,
`database/seeders/Catalogo/SeguridadSeeder.php`,
`database/seeders/Catalogo/SecMenuSeeder.php`, `lang/es/comercial.php`,
`resources/css/pages/campos.css` (si hace falta), `tests/**`.

Fuera de alcance: cualquier otro módulo de Sprint 7, cambios al esquema de
`com_campos`/`com_lotes`, Livewire, mapas interactivos.
