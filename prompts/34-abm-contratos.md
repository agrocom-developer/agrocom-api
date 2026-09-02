<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/contratos-panel etapas=5 -->

# Tarea 34 — HU-23: administración de contratos con sus ventanas de aplicación

## Por qué esta tarea y por qué después de la 33

`plan_sprints.md` Sprint 7 (§174): "Como encargado, quiero administrar
contratos con sus ventanas de aplicación y tarifa, para que las órdenes
cuelguen de un contrato vigente." `com_contratos` y `com_contrato_ventanas`
existen, migradas y auditadas, desde TE-03.

Un contrato pertenece a un cliente (`cliente_id`, `restrictOnDelete`) — esta
tarea asume que la tarea 33 (HU-22, clientes) ya está integrada en `develop`
y reutiliza su mismo patrón: `AutorizacionPanelWeb` para permiso + cáscara,
Controller + Form Request + Blade (sin Livewire), namespace de vista
`comercial::`, arquetipo formulario de la tarea 31. No repitas las decisiones
de esa tarea explicándolas de nuevo — léela (`prompts/33-abm-clientes.md`) si
necesitás el detalle exacto de cómo armó el patrón.

**A diferencia de clientes, `com_contratos` tiene una columna `estado`** con
`CHECK (estado IN ('borrador', 'vigente', 'finalizado', 'cancelado'))`. Eso es
una máquina de estados de dominio — invariante 7 de `CLAUDE.md`: "toda
transición de estado pasa por el servicio de dominio de la máquina de estados
correspondiente [...] nunca un `estado = ...` suelto en un controlador". El
gate de la tarea 04 (`tests/Unit/TransicionesEstadoTest.php`) vigila
exactamente esto — si el `estado` se asigna fuera de un servicio reconocido,
la cascada lo va a rechazar.

## Qué hacer

Cargá las skills `dominio-backend`, `panel-design-ui` y `verificacion`.

### 1. Máquina de estados de `Contrato`

`app/Dominios/Comercial/Aplicacion/MaquinaEstados/MaquinaEstadosContrato.php`
(o `Dominio/MaquinaEstados/`, seguí la ubicación que ya usan
`MaquinaEstadosTrabajo`/`MaquinaEstadosSesion`/`MaquinaEstadosActa`/
`MaquinaEstadosVersionApk` en sus respectivos módulos — buscalas antes de
decidir la carpeta). Tabla de transiciones permitidas + guardas:

- `borrador → vigente`: requiere al menos una ventana horaria cargada y
  `fecha_inicio` no en el pasado (decisión razonable a falta de una regla más
  específica en la especificación — documentala en el docblock de la clase).
- `vigente → finalizado`: sin guarda adicional de esta tarea (el cierre real
  por consumo de hectáreas es de otro dominio, `Operaciones`).
- `vigente → cancelado`, `borrador → cancelado`: baja anticipada.
- Cualquier otra transición: rechazada (excepción de dominio, mismo patrón
  que las otras máquinas de estados — no un `abort()` HTTP directo desde el
  controller).

Si mientras implementás encontrás que la especificación funcional sí dice
algo más preciso sobre estas transiciones, seguí lo que diga ahí — esto es
una base razonable, no una regla cerrada.

### 2. Capa de aplicación — `app/Dominios/Comercial/Aplicacion/`

Sumá a lo que dejó la tarea 33: `ListarContratos`, `CrearContrato`,
`ActualizarContrato`, `CambiarEstadoContrato` (invoca la máquina de estados).
Mismo criterio que clientes: un contrato se crea/edita con sus ventanas
horarias en la misma operación transaccional.

**`monto_total` no es un campo del formulario.** El comentario de la
migración lo dice explícito: "Recalculable: hectareas_contratadas ×
aplicaciones_previstas × precio_ha". Calculalo en `CrearContrato`/
`ActualizarContrato`, nunca lo aceptes como input libre — es la invariante 6
de `CLAUDE.md` (todo monto derivado debe recalcularse desde el origen y
cuadrar exacto). Usá `Brick\Math\BigDecimal` para el cálculo (`bcmath` no
está instalado en este repo, `BigDecimal` ya es dependencia de Laravel — no
uses operadores aritméticos de PHP nativo sobre decimales de dinero).

**Ventanas horarias**: `hora_fin > hora_inicio` ya es un `CHECK` en Postgres,
y no hay dos ventanas idénticas por el índice único parcial. Falta la validación
de aplicación que pide el criterio del plan de sprints ("no permite ventana
fuera del rango del contrato"): ninguna ventana nueva puede **solaparse** con
otra ventana ya cargada del mismo contrato (el índice único solo bloquea
duplicados exactos, no solapamientos parciales como 06:00-10:00 contra
08:00-12:00). Validalo en el caso de uso, con un mensaje de error claro.

### 3. HTTP — `app/Dominios/Comercial/Infraestructura/Http/`

- `ContratosController.php`: `index`, `create`, `store`, `edit`, `update`,
  y una acción de cambio de estado (`cambiarEstado` o similar, `POST
  /panel/contratos/{contrato}/estado`) — no un `destroy` de baja lógica común,
  la baja de un contrato es una transición a `cancelado`, no un soft delete
  fuera de la máquina de estados.
- Form Requests con los rangos exactos de los 13 `CHECK` de la migración
  (`hectareas_contratadas > 0`, `aplicaciones_previstas >= 1`, `precio_ha >=
  0`, `fecha_fin >= fecha_inicio` si viene, los seis parámetros de clima
  opcionales con sus rangos, etc.) — que el usuario vea el error de
  validación de Laravel, no el `QueryException` de Postgres.
- Vistas en `Infraestructura/Http/Views/pages/contratos/`: `index` (arquetipo
  Listado, con `atoms/badge` por estado — un color por estado, vía token, no
  hardcodeado), `create`/`edit` (arquetipo Formulario: sección "Datos del
  contrato", sección "Parámetros de vuelo" para los seis campos de clima
  opcionales, sección "Ventanas de aplicación" con el mismo mecanismo de
  filas dinámicas que la tarea 33 usó para contactos — reutilizá ese patrón
  de JS si quedó como pieza reusable, no lo reescribas). El selector de
  cliente es un `<select>` con los clientes activos
  (`Cliente::query()->orderBy('razon_social')->pluck(...)`) — no hay átomo
  `select` en el catálogo todavía (confirmado: no existe); usá un `<select>`
  nativo con las clases BEM del sistema de diseño, no un componente nuevo —
  eso es alcance de `design-ui`, no de esta HU.

### 4. Rutas, permisos, menú

Mismo patrón que la tarea 33. Permisos nuevos en `SeguridadSeeder`:

```
comercial.contrato.ver
comercial.contrato.crear
comercial.contrato.editar
comercial.contrato.cambiar_estado
```

(separado de `.editar`, mismo criterio que `usuario.bloquear` separado de
`usuario.editar`: cambiar de `vigente` a `cancelado` no es la misma
responsabilidad que corregir un dato). Sumalos a
`PERMISOS_ENCARGADO_OPERACIONES`. Activá el ítem `comercial.contratos` en
`SecMenuSeeder` (ya sembrado como botón sin link, línea 73) con `ruta:
'panel.contratos.index', codigoPermiso: 'comercial.contrato.ver'`.

### 5. Copy — `lang/es/comercial.php`

Ya existe desde la tarea 33: sumale las claves de esta pantalla.

## Qué NO hacer

- No conviertas `monto_total` en un campo editable "por si el usuario lo
  quiere ajustar" — si algún día hace falta un ajuste manual (descuento,
  corrección), es una decisión de negocio nueva, no algo que esta tarea
  decida por su cuenta.
- No le pongas un permiso único `comercial.contrato.gestionar` que tape
  crear/editar/cambiar_estado — separalos, es lo que permite roles futuros
  de solo lectura o solo aprobación.
- No toques `com_campos`/`com_lotes`/`ope_ordenes_aplicacion` — son HU-24 y
  HU-25, tareas aparte, aunque las órdenes cuelguen de un contrato vigente.
- No inventes un átomo `select` nuevo para el catálogo — usá el `<select>`
  nativo con las clases del sistema de diseño; si aparece un segundo
  consumidor que lo necesite, ahí se justifica pedírselo a `design-ui`
  (regla de "un patrón que aparece dos veces ya es del catálogo", guía §2).

## Cómo repartir las etapas

- **Etapa 1**: `MaquinaEstadosContrato` + su test unitario de transiciones
  (aislado, sin HTTP — confirma que el gate de la tarea 04 lo reconoce).
- **Etapa 2**: `Aplicacion/` (casos de uso, cálculo de `monto_total`,
  validación de solapamiento de ventanas).
- **Etapa 3**: `Infraestructura/Http` completo (controller, requests, rutas,
  permisos, menú).
- **Etapa 4**: vistas sobre el arquetipo + `lang/es/comercial.php`.
- **Etapa 5**: tests Feature + spec visual + margen de checklist §8.

## Criterio de aceptación

- `./bin/verify` = 0, con la etapa de Playwright.
- Test unitario de la máquina de estados: transición válida pasa, inválida
  lanza excepción de dominio, y `tests/Unit/TransicionesEstadoTest.php` sigue
  en verde con `Contrato` incluido en su descubrimiento.
- Test Feature con, al menos:
  - Alta de un contrato con dos ventanas horarias que no se solapan → 302/200
    y `monto_total` calculado exacto (`hectareas_contratadas ×
    aplicaciones_previstas × precio_ha`, comparado como `DECIMAL`, nunca con
    `==` de float).
  - Alta con una ventana que se solapa con otra del mismo contrato → error de
    validación, no persiste ninguna.
  - Transición `borrador → vigente` sin ninguna ventana cargada → rechazada
    por la guarda.
  - Transición inválida (`finalizado → vigente`) → rechazada por la máquina
    de estados, no llega a persistir.
  - Bitácora de alta y de cada cambio de estado, mismo patrón que la tarea
    33 (`Bitacora::query()->where('tabla', 'com_contratos')...`).
  - 403 para un rol sin el permiso correspondiente.
- Spec visual nuevo (`tests/Visual/contratos.spec.ts`), mismo patrón que
  `organizacion.spec.ts`.
- Checklist de `docs/diseno/guia_pantalla_panel.md` §8 pasa.

## Puede tocar

`app/Dominios/Comercial/**`, `routes/web.php`,
`database/seeders/Catalogo/SeguridadSeeder.php`,
`database/seeders/Catalogo/SecMenuSeeder.php`, `lang/es/comercial.php`,
`resources/css/pages/contratos.css` (si hace falta), `tests/**`.

Fuera de alcance: cualquier otro módulo de Sprint 7, cambios al esquema de
`com_contratos`/`com_contrato_ventanas`, Livewire, un átomo `select` nuevo.
