<!-- ciclo: critica=no turno-noche=1 rama=feature/frontera-contratos etapas=1 descongela=decisiones -->

# Tarea 83 — pegar la revisión pendiente del ADR 0003

## Por qué esta tarea

No es una HU ni una TE de `plan_sprints.md`: es deuda de documentación, no de
código — el código ya está. Mismo tipo de fila que 28-30/61/82, pero sin
tocar una sola línea de PHP.

La tarea 45 (HU-31, facturar desde el acta conformada) chocó con una regla
vieja de `tests/Unit/ArquitecturaModulosTest.php` (de TE-03, PR #9) que
bloqueaba a `Comercial` de usar *cualquier cosa* de `Operaciones`, sin
excepción para `Contratos/` — aunque `Contratos/` ya era, para ese momento del
proyecto, la frontera sancionada entre cualquier otro par de módulos (HU-16 la
había generalizado). Esa sesión consultó al agente `arquitectura`, angostó la
regla (`Operaciones\Aplicacion`/`Dominio`/`Infraestructura` bloqueados,
`Operaciones\Contratos` permitido) y aplicó el cambio de código — podés
confirmarlo hoy mismo en `tests/Unit/ArquitecturaModulosTest.php`, la sección
"Dirección de dependencia entre módulos concretos" al final del archivo, en
`develop` desde el PR #91.

Lo que **no** se aplicó fue la documentación: `descongela=decisiones` no
estaba declarado en esa tarea, así que el guardarraíl de turno noche bloqueó
la escritura sobre `docs/decisiones/0003-arquitectura-modular-clean-por-feature.md`
(correctamente — "los ADRs registran decisiones tomadas por una persona"). El
agente `arquitectura` dejó redactada la sección completa, formato "Revisión"
(mismo precedente que la ya existente del 27/8/2026 en ese mismo ADR), lista
para pegar. Sigue sin pegarse, documentado en `runs/45.md` como pendiente.

## Qué hacer

1. Confirmá primero que la regla que el texto describe sigue siendo la que
   hoy aplica `tests/Unit/ArquitecturaModulosTest.php` (la sección "Dirección
   de dependencia..." al final del archivo) — si algo cambió desde el 3/9/2026
   y el texto ya no describe el código real, no lo pegues tal cual: decilo en
   `runs/83.md` y ajustá la redacción a lo que el código hace hoy antes de
   pegarlo (la revisión tiene que ser fiel al comportamiento real, no un
   copy-paste ciego).
2. Si coincide (caso esperado), pegá el bloque siguiente **tal cual**, al
   final de `docs/decisiones/0003-arquitectura-modular-clean-por-feature.md`
   (después de la sección "### 6. Estado de la decisión", como último
   contenido del archivo — mismo lugar donde vive la revisión del 27/8/2026):

   ```markdown
   ## Revisión (3/9/2026) — Comercial lee Operaciones vía `Contratos/`, primera excepción a la regla direccional de TE-03

   **Contexto de la revisión.** Tarea 45 (HU-31, facturar un trabajo desde su acta conformada): el caso de uso vive en `Comercial` (el dinero que entra —clientes, contratos, ahora facturas— es de ese módulo, mismo criterio ya usado en HU-22/23/24), pero necesita leer si el acta de `Operaciones` está `firmada` y sus hectáreas conformadas para calcular el monto. Al seguir el patrón ya establecido por HU-16 (tarea 16) —un contrato de lectura en `Operaciones/Contratos/`, el mismo mecanismo que hoy usa `Finanzas` con `LecturaSesionValidada`— la implementación choca con una regla de `tests/Unit/ArquitecturaModulosTest.php` agregada en TE-03 (PR #9), cuando `Operaciones` recién existía como carpeta y el patrón `Contratos/` como frontera sancionada entre módulos concretos todavía no se había escrito (llegó recién con HU-16). Esa regla bloquea a `Comercial` de usar *cualquier cosa* de `Operaciones`, sin excepción para `Contratos/`, y nadie la había vuelto a revisar desde entonces.

   **Análisis.** La regla genérica del mismo archivo —aplicada a todo par de módulos, incluido este— ya prohíbe lo que ADR 0003 regla 2 vino a evitar: importar el modelo Eloquent ajeno. Esa regla genérica deja pasar el cruce por `Contratos/` a propósito, porque `Contratos/` es la frontera pública que el propio ADR sanciona para esto. La regla extra de TE-03, en cambio, no distinguía `Contratos/` del resto porque en ese momento del proyecto no había nada que distinguir: el mecanismo de contrato de lectura entre módulos concretos no existía todavía. Es una defensa correcta para su época, convertida en punto ciego después de que ese patrón se generalizara — hoy `Sincronizacion` ya lee `Operaciones` vía `LecturaOrdenesVigentes` y `Finanzas` vía `LecturaSesionValidada`, sin que ninguna regla equivalente se los impida.

   **Decisión.** Se angosta la regla de TE-03: en vez de bloquear el módulo `Operaciones` completo, `Comercial` no puede usar `Operaciones\Aplicacion`, `Operaciones\Dominio` ni `Operaciones\Infraestructura` — pero sí puede usar `Operaciones\Contratos` (interfaces + DTOs, nunca Eloquent). No es un mecanismo nuevo: es el mismo cruce síncrono que ya usan `Sincronizacion` y `Finanzas` para leer `Operaciones`, aplicado por primera vez a este par de módulos. La asimetría original de TE-03 queda intacta donde importa de verdad: `Operaciones` sigue sin poder referenciar nada de `Comercial` salvo IDs planos por FK (regla 3 del ADR). Lo único que cambia es que la lectura síncrona por contrato —ya sancionada por la regla 2 para cualquier otro par de módulos del sistema— deja de estar vetada específicamente para este par.

   **Alternativas descartadas.**

   - *Mover `Factura` a `Operaciones`*: contradice el criterio ya asentado de que el dinero que entra —clientes, contratos, facturas— vive en `Comercial` (HU-22/23/24); además `com_facturas` referencia `com_contratos` en su FK principal, con prefijo `com_` (ADR 0011), no `ope_`.
   - *Evento asíncrono en vez de lectura síncrona*: la regla 2 del ADR ya distingue los dos mecanismos por el tipo de necesidad —evento cuando es consecuencia de algo que ya ocurrió (`SesionValidada` → devengo), contrato síncrono cuando se necesita una respuesta en el momento—. Emitir una factura es una acción directa del usuario que necesita el estado del acta en el instante del clic, no una reacción tardía a un evento pasado; forzar un evento acá inventaría asincronía donde el ADR ya prevé el mecanismo correcto.
   - *Dejar la regla de TE-03 como está y que `EmitirFactura` reconstruya la cadena acta→trabajo→orden→contrato con SQL propio dentro de `Comercial`*: es exactamente la "lógica cruzada" que la regla 3 del ADR prohíbe (referencias por ID sí, lógica cruzada no); no resuelve el problema, lo esconde detrás de una consulta duplicada que nadie mantiene sincronizada con `Operaciones` si su modelo cambia.

   **Consecuencias.** `tests/Unit/ArquitecturaModulosTest.php` reemplaza la regla `Comercial no usa nada de Operaciones` por una que solo bloquea `Operaciones\Aplicacion`, `Operaciones\Dominio` y `Operaciones\Infraestructura`, dejando pasar `Operaciones\Contratos`. Queda como default del sistema, desde esta revisión: entre cualquier par de módulos concretos, `Contratos/` es la frontera sancionada salvo que una regla de arquitectura documente explícitamente, con su razón, por qué ese par necesita algo más estricto.
   ```

## Qué NO hacer

- No reescribas ni "mejores" el texto — ya fue redactado y verificado por el
  agente `arquitectura` contra el código real en su momento. Si de verdad no
  coincide más con el código actual, seguí el paso 1 (ajustar a lo real) en
  vez de inventar una redacción nueva desde cero.
- No toques ninguna otra sección del ADR 0003 (ni la revisión del 27/8/2026,
  ni el cuerpo original).
- No toques código. Esta tarea es puramente de documentación.
- No repitas esta corrección para la tarea 82 (aduana de `Dominio/`) — esa es
  una regla nueva sin decisión previa que documentar; esta tarea es solo la
  constancia escrita de una decisión ya tomada y ya aplicada.

## Criterio de aceptación

`./bin/verify` = 0, y
`grep -q "Comercial lee Operaciones vía" docs/decisiones/0003-arquitectura-modular-clean-por-feature.md`
devuelve éxito.

## Cierre obligatorio

`runs/83.estado` = `OK` (es una tarea de una sola etapa; si por algo quedara
sin cerrar, `BLOQUEADA` con el motivo). `runs/83.md` con la confirmación de
que el texto coincide con el código actual (o el ajuste que le hiciste, con el
porqué). `runs/83.pr.md` con título y cuerpo del PR.

## Commits

Uno solo: "documenta la revisión de ADR 0003 pendiente desde la tarea 45", en
español, imperativo. Sin trailer `Co-Authored-By`.
