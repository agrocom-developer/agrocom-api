<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/galeria-evidencias etapas=2 -->

# Tarea 56 — HU-42: galería de evidencias de un trabajo

## Por qué esta tarea

`plan_sprints.md` Sprint 12 (§251): "Como jefe de campo, quiero ver la
galería de evidencias de un trabajo, para revisar sin abrir la base."
Criterio: pantalla sobre `ope_evidencias` agrupada por trabajo y sesión, con
miniaturas y descarga.

No depende de la tarea 55 (portal): son módulos y guards distintos (panel
interno vs. portal del cliente). Podés arrancarla aunque la 55 no esté
integrada todavía — si lo está, no la toques, no hay superposición de
archivos.

No es crítica: pantalla de solo lectura, sin dinero ni estado.

## Lo que ya existe — el dato ya está, falta la pantalla

`ope_evidencias` no tiene `trabajo_id`/`sesion_id` propio — se referencia
desde el lado que la usa, con FK real (mismo módulo `Operaciones`, ADR 0003
regla 1):

- `Trabajo::imagenCampoEvidencia()` — `BelongsTo`, imagen de campo al cierre
  (HU-09, tarea 21).
- `Acta::evidenciaFirma()` — `BelongsTo`, firma del agrónomo (HU-17, tarea
  24).
- `Sesion::incidencias()` (`HasMany`) → `Incidencia::evidenciaFoto()`
  (`BelongsTo`) — foto de cada incidencia (HU-08, tarea 22).

Esas tres son TODAS las evidencias que existen hoy para un trabajo — no hay
`captura_rc` a nivel de sesión (recorte de la tarea 09, sigue pendiente,
fila 21 de `cola_tareas.md`; no lo agregues, fuera de alcance).

`Evidencia.archivo_url` (pese al nombre) es una ruta privada del disco
`r2`, no una URL pública — mismo criterio que `Acta.pdf_path`/
`ReporteTecnico.pdf_path`. Nunca la expongas directo en el HTML ni uses
`Storage::url()`: servila por streaming, mismo patrón que
`TrabajosController::actaPdf()`/`reporteTecnicoPdf()`
(`app/Dominios/Operaciones/Infraestructura/Http/Controllers/Web/TrabajosController.php`).
`Evidencia.tipo` (`captura_rc`, `imagen_campo`, `foto_incidencia`,
`comprobante`, `firma_acta`) te dice el tipo de icono/preview a mostrar —
son todas imágenes salvo que a futuro se suban otros formatos; no asumas
extensión, mirá el `hash`/`archivo_url` real.

## Qué hacer

Cargá las skills `dominio-backend`, `panel-design-ui` y `verificacion`.

### 1. Acción nueva en `TrabajosController`

Extendé el controlador existente (mismo patrón que `actaPdf`/
`reporteTecnicoPdf`, mismo permiso `operaciones.trabajo.ver` que ya gatea
`show()` — es parte del detalle de un trabajo, no un recurso nuevo con
permiso propio):

- `GET /panel/trabajos/{trabajo}/evidencias` → vista con las evidencias
  agrupadas: la imagen de campo del trabajo (si existe), la firma del acta
  (si existe), y por cada sesión del trabajo, sus incidencias con foto.
  Cargá las relaciones con `with()` para no generar N+1 (mismo criterio que
  `show()`, que ya hace `$trabajo->load([...])`).
- `GET /panel/evidencias/{evidencia}/archivo` (o ruta equivalente dentro del
  mismo controlador o uno nuevo `EvidenciasController`, a tu criterio) →
  streaming del archivo real desde `Storage::disk('r2')`, 404 si no existe
  en disco. Sin permiso propio adicional: quien llega a la galería ya pasó
  `operaciones.trabajo.ver`, pero igual verificá ese permiso en este
  endpoint (no asumas que nadie pega la URL directo sin pasar por la
  pantalla).

### 2. Vista

`resources/views/.../pages/trabajos/evidencias.blade.php` (o el nombre que
uses), tres secciones (imagen de campo, firma de acta, incidencias por
sesión), miniaturas con link a la descarga/apertura del archivo real. Un
link desde `trabajos/show.blade.php` hacia esta pantalla nueva (agregalo
junto a los links existentes de acta/reporte técnico).

### 3. Menú

No hay ítem de menú propio para esto en `SecMenuSeeder.php` — es una
sub-pantalla del detalle de trabajo, no un ítem de primer nivel. No agregues
uno: se llega desde `trabajos/show`, igual que las descargas de PDF.

## Qué NO hacer

- No le agregues `trabajo_id`/`sesion_id` a `ope_evidencias` — no hace
  falta, el dato ya es alcanzable desde el lado que lo referencia (ver
  arriba). Cambiar el esquema para esto sería una migración sin motivo real.
- No implementes `captura_rc_id` en `ope_sesiones` — recorte explícito de
  otra tarea (fila 21 de `cola_tareas.md`), fuera del alcance de esta HU.
- No expongas `archivo_url` como link directo ni con `Storage::url()`.
- No crees un permiso nuevo — reusá `operaciones.trabajo.ver`.

## Cómo repartir las etapas

- **Etapa 1**: acciones del controlador (galería + streaming del archivo),
  vista, link desde `trabajos/show`, tests Feature.
- **Etapa 2**: spec visual (claro/oscuro), checklist §8, `bin/verify`
  completo.

## Criterio de aceptación

- `./bin/verify` = 0, con Playwright.
- Test Feature: un trabajo con imagen de campo, acta firmada y una
  incidencia con foto muestra las tres en la galería; un trabajo sin
  ninguna evidencia no rompe la pantalla (secciones vacías, no error); 403
  sin `operaciones.trabajo.ver`; la descarga del archivo devuelve el
  contenido real (no un 404 ni la ruta cruda).
- Spec visual (`tests/Visual/evidencias-trabajo.spec.ts` o nombre
  equivalente), claro y oscuro.
- Checklist de `docs/diseno/guia_pantalla_panel.md` §8 pasa.

## Puede tocar

`app/Dominios/Operaciones/Infraestructura/Http/Controllers/Web/TrabajosController.php`
(o un `EvidenciasController` nuevo en la misma carpeta), `routes/web.php`,
vistas bajo `Operaciones/Infraestructura/Http/Views/pages/trabajos/`,
`tests/**`.

Fuera de alcance: cualquier migración, `SecMenuSeeder.php`,
`SeguridadSeeder.php`, y todo lo que no sea la galería en sí.
