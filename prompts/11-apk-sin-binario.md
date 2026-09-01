<!-- ciclo: critica=no turno-noche=1 descongela=tests,decisiones rama=feature/version-apk etapas=2 -->

# Tarea 11 — HU-20: el binario del APK vive en `agrocom-field`, no acá

## Esto NO es una tarea nueva: es una corrección de alcance sobre trabajo ya hecho

Estás retomando la rama `feature/version-apk`, que **ya tiene HU-20
implementada y aprobada por el verificador** (6 commits, tarea 10). No
empieces de cero, no rehagas lo que está, no borres la tabla ni la máquina de
estados. Leé lo que hay con `git log --oneline develop..feature/version-apk`
y `runs/10.md` antes de tocar nada.

El usuario revisó ese trabajo y pidió **un solo cambio de alcance**. Todo lo
demás queda exactamente como está.

### El cambio pedido, y su porqué

La tarea 10 hospeda el binario `.apk` en este servidor: lo sube al disco `r2`
(`distribucion/apk/{version}.apk`) y sirve una URL firmada con expiración.
Eso sale.

El motivo: **`agrocom-field` existe** — `https://github.com/agrocom-developer/agrocom-field.git`,
creado el 25/8/2026, no vacío. El prompt de la tarea 10 afirmaba que "ese repo
no existe todavía" y razonó sobre ese dato falso. La app y su binario son de
ese repo; el release del `.apk` se publica allá (GitHub Releases), no se sube
acá.

Lo que **sí** es de este repo y se queda tal cual: el **control** de qué
versión está autorizada. HU-20 dice "autorizar las versiones del APK **desde
el panel**", y el panel es `agrocom-api`. `agrocom-api` sigue siendo la
autoridad sobre qué versión puede operar; lo que deja de ser es el *hosting*
del archivo.

## Qué se queda, intacto

No toques nada de esto salvo lo mínimo que arrastre el cambio de columna:

- La tabla `dis_versiones_apk`, sus tres índices únicos parciales y sus CHECK.
- La invariante "una sola versión `autorizada` a la vez" y el índice parcial
  que la respalda.
- `MaquinaEstadosVersionApk` y toda la máquina de estados (invariante 7).
- El permiso `distribucion.version.autorizar`, su seed y el rol dueño.
- El contrato `AutorizacionPanelWeb`.
- La pantalla del panel: listar, registrar y autorizar.
- `GET /api/version` sin auth, con throttle, y su forma de respuesta
  (`minima` / `vigente`).
- `runs/10-diseno.md` y la fila del módulo `Distribucion` en ADR 0011.

## Qué cambia

1. **La columna `ruta_apk` pasa a `url_apk`**: ya no una clave dentro de `r2`,
   sino la **URL absoluta del release en `agrocom-field`**. Editá la migración
   existente en lugar de agregar un `ALTER TABLE`: la tabla nace en esta rama
   y nunca llegó a `develop`, así que no hay nada desplegado que migrar.
   Actualizá su docblock — hoy dice explícitamente "nunca una URL", que es lo
   contrario de lo que va a ser.

   **Para aplicarla en el Postgres del compose: `php artisan migrate:rollback
   --step=1` y volver a migrar.** Nunca `migrate:fresh`, `migrate:refresh` ni
   `db:wipe` — hay datos demo cargados que no se borran (el guardarraíl te lo
   va a denegar igual).

2. **`SubirVersionApk` desaparece como "subida"**: pasa a registrar. Recibe
   `version`, `version_code` y la URL del release; sin `UploadedFile`, sin
   `Storage::disk('r2')`. Renombrala a lo que describa lo que hace ahora
   (`RegistrarVersionApk`), junto con su Request y su test.

3. **La validación de la URL**: `required`, `url`, y esquema `https`
   obligatorio. No la ates al dominio de GitHub con una regex — si mañana el
   release se sirve desde otro lado, esa regla es una piedra; que sea una URL
   https válida alcanza.

4. **`VersionApkResource` deja de firmar nada**: expone la URL tal cual está
   guardada. Sacá la generación de URL temporal y su import de `Storage`.
   Ajustá también la descripción OpenAPI del endpoint, que hoy dice "enlace de
   descarga firmado", y regenerá `docs/api/openapi.yaml` (`composer openapi`).

5. **El panel deja de recibir un archivo**: el formulario pide la URL del
   release en vez de un `<input type="file">`. Mismo permiso, mismo flujo de
   autorización, misma pantalla — cambia un campo. Sin colores hardcodeados
   (invariante 11); si tocás CSS, cargá `panel-design-ui`.

6. **Revertí la extensión del ADR 0009.** El commit `aae0b3c` le agregó la
   sección "Extensión (1/9/2026) — ruta del binario `.apk`". Como el binario
   ya no se hospeda acá, esa extensión no tiene objeto: sacala y dejá el ADR
   como estaba. No toques nada más de ese ADR — evidencias, reportes y assets
   siguen exactamente igual.

7. **Anotá la regla de exclusión en `docs/gestion/cola_tareas.md`**, en la
   sección "Fuera del ciclo automático" — y escribila como **regla
   permanente**, no como nota de esta tarea. Es una instrucción explícita del
   usuario, y existe porque el prompt de la tarea 10 razonó sobre un dato
   falso y trajo trabajo de la app a este repo:

   > **La app no entra al ciclo automático.** `agrocom-field`
   > (`https://github.com/agrocom-developer/agrocom-field.git`, creado el
   > 25/8/2026) **ya existe** y es un proyecto **Flutter**. Todo lo que sea
   > de la app queda fuera de este ciclo, sin excepción y sin importar en qué
   > sprint aparezca: el build del APK, su binario y su publicación, el
   > bloqueo por versión mínima del lado cliente, la UI del piloto, el outbox
   > offline (TE-04) y cualquier código Dart/Flutter. `agrocom-api` aporta
   > solo el lado servidor: endpoints, panel web y permisos. Si una HU mezcla
   > ambas cosas, entra al ciclo **únicamente** su parte de servidor, y el
   > prompt debe decir explícitamente qué queda del lado de la app.

   Ninguna planificación futura debe volver a escribir que `agrocom-field`
   "no existe todavía". Actualizá también la fila de la tarea 10 para que
   refleje el alcance real.

## Qué NO hacer

- No borres ni rehagas la tabla, la máquina de estados, el permiso ni la
  pantalla. El trabajo de la tarea 10 está aprobado; esto es un recorte
  quirúrgico.
- No implementes nada del lado de la app: el bloqueo por versión mínima, el
  build y la publicación del release son de `agrocom-field`.
- No toques `app/Dominios/Operaciones/` ni `Sincronizacion/` — sus tablas no
  existen en esta rama (TE-05 sigue en el PR #46, en borrador).
- No saques `bootstrap/cache/services.php` del control de versiones: ya venía
  trackeado desde antes de esta rama, y decidir eso no es de esta tarea.
- No reabras ADR 0011 (la fila del módulo `Distribucion` se queda) ni el resto
  del ADR 0009.

## Criterio de aceptación

`./bin/verify` → exit code 0, con los tests de HU-20 adaptados y en verde:
`GET /api/version` devuelve la vigente y la mínima **con la URL del release**;
autorizar sin el permiso → 403; autorizar una versión nueva desautoriza la
anterior. `ArquitecturaModulosTest` y `TransicionesEstadoTest` en verde sin
editarlos. Y, explícito de esta tarea: **ninguna referencia viva al disco
`r2`, a `Storage` ni a `ruta_apk` queda en `app/Dominios/Distribucion/`** —
verificalo con `grep -rn "r2\|ruta_apk\|Storage" app/Dominios/Distribucion/`
antes de cerrar.

## Cierre obligatorio de cada etapa

`runs/11.estado` con una sola palabra: `PARCIAL` si avanzó y commiteó pero
falta, `OK` cuando los siete puntos están hechos y la cascada cierra en 0,
`BLOQUEADA` si aparece una decisión que no te corresponde.

`runs/11.md` con qué se hizo y qué falta, concreto.

Al cerrar con `OK`, `runs/11.pr.md` con el título en la primera línea y el
cuerpo debajo. **Ojo: el PR es de HU-20 entera**, no de esta corrección — la
rama lleva los commits de la tarea 10 y los tuyos juntos, y la unidad de
entrega es la historia completa. Escribilo así: qué hace HU-20, y una sección
que explique que el binario se distribuye desde `agrocom-field` y este repo
solo autoriza.

## Commits

Agrupados por función, español, imperativo, el porqué antes que el qué, sin
`Co-Authored-By`. Por ejemplo: uno para el cambio de columna y el registro por
URL, uno para el endpoint y el recurso, uno para la pantalla, uno para revertir
la extensión del ADR 0009 y anotar `agrocom-field` en la cola.
