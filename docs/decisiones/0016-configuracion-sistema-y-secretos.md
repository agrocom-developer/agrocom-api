# ADR 0016 — Configuración del sistema y secretos: en la base, cifrados, con `.env` de respaldo

**Estado:** Aceptada · **Fecha:** 7/9/2026 · **Origen:** pedido del dueño del 7/9/2026 — *"en mi configuración del sistema, muy por separado de la configuración de la empresa, podría tener sectores para agregar las keys de mapas, correos u otros demás tokens, keys, para que el sistema funcione y se parametrice"*.

## Contexto

Hasta hoy todo parámetro de infraestructura vive en `.env`: base de datos, `APP_KEY`, correo (Mailpit, ADR ampliado en la tarea 66), disco `r2`. Eso funciona mientras el único que configura el sistema es quien tiene acceso al servidor — que hoy es la misma persona que lo programa.

Deja de funcionar en cuanto aparece una llave que el **dueño** necesita poder cambiar: la de Google Maps (tarea 79) es el caso concreto que forzó la decisión. Pedirle que edite un `.env` por SSH y reinicie el contenedor no es una opción, y hardcodearla en el repositorio tampoco.

Hay además una confusión de conceptos que la pantalla actual arrastra: `/panel/organizacion` mezcla —o mezclaría, si se le agregara esto— los **datos de la empresa** (razón social, NIT, datos fiscales) con los **parámetros del sistema**. Son cosas distintas: uno es contenido del negocio, el otro es cómo funciona la herramienta.

## Decisión

### 1. Dos pantallas, no una

- **`/panel/organizacion`** — datos de la empresa: identidad, logo y la pestaña de **Facturación** (razón social fiscal, NIT, domicilio, actividad económica, leyenda del documento), que hoy solo dice "Próximamente".
- **`/panel/configuracion`** — parámetros del sistema, agrupados por sector (`mapas`, `correo`, `integraciones`). Permiso propio, solo para el rol dueño.

### 2. Los parámetros configurables viven en la base, cifrados en reposo

Tabla en `Compartido` (`plt_`, es plataforma y no negocio): `clave`, `valor` (cast `encrypted` de Laravel), `grupo`, `descripcion`, `es_secreto`.

### 3. Cascada: la base manda, `.env` respalda

Si la clave está en la base, gana la base; si no, el valor de `config()`/`.env`; si tampoco, el sistema sigue andando con su comportamiento por defecto. **Nunca al revés, y nunca reventando por una llave ausente** — un sistema sin llave de mapas dibuja con Leaflet + Esri, no se rompe.

### 4. Lo que NO se mueve a la base

`APP_KEY` y las credenciales de la base de datos siguen en `.env`, y no es negociable: el sistema las necesita **antes** de poder leer ninguna tabla, y `APP_KEY` es justamente lo que descifra la columna `valor`. Guardar la llave de cifrado en la tabla que cifra es un círculo.

### 5. Un secreto guardado no vuelve al navegador. Nunca.

- La pantalla muestra estado ("configurada" / "sin configurar") y a lo sumo los últimos cuatro caracteres. El `<input>` va vacío.
- Guardar el campo vacío **no borra** lo guardado; borrar es una acción explícita. Si no, cualquier guardado de otro campo del formulario vacía la llave.
- **La bitácora registra el cambio, no el valor.** Es una excepción declarada a la invariante 9 de `CLAUDE.md`, que pide valores antes/después "donde aplique": acá no aplica, porque el registro de auditoría se volvería el lugar más fácil del sistema para leer todas las llaves en claro. Queda el quién, el cuándo y qué clave.
- Nada de llaves en logs, en mensajes de excepción, ni en snapshots de Playwright.

### 6. El proveedor de mapas es configurable, no reemplazado

Con llave de Google Maps cargada, el editor de perímetro usa Google Maps; sin ella, Leaflet + Esri World Imagery, que es el camino de hoy y sigue siendo el **camino por defecto**. Google Maps Platform se factura por uso: la llave puede no estar, o agotarse, y el panel tiene que seguir dibujando lotes igual.

**El proveedor no toca el dato**: el perímetro se guarda como GeoJSON `Polygon` en `com_lotes.geometria` con cualquiera de los dos. Cambiar de proveedor mañana no migra ninguna geometría.

## Consecuencias

**A favor**

- El dueño configura el sistema sin tocar el servidor.
- Aparece un lugar declarado para las integraciones que vengan, en vez de que cada una invente el suyo.
- La distinción "datos de la empresa" / "parámetros del sistema" queda en la interfaz, no solo en la cabeza de quien lo programó.

**En contra, y asumido**

- Una tabla cifrada con `APP_KEY` significa que **rotar `APP_KEY` inutiliza los valores guardados**. Es el mismo compromiso que ya tiene cualquier cast `encrypted` de Laravel, y hay que saberlo antes de rotarla.
- La configuración deja de estar versionada: un `.env.example` documenta qué existe, una fila en la base no. Se compensa con el campo `descripcion` y con el sector, que dicen para qué es cada clave.
- Un consumidor de configuración hace una consulta más. Se resuelve con caché si alguna vez pesa; hoy no pesa.

## Alternativas descartadas

**Todo en `.env`.** Es lo que hay, y es exactamente lo que el pedido rechaza: obliga a que el dueño dependa de quien tiene acceso al servidor para cambiar una llave suya.

**Todo en la base, incluida la configuración de infraestructura.** Imposible para `APP_KEY` (cifra la propia tabla) y frágil para la base de datos (habría que leerla desde la base que todavía no se pudo abrir).

**Sin cifrar, confiando en el permiso de la pantalla.** El permiso protege la pantalla, no la tabla: un volcado de base, un respaldo mal guardado o una consulta de soporte exponen todo. Cifrar en reposo cuesta un cast.

**Reemplazar Leaflet por Google Maps directamente.** Ata el panel a un servicio facturado por uso para una función —dibujar un polígono sobre imagen satelital— que hoy funciona sin costo. Google Maps entra como opción configurable; Leaflet + Esri sigue siendo el camino que funciona sin llave.
