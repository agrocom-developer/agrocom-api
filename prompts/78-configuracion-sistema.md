<!-- ciclo: critica=si turno-noche=1 descongela=tests rama=feature/configuracion-sistema etapas=4 -->

# Tarea 78 — HU-55: configuración del sistema (llaves y tokens), separada de los datos de la empresa

## Por qué esta tarea

Pedido del dueño el 7/9/2026: *"en mi configuración del sistema, muy por
separado de la configuración de la empresa, podría tener sectores para agregar
las keys de mapas, correos u otros demás tokens, keys, para que el sistema
funcione y se parametrice"*. Y en la misma tanda: *"en `/panel/organizacion` el
tab de Facturación está vacío, marcado como Próximamente"*.

Son dos cosas distintas y por eso van juntas — la tarea es justamente
**separarlas**:

- **`/panel/organizacion`** son los datos de **la empresa**: razón social, NIT,
  logo, y la pestaña de **Facturación** que hoy solo dice "Próximamente"
  (`organizacion/index.blade.php` línea 222, `seguridad.organizacion.tab_proximamente`).
- **`/panel/configuracion`** (nueva) son los parámetros con que **el sistema**
  funciona: llaves de terceros, credenciales de correo, tokens. No son datos de
  la empresa; son infraestructura configurable.

Habilita la tarea 79: sin un lugar donde poner la llave de Google Maps, el mapa
no puede cambiar de proveedor.

**Es crítica**: guarda secretos. Una llave filtrada en un log, en la bitácora, en
un snapshot de Playwright o en el HTML de la pantalla es un incidente real, no
un bug de interfaz. Revisión posterior a la integración, anotada en
`runs/revision-pendiente.txt`.

## Lo que ya existe

- `app/Dominios/Seguridad/Infraestructura/Http/Views/pages/organizacion/index.blade.php`
  — las dos pestañas; la de facturación es un `<p>` con "Próximamente".
- `lang/es/seguridad.php` §367 — `tab_organizacion`, `tab_facturacion`,
  `tab_proximamente`.
- `OrganizacionController` y su caso de uso de guardado.
- `Compartido/Infraestructura/Eloquent/{ModeloDominio,BitacoraObserver}` — la
  bitácora es un observer automático (invariante 9). **Ahí está el riesgo**:
  por defecto va a querer registrar el valor antes/después.
- `.env.example` y `config/mail.php` con Mailpit ya configurado (tarea 66).

## Qué hacer

1. **Pestaña Facturación de la empresa**, en `/panel/organizacion`: los datos
   fiscales con que se emite factura — razón social fiscal, NIT, domicilio
   fiscal, actividad económica, y la leyenda al pie del documento. Van con los
   datos de la empresa, en la misma tabla de organización, **no** en la
   configuración del sistema. Que la use el PDF de factura si ya existe punto de
   consumo; si no, dejalo solo persistido y anotado.
2. **Tabla de configuración del sistema**, en `Compartido` (`plt_`) — es
   plataforma, no negocio: `clave` (string, única entre activas), `valor`
   (text, **cifrado en reposo** con el cast `encrypted` de Laravel), `grupo`
   (`mapas` / `correo` / `integraciones`), `descripcion`, `es_secreto` (bool),
   + auditoría y soft delete.
3. **Pantalla `/panel/configuracion`**, agrupada por sector, con permiso propio
   `seguridad.configuracion.{ver,editar}` **solo para el rol dueño**, e ítem de
   menú separado del de organización.
4. **Tratamiento de secretos — esto es el corazón de la tarea**:
   - El valor de un campo `es_secreto` **nunca se devuelve al navegador**. La
     pantalla muestra el estado ("configurada" / "sin configurar") y los últimos
     4 caracteres como mucho; el `<input>` va vacío, y guardar vacío **no borra**
     lo guardado (hay que borrar explícitamente).
   - **La bitácora registra el cambio, no el valor.** Excluí estos campos del
     diff antes/después del observer — dejá el "quién, cuándo, qué clave", nunca
     el contenido. Escribí el porqué en el docblock: la invariante 9 pide
     valores "donde aplique", y acá no aplica.
   - Nada de llaves en logs, en mensajes de excepción, ni en respuestas de error.
   - Los snapshots de Playwright de esta pantalla se toman **sin** ninguna llave
     real cargada.
5. **Resolución en cascada, con `.env` como respaldo**: si la clave está en la
   base, manda la base; si no, el valor de `config()`/`.env`. Nunca al revés, y
   nunca reventando si falta — un sistema sin llave de mapas tiene que seguir
   funcionando con el proveedor actual (Leaflet + Esri), no romperse.
   **No muevas a la base los secretos de infraestructura** (`APP_KEY`,
   credenciales de la base de datos): esos siguen en `.env`, donde el sistema
   los necesita antes de poder leer ninguna tabla.
6. **Sectores iniciales**: `mapas` (llave de Google Maps, proveedor
   preferido), `correo` (host, puerto, usuario, contraseña, remitente — con
   respaldo en el Mailpit de desarrollo) e `integraciones` (vacío, listo para
   crecer).
7. **Contrato de lectura** `Compartido/Contratos/LecturaConfiguracion` para que
   la tarea 79 y cualquier otro consumidor pidan una clave sin tocar el modelo.

## Qué NO hacer

- **No muestres una llave guardada en el HTML**, ni siquiera deshabilitada, ni
  "solo para el dueño". Si está en el DOM, está filtrada.
- No registres valores de secretos en `plt_bitacoras`.
- No mezcles los datos de facturación de la empresa con la configuración del
  sistema: son las dos pantallas que esta tarea separa.
- No hagas que la ausencia de una llave rompa una pantalla.
- No saques `APP_KEY` ni las credenciales de base de datos de `.env`.
- No inventes integraciones que nadie pidió: el sector `integraciones` nace
  vacío.

## Cómo repartir las etapas

- **Etapa 1**: pestaña Facturación de la empresa, con sus campos y tests.
- **Etapa 2**: tabla de configuración, cifrado en reposo, exclusión de la
  bitácora, contrato de lectura, tests unitarios del tratamiento de secretos.
- **Etapa 3**: pantalla, permisos, menú, resolución en cascada con `.env`,
  tests Feature.
- **Etapa 4**: traducciones, snapshots sin llaves reales, `bin/verify`.

## Criterio de aceptación

- `./bin/verify` = 0 (en este Mac, `./bin/verify --sin-assets`).
- Test: guardado un secreto, el HTML de `/panel/configuracion` **no contiene**
  el valor en claro (aserción sobre el cuerpo de la respuesta).
- Test: cambiar un secreto deja fila en `plt_bitacoras` con la clave y el autor,
  y **sin** el valor viejo ni el nuevo en el diff.
- Test: guardar el campo vacío no borra el secreto ya configurado; el borrado
  explícito sí.
- Test: sin fila en la base, la clave resuelve al valor de `config()`; con fila,
  gana la base.
- Test: un rol que no es dueño recibe 403 en `/panel/configuracion` y no ve el
  ítem de menú.
- Test: el valor queda cifrado en la columna (leer la fila cruda no devuelve el
  texto plano).

## Puede tocar

`app/Dominios/Compartido/**`, `app/Dominios/Seguridad/**` (organización,
permisos, menú), `database/migrations/**`, `database/seeders/**`,
`config/**` (solo lectura de respaldo), `lang/es/**`, `routes/web.php`,
`resources/**`, `tests/**`, `tests/Visual/**`.

Fuera de alcance: `.env` real, `APP_KEY`, credenciales de base de datos, el
proveedor de mapas en sí (tarea 79), `com_*`, `ope_*`, `fin_*`.

## Cierre obligatorio de cada etapa

`runs/78.estado`, `runs/78.md`, y al `OK` `runs/78.pr.md`. Anotá la tarea en
`runs/revision-pendiente.txt` (es crítica: guarda secretos). Commits agrupados
por función, en español, imperativo, sin `Co-Authored-By`.
