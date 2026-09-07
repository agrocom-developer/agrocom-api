<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/parametros-vuelo etapas=2 -->

# Tarea 70 — HU-47: altura de vuelo en el contrato, ventana "todo el día", y aplicación de siembra o de cosecha

## Por qué esta tarea

Tres ajustes que pidió el dueño el 7/9/2026, los tres sobre los parámetros con
que se acuerda y se vuela una aplicación:

1. *"Altura del vuelo en los parámetros de vuelo del contrato"* — el contrato
   ya fija `velocidad_max_kmh` y los límites de clima, pero no la altura. La
   altura sí está en `ope_ordenes_aplicacion` (`altura_vuelo_m`): el acuerdo
   con el cliente vive un nivel más arriba que la orden y hoy no tiene dónde
   escribirse. Es un dato con desacuerdo real en campo (4-5 m según Josué,
   2-3 m según Miguelito, `rol_piloto.md` §128), justamente del tipo que
   conviene pactar por contrato.
2. *"Ventana de aplicación todo el día, no requerido los rangos"* — hoy
   `CrearContratoRequest` exige `ventanas` `required|array|min:1` y
   `MaquinaEstadosContrato::activar()` no deja pasar a `vigente` un contrato
   sin ventanas (`ActivacionContratoNoDisponible::porFaltaDeVentanas`). Hay
   clientes que no restringen horario, y hoy el sistema los obliga a inventar
   una ventana 00:00-23:59.
3. *"La fumigación para siembra o cosecha"* — falta decir en qué momento del
   ciclo se aplica.

El diseño está en **ADR 0015 punto 5** y en la especificación §4.1 y §4.3.
Es la tarea más barata del Sprint 13 y no depende de la 69 más que para el
`campania_id` que esa ya dejó puesto.

## Lo que ya existe

- `database/migrations/2026_08_26_100003_create_com_contratos_table.php` — el
  bloque de "Parámetros por contrato" con sus `CHECK`; ahí va la altura.
- `database/migrations/2026_08_26_100007_create_ope_ordenes_aplicacion_table.php` —
  ya tiene `altura_vuelo_m`, `velocidad_vuelo_kmh` y `ancho_pasada_m` con sus
  `CHECK`. Copiá ese molde para la altura del contrato.
- `CrearContratoRequest` / `ActualizarContratoRequest` — los rangos replican
  uno a uno los `CHECK` de la migración; mantené esa correspondencia.
- `Comercial/Infraestructura/Http/Views/pages/contratos/_formulario.blade.php`
  y `_ventana-fila.blade.php` — el formulario con las filas de ventana.

## Qué hacer

1. **`altura_vuelo_m`** `DECIMAL(5,2)` nullable en `com_contratos`, por
   `ALTER TABLE`, con `CHECK (altura_vuelo_m IS NULL OR altura_vuelo_m > 0)`
   solo en pgsql. NULL = no pactada, rige lo que diga la orden. Sumala a los
   dos requests, al formulario y a la ficha del contrato.
2. **Ventanas opcionales**: `ventanas` pasa a `nullable|array` en los dos
   requests, y se elimina la guarda `porFaltaDeVentanas` de
   `MaquinaEstadosContrato::activar()` junto con su método de excepción y sus
   tests. **Cero ventanas significa "día completo"** y así lo tiene que decir
   la pantalla. Precisión del dueño del 7/9/2026: *"debe figurar la opción de
   día completo, y los campos de desde y hasta no tienen que ser requeridos
   porque está por defecto la aplicación de todo el día"*:
   - En el formulario de alta, el interruptor **"Día completo" arranca
     encendido** y las filas de ventana ni se muestran. Apagarlo revela las
     franjas; volver a encenderlo las limpia.
   - `hora_inicio` y `hora_fin` **no son requeridas** en ningún caso: solo se
     validan entre sí cuando hay una fila cargada (`hora_fin > hora_inicio`).
   - En la ficha y el listado, "Día completo" donde iría la lista de franjas.
   **No agregues un booleano** `ventana_todo_el_dia`: convive con las filas y
   hace representable un estado contradictorio que ningún `CHECK` puede
   impedir porque cruza dos tablas (ADR 0015 punto 5).
3. **`tipo_aplicacion`** en `ope_ordenes_aplicacion`: string 20, default
   `desarrollo`, `CHECK (tipo_aplicacion IN ('siembra','desarrollo','cosecha'))`.
   Enum de dominio propio en `Operaciones/Dominio/`, como `EstadoContrato`.
   Al formulario de la orden, a su ficha y al listado (como filtro).
   **Nota de alcance**: el dueño nombró dos (`siembra` y `cosecha`); el tercero
   (`desarrollo`) sale de la propia especificación —"aplicaciones desde el
   desarrollo vegetativo hasta cerca de cosecha", `ventana_al_negocio.md` §67— y
   es el que cubre el grueso de las 6-8 aplicaciones, por eso es el default.
   Si el dueño lo corrige, es un `CHECK` y un enum, nada más.
4. **Traducciones** en `lang/es/comercial.php` y `lang/es/operaciones.php` para
   todo texto nuevo (ADR 0013: nada hardcodeado en la vista).

## Qué NO hacer

- No toques `velocidad_max_kmh` ni los límites de clima del contrato: funcionan
  y no fueron parte del pedido.
- No borres `com_contrato_ventanas` ni la validación de solapamiento entre
  ventanas cargadas — sigue valiendo cuando hay ventanas.
- No agregues altura a `ope_sesiones` ni al reporte técnico.

## Cómo repartir las etapas

- **Etapa 1**: migraciones (altura, `tipo_aplicacion`), enum de dominio,
  requests, caída de la guarda de ventanas, tests unitarios y Feature.
- **Etapa 2**: formularios, fichas y listados (contrato y orden), traducciones,
  regeneración de los snapshots visuales tocados, `bin/verify`.

## Criterio de aceptación

- `./bin/verify` = 0 (en este Mac, `./bin/verify --sin-assets`).
- Test: un contrato **sin ninguna ventana** pasa de `borrador` a `vigente` sin
  error, y la ficha muestra "Día completo".
- Test: el formulario de alta enviado sin tocar el interruptor crea un contrato
  válido de día completo — ningún campo de hora es obligatorio.
- Test: un contrato con dos ventanas solapadas sigue siendo rechazado.
- Test: `altura_vuelo_m = 0` o negativa se rechaza en el request y en la base.
- Test: una orden creada sin `tipo_aplicacion` queda en `desarrollo`; una con
  valor fuera del enum se rechaza.
- `grep -rn "porFaltaDeVentanas" app/ tests/` no devuelve nada.

## Puede tocar

`app/Dominios/Comercial/**`, `app/Dominios/Operaciones/**` (orden de
aplicación), `database/migrations/**`, `lang/es/**`, `tests/**`,
`tests/Visual/**` (snapshots de contratos y órdenes).

Fuera de alcance: `cpn_campanias`, `per_*`, `fin_*`, el motor de sync.

## Cierre obligatorio de cada etapa

`runs/70.estado`, `runs/70.md`, y al `OK` `runs/70.pr.md`. Commits agrupados por
función, en español, imperativo, sin `Co-Authored-By`.
