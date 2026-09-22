# Pedido del dueño — Pago por trabajo, tarifas base y el modelo del Trabajo (22/9/2026)

**Origen.** Sesión del 22/9/2026. El dueño retomó tres pendientes: el pago por día o por hectárea, el
modelo del Trabajo y lo abierto de siembra. Este documento consolida lo que dijo y lo que decidió.

**Estado.** Secciones 1 y 2 implementadas en `feature/pago-por-trabajo` (ADR 0023). La sección 3 es una
propuesta que espera decisión. La sección 4 entra en `feature/siembra-contrato-kpi`.

---

## 1. Lo que dijo

- «Actualmente está registrado el pago de un trabajo en su formulario de persona, pero eso es erróneo:
  el pago es por el trabajo.»
- «Una configuración de pago base como parte del módulo de finanzas, algo así como pago por default; en
  caso de que no se acepte se puede negociar con el trabajador por su trabajo puntual, ya sea por el tipo
  de terreno, si la fumigación es manual o con mapeo, si tiene que hacer voleo.»
- «Se tienen 2 tipos de pagos: por día o por hectárea; eso también tiene que figurar en mi configuración
  de pagos para el sistema.»

## 2. Decisiones tomadas (22/9/2026)

| Pregunta | Decisión |
|---|---|
| ¿A qué nivel se negocia cuando no se acepta el valor por defecto? | **Por equipo en la Orden de Trabajo**: tipo de trabajo (tarifa), modalidad y monto para piloto y para ayudante. Un solo lugar; el devengo copia y congela. |
| Si una persona hace el mismo día un trabajo por día y otro por hectárea | **Solo el jornal**: el día por jornal absorbe todo lo del día; lo por hectárea de esa fecha no se paga aparte (queda registrado, marcado como absorbido). |
| ¿Dónde vive el valor por defecto? | En **Financiero → Tarifas de pago** (`fin_tarifas`), con una predeterminada. |
| ¿El jornal exige un mínimo? | No se pidió: una sesión validada del día alcanza. |

Cómo quedó: ver ADR 0023.

## 3. El modelo del Trabajo — lo que el dueño describió y una propuesta

**Lo que pasa en el campo (palabras del dueño):** «En la orden de trabajo se dividen las hectáreas y
después entre los equipos se dividen los lotes; los equipos ven qué lotes realizan en cada trabajo. Si un
terreno es grande un equipo comienza adelante y el otro atrás o por los costados, pero para eso se
necesita definir los límites de los lotes, y eso a veces lo tenemos al final de cada trabajo. No tengo la
manera correcta de cómo registrarlo en el sistema.»

**Lectura:** la orden le asigna a cada equipo *cuántas hectáreas*; *qué lotes* (y qué parte de un lote
grande) se resuelve en el terreno y a veces se conoce recién al terminar. Hoy `ope_trabajos` es
equipo×lote con `lote_id` obligatorio, y el reparto automático (parejo / por dificultad) es el puente.

**Propuesta (sin implementar, decide el dueño):**

1. El **Trabajo pasa a ser «equipo + hectáreas asignadas»** dentro de la Orden de Trabajo: sin lote
   obligatorio al crearse. La condición de pago ya vive a ese nivel (ADR 0023).
2. Lo que hoy es el lote del trabajo pasa a una tabla de **cobertura** (`ope_trabajo_lotes`: trabajo,
   lote, hectáreas aplicadas, informado desde la app de campo o corregido en el panel). Las sesiones
   siguen colgando del trabajo; cada sesión puede declarar en qué lote(s) estuvo.
3. El acta y la factura se calculan desde la cobertura (hectáreas aplicadas por lote), no desde el
   `lote_id` del trabajo. `ope_actas.trabajo_id` (UNIQUE) no cambia.
4. Los límites imprecisos se resuelven **a posteriori**: un lote grande partido entre dos equipos se
   registra como dos filas de cobertura del mismo lote con las hectáreas que cada uno hizo; la suma no
   puede superar lo solicitado del lote en la orden.
5. Migración: los trabajos existentes conservan su `lote_id` como primera fila de cobertura.

Toca sincronización (la app manda cobertura, no solo sesiones), actas, informe de avance y reparto
automático. Conviene probar en campo una campaña con el modelo actual antes de cambiarlo.

## 4. Siembra — lo que entra ahora

El dueño eligió dos de los cuatro puntos abiertos el 21/9: **validar que el contrato no mezcle lotes de
distinto cultivo o etapa** y **KPI por cultivo** (listado de Cultivos + «dónde está sembrado» en su
ficha). Quedan afuera por ahora: la foto de la etapa en cada orden y el cruce con `tipo_aplicacion`.
