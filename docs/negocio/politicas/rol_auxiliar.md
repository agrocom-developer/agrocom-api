# Políticas y lógica de negocio — Rol: Auxiliar

**Agrocom SRL · Documento de negocio derivado de campo**

**Fuentes:**
- Primaria: `docs/gestion/respuestas_campo/Cuestionario Auxiliar (Respuestas) - Respuestas de formulario 1.csv` — respuestas del 25/8/2026 de Abraham Gutiérrez Contreras (4 campañas), David Omar Ríos Lino (2 campañas) y Miguelito Justiniano Dorado (1 campaña).
- Instrumento: `docs/gestion/respuestas_campo/banco_preguntas_por_rol.md`, sección ROL: AUXILIAR.
- Contraste: `docs/especificacion/especificacion_funcional_tecnica.md` (§2, §3, §4.3, §5, §7, §10) y `docs/negocio/ventana_al_negocio.md` (§4.2, §7, §8).

**Estado:** derivado de respuestas de campo del 25/8/2026. Es **insumo para actualizar la especificación**, no la reemplaza. Las citas textuales conservan la ortografía original de los encuestados. Los ajustes propuestos en §6 que tocan reglas con ADR asociado (superficies de la app, rol escritor por registro — ADR 0001/0005) deben pasar por `arquitectura` antes de aplicarse.

---

## 1. Misión del rol y límites

**Misión:** sostener el ciclo de vuelo para que el dron nunca espere. El auxiliar es el dueño de la cadena de soporte descrita en `ventana_al_negocio.md` §8: **gasolina → generador → baterías → dron**. El caldo es su frontera de contacto: hoy casi siempre lo recibe preparado (ver §6, hallazgo mayor) y su tarea es cargarlo al dron respetando los límites del modelo.

Los tres encuestados coinciden en que la división con el piloto está clara. David: *"En si mi trabajo con el del piloto está bien divido. No creo que le faltaría hacer algo más o yo hacer algo más."*

| Dimensión | Contenido según las respuestas |
|---|---|
| **EJECUTA** | Alistamiento y limpieza de dron, generador y baterías (pines, boquillas, conectores); prueba de boquillas con agua; cambio de batería en cada retorno; rotación y carga de baterías; llenado del tanque de caldo del dron; carga de combustible del generador; atención permanente al piloto; limpieza de bombas y centrífugas tras caldo problemático. |
| **DECIDE** | Casi nada de parámetros: retirar de servicio una batería sobrecalentada; el ritmo interno de la rotación de baterías; cuánta gasolina llevar (compartido con el piloto — ver §9). **No decide** volumen por tanque, dosis, ni L/ha: *"Mi persona nunca decidió eso. Solo miraba"* (David). |
| **REGISTRA** | Hoy, casi nada: *"no se escribe nada por el motivo de que todo se queda en el dron. todo lo sacas del control remoto"* (Miguelito). Abraham anota hectáreas (avance/faltante) en celular o cuaderno, extraídas del RC. |
| **VALIDA** | Nada formalmente. Verificación informal: estado visual del caldo (espesor, corte), porcentaje de carga en el RC, temperatura de batería al tacto, RPM de bombas y centrífugas. |

**Frontera con el piloto:** batería, generador y caldo son del auxiliar; el vuelo, el RC y los parámetros de aplicación son del piloto. Abraham y Miguelito ubican del lado del piloto la revisión de pines, hélices y RPM: *"estar pendiente de los RPM de las bombas como de las centrífugas eso es ley para todo piloto"* (Abraham). Sin embargo David, como auxiliar, hace él mismo la limpieza de pines y boquillas cada mañana — la titularidad de esa tarea es una celda en conflicto (ver §9).

---

## 2. Flujo base del día del auxiliar (consolidado)

1. **Alistamiento y limpieza (antes del primer vuelo):** dron, generador y baterías *"que todo este en perfecto estado y limpio"* (David). Limpieza de pines de baterías, pines del dron y boquillas; verificar aspas/hélices fijas. Baterías **cargadas antes de empezar**: *"si te demoras en cargar las baterías también demoras en el avance de fumigación"* (Abraham).
2. **Prueba de boquillas con agua:** vuelo/aspersión de prueba con agua para verificar que las boquillas no estén tapadas. David la repite **cada 3 viajes** del dron durante el día.
3. **Verificación del caldo:** si lo prepara el cliente (caso dominante hoy), verificar que haya caldo listo y **avisar cuando queda poco**; si lo prepara el propio equipo, preparar según la orden de mezcla entregada.
4. **Ciclo de cambio de batería (en cada retorno, sin excepción):** el dron aterriza → se apaga → se retira la batería descargada → se coloca una cargada → la descargada va al generador → se recarga el caldo respetando el rango del modelo → despega. Miguelito: *"se demora en 5mins"*. El RC indica el porcentaje de carga.
5. **Rotación etiquetada de baterías (3 por dron):** David: *"Mi persona etiqueta las baterías. Batería 1, batería 2 y batería 3. Carga la 1 procedo con la 2 mientras las 3 están en vuelo."* Abraham: con 3 baterías, cuando una sale a vuelo hay dos *"esperando su turno cargadas y enfriadas — ese ciclo evita sobrecalentamiento"*.
6. **Mientras el dron vuela (10–12 min):** la batería saliente entra a carga (8 min en carga rápida DJI), se vigila la carga, se alista el caldo siguiente y se atiende lo que el piloto necesite.
7. **Carga del generador:** 1–2 veces al día según equipo (ver §5); vigilar nivel de gasolina es tarea continua del auxiliar.
8. **Cierre del día:** el sobrante del tanque del dron se aplica en las cortinas (lo riega el piloto), los envases vacíos se entregan o se dejan ordenados para el cliente, y se guarda y limpia el equipo — con limpieza profunda de bombas y centrífugas si el caldo dio problemas.

---

## 3. Políticas y reglas de negocio

| # | Política | Fuente |
|---|---|---|
| A-01 | **La batería se cambia en cada retorno del dron, sin excepción**, para evitar aterrizajes de emergencia o apagado en vuelo: *"se cambia si o si la batería cada que el dron llegue a su punto de partida"*. | Abraham |
| A-02 | **Rotación mínima de 3 baterías por dron, etiquetadas** (1/2/3): una en vuelo, una en carga, una enfriando/lista. La rotación es la prevención primaria del sobrecalentamiento. | Los tres |
| A-03 | **Las baterías inician la jornada cargadas.** La carga en caliente durante el día solo repone; no se arranca de cero. | Abraham |
| A-04 | **Batería sobrecalentada se retira de servicio de inmediato** — no se reintenta: el daño se propaga (pines → placa de conexión del dron → resto de las baterías). Detección: al tacto; el aviso del RC no es confiable (ver §9). | Abraham, Miguelito |
| A-05 | **El llenado del tanque respeta el rango recomendado por modelo, nunca el máximo nominal**: T30: 20–26 L; T40/T50: 30–36 L. *"respetando los rangos de cada dron"*. | Abraham, Miguelito |
| A-06 | **El volumen por hectárea (L/ha) y la dosis los define el cliente/agrónomo; el auxiliar nunca los decide.** | Los tres |
| A-07 | **La preparación del caldo corresponde hoy al cliente** (agrónomo o personal de la propiedad). Cuando el equipo Agrocom la hace, sigue la orden de mezcla entregada por el dueño/agrónomo. | Los tres — hallazgo mayor, ver §6 |
| A-08 | **Si el caldo sale mal, responde quien lo preparó**: *"Corresponde a quien hizo la preparación"*. Causas típicas: mala mezcla o producto vencido/de mala calidad. | David, Abraham, Miguelito |
| A-09 | **Tras un caldo cortado o con grumos, la limpieza de bombas y centrífugas es obligatoria** antes de seguir: *"es primordial darle una limpieza de bombas y centrífugas al dron para evitar daños"*. | Abraham |
| A-10 | **RPM anormal de bombas/centrífugas = grumos dentro del tanque = parar y limpiar.** Los grumos casi no son visibles (*"el enemigo silencioso"*); el RPM es el detector real. | Abraham, Miguelito |
| A-11 | **Prueba de boquillas con agua al alistar y periódicamente** (cada ~3 viajes) para detectar boquillas tapadas antes de cargar producto. | David |
| A-12 | **El sobrante del tanque del dron se aplica en las cortinas del lote** (*"para no botar por botar el caldo"*); el sobrante del tanque de mezcla en tierra queda para el cliente. Lo riega el piloto. | Los tres |
| A-13 | **Los envases vacíos vuelven al cliente**: entregados al dueño (si el equipo hizo la mezcla) o dejados ordenados en el lote para que los recoja el personal de la propiedad. | Abraham, David |
| A-14 | **Provisión de insumos: agua y agroquímicos los pone el cliente; la gasolina es logística de Agrocom.** Regla de oro de Abraham: *"siempre es bueno llevarse más gasolina de lo que se va ocupar"*. | Los tres |
| A-15 | **Faltantes se escalan por canal según el insumo:** gasolina → al jefe; agua/producto → al cliente (dueño o encargado de la propiedad), avisando también al jefe. | Abraham, David, Miguelito |
| A-16 | **Generador: falla mecánica no se resuelve en campo** (taller y/o generador de respaldo); falla de firmware en generador DJI se resuelve conectando el RC al cargador interno. Sin generador, el día se muere. | Abraham, Miguelito |
| A-17 | **Dos drones cerca: nunca comparten pareja.** Cada dron con su piloto y su auxiliar; el terreno se divide; los auxiliares se coordinan y ayudan entre sí. | Los tres |
| A-18 | **El avance se mide en hectáreas, no en tanques**, y la fuente es el RC: *"Nunca hemos contado cuántos tanques. Nos basamos en las hectáreas"*. | David, Miguelito |
| A-19 | **La recuperación de un caldo cortado es posible** con coadyuvantes (tipo "all ok") y agitación constante posterior — pero el cliente a veces niega el coadyuvante (ver §9). | Abraham |

---

## 4. Excepciones y casos reales

- **Caldo cortado "como quesillo"** (Abraham): el corte deja el caldo con textura de quesillo; obliga a limpieza de bombas y centrífugas. Se intenta recuperar con coadyuvante (*"como el all ok actúan muy rápido"*) y después *"estar batiendo constantemente el caldo para evitar que se corte de nuevo"*. Causa típica: mala mezcla o veneno vencido.
- **Caldo degradado por espera de días — el caso "lodo"** (David): prepararon una calda para un lote; el encargado de la propiedad los movió a otro lote "más urgente"; volvieron 3–4 días después y *"el veneno que tenía que estar líquido estaba muy espeso, como lodo"*. Resolución: **"Hicimos el reporte correspondiente con pruebas de que el encargado de la propiedad nos pidio dejar ese lote y movernos a otro."** Es el principio rector del sistema ejecutado a mano: la evidencia le dio la razón al equipo cuando el reclamo llegó.
- **Batería sobrecalentada y el ciclo de daños** (Abraham): sobrecalentamiento por pines sucios o celdas golpeadas. Se detecta tocando la batería, porque *"la mayoria de las veces no te sale sobrecalentamiento en las beterias"* en el RC — y para entonces ya *"te daña la placa de conexión y esa placa te daña las otras baterías en si es ciclo de daños"*. Acción: retirar la batería de servicio de inmediato (A-04). Este mecanismo causal es el fundamento de campo de la alerta "dron sospechoso" de la espec §10.
- **Generador que falla** (Abraham, Miguelito): *"se para la fumigacion"*. Falla mecánica: casi nada que hacer en campo — taller lo antes posible y generador de respaldo (*"un generador que te salve el día"*). Falla de sistema/firmware (generador DJI): se soluciona conectando el RC al cargador interno. Consecuencia directa: *"si no cargas batería por fallas del generador te quedas sin fumigar el terreno"*.
- **Grumos invisibles** (Abraham, Miguelito): no se detectan antes de cargar; se manifiestan como caída de RPM de bombas y centrífugas con el caldo ya en el dron. *"eso jode centrifuga y bomba"*. El dueño *"te niega colocar un coadyuvante para combatir el grumo"* — el costo del daño lo absorbe el equipo.
- **Dos drones cerca** (los tres): posible pero riesgoso (*"un simple descuido puede causar accidentes"*). Regla: dividir el terreno, cada dron con su pareja completa; los auxiliares se ayudan pero no se mezclan las atenciones.
- **Sin motobomba** (Abraham): el llenado de agua del tanque de mezcla pasa de 10–15 minutos a *"más de 30 o 40 minutos"* — la logística menor (una motobomba) triplica o cuadruplica el tiempo del cuello de botella.

---

## 5. Números de calibración

| Parámetro | Valor de campo | Fuente |
|---|---|---|
| Baterías por dron en rotación | **3** (con 3 alcanza; con 4 *"avanzas más rápido"*; Miguelito: *"se llevan 2 a 3"*) | Los tres |
| Carga de batería (generador DJI, carga rápida) | **8 min** | Miguelito |
| Duración de un vuelo/tanque | **10–12 min** → con carga rápida no hay espera | Miguelito |
| Cambio de batería completo (aterrizar→despegar) | **~5 min** | Miguelito |
| Frecuencia de cambio de batería | **cada retorno, sin excepción** | Abraham |
| Cuello de botella de la rotación | El generador, no las baterías: los no-DJI son más lentos y *"el dron te alcanza en las baterías"* | Abraham |
| Gasolina del generador | DJI ~30 L aguanta el día completo; no-DJI rinde ~10 L menos. David: 20 L cada 4 h. Miguelito: 25 L × 2 cargas = **50 L/día** | Los tres |
| Jornada declarada | **5:00 a 22:00** (incluye trabajo nocturno) | Miguelito |
| Preparación de un tanque de mezcla | **10–15 min con motobomba; 30–40+ min sin** (el llenado de agua es lo que más demora) | Abraham |
| Tanques de mezcla en tierra | 200 / 500 / 1.000 L; lote grande → 2+ tanques de 1.000 L o 10+ preparaciones de 200–500 L | Abraham |
| Límite de llenado del dron por modelo | **T30: 20–26 L · T40/T50: 30–36 L** (coherente con la regla de la espec §7.1 de no cargar al máximo; el rango T50 de campo llega por encima de los 30 L "habituales" de la espec) | Abraham |
| Limpieza de boquillas / prueba con agua | Al alistar y **cada ~3 viajes** | David |
| Umbral de "batería caliente" | **No cuantificado en campo** (detección al tacto); el umbral 50 °C de la espec §10 sigue siendo supuesto | Abraham, Miguelito |
| Registro escrito diario | ~0: el RC es la memoria; Abraham anota solo hectáreas (avance/faltante) | Los tres |

---

## 6. Clasificación contra la especificación

| # | Hallazgo | Clasificación | Sección | Impacto / ajuste propuesto |
|---|---|---|---|---|
| 1 | **La mezcla la prepara el cliente (agrónomo o personal de la propiedad), no el auxiliar** — 3 de 3: *"Normalmente los agronomos hacen la preparación del caldo"*, *"la preparación lo hace personal de la propiedad"*, *"eso se encarga el cliente"*. La espec §7 pone la preparación en la app del auxiliar. | **CORREGIDO (mayor)** | §7, §2, §3 | Hay **dos escenarios** y el sistema debe registrar **quién preparó**: (a) prepara Agrocom → checklist §7.2 completo en app auxiliar; (b) prepara el cliente → el auxiliar registra **recepción del caldo** (hora, estado visual, problema detectado), no el checklist. `mezclas.preparada_por` ya existe; falta el discriminador de escenario (p. ej. `preparador_tipo: agrocom \| cliente`) y el flujo alternativo en la app. La carga probatoria cambia de lado: si preparó el cliente, la trazabilidad de Agrocom demuestra recepción y aplicación fiel, no incorporación. **Toca superficies y rol escritor (ADR 0001/0005) → avisar a `arquitectura` antes de aplicar.** |
| 2 | Responsabilidad del caldo malo = de quien lo preparó | CONFIRMADO | §7.4; ventana §5 | Refuerza el hallazgo 1: registrar el preparador es lo que asigna la responsabilidad. |
| 3 | El sobrante del tanque del dron se aplica en las **cortinas**; el del tanque de mezcla queda para el cliente | CONFIRMADO (con matiz) | §4.3 `sobrantes.destino` | `aplicado_en_lote` cubre el caso; evaluar valor explícito `aplicado_en_cortinas` para el reporte técnico. Matiz de ejecutor: **lo riega el piloto** (David), pero el registro vive en la app del auxiliar — definir rol escritor. |
| 4 | Envases vacíos: se entregan al dueño o se dejan ordenados en el lote. **Nadie menciona triple lavado.** | CORREGIDO (parcial) | §7.3 | El destino "devuelto al cliente" está bien modelado; `triple_lavado (bool)` registraría una práctica que hoy no existe → confirmar en reunión si se exige como política o se registra como opcional. |
| 5 | **EPP: "normalmente no te dan equipos de protección"** (Abraham); *"no utilizas nada"* (Miguelito); David usa guantes y barbijo siempre y lleva su propia agua para lavarse las manos. La espec §7.3 exige confirmación de EPP al iniciar mezcla. | **CORREGIDO (bloqueante operativo)** | §7.3 | Confirmar EPP en la app sin que la empresa lo provea genera un registro falso sistemático. Requiere decisión del dueño: **provisión de EPP como política previa al checklist**. Además, si la mezcla la hace el cliente (hallazgo 1), el checklist EPP del auxiliar aplica solo al escenario Agrocom. Abraham lo pide explícitamente: *"sería bueno que todos cuiden su salud"*. |
| 6 | **Nadie anota casi nada porque "todo queda en el control remoto"** — el RC es la memoria del día | CONFIRMADO (premisa) con restricción de diseño | §16 (captura RC sin API DJI); §2 | Confirma que la captura del RC es la fuente de respaldo correcta. Restricción UX dura: la app compite contra "cero registro" — cada registro del auxiliar debe caber en los tiempos muertos del ciclo (ver §8) o será esquivado. |
| 7 | **Limpieza profunda del dron (bombas, centrífugas, pines, boquillas) como tarea recurrente del auxiliar** — al alistar, cada ~3 viajes, y obligatoria tras caldo cortado/grumos | **DESCUBIERTO** | §12; app auxiliar | Hoy invisible para el sistema. Modelar como checklist de alistamiento/limpieza en la app auxiliar o tarea rutinaria de mantenimiento (sin orden de mantenimiento formal). Da contexto a fallas de bombas/centrífugas. |
| 8 | **RPM de bombas y centrífugas como indicador de salud y detector de grumos** | **DESCUBIERTO** | §4.3 `incidencias`; §10 | Nuevo subtipo de incidencia (`rpm_anormal` o similar) — es el síntoma observable que hoy dispara la limpieza; insumo futuro de mantenimiento predictivo. |
| 9 | **Recuperación de caldo cortado con coadyuvante ("all ok") + agitación continua**; el cliente a veces niega el coadyuvante | **DESCUBIERTO** | §7; incidencias | Proceso real no escrito. Pregunta abierta: quién autoriza y quién paga el coadyuvante recuperador — no asumir; llevar a reunión con jefe de campo y agrónomo. |
| 10 | **Caldo degradado por espera de días tras reasignación ordenada por el cliente** (caso "lodo": el encargado movió al equipo de lote; se resolvió con "reporte con pruebas") | **DESCUBIERTO** | §4.3 incidencias/evidencias; §5 | Registrar la **interrupción ordenada por el cliente** como incidencia con evidencia y autor de la orden — hoy se defiende con un reporte artesanal. Valida el principio rector: la evidencia le da la razón a quien corresponde. |
| 11 | Cambio de batería en cada retorno + rotación etiquetada (Batería 1/2/3) | CONFIRMADO | §4.3 `recargas`, `baterias.codigo`; ventana §4.2 | El modelo `recargas` (una por ciclo, con `bateria_saliente_id`) calza exacto. El etiquetado manual ya es práctica → adopción fácil del código propio (T50-A-01). |
| 12 | **Temperatura de batería: detección al tacto; el aviso del RC llega tarde o no llega; no usan termómetro** | **CORREGIDO** | ventana §4.2; §4.3 `recargas.temperatura_bateria_c`; §10 | La espec asume medición con termómetro infrarrojo en cada cambio. Opciones: dotar termómetro IR (decisión de compra) o admitir en v1 registro cualitativo (normal/caliente/muy caliente) con la temperatura numérica opcional. El umbral 50 °C queda como supuesto §16. |
| 13 | Ciclo de daños pines → placa de conexión → otras baterías | CONFIRMADO | §10 alerta "dron sospechoso" | El mecanismo causal de campo respalda la alerta "3+ baterías calientes en el mismo dron → revisar el dron". |
| 14 | Rangos de llenado por modelo (T30 20–26 L; T40/T50 30–36 L) y regla de no cargar al máximo | CONFIRMADO (calibra) | §7.1 | Mantener tabla de carga habitual por modelo como parámetro configurable; los rangos de campo afinan los valores de la espec. |
| 15 | **Frontera de provisión de insumos: agua y agroquímicos del cliente; gasolina de Agrocom** | **DESCUBIERTO** (regla explícita) | §4.4; contrato/orden | Documentar en contrato y orden de aplicación quién provee qué — hoy es costumbre, no cláusula. Alimenta la fila "Gasolina" de conflictos (ventana §7). |
| 16 | **La motobomba define el tiempo de preparación** (10–15 vs 30–40+ min por tanque) | **DESCUBIERTO** | §4.5 equipos; logística | Equipo menor no modelado que triplica el cuello de botella. Candidato a checklist de logística del día del jefe de campo. |
| 17 | **Generador de respaldo como política de continuidad** (*"un generador que te salve el día"*) | **DESCUBIERTO** | §4.5 `generadores` | La espec modela generadores pero no la política de redundancia. Decisión de dotación por base, no de software — anotar para el dueño. |
| 18 | Jornada declarada 5:00–22:00 (trabajo diurno y nocturno) | **DESCUBIERTO** (dato) | ventana §4.1 [vuelo nocturno] | Calibra el supuesto abierto de vuelo nocturno: al menos un equipo ya trabaja de noche. Confirmar condiciones en reunión. |
| 19 | **Pago por QR a empleados**: *"carga el Qr para cobra de los empleados"* | **DESCUBIERTO** | §11 planilla | Deseo de cobrar vía QR bancario desde la app. Fuera de alcance v1; anotar para la fase de planilla/pagos. |
| 20 | Un auxiliar por dron; dos drones cerca = terreno dividido, parejas separadas | CONFIRMADO | §4.3 `sesiones.auxiliar_id` | El `auxiliar_id` único por sesión refleja la práctica; el devengo del auxiliar por sesión queda bien asignado. |

---

## 7. Oportunidades de automatización / sistematización

**Los tres usarían una app que calcule cantidades por tanque y orden de mezcla** — es el punto de mayor tracción del rol:

- Abraham: *"si una app te da el orden de mezcla, cantidad de litros, cantidad de agroquímicos sería de una gran ayuda para todos y especialmente para los pilotos"*.
- Miguelito, lo que debería tener **sí o sí**: *"calculacion de la dosis por ha. por tipo de siembra, por ancho de banda del terreno"* — dosis por hectárea, por tipo de siembra y por ancho de banda.
- David: *"Llegaría a usarla pero tendría que consultar con el ing agrónomo si es lo adecuado"* — señal clave de adopción: la app no debe competir con la autoridad del agrónomo sino **presentarse como la receta del agrónomo ejecutable** (coincide con la espec: la receta la define el agrónomo, Agrocom la ejecuta y documenta).

Otras oportunidades derivadas:

- **Registro de recepción de caldo** (escenario cliente-prepara): un toque para dejar constancia de hora y estado — convierte el "reporte con pruebas" artesanal del caso "lodo" en un registro estándar.
- **Aviso de caldo por agotarse**: hoy el auxiliar avisa verbalmente al personal del cliente; un registro de nivel al recargar habilita la alerta.
- **Checklist de alistamiento/limpieza** (pines, boquillas, prueba con agua) con frecuencia sugerida cada 3 viajes.
- **Incidencia rápida de RPM anormal / batería caliente / generador**, precargada con el contexto de la sesión activa.
- Lo que pidieron como mejora general — *"un dron más grande"* (David), condiciones logísticas (*"campo limpio, que el cliente tenga la calda preparada, el camino mas accesible"*, Miguelito) — no es software, pero calibra qué frena el día.

---

## 8. Qué registra este rol y en qué superficie

**Superficie única: app auxiliar en celular Android** (espec §2). El auxiliar no usa panel web. Todo offline-first con UUID de cliente.

| Registro | Momento del ciclo | ¿Frena la rotación? |
|---|---|---|
| Recarga (litros de caldo, `problema_caldo`, batería saliente, estado/temperatura) | **Después del despegue**, durante los 10–12 min de vuelo | No — es el tiempo muerto natural del rol |
| Mezcla con checklist §7.2 (solo escenario Agrocom-prepara) | Durante la preparación del tanque en tierra (10–15 min) | No, si el checklist acompaña el orden real de incorporación |
| Recepción de caldo del cliente (propuesto — hallazgo 1) | Al recibir cada tanque preparado | No — un toque + observación opcional |
| Carga de combustible del generador (litros) | 1–2 veces al día, momento tranquilo | No |
| Incidencias (batería caliente, RPM anormal, generador, caldo) | Al detectarse; detalle diferible al siguiente vuelo | No, si el alta es de un toque y el detalle espera |
| Sobrante y envases | Cierre del día | No — pero el ejecutor del riego en cortinas es el piloto: definir quién registra (§9) |

**Regla de diseño derivada del campo:** los **5 minutos del cambio de batería son intocables** — ningún registro puede pedirse entre aterrizaje y despegue. La ventana de captura es el vuelo (10–12 min). Y como hoy el registro escrito es casi cero (*"todo se queda en el dron"*), cada pantalla compite contra la alternativa de no anotar nada: valores precargados desde la sesión activa, un toque para el caso normal, texto libre solo opcional.

---

## 9. Contradicciones y vacíos para la reunión de cierre

1. **¿Quién prepara la mezcla cuando le toca a Agrocom?** Los tres describen el escenario cliente-prepara; Abraham insinúa el otro (*"en caso si ellos mismos lo preparan"*, y tiene guardado un orden general de mezcla). Vacío: con qué frecuencia prepara Agrocom, y quién dentro de la pareja. **Pedirle a Abraham el orden de mezcla que guarda** y contrastarlo con el checklist §7.2.
2. **Limpieza de pines/hélices/boquillas: ¿de quién es?** Abraham y Miguelito la ponen como "ley del piloto"; David la ejecuta él cada mañana como auxiliar. Celda EJECUTA en conflicto en el alistamiento diario.
3. **Responsable del caldo malo:** David: "quien hizo la preparación"; Miguelito: "el cliente". Coinciden solo mientras prepare el cliente. ¿Y cuando prepara Agrocom: responde el auxiliar, la pareja, o quien dio la orden de mezcla?
4. **Cálculo de la gasolina: ¿pareja o jefe?** Abraham: la logística es tuya y pedís al jefe; Miguelito: faltar gasolina es *"mala coordinacion del piloto y ayudante"*. Celda DECIDE de la logística de combustible en conflicto (y quién asume el faltante).
5. **Aviso del RC por batería caliente:** Abraham dice que la mayoría de las veces **no** avisa; Miguelito se contradice en su propia respuesta ("no te avisa" / "te sale advertencia"). Vacío: cómo se objetiva "caliente" — ¿se dota termómetro IR o se registra cualitativo?
6. **Envases:** ¿se entregan en mano al dueño (Abraham) o se dejan ordenados en el lote (David)? ¿Se exige triple lavado como política o no se registra?
7. **Sobrante en cortinas: ¿quién registra?** Lo riega el piloto (David), pero el registro de sobrante vive en la app del auxiliar (espec §7.3/§8). Definir rol escritor — regla con ADR asociado (un rol escritor por registro): si cambia, pasa por `arquitectura`.
8. **Coadyuvante recuperador (all ok):** ¿quién lo autoriza y quién lo paga cuando el caldo se corta? El dueño suele negarlo y el daño lo absorbe el equipo. No asumir regla: pregunta abierta.
9. **Jornada 5:00–22:00:** ¿es vuelo nocturno real y habitual? ¿Con qué condiciones y cómo cambia la rotación de baterías y la carga del generador de noche?
10. **Conteo por tanque:** hoy nadie cuenta tanques (se cuentan hectáreas); el modelo por mezcla/recarga introduce un conteo nuevo. Validar en reunión que el flujo propuesto en §8 no agrega fricción percibida.
11. **EPP:** decisión del dueño pendiente — proveer EPP antes de exigir su confirmación en la app (si no, el checklist §7.3 nace muerto o miente).

---

## 10. Términos candidatos al glosario

| Término | Significado en campo |
|---|---|
| **calda / caldo** | La mezcla de aplicación (agua + productos). "Calda" es la forma usada por David. |
| **chaco** | El campo del cliente. |
| **cortinas** | Bordes arbolados del lote; ahí se aplica el sobrante y son la zona donde el dron "casi no puede llegar". |
| **repacar** | Reaplicar/repasar el sobrante sobre las cortinas u orillas (*"se lo repaca en las cortinas"*). |
| **quesillo** | Textura del caldo cortado (*"queda como quesillo"*). |
| **lodo** | Estado de un caldo degradado por días de espera. |
| **pines** | Contactos eléctricos de batería, dron y placa de conexión; su limpieza es crítica. |
| **placa (de conexión)** | Receptáculo eléctrico de la batería en el dron; dañada, propaga el daño a las demás baterías ("ciclo de daños"). |
| **RPM de bombas y centrífugas** | Indicador de salud del sistema de aspersión; su caída delata grumos en el tanque. |
| **all ok** | Coadyuvante comercial usado como recuperador de caldo cortado. |
| **RC / control** | Control remoto DJI; fuente de verdad de hectáreas, carga de batería y advertencias. |
| **motobomba** | Bomba para llenar de agua el tanque de mezcla; su ausencia triplica el tiempo de preparación. |
| **carga rápida / carga lenta** | Modos de carga del generador; la rápida (DJI, ~8 min) es la que sostiene la rotación de 3 baterías. |
| **generador DJI vs industrial** | El DJI tiene sistema inteligente (firmware recuperable vía RC) y mejor rendimiento de combustible; el industrial/genérico es más lento y solo se repara en taller. |
| **boleo** | Aplicación de sólidos al voleo con dron (término del entorno de los cuestionarios; no aparece en las respuestas de auxiliares — confirmar uso en otros roles). |
| **ha** | Hectáreas. |
