<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/clientes-panel etapas=4 -->

# Tarea 33 — HU-22: alta y mantenimiento de clientes

## Por qué esta tarea y por qué ahora

`plan_sprints.md` Sprint 7 (§165-181): "Como encargado, quiero dar de alta y
mantener clientes con sus contactos, para no depender de que alguien toque la
base." `com_clientes` y `com_cliente_contactos` existen, migradas y auditadas,
desde TE-03 — no hay modelo de datos nuevo, es la capa de pantalla que falta.

**Es el primer ABM real del panel.** Repasá qué existe hoy antes de empezar,
para no asumir un precedente que no está:

- `/panel/organizacion` (tarea 31) es un **mockup sin persistencia** — sirve
  de caso de prueba visual del arquetipo formulario, no de plantilla de
  guardado. No la copies para la lógica de guardado, solo para el markup.
- `VersionesApkController`/`versiones-apk` (tarea 10-11) tiene `store()` real
  con Form Request, pero es anterior al arquetipo de la tarea 31: no usa
  `page-header`/`tabs`/`form-actions-bar`. Sirve de referencia para el patrón
  de controller + Form Request + `old()`/`$errors`, no para el layout.
- `DispositivosController` (tarea 03) tiene listar + revocar (soft delete)
  con su test de referencia, pero no crear ni editar.
- Ningún módulo usa Livewire todavía, aunque `livewire/livewire` está en
  `composer.json`. Todo el panel es Controller + Blade + Form Request.
  **No introduzcas Livewire acá** — es una decisión de arquitectura que esta
  tarea no tiene mandato para tomar.

Componés el patrón entero por primera vez: es el molde que van a copiar
HU-23 a HU-27 y HU-45. Lo que decidas acá (nombres de método, estructura de
carpetas, cómo se arma un permiso nuevo) se vuelve la convención.

## Qué hacer

Cargá las skills `dominio-backend`, `panel-design-ui` y `verificacion` antes
de escribir código. Leé `docs/diseno/guia_pantalla_panel.md` completa — es la
receta de "dónde va cada archivo" (§1), el arquetipo Listado (§6.2) para
`index` y el arquetipo Formulario (§6.3) para `create`/`edit`.

### 1. Capa de aplicación — `app/Dominios/Comercial/Aplicacion/`

No existe todavía (`Comercial` hoy solo tiene `Dominio/`, `Contratos/` —
puertos de lectura hacia otros módulos, no confundir con "contratos" de
negocio — e `Infraestructura/Eloquent/`). Casos de uso, uno por operación:
`ListarClientes`, `CrearCliente`, `ActualizarCliente`, `EliminarCliente`.
`Cliente` y `ClienteContacto` (`Infraestructura/Eloquent/`) ya existen con sus
relaciones (`Cliente::contactos()`, `ClienteContacto::cliente()`).

Un cliente se crea/edita **con sus contactos en la misma operación** (el
formulario es uno solo, con una sección de contactos repetible — tipo
`dueno`/`agronomo`/`encargado_propiedad`/`otro`, ver el `CHECK` de
`com_cliente_contactos`). `CrearCliente`/`ActualizarCliente` reciben el
cliente y la lista de contactos, y hacen el upsert de ambos en una
transacción (`DB::transaction`) — nunca dos requests separados para una sola
acción de usuario.

### 2. HTTP — `app/Dominios/Comercial/Infraestructura/Http/`

- `Controllers/Web/ClientesController.php`: `index`, `create`, `store`,
  `edit`, `update`, `destroy`. Cada acción verifica el permiso contra el rol
  activo vía `App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb`
  (`tienePermiso($request, $codigo)` + `cascara($request)` para la cáscara del
  panel) — es el patrón más nuevo del repo (`TrabajosController`,
  `VersionesApkController`), **no** el acceso directo a `SecUser`/`session()`
  que usa `DispositivosController` (más viejo, no lo repitas). Ningún cálculo
  de negocio en el controller — invoca los casos de uso de `Aplicacion/`.
- `Requests/CrearClienteRequest.php` y `ActualizarClienteRequest.php`: validan
  `razon_social` (requerido, máx 200), `nit` (nullable, máx 20), y el array de
  contactos (cada uno con `tipo` dentro del enum, `nombre` requerido,
  `telefono`/`email` opcionales). El NIT único entre clientes activos ya es un
  índice parcial en BD (`com_clientes_nit_unico`) — atrapá la violación y
  devolvé un error de validación legible (`nit` ya en uso), no dejes que el
  usuario vea un `QueryException` crudo.
- Namespace de vista: `ComercialServiceProvider` (existe, solo con
  `register()`) necesita un `boot()` nuevo con
  `View::addNamespace('comercial', app_path('Dominios/Comercial/Infraestructura/Http/Views'))`
  — mismo patrón que `OperacionesServiceProvider::boot()`.
- Vistas en `Infraestructura/Http/Views/pages/clientes/`: `index.blade.php`
  (arquetipo Listado §6.2: cabecera → filtros → tabla → paginación, badges
  `atoms/badge` si hace falta distinguir algo, cifras en
  `--ag-font-family-mono`), `create.blade.php`/`edit.blade.php` (arquetipo
  Formulario §6.3: `page-header`, `form-section` para "Datos del cliente" y
  otra para "Contactos", `form-actions-bar` al pie). El aside pegajoso
  (`summary-card`/`progress-meter`) es opcional acá — un cliente nuevo no
  tiene métricas de solo-lectura que mostrar; si en `edit` te parece que suma
  (cantidad de contratos/campos vigentes), usalo, si no, omitilo.
- Los contactos son N por cliente: necesitás un mecanismo de agregar/quitar
  fila en el formulario sin recargar la página. JS vanilla (clonar un
  `<template>`, reindexar `contactos[N][campo]`), sin frameworks nuevos —
  seguí la convención de `resources/js/{atoms,molecules,organisms}/` si el
  patrón amerita un archivo propio, o JS de página si es puntual de esta
  pantalla (`docs/diseno/guia_pantalla_panel.md` §1).

### 3. Rutas — `routes/web.php`

Dentro del mismo grupo `auth:interno` → `rol.activo` que ya usan
`trabajos`/`sesiones`/`alertas`. Nombres `panel.clientes.index`, `.create`,
`.store`, `.edit`, `.update`, `.destroy` (RESTful estándar — es el primer ABM
completo, no hay una convención de acción puntual como `.revocar`/`.autorizar`
que seguir acá).

### 4. Permisos — `database/seeders/Catalogo/SeguridadSeeder.php`

Cuatro códigos nuevos en `PERMISOS`, mismo patrón `<modulo>.<entidad>.<accion>`
que ya usa `seguridad.usuario.*`:

```
comercial.cliente.ver
comercial.cliente.crear
comercial.cliente.editar
comercial.cliente.eliminar
```

Sumalos a `PERMISOS_ENCARGADO_OPERACIONES` (el único rol "encargado" del
catálogo — la HU dice literalmente "como encargado"). `dueno` los tiene todos
automático (asigna el catálogo completo).

### 5. Menú — `database/seeders/Catalogo/SecMenuSeeder.php`

El ítem `comercial.clientes` ya está sembrado como "botón sin link"
(línea 72: `$this->item($comercial, 'comercial', 'clientes', 'contact_page', 1)`).
Activalo agregando `ruta: 'panel.clientes.index', codigoPermiso:
'comercial.cliente.ver'` — el seeder ya sabe completar `ruta`/`permission_id`
sin pisar nada (`item()`, comentario de la línea 158).

### 6. Copy — `lang/es/comercial.php`

No existe todavía. Creálo con las claves de esta pantalla (título, labels de
campo, botones, mensajes de confirmación de baja). Cero texto literal en
Blade (ADR 0013).

## Qué NO hacer

- No toques `com_campos`/`com_lotes` ni sus pantallas — son HU-24, tarea
  aparte.
- No le agregues a `Cliente`/`ClienteContacto` ninguna relación ni columna
  nueva — el modelo de datos ya está completo desde TE-03.
- No inventes un permiso genérico `comercial.ver` — seguí el grano fino ya
  establecido (`ver`/`crear`/`editar`/`eliminar` separados), es lo que
  permite que un rol futuro tenga solo lectura.
- No repitas el envoltorio `<div class="ag-form-section__field--full">` para
  campos anchos — la tarea 32, que va antes en la cola, ya deja
  `atoms/input` fusionando `$attributes` en su raíz.

## Cómo repartir las etapas

- **Etapa 1**: `Aplicacion/` (los cuatro casos de uso), `Infraestructura/Http`
  (controller, requests, rutas), permisos, menú, `ComercialServiceProvider::boot()`.
- **Etapa 2**: las tres vistas sobre el arquetipo, contactos dinámicos,
  `lang/es/comercial.php`.
- **Etapa 3**: tests Feature (ver criterio de aceptación).
- **Etapa 4**: margen — spec visual, checklist §8 de la guía, verificación en
  navegador en los dos temas.

## Criterio de aceptación

- `./bin/verify` = 0, con la etapa de Playwright.
- Test Feature (`tests/Feature/Comercial/GestionClientesPanelTest.php` o
  similar) que cubra, siguiendo el patrón de asserts de
  `tests/Feature/Seguridad/RevocarDispositivoPanelTest.php`:
  - Alta de un cliente con al menos un contacto → 302/200 + registro en BD.
  - Bitácora de alta, edición y baja, patrón de
    `tests/Feature/Finanzas/GenerarDevengosSesionTest.php:238-246`:
    `Bitacora::query()->where('tabla', 'com_clientes')->where('registro_id', $id)->where('accion', AccionBitacora::Creado)->sole()`.
  - Baja: soft delete (`Cliente::withTrashed()`, `->trashed()` true), no
    aparece en `index`, 404 si se reintenta sobre uno ya borrado.
  - NIT duplicado entre dos clientes activos → error de validación, no
    `QueryException`.
  - 403 para un rol sin el permiso; permiso en un rol no-activo de la sesión
    no alcanza (invariante 10).
  - El ítem de menú "Clientes" queda gateado por `comercial.cliente.ver`.
- Spec visual nuevo (`tests/Visual/clientes.spec.ts`, mismo patrón que
  `organizacion.spec.ts`: login real, `esperarFuentes`, claro y oscuro) para
  al menos la vista `index` y una de `create`/`edit`.
- Checklist de cierre de `docs/diseno/guia_pantalla_panel.md` §8 pasa (ventana
  no scrollea, `position: absolute` con ancestro explícito, cero color/medida
  literal en el CSS de página, contraste AA en claro y oscuro).

## Puede tocar

`app/Dominios/Comercial/**` (nuevo: `Aplicacion/`, `Infraestructura/Http/`),
`app/Dominios/Comercial/Infraestructura/ComercialServiceProvider.php`,
`routes/web.php`, `database/seeders/Catalogo/SeguridadSeeder.php`,
`database/seeders/Catalogo/SecMenuSeeder.php`, `lang/es/comercial.php`,
`resources/css/pages/clientes.css` (si hace falta), `tests/**`.

Fuera de alcance: cualquier otro módulo de Sprint 7, cambios al esquema de
`com_clientes`/`com_cliente_contactos`, Livewire.
