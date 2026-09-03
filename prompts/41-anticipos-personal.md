<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/anticipos-personal etapas=4 -->

# Tarea 41 — HU-29: anticipos con tope validado

## Por qué esta tarea

`plan_sprints.md` Sprint 8 (§192): "Como encargado, quiero registrar
anticipos validando el tope, para no adelantar más de lo devengado."
Criterio: tope 3.000 Bs/mes y 70 % del devengado; el rechazo dice cuánto es
el máximo disponible. Sigue a la tarea 40 (HU-28, devengos): reusa la misma
pantalla de "cuánto devengó la persona en el período" como base de cálculo
del tope — hacela después de que esa tarea esté integrada, no en paralelo,
para no duplicar la suma de devengos por período en dos ramas a la vez.

**No existe ninguna tabla `fin_*` salvo `fin_devengos_personal`.** Esta
tarea crea `fin_anticipos` desde cero. No hay máquina de estados en el
criterio de aceptación (a diferencia de HU-34, rendiciones, Sprint 10, que
sí la va a tener) — es un registro simple con una validación de tope al
crear, más cercano a un ABM acotado que a un flujo con transiciones.

**El "devengado del período" no tiene ningún agregado reusable todavía.**
Ni `DevengoPersonal` ni `Finanzas/Aplicacion` tienen un `sum()`/`groupBy`
por persona y rango de fechas — lo escribís en esta tarea. Si la tarea 40 ya
integró un query object equivalente para HU-28 (`ListarDevengosPersona` o
similar), extendelo o compartilo en vez de duplicar la lógica de "sumar
devengos de una persona en un mes calendario".

## Qué hacer

Cargá las skills `modelo-datos`, `dominio-backend`, `panel-design-ui` y
`verificacion`.

### 1. Migración — `fin_anticipos`

`id, persona_id (FK per_personas, restrictOnDelete), monto DECIMAL(12,2),
fecha DATE, motivo VARCHAR(200) nullable, created_by, updated_by,
timestamps, softDeletes`. `CHECK (monto > 0)` solo en pgsql (mismo patrón
que el resto de `fin_*`/`ope_*`). Sin `UNIQUE` especial — nada impide varios
anticipos de la misma persona en el mismo mes, el tope los limita en
conjunto, no exclusivamente uno por mes.

### 2. Modelo — `Infraestructura/Eloquent/Anticipo.php`

Extiende `ModeloDominio`, usa `RegistraBitacora`, casts `decimal:2` para
`monto`, `date` para `fecha`.

### 3. Cálculo del tope — `Aplicacion/CalcularDisponibleAnticipo.php` (o similar)

Recibe `persona_id` + mes calendario (derivado de la fecha del anticipo que
se quiere registrar). Calculá con `Brick\Math\BigDecimal` (invariante 6,
nunca floats — mismo criterio que
`GenerarDevengosSesion::calcularMonto()`):

```
devengadoMes    = suma de fin_devengos_personal.monto de esa persona en el mes
anticiposMes    = suma de fin_anticipos.monto vivos de esa persona en el mismo mes
topeDevengado   = devengadoMes * 0.70  (toScale(2, RoundingMode::HalfDown) — redondear
                  a favor del tope, nunca a favor de un anticipo más grande)
topePeriodo     = min(3000.00, topeDevengado)
disponible      = topePeriodo - anticiposMes   (nunca negativo hacia abajo del mensaje:
                  si ya da negativo, el disponible a mostrar es 0.00)
```

### 4. Caso de uso — `Aplicacion/RegistrarAnticipo.php`

Si `monto > disponible`, lanzá una excepción de dominio nueva
(`Dominio/Excepciones/AnticipoExcedeTope.php`) con el `disponible` calculado
en el mensaje — el criterio de aceptación pide literal que el rechazo diga
cuánto es el máximo disponible, no un "excede el tope" genérico. Si no
excede, creá el registro con auditoría.

También un caso de uso de baja simple (`EliminarAnticipo`, soft delete,
invariante 8) por si se registró un anticipo por error — **no implementes
edición de monto**: un anticipo, una vez creado, es inmutable salvo baja por
error (el dinero ya se entregó; cambiar el monto a mano rompe la
trazabilidad de por qué cambió).

### 5. HTTP — `Infraestructura/Http/Controllers/Web/AnticiposController.php`

`index` (listado, filtrable por persona y período), `create`, `store`,
`destroy`. Capturá `AnticipoExcedeTope` con try/catch →
`redirect()->back()->withErrors(['estado' => $excepcion->getMessage()])`,
mismo patrón que `OrdenesController`.

### 6. Vistas — `Infraestructura/Http/Views/pages/anticipos/`

`index.blade.php` (arquetipo Listado) y `create.blade.php` (arquetipo
Formulario: persona, monto, fecha, motivo opcional). Si el diseño lo
permite sin inflar el alcance, mostrá el disponible calculado en la propia
pantalla de alta (antes de enviar) — no es obligatorio para el criterio de
aceptación, pero evita que el encargado descubra el tope recién al rechazo.

### 7. Permisos — `SeguridadSeeder.php`

`finanzas.anticipo.ver`, `finanzas.anticipo.crear`,
`finanzas.anticipo.eliminar`. Sumalos a `PERMISOS_ENCARGADO_OPERACIONES`
(es quien registra anticipos, mismo criterio que el resto de catálogos del
Sprint 7).

### 8. Menú — `SecMenuSeeder.php`

No hay ítem "anticipos" sembrado (a diferencia de devengos/gastos/etc.) —
agregalo al grupo financiero, mismo patrón que los demás ítems del grupo,
con `ruta: 'panel.anticipos.index'`, `codigoPermiso:
'finanzas.anticipo.ver'`.

### 9. Rutas y copy

`panel.anticipos.index/create/store/destroy` en `routes/web.php`. Sumá las
claves que falten a `lang/es/finanzas.php` (ya creado por la tarea 40).

## Qué NO hacer

- No implementes edición de `monto`/`fecha` de un anticipo ya creado — solo
  alta y baja.
- No conviertas esto en una máquina de estados — no la pide el criterio de
  aceptación (a diferencia de HU-34, rendiciones, más adelante).
- No calcules el tope con floats — `BigDecimal` en todo el camino, redondeo
  documentado (a favor del sistema, nunca del anticipo).
- No toques `fin_devengos_personal`, `GenerarDevengosSesion`, ni la
  pantalla de la tarea 40 salvo para reusar su query de suma si ya existe.
- No implementes HU-30 (planilla) — esta tarea es solo el registro de
  anticipos con su tope; la planilla que los consume es la siguiente HU del
  sprint, con su propio prompt.

## Cómo repartir las etapas

- **Etapa 1**: migración, modelo, `CalcularDisponibleAnticipo`,
  `RegistrarAnticipo`, `EliminarAnticipo`.
- **Etapa 2**: controller, requests, rutas, permisos, menú.
- **Etapa 3**: vistas + copy.
- **Etapa 4**: tests Feature + spec visual + checklist §8.

## Criterio de aceptación

- `./bin/verify` = 0, con Playwright.
- Test Feature (`tests/Feature/Finanzas/AnticiposPanelTest.php`) cubriendo:
  - Anticipo dentro de ambos topes (≤ 3.000 Bs/mes y ≤ 70 % del devengado
    del mes) → se registra.
  - Anticipo que excede el tope absoluto de 3.000 Bs/mes → rechazado, el
    mensaje incluye el máximo disponible exacto.
  - Anticipo que excede el 70 % del devengado (con devengado bajo, p. ej.
    2.000 Bs → tope real 1.400, no 3.000) → rechazado, mensaje con el
    disponible real.
  - Dos anticipos en el mismo mes que en conjunto exceden el tope: el
    primero pasa, el segundo se rechaza contra el disponible restante (no
    contra el tope total de nuevo).
  - Baja de un anticipo por soft delete: no aparece en `index`, y libera
    cupo (un anticipo nuevo que antes hubiera excedido el tope ahora
    entra, porque el eliminado no cuenta en la suma).
  - Bitácora en alta y baja.
  - 403 sin el permiso correspondiente.
  - Monto en `DECIMAL` exacto, ningún resultado con error de redondeo
    flotante en los casos de prueba con decimales no exactos en base 2
    (mismo espíritu que el caso `3.33 × 12.35` de la tarea 16).
- Spec visual (`tests/Visual/anticipos.spec.ts`), claro y oscuro.
- Checklist de `docs/diseno/guia_pantalla_panel.md` §8 pasa.

## Puede tocar

`app/Dominios/Finanzas/**`, migraciones nuevas, `routes/web.php`,
`database/seeders/Catalogo/SeguridadSeeder.php`,
`database/seeders/Catalogo/SecMenuSeeder.php`, `lang/es/finanzas.php`,
`tests/**`.

Fuera de alcance: HU-30 (planilla), cualquier cambio a
`fin_devengos_personal`.

---

**Nota de la planificación**: esta es la tercera y última tarea de esta
tanda. HU-30 (planilla, Sprint 8) consume tanto `fin_devengos_personal`
como `fin_anticipos` — su diseño de datos concreto (cómo referencia los
anticipos de un período al armar el borrador) depende de cómo termine
saliendo esta tabla, así que se escribe en la próxima vuelta de
planificación, con el resultado real de esta tarea ya integrado en
`develop`.
