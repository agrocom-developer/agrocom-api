<!-- ciclo: critica=no turno-noche=1 rama=feature/aduana-dominio etapas=2 descongela=tests -->

# Tarea 82 — aduana genérica de la capa `Dominio/` entre módulos

## Por qué esta tarea

No es una HU ni una TE de `plan_sprints.md`: es deuda técnica concreta, con
criterio de aceptación ejecutable — mismo tipo de fila que las tareas 04, 06,
29, 30 y 61.

`tests/Unit/ArquitecturaModulosTest.php` ya prohíbe que un módulo importe el
`Infraestructura/Eloquent` ajeno y el `Aplicacion/` ajeno (con la excepción
sancionada de `Contratos/`, ver la sección "Dirección de dependencia" al
final del archivo). No prohíbe, en cambio, que un módulo importe la capa
`Dominio/` ajena — y ADR 0003 regla 2 es clara en que la frontera pública
entre módulos concretos es `Contratos/` (interfaces + DTOs), nunca `Dominio/`
ni `Aplicacion/` ajenos.

La tarea 69 (HU-46, `runs/69.md`) encontró un cruce real de este tipo al
escribir su propia guarda de arquitectura y decidió, correctamente, no
corregirlo por estar fuera de su alcance: `Mantenimiento` importa
`App\Dominios\Inventario\Dominio\Excepciones\StockInsuficiente` directo, en
tres lugares:

- `app/Dominios/Mantenimiento/Aplicacion/MaquinaEstados/MaquinaEstadosOrdenMantenimiento.php`
  (la importa y la captura para traducirla a
  `Mantenimiento\Dominio\Excepciones\RepuestosInsuficientes`).
- El docblock de `app/Dominios/Mantenimiento/Dominio/Excepciones/RepuestosInsuficientes.php`
  la referencia como la excepción que envuelve.

Dentro de `Inventario` mismo, la excepción se usa en `Aplicacion/RegistrarMovimientoStock.php`
(quien la lanza) y en `Infraestructura/Http/Controllers/Web/StockController.php`
(quien la captura para el formulario) — y el contrato de escritura
`app/Dominios/Inventario/Contratos/EscrituraConsumoStock.php` ya la declara en
su docblock (`@throws StockInsuficiente`) como parte de su interfaz pública.
Eso es la pista de la corrección: la excepción **ya es, de hecho, parte del
contrato** que `Inventario` expone hacia afuera — solo que vive en el paquete
equivocado.

## Qué hacer

Cargá el skill `verificacion` y el skill `dominio-backend` antes de empezar.
Este cambio toca una regla de arquitectura ya establecida (angosta la lista de
lo permitido, no inventa un mecanismo nuevo) — no hace falta ADR nuevo ni
`descongela=decisiones`, es la misma clase de extensión que ya hizo la tarea
45 con la regla de `Aplicacion/` ajena. Si tenés dudas sobre si algún caso
concreto es una excepción legítima, consultá al agente `arquitectura` antes de
escribir un `.ignoring()` — no lo agregues por conveniencia.

1. **Agregar la regla genérica** en `tests/Unit/ArquitecturaModulosTest.php`,
   mismo molde que la regla ya existente de `Aplicacion/` ajena (el bloque
   `$aplicacionAjena`/`arch(...)->not->toUse($aplicacionAjena)` dentro del
   `foreach ($modulos as $modulo)`): un módulo no puede usar
   `App\Dominios\{Otro}\Dominio` de ningún otro módulo. Antes de tocar nada
   más, corré esa regla sola contra el código actual y confirmá que **falla**
   por el cruce `Mantenimiento → Inventario\Dominio\Excepciones\StockInsuficiente`
   — es la evidencia de que el gate atrapa algo real, no un placebo. Documentá
   ese rojo en `runs/82.md` antes de seguir.
2. **Corregir el cruce real**: mové
   `App\Dominios\Inventario\Dominio\Excepciones\StockInsuficiente` a
   `App\Dominios\Inventario\Contratos\Excepciones\StockInsuficiente` (mismo
   patrón de ubicación que el resto de `Contratos/`: interfaces y DTOs
   públicos del módulo). Actualizá los cuatro puntos que la referencian
   (`EscrituraConsumoStock.php`, `RegistrarMovimientoStock.php`,
   `StockController.php`, `MaquinaEstadosOrdenMantenimiento.php`) y el
   docblock de `RepuestosInsuficientes.php`. `Inventario\Aplicacion` e
   `Inventario\Infraestructura` importando `Inventario\Contratos` (su propio
   módulo) no viola nada — la regla nueva y las existentes solo prohíben
   cruces *entre* módulos.
3. Corré la regla nueva de nuevo: tiene que estar en verde, sin ningún
   `.ignoring()` que tape este caso — la corrección es real, no una excepción
   documentada.
4. `./bin/verify` completo.

## Cómo repartir las etapas

- **Etapa 1**: agregar la regla, confirmar que falla en rojo contra el código
  actual (el paso 1 de arriba), documentar la evidencia.
- **Etapa 2**: aplicar la corrección (mover la excepción, actualizar las
  cinco referencias), confirmar la regla en verde, `bin/verify` completo.

Es chica — si el rojo se confirma rápido, puede cerrarse entera en la etapa 1.

## Qué NO hacer

- No tocar ninguna lógica de negocio de `Inventario` ni de `Mantenimiento` —
  el fix es de namespace/ubicación de una clase, no de comportamiento. Los
  tests existentes de ambos módulos tienen que seguir pasando sin editarlos
  (salvo el `use` de la excepción, si algún test la referencia por FQCN).
- No reabrir la regla ya resuelta de `Comercial ↔ Operaciones` (ver la
  sección "Dirección de dependencia" al final del archivo) — no es parte de
  esta tarea. Esa documentación pendiente es la tarea 83, no esta.
- No barrer el resto del árbol de módulos buscando más cruces de `Dominio/`
  más allá de los que el propio gate nuevo encuentre — si aparece otro cruce
  real al correr la regla, corregilo (es exactamente lo que esta tarea existe
  para hacer), pero no audites módulos donde el gate no encontró nada.
- No agregar un `.ignoring()` a la regla nueva para "resolverla rápido" —
  si un caso legítimo lo necesitara, es motivo para consultar al agente
  `arquitectura` antes, no para escribirlo solo.

## Criterio de aceptación

`./bin/verify` = 0, con la regla nueva de `Dominio/` ajena en verde, sin
ningún `.ignoring()` sobre el cruce `Mantenimiento`/`Inventario`, y con
evidencia en `runs/82.md` de que la regla, antes del fix, fallaba de verdad.

## Cierre obligatorio de cada etapa

`runs/82.estado` con una sola palabra (`PARCIAL`/`OK`/`BLOQUEADA`).
`runs/82.md` con la evidencia del rojo inicial y el fix aplicado — o, si
quedó `PARCIAL`, qué falta para la etapa siguiente. Al cerrar con `OK`,
`runs/82.pr.md` con título y cuerpo del PR.

## Commits

Agrupados por función, en español, imperativo, explicando el porqué: uno para
la regla nueva (con la evidencia de que atrapa el cruce real), otro para la
corrección (mover la excepción y actualizar las referencias). Sin trailer
`Co-Authored-By`.
