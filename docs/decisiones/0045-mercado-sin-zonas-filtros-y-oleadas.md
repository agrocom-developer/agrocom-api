# ADR 0045 — Mercado abierto sin zonas: filtros combinados, avisos por oleadas y etapa de negociación

**Estado:** aceptada, revisable · **Fecha:** 2026-11-26 · **Decide:** el dueño · **Reemplaza en parte:** ADR 0030 (puntos 6 y 7), 0034 (punto 6), 0038 (puntos 7 y 12) y 0041 (puntos 1, 3, 4 y 6); aclara el punto 3 del ADR 0028.

## Contexto

El marketplace nació con un mapa por zonas de Santa Cruz (valles, Chiquitania y otras), un precio de referencia por zona, avisos por zona, una distancia calculada desde una base declarada y el principio de que ningún proveedor tiene prioridad. Con la operación a la vista, esas piezas estorban:

- La app es pública. Un cliente de Santa Cruz puede pedir fumigar en San José o en Comarapa, y es el piloto quien decide si quiere ir. Una zona administrable, un radio o una base declarada solo agregan datos que alguien tiene que mantener y que no cambian la decisión del piloto; la distancia se acuerda en la negociación.
- Los precios los ponen el cliente y el proveedor. Un precio de referencia sin datos reales de mercado es una cifra inventada que ancla a las dos partes.
- Avisar a todos los proveedores a la vez satura y no premia a quien mejor trabaja. El cliente quiere ofertas de buena calidad pronto, y los pilotos propios de Agrocom y los mejor calificados deben tener la primera oportunidad de negociar.
- Mientras el cliente conversa con un piloto, el resto no tiene qué hacer con esa solicitud; y un cliente que ya conversa con dos no quiere más ofertas.
- Se contrata con mucha anticipación: una solicitud puede pedirse hoy para dentro de tres semanas o para marzo, y una vigencia de siete días no lo permite.

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
   | Sistema | Reglas del sistema | `aprobado` (proveedor aprobado con al menos un equipo apto), `disponible` (punto 8), `calificacion` (mínimo de calificación del paso; los propios siempre pasan) | `calificacion` sí; los otros no |
   | Cliente | `sol_solicitudes_servicio.requisitos` (JSON) | `dron_capacidad` (capacidad mínima del tanque, en litros) | Sí |
   | Proveedor | `neg_preferencias.filtros` (JSON), en los ajustes de su app | `departamentos_excluidos`, `hectareas_minimas`, `tipos_aplicacion_excluidos` | No |

   La lista **no está cerrada**. Candidatos todavía sin definir: precio mínimo por hectárea del proveedor, cultivo, época. Cada uno entra por el mecanismo del punto 5 con su propio PR.
7. **Reglas del mecanismo.**
   - Un criterio sin valor declarado no restringe.
   - Una clave desconocida guardada en `requisitos` o `filtros` se ignora al evaluar y se conserva (un cliente nuevo no rompe a un servidor viejo).
   - Los filtros del proveedor son preferencias suyas: la escalera nunca los relaja. Si el proveedor dijo «no acepto Pando» o «menos de 50 ha no», no recibe esos avisos aunque la solicitud no tenga oferta.
   - Los filtros del proveedor **solo gobiernan avisos**. No ocultan el mapa: ahí el proveedor aplica los filtros que quiera en el momento.
   - Una prueba de arquitectura exige que todo criterio registrado tenga prueba propia y que la escalera de configuración nombre solo criterios relajables.
8. **Mi disponibilidad** es un estado manual del proveedor, sin calendario: `prv_proveedores.disponibilidad` (`disponible`, `ocupado` o `en_trabajo`) con la fecha del último cambio. `ocupado` y `en_trabajo` tienen el mismo efecto (el proveedor no recibe avisos de nuevas solicitudes) y se distinguen solo por el texto. Lo cambia el titular desde la app (`PUT /proveedores/mi/disponibilidad`). Un proveedor no disponible sigue viendo el mapa y puede ofertar: solo se le deja de avisar.

### C. Avisos por oleadas

9. **Qué es una oleada.** Al publicarse una solicitud el sistema arma la lista de proveedores que cumplen **todos** los criterios (paso 0, el más estricto) y los avisa. Pasados T minutos sin que nadie oferte, avanza un paso de la escalera, quita un criterio o lo afloja, y avisa **solo a los proveedores que ahora cumplen y no habían sido avisados**. Así cada T minutos. Las apps no calculan nada de esto: reciben el aviso y ven la solicitud.
10. **La escalera.** Es una lista ordenada en configuración (`config/negociacion.php`, ADR 0016). Cada paso declara los criterios que relaja y los parámetros que cambia, de forma acumulativa. Valores iniciales, revisables:

    | Paso | Qué cambia | Quién recibe aviso (además de lo ya avisado) |
    |---|---|---|
    | 0 | Todo. Calificación mínima alta (4,50) | Propios de Agrocom y proveedores con calificación alta que cumplen todos los criterios |
    | 1 | Calificación mínima media (4,00) | Los que ahora cumplen |
    | 2 | Sin mínimo de calificación | Todos los demás que cumplen los criterios no relajables |
    | 3 | Se quita el requisito de dron del cliente | Proveedores cuyo dron no cumple el requisito (punto 18) |

    - T es `negociacion.oleadas.intervalo_minutos` (2 por defecto).
    - Un paso que no tiene a nadie nuevo que avisar no espera: la oleada pasa al siguiente en la misma pasada del reloj.
    - La prioridad es por el tiempo que la solicitud lleva disponible: los propios de Agrocom y los mejor calificados tienen la primera oportunidad de negociar y luego se abre al resto.
    - Un proveedor con menos de `negociacion.oleadas.calificaciones_minimas_para_contar` calificaciones (3) cuenta como sin calificación, salvo que sea propio.
11. **Reinicio.** Cuando la escalera se agota sin que la solicitud salga del mercado, la oleada queda `agotada` y, pasadas `negociacion.oleadas.reinicio_horas` (24), comienza un **ciclo nuevo** que vuelve a avisar a los proveedores del paso 0 (el recordatorio del inicio). Hay como máximo `negociacion.oleadas.max_reinicios` (2) reinicios. La espera entre ciclos existe porque con la vigencia larga del punto 20 un reinicio inmediato avisaría a los mismos proveedores cada pocos minutos durante semanas. Renovar una solicitud expirada también abre un ciclo nuevo.
12. **Nunca se avisa dos veces al mismo proveedor en un ciclo.** El registro de avisos (`neg_oleada_avisos`) tiene `UNIQUE (solicitud_id, proveedor_id, ciclo)`; se inserta antes de emitir el aviso y solo se emite para las filas realmente insertadas. El aviso del motor de notificaciones (ADR 0025) usa como clave `nueva_solicitud_abierta:{solicitud}:{ciclo}`, y su `UNIQUE (usuario_id, clave_evento)` es la segunda barrera. Un aviso insertado y no entregado queda con `entregado_en` nulo y la siguiente pasada del reloj lo reintenta; reintentar es seguro por la misma unicidad. El reloj es idempotente: correr la pasada dos, tres o diez veces deja las mismas filas.
13. **El reloj** es el comando `negociacion:procesar` que ya corre cada minuto (ADR 0038 punto 6); procesa las oleadas con `proxima_en` vencida. Cada oleada se procesa en su propia transacción: bloquea primero la fila de la solicitud, después las de sus ofertas y al final la de su oleada (se extiende el orden del invariante 12), y un fallo en una solicitud se reporta sin detener a las demás. La primera oleada (paso 0) corre en el acto al publicar, sin esperar al reloj.
14. **Preferencias de aviso.** Si la cuenta apagó `nuevas_solicitudes`, no recibe estos avisos en ningún paso. Las categorías obligatorias del ADR 0041 no cambian.

### D. Etapa de negociación y búsqueda detenida

15. **Etapa de negociación (nombre visible: «En negociación»).** Mientras el cliente negocia con proveedores, la solicitud deja de aparecer en el mapa de los demás pilotos y las oleadas se pausan. No es un estado nuevo de la máquina de la solicitud (sigue `con_ofertas`): es una condición que se calcula de los hechos. La solicitud está en negociación cuando alguna de sus negociaciones tiene una oferta `vigente` con `numero >= 2`, es decir, cuando el cliente respondió a una oferta (decisión revisable). Termina sola cuando ya no queda ninguna oferta vigente de ese tipo (rechazo, retiro o vencimiento) o cuando se adjudica. Al terminar, la solicitud vuelve a aparecer y la oleada continúa desde donde estaba, con el reloj del siguiente paso reiniciado. Quien ya tiene una negociación en esa solicitud la sigue viendo. La palabra «tomada» no se usa.
16. **«No seguir buscando más pilotos».** El cliente puede detener la búsqueda (`POST /solicitudes/{id}/busqueda/detener`) y reanudarla (`/busqueda/reanudar`): las oleadas se detienen, la solicitud no se abre a más proveedores que los que ya la ven y sigue disponible para ellos. Es independiente de la etapa de negociación. Nombre visible: «Sin buscar más pilotos». Adjudicar, cancelar o expirar la solicitud cierra la oleada.
17. **Qué ve el cliente.** La solicitud trae `etapa` (`buscando`, `en_negociacion` o `busqueda_detenida`) y `busqueda` con cuántos proveedores se avisaron. Nunca quiénes.

### E. Dron que no cumple

18. **No se bloquea.** Si al ofertar el equipo no cumple un requisito del cliente, la oferta se crea igual y guarda `incumple` (lista de claves de criterio que no cumple). El cliente la ve marcada en el comparador («tu pedido era un dron de 20 L; este es de 10 L») y el aviso de la oferta lo dice. Si todas las ofertas vigentes de la solicitud incumplen, el aviso de oferta recibida lo informa de forma explícita. El piloto ve el requisito antes de ofertar y la hoja de oferta le avisa que su dron no cumple.
19. **«Tipo de dron» es la capacidad mínima del tanque** (litros), un atributo que ya existe en `flo_drones.capacidad_tanque_l`. Las apps ofrecen opciones predefinidas y el servidor guarda el número. Ampliarlo a otro atributo es agregar un criterio.

### F. Vigencia larga y fecha deseada

20. **La solicitud admite semanas y meses.** `ventana_desde` y `ventana_hasta` (la fecha deseada) pueden estar hasta `negociacion.anticipacion_maxima_dias` (365) en el futuro. Al publicar, `vence_en` es el fin del día de `ventana_hasta`, sin pasar de `negociacion.vigencia_solicitud_max_dias` (365) desde la publicación. Sin fecha deseada, vale `negociacion.vigencia_solicitud_dias`, que pasa de 7 a 30. El cliente puede renovar una expirada dentro de `plazo_renovacion_dias` (30), como hoy. Cuando faltan `negociacion.aviso_vencimiento_dias` (3) para el vencimiento se avisa al cliente.
21. **La oferta también.** `negociacion.vigencia_oferta_horas` pasa a ser un parámetro en horas que admite cualquier valor (720 son 30 días) y la oferta nunca vence después de la solicitud. El valor por defecto no cambia. Que cada parte elija la validez de su oferta dentro de un rango es una mejora posible, no parte de esta etapa.
22. **Cómo se relacionan fecha, vigencia y oleadas.** Las oleadas miden el tiempo desde la publicación, no desde la fecha deseada: dar a conocer la solicitud a los proveedores es urgente aunque el trabajo sea en tres semanas. La fecha deseada solo fija cuándo vence la solicitud y se usa para ordenar y filtrar el mapa. La verificación de equipo libre al adjudicar (negociacion-contrato §3.6) sigue comparando rangos de fecha deseada.
23. **Visibilidad en el mapa.** Un proveedor ve una solicitud `abierta` o `con_ofertas` cuando:
    - tiene una negociación en ella, o
    - la solicitud no está en negociación y, o bien ya se abrió a todos, o bien el proveedor fue alcanzado por alguna oleada.

    Una solicitud se **abre a todos** al llegar al paso `negociacion.oleadas.paso_apertura_general` (2, el de «sin mínimo de calificación»); así la prioridad de los primeros pasos también es visible y no solo un aviso retrasado. Detener la búsqueda congela este alcance. Ofertar sobre una solicitud que el proveedor no ve responde 409 `solicitud_no_disponible`.

### G. Orden de las ofertas ante el cliente

24. El comparador usa por defecto `orden=recomendado`: primero las de proveedores `propio`; dentro de cada grupo, mayor calificación (quien tiene menos de las calificaciones mínimas va al final del grupo); luego menor precio por hectárea; luego la más antigua. El cliente cambia el orden a `precio`, `calificacion` o `reciente`. Un proveedor propio no tiene ventaja en la adjudicación: el cliente decide (ADR 0030 punto 5); el orden es una sugerencia que solo cambia la presentación.

### H. Contrato y datos

25. **El contrato cambia de golpe.** Sin capas de compatibilidad ni parámetros ignorados: salen los recursos y los campos retirados y entran los nuevos. La lista exacta está en el plan de implementación y se refleja en `docs/api/openapi.yaml` (generado) y en `docs/especificacion/negociacion-contrato.md`.
26. **Sobre el invariante 15 (respaldo en lugar de borrar): excepción explícita y acotada.** La regla protege datos que pueden ser reales. En este cambio **no aplica**, por tres hechos verificables: (a) el servidor de beta solo tiene datos de prueba y las apps no están en tiendas; (b) los catálogos que se retiran (`sol_zonas`, `sol_precios_referencia`) son semillas que se regeneran con sus sembradores y no contienen decisiones de nadie; (c) los valores por zona de terrenos, proveedores y preferencias son datos de demostración. Por eso las migraciones de retiro pueden hacer `DROP` de las tablas y columnas retiradas sin copiarlas a un esquema `respaldo_*`, siempre como migraciones nuevas (no se editan las anteriores), con `down()` que recrea la estructura vacía y con la prueba contra PostgreSQL. Después se reconsolidan las migraciones con `bin/consolidar-migraciones` (ADR 0024) y se reinicia beta con datos de demostración. La exención **caduca** si antes de integrar la migración de retiro existe un dato real en beta; el PR lo confirma por escrito. Cualquier retiro posterior vuelve a regirse por el invariante 15.

## Decisiones revisables

Marcadas para revisión del dueño con datos de operación:

- La condición que dispara la etapa de negociación (punto 15).
- Que los propios y los mejor calificados tengan prioridad en el aviso, en la visibilidad del mapa (punto 23) y en el orden de ofertas (punto 24), y los umbrales de calificación.
- El orden de relajación de la escalera (punto 10) y que los filtros del proveedor no se relajen.
- La espera y el tope de reinicios (punto 11) y los valores de T, vigencia y anticipación.
- Que `ocupado` y `en_trabajo` sean manuales y valgan por proveedor, no por piloto (punto 8).
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
- **Calendario de disponibilidad**: más piezas que mantener que el interruptor que el piloto sí actualiza; el calendario real queda fuera de esta etapa.
- **Una tabla de reglas de filtro editable en el panel**: el conjunto de criterios cambia con el código y con las pruebas; solo la escalera y los parámetros son configuración.
- **Bloquear la oferta de un dron que no cumple**: el cliente puede aceptar un dron menor si el precio le conviene; se le informa y decide.
- **Conservar los parámetros viejos en el contrato por una ventana de compatibilidad**: no hay apps fuera del equipo.

## Consecuencias

- Tablas nuevas del módulo `Negociacion`: `neg_oleadas` (una fila por solicitud, con estado `activa`, `en_pausa`, `detenida`, `agotada` o `terminada`, ciclo, paso y `proxima_en`), `neg_oleada_pasos` (qué pasó en cada paso, solo se inserta) y `neg_oleada_avisos` (registro de avisos con su unicidad). Columnas nuevas: `sol_solicitudes_servicio.requisitos`, `.departamento_codigo`, `.provincia_codigo`; `sol_terrenos.departamento_codigo`, `.provincia_codigo`, `.municipio`, `.comunidad`; `prv_proveedores.disponibilidad`; `neg_preferencias.filtros`; `neg_ofertas.incumple`.
- Se reemplaza el oyente `AvisarNuevaSolicitud` por el inicio de la oleada. Las apps dejan de armar avisos locales a partir del mapa: el aviso lo decide el servidor.
- El tablero y las métricas pasan de «por región» a «por departamento» (la vista `vw_metricas_solicitudes` se recrea) y suman tiempo hasta la primera oferta por paso de la escalera.
- Hay una zona de revisión línea por línea nueva: el motor de oleadas (idempotencia del registro de avisos, orden de bloqueos, visibilidad del mapa). Se agrega a la lista de «qué no delegar» de `CLAUDE.md` cuando se implemente.
- La política (`politicas/negociacion.md`), la especificación (`marketplace.md`, `negociacion-contrato.md`) y el glosario describen este modelo. Se implementa por fases, una tarea y un PR por fase, con revisión posterior del dueño en las piezas críticas.
