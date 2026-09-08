# Glosario del proyecto

**Agrocom SRL · Documento oficial vigente**

Referencia rápida de vocabulario — de negocio y técnico — para que cualquier persona o agente de IA nuevo en el proyecto entienda un término sin tener que leer toda la especificación. Cuando un término tiene definición formal en otro documento, se referencia en vez de repetirla completa.

## Negocio y operación

| Término | Definición | Referencia |
|---|---|---|
| **Campaña** | Ciclo productivo de **un cliente** (`2025-2026`): Agrocom no corre campañas propias, aplica dentro de la del cliente. Única por `(cliente_id, código)`, varias abiertas a la vez y solapadas — un cliente cosechando mientras otro siembra. **No hay campaña activa de sesión**: se elige dentro del cliente o del contrato (corrección del 8/9/2026). Se escribe `campania` en código — `campana` es el ícono de notificaciones | especificación §4.0, §5; ADR 0015 |
| **Hacienda / Propiedad** | Sinónimos de negocio de **campo** (`com_campos`): el predio del cliente. Un cliente tiene varias propiedades, cada una con varios lotes. En el panel el rótulo es "Propiedades"; el nombre de tabla no cambia | especificación §4.1 |
| **Personal** | Rótulo del menú para las personas operativas (`per_personas`). El personal es **móvil**: puede trabajar en propiedades, campañas, equipos y lotes distintos — si falta gente, se acopla la disponible | ADR 0015 |
| **Día completo** | Contrato sin ninguna ventana horaria cargada: se aplica a cualquier hora. Es el valor por defecto, y las horas desde/hasta nunca son obligatorias | especificación §4.1; ADR 0015 |
| **Estadía** | Registro de entrada y salida de un equipo de trabajo en una hacienda; dice cuántos días efectivos estuvo cada cuadrilla en cada propiedad | especificación §4.3 |
| **Equipo de trabajo** | La cuadrilla: el piloto y su auxiliar — **no** dónde están trabajando. Lleva además su equipamiento asignado (dron, vehículo, generador), y es la unidad a la que se imputa el gasto que no pertenece a ningún trabajo concreto. No confundir con `equipos` de la especificación §4.5, que es la vista unificada de maquinaria | especificación §4.2; ADR 0015 |
| **Gestión** | Como la usa el dueño, sinónimo de **campaña**: el período que se abre, se imputa y se cierra. El sistema la nombra "campaña"; "gestión" queda como la palabra contable equivalente | ADR 0015 |
| **Campaña activa** | La campaña bajo la que opera la sesión del panel, elegida igual que el rol activo y visible en el chip del header. **Filtra, no autoriza**: cambiarla no da acceso a nada nuevo | ADR 0015 |
| **Cuadrilla** | Lo mismo que **equipo de trabajo**, en la forma en que lo dice el equipo en campo | ADR 0015 |
| **Equipamiento asignado** | Los recursos (dron, vehículo, generador) que tiene asignado un equipo de trabajo, con vigencia. Es lo que permite saber qué unidad consumió el combustible que se imputó al equipo | especificación §4.2 |
| **Generador** | Grupo electrógeno del campamento; alimenta la carga rápida de baterías y consume combustible propio, imputable al equipo que lo tiene asignado | especificación §4.2, §4.4 |
| **Parámetros de vuelo** | Altura, velocidad y ancho de pasada acordados para volar. Hoy se pactan a voz entre piloto y agrónomo; el contrato fija los del cliente y la orden puede afinarlos | especificación §4.1, §4.3 |
| **Barbecho / presiembra** | Momento previo a sembrar, cuando se aplica para limpiar el terreno; es el `tipo_aplicacion = siembra` | especificación §4.3 |
| **Desecante** | Aplicación previa a la cosecha que seca el cultivo para poder cosecharlo parejo; es el `tipo_aplicacion = cosecha` | especificación §4.3 |
| **A aplicar** | Hectáreas pactadas menos hectáreas aplicadas: lo que falta cumplir de un contrato. Es la columna que totaliza el informe de avance | especificación §9.1 |
| **Cultivo** | Qué se sembró en un lote en una campaña (soya, maíz, girasol…). Es dimensión de la dupla lote-campaña, no atributo del lote: el lote no "es" de soya, se siembra de soya esta campaña | especificación §4.1 |
| **Tipo de aplicación** | Momento del ciclo en que se fumiga: `siembra` (barbecho o presiembra), `desarrollo` (el grueso de las 6-8 aplicaciones) o `cosecha` (desecante previo a cosechar) | especificación §4.3 |
| **Lote** | Superficie física dentro de un campo, con hectáreas y geometría propias, unidad sobre la que se aplica una orden | `docs/especificacion/...` §4.1 |
| **Trabajo** | Un lote en una aplicación concreta; agrupa una o más sesiones hasta cubrir sus hectáreas | especificación §4.3 |
| **Sesión** | Unidad de trabajo continua de un piloto con un dron dentro de un trabajo; se abre, se ejecuta, se cierra y se valida | especificación §4.3, §5 |
| **Orden de aplicación** | Instrucción del agrónomo (producto, dosis, L/ha) que habilita abrir un trabajo; sin orden vigente no se vuela | especificación §4.3 |
| **Receta de mezcla** | Secuencia de incorporación de productos definida por el agrónomo para una orden; Agrocom la ejecuta y documenta, no la modifica | especificación §4.3, §7 |
| **Mezcla** | Un tanque de caldo preparado según la receta; cada mezcla queda ligada a una sesión y a las hectáreas que cubrió | especificación §4.3, §7 |
| **Recarga** | Evento de reabastecimiento de caldo y cambio de batería durante una sesión | especificación §4.3 |
| **Incidencia** | Evento anómalo registrado durante una sesión (caldo, ESC, batería, mecánica, clima) con evidencia | especificación §4.3 |
| **Acta (de conformidad)** | Documento firmado por el agrónomo, por lote, que certifica lo aplicado — habilita la factura | especificación §4.3, §9 |
| **Devengo** | Monto que un piloto o auxiliar gana por una sesión validada; se genera solo al validar, nunca al cerrar | especificación §4.4, §5 |
| **Validación** | Acto de un jefe de campo o encargado (nunca el propio piloto de esa sesión) que confirma una sesión y dispara el devengo | especificación §5 |
| **Rendición** | Registro de gastos de campo con comprobante, presentado por el jefe de campo | especificación §4.4 |
| **Planilla** | Liquidación mensual de pagos para los cuatro roles operativos | especificación §11 |
| **Base** | Punto físico de operación (campamento, conectividad Starlink) desde donde se despliega al lote | `docs/negocio/ventana_al_negocio.md` §3 |
| **Hectárea acumulada (de partida)** | Valor que registra el piloto entrante en un relevo — evita el doble conteo cuando la misión de DJI se retoma | especificación §5 |
| **Solape** | Superposición real entre pasadas de vuelo; tolerado hasta un parámetro configurable antes de disparar `observado` | especificación §5, §16 |
| **EPP** | Equipo de protección personal del auxiliar (guantes, respirador, antiparras, ropa impermeable); se confirma al iniciar cada mezcla | especificación §7.3 |

## Vocabulario de campo (capturado en las encuestas del 25/8/2026)

Jerga real del equipo, registrada al procesar las respuestas del banco de preguntas (`docs/gestion/respuestas_campo/`). El detalle por rol vive en `docs/negocio/politicas/`.

| Término | Definición | Referencia |
|---|---|---|
| **Chaco** | El campo/terreno del cliente donde se fumiga (uso regional cruceño) | negocio/politicas/rol_piloto.md |
| **Caldo / calda** | La mezcla líquida de agua + productos que aplica el dron; "calda" es la forma habitual del equipo | especificación §7 |
| **Tanque de mezcla** | Recipiente en tierra (200–1.000 L) donde se prepara la calda; de ahí se recarga el dron. Cuando se habla de "tanque" hay que especificar cuál de los tres | especificación §7 |
| **Tanque del dron** | Depósito líquido del dron que se llena de caldo en cada vuelo (T30 20–26 L, T50 30–36 L, T70 ~50 L, T100 ~60 L); no se carga al máximo para cuidar las baterías | especificación §7.1 |
| **Tolva / boleadora** | Depósito de sólidos del dron para el boleo (esparcido de semilla/fertilizante); es un accesorio distinto del tanque líquido, con volumen propio | negocio/politicas/rol_jefe_campo.md |
| **Mapeo** | Planificación de la misión de vuelo sobre el lote en el RC; solo se hace con luz de día; los obstáculos marcados quedan registrados y el dron los rodea | negocio/flujo_base_y_excepciones.md |
| **Cortinas** | Bordes arbolados del lote; ahí se aplica ("repaca") el sobrante del último tanque | negocio/politicas/rol_auxiliar.md |
| **Sereno** | Humedad nocturna que hace resbalar la gota de la planta; límite natural del vuelo nocturno | negocio/politicas/rol_piloto.md |
| **Ventana horaria de aplicación** | Franjas típicas 6:00–10:00 y 16:00–20:00: con sol fuerte la gota se pulveriza antes de caer; modulable por cliente y clima | negocio/flujo_base_y_excepciones.md §3 |
| **Pausa atribuible** | Interrupción de la operación con causa registrada (clima / imprevisto del cliente / falla / logística); hoy no se registra y es la información que "falta siempre" | especificación (pendiente §4.3) |
| **Encargado de la propiedad** | Persona del cliente en campo: indica lotes, prepara el caldo, ordena pausas y recoge envases; opera mucho, no firma nada | negocio/politicas/rol_cliente.md |
| **Anemómetro ("medidor del tiempo")** | Instrumento con que el equipo mide el viento para decidir pausas y reanudaciones | negocio/politicas/rol_jefe_campo.md |
| **Captura del RC** | Foto de la pantalla del control remoto al cerrar un lote: hectáreas, tiempo, caudal, altura — la evidencia que hoy zanja toda disputa | especificación §4.3 |
| **Carta de confirmación de efecto laboral** | Pantalla de DJI al terminar un vuelo/misión: área de trabajo, pendiente, plan, margen, obstáculo, tiempo, litros y L/ha — el "reporte" que los pilotos fotografían | especificacion/analisis_capturas_rc.md |
| **Nube DJI** | Respaldo en línea de las misiones del RC; de ahí se descarga hoy el PDF para el reporte al cliente | negocio/politicas/rol_encargado_operaciones.md |
| **Inicio de sesión (RC)** | Registro de quién operó el control; junto a las capturas, prueba quién voló qué | negocio/politicas/rol_jefe_campo.md |
| **Pines** | Contactos eléctricos de batería y dron; su limpieza evita el "ciclo de daños" (pines sucios → placa del dron → daña las demás baterías) | negocio/politicas/rol_auxiliar.md |
| **RPM de bombas y centrífugas** | Indicador de salud del sistema de aspersión; una caída delata grumos en el tanque ("el enemigo silencioso") | negocio/politicas/rol_auxiliar.md |
| **Carga rápida** | Carga de batería en ~8 min con el generador DJI — menos que un vuelo (10–12 min): con 3 baterías el dron no espera | negocio/politicas/rol_auxiliar.md |
| **Boleo** | Aplicación de sólidos al voleo con dron (esparcido); línea de servicio adicional a la fumigación líquida | negocio/politicas/rol_jefe_campo.md |
| **Zona GEO** | Geocerca de DJI donde el dron no puede volar; exclusión técnica, no decisión del agrónomo | negocio/politicas/rol_agronomo.md |
| **Papel hidrosensible** | Tarjeta que revela la cobertura de gota; verificación de calidad deseada por el agrónomo | negocio/politicas/rol_agronomo.md |
| **Deriva** | Arrastre de la gota fuera del lote por viento; riesgo con vecinos y colmenas, y motivo de pausa | negocio/ventana_al_negocio.md §7 |
| **Lote bueno / lote feo** | Bueno: plano, recto, sin obstáculos. Feo: desniveles, árboles, bordes irregulares — menos ha/hora a igual tarifa; su reparto equitativo es la regla abierta de `ventana_al_negocio.md` §6.3 | negocio/politicas/rol_piloto.md |
| **Día volable / día perdido** | Día con ventana de aplicación aprovechable vs. día comido por lluvia o viento (≈1 de cada 4; 15–16 volables/mes) | negocio/politicas/rol_jefe_campo.md |
| **Trufi / encomienda** | Transporte interurbano usado para enviar repuestos al campo (4–48 h según distancia) | negocio/politicas/rol_encargado_operaciones.md |
| **QR (pago por)** | Medio de pago bancario usado para pagar al personal y respaldar rendiciones con capturas | negocio/politicas/rol_encargado_operaciones.md |
| **Aplicación de emergencia** | Trabajo spot para otro cliente, negociado por el encargado fuera del contrato residente | negocio/politicas/rol_encargado_operaciones.md |
| **Chata** | Carro de arrastre donde viaja el dron cuando no va en la camioneta | negocio/politicas/rol_piloto.md |
| **Ración seca** | Víveres de respaldo del equipo para cuando el cliente no provee alimentación | negocio/politicas/rol_jefe_campo.md |

## Técnico

| Término | Definición | Referencia |
|---|---|---|
| **`campania` vs. `campana`** | **`campania` (con `i`) es el ciclo productivo; `campana` es el ícono de notificaciones.** No son variantes de lo mismo y ya convivieron a una letra de distancia en `CascaraPanel`. Si vas a escribir `campana`, es porque estás tocando notificaciones | ADR 0015 |
| **Configuración del sistema** | `/panel/configuracion`: llaves y tokens con que el sistema funciona (mapas, correo, integraciones), cifrados en reposo y nunca devueltos al navegador. **Distinta** de `/panel/organizacion`, que son los datos de la empresa | ADR 0016 |
| **`campania_id`** | Obligatorio donde alguien elige la campaña explícitamente: `contratos` (de la campaña de su propio cliente) y `lote_campania`. **Nullable** en `gastos` y `cargas_combustible`, donde significa en qué campaña se **consumió** — atribución de costo interno, nunca un cargo al cliente, que paga por hectárea. No lo llevan órdenes, trabajos, sesiones, actas ni facturas (lo heredan por contrato), ni `equipos_trabajo` ni `estadias_hacienda` | ADR 0015 |
| **`uuid_cliente`** | Identificador único generado en el dispositivo de campo antes de sincronizar; garantiza idempotencia | especificación §2.1 |
| **Outbox** | Patrón de cola local (`cola_sync`) donde toda escritura offline se encola antes de sincronizar | especificación §2.1 |
| **Idempotencia** | Propiedad de que reintentar la misma operación no cambia el resultado; se logra con `UNIQUE (uuid_cliente)` en base, no en el código | ADR 0001, especificación §2.1 |
| **`sec_permission`** | Código de permiso abstracto (`operaciones.sesion.validar`) que el menú referencia — no al revés | ADR 0004 |
| **Policy** | Clase de Laravel que responde "¿puede este usuario, sobre ESTE registro?" — complementa a `sec_permission`, que responde "¿puede en general?" | ADR 0004 |
| **ADR** (Architecture Decision Record) | Documento corto en `docs/decisiones/` que registra una decisión de arquitectura: contexto, decisión, alternativas descartadas, consecuencias | `docs/README.md` |
| **`feature/*`** | Rama de GitFlow simplificado para una historia de usuario o tarea técnica; nace y muere en `develop` | ADR 0006 |
| **Soft delete** | Borrado lógico (`deleted_at`); ningún módulo hace `DELETE` físico salvo excepción justificada | ADR 0007 |
| **Bitácora (de auditoría)** | Registro transversal de quién hizo qué, cuándo, sobre qué entidad — no limitado a un módulo | ADR 0007 |
| **Atomic Design** | Metodología de componentes del panel: atoms/molecules/organisms/templates/pages en Blade | ADR 0002 |
| **Token (de diseño)** | Variable CSS (custom property) que representa un color/espaciado/tipografía — nunca se hardcodea un valor literal | ADR 0002 |
