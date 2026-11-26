# ADR 0045 — Mercado abierto sin zonas: filtros combinados, avisos por oleadas y etapa de negociación

**Estado:** aceptada, revisable · **Fecha:** 2026-11-26 · **Decide:** el dueño · **Reemplaza en parte:** ADR 0030 (puntos 6 y 7), 0034 (punto 6), 0038 (puntos 6, en lo que expira solicitudes, 7 y 12) y 0041 (puntos 1, 3, 4 y 6); aclara el punto 3 del ADR 0028. Revisado el 26/11/2026 con las decisiones del dueño (ver «Revisión»).

## Contexto

El marketplace nació con un mapa por zonas de Santa Cruz (valles, Chiquitania y otras), un precio de referencia por zona, avisos por zona, una distancia calculada desde una base declarada y el principio de que ningún proveedor tiene prioridad. Con la operación a la vista, esas piezas estorban:

- La app es pública. Un cliente de Santa Cruz puede pedir fumigar en San José o en Comarapa, y es el piloto quien decide si quiere ir. Una zona administrable, un radio o una base declarada solo agregan datos que alguien tiene que mantener y que no cambian la decisión del piloto; la distancia se acuerda en la negociación.
- Los precios los ponen el cliente y el proveedor. Un precio de referencia sin datos reales de mercado es una cifra inventada que ancla a las dos partes.
- Avisar a todos los proveedores a la vez satura y no premia a quien mejor trabaja. El cliente quiere ofertas de buena calidad pronto, y los pilotos propios de Agrocom y los mejor calificados deben tener la primera oportunidad de negociar.
- Mientras el cliente conversa con un piloto, el resto no tiene qué hacer con esa solicitud; y un cliente que ya conversa con dos no quiere más ofertas.
- Se contrata con mucha anticipación y un trato puede tardar semanas: una solicitud puede pedirse hoy para dentro de tres semanas o para marzo, y cerrarse a las tres semanas de publicada. Una vigencia fija, de siete días o de un año, la daría por perdida cuando el cliente todavía la quiere; quien decide cuándo cancelarla es el cliente.
- Sin notificaciones push (todavía), un aviso solo se ve cuando el piloto abre la app: los tiempos de las oleadas tienen que poder ser de horas y no de minutos sin cambiar el modelo.

El dueño decidió el modelo siguiente. Las apps no están en tiendas y el servidor de beta solo tiene datos de prueba, así que el contrato cambia de golpe, sin capas de compatibilidad.

## Decisión

### A. Sin zonas, sin distancia, sin precio de referencia

1. **Se retira el concepto de zona.** Salen `sol_zonas`, las macrorregiones, `sol_terrenos.zona_id`, `prv_proveedores.zona_base_id` y `radio_operacion_km`, las zonas de `neg_preferencias`, el resumen por zona (`GET /solicitudes-abiertas/resumen-zonas`), `GET /zonas`, la pantalla `panel/zonas` y sus permisos, y los filtros por zona o región del mapa, del panel y de los avisos. No hay restricción geográfica: todo proveedor habilitado puede ver y ofertar sobre cualquier solicitud que le sea visible (punto 23).
2. **Se retira el cálculo de distancia.** Sale `distancia_km` de la oferta y del mapa, el parámetro de posición (`lat`, `lon`) de la consulta del mapa, `orden=distancia` y la base aproximada del proveedor (que no llegó a existir). El piloto ve el lote dibujado y decide; cuánto hay que viajar lo acuerdan las partes. La ubicación del piloto sigue siendo solo la de `en_ejecucion` (ADR 0040).
3. **Ubicación de la solicitud: departamento y provincia seleccionables; el resto es texto.**
   - `sol_departamentos` (9) y `sol_provincias` (112) son un catálogo oficial de Bolivia, de solo lectura, sembrado con el código del INE y sin geometría, centro ni parámetros. No es una zona administrable: nadie lo edita desde el panel.
   - `sol_terrenos` guarda `departamento_codigo` y `provincia_codigo` (obligatorios para publicar una solicitud sobre el terreno) y, como texto libre, `municipio` y `comunidad`. Las indicaciones para llegar siguen en `referencias_acceso`. El polígono del lote ya existe y es la verdad geográfica.
   - Al publicar, la solicitud congela `departamento_codigo` y `provincia_codigo` (los de sus terrenos). Todos los terrenos de una solicitud están en el mismo departamento; si no, 422 `terrenos_en_departamentos_distintos`.
   - **Solo el departamento filtra** (mapa del piloto, panel, filtros de aviso y métricas). Provincia, municipio y comunidad informan.
   - Antes de ofertar el piloto ve departamento, provincia, municipio y comunidad junto con el polígono; el domicilio del cliente sigue oculto hasta adjudicar (invariante 13).
   - El catálogo se publica en `GET /catalogos/ubicaciones` (sin token), con los departamentos y sus provincias, para que las dos apps lo usen sin llevar una copia propia.
4. **Se retira el precio de referencia por completo.** Salen `sol_precios_referencia`, `PrecioDeReferencia`, `GET /referencias/precio`, los precios del parámetro `precio_referencia_ha`, el campo `precio_referencia_ha` de la estimación y su sembrado. Los precios los ponen el cliente y el proveedor. Se conserva el **precio máximo de sanidad** (parámetro): es un tope técnico contra errores de digitación, no una referencia. Es una decisión de la primera etapa; cuando haya precios acordados reales se puede evaluar una mediana histórica en un ADR nuevo.

### B. Alcance de una solicitud: filtros combinados con un mecanismo extensible

5. **Un criterio de alcance** decide si un proveedor es alcanzable por una solicitud. Es una clase que implementa `CriterioDeAlcance` (`Negociacion/Aplicacion/Alcance/`) y se registra en `AlcanceDeSolicitud`. Cada criterio declara:
   - `clave` (estable, p. ej. `departamento`, `dron_capacidad`),
   - `origen`: `sistema`, `cliente` (lee el valor de `requisitos` de la solicitud) o `proveedor` (lee el valor de `filtros` de sus preferencias),
   - `relajable`: si la escalera de oleadas puede quitarlo (punto 10),
   - `validar(valor)`: normaliza y rechaza valores inválidos (el valor decimal viaja como cadena exacta),
   - `cumple(solicitud, proveedor, paso)`: verdadero o falso.
   Agregar un filtro es agregar una clase, su prueba y, si lleva texto, una clave de idioma. **No exige migración**, porque los valores viajan en columnas JSON.
6. **Tres orígenes, combinados con Y.** Un proveedor es alcanzable en un paso cuando cumple todos los criterios no relajados en ese paso.

   | Origen | Dónde se declara | Criterios iniciales | Relajables |
   |---|---|---|---|
   | Sistema | Reglas del sistema | `aprobado` (proveedor aprobado con al menos un equipo apto), `disponible` (punto 8), `sin_rechazo_previo` (el cliente no rechazó a ese proveedor en esa solicitud), `calificacion` (mínimo de calificación del paso; los propios siempre pasan) | `calificacion` sí; los otros no |
   | Cliente | `sol_solicitudes_servicio.requisitos` (JSON) | `dron_capacidad` (capacidad mínima del tanque, en litros) | Sí |
   | Proveedor | `neg_preferencias.filtros` (JSON), en los ajustes de su app | `departamentos_excluidos`, `hectareas_minimas`, `tipos_aplicacion_excluidos` | No |

   La lista **no está cerrada**. Candidatos todavía sin definir: precio mínimo por hectárea del proveedor, cultivo, época. Cada uno entra por el mecanismo del punto 5 con su propio PR.
7. **Reglas del mecanismo.**
   - Un criterio sin valor declarado no restringe.
   - Una clave desconocida guardada en `requisitos` o `filtros` se ignora al evaluar y se conserva (un cliente nuevo no rompe a un servidor viejo).
   - Los filtros del proveedor son preferencias suyas: la escalera nunca los relaja. Si el proveedor dijo «no acepto Pando» o «menos de 50 ha no», no recibe esos avisos aunque la solicitud no tenga oferta.
   - Los filtros del proveedor **solo gobiernan avisos**. No ocultan el mapa: ahí el proveedor aplica los filtros que quiera en el momento.
   - Una prueba de arquitectura exige que todo criterio registrado tenga prueba propia y que la escalera de configuración nombre solo criterios relajables.
8. **Mi disponibilidad** es un estado manual, sin calendario, con tres valores: `disponible`, `ocupado` y `en_trabajo`. `ocupado` y `en_trabajo` tienen el mismo efecto (a esa cuenta no se le avisa de nuevas solicitudes) y se distinguen solo por el texto: «Ocupado» es «no puedo tomar trabajo ahora» y «En trabajo» es «estoy fumigando». Un proveedor no disponible sigue viendo el mapa y puede ofertar: solo se le deja de avisar. Las reglas, en lenguaje sencillo, están en «Disponibilidad, en simple» (más abajo).

#### Disponibilidad, en simple

Es un interruptor, como el «No molestar» de un teléfono. Cada persona que trabaja como proveedor lo tiene en su app: «Disponible» (estoy libre, avísenme de los trabajos nuevos), «Ocupado» o «En trabajo» (no me avisen). No es un calendario ni el sistema lo adivina: lo mueve la persona. Sirve para una sola cosa: que no le lleguen avisos de solicitudes nuevas cuando no puede tomarlas, y que el cliente no espere a alguien que no va a responder. No oculta nada: quien está ocupado sigue viendo el mapa y puede ofertar si quiere.

La pregunta que quedaba era de quién es el interruptor cuando hay una empresa con varios pilotos: ¿uno solo para toda la empresa, o uno por piloto? Decisión por defecto (revisable):

- **Cada cuenta tiene su interruptor.** El titular (en un proveedor independiente, que es a la vez titular y piloto, es el único) en `prv_proveedores.disponibilidad`; cada piloto vinculado a un proveedor tercero en `prv_pilotos.disponibilidad`. Cada uno tiene además la fecha del último cambio y quién lo cambió.
- **A una cuenta se le avisa solo si su interruptor está en `disponible`** y, si es un piloto de una empresa, también el de su empresa. Así, si la empresa se marca ocupada, no avisa a ningún piloto suyo; si un piloto se marca ocupado, solo se silencia a él.
- **Quién lo cambia:** la propia cuenta (`PUT /proveedores/mi/disponibilidad`) y, además, el encargado de la empresa (el titular) puede marcar a un piloto suyo (`PUT /proveedores/pilotos/{id}/disponibilidad`) o a la empresa. Gana el último cambio; la app del piloto muestra quién lo cambió («Tu empresa te marcó como ocupado»).
- **Un proveedor cuenta como alcanzado** en un paso si al menos una de sus cuentas recibe el aviso. Si ninguna puede recibirlo en ese paso, no queda registrado como avisado.
- **Al volver a «Disponible» no le llegan avisos atrasados.** Si hay una oleada en curso, puede alcanzarlo en su siguiente paso, como a cualquiera que cumpla; pero nunca recibe un aviso de pasos que ya ocurrieron ni de solicitudes cuya oleada ya terminó o está en pausa. Sigue viendo el mapa en todo momento.
- **«En trabajo» se marca a mano.** Que el sistema lo ponga solo al iniciar un servicio queda fuera de esta etapa.

### C. Avisos por oleadas («sistema solar»)

9. **Qué es una oleada.** La solicitud es una ola solar que sale del centro y los proveedores son planetas en anillos de prioridad. La **primera ola** alcanza a los proveedores que cumplen **todos** los criterios (paso 0, el anillo más cercano). Pasado el intervalo T sin que nadie acepte ni contraoferte, la ola se relanza **saltando a quienes ya la recibieron** y alcanza solo al siguiente anillo: quienes cumplen cuando se quita o afloja un criterio relajable más. Así, anillo por anillo, hasta que ya no quedan criterios relajables. Entonces sale la **súper ola**: todos los elegibles, es decir, todo proveedor aprobado con equipo apto, disponible y que no excluyó esa solicitud con sus propios filtros. Nunca se avisa a un `ocupado` ni `en_trabajo`. Las apps no calculan nada de esto: reciben el aviso y ven la solicitud.
10. **La escalera.** Es una lista ordenada en configuración (`config/negociacion.php`, ADR 0016). Cada paso declara los criterios que relaja y los parámetros que cambia, de forma acumulativa. El último paso es la súper ola. La escalera inicial la fijó el criterio técnico por encargo del dueño; es una decisión revisable:

    | Paso | Qué cambia | Quién recibe aviso (además de lo ya avisado) |
    |---|---|---|
    | 0 | Todo. Calificación mínima alta (4,50) | Propios de Agrocom (entran desde la primera ola) y proveedores con calificación alta que cumplen todos los criterios |
    | 1 | Calificación mínima media (4,00) | Los que ahora cumplen |
    | 2 | Sin mínimo de calificación | Todos los demás que cumplen los criterios no relajables |
    | 3 (súper ola) | Se quita el requisito de dron del cliente | Los elegibles cuyo dron no cumple el requisito (punto 18): ya no queda nadie elegible sin avisar |

    - T es `negociacion.oleadas.intervalo_minutos`. Mientras no haya push vale 60 (punto 28); con push baja a unos 2 a 5 minutos. El modelo es el mismo.
    - Un paso que no tiene a nadie nuevo que avisar no espera: la oleada pasa al siguiente en la misma pasada del reloj.
    - Los filtros del proveedor, la disponibilidad y la aprobación **nunca** se relajan, ni siquiera en la súper ola.
    - La prioridad es por el tiempo que la solicitud lleva en el ciclo: los propios de Agrocom y los mejor calificados tienen la primera oportunidad de negociar y luego se abre al resto.
    - Un proveedor con menos de `negociacion.oleadas.calificaciones_minimas_para_contar` calificaciones (3) cuenta como sin calificación, salvo que sea propio.
11. **Si nadie acepta ni contraoferta: «esperando piloto».** Cuando la súper ola sale y nadie ofertó, la solicitud queda **pendiente de piloto**; el nombre visible propuesto es «Esperando piloto» (a confirmar por el dueño; no se usa «tomada», y se evita «pendiente» solo porque ya nombra a un proveedor sin aprobar). El sistema **no la desactiva, no la cancela ni la vence**: puede esperar tres semanas o más hasta cerrar trato. Sigue visible para todo proveedor habilitado (punto 23). La oleada queda `esperando` y se relanza así:
    - **A mano.** El cliente ajusta lo que quiera (precio, requisitos, fecha deseada) y **vuelve a lanzar la ola** (`POST /solicitudes/{id}/busqueda/relanzar`), o la cancela.
    - **Automático, con el horario del cliente.** El cliente elige los días de la semana y la hora (`PUT /solicitudes/{id}/busqueda/programacion`): **mínimo 2 días por semana y como máximo un relanzamiento por día**, con una sola hora por día (días y hora a elegir; hora de Bolivia). Si no relanza a mano, el sistema relanza según ese horario. Si el cliente no eligió nada, rige el valor por defecto de `negociacion.oleadas.relanzamiento` (martes y viernes a las 07:00; hasta que elija).
    - **Un relanzamiento es un ciclo nuevo desde el paso 0.** Vuelve a avisar a los del primer anillo; **nadie recibe dos veces el mismo aviso dentro de un ciclo**, pero sí uno nuevo en el ciclo siguiente. No hay tope de ciclos.
    - **Tope de un ciclo nuevo por día calendario**, contando los automáticos y los manuales, para no saturar a los pilotos (409 `relanzamiento_demasiado_pronto`; decisión revisable).
    - Un horario que cae mientras el ciclo está en marcha, la solicitud está en negociación o la búsqueda está detenida no hace nada y no se acumula. Solo relanza una oleada `esperando`.
12. **Nunca se avisa dos veces al mismo proveedor en un ciclo.** El registro de avisos (`neg_oleada_avisos`) tiene `UNIQUE (solicitud_id, proveedor_id, ciclo)`; se inserta antes de emitir el aviso y solo se emite para las filas realmente insertadas. El aviso del motor de notificaciones (ADR 0025) usa como clave `nueva_solicitud_abierta:{solicitud}:{ciclo}`, y su `UNIQUE (usuario_id, clave_evento)` es la segunda barrera. Un aviso insertado y no entregado queda con `entregado_en` nulo y la siguiente pasada del reloj lo reintenta; reintentar es seguro por la misma unicidad. El reloj es idempotente: correr la pasada dos, tres o diez veces deja las mismas filas.
13. **El reloj** es el comando `negociacion:procesar` que ya corre cada minuto (ADR 0038 punto 6); procesa las oleadas con `proxima_en` vencida y los relanzamientos con `proximo_relanzamiento_en` vencido. Cada oleada se procesa en su propia transacción: bloquea primero la fila de la solicitud, después las de sus ofertas y al final la de su oleada (se extiende el orden del invariante 12), y un fallo en una solicitud se reporta sin detener a las demás. La primera oleada (paso 0) corre en el acto al publicar, sin esperar al reloj.
14. **Preferencias de aviso.** Si la cuenta apagó `nuevas_solicitudes`, no recibe estos avisos en ningún paso. Las categorías obligatorias del ADR 0041 no cambian.

### D. Etapa de negociación y búsqueda detenida

15. **Etapa de negociación (nombre visible: «En negociación»).**
    - **Cuándo empieza.** Cuando un proveedor toma la solicitud para negociar: en el servidor es el **primer registro de una oferta** del proveedor (`POST /solicitudes/{id}/ofertas`, de tipo `acepta_propuesta` o `contraoferta`), que es lo que la app envía cuando el piloto acepta el precio o propone el suyo en la pantalla de precios. **Entrar a esa pantalla sin enviar nada no cambia nada en el servidor** y no esconde la solicitud a los demás.
    - **Qué cambia.** Desde ese registro la solicitud deja de ofertarse y de aparecer a los demás pilotos y las oleadas se pausan, hasta que el cliente cierre esa negociación o se cierre el trato. Quien ya tiene una negociación en esa solicitud la sigue viendo. No es un estado nuevo de la máquina: se calcula de los hechos. Está en negociación cuando el número de negociaciones con una oferta `vigente` alcanza `negociacion.etapa.cupo_negociaciones` (1 por defecto; con 1, la etapa coincide con `con_ofertas`). El parámetro existe por si el dueño decide que el cliente compare varias ofertas a la vez (decisión revisable).
    - **Carrera entre dos pilotos.** Si dos pilotos tienen la pantalla de precios abierta y uno envía primero, el servidor (que decide bajo el bloqueo de la solicitud) responde al segundo 409 `solicitud_no_disponible` y la app le explica que otro piloto ya negocia y que, si no llegan a acuerdo, la solicitud vuelve a ofertarse.
    - **Cuándo termina.** Al adjudicarse, o cuando la oferta vigente deja de estarlo sin acuerdo: el cliente la rechaza, el proveedor la retira o vence según su parámetro (`negociacion.vigencia_oferta_horas`). Sin acuerdo, la solicitud **vuelve a ofertarse**: reaparece en el mapa de quienes la veían y la oleada continúa desde donde estaba, con el reloj del siguiente paso reiniciado (si estaba `esperando`, vuelve a `esperando` y sigue el horario del cliente). A quien el cliente rechazó no se le vuelve a avisar en esa solicitud (criterio `sin_rechazo_previo`). La palabra «tomada» no se usa.
16. **«No seguir buscando más pilotos».** El cliente puede detener la búsqueda (`POST /solicitudes/{id}/busqueda/detener`) cuando ya conversa con quien le interesa y reanudarla (`/busqueda/reanudar`): las oleadas y los relanzamientos automáticos se detienen, la solicitud no se abre a más proveedores que los que ya la ven y sigue disponible para ellos. Es independiente de la etapa de negociación: si esa negociación termina sin acuerdo y la búsqueda está detenida, la solicitud no vuelve a ofertarse hasta que el cliente la reanude. Al reanudar, la oleada vuelve al estado que tenía (en marcha o `esperando`). Nombre visible: «Sin buscar más pilotos». Adjudicar o cancelar la solicitud cierra la oleada.
17. **Qué ve el cliente.** La solicitud trae `etapa` (`buscando`, `en_negociacion`, `busqueda_detenida` o `esperando_piloto`; si coinciden, `en_negociacion` manda sobre las demás) y `busqueda` con cuántos proveedores se avisaron, el ciclo, el horario de relanzamiento y cuándo es el próximo. Nunca quiénes.

### E. Dron que no cumple

18. **No se bloquea.** Si al ofertar el equipo no cumple un requisito del cliente, la oferta se crea igual y guarda `incumple` (lista de claves de criterio que no cumple). El cliente la ve marcada en el comparador («tu pedido era un dron de 20 L; este es de 10 L») y el aviso de la oferta lo dice. Si todas las ofertas vigentes de la solicitud incumplen, el aviso de oferta recibida lo informa de forma explícita. El piloto ve el requisito antes de ofertar y la hoja de oferta le avisa que su dron no cumple.
19. **«Tipo de dron» es la capacidad mínima del tanque** (litros), un atributo que ya existe en `flo_drones.capacidad_tanque_l`. Las apps ofrecen opciones predefinidas y el servidor guarda el número. Ampliarlo a otro atributo es agregar un criterio.

### F. La solicitud no caduca: fecha deseada, oferta con vigencia

20. **La solicitud no vence sola.** Una solicitud sin adjudicar sigue en el mercado hasta que se adjudica o el cliente la cancela; el sistema no la desactiva ni la expira (puede esperar semanas). Se retiran la vigencia de la solicitud (`vence_en`, `vigencia_solicitud_dias`, `vigencia_solicitud_max_dias`), el estado `expirada`, la renovación (`POST /solicitudes/{id}/renovar`, `plazo_renovacion_dias`) y el aviso de vencimiento. Sí puede llevar una **fecha deseada** de fumigación (`ventana_desde` y `ventana_hasta`), hasta `negociacion.anticipacion_maxima_dias` (365) en el futuro, que es solo una validación de sanidad. En su lugar el servidor **recuerda al cliente** cada `negociacion.pendiente.recordatorio_dias` (7) que la solicitud sigue sin trato («ajusta el precio o los requisitos, o cancélala») y, si pasa la fecha deseada sin trato, le avisa y deja de relanzarla sola hasta que actualice la fecha (decisión revisable); la solicitud no se cancela.
21. **La oferta sí vence.** `negociacion.vigencia_oferta_horas` es un parámetro en horas que admite cualquier valor (720 son 30 días). El valor por defecto no cambia. Que cada parte elija la validez de su oferta dentro de un rango es una mejora posible, no parte de esta etapa.
22. **Cómo se relacionan fecha, oleadas y relanzamientos.** Las oleadas miden el tiempo desde que empieza cada ciclo, no desde la fecha deseada: dar a conocer la solicitud a los proveedores es urgente aunque el trabajo sea en tres semanas. La fecha deseada solo se muestra, ordena y filtra el mapa. La verificación de equipo libre al adjudicar (negociacion-contrato §3.6) sigue comparando rangos de fecha deseada.
23. **Visibilidad en el mapa.** Un proveedor habilitado ve una solicitud `abierta` o `con_ofertas` cuando:
    - tiene una negociación en ella, o
    - la solicitud no está en negociación y, o bien ya se abrió a todos, o bien el proveedor fue alcanzado por alguna oleada.

    Una solicitud se **abre a todos** cuando sale la súper ola (el último paso de la escalera); desde entonces queda visible para todo proveedor habilitado, también mientras está `esperando` y en los ciclos siguientes. Así la prioridad de los primeros pasos también es visible y no solo un aviso retrasado. Detener la búsqueda congela este alcance. Ofertar sobre una solicitud que el proveedor no ve responde 409 `solicitud_no_disponible`. El proveedor que aún no está habilitado no ve el mapa: solo el contador del punto 29.

### G. Orden de las ofertas ante el cliente

24. El comparador usa por defecto `orden=recomendado`: primero las de proveedores `propio`; dentro de cada grupo, mayor calificación (quien tiene menos de las calificaciones mínimas va al final del grupo); luego menor precio por hectárea; luego la más antigua. El cliente cambia el orden a `precio`, `calificacion` o `reciente`. Un proveedor propio no tiene ventaja en la adjudicación: el cliente decide (ADR 0030 punto 5); el orden es una sugerencia que solo cambia la presentación.

### H. Contrato y datos

25. **El contrato cambia de golpe.** Sin capas de compatibilidad ni parámetros ignorados: salen los recursos y los campos retirados y entran los nuevos. La lista exacta está en el plan de implementación y se refleja en `docs/api/openapi.yaml` (generado) y en `docs/especificacion/negociacion-contrato.md`.
26. **Sobre el invariante 15 (respaldo en lugar de borrar): excepción explícita y acotada.** La regla protege datos que pueden ser reales. En este cambio **no aplica**, por tres hechos verificables: (a) el servidor de beta solo tiene datos de prueba y las apps no están en tiendas; (b) los catálogos que se retiran (`sol_zonas`, `sol_precios_referencia`) son semillas que se regeneran con sus sembradores y no contienen decisiones de nadie; (c) los valores por zona de terrenos, proveedores y preferencias son datos de demostración; (d) la vigencia de la solicitud (`vence_en`) y el estado `expirada` salen del modelo (punto 20) y las solicitudes `expiradas` de beta son de demostración: pasan a `cancelada` antes de quitar la columna. Por eso las migraciones de retiro pueden hacer `DROP` de las tablas y columnas retiradas sin copiarlas a un esquema `respaldo_*`, siempre como migraciones nuevas (no se editan las anteriores), con `down()` que recrea la estructura vacía y con la prueba contra PostgreSQL. Después se reconsolidan las migraciones con `bin/consolidar-migraciones` (ADR 0024) y se reinicia beta con datos de demostración. La exención **caduca** si antes de integrar la migración de retiro existe un dato real en beta; el PR lo confirma por escrito. Cualquier retiro posterior vuelve a regirse por el invariante 15.

### I. Ajustes del cliente, avisos sin push y proveedores aún no habilitados

27. **Ajustar antes de relanzar.** Mientras la solicitud está `abierta` o `con_ofertas` y no hay una negociación vigente, el cliente puede cambiar el precio propuesto (`POST /solicitudes/{id}/precio`, que ya existe) y, además, los requisitos y la fecha deseada (`PUT /solicitudes/{id}/ajustes`). Ajustar no relanza por sí solo: el cliente relanza a mano o espera su horario. Cada ajuste queda en la bitácora.
28. **Sin push, los avisos viven dentro de la app.** El push no se implementa en esta etapa: se aplicará después con Firebase (FCM y APNs) o con Supabase, según lo que se decida entonces, y el registro de dispositivos (`POST /dispositivos`) ya existe. Hasta entonces:
    - el servidor deja el aviso en la lista de notificaciones (`ntf_notificaciones`, `GET /notificaciones`, ADR 0025), con la clave de idempotencia del punto 12;
    - la app del piloto lo muestra dentro: una **campana con el contador de no leídos y una lista de avisos**, que se consulta al abrir la app, al volver a primer plano y al refrescar a mano (un sondeo en primer plano es opcional);
    - **T se configura más largo** (60 minutos) para que la prioridad tenga sentido: con T de 2 minutos nadie vería el aviso antes de que la súper ola ya haya salido. Cambiar T no cambia el modelo ni exige una versión nueva de las apps; con push se baja.
    - **Trabajo que hace falta en la app del piloto para que las oleadas sirvan:** hoy solo el cliente consulta `GET /notificaciones`; el piloto debe consultar los avisos propios, mostrar la lista con contador, marcar leídos y abrir la solicitud desde el aviso (que el servidor ya le hace visible por haberlo alcanzado). Es la fase F4 del plan, y sin ella las oleadas no llegan a nadie.
29. **El proveedor aún no habilitado ve solo un contador.** Una cuenta de proveedor cuyo proveedor está `pendiente` de aprobación, o aprobado pero sin un equipo apto, no ve el mapa, el detalle ni puede ofertar (403 `proveedor_no_habilitado`). Sí ve cuántos trabajos se pierde: `GET /solicitudes-abiertas/contador` devuelve `total`, la cantidad de solicitudes `abierta` o `con_ofertas`, con el parámetro opcional `departamento`. Sin lotes, clientes, precios, etapa ni ubicación más fina que el departamento. La app dice «Hay N solicitudes abiertas que no puedes ver hasta que te aprueben». Un proveedor rechazado, suspendido o dado de baja no lo recibe (403).

## Decisiones revisables

Marcadas para revisión del dueño con datos de operación:

- La condición que dispara la etapa de negociación (punto 15) y su cupo de una negociación a la vez.
- Que los propios y los mejor calificados tengan prioridad en el aviso, en la visibilidad del mapa (punto 23) y en el orden de ofertas (punto 24), y los umbrales de calificación.
- El orden de relajación de la escalera (punto 10), que fijó el criterio técnico, y que los filtros del proveedor no se relajen ni en la súper ola.
- El valor de T, el horario por defecto del relanzamiento, el tope de un ciclo por día y el recordatorio al cliente (puntos 10, 11 y 20).
- El nombre visible «Esperando piloto» (punto 11).
- Qué pasa cuando pasa la fecha deseada sin trato: sigue en el mercado, se avisa al cliente y deja de relanzarse sola (punto 20).
- La disponibilidad por cuenta, que el encargado pueda marcar a sus pilotos y que «En trabajo» sea manual (punto 8).
- Que la provincia no filtre (punto 3).
- La lista de filtros iniciales (punto 6), abierta por definición.

## Alternativas descartadas

- **Mantener las zonas con otra forma** (radio, polígonos de cobertura, «zona base»): exige mantener un catálogo y declara una restricción que el modelo abierto no quiere.
- **Calcular distancia desde una base declarada por el proveedor**: dato personal sin función en la decisión del cliente; la distancia se acuerda en la negociación.
- **Mantener un precio de referencia sin zona**: sigue siendo una cifra sin respaldo real que ancla la negociación.
- **Avisar a todos a la vez** (como hasta ahora) o **asignar al mejor calificado**: lo primero satura y no premia; lo segundo quita al cliente la elección (ADR 0030).
- **Estado nuevo `en_negociacion` en la máquina de la solicitud**: obliga a tocar todas las transiciones, las apps y el panel para algo que se deduce de las ofertas y que se corrige solo cuando una oferta vence o se rechaza.
- **Bandera guardada de «en negociación»**: puede quedar desactualizada si una oferta vence; el cálculo desde las ofertas no.
- **Reiniciar la oleada de inmediato al agotar la escalera**: avisa a los mismos proveedores cada pocos minutos durante semanas.
- **Reinicio automático tras 24 horas con un máximo de 2 reinicios** (primera versión de este ADR): el sistema decidía cuándo molestar a los pilotos y daba por perdida la solicitud sin que el cliente lo decidiera; ahora el horario es del cliente y no hay tope de ciclos.
- **Vigencia de la solicitud con tope (7 días, y luego hasta 365 días) y estado `expirada`**: un trato puede tardar semanas y la solicitud sigue siendo querida; quien decide cuándo cancelar es el cliente. El recordatorio periódico cubre el olvido.
- **Que la etapa de negociación empiece cuando el cliente responde a una oferta** (primera versión): dejaba a varios pilotos negociando a la vez sobre algo que el cliente ya estaba decidiendo y no coincidía con la decisión del dueño de que la solicitud deje de ofertarse desde que un proveedor la toma.
- **Una sola disponibilidad por proveedor, que solo cambia el titular** (primera versión): un piloto de una empresa no podía decir «hoy no» y el encargado no podía silenciar a uno solo.
- **Mostrar el mapa a quien no está aprobado**: expone lotes y datos del cliente a quien no ha pasado la verificación; el contador muestra lo que pierde sin exponer nada.
- **Calendario de disponibilidad**: más piezas que mantener que el interruptor que el piloto sí actualiza; el calendario real queda fuera de esta etapa.
- **Una tabla de reglas de filtro editable en el panel**: el conjunto de criterios cambia con el código y con las pruebas; solo la escalera y los parámetros son configuración.
- **Bloquear la oferta de un dron que no cumple**: el cliente puede aceptar un dron menor si el precio le conviene; se le informa y decide.
- **Conservar los parámetros viejos en el contrato por una ventana de compatibilidad**: no hay apps fuera del equipo.

## Consecuencias

- Tablas nuevas del módulo `Negociacion`: `neg_oleadas` (una fila por solicitud, con estado `activa`, `en_pausa`, `detenida`, `esperando` o `terminada`, ciclo, paso, `proxima_en` y la programación del relanzamiento: días, hora, `proximo_relanzamiento_en` y `ultimo_relanzamiento_en`), `neg_oleada_pasos` (qué pasó en cada paso, solo se inserta) y `neg_oleada_avisos` (registro de avisos con su unicidad). Columnas nuevas: `sol_solicitudes_servicio.requisitos`, `.departamento_codigo`, `.provincia_codigo`; `sol_terrenos.departamento_codigo`, `.provincia_codigo`, `.municipio`, `.comunidad`; `prv_proveedores.disponibilidad` y `prv_pilotos.disponibilidad` (con fecha y autor del cambio); `neg_preferencias.filtros`; `neg_ofertas.incumple`. Sale `sol_solicitudes_servicio.vence_en` y el estado `expirada`.
- Parámetros nuevos o cambiados en `config/negociacion.php`: `oleadas.intervalo_minutos` (60 sin push), `oleadas.escalera`, `oleadas.relanzamiento` (días y hora por defecto, mínimo de días), `etapa.cupo_negociaciones` (1), `pendiente.recordatorio_dias` (7). Salen `vigencia_solicitud_dias`, `vigencia_solicitud_max_dias`, `plazo_renovacion_dias`, `aviso_vencimiento_dias`, `oleadas.reinicio_horas`, `oleadas.max_reinicios` y `oleadas.paso_apertura_general`.
- Recursos nuevos: `POST /solicitudes/{id}/busqueda/relanzar`, `PUT /solicitudes/{id}/busqueda/programacion`, `PUT /solicitudes/{id}/ajustes`, `PUT /proveedores/pilotos/{id}/disponibilidad`, `GET /solicitudes-abiertas/contador`. Retirado: `POST /solicitudes/{id}/renovar`.
- Se reemplaza el oyente `AvisarNuevaSolicitud` por el inicio de la oleada. Las apps dejan de armar avisos locales a partir del mapa: el aviso lo decide el servidor.
- El tablero y las métricas pasan de «por región» a «por departamento» (la vista `vw_metricas_solicitudes` se recrea), suman tiempo hasta la primera oferta por paso de la escalera y cuentan las solicitudes esperando piloto y los relanzamientos en lugar de las expiradas.
- La app del piloto necesita la lista de avisos recibidos (campana, contador, consulta al abrir) para que las oleadas lleguen a alguien mientras no haya push (punto 28).
- Hay una zona de revisión línea por línea nueva: el motor de oleadas (idempotencia del registro de avisos, orden de bloqueos, visibilidad del mapa, relanzamientos). Se agrega a la lista de «qué no delegar» de `CLAUDE.md` cuando se implemente.
- La política (`politicas/negociacion.md`), la especificación (`marketplace.md`, `negociacion-contrato.md`) y el glosario describen este modelo. Se implementa por fases, una tarea y un PR por fase, con revisión posterior del dueño en las piezas críticas.

## Revisión (26/11/2026) — decisiones del dueño sobre etapa, oleadas, vigencia, push y disponibilidad

El dueño revisó la primera versión de este ADR y decidió lo siguiente; manda sobre lo que antes decía el documento, que se corrigió arriba en presente:

1. **Etapa de negociación.** Antes empezaba cuando el cliente respondía a una oferta (oferta vigente con `numero >= 2`). Ahora empieza cuando un proveedor toma la solicitud para negociar: el primer registro de oferta en el servidor (punto 15). Entrar a la pantalla de precios sin enviar nada no cambia nada. Si la negociación termina sin acuerdo la solicitud vuelve a ofertarse. Se agrega el criterio `sin_rechazo_previo`.
2. **Oleadas como anillos y súper ola.** El último paso es la súper ola a todos los elegibles (nunca ocupados). Si nadie acepta, la solicitud queda «Esperando piloto» y el sistema no la desactiva ni la vence (puntos 9 a 11). Antes la escalera agotada se reiniciaba a las 24 horas, hasta 2 veces; esos parámetros salen. Cada ciclo nuevo vuelve a avisar y nadie recibe dos veces el mismo aviso dentro de un ciclo. `paso_apertura_general` sale: la solicitud se abre a todos con la súper ola.
3. **Escalera de relajación.** Queda a criterio técnico y se adopta la propuesta del plan, marcada revisable (punto 10).
4. **Relanzamiento y vigencia.** El relanzamiento automático lo parametriza el cliente: mínimo 2 veces por semana, máximo 1 por día, con días y hora a elegir. Reemplaza el reinicio tras 24 horas y la vigencia con tope de 365 días: la solicitud no caduca sola y se retiran `expirada`, la renovación y el aviso de vencimiento. La oferta sí vence según su parámetro (puntos 11 y 20).
5. **Push.** No se implementa ahora; los avisos se ven dentro de la app y T es más largo hasta que haya push (punto 28).
6. **Disponibilidad.** Por cuenta, con interruptor propio de cada piloto y control del encargado; explicada en lenguaje sencillo en el punto 8. Antes era un solo valor por proveedor que solo cambiaba el titular.
7. **Proveedor aún no habilitado.** Ve un contador de solicitudes abiertas, no el mapa (punto 29).
8. **Lista de filtros.** Sigue sin cerrarse; el mecanismo extensible del punto 5 es la decisión, no la lista.
