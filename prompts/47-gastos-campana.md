<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/gastos-campana etapas=5 -->

# Tarea 47 — HU-33: gastos con categoría y comprobante

## Por qué esta tarea

`plan_sprints.md` Sprint 10 (§218): "Como encargado, quiero cargar gastos
con su categoría y comprobante, para que la campaña tenga costo real." CA
esencial: ABM de gastos con evidencia adjunta; categorías de catálogo;
imputable a trabajo, base o general. Abre Sprint 10 ("gastos y
rendiciones") con Sprint 9 (HU-31/HU-32) ya cerrado por las tareas 45/46.

**No es crítica**: es un ABM de carga de gasto real, no un listener
automático que genera dinero (a diferencia de devengos/planilla, que
`CLAUDE.md` sí nombra explícitamente).

**Módulo dueño: `Finanzas`** (`fin_`, ADR 0011) — mismo módulo de
devengos/anticipos/planilla, dinero de la operación. No es `Comercial`
(eso es dinero que entra) ni `Operaciones` (eso es ejecución de campo).

## El modelo completo de la especificación es más rico que el CA de esta HU — recortalo a propósito

`docs/especificacion/especificacion_funcional_tecnica.md` (sección 4,
~línea 143) describe `rubros`, `subrubros` (con `tipo_imputacion`:
`directo_dron` / `directo_vehiculo` / `compartido`) y `gastos` (con
`dron_id`, `vehiculo_id`, `medio_pago`, `tiene_comprobante`,
`rendicion_id`). El CA esencial de `plan_sprints.md` es más chico:
"categorías de catálogo" + "imputable a trabajo, base o general". Sí existe
`ope_drones` (tarea 36) pero **no** existe ningún módulo `Vehículo` ni
`fondos_caja` todavía. Recorte para esta tarea, mismo criterio que la 18
(mezcla) y la 26 (alertas) usaron antes:

- Traé `rubros`/`subrubros` como catálogo real (`fin_rubros`, `fin_subrubros`),
  sembrado por seeder con los 8 rubros del presupuesto + "Indirectos" (espec
  §4, línea 143) — es justamente lo que el CA llama "categorías de
  catálogo".
- **Dejá fuera** `dron_id`, `vehiculo_id`, `medio_pago`, `tiene_comprobante`
  como columna booleana separada (el comprobante adjunto ya lo dice por su
  sola presencia) y `tipo_imputacion` de `subrubros` — no hay dato hoy que
  los sostenga (ni vehículo, ni fondos de caja). Si `tipo_imputacion`
  te resulta necesario para alguna guarda real, dejalo con solo los dos
  valores que sí tienen módulo (`directo_dron`/`compartido`) y documentá
  por qué en el docblock — decisión tuya, no es un CA esencial.
- **`rendicion_id` NO va en esta tarea** — lo agrega la tarea 48 (HU-34,
  siguiente en la cola) por `ALTER TABLE` cuando exista `fin_rendiciones`,
  mismo patrón que `capacidad_l` se agregó a `ope_drones` por ALTER (tarea
  36) en vez de anticiparlo.

## Qué hacer

Cargá las skills `modelo-datos`, `dominio-backend`, `panel-design-ui` y
`verificacion`.

### 1. Modelo de datos

- `fin_rubros`: `id, nombre, presupuesto_bs_ha DECIMAL`, auditoría, soft
  delete. Seeder con los 8 rubros + Indirectos (buscá los nombres exactos
  en la especificación si están, si no, usá categorías genéricas
  razonables para agroaplicación — combustible, mantenimiento, personal de
  apoyo, insumos varios, etc. — y dejalo anotado en el seeder).
- `fin_subrubros`: `id, rubro_id` (FK `fin_rubros`), `nombre`, auditoría,
  soft delete.
- `fin_gastos`: `id, fecha DATE, rubro_id` (FK), `subrubro_id` (FK,
  nullable), `cantidad DECIMAL`, `precio_unitario DECIMAL`, `monto DECIMAL`
  (= `cantidad × precio_unitario`, calculado y persistido — invariante 6),
  `base_id` (FK `per_bases`, nullable, entero plano sin `belongsTo`
  cross-módulo — mismo criterio que `Anticipo.persona_id`),
  `trabajo_id` (FK `ope_trabajos`, nullable, mismo criterio), `comprobante_url`
  (nullable, ruta en `Storage::disk('r2')`), `comprobante_hash` (nullable,
  SHA-256 del archivo), auditoría, soft delete.
- "Imputable a trabajo, base o general": `trabajo_id`/`base_id` nullable
  simples alcanza para el CA esencial — un gasto con ambos NULL es
  "general". No hace falta un `CHECK` de exclusión mutua salvo que quieras
  documentar por qué sí o por qué no (un gasto de un trabajo específico
  bien puede tener también su `base_id`).

Consultá la skill `modelo-datos` para el patrón de FK plana + índice.

### 2. Comprobante — primera subida de archivo humana desde el panel

Toda subida de evidencia que existe hoy (`ope_evidencias`) es del motor de
sync, subida por la app de campo con `uuid_cliente`. Esta es distinta: un
humano sube un archivo desde un formulario del panel web. **No reuses
`ope_evidencias`** (es de `Operaciones`, cruzar a ella para esto viola ADR
0003 regla 1 — esta tabla no es de Operaciones). Implementación propia
dentro de `Finanzas`:

- `FormRequest` valida tipo (imagen o PDF) y tamaño máximo — mirá
  `SubirEvidenciaRequest` (`Operaciones/Infraestructura/Http/Requests/`)
  como referencia de qué validar, sin copiar su tabla destino.
- Guardá el archivo con `Storage::disk('r2')->put(...)`, mismo disco que
  usa `GenerarReciboPlanilla` para el PDF del recibo.
- Calculá el hash SHA-256 del contenido antes de guardarlo, igual criterio
  que `RegistrarEvidencia` en Operaciones (integridad del archivo).

Decisión de negocio que te toca a vos: si el comprobante es obligatorio
siempre o solo según el medio de pago (la espec lo sugiere, pero
`medio_pago` queda fuera de esta tarea) — como no hay ese dato, hacelo
opcional y documentá el porqué en el docblock del `FormRequest`.

### 3. Caso de uso y ABM

`Finanzas/Aplicacion/CrearGasto.php` (calcula `monto` con
`Brick\Math\BigDecimal`, persiste, guarda comprobante si vino),
`ListarGastos.php` (filtros por rubro/base/trabajo/período),
`EliminarGasto.php` (baja lógica — mismo criterio que `Anticipo`: un gasto
cargado no se edita, si está mal se da de baja y se recarga, evita que un
gasto ya rendido cambie de monto por debajo de una rendición en curso).
Documentá esa decisión (sin edición) en el docblock si la tomás así; si
preferís permitir editar antes de que esté asociado a una rendición,
también es válido — dejalo explícito.

### 4. HTTP, permisos, menú

`GastosController@index/create/store/destroy`. Permisos
`finanzas.gasto.ver`/`.crear`/`.eliminar` en `PERMISOS_ENCARGADO_OPERACIONES`
(el encargado es quien carga gastos, HU-33 lo dice literal). Activá el
ítem **`gastos`** ya sembrado como "botón sin link" en el grupo
`financiero` de `SecMenuSeeder.php` (línea ~109) — no crees uno nuevo.

## Qué NO hacer

- No implementes `rendiciones`, `combustible` ni `fondos_caja` — son HU-34
  y HU-35, prompts propios (la 34 ya está escrita, la 35 queda para la
  próxima planificación).
- No agregues `dron_id`/`vehiculo_id`/`medio_pago`/`tipo_imputacion` con
  todos los valores de la espec si no hay dato real detrás (ver el recorte
  de arriba) — no inventes un módulo `Vehículo` para esto.
- No reuses `ope_evidencias` para el comprobante.
- No calcules `monto` con floats.

## Cómo repartir las etapas

- **Etapa 1**: migraciones (`fin_rubros`, `fin_subrubros`, `fin_gastos`),
  modelos Eloquent, seeder de rubros/subrubros.
- **Etapa 2**: subida de comprobante (`FormRequest` + `Storage`).
- **Etapa 3**: `CrearGasto`, `ListarGastos`, `EliminarGasto`.
- **Etapa 4**: controller, rutas, permisos, menú, vistas + copy.
- **Etapa 5**: tests Feature + spec visual + checklist §8.

## Criterio de aceptación

- `./bin/verify` = 0, con Playwright.
- Test Feature (`tests/Feature/Finanzas/GastosPanelTest.php`) cubriendo:
  `monto` calculado exacto (`cantidad × precio_unitario`, sin error
  flotante); gasto imputado a trabajo, a base, y general (ninguno de los
  dos) los tres casos válidos; comprobante subido se guarda con su hash;
  tipo de archivo inválido rechazado; 403 sin el permiso; bitácora en alta
  y baja; baja lógica no borra físicamente la fila.
- Spec visual (`tests/Visual/gastos.spec.ts`), claro y oscuro.
- Checklist de `docs/diseno/guia_pantalla_panel.md` §8 pasa.

## Puede tocar

`app/Dominios/Finanzas/**`, migraciones nuevas, `routes/web.php`,
`database/seeders/Catalogo/SeguridadSeeder.php`,
`database/seeders/Catalogo/SecMenuSeeder.php`, `lang/es/finanzas.php`,
`tests/**`.

Fuera de alcance: `Operaciones/**` (salvo leer `ope_trabajos.id` por FK
plana), `fin_rendiciones` (tarea 48), combustible (HU-35, sin prompt
todavía).
