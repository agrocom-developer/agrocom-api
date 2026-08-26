# Análisis de las capturas del control remoto (RC)

**Agrocom SRL · Documento de trabajo vigente · 21 capturas recibidas el 26/8/2026**

Analiza las capturas reales del RC del DJI Agras (`docs/gestion/respuestas_campo/capturas_rc/rc_01..rc_21.jpeg`) — el insumo que estaba bloqueando la consolidación del modelo de datos (`analisis_clasificacion.md` §8, `insumos_modelo_datos.md` §4). Identifica qué muestra cada pantalla, qué datos sirven a la app Flutter, al backend y a los reportes, y qué se omite y debe aportar el sistema.

**Primer hallazgo, antes de mirar el contenido**: no son screenshots — son **fotos de la pantalla del RC tomadas con un celular** y enviadas por WhatsApp (reflejos, cortes, ángulo, texto parcial). Eso define la dirección del producto: (a) la app en el RC captura **screenshot nativo** (corre en el mismo dispositivo — sin reflejos, layout fijo, tipografía DJI conocida), y (b) sobre ese screenshot **sí corre OCR de a bordo** que recupera los datos y prellena el cierre; el piloto solo confirma, la validación aritmética de §3 verifica al OCR, y la entrada manual queda como respaldo. Sobre fotos de celular como estas, en cambio, el OCR no es confiable — por eso la captura siempre se adjunta además como evidencia visual.

---

## 1. Inventario: cuatro pantallas distintas

| Pantalla | Capturas | Cuándo aparece |
|---|---|---|
| **A. Carta de confirmación de efecto laboral — versión corta** | rc_11, rc_17, rc_20 | Al terminar un vuelo/tanda; solo 3 campos |
| **B. Carta de confirmación — versión larga** ("Modo trayectoria") | rc_03, rc_06, rc_07, rc_09, rc_10, rc_13, rc_18, rc_19 | Resumen de la misión con desglose de áreas |
| **C. HUD en vuelo con diálogo Cancelar/Continuar** | rc_01, rc_02, rc_04, rc_05, rc_08, rc_12 | Al aterrizar por tanque vacío o batería — la **retoma de misión** |
| **D. Mapa de misión (Iniciar / post-vuelo)** | rc_14, rc_15, rc_16, rc_21 | Antes de iniciar y al cerrar; pasadas pintadas y obstáculos recortados en blanco |

## 2. Campos exactos por pantalla

**A. Carta corta** (ej. rc_20): `Área de trabajo` (Ha) · `Tiempo de vuelo` (m:ss) · `Uso total de pesticida` (L) · marca "M" abajo.

**B. Carta larga** (ej. rc_03): `Área de trabajo` · `Área pendiente` (en naranja) · `Área de plan` · `Zona del margen de seguridad` · `Área de obstáculo` · `Tiempo de vuelo` · `Uso total de pesticida` · `Cantidad por acre` (⚠ mal rotulado: la unidad real es **L/ha**) · `Completar tasa` (%) · leyenda `Modo trayectoria`.

**C. HUD en vuelo**: `Altitud (m)` · `Caudal (L/min)` · `Distancia (m)` · `vertical (km/h)` · `Área completada (ha)` + banners de alerta + brújula con rumbo + miniatura FPV + botones `Cancelar` / `Continuar`.

**D. Mapa**: polígono de misión con pasadas en amarillo/verde, obstáculos como recortes blancos dentro del polígono, punto Home (H), posición del dron, línea de retorno; botón `Iniciar` en el arranque.

**Alertas observadas** (banners rojos): "Se ha quedado sin pesticida. Aterrice y rellene el tanque" · "Advertencia de nivel de batería (bajo nivel 1)" · "Personas u obstáculos detectados".

## 3. La aritmética que valida el modelo

En todas las cartas largas se cumple:

> **Área de plan = Área de trabajo + Área pendiente + Zona del margen de seguridad + Área de obstáculo**

Verificado con las 7 misiones capturadas (redondeo ±0,1 ha):

| Captura | Trabajo | Pendiente | Margen | Obstáculo | Suma | Plan |
|---|---|---|---|---|---|---|
| rc_03 | 9,82 | 0,43 | 0,90 | 0,00 | 11,15 | 11,1 ✓ |
| rc_06 | 24,5 | 8,07 | 1,72 | 0,08 | 34,37 | 34,4 ✓ |
| rc_07 | 8,21 | 2,40 | 0,79 | 0,00 | 11,40 | 11,4 ✓ |
| rc_09 | 15,8 | 1,02 | 1,58 | 0,00 | 18,40 | 18,4 ✓ |
| rc_13 | 12,3 | 4,00 | 1,48 | 0,00 | 17,78 | 17,8 ✓ |
| rc_18 | 3,87 | 0,49 | 0,43 | 0,00 | 4,79 | 4,79 ✓ |
| rc_19 | 17,6 | 5,17 | 1,31 | 0,00 | 24,08 | 24,1 ✓ |

Consecuencias directas:

1. **DJI ya desglosa la superficie no aplicada por causa técnica** (obstáculo, margen de seguridad): el cierre de lote de la app puede pedir estos 4 números y la "superficie no aplicada con motivo" queda semi-preclasificada — solo falta el motivo humano (anegado, colmenas, orden del agrónomo) para lo que exceda.
2. **El backend gana una validación aritmética gratis**: si los números transcritos no suman (±0,2 ha por redondeo), la transcripción tiene error de tipeo → rechazo inmediato en la app, sin ida y vuelta con el validador.
3. La `Cantidad por acre` (L/ha real: 9,15–12,15 en las capturas, siempre alrededor del estándar de 10) contra los L/ha de la orden alimenta la alerta de coherencia de espec §10 (±15%).

## 4. Lo que la pantalla NO tiene (y quién lo aporta)

| Omisión | Consecuencia | Lo aporta |
|---|---|---|
| **Fecha y hora** | La foto no prueba cuándo fue; WhatsApp además borra el EXIF | El cierre de sesión en la app (timestamp del dispositivo, `secuencia` local) |
| **Identificación del lote/campo** | Ninguna pantalla nombra el lote; solo se ve el polígono | La sesión abierta contra un lote del catálogo |
| **Quién voló** | No aparece usuario ni "inicio de sesión" en estas pantallas | `sesiones.piloto_id` + token por dispositivo |
| **Qué dron** | Sin serie ni modelo en pantalla | `sesiones.dron_id` |
| **Qué batería / temperatura** | El RC avisa "batería baja" pero no identifica la batería física | Registro de recarga del auxiliar (código + temperatura) |
| **Producto aplicado** | "Pesticida" genérico, sin nombre ni dosis de la orden | Orden de aplicación + mezcla vinculada |
| **Condiciones (viento/temp/humedad)** | Nada en pantalla | Registro de condiciones con anemómetro al abrir sesión |
| **Coordenadas / geometría exportable** | El mapa es visual; no hay export en estas pantallas | El GeoJSON del lote en el catálogo; la nube DJI para el detalle |

**Conclusión de diseño (ratifica espec §16)**: la captura del RC es **evidencia visual, nunca fuente de datos estructurada**. La cadena lote–piloto–dron–fecha–condiciones la aporta la app al abrir/cerrar la sesión; la carta se adjunta y se transcriben pocos números.

## 5. Ambigüedades a confirmar con los pilotos (reunión de cierre)

*Nota (26/8/2026): el equipo está en campaña y no disponible — estas preguntas quedan en cola para la reunión de cierre; ninguna bloquea el diseño.*

1. **`Completar tasa 100%` convive con `Área pendiente` > 0** (rc_06: 100% con 8,07 ha pendientes) — ¿la tasa es del recorrido ejecutado y no del plan? No usar ese % como "lote terminado"; el fin de lote lo declara el piloto.
2. **`Tiempo de vuelo` y `Uso total de pesticida` no cuadran con el área acumulada** (rc_03: 9,82 ha con solo 9,8 L) — hipótesis: áreas acumulan la misión, tiempo/litros se reinician por vuelo/tanda. Confirmar qué abarca cada campo antes de usarlos en cruces.
3. **Código de color amarillo/verde** de las pasadas (ejecutado vs. pendiente) — las capturas no lo dejan inequívoco (rc_16 pre-inicio es todo verde; rc_01/rc_04 mezclan). Confirmar.
4. **La marca "M"** en las cartas cortas — ¿vuelo en modo manual? Si existe modo manual, registrar el modo por sesión: en manual el área medida es menos confiable.
5. **Carta corta vs. larga** — ¿cuándo muestra DJI una u otra? (¿misión sin plan / vuelo manual → corta?).

## 6. Implicaciones por consumidor

### App Flutter — flavor piloto (RC)

- **Captura nativa de pantalla** en el cierre de sesión y en cada retoma — reemplaza la foto con celular (calidad, encuadre, EXIF, y elimina el paso WhatsApp).
- **OCR de a bordo sobre el screenshot** (offline, p. ej. ML Kit on-device): la carta DJI tiene layout fijo y tipografía conocida, así que el OCR extrae los campos (trabajo/pendiente/plan/margen/obstáculo, tiempo, litros, L/ha) y **prellena** el formulario de cierre; el piloto confirma en un vistazo, la validación aritmética de §3 actúa como detector de errores del OCR, y si el OCR falla queda la entrada manual de los mismos campos. El screenshot original se adjunta siempre como evidencia.
- El diálogo **Cancelar/Continuar** es el momento natural de registro: cada "Continuar" es una recarga (tanque) o cambio de batería → la app puede pedir ahí el registro rápido tipificado por la alerta que lo causó (sin pesticida / batería) — dron en tierra, regla UX respetada.
- La pantalla del RC muestra **acumulado de la misión** — ratifica visualmente el control de doble conteo: en relevo, el entrante registra `hectarea_inicial_acumulada` leyéndola de la pantalla.

### App Flutter — flavor auxiliar

- Cruce nuevo: **litros cargados por el auxiliar** (recarga) vs. **`Uso total de pesticida` del RC** (transcrito por el piloto) — detecta fugas, derrames o transcripción errada, por tanda.

### Condiciones y clima (API de Google)

Las pantallas del RC no traen viento/temperatura/humedad (§4), y el lote no tiene señal — la integración de clima (Weather API de Google Maps Platform, elegida por precisión; requiere API key) se diseña en **tres capas** compatibles con offline:

1. **Pronóstico cacheado en base**: antes de salir al lote, la app baja el pronóstico horario por coordenadas del lote y lo guarda — el jefe planifica con él.
2. **El anemómetro sigue mandando en el lote**: el registro manual de condiciones al abrir sesión es el dato de la autorización (y de la firma si se fuerza fuera de rango) — un servicio remoto sin señal no puede reemplazarlo.
3. **Enriquecimiento retroactivo en el backend**: al sincronizar cada sesión, el servidor consulta el histórico horario de Google por coordenadas del lote y hora de la sesión y lo guarda junto a las condiciones declaradas — segundo testigo independiente ante un reclamo de deriva o de eficacia.

### Backend

- `sesiones` (o su cierre): agregar `area_pendiente_ha`, `area_plan_ha`, `area_margen_ha`, `area_obstaculo_ha`, `lha_real`, `modo_vuelo` — todos transcritos, con la validación de suma.
- `recargas`: agregar `litros_aplicados_rc` (opcional) para el cruce auxiliar/RC.
- Validaciones de dominio: suma aritmética (±0,2 ha) · L/ha real vs. orden (±15%, espec §10) · área de trabajo ≤ hectáreas del lote + tolerancia de solape.
- Las alertas del RC ("sin pesticida", "batería", "personas u obstáculos") calzan con los enums existentes de motivo de recarga/incidencia — no hacen falta tipos nuevos.

### Reportes (dueño y cliente)

- La **carta larga es el esqueleto del reporte técnico por lote**: área aplicada, pendiente, obstáculos y margen (superficie no aplicada justificada), L/ha real vs. ordenado, tiempo total — más el mapa de pasadas como imagen que el cliente entiende sin explicación.
- El desglose margen/obstáculo le da al reporte comercial la frase que protege la factura: "X ha no aplicadas por obstáculo declarado, Y ha de margen de seguridad" — decisión técnica visible, no faltante.

## 7. Estado de los pendientes que esto cierra

| Pendiente (analisis_clasificacion §8 / insumos §4) | Estado |
|---|---|
| Campos del reporte de misión DJI | **CERRADO** — §2 de este documento |
| Formato del mapeo y sus obstáculos | **CERRADO** — pantalla D; obstáculos como recortes con área cuantificada |
| Qué muestra la pantalla al retomar misión | **CERRADO** — pantalla C (Cancelar/Continuar) con área completada **acumulada** |
| Tolerancia de solape (valor numérico) | Sigue abierto — las capturas no lo muestran; medirlo con datos reales |
| Semántica fina (tasa 100%, alcance de tiempo/litros, colores, "M") | Abierto — 5 preguntas puntuales para la reunión (§5) |

Con esto, la consolidación de la especificación §4 ya no espera insumos técnicos: solo la reunión de cierre (agenda en `analisis_clasificacion.md` §7, más las 5 preguntas de §5 de este documento).

## 8. Anexo — datos crudos extraídos, captura por captura

Transcripción manual de lo legible en cada foto (valores tal como los muestra la pantalla; los campos tapados o cortados se omiten).

### Cartas de confirmación — versión larga (una fila por misión)

| Captura | Trabajo (ha) | Pendiente (ha) | Plan (ha) | Margen (ha) | Obstáculo (ha) | Tiempo | Pesticida (L) | L/ha | Tasa |
|---|---|---|---|---|---|---|---|---|---|
| rc_03 | 9,82 | 0,43 | 11,1 | 0,90 | 0,00 | 4:56 | 9,8 | 12,15 | 100% |
| rc_06 | 24,5 | 8,07 | 34,4 | 1,72 | 0,08 | 3:34 | 6,5 | 10,50 | 100% |
| rc_07 | 8,21 | 2,40 | 11,4 | 0,79 | 0,00 | 8:10 | 20,8 | 9,15 | 100% |
| rc_09 / rc_10 ¹ | 15,8 | 1,02 | 18,4 | 1,58 | 0,00 | 2:54 | 2,7 | 10,06 | 100% |
| rc_13 | 12,3 | 4,00 | 17,8 | 1,48 | 0,00 | 5:45 | 10,4 | 10,69 | 100% |
| rc_18 | 3,87 | 0,49 | 4,79 | 0,43 | 0,00 | 7:38 | 13,2 | 10,05 | 100% |
| rc_19 | 17,6 | 5,17 | 24,1 | 1,31 | 0,00 | 5:10 | 9,0 | 10,05 | 100% |

¹ rc_10 es una segunda foto de la misma pantalla que rc_09.

### Cartas de confirmación — versión corta

| Captura | Trabajo (ha) | Tiempo | Pesticida (L) | Nota |
|---|---|---|---|---|
| rc_11 | 1,82 | 10:40 | 11,0 | Marca "M" |
| rc_17 | 1,04 | 5:59 | 16,1 | Alertas de "sin pesticida" al costado; 16,1 L en 1,04 ha refuerza que los litros no son solo de esa área (§5.2) |
| rc_20 | 3,82 | 7:10 | 6,7 | Título completo legible: "Carta de confirmación de efecto laboral"; marca "M" |

### HUD en vuelo / retoma (Cancelar–Continuar)

| Captura | Altitud (m) | Caudal (L/min) | Distancia (m) | Vert. (km/h) | Área completada (ha) | Alertas visibles |
|---|---|---|---|---|---|---|
| rc_01 | 0,0 | 0,0 | 4,0 | 0,0 | 25,2 | Sin pesticida · personas u obstáculos |
| rc_02 | 0,0 | 0,0 | 208,7 | 0,0 | 29,0 | Nivel de batería |
| rc_04 | 0,0 | 0,0 | 8,4 | 0,0 | 13,5 | Sin pesticida · personas u obstáculos |
| rc_05 | 0,0 | 0,0 | 0,2 | 0,0 | 9,8 | — (HUD parcialmente tapado) |
| rc_08 | 0,0 | 0,0 | 0,0 | 0,0 | 7,0 | — |
| rc_12 | 0,0 | 0,0 | 36,3 | 0,0 | 2,7 | Batería bajo nivel 1 · personas u obstáculos |

### Mapa de misión (inicio / post-vuelo)

| Captura | Qué muestra |
|---|---|
| rc_14 | Misión recién iniciada: altitud 6,1 m, distancia 178,3 m, vertical 0,4, área 0,0; obstáculos recortados en blanco |
| rc_15 | Pantalla previa con botón **Iniciar**: altitud 7,4, distancia 152,0, área 0,0; ruta completa dibujada |
| rc_16 | Pre-inicio (botón Iniciar), área 0,0, distancia ~29,4; polígono con obstáculos marcados |
| rc_21 | Post-misión: pasadas pintadas, línea de retorno al punto Home, "Área completada" cortado por el encuadre |

### Lecturas agregadas (calibración)

- **L/ha real** en 7 misiones: 9,15–12,15, mediana ~10,05 — clava el estándar declarado de 10 L/ha y calibra la alerta ±15%.
- **Margen de seguridad**: 0,43–1,72 ha por misión (~4–9% del plan) — el solape/borde no aplicado es sistemático y cuantificable, insumo para la tolerancia de solape pendiente.
- **Área de obstáculo**: casi siempre 0,00 (solo rc_06 con 0,08) — los obstáculos se excluyen en el mapeo, no durante el vuelo.
- **Tiempo por vuelo**: 2:54–10:40 — consistente con los 10–12 min/batería declarados en las encuestas.
- **Área completada acumulada** vista en retomas: 2,7 → 29,0 ha — confirma que la pantalla acumula la misión completa (doble conteo en relevos).
