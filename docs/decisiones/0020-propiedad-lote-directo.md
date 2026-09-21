# ADR 0020 — Se elimina `Campo`: `Lote` cuelga directo de `Propiedad`

**Estado:** Aceptada · **Fecha:** 15/9/2026 · **Origen:** corrección de negocio del dueño
sobre ADR 0018 (aceptado 10/9/2026) — el nivel intermedio "Campo" no corresponde a cómo
el productor nombra su tierra.

## Contexto

ADR 0018 creó `Propiedad` como entidad separada de `Campo` para representar el caso
"Gamelera": una propiedad con dos terrenos físicos de 1500 ha separados por una carretera,
cada uno con sus lotes y su propia campaña. La resolución fue `Cliente → Propiedad → Campo
→ Lote`, con `Campo` como el terreno físico delimitado dentro de la propiedad.

El dueño corrigió la premisa: en la jerga real del productor, "propiedad" ES la unidad que
él nombra — si hay otro terreno físico separado, lo llama otra propiedad, no un "campo"
dentro de la misma. No hay una entidad intermedia con nombre, código o identidad propia
entre la propiedad y sus lotes. Una propiedad sí puede tener terrenos separados
geográficamente ("islas") — pero eso es geometría (varios polígonos), no una fila de base
de datos con `id`, `nombre` e hijos propios: no tiene código, no tiene hectáreas propias,
no tiene restricciones.

Gamelera se resuelve sin `Campo`: una fila `Propiedad` con geometría `MultiPolygon` (los
dos terrenos) y sus lotes (200 ha c/u) colgando directo de esa propiedad. La independencia
de campaña por terreno —el otro requisito real detrás del caso— ya la resuelve
`com_lote_campania` (lote_id, campania_id, cultivo_id) a nivel `Lote`, sin depender de que
exista un nivel `Campo`: dos lotes de la misma propiedad ya pueden estar en campañas
distintas hoy.

## Decisión

### 1. `com_campos` se elimina

`com_lotes` pasa de `campo_id` a `propiedad_id` (FK directa a `com_propiedades`).
`com_propiedades` gana `geometria` (`jsonb`, GeoJSON `MultiPolygon` nullable) — mismo
nombre de columna que ya usan `com_campos.geometria`/`com_lotes.geometria` (consistencia
con la convención del repo, ADR 0001: sin PostGIS, se guarda y se dibuja, nunca se
consulta espacialmente).

**Por qué `MultiPolygon` y no `Polygon`:** es el tipo GeoJSON estándar (RFC 7946 §3.1.7)
para geometría no contigua — exactamente el caso "islas". Con un solo terreno es un
`MultiPolygon` de un elemento, sin rama especial de código. Mantener `Polygon` simple y
"aceptar que casi siempre es un solo terreno" es la misma trampa que llevó a crear
`Campo`: modelar el caso común y dejar el caso real (Gamelera) sin representación fiel.

La construcción/edición visual de esa geometría (el editor de mapa multi-polígono) queda
fuera del alcance de esta tarea — se agrega la columna a nivel de esquema y modelo,
nullable, y el editor se construye en un feature aparte.

### 2. El flujo de alta de lotes queda separado del de propiedad

A diferencia de cómo `Campo` funcionaba (crear un campo también creaba sus lotes en el
mismo formulario), la propiedad se crea/edita con sus propios datos —nombre, ubicación,
geometría de terrenos— sin lotes adentro. Los lotes se siguen dando de alta desde la
pantalla propia de Lotes, con una cascada de selección cliente → propiedad (2 niveles,
antes 3 con Campo en el medio).

### 3. `ope_estadias_hacienda.campo_id` pasa a `propiedad_id`

Única FK de esquema fuera de Comercial acoplada a `Campo` (ADR 0018 ya lo había dejado
señalado como frontera). El piloto elige la propiedad donde entra el equipo, no un
"campo" — coherente con que el copy visible de esa pantalla (`lang/es/operaciones.php`)
ya decía "Propiedad" aunque la columna interna fuera `campo_id`.

### 4. `com_contrato_alcances` pierde la columna `campo_id`

Sin modelo Eloquent ni caso de uso implementado (confirmado contra el compose: la tabla
tiene 0 filas), así que no hay código dependiente que migrar. Un alcance de contrato pasa
a ser siempre "esta propiedad, tantas hectáreas" — se pierde la distinción "propiedad
entera / campo específico" que ya no existe, nunca tuvo UI.

### 5. El contrato de sync con `agrocom-field` renombra `campo_id` → `propiedad_id`

En `LoteCatalogo` (pull de catálogo) y en el evento `estadia_entrada` (`POST /api/sync`),
sin compatibilidad retroactiva con el shape viejo — coordinado con una versión nueva del
APK (mecanismo de `GET /api/version` + SemVer, `dis_versiones_apk`).

## Consecuencias

**A favor**

- El modelo vuelve a coincidir con la jerga real del productor: "propiedad" es la unidad
  que él nombra, sin un nivel intermedio inventado que nadie usa en la conversación.
- Un salto menos en toda lectura/join que hoy resuelve `lote → campo → propiedad`
  (paneles, dashboard, Operaciones, sync).
- El caso Gamelera se sigue representando de verdad (geometría multi-terreno + campaña
  independiente por lote, ya soportada), sin la entidad que ADR 0018 le había puesto
  alrededor.

**En contra, y asumido**

- Radio de impacto mecánico pero real: ~65 archivos de test que hoy crean
  `Cliente → Propiedad → Campo → Lote` a mano pasan a `Cliente → Propiedad → Lote`; media
  docena de lecturas/`with()`/`whereHas` que navegaban `lote.campo.propiedad_id` en dos
  saltos pasan a `lote.propiedad_id` en uno.
- El formulario de Lote pasa de una cascada de 3 niveles (cliente → propiedad → campo) a
  una de 2 (cliente → propiedad) — mismo tipo de cambio mecánico que ADR 0018 hizo en
  sentido contrario, ahora invertido.
- La pantalla `/panel/campos*` (alta de campo+lotes en una operación) desaparece; el alta
  de propiedad y el alta de lotes quedan en pantallas separadas, sin el paso combinado que
  `Campo` ofrecía.
- Se pierde la posibilidad —nunca usada, sin UI ni caso de uso— de que
  `com_contrato_alcances` distinga "toda la propiedad" de "un campo específico": ahora
  todo alcance es a nivel propiedad completa.
- Segunda migración de datos sobre el mismo terreno conceptual en 5 días (ADR 0018 el
  10/9, este el 15/9) — coste de haber modelado `Campo` antes de confirmar la jerga con el
  dueño. Se documenta para que quede el aprendizaje, no para reabrir la decisión.

**Confirmado, sin impacto más allá del rename:** el motor de sync no expone hoy ningún
dato que dependa de que `Campo` exista como entidad con lotes agrupados aparte de
`propiedad_id` — el cambio es 1:1, `campo_id` entero opaco pasa a ser `propiedad_id`
entero opaco, mismo contrato de idempotencia por `uuid_cliente`.

## Alternativas descartadas

**Mantener `Campo` como agrupador opcional** (una propiedad puede tener 0 o 1 "campos"
intermedios cuando hace falta subdividir por terreno) — descartado: reintroduce la misma
ambigüedad que motivó crear la entidad en ADR 0018 y que el dueño acaba de rechazar; un
nivel opcional que en el 99% de los casos está vacío es peor que no tenerlo, porque cada
lectura tiene que seguir contemplando el salto extra "por si acaso".

**Modelar los terrenos de una propiedad como filas separadas con FK a `Lote`** (en vez de
geometría) — descartado explícitamente por el dueño: un terreno no tiene código, no tiene
hectáreas propias, no tiene restricciones — no es una entidad de negocio, es forma. Darle
una fila y un `id` sería recrear `Campo` con otro nombre.
