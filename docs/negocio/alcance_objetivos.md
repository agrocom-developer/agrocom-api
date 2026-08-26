# Objetivos del proyecto y alcance ajustado por el campo

**Agrocom SRL · Documento oficial vigente · Derivado de las respuestas de campo del 25/8/2026**

La especificación (`docs/especificacion/especificacion_funcional_tecnica.md` §1) define el alcance funcional en doce funciones; este documento fija **para qué existe el sistema** en términos del negocio y ajusta el alcance con lo que las respuestas de campo confirmaron o corrigieron. Cuando ambos documentos difieran, la especificación manda en el *qué técnico*; este manda en el *por qué* y la prioridad.

---

## 1. Objetivo general

Convertir cada ventana volable en **hectáreas aplicadas, validadas, documentadas y cobradas**, con un registro que:

1. le pague a cada persona exactamente lo que trabajó,
2. le demuestre al cliente exactamente lo que recibió, y
3. le muestre al dueño exactamente dónde se gana y se pierde la plata.

El campo lo confirmó con sus propias palabras: la evidencia del RC ya zanja las discusiones de hectáreas ("ninguna discusión, porque se sacan capturas"), lo que falta es **estructurar lo que hoy vive en WhatsApp, Excel y la memoria** — y capturar lo que hoy no se registra en ningún lado (las pausas y sus causas).

## 2. Objetivos específicos (medibles)

| # | Objetivo | Situación hoy (según el campo) | Meta con el sistema |
|---|---|---|---|
| O-01 | Trazabilidad por hectárea: cada ha aplicada reconstruible (orden → mezcla → sesión → evidencia) | Capturas de RC sueltas en celulares + nube DJI | 100% de sesiones cerradas con captura y datos estructurados |
| O-02 | Pago justo por sesión validada | Cálculo manual, sin registro central; anticipos de memoria | Devengo automático al validar; cuenta corriente por persona |
| O-03 | Registrar toda pausa con causa atribuible (clima / cliente / falla / logística) | "La información que falta siempre" — no se registra nada | Toda jornada con horas de vuelo y pausas explicadas |
| O-04 | Reporte al cliente sin armado manual | PDF manual de la nube DJI + capturas por WhatsApp, se arma al pasar ~500 ha | PDF técnico automático al conformar; comercial por aplicación |
| O-05 | Costos visibles por rubro, dron y lote | Excel del encargado (descripción, categoría, monto) | Estado de resultados básico y costo Bs/ha en dashboard |
| O-06 | Repuesto crítico nunca frena la flota más de lo inevitable | Dron parado 24–48 h por falta de pieza; envíos por trufi | Alerta de stock mínimo por base + pedido con estados y tiempos |
| O-07 | Responsabilidad de la mezcla siempre asignada | "La responsabilidad cae en quien preparó la calda" — pero no queda registrado quién | Toda mezcla registra quién la preparó (cliente o Agrocom) |
| O-08 | Decisiones de clima forzadas siempre firmadas | Se decide a voz entre piloto, cliente y agrónomo | Condición fuera de rango solo con autorización firmada |

## 3. Alcance v1 — ratificado y ajustado

**Se ratifica** el alcance de las doce funciones de la especificación §1. Las respuestas de campo **agregan o refuerzan** dentro de v1:

- **Registro de pausas con causa atribuible** (nuevo — el hallazgo más repetido: demoras del cliente con agua/químicos, cambios de lote ordenados, clima).
- **Mezcla con dos escenarios**: preparada por el cliente (dominante hoy) o por Agrocom — el módulo de mezcla de la app del auxiliar debe cubrir ambos, no solo el segundo.
- **Actor "encargado de la propiedad"** del lado del cliente (indica lotes, prepara caldo, ordena pausas) como contacto operativo del contrato.
- **Ventanas horarias y límites de condiciones como parámetros por contrato/orden** (clientes que permiten todo el día vs. clientes que exigen parar; velocidad máxima impuesta).
- **Flota real**: incluir T30 (y la carga por modelo que relató el campo) además de T50/T70/T100.
- **Checklist de cierre de jornada** (limpieza del dron, lavado de tanque) como mantenimiento diario operativo.

**Se ratifica fuera de alcance en v1**: contabilidad formal con plan de cuentas, integración con la API de DJI (las hectáreas siguen siendo declaradas + captura del RC), facturación electrónica. **Se agrega fuera de alcance v1**: integración con pronóstico meteorológico (deseo del campo, queda anotado), papel hidrosensible como flujo formal de verificación (deseo del agrónomo, se admite como evidencia adjunta), gestión multi-cliente comercial completa (los trabajos spot existen y el modelo los admite, pero v1 opera un contrato residente principal).

## 4. Criterios de éxito de la primera campaña con sistema

1. Ninguna sesión validada sin captura de RC adjunta.
2. Ningún devengo calculado a mano.
3. Toda jornada de baja producción tiene sus pausas explicadas con causa.
4. El reporte técnico de un lote sale del sistema en minutos, no en horas de armado manual.
5. La planilla mensual cuadra exacta contra las sesiones validadas — recalculable desde el origen.
6. Ante un reclamo de eficacia del cliente, la cadena completa del lote (orden → mezcla → condiciones → sesiones → evidencia) se reconstruye en una sola consulta.
