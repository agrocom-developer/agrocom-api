<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/facturacion-actas etapas=4 -->

# Tarea 45 — HU-31: facturar un trabajo desde su acta conformada

## Por qué esta tarea

`plan_sprints.md` Sprint 9 (§205): "Como encargado, quiero emitir la factura
de un trabajo desde su acta conformada, para cobrar sobre hectáreas ya
firmadas." Criterio: factura solo desde acta en estado `firmada`; monto =
hectáreas conformadas × tarifa del contrato; no permite facturar dos veces
el mismo trabajo. Abre Sprint 9 ("cobrarle al cliente") con Sprint 8
(devengos/anticipos/planilla) ya cerrado por las tareas 40/42/43/44.

**No es crítica** (a diferencia de HU-30): no está en la lista de
`CLAUDE.md` — genera un registro de cobro, no un desembolso automático de
dinero real como devengos/planilla.

**Módulo dueño: `Comercial`**, no `Operaciones`. El dinero que entra
(clientes, contratos, ahora facturas) vive en `Comercial` — mismo criterio
que HU-22/23/24 (tareas 33/34/35). El acta (`ope_actas`) y el trabajo
(`ope_trabajos`) son de `Operaciones`; cruzar ese límite de módulo es el
punto central de esta tarea, no un detalle.

## Qué hacer

Cargá las skills `modelo-datos`, `dominio-backend`, `arquitectura`,
`panel-design-ui` y `verificacion`.

### 1. El cruce de módulos — seguí el patrón de `LecturaSesionValidada`, no inventes uno nuevo

`Comercial` necesita, del acta de `Operaciones`: si está `firmada`, sus
`hectareas_conformadas`, y el `contrato_id` al que pertenece (vía
`Acta.trabajo_id → Trabajo.orden_id → OrdenAplicacion.contrato_id` — mirá
`tests/Feature/Finanzas/AnticiposPanelTest.php::trabajoAbiertoParaAnticipos()`
para la cadena real de esas tres tablas, ya la usa otra tarea reciente).

ADR 0003 regla 3 prohíbe que `Comercial` importe el modelo Eloquent `Acta`
de `Operaciones` directo. El patrón ya establecido para esto (HU-16, tarea
16) es un contrato de lectura expuesto por el módulo DUEÑO del dato:

- `Operaciones/Contratos/LecturaActaConformada.php` (interfaz) +
  `Operaciones/Contratos/DatosActaConformada.php` (DTO readonly) — mismo
  estilo exacto que `Operaciones/Contratos/LecturaSesionValidada.php` +
  `DatosSesionValidada.php`, leelos antes de escribir los tuyos.
- `Operaciones/Infraestructura/LecturaActaConformadaEloquent.php` — la
  implementación real, resuelve la cadena acta→trabajo→orden→contrato.
- Bindeala en `OperacionesServiceProvider` (`$this->app->bind(...)`, mismo
  lugar donde está el bind de `LecturaSesionValidada`).
- `Comercial/Aplicacion/EmitirFactura.php` la consume por constructor,
  nunca importa `Acta` ni `Trabajo` ni `OrdenAplicacion`.

Decidí vos la forma exacta del método (`obtenerPorActaId(int): ?DatosActaConformada`
o `obtenerPorTrabajoId`, según qué use el flujo de la pantalla — probablemente
la pantalla lista actas firmadas sin factura, así que puede convenir un
segundo método `listarFirmadasSinFacturar(): array` en el mismo contrato).
Documentá la decisión en el docblock del contrato, como hace
`LecturaSesionValidada`.

### 2. Modelo de datos — `com_facturas`

`id, contrato_id` (FK `com_contratos`, `restrictOnDelete`), `acta_id`
(entero plano, **sin** `belongsTo` hacia `Acta` — mismo criterio que
`fin_devengos_personal.sesion_id`; sí puede llevar `->constrained('ope_actas')`
a nivel de FK física, eso no es un problema de arquitectura, lo que se evita
es el `belongsTo` en el modelo Eloquent), `UNIQUE (acta_id)` entre vivas —
es la guarda real de "no permite facturar dos veces el mismo trabajo" (un
acta es única por trabajo desde la tarea 24, así que única por acta implica
única por trabajo). `hectareas_facturadas DECIMAL(10,2)` (snapshot al
emitir, copiado de `hectareas_conformadas` del acta — no recalculable
después, mismo criterio que `DevengoPersonal.hectareas`), `precio_ha
DECIMAL(10,2)` (snapshot del contrato al momento de emitir), `monto
DECIMAL(12,2)` (= `hectareas_facturadas × precio_ha`, calculado y
persistido, nunca editable a mano), `fecha_emision DATE`, auditoría, soft
delete. Consultá la skill `modelo-datos` para el patrón de índice único
parcial con soft delete.

### 3. Caso de uso — `Aplicacion/EmitirFactura.php`

Recibe un `acta_id`. Guardas de negocio (excepción de dominio
`ActaNoFacturable`, con el motivo específico en el mensaje):

1. El acta existe y está `firmada` (vía el contrato del punto 1).
2. No existe ya una factura viva para esa `acta_id` (`lockForUpdate()` o
   reintento contra el `UNIQUE`, mismo patrón que `GenerarActaTrabajo`
   frente al `UNIQUE` de `ope_actas.uuid_cliente`).

Resuelve `contrato_id` desde el DTO del contrato, carga `precio_ha` de
`Comercial\Infraestructura\Eloquent\Contrato` (esa sí es propia del módulo,
`belongsTo` normal), calcula `monto` con `Brick\Math\BigDecimal` (invariante
6 — nunca floats), persiste.

### 4. HTTP, permisos, menú — mismo patrón que Comercial (tareas 33-35)

`FacturasController@index/create/store`. `create` lista las actas firmadas
sin facturar (vía `listarFirmadasSinFacturar()` del contrato) para elegir.
Permisos `comercial.factura.ver`/`.crear` en `PERMISOS_ENCARGADO_OPERACIONES`.
Ítem de menú "facturas" — ya existe como botón sin link en
`SecMenuSeeder.php` (grupo financiero), activalo con `codigoPermiso:
'comercial.factura.ver'` en vez de crear uno nuevo.

## Qué NO hacer

- No importes `Acta`, `Trabajo` ni `OrdenAplicacion` desde código de
  `Comercial` — todo pasa por el contrato de lectura del punto 1.
- No recalcules `monto` en vivo contra el contrato actual al mostrar la
  factura — es un snapshot congelado al emitir (invariante 6, mismo
  criterio que devengos).
- No implementes HU-32 (reporte comercial de avance) — es la siguiente HU
  del sprint, con su propio prompt.
- No toques `ope_actas` ni su máquina de estados — esta tarea solo lee actas
  ya firmadas, nunca las muta.
- No calcules con floats en ningún paso.

## Cómo repartir las etapas

- **Etapa 1**: contrato de lectura (`LecturaActaConformada` +
  implementación + bind), migración, modelo `Factura`.
- **Etapa 2**: `EmitirFactura`, `ActaNoFacturable`.
- **Etapa 3**: controller, rutas, permisos, menú, vistas + copy.
- **Etapa 4**: tests Feature + spec visual + checklist §8.

## Criterio de aceptación

- `./bin/verify` = 0, con Playwright.
- Test Feature (`tests/Feature/Comercial/FacturasPanelTest.php`) cubriendo:
  emitir factura desde acta `firmada` calcula `monto` exacto (hectáreas
  conformadas × precio_ha del contrato); rechaza emitir desde un acta
  `pendiente` (sin firmar); rechaza una segunda emisión sobre la misma acta
  (`ActaNoFacturable`, ya facturada); el monto se persiste en `DECIMAL`
  exacto sin error de redondeo flotante; 403 sin el permiso correspondiente;
  bitácora en el alta.
- Spec visual (`tests/Visual/facturas.spec.ts`), claro y oscuro.
- Checklist de `docs/diseno/guia_pantalla_panel.md` §8 pasa.

## Puede tocar

`app/Dominios/Comercial/**`, `app/Dominios/Operaciones/Contratos/**`
(contrato de lectura nuevo, ver punto 1), `app/Dominios/Operaciones/Infraestructura/`
(su implementación y el bind en `OperacionesServiceProvider`), migración
nueva, `routes/web.php`, `database/seeders/Catalogo/SeguridadSeeder.php`,
`database/seeders/Catalogo/SecMenuSeeder.php`, `lang/es/comercial.php`,
`tests/**`.

Fuera de alcance: HU-32 (reporte comercial), cualquier cambio a la máquina
de estados del acta o del trabajo.

---

**Nota de la planificación**: esta es la tercera tarea de esta tanda
(43-anticipos, 44-planilla, 45-facturas). No pude verificar contra código
real que el contrato `LecturaActaConformada` no exista ya con otro nombre
—si al arrancar encontrás uno equivalente, extendelo en vez de duplicarlo—.
HU-32 (reporte comercial de avance, siguiente HU de Sprint 9) queda para la
próxima vuelta de planificación: depende de cómo termine exponiendo esta
tarea el dato de "hectáreas facturadas por contrato", conviene decidirlo con
`com_facturas` ya integrada.
