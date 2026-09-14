<!-- ciclo: critica=si turno-noche=1 rama=feature/ciclos-bateria-odometro etapas=3 descongela=tests -->

# Tarea 102 — HU-87: el ciclo de batería se incrementa solo, como un odómetro

## Qué hacer

Hoy `ciclos_acumulados` de `man_baterias` se edita a mano desde el panel
(`ActualizarBateria::ejecutar()`, `app/Dominios/Mantenimiento/Aplicacion/ActualizarBateria.php:30-44`)
sin ningún control. El dueño lo comparó con el odómetro de un auto: el
contador tiene que subir solo, cuando una batería termina un ciclo real de
uso, y bajarlo a mano sin dejar rastro tiene que quedar bloqueado. Depende de
HU-83 (tarea 98, ya integrada — `ciclos_inicial` existe y es inmutable, no lo
toques).

**Es crítica**: toca el motor de eventos de dominio entre `Operaciones` y
`Mantenimiento` (CLAUDE.md, "qué no delegar sin revisión línea por línea").
Se integra igual a `develop` sin PR en borrador — esa política se corrigió el
2/9/2026 (`docs/gestion/automatizacion_desarrollo.md` §5: "ninguna sesión
abre su PR en borrador por ser crítica"). El ciclo anota el PR en
`runs/revision-pendiente.txt` solo. **No crees el PR vos mismo** en ningún
punto — dejá `runs/102.pr.md` listo y que `bin/ciclo` lo abra.

Cargá los skills `verificacion` y `dominio-backend` antes de tocar código.

### 1. Qué dispara el incremento

Una `Recarga` (`app/Dominios/Operaciones/Infraestructura/Eloquent/Recarga.php`)
no tiene máquina de estados ni "cierre": es un hecho puntual que nace ya
completo vía sync (`EscrituraSincronizacionEloquent::registrarRecarga()`,
`app/Dominios/Operaciones/Infraestructura/EscrituraSincronizacionEloquent.php:316-352`).
"Cerrarse cada recarga" de la HU se traduce en este repo a "quedar
registrada" — no inventes un `EstadoRecarga` ni una transición que no existe.

El punto de disparo es el `Recarga::query()->create(...)` de esa función
(línea ~326), dentro del mismo `DB::transaction`. `bateria_saliente_id` es
texto libre sin catálogo conocido por `Operaciones` (correlación blanda,
mismo criterio que `ope_drones`/`man_baterias` de HU-82) — el incremento le
corresponde a esa batería, la que volvió del vuelo.

Replicá el patrón exacto de `SesionValidada` (invariante 3 de CLAUDE.md):
- Evento nuevo `app/Dominios/Operaciones/Contratos/Eventos/RecargaRegistrada.php`
  (DTO readonly, un solo campo `string $bateriaSalienteId`), mismo molde que
  `SesionValidada.php`.
- `event(new RecargaRegistrada($datos->bateriaSalienteId));` justo después
  del `Recarga::query()->create(...)`, dentro del mismo `DB::transaction` —
  así un duplicado (`QueryException` por `uuid_cliente` repetido) nunca llega
  a disparar el evento, la idempotencia de la recarga alcanza gratis.
- Caso de uso oyente `app/Dominios/Mantenimiento/Aplicacion/IncrementarCiclosBateria.php`:
  busca `Bateria::query()->where('identificador', $bateriaSalienteId)->first()`;
  si no existe, no hace nada (correlación blanda, no es un error — igual que
  Operaciones no valida el identificador al recibir la recarga); si existe,
  `ciclos_acumulados++` y guarda (la bitácora del `RegistraBitacora` de
  `Bateria` ya deja el rastro de autor/momento/valores, invariante 9).
- Cableado en `MantenimientoServiceProvider::boot()`
  (`app/Dominios/Mantenimiento/Infraestructura/MantenimientoServiceProvider.php`,
  hoy vacío de listeners), mismo molde que
  `FinanzasServiceProvider::boot()` (`Event::listen(function (RecargaRegistrada $evento) { app(IncrementarCiclosBateria::class)->ejecutar($evento->bateriaSalienteId); });`).

### 2. Bloquear la baja a mano sin corrección explícita

`ActualizarBateria::ejecutar()` (línea 30) hoy acepta cualquier
`$ciclosAcumulados`, incluso menor al actual — es justo lo que hay que
cerrar. Sumale un parámetro nuevo, nullable, que tenga que llegar
explícitamente para permitir una baja: `?string $motivoCorreccion = null`.

- Si `$ciclosAcumulados < $bateria->ciclos_acumulados` y `$motivoCorreccion`
  es `null` (o vacío): rechazá con una excepción nueva
  `app/Dominios/Mantenimiento/Dominio/Excepciones/CorreccionCiclosNoAutorizada.php`
  (mismo molde que `BateriaDuplicada`: `RuntimeException` + factory estático
  con el identificador y los dos valores en el mensaje).
- Si viene el motivo, se permite — la bitácora automática ya registra
  antes/después + autor + momento (invariante 9); no hace falta tabla nueva
  de corrección tipo `anula_a_id` (esa es para registros validados de un
  flujo de dominio, invariante 2; un contador de catálogo no lo es).
- El formulario de edición de batería (`BateriasController`,
  `_formulario.blade.php`) necesita poder mandar ese motivo — si no, la
  corrección queda sin manera real de hacerse desde el panel. Un campo de
  texto nuevo alcanza (mostralo siempre o solo al detectar una baja, es tu
  criterio de UI); documentá la decisión en el commit.

## Cómo repartir las etapas

- **Etapa 1**: evento `RecargaRegistrada`, disparo desde
  `registrarRecarga()`, `IncrementarCiclosBateria`, cableado en el
  ServiceProvider. Tests: uno con `Event::fake()` + `assertDispatched` desde
  `registrarRecarga()` (mismo molde que
  `tests/Feature/Operaciones/MaquinaEstadosValidacionTest.php:90-108`), y uno
  que dispara `event(new RecargaRegistrada(...))` dos veces con el mismo
  identificador y comprueba que `ciclos_acumulados` sube dos veces (mismo
  molde que `tests/Feature/Finanzas/GenerarDevengosSesionTest.php`,
  líneas ~213-219, para probar el listener aislado del transporte HTTP).
- **Etapa 2**: guarda en `ActualizarBateria`, excepción nueva, campo de
  motivo en el formulario del panel, tests de la guarda (baja rechazada sin
  motivo, aceptada con motivo).
- **Etapa 3**: `./bin/verify` completo, `runs/102.pr.md`.

## Qué NO hacer

- No inventes `EstadoRecarga` ni un método `cerrar()` en `Recarga` — no
  existe y no lo pide la HU.
- No repliques el patrón `anula_a_id` de `RechazarSesion`
  (`ope_sesion_rechazos`) para esto — es para registros validados de un
  flujo de dominio (invariante 2), no para un contador de catálogo. Un
  parámetro de motivo explícito + la bitácora automática alcanza.
- No toques `ciclos_inicial` (tarea 98) ni le agregues lógica nueva — sigue
  fijo al alta.
- No crees el PR vos mismo ni lo pases por `--draft` — lo abre el ciclo.
- No toques `docs/decisiones/**` — no hay ADR que ampliar acá, es
  implementación de una HU ya decidida.

## Criterio de aceptación

`./bin/verify` = 0, con:
- Test de que dos recargas de la misma batería (mismo `bateria_saliente_id`)
  incrementan `ciclos_acumulados` dos veces.
- Test de que `ActualizarBateria` rechaza una baja de `ciclos_acumulados`
  sin `motivoCorreccion`, y la acepta con él.
- Test de regresión: una recarga con `bateria_saliente_id` que no
  corresponde a ninguna batería cargada no rompe el sync (sigue devolviendo
  `aplicado()`).

## Cierre obligatorio de cada etapa

`runs/102.estado` con una sola palabra: `PARCIAL` si avanzó y commiteó pero
la HU sigue abierta, `OK` recién cuando está entera, `BLOQUEADA` si falta una
decisión que no le corresponde. `runs/102.md` con qué se hizo y **qué
falta**, concreto. Al cerrar con `OK`, `runs/102.pr.md` con el título del PR
en la primera línea y el cuerpo debajo.

## Commits

Agrupados por función (evento + listener + cableado, guarda de corrección +
UI del motivo, tests si no entraron ya en los anteriores), español,
imperativo, explicando el porqué. Sin trailer `Co-Authored-By`.
