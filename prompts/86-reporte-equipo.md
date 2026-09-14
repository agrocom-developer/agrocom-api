<!-- ciclo: critica=si turno-noche=1 descongela=tests rama=feature/reporte-equipo etapas=4 -->

# Tarea 86 — HU-80: ciclos de batería, horas de vuelo y evidencia de equipo en el reporte técnico

## Por qué esta tarea

Segundo reclamo por audio del dueño (13/9/2026): *"cantidad del ciclo de
batería, ciclo actual, cuándo se emitió el reporte"*. La segunda versión del
Word sumó la sección "Reporte de Equipos": horas de vuelo, foto de control,
foto de cada ciclo de batería y balanceo, foto de dron limpio. Segunda del
Sprint 16 — no depende de nada, así que va apenas después de la 85.

El dato de ciclos **ya existe** en `Mantenimiento` (`man_baterias.ciclos_acumulados`,
tarea 51); acá falta cruzarlo hacia el reporte técnico y sumar la evidencia
fotográfica nueva.

## Lo que ya existe

- `Operaciones/Aplicacion/ArmarContenidoReporteTecnico.php` arma el contenido
  del reporte como array plano (para poder testearlo sin abrir el PDF
  comprimido) y `Infraestructura/Http/Views/pdf/reporte-tecnico.blade.php` lo
  renderiza. Es donde sumás la sección nueva.
- `ope_reportes_tecnicos.generado_en` **ya existe en la base** (columna del
  modelo `ReporteTecnico`) — hoy no se imprime en el PDF. Es agregar la fila
  a la tabla del blade, nada de esquema.
- `Operaciones/Contratos/LecturaAlertasTemperaturaBateria` es el molde exacto
  a copiar: contrato de lectura de `Operaciones` hacia `Mantenimiento`,
  correlación por **igualdad de texto** entre `man_baterias.identificador` y
  `ope_recargas.bateria_saliente_id` (sin FK real — mismo motivo documentado
  en su docblock: no existía catálogo de baterías cuando esa columna nació).
- `Operaciones/Contratos/LecturaHorasVueloPorModelo` documenta el criterio ya
  asentado en el proyecto: **las horas de vuelo nunca se persisten**, se
  recalculan de `fin - inicio` de las sesiones cerradas (mismo espíritu que
  la invariante 6, aunque hable de plata/hectáreas). Aplicá el mismo criterio
  acá: `horas_vuelo_dron` del reporte se calcula sumando `fin - inicio` de las
  sesiones del propio `Trabajo` agrupadas por `dron_id` — **no agregues una
  columna nueva para guardarlo**.
- `Operaciones/Dominio/TipoEvidencia` es el enum cerrado (`captura_rc`,
  `imagen_campo`, `foto_incidencia`, `comprobante`, `firma_acta`) con `CHECK`
  en `ope_evidencias` (migración `2026_09_01_100012_create_ope_evidencias_table.php`).
  Sumar un tipo nuevo es agregar el caso al enum + `ALTER` del `CHECK`
  (`DB::getDriverName() === 'pgsql'`, mismo patrón que esa migración).
- `Trabajo::imagenCampoEvidencia()` es el molde de cómo un trabajo cuelga UNA
  evidencia propia por FK nullable (`imagen_campo_evidencia_id`), poblada por
  `EscrituraSincronizacionEloquent::cerrarTrabajo()`. Las tres fotos nuevas
  van con el mismo molde, pero **no** dentro de `cerrarTrabajo()` — no son
  obligatorias para cerrar (a diferencia de `imagen_campo`, HU-09), así que
  necesitan su propio método de escritura del motor de sync, independiente,
  que el piloto puede llamar en cualquier momento antes de que se genere el
  reporte.

## Qué hacer

Cargá los skills `verificacion`, `dominio-backend` y `modelo-datos`.

1. **Enum**: agregá a `TipoEvidencia` los tres casos nuevos (nombralos
   `foto_control`, `foto_ciclo_bateria_balanceo`, `foto_dron_limpio` o el
   nombre que te resulte más claro — mantené el criterio de nombres del
   enum existente). `ALTER` del `CHECK` de `ope_evidencias.tipo` en una
   migración nueva.
2. **`ope_trabajos`**: `ALTER` que agrega tres columnas nullable
   (`foto_control_evidencia_id`, `foto_ciclo_bateria_balanceo_evidencia_id`,
   `foto_dron_limpio_evidencia_id`), cada una FK a `ope_evidencias` sin
   `belongsTo` cruzado — mismo patrón que `imagen_campo_evidencia_id`.
   Relaciones nuevas en `Trabajo` Eloquent, mismo molde que
   `imagenCampoEvidencia()`.
3. **Motor de sync**: un método nuevo en `EscrituraSincronizacion`/
   `EscrituraSincronizacionEloquent` (p. ej. `registrarEvidenciaEquipo`) que
   recibe `trabajo_uuid_cliente`, `tipo` (uno de los tres nuevos) y
   `evidencia_uuid_cliente`; busca el trabajo y la evidencia por
   `uuid_cliente`, valida que el `tipo` de la evidencia matchee, y setea la
   columna que corresponda según `tipo`. Mismo patrón de rechazo que
   `registrarIncidencia()` (evidencia inexistente o de tipo distinto →
   `rechazado`, sin frenar el resto del lote). Sumalo a
   `SincronizarLote::ORDEN_CAUSAL` **después** de `trabajo` (puede llegar en
   el mismo lote que lo abre).
4. **`LecturaCiclosBateria`** (`Operaciones/Contratos/`, mismo molde que
   `LecturaAlertasTemperaturaBateria`): `ciclosAcumulados(string
   $identificador): ?int`. Implementación en `Mantenimiento/Infraestructura/`
   consultando `man_baterias` por `identificador` (texto exacto, sin FK).
   Resolvé el identificador a consultar desde la **recarga más reciente** de
   las sesiones del trabajo (`bateria_saliente_id`); si el trabajo no tiene
   ninguna recarga, el campo va `null` en el reporte.
5. **`ArmarContenidoReporteTecnico`**: sumá una clave `equipo` al array de
   salida con `ciclos_bateria_actual` (vía el contrato nuevo),
   `horas_vuelo_dron` (calculado localmente, por `dron_id`), y las tres URLs
   de evidencia (`foto_control_url`, `foto_ciclo_bateria_balanceo_url`,
   `foto_dron_limpio_url`, cada una `null` si no se registró). Actualizá el
   PHPDoc del array de retorno.
6. **`reporte-tecnico.blade.php`**: sección nueva "Reporte de equipo" con
   ciclo de batería, horas de vuelo, las tres fotos (mismo patrón
   `EvidenciaIncrustada::dataUri()` que ya usa `imagen_campo`/`capturas_rc`),
   y la fecha/hora de emisión (`$reporte->generado_en`, ya disponible —
   confirmá cómo llega la variable al blade, hoy solo usa `$datos`/`$trabajo`).

## Qué NO hacer

- No persistas `horas_vuelo_dron` en una columna. El proyecto ya decidió que
  las horas de vuelo se recalculan siempre (`LecturaHorasVueloPorModelo`) —
  guardarlas acá contradice esa decisión ya escrita.
- No metas las tres fotos nuevas dentro de `cerrarTrabajo()` ni las hagas
  obligatorias para cerrar el trabajo — HU-09 (imagen de campo) es la única
  evidencia obligatoria al cierre; esta es independiente.
- No conviertas `bateria_saliente_id`/`man_baterias.identificador` en FK real.
  El motivo ya está escrito en el docblock de `LecturaAlertasTemperaturaBateria`
  y sigue aplicando.
- No toques `nota_mezcla()` ni la sección de mezcla del reporte — sigue fuera
  de alcance (CR-01), es la tarea 94 la que la revierte.

## Cómo repartir las etapas

- **Etapa 1**: enum + migraciones (`CHECK` de evidencias, columnas de
  `ope_trabajos`), relaciones Eloquent, tests unitarios.
- **Etapa 2**: `registrarEvidenciaEquipo` en el motor de sync + `LecturaCiclosBateria`
  con su implementación en Mantenimiento, tests Feature de sync (aceptado,
  rechazado por tipo incorrecto, rechazado por evidencia inexistente).
- **Etapa 3**: `ArmarContenidoReporteTecnico` + blade, test de contenido
  armado (ciclos reales, horas calculadas, fotos presentes/ausentes).
- **Etapa 4**: `bin/verify` de punta a punta, revisión de que `generado_en`
  se imprime.

## Criterio de aceptación

- `./bin/verify` = 0.
- Test: el reporte técnico de un trabajo cuya última recarga referencia una
  batería real de `Mantenimiento` muestra su `ciclos_acumulados` exacto.
- Test: `ArmarContenidoReporteTecnico` calcula `horas_vuelo_dron` sumando
  `fin - inicio` de las sesiones cerradas del trabajo, no de una columna.
- Test: registrar una evidencia de equipo con un `tipo` que no matchea la
  evidencia real se rechaza sin frenar el resto del lote.
- Test: el PDF/contenido armado imprime `generado_en`.

## Puede tocar

`app/Dominios/Operaciones/**` (incluido `Contratos/` para el contrato de
lectura nuevo), `app/Dominios/Mantenimiento/Infraestructura/**` (su
implementación), `app/Dominios/Sincronizacion/Aplicacion/**` (orden causal),
migraciones nuevas, `tests/**`.

## Cierre obligatorio de cada etapa

`runs/86.estado`, `runs/86.md`, y al `OK` `runs/86.pr.md`. Commits agrupados
por función, español, imperativo, sin `Co-Authored-By`.
