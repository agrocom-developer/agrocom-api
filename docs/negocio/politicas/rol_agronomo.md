# Políticas y lógica de negocio — Rol: Agrónomo (cliente)

**Agrocom SRL · Documento derivado de respuestas de campo del 25/8/2026 · Insumo para actualizar `docs/especificacion/especificacion_funcional_tecnica.md`**

**Fuentes y su sesgo — leer antes de usar este documento:**

| Fuente | Campañas | Quién es de verdad | Sesgo a descontar |
|---|---|---|---|
| "carlos" (Carlos Ferrufino) | 3 | **Dueño de Agrocom**, que también actúa de agrónomo/asesor | Respuesta más completa; trae la doble visión dueño+técnico. Mezcla criterios del aplicador (gasolina, repuestos) con los del agrónomo. No es la voz del cliente. |
| Abraham Gutiérrez Contreras | 4 | Piloto/auxiliar experimentado — él mismo aclara *"no soy agrónomo"* | Perspectiva operativa valiosa (qué se ve en el RC, deriva, sectores), pero **no** voz del cliente ni criterio agronómico formal. |

> **Estado: PENDIENTE DE VALIDACIÓN con un agrónomo de un cliente real.** Ninguno de los dos respondientes es EL agrónomo del cliente. Todo lo que aquí figura como política se sostiene como "mejor conocimiento disponible al 25/8/2026" y debe confirmarse en la reunión de cierre y/o con el agrónomo del contrato v1.

Las citas se transcriben con ortografía normalizada; los originales (ortografía libre) están en `docs/gestion/respuestas_campo/Cuestionario Agrónomo (Respuestas) - Respuestas de formulario 1.csv`.

---

## 1. Misión del rol y límites

El agrónomo es la figura clave del lado del cliente (`ventana_al_negocio.md` §2.3): la relación diaria del negocio es Agrocom ↔ agrónomo. Su misión, según la especificación y confirmada por las respuestas:

- **Decide cuándo se aplica**: la orden de aplicación nace de su lectura del cultivo y la presión de plaga/enfermedad. Sin orden vigente, el sistema no permite abrir un trabajo (espec §4.3, `trabajos`).
- **Define el producto, la dosis y los litros de caldo por hectárea** — la receta es suya; Agrocom la ejecuta y la documenta, no la modifica (espec §4.3, `receta_items`; ventana §5).
- **Define la receta y su orden de incorporación** — con el matiz descubierto en campo: hoy ese orden no viaja en un documento formal propio (ver G-03).
- **Autoriza aplicar fuera de rango de condiciones**, y esa autorización queda firmada como observación (espec §5, transición `→ autorizado_con_observacion`; `condiciones.firma_observacion`).
- **Firma el acta de conformidad** por lote, que es lo que convierte hectáreas validadas en hectáreas cobrables (espec §5, `→ conformado`).
- **Cuando la aplicación falla, lidera la investigación**: *"el agrónomo debe hacerlo, él dio el caldo"* (carlos).

**Límite duro del rol, confirmado por ambas fuentes:** frente al dron, la última palabra en todo lo que ponga en riesgo al equipo la tiene **el piloto**, no el agrónomo. *"¿Quién define los sectores que no se aplican? El agrónomo o dueño de la propiedad en el caso del terreno. Y en el caso que sea peligroso para el dron, el piloto"* (carlos). Los parámetros técnicos de vuelo (ancho de franja, altura, velocidad) son dominio de la aplicadora: *"[que decida sola] el ancho de banda, cuántos metros de ancho le dan al dron"* (Abraham).

---

## 2. Flujo base (cómo trabaja una aplicación, según las respuestas)

1. **Decisión de aplicar, con ~2 días de anticipación.** *"Al menos 2 días antes, porque se debe ver el clima, lluvia, viento, etc. — ese es el primer parámetro. Si todo está ok, se tiene que ver si tendremos gasolina disponible, si no tenemos problemas con algún repuesto. Si todo va ok, podemos iniciar"* (carlos). Ojo con el sesgo: gasolina y repuestos son checklist del **aplicador**, no del agrónomo del cliente — carlos responde con las dos gorras puestas. El dato aprovechable: existe una **ventana de preaviso de ~2 días** mirando clima, que matiza el "entre el disparo y el inicio hay horas, no días" de `ventana_al_negocio.md` §3 (ver contradicción C-7).
2. **Entrega de la orden como "dosis por cada 100 litros".** *"Cantidad de dosis por cada 100 litros, para que sea más fácil; por lo general con 10 litros de caldo por hectárea, así que ese caldo se usa para las 10 primeras hectáreas, de ahí se hace el tema de más litraje"* (carlos). Es decir: la unidad de trabajo mental del rol es **dosis/100 L con caldo de referencia de 10 L/ha** — 100 L de caldo = 10 ha. La espec ya soporta la unidad (`receta_items.dosis_unidad: ml/100L`) y la fórmula de §7.1 la convierte por tanque.
3. **El orden de incorporación viene (o no) dentro de la orden de aplicación.** *"No lo hacemos; normalmente la orden de mezcla la define la orden de aplicación del agrónomo de la propiedad"* (carlos). No hay documento formal separado de orden de mezcla.
4. **Ejecución** — del lado Agrocom: trabajo, sesiones, mezclas, recargas (espec §4.3, §7). El agrónomo no interviene salvo consulta (desvío de dosis, condiciones al límite).
5. **Verificación post-aplicación: visual inmediata + revisión a las 24 horas.** *"Por la parte visual: que no haya deriva, no se trancaron la bomba o las centrífugas, que el caldo no se asentó, que no haya habido mucho viento. Y después de 24 horas normalmente se ve si la aplicación es buena"* (carlos). Deseo explícito: *"sería interesante tener la opción de papel hidrosensible como muestras para cada lote"*. Abraham verifica por resultado agronómico: *"en la cantidad de cultivo que sacás, en la propia hoja del cultivo que está en buen estado"*.
6. **Acta y reporte.** Firma del acta (pantalla o papel fotografiado, indistinto) y reporte técnico cuya pieza central pedida es el screen del RC (ver G-11 y §7 de este documento).

---

## 3. Políticas y reglas de negocio

| # | Política | Fuente |
|---|---|---|
| **G-01** | **La orden de aplicación la emite el agrónomo del cliente y es requisito previo para volar.** Sin orden vigente no se abre trabajo. | Espec §4.3 y §5; confirmado implícitamente por ambas respuestas (nadie disputa la titularidad). |
| **G-02** | **El formato natural de la orden es "dosis por cada 100 litros", con caldo de referencia de 10 L/ha.** El sistema debe aceptar y mostrar la dosis en esa unidad como primera opción, y convertirla por tanque automáticamente (espec §7.1). | carlos, resp. a "¿cómo entregás hoy la orden?". |
| **G-03** | **El orden de incorporación de la mezcla pertenece al agrónomo, pero hoy NO lo define un documento formal propio**: *"la orden de mezcla la define la orden de aplicación del agrónomo de la propiedad"* — viene embebido en la orden, o no viene. Cuando no viene, aplica el orden por defecto de espec §7.2 (configurable, sobrescribible por el agrónomo) — **pendiente confirmar que el cliente acepta ese default** (vacío C-5). | carlos; espec §7.2. |
| **G-04** | **Límites de condiciones declarados por el rol: viento < 17 km/h; temperatura < 40 °C; humedad 80–95 %.** Abraham coincide en lo esencial (*"menos de 18 km/h... mayormente siempre menos de 40 grados, no en todos los casos"*) y agrega que **el límite térmico depende del producto**. ⚠️ La temperatura **difiere** del límite operativo escrito en `ventana_al_negocio.md` §4.1 (≤ 30 °C) y la humedad difiere del "< 90 %" de la misma fuente — ver contradicciones C-1 y C-2. El viento (≤ 17 km/h) queda CONFIRMADO. | carlos + Abraham; ventana §4.1. |
| **G-05** | **Autorización fuera de rango: la especificación exige observación firmada por el agrónomo** (`→ autorizado_con_observacion`). carlos confirma al decisor: *"el agrónomo de la propiedad"*. Abraham contradice: *"el dueño, siempre el dueño, porque él directamente sería afectado si se llega a perder su cultivo"*. **La regla de la espec se mantiene vigente hasta la reunión de cierre** (conflicto de matriz, C-3). | carlos vs. Abraham; espec §5. |
| **G-06** | **Sectores excluidos: los define el agrónomo o el dueño del terreno; el piloto los define unilateralmente cuando hay riesgo para el dron.** Además existe una tercera fuente de exclusión que nadie "decide": la **geocerca DJI (zona Geo)**, *"donde no se puede permitir el vuelo con dron"* (Abraham), más viviendas cercanas (riesgo de intoxicación) y franjas de seguridad. | carlos + Abraham; consistente con ventana §7 (fila "Terreno"). |
| **G-07** | **Ante un desvío entre dosis ordenada e incorporada, la acción esperada es "consultar con el agrónomo"** — no solo la alerta interna al encargado (espec §10). El porqué operativo lo da Abraham: *"si es menos no llega a controlar la enfermedad o plagas, y si es más puede llegar a quemar tu cultivo"*. El circuito de resolución del desvío debe incluir al agrónomo como destinatario. | carlos + Abraham. |
| **G-08** | **La deriva es responsabilidad que se resuelve por acuerdo entre dueños** — dueño afectado, cliente y dueño de Agrocom — con riesgo legal real: *"normalmente esos casos directamente te denuncian por daños y perjuicios; tratar de resolver sería entre los dueños y el dueño del dron, tratar de llegar a un acuerdo"* (Abraham). carlos confirma que ocurrió: *"sí, algunas veces con algunas otras siembras, por temas de viento"*. La defensa es **preventiva**: restricciones del lote declaradas antes + condiciones de viento registradas al aplicar (ventana §7). | Abraham + carlos; ventana §7. |
| **G-09** | **La investigación de una aplicación que no controló la plaga la lidera el agrónomo** (*"él dio el caldo"* — carlos), **y la evidencia que deslinda responsabilidad sale del control remoto**: *"todo eso se puede ver en el control remoto: qué cantidad de gotas usó, ancho de banda, velocidad, altura, litros por hectárea — entonces es algo fácil de saber si fue el piloto o el producto usado"* (Abraham). Confirma el principio de los tres sospechosos (producto, dosis, aplicación — ventana §5) y que la captura del RC es la pieza probatoria central. | carlos + Abraham; ventana §5; espec §6. |
| **G-10** | **La verificación de calidad tiene dos momentos: visual inmediata y revisión a las 24 horas.** El **papel hidrosensible por lote** es una opción de verificación deseada (hoy no existe en el proceso ni en la espec). | carlos. |
| **G-11** | **El acta la firma el agrónomo, en pantalla o en papel fotografiado — "cualquiera de las dos".** Momento: la espec dice inmediatamente después del lote; carlos flexibiliza: *"puede ser ese día o al terminar la aplicación completa de los lotes, para que aprovechemos la ventana de aplicación"*. **Flexibilización DESCUBIERTA que toca la decisión comercial más importante del diseño** (ventana §2.2) — no se aplica sin decisión explícita (ver C-6). | carlos; ventana §2.2; espec §5. |
| **G-12** | **Desvío aceptable entre mezcla ordenada e incorporada: ±5 %** — la propuesta de la espec fue aceptada tal cual (*"-+5%"*). Cierra el supuesto de espec §16. | carlos. |
| **G-13** | **El solape entre pasadas es casi nulo con el DJI Agras**: *"normalmente la franja es de 9 metros, pero pocas veces se solapa con el dron DJI Agras; es un dato muy fino"*. La tolerancia de solape (espec §5, validación de suma; §16) puede calibrarse **baja**; el valor numérico exacto queda pendiente. | carlos. |
| **G-14** | **Los parámetros de vuelo son decisión exclusiva de la aplicadora** (franja/ancho de banda, altura, velocidad); el cliente no los ordena, pero **sí espera verlos en el reporte** (ver §7). | Abraham; consistente con espec §3 (el agrónomo no tiene permisos operativos). |
| **G-15** | **El número de aplicaciones por campaña varía por cultivo**: *"algunos cultivos se aplican más de 5 por temas de enfermedades y plagas, otros incluso hasta más por falta de nutrientes en el suelo"* (Abraham) — consistente con las 6–8 de ventana §3; el modelo (`contratos.aplicaciones_previstas`, `nro_aplicacion`) ya lo soporta. | Abraham. |

---

## 4. Excepciones y casos reales

- **La aplicación que no controló la plaga.** Los tres sospechosos de siempre (producto, dosis, aplicación). Perspectiva de campo: *"normalmente es por los productos no tan buenos; el dron en buen estado y un piloto que sepa solo cumple la tarea... muy pocas veces es por fallas del piloto"* (Abraham). Lo decisivo: **la exoneración del piloto se demuestra con los datos del RC** (gotas, franja, velocidad, altura, L/ha) — exactamente la cadena de trazabilidad de espec §6. Quien investiga es el agrónomo, porque la receta era suya (carlos). Vacío: carlos no reportó qué información *le faltó* en investigaciones pasadas — repreguntar en persona.
- **Deriva con siembras vecinas.** Ocurrió *"algunas veces, con algunas otras siembras, por temas de viento"* (carlos). Escenario extremo real: denuncia por daños y perjuicios, resuelta por acuerdo entre dueños (Abraham). Refuerza el valor de registrar restricciones del lote **antes** y condiciones de viento **al aplicar** — la defensa es preventiva, no reactiva.
- **Condiciones al límite con la ventana apretando.** carlos: decide el agrónomo de la propiedad. Abraham: decide el dueño del cultivo. La espec resuelve el empate con la firma: quien autoriza fuera de rango, firma la observación — pero **quién es esa persona del lado del cliente quedó en conflicto** (C-3). Nota: ninguna de las dos respuestas confirmó explícitamente la disposición a *firmar* la autorización — repreguntar.

---

## 5. Números de calibración y cierres de supuestos (espec §16)

| Supuesto / parámetro | Valor en la espec | Respuesta de campo (25/8/2026) | Estado del supuesto |
|---|---|---|---|
| Tolerancia de desvío de mezcla | Propuesta ±5 %, a validar con el agrónomo | *"-+5%"* — aceptado sin objeción (carlos) | **CERRADO: ±5 %** (pendiente ratificar con agrónomo de cliente real) |
| Formato de firma del agrónomo | Firma en pantalla o foto del acta física | *"Cualquiera de las dos"* (carlos) | **CERRADO: ambas modalidades, indistinto** |
| Tolerancia de solape entre sesiones | Parámetro configurable, a definir con experiencia de campo | *"La franja es de 9 metros, pocas veces se solapa con el DJI Agras, es un dato muy fino"* (carlos) | **PARCIALMENTE CERRADO**: tolerancia baja; falta el valor numérico (proponer en reunión, p. ej. 1–2 %) |
| Momento del acta de conformidad | Por lote, inmediatamente después de aplicado (ventana §2.2, espec §5) | *"Puede ser ese día o al terminar la aplicación completa de los lotes para aprovechar la ventana"* (carlos) | **EN DISPUTA** — flexibilización DESCUBIERTA, decisión comercial pendiente (C-6) |
| Litros de caldo por hectárea (referencia) | `litros_ha` por orden, sin default documentado | **10 L/ha** como estándar de trabajo (carlos) | Nuevo default de calibración |
| Unidad preferida de dosis | Múltiples unidades en `receta_items.dosis_unidad` | **Dosis por cada 100 L** como formato natural (carlos) | Calibración de UX: unidad por defecto |
| Ancho de franja (pasada) | No parametrizado | **9 m** (carlos) | Dato para validación de coherencia ha/recorrido |
| Límite de viento | ≤ 17 km/h (ventana §4.1) | < 17 km/h (carlos); < 18 km/h (Abraham) | **CONFIRMADO ~17 km/h** |
| Límite de temperatura | ≤ 30 °C (ventana §4.1) | **< 40 °C** (carlos y Abraham; "depende de lo que apliques") | **EN DISPUTA** (C-1) |
| Humedad | < 90 % (ventana §4.1); `humedad_minima` por orden (espec §4.3) | **80–95 %** (carlos) | **EN DISPUTA / a precisar** (C-2) |
| Anticipación del aviso de aplicación | "Horas, no días" entre disparo e inicio (ventana §3) | **~2 días** de preaviso mirando clima (carlos) | Matiz a conciliar (C-7) |
| Verificación post-aplicación | No modelada | Visual inmediata + **revisión a las 24 h**; deseo de **papel hidrosensible por lote** | DESCUBIERTO — candidato a v2 |

---

## 6. Clasificación contra la especificación

| # | Hallazgo | Clasificación | Sección espec/ventana | Impacto |
|---|---|---|---|---|
| 1 | Orden de aplicación emitida por el agrónomo, requisito para volar | **CONFIRMADO** | Espec §4.3, §5 | Ninguno |
| 2 | Dosis por 100 L soportada y preferida; caldo de referencia 10 L/ha | **CONFIRMADO** (con matiz de UX) | Espec §4.3 (`dosis_unidad`), §7.1 | Poner "dosis/100 L" como unidad por defecto en la pantalla de recetas |
| 3 | Orden de incorporación: sin documento formal propio; viene en la orden de aplicación o no viene | **DESCUBIERTO** | Espec §4.3 (`recetas_mezcla`), §7.2 | La captura de la receta debe tolerar orden ausente → default §7.2 con confirmación del agrónomo; agregar a espec §16 hasta cerrar C-5 |
| 4 | Límite de viento ~17 km/h | **CONFIRMADO** | Ventana §4.1; espec §5 (guardas) | Ninguno |
| 5 | Límite de temperatura < 40 °C (vs. ≤ 30 °C escrito) | **CORREGIDO** (pendiente decisión) | Ventana §4.1; espec §5 (transición `→ autorizado`) | Ver C-1: posible modelo de **dos umbrales** (operativo Agrocom vs. agronómico del cliente). No tocar la guarda hasta la reunión |
| 6 | Humedad 80–95 % (vs. "< 90 %" y vs. `humedad_minima` solo-mínimo) | **CORREGIDO** (pendiente decisión) | Ventana §4.1; espec §4.3 (`ordenes_aplicacion.humedad_minima`) | Evaluar `humedad_maxima` además de mínima; cerrar en C-2 |
| 7 | Autoriza fuera de rango el agrónomo (carlos) / el dueño (Abraham) | **CONFIRMADO por una fuente, en conflicto con la otra** | Espec §5 (`autorizado_con_observacion`) | Regla vigente se mantiene; conflicto C-3 a reunión |
| 8 | Sectores excluidos: agrónomo/dueño del terreno + piloto ante riesgo del dron | **CONFIRMADO** | Ventana §7 ("Terreno"); espec §4.1 (`lotes.restricciones`) | Ninguno |
| 9 | Geocerca DJI (zona Geo) como exclusión técnica impuesta por el fabricante | **DESCUBIERTO** | Espec §4.1 (`lotes.restricciones`) | Agregar "zona Geo DJI" como motivo tipificado de superficie no aplicada / restricción de lote |
| 10 | Desvío de dosis → consultar con el agrónomo (no solo alerta interna) | **DESCUBIERTO** | Espec §10 (alerta "Desvío de mezcla") | El circuito de la alerta debe contemplar notificación/consulta al agrónomo, no solo al encargado |
| 11 | Deriva: acuerdo entre dueños; riesgo de denuncia por daños y perjuicios | **CONFIRMADO** (con matiz legal) | Ventana §7 ("Deriva a vecinos") | Refuerza el peso probatorio de `condiciones` y `lotes.restricciones` |
| 12 | Investigación de eficacia la lidera el agrónomo; el RC deslinda al piloto | **CONFIRMADO** | Ventana §5; espec §6 | Ninguno — valida el diseño de trazabilidad |
| 13 | Verificación a las 24 h + papel hidrosensible como muestra por lote | **DESCUBIERTO** | No modelado (espec §9, `evidencias`) | Candidato: nuevo tipo de `evidencia` (papel hidrosensible) y un hito opcional de revisión a 24 h — decidir si entra en v1 o se anota para después |
| 14 | Reporte técnico: pieza central = screen del RC con hectáreas, recorrido y parámetros de vuelo (altura, franja, velocidad, gotas, L/ha) | **DESCUBIERTO** (ampliación) | Espec §9 (reporte técnico) | El contenido obligatorio del reporte técnico no lista hoy los parámetros de vuelo — agregarlos (salen de la misma captura de RC ya exigida) |
| 15 | ±5 % de desvío de mezcla aceptado | **CONFIRMADO** | Espec §10, §16 | Cierra supuesto §16 |
| 16 | Firma: "cualquiera de las dos" (pantalla o papel fotografiado) | **CONFIRMADO** | Espec §16, `evidencias.tipo: firma_acta` | Cierra supuesto §16 |
| 17 | Solape casi nulo, franja 9 m | **CONFIRMADO** (dirección) | Espec §5 (validación de suma), §16 | Tolerancia de solape configurable en valor bajo; número final pendiente |
| 18 | Acta: "ese día o al terminar la aplicación completa de los lotes" | **DESCUBIERTO** (flexibilización) | Ventana §2.2; espec §5 (`→ conformado`) | ⚠️ Toca la decisión comercial central del diseño. Proponer: mantener acta por lote como regla, admitir firma diferida en tanda al cierre del día/aplicación como excepción registrada. **No aplicar sin decisión del dueño en reunión** (C-6) |
| 19 | Preaviso de aplicación ~2 días | **DESCUBIERTO** (matiz) | Ventana §3 | Compatible si se lee como: decisión ~2 días antes, orden formal dispara con horas. Conciliar redacción (C-7) |
| 20 | Portal: "resultados de tus aplicaciones y puntualidad" | **CONFIRMADO parcial** | Espec §9 (reporte comercial incluye cumplimiento de ventana), §13 | "Resultados" (eficacia agronómica) no está en el portal — se cubriría con el hallazgo 13 (papel hidrosensible/revisión 24 h) si se decide incorporarlo |

**Nota de procedimiento:** los ajustes propuestos no contradicen ningún ADR existente, con una salvedad: qué acciones de escritura gana el agrónomo (emitir orden, firmar acta) roza el modelo de guards y permisos del portal (ADR 0004) y se decide en la reunión de cierre. La actualización de espec §4.3, §5, §9, §10 y §16 va **después** de esa reunión, no antes.

---

## 7. Oportunidades de automatización / sistematización

1. **Reporte técnico armado sobre el screen del RC.** Es literalmente lo único que carlos pidió del reporte: *"el screen de la pantalla del RC, donde muestre la cantidad de hectáreas aplicadas, el recorrido y los otros parámetros"*. La captura ya es obligatoria al cerrar sesión (espec §8) — la oportunidad es extraer/transcribir junto a ella los parámetros que Abraham enumera (gotas, ancho de franja, velocidad, altura, L/ha) para que el reporte deslinde responsabilidad sin que nadie redacte nada.
2. **Portal con resultados y puntualidad.** Las dos respuestas al portal apuntan a lo mismo: *"resultados de tus aplicaciones y puntualidad, lo que todos quisieran"* (Abraham). La puntualidad (cumplimiento de ventana) ya está en el reporte comercial — exponerla como indicador visible del portal es costo casi cero y es exactamente lo que hace recomendar el servicio (*"puntualidad y fumigación buena"*).
3. **Registro de condiciones con autorización firmada en el momento.** El flujo ya está modelado (`condiciones` + `firma_observacion`); la oportunidad es que la firma fuera de rango sea un gesto de segundos en el dispositivo del piloto, para que la protección exista de verdad cuando la ventana aprieta.
4. **Cálculo de mezcla nativo en dosis/100 L.** Como el rol piensa en dosis/100 L y 10 L/ha, la app del auxiliar debe aceptar la receta en ese formato y convertir por tanque sola (la fórmula de espec §7.1 ya lo contempla) — elimina la aritmética manual que hoy hace el campo ("ese caldo se usa para las 10 primeras hectáreas...").
5. **Papel hidrosensible como evidencia fotografiable por lote** + recordatorio de revisión a las 24 h: convertiría la verificación de eficacia (hoy puramente visual y de memoria) en evidencia adjunta al lote. Candidato a fase posterior a v1.

---

## 8. Qué usa este rol y en qué superficie

| Interacción | Superficie | Estado en la espec |
|---|---|---|
| Emitir la orden de aplicación (producto, dosis/100 L, L/ha, humedad, restricciones) | Hoy: informal (formato exacto sin confirmar — C-4). Sistema: carga por el panel o transcripción por Agrocom | Espec §4.3 — pendiente definir quién la transcribe al sistema |
| Ver reportes técnicos por lote, reporte comercial, actas, historial, hectáreas vs. contratadas | **Portal del cliente** (solo lectura, solo su contrato) | Espec §13 — confirmado como lo que el rol quiere ver (+ puntualidad y resultados, §7) |
| Firmar el acta de conformidad | **Pantalla del dispositivo de Agrocom** (el piloto/jefe la presenta) **o papel fotografiado** — indistinto (G-11) | Espec §8 (`POST /api/actas/{id}/firmar`), `evidencias.tipo: firma_acta` |
| Firmar la autorización fuera de rango | Dispositivo de campo de Agrocom, en el momento (`condiciones.firma_observacion`) | Espec §4.3, §5 — quién firma del lado cliente en disputa (C-3) |
| Ser consultado ante desvío de dosis | Hoy: llamada/WhatsApp. Sistema: notificación (G-07) | No modelado — DESCUBIERTO (hallazgo 10) |

El agrónomo **no** opera la app de campo ni tiene permisos operativos (espec §3): sus superficies son el portal (lectura), la firma presencial en dispositivo ajeno o papel, y el canal de consulta.

---

## 9. Contradicciones y vacíos para la reunión de cierre

- **C-1 — Límite de temperatura: 30 °C (ventana §4.1) vs. 40 °C (ambas respuestas).** Hipótesis a plantear (no asumida): son **dos umbrales de naturaleza distinta** — 30 °C como límite operativo interno de Agrocom (salud de baterías, evaporación/deriva) y 40 °C como límite agronómico dependiente del producto (*"depende de lo que apliques"* — Abraham). Si es así, la guarda `→ autorizado` debería evaluar el más restrictivo y la banda 30–40 °C caería en `autorizado_con_observacion`. **Decidir en reunión; hasta entonces rige lo escrito (≤ 30 °C).**
- **C-2 — Humedad: rango 80–95 % (carlos) vs. "< 90 %" (ventana) vs. solo `humedad_minima` (espec §4.3).** ¿El límite es mínimo, máximo o rango? ¿Se agrega `humedad_maxima` a la orden?
- **C-3 — Quién autoriza fuera de rango del lado del cliente: agrónomo (carlos, espec) vs. dueño del cultivo (Abraham).** Y ninguno confirmó explícitamente la disposición a **firmar** esa autorización. Definir titular y suplente de la firma.
- **C-4 — Formato definitivo de la orden de aplicación.** Se capturó la unidad preferida (dosis/100 L) pero no el soporte actual (¿papel, WhatsApp, verbal?) ni el formato cómodo a futuro (¿formulario del portal, foto, transcripción por Agrocom?). La pregunta del banco quedó a medias.
- **C-5 — Quién define el orden de incorporación cuando el cliente no lo da.** Hoy no hay documento formal (G-03). ¿El agrónomo del cliente acepta el orden por defecto de espec §7.2 como estándar cuando su orden no lo especifica? ¿Quién asume la responsabilidad del caldo en ese caso?
- **C-6 — El momento del acta: por lote inmediato (ventana §2.2, "la decisión comercial más importante de todo el diseño") vs. "ese día o al terminar la aplicación completa" (carlos).** Ojo: quien flexibiliza es el propio dueño de Agrocom, contradiciendo el racional comercial de su propio diseño. Decidir si se mantiene la regla estricta, se admite la firma diferida como excepción registrada, o se cambia la regla — y qué implica para el flujo `→ conformado → facturado`.
- **C-7 — Anticipación: ~2 días de preaviso (carlos) vs. "horas, no días" entre disparo e inicio (ventana §3).** Probablemente compatible (decisión anticipada + disparo formal inmediato), pero conviene precisar la redacción porque calibra la logística de "estar siempre listos".
- **Vacíos por respuesta escueta o ausente:** qué decide sola la empresa y qué se consulta siempre (carlos respondió solo *"aplique sola"*); qué querría ver en el portal (carlos: *"sí"*); qué información le faltó en investigaciones de eficacia pasadas; y todos los supuestos de firma/±5 %/solape/acta que Abraham dejó en blanco.
- **El vacío mayor: no respondió ningún agrónomo de un cliente real.** Todo este documento debe re-validarse con el agrónomo del contrato v1 antes de dar por cerrada la sección D del banco de preguntas.

---

## 10. Términos candidatos al glosario

| Término | Significado en el negocio |
|---|---|
| **Caldo** (en las respuestas: "calda") | La mezcla lista para aplicar: agua + productos según la receta. Unidad mental del campo. |
| **Dosis por cada 100 litros** | Formato natural de la orden del agrónomo: cantidad de producto por cada 100 L de caldo (con 10 L/ha, 100 L = 10 ha). |
| **Litros por hectárea (L/ha)** | Volumen de caldo aplicado por hectárea; referencia estándar del rol: 10 L/ha. |
| **Franja / ancho de banda** | Ancho de la pasada del dron (~9 m en el Agras). Parámetro de vuelo que decide la aplicadora. |
| **Papel hidrosensible** | Tarjetas que revelan la cobertura de gota sobre el cultivo; evidencia física de calidad de aplicación deseada por lote. |
| **Zona Geo** | Geocerca de DJI donde el dron no puede volar; exclusión técnica impuesta por el fabricante, no decidida por nadie del negocio. |
| **Screen del RC / captura de RC** | Captura de pantalla del control remoto con hectáreas, recorrido y parámetros de vuelo; la pieza probatoria central del cierre y del reporte. |
| **Deriva** | Arrastre del producto fuera del lote objetivo (viento); origen de reclamos de vecinos y hasta denuncias por daños y perjuicios. |
| **Ventana de aplicación** | El plazo útil (agronómico + climático) para completar una aplicación; su aprovechamiento justifica flexibilidades como la firma diferida del acta. |
| **Agrónomo de la propiedad** | Como llama el campo al agrónomo del cliente: el que emite la orden, da el caldo y firma. |
| **Revisión a las 24 horas** | Segunda verificación de la aplicación, al día siguiente, cuando "se ve si la aplicación es buena". |
