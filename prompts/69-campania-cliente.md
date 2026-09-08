<!-- ciclo: critica=si turno-noche=1 descongela=tests rama=feature/campania-cliente etapas=3 -->

# Tarea 69 — HU-46: la campaña **del cliente** como eje del sistema

## Por qué esta tarea

El sistema nunca tuvo corte por ciclo productivo. `ObtenerAvanceComercial`
(HU-32) se escribió con el enunciado "avance por cliente, contrato **y
campaña**" y entregó las dos primeras; `fin_gastos` y `fin_combustibles`
tienen docblocks que dicen "para que la campaña tenga costo real" e imputan a
algo que no existe como tabla; la tarea 67 apagó el chip del header con el
comentario *"el dominio no tiene el concepto de campaña en ninguna tabla"*.

**Esta tarea ya arrancó una vez y se cortó a propósito.** El 7/9 se escribió
con la campaña como eje de Agrocom (una `2025-2026` de la empresa, activa por
sesión como el rol activo). El 8/9, con la implementación en curso, el dueño
corrigió el supuesto de base:

> *"cada cliente maneja sus campañas, nosotros solo vamos a fumigar (…)
> nosotros no hacemos campañas, solo fumigamos cuando el cliente está en
> campaña"*

**La campaña es del cliente.** Agrocom aplica dentro de la campaña ajena. El
ADR 0015 está corregido (leé su punto 1 y su punto 6, más la sección
"Corrección del 8/9/2026" al final) y es la fuente del alcance junto con
`plan_sprints.md` HU-46.

**Es crítica**: agrega una columna obligatoria a `com_contratos` (tabla de
dinero) con migración de datos. Revisión posterior a la integración, anotada
en `runs/revision-pendiente.txt` — el PR **no** se retiene (`CLAUDE.md`).

## De dónde arrancás: la rama ya tiene trabajo hecho

`feature/campania-cliente` tiene tres commits y **el ciclo la retoma, no la
crea**. Antes de escribir nada, `git log develop..HEAD` y leé lo que hay.

**Sirve tal cual, no lo rehagas:**

- El módulo `app/Dominios/Campania/` con sus cuatro capas y su
  `CampaniaServiceProvider` registrado.
- La migración `2026_09_08_100001_create_cpn_campanias_table.php` (hay que
  **editarla**, no crear una segunda: todavía no está en `develop`).
- La máquina de estados `planificada → abierta → cerrada` con sus
  transiciones, el modelo `Campania`, y el ABM (`CampaniasController`,
  requests, vistas, permisos, menú).
- El renombrado mecánico `campana` → `campaniaActiva` en 82 vistas y en el
  chrome del panel (punto 2 del ADR: es independiente de a quién pertenece la
  campaña).

**Hay que borrarlo, porque asumía la campaña de la empresa:**

- `Aplicacion/ElegirCampaniaActiva.php`, `Contratos/CampaniaActivaSesion.php`,
  `Infraestructura/CampaniaActivaSesionEloquent.php`,
  `Http/Controllers/Web/CampaniaActivaController.php`,
  `Http/Middleware/ResolverCampaniaActiva.php`,
  `Http/Requests/ActualizarCampaniaActivaRequest.php` y sus rutas.
- `Dominio/ValidadorSolapamientoCampanias.php` y
  `Dominio/Excepciones/CampaniaSolapada.php`.

La prop `campaniaActiva` del chrome **se conserva con el nombre nuevo y sigue
recibiendo `null`**, igual que desde la tarea 67: no hay chip que pintar. No
inventes un chip nuevo ni la elimines de las 82 vistas.

## Qué hacer

1. **`cpn_campanias` gana `cliente_id`** (FK real a `com_clientes`, entero
   plano, `restrictOnDelete`). Editá la migración existente. El índice único
   parcial pasa de `codigo` a **`(cliente_id, codigo)`** entre filas activas
   (`WHERE deleted_at IS NULL`): dos clientes pueden tener cada uno su
   `2025-2026`.
2. **Se cae la guarda de solapamiento**, entre clientes y dentro del mismo
   cliente. Hay tantas campañas abiertas como clientes en campaña, y un mismo
   cliente puede tener dos (soya de verano, maíz de invierno). Borrá el
   validador y su excepción, y el test que exigía el rechazo.
3. **No hay campaña activa de sesión.** Con decenas abiertas a la vez no
   significa nada: el operador trabaja en varias por día. La campaña se elige
   **dentro del cliente o del contrato**.
4. **ABM de campañas** (`/panel/campanias`), ya empezado: agregá el selector
   de cliente en alta y edición, mostrá el cliente en el listado y permití
   filtrar por él. Los permisos y el ítem de menú ya están sembrados. Solo el
   dueño cierra una campaña.
5. **`campania_id` en `com_contratos`**, `NOT NULL` al final, FK real + entero
   plano (ADR 0003 regla 3, sin `belongsTo` cross-módulo). Migración de datos
   en la misma migración: **una campaña `2025-2026` por cada cliente que ya
   tenga contratos** (`abierta`, 1/7/2025 a 30/6/2026), cada contrato a la de
   su propio cliente, y recién ahí la columna `NOT NULL`.
6. **Guarda de mismo cliente**: `CrearContrato` y `ActualizarContrato`
   rechazan una campaña cuyo `cliente_id` no sea el del contrato, con
   excepción de dominio propia y mensaje traducido. Es la guarda central de
   esta tarea: sin ella el modelo permite facturarle a un cliente el ciclo de
   otro.
7. **`campania_id` nullable en `fin_gastos`**, con el significado del punto 6
   del ADR: **en qué campaña se consumió** el gasto — atribución de costo, no
   de cobro. Al cliente no se le factura el gasto: paga por hectárea aplicada.
   Vacío = gasto interno que no pertenece a ninguna campaña. En el formulario,
   el selector es opcional y se filtra por campañas no cerradas.
8. **Guarda de campaña cerrada**: `CrearContrato` y `CrearGasto` rechazan
   imputar a una campaña `cerrada`.
9. **Filtro por campaña** en el listado de contratos, dentro del cliente. No
   inventes filtros donde la pantalla no los tenga hoy.
10. **Seeder** idempotente: una campaña `2025-2026` por cada cliente demo, para
    que `migrate --seed` deje el panel usable.

## Qué NO hacer

- **No agregues `campania_id`** a `ope_ordenes_aplicacion`, `ope_trabajos`,
  `ope_sesiones`, `ope_actas` ni `com_facturas`: cuelgan de un contrato que ya
  la tiene, y duplicarla hace representable "trabajo de la campaña A en un
  contrato de la campaña B".
- **No lo agregues tampoco a `per_equipos_trabajo` ni a
  `ope_estadias_hacienda`** (tareas 72 y 74): el equipo es de Agrocom y trabaja
  para varias campañas; la estadía saca el cliente de su campo y la fecha
  ubica la campaña.
- No toques `fin_combustibles` (es la 73), `per_*`, `ope_*` ni el motor de sync.
- No reintroduzcas campaña activa, chip del header, ni un booleano `es_actual`.
- No prorratees gastos entre campañas: la carga se atribuye entera a una sola
  (ADR 0015 punto 6). Y no expongas costo ni gasto en el portal del cliente.

## Cómo repartir las etapas

- **Etapa 1**: `cliente_id` en la migración y el modelo, unicidad por cliente,
  baja del validador de solape, baja de la campaña activa y sus rutas, y los
  tests unitarios de la máquina de estados (incluida `cerrada → abierta`).
- **Etapa 2**: ABM con cliente (selector, listado, filtro), permisos, vistas y
  tests Feature.
- **Etapa 3**: `campania_id` en contratos con migración por cliente y guarda de
  mismo cliente, nullable en gastos, guardas de campaña cerrada, filtro,
  seeder, `bin/verify`.

## Criterio de aceptación

- `./bin/verify` = 0 (en esta Mac la etapa de Playwright se saltea sola: las
  capturas de referencia son `-win32`. No generes capturas `-darwin`).
- Test: `cerrada → abierta` lanza la excepción de transición no permitida.
- Test: **dos campañas del mismo cliente con rangos solapados se aceptan**, y
  dos de clientes distintos también.
- Test: dos clientes pueden tener cada uno una campaña con código `2025-2026`;
  el mismo cliente no puede repetir el código.
- Test: crear un contrato con una campaña de **otro** cliente se rechaza.
- Test: crear un contrato o un gasto contra una campaña `cerrada` se rechaza.
- Test: un gasto sin `campania_id` se guarda bien (es gasto interno).
- Test de migración: con contratos preexistentes de varios clientes, `migrate`
  deja a cada uno con una campaña **de su propio cliente** y ninguna fila con
  `campania_id` nulo.
- `grep -rn "CampaniaActiva\|ResolverCampaniaActiva" app/ routes/` no devuelve
  nada.
- `grep -rn "'campana'" app/ resources/` solo devuelve el ícono de
  notificaciones.

## Puede tocar

`app/Dominios/Campania/**`, `app/Dominios/Comercial/**` (contrato: columna,
guarda, filtro), `app/Dominios/Finanzas/**` (gasto: columna opcional),
`app/Dominios/Seguridad/**` (chrome, seeders de permisos y menú),
`database/migrations/**`, `database/seeders/**`, `lang/es/**`,
`routes/web.php`, `resources/views/components/organisms/**`, `tests/**`.

Fuera de alcance: `fin_combustibles`, `per_*`, `ope_*`, el motor de sync.

## Cierre obligatorio de cada etapa

`runs/69.estado`, `runs/69.md`, y al `OK` `runs/69.pr.md`. Anotá la tarea en
`runs/revision-pendiente.txt` (es crítica). Commits agrupados por función, en
español, imperativo, sin `Co-Authored-By`.
