# ADR 0026 — Convención general de ruta y nombre para archivos por objeto

**Estado:** Aceptada. **Actualizada dos veces el mismo día de creación (23/9/2026, pedido directo del dueño):**
1. Ver "Actualización 23/9/2026" al final de "Decisión": cambia el orden de la ruta de "actividad primero" a "objeto primero", y extiende el alcance de "clientes" a "trabajos" (y dos raíces reservadas: `personal/`, `propiedades/`).
2. Ver "Verificación de existencia y reconstrucción" al final de "Decisión": qué hacer cuando la fila tiene una ruta guardada pero el archivo físico no existe.

## Contexto

ADR 0009 fijó tres categorías de archivo (evidencias, reportes generados, assets de marca), cada una con su disco y su regla de vida. ADR 0019 abrió una excepción puntual dentro de la tercera categoría para el logo de la propia empresa: disco `public`, nombre generado por el servidor (`logos/empresa/logo-{timestamp}.{ext}`), justificada explícitamente en que `sec_datos_empresa` es una fila única — no hay "otro" logo de empresa con el que pueda chocar.

Sin que ningún ADR lo registrara, el mismo patrón de nombre se extendió por código al logo de cliente (`CrearCliente`, `ActualizarCliente`, HU-75/tarea 91): `logos/clientes/logo-{timestamp}.{ext}`. La diferencia con el caso de ADR 0019 es que acá SÍ hay muchos objetos — un cliente por fila de `com_clientes` — compartiendo una sola carpeta plana, distinguidos solo por el timestamp de subida. Dos subidas de clientes distintos en el mismo segundo colisionan de nombre, y no hay forma de aislar "los archivos del cliente X" a nivel de directorio — solo leyendo `logo_path` en la base.

El dueño pidió además dejar sentado el criterio para lo que todavía no existe: exportaciones de reportes (Excel u otro formato — hoy no hay ninguna implementada, no hay `maatwebsite/excel` en el proyecto) y, en general, cualquier archivo futuro que un caso de uso escriba a disco.

## Decisión

### 1. Regla general: anidar por el id del objeto dueño cuando puede haber muchos

Cuando una categoría de archivo tiene (o puede llegar a tener) más de un objeto dueño posible, el path siempre anida por su id **antes** del nombre de archivo. Ver "Actualización 23/9/2026" para el orden definitivo de los segmentos.

### 2. La excepción de "fila única" queda acotada al logo de empresa de ADR 0019

Nada más se exime de anidar por id. Aunque una categoría nueva tenga pocos registros al principio, se trata como "muchos objetos" desde el día uno — no hay forma de saber si va a seguir siendo pocos.

### 3. Nombre de archivo: máximo dos palabras + timestamp del servidor

`{nombre-corto}-{timestamp}.{ext}`, nunca el nombre original del archivo subido por el usuario — mismo criterio que ya aplican ADR 0009 y ADR 0019 (evita colisión entre reemplazos sucesivos, invalida caché de navegador/CDN, no expone nombres de archivo ajenos al sistema). El nombre corto describe **qué** es el archivo (`logo`, `acta`, `recibo`); de **quién** es lo dice la carpeta, no el nombre.

### 4. Exportaciones futuras (Excel u otro formato)

Cuando se implemente una exportación de reportes o listados, sigue el mismo patrón que los PDF de ADR 0009, con el orden de segmentos de la actualización de abajo. Disco según sensibilidad: `r2` si es un archivo privado que debe expirar (mismo criterio que evidencias/reportes), `public` si no. Este ADR no implementa ninguna exportación — deja la ruta decidida para no reabrir esta discusión ese día.

### 5. Fuera de alcance: archivos de respaldo

Hoy no existe una funcionalidad de respaldo (backups de base de datos u otro archivo) ni un ADR que la defina — cuándo se dispara, dónde vive, cuánto se retiene son preguntas de una decisión futura y más grande que esta. Queda anotado acá solo para que, cuando se aborde, siga el mismo principio de "categoría propia con su propia regla" — nunca una carpeta `uploads/` genérica con de todo adentro.

### Actualización 23/9/2026: objeto primero, actividad después

Redacción original de este mismo ADR (más arriba): `{categoría-de-archivo}/{id_del_objeto}/{nombre-corto}-{timestamp}.{ext}` — la actividad (logo, acta, reporte) como raíz, el objeto dueño como subcarpeta. El dueño pidió invertir el orden, por una razón concreta que la redacción original no consideraba: un mismo objeto de negocio (un cliente, un trabajo) va a acumular archivos de MÁS de una actividad con el tiempo (un cliente: logo, y a futuro reportes e informes; un trabajo: acta y reporte técnico, ya hoy). Con "actividad primero", esos archivos quedan esparcidos en raíces distintas del árbol (`actas/5/...`, `reportes-tecnicos/5/...`) sin ningún directorio que represente "todo lo del trabajo 5". Con "objeto primero", cuelgan de la misma carpeta raíz (`trabajos/5/actas/...`, `trabajos/5/reportes-tecnicos/...`), lo que además compone mejor al armar una URL o al pensar en un futuro respaldo por objeto.

**Orden definitivo:**

```
{tipo_objeto}/{id_objeto}/{actividad}/{nombre-corto}-{timestamp o id}.{ext}
```

**Raíces de objeto activas:**

- `clientes/{cliente_id}/logos/logo-{timestamp}.{ext}` — corrige la redacción original (`logos/clientes/{cliente_id}/...`); ver "Consecuencias".
- `trabajos/{trabajo_id}/actas/{acta_id}.pdf` — antes `actas/{trabajo_id}/{acta_id}.pdf` (ADR 0009).
- `trabajos/{trabajo_id}/reportes-tecnicos/{reporte_id}.pdf` — antes `reportes-tecnicos/{trabajo_id}/{reporte_id}.pdf` (ADR 0009).
- `planillas/{planilla_id}/recibos/{detalle_id}.pdf` — ya tenía objeto como raíz (ADR 0009); se le agrega la subcarpeta de actividad para quedar en el mismo molde que las dos anteriores.

**Raíces de objeto reservadas** (sin código propio todavía, el nombre queda fijado para cuando se implementen, y no otro):

- `personal/{persona_id}/...` — para cuando un trabajador tenga un archivo propio (documento, foto), pedido explícito del dueño al generalizar este ADR.
- `propiedades/{propiedad_id}/fotos/...` — para cuando el cliente pueda enviar fotos de sus terrenos (pedido explícito del dueño, funcionalidad todavía no construida).

**Lo que NO cambia de orden, y por qué:** `evidencias/{tipo}/{yyyy}/{mm}/{uuid_cliente}-{id}.{ext}` y `gastos/{yyyy}/{mm}/{gasto_id}.{ext}` (ADR 0009) siguen agrupadas primero por fecha, no por objeto dueño. El bucketing por año/mes ahí resuelve un problema distinto (volumen y una futura política de retención por antigüedad, no "encontrar todo lo de un objeto") y no tiene un único objeto dueño evidente en todos los casos (una evidencia de campo no siempre nace atada a un `trabajo_id` cerrado). Se deja fuera de esta ronda; si en el futuro hace falta también organizarlas por objeto, es una decisión propia con su propio análisis del trade-off fecha-vs-objeto, no una consecuencia automática de este ADR.

### Persistencia en producción entre releases: ya resuelta, sin cambios de este ADR

El dueño preguntó si estos archivos sobreviven a cada deploy de un tag de release (`Dockerfile.prod` reconstruye la imagen entera en cada lanzamiento). Ya está resuelto desde `docker-compose.prod.yml` (ADR 0017): `storage/` completo —incluye `storage/app/public`, donde viven los logos— es un volumen Docker con nombre (`agrocom-storage-prod:/var/www/html/storage`), que sobrevive a un `docker compose up -d --build` porque no es parte de la imagen. `public/storage` es un symlink **versionado en git** hacia la ruta absoluta del contenedor (`/var/www/html/storage/app/public`): se reconstruye con la imagen en cada deploy, pero al ser un symlink (no una copia) sigue resolviendo contra el volumen persistente sin depender de que alguien corra `storage:link` a mano después del deploy. Evidencias, actas, reportes técnicos, planillas y gastos ya persisten aparte, en R2 (servicio externo, fuera del ciclo de vida del contenedor). No hace falta ningún cambio de infraestructura para lo que agrega este ADR.

### Verificación de existencia y reconstrucción

La ruta guardada en `logo_path`/`pdf_path` puede sobrevivir a la desaparición del archivo físico (borrado a mano, purga de un bucket, un volumen que no persistió). Antes de servir cualquiera de estos archivos, el código verifica que exista de verdad — nunca confía en que la ruta guardada siga siendo válida — y el comportamiento ante una ausencia difiere según de quién es el archivo:

- **Imagen subida por el usuario (logo de empresa, logo de cliente):** no se puede reconstruir sola — nadie tiene el archivo original para volver a subirlo automáticamente. Si no existe, se muestra el placeholder fijo del repositorio (`public/images/logo-placeholder.png`, ya versionado). Centralizado en {@see \App\Dominios\Compartido\Aplicacion\ResolverUrlImagenConPlaceholder} — cualquier pantalla que MUESTRE un logo (no que lo administre) pasa por acá, para no repetir la misma verificación en cada controlador.
- **Documento propio del sistema (acta, reporte técnico, recibo de planilla):** SÍ se puede reconstruir, porque el contenido sale enteramente de filas que el propio sistema ya tiene guardadas (`ope_actas`, `ope_reportes_tecnicos`, `fin_planilla_detalles` y sus relaciones). Si la fila existe pero el PDF físico no, se vuelve a renderizar la MISMA vista con los datos ACTUALES de esas relaciones y se sube a la misma ruta (`pdf_path` no cambia) — nunca placeholder para esto, porque un documento en blanco o genérico sería peor que tardar un segundo más en reconstruirlo. "Datos actuales" es deliberado, no "lo que la fila ya tenía guardado": si el archivo se perdió y mientras tanto los datos de origen cambiaron (por ejemplo, una sesión del trabajo se corrigió), reconstruir con datos viejos serviría un documento desactualizado sin que nadie lo note — el método `asegurarPdf()` de cada generador (`GenerarActaTrabajo`, `GenerarReporteTecnico`, `GenerarReciboPlanilla`) vuelve a cargar las relaciones antes de renderizar, en vez de confiar en lo que el objeto ya tenía en memoria.
- **Fuera de alcance a propósito:** los endpoints de EDICIÓN/administración del logo (`ClientesController::logoArchivo()`, `OrganizacionController::logoArchivo()`) siguen devolviendo `null` cuando no hay archivo — ahí `null` significa "sin logo subido todavía" para que el `file-field` muestre su estado vacío, un contrato distinto al de una pantalla que solo MUESTRA el logo ya cargado en otro lugar.
- Evidencias (fotos, firmas) quedan fuera de este mecanismo: no son documentos que el sistema pueda regenerar (nadie puede volver a tomar la foto) ni tienen un "placeholder de evidencia" razonable — si el archivo no existe, sigue siendo un 404 liso, como hasta ahora.

## Alternativas descartadas

- **Carpeta genérica `uploads/` con subcarpetas por tipo de archivo** (imágenes, excel, pdf): agrupar por tipo de archivo en vez de por objeto dueño no resuelve el problema real — aislar los archivos de un objeto — y mezcla en una misma carpeta archivos de negocio con reglas de vida distintas (evidencia inmutable vs. logo reemplazable), que es exactamente lo que ADR 0009 ya había descartado al definir categorías por propósito.
- **Prefijar el id en el nombre de archivo en vez de anidar en carpeta** (`clientes/logo-42-{timestamp}.{ext}`): evita la colisión de nombre, pero no da lo mismo para aislar por directorio — con carpeta por id, listar, mover o respaldar "todo lo del cliente 42" es una operación de directorio, no un filtro de nombre de archivo.
- **Mantener "actividad primero" (redacción original de este ADR, mismo día)**: descartada por el dueño antes de que hubiera ningún archivo real escrito bajo ese orden en producción — el único caso implementado (logo de cliente) es de hoy mismo, así que no hay costo de migración por el cambio.

## Consecuencias

- `CrearCliente` y `ActualizarCliente` (`app/Dominios/Comercial/Aplicacion/`) escriben bajo `clientes/{cliente_id}/logos/...`.
- `GenerarActaTrabajo` y `GenerarReporteTecnico` (`app/Dominios/Operaciones/Aplicacion/`) escriben bajo `trabajos/{trabajo_id}/actas/...` y `trabajos/{trabajo_id}/reportes-tecnicos/...`.
- `GenerarReciboPlanilla` (`app/Dominios/Finanzas/Aplicacion/`) escribe bajo `planillas/{planilla_id}/recibos/...`.
- Ninguno de estos cambios requiere backfill: `logo_path`/`pdf_path` guardan la ruta completa por fila, así que los archivos ya generados (actas, reportes técnicos y planillas ya emitidos) siguen resolviendo con su ruta vieja tal cual está guardada. Solo lo que se genere de acá en más usa la ruta nueva.
- Queda como regla explícita para cualquier módulo que en el futuro suba o genere un archivo con más de un objeto dueño posible: raíz por tipo de objeto, subcarpeta por actividad, nunca al revés.
- No se toca la letra de ADR 0009 ni ADR 0019 en sus categorías de evidencias, gastos y logo de empresa — este ADR generaliza el resto y cierra el caso no documentado que había quedado suelto en el código.
- Nueva clase `Compartido/Aplicacion/ResolverUrlImagenConPlaceholder`, usada por `OrdenesController::logoUrl()` y `OrdenesTrabajoController::contratosParaFormulario()` — antes devolvían `null` sin el archivo, ahora siempre una URL válida.
- `ActaController::pdf()`, `TrabajosController::actaPdf()`/`reporteTecnicoPdf()`, `ReporteTecnicoController::mostrar()` y `PlanillasController::recibo()` ya no dan 404 solo porque el archivo físico falta: primero llaman a `asegurarPdf()` del generador correspondiente. Siguen dando 404 si la FILA no existe o `pdf_path` es `null` (nunca generado) — esos casos no cambian.
