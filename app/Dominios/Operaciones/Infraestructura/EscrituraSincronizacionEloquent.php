<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use App\Dominios\Operaciones\Aplicacion\GenerarAlertaExcepcion;
use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosSesion;
use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosTrabajo;
use App\Dominios\Operaciones\Contratos\AperturaEstadiaHacienda;
use App\Dominios\Operaciones\Contratos\AperturaSesion;
use App\Dominios\Operaciones\Contratos\AperturaTrabajo;
use App\Dominios\Operaciones\Contratos\CierreEstadiaHacienda;
use App\Dominios\Operaciones\Contratos\CierreSesion;
use App\Dominios\Operaciones\Contratos\CierreTrabajo;
use App\Dominios\Operaciones\Contratos\EscrituraSincronizacion;
use App\Dominios\Operaciones\Contratos\Eventos\RecargaRegistrada;
use App\Dominios\Operaciones\Contratos\RegistroCondiciones;
use App\Dominios\Operaciones\Contratos\RegistroEvidenciaEquipo;
use App\Dominios\Operaciones\Contratos\RegistroIncidencia;
use App\Dominios\Operaciones\Contratos\RegistroRecarga;
use App\Dominios\Operaciones\Contratos\RegistroRecepcionCaldo;
use App\Dominios\Operaciones\Contratos\ResultadoSincronizacion;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Dominio\Excepciones\TransicionSesionNoPermitida;
use App\Dominios\Operaciones\Dominio\Excepciones\TransicionTrabajoNoPermitida;
use App\Dominios\Operaciones\Dominio\TipoEvidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Condiciones;
use App\Dominios\Operaciones\Infraestructura\Eloquent\EstadiaHacienda;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Evidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\EvidenciaEquipo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Incidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Recarga;
use App\Dominios\Operaciones\Infraestructura\Eloquent\RecepcionCaldo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Implementación Eloquent del contrato de escritura de `Operaciones` (ADR
 * 0003, regla 2). Vive fuera de `Infraestructura/Eloquent/` a propósito,
 * mismo criterio que `LecturaOrdenesVigentesEloquent`: esa subcarpeta está
 * reservada a modelos que extienden `ModeloDominio`
 * (`tests/Unit/ArquitecturaModulosTest.php` lo exige), y esta clase no es un
 * modelo — es el adaptador que el `ServiceProvider` del módulo liga a
 * {@see EscrituraSincronizacion}.
 *
 * La resolución de `sesion → trabajo` por `uuid_cliente` (espec §2.1, punto
 * 5) es una consulta interna del propio módulo (ADR 0003, regla 1: un módulo
 * lee y escribe sus propias tablas sin restricción entre sí) — el trabajo
 * referenciado, si vino en el mismo lote, ya quedó persistido:
 * `Sincronizacion\Aplicacion\SincronizarLote` aplica siempre `trabajo` antes
 * que `sesion`, sin importar el orden de llegada del arreglo del cliente.
 *
 * `duplicado` se resuelve capturando la violación del índice único parcial
 * (invariante 1 de CLAUDE.md) — nunca con un `SELECT` previo, que tendría
 * condición de carrera. Cualquier otra violación de la base (FK inexistente,
 * dato fuera de rango) se traduce a `rechazado`: un lote no se frena por un
 * registro individual inválido (espec §2.1, punto 3).
 */
final class EscrituraSincronizacionEloquent implements EscrituraSincronizacion
{
    public function __construct(
        private readonly MaquinaEstadosTrabajo $maquinaTrabajo,
        private readonly MaquinaEstadosSesion $maquinaSesion,
        private readonly GenerarAlertaExcepcion $alertas,
    ) {}

    /**
     * Antes de aplicar (tarea 12, hallazgo 2), verifica que `orden_id` y
     * `lote_id` formen un par legítimo: la orden debe existir, estar
     * `Vigente`, y el `lote_id` declarado debe pertenecer a sus lotes
     * (`ope_orden_lotes`, HU-92 tarea 107 — antes un único `lote_id` por
     * orden). La espec (§4.3: "el trabajo no lleva piloto propio — un lote
     * puede tener varios pilotos y drones por relevo, falla o logística")
     * descarta que exista una noción de "lote del operario"; lo único
     * verificable con lo que hay en el repo es que el par orden/lote sea
     * consistente con el catálogo vigente que cualquier operario legítimo
     * puede operar — un `lote_id` que no integra esa orden (o una orden ya
     * consumida/vencida/emitida sin vigencia) no es algo que el operario
     * pueda tocar, aunque ambos ids existan físicamente y la FK los acepte.
     */
    public function abrirTrabajo(AperturaTrabajo $datos): ResultadoSincronizacion
    {
        $orden = OrdenAplicacion::query()->find($datos->ordenId);

        if ($orden === null || $orden->estado !== EstadoOrdenAplicacion::Vigente) {
            return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.orden_lote_no_vigente'));
        }

        $loteEsDeLaOrden = $orden->ordenLotes()->where('lote_id', $datos->loteId)->exists();

        if (! $loteEsDeLaOrden) {
            return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.orden_lote_no_vigente'));
        }

        try {
            DB::transaction(function () use ($datos): void {
                $this->maquinaTrabajo->abrir([
                    'uuid_cliente' => $datos->uuidCliente,
                    'orden_id' => $datos->ordenId,
                    'lote_id' => $datos->loteId,
                    'nro_aplicacion' => $datos->nroAplicacion,
                    'hectareas_declaradas' => $datos->hectareasDeclaradas,
                    'inicio' => $datos->inicio,
                    'fin' => $datos->fin,
                ]);
            });
        } catch (QueryException $excepcion) {
            return $this->resultadoDesdeExcepcion($excepcion, 'ope_trabajos');
        }

        return ResultadoSincronizacion::aplicado();
    }

    public function abrirSesion(AperturaSesion $datos): ResultadoSincronizacion
    {
        $trabajoId = Trabajo::query()->where('uuid_cliente', $datos->trabajoUuidCliente)->value('id');

        if ($trabajoId === null) {
            return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.trabajo_no_existe_aun'));
        }

        try {
            DB::transaction(function () use ($datos, $trabajoId): void {
                $this->maquinaSesion->abrir([
                    'uuid_cliente' => $datos->uuidCliente,
                    'trabajo_id' => $trabajoId,
                    'secuencia' => $datos->secuencia,
                    'piloto_id' => $datos->pilotoId,
                    'auxiliar_id' => $datos->auxiliarId,
                    'dron_id' => $datos->dronId,
                    'hectareas_declaradas' => $datos->hectareasDeclaradas,
                    'hectarea_inicial_acumulada' => $datos->hectareaInicialAcumulada,
                    'inicio' => $datos->inicio,
                    'fin' => $datos->fin,
                ]);
            });
        } catch (QueryException $excepcion) {
            return $this->resultadoDesdeExcepcion($excepcion, 'ope_sesiones');
        }

        return ResultadoSincronizacion::aplicado();
    }

    /**
     * Condiciones de vuelo (HU-06, tarea 17): a diferencia de
     * `abrirTrabajo()`/`abrirSesion()`, un registro puede ser estructuralmente
     * válido y aun así no persistirse — fuera de rango sin observación
     * firmada del agrónomo se rechaza ANTES de intentar el `INSERT` (espec
     * §5: "condiciones dentro de rango" u "observación firmada" son las dos
     * únicas condiciones que autorizan; ninguna de las dos, no autoriza
     * nada).
     *
     * `trabajo_id` se resuelve leyendo la sesión (denormalizado, ver
     * docblock de `RegistroCondiciones` y de la migración): no hace falta un
     * segundo `uuid_cliente` de trabajo en el payload.
     *
     * `autorizado` (bool persistido): TRUE cuando cae dentro de rango, FALSE
     * cuando quedó autorizado solo por la observación del agrónomo — nunca
     * FALSE por rechazo, porque ese caso no llega a crear fila (ver arriba).
     */
    public function registrarCondiciones(RegistroCondiciones $datos): ResultadoSincronizacion
    {
        $sesion = Sesion::query()->where('uuid_cliente', $datos->sesionUuidCliente)->first();

        if ($sesion === null) {
            return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.sesion_no_existe_aun'));
        }

        $dentroDeRango = $datos->dentroDeRango();

        if (! $dentroDeRango && ! $datos->tieneObservacionFirmada()) {
            return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.condiciones_fuera_de_rango'));
        }

        try {
            DB::transaction(function () use ($datos, $sesion, $dentroDeRango): void {
                $condiciones = Condiciones::query()->create([
                    'uuid_cliente' => $datos->uuidCliente,
                    'trabajo_id' => $sesion->trabajo_id,
                    'sesion_id' => $sesion->id,
                    'momento' => $datos->momento,
                    'viento_kmh' => $datos->vientoKmh,
                    'temperatura_c' => $datos->temperaturaC,
                    'humedad_pct' => $datos->humedadPct,
                    'autorizado' => $dentroDeRango,
                    'observacion_agronomo' => $datos->observacionAgronomo,
                    'firma_observacion' => $datos->firmaObservacion,
                ]);

                // HU-19 (tarea 26): "condiciones forzadas" — autorizado solo
                // por observación del agrónomo, nunca por rango (ver arriba).
                if (! $dentroDeRango) {
                    $this->alertas->porCondicionesForzadas($condiciones);
                }
            });
        } catch (QueryException $excepcion) {
            return $this->resultadoDesdeExcepcion($excepcion, 'ope_condiciones');
        }

        return ResultadoSincronizacion::aplicado();
    }

    /**
     * Incidencia de sesión (espec §4.3, HU-08, tarea 22): crea una fila
     * nueva, mismo mecanismo de idempotencia que `registrarCondiciones()` —
     * el `UNIQUE` parcial de `uuid_cliente` resuelve el reintento, nunca un
     * `SELECT` previo.
     *
     * Evidencia obligatoria ("con foto" en el título de la HU): se busca por
     * el `uuid_cliente` que trae `RegistroIncidencia::$evidenciaFotoUuidCliente`;
     * si no existe o no es de tipo `foto_incidencia`, se rechaza — mismo
     * patrón que la imagen de campo de `cerrarTrabajo()`. También se rechaza
     * si esa MISMA evidencia ya respalda otra incidencia (mismo criterio que
     * la imagen de campo): el chequeo excluye el propio `uuid_cliente` del
     * registro entrante para que un REINTENTO del mismo evento (que
     * referencia su propia fila ya creada) no se confunda con una foto
     * ajena — ese caso se resuelve más abajo como `duplicado`, vía la
     * violación del `UNIQUE` de `uuid_cliente`. El chequeo acá da un mensaje
     * de rechazo claro; el índice único parcial
     * `ope_incidencias_evidencia_foto_id_unico` es la garantía real contra la
     * condición de carrera de dos incidencias DISTINTAS registrándose a la
     * vez con la misma evidencia.
     *
     * `ope_incidencias` es la primera tabla de este módulo con DOS índices
     * únicos parciales que un mismo `INSERT` puede violar a la vez
     * (`uuid_cliente` y `evidencia_foto_id`): un reintento EXACTO del mismo
     * evento repite ambos valores, y el motor de base no garantiza cuál de
     * los dos constraints reporta primero (hallazgo empírico: SQLite, el
     * motor de los tests, reporta acá el de `evidencia_foto_id` primero). Por
     * eso el catch usa {@see resultadoIncidenciaDesdeExcepcion()} en vez del
     * genérico {@see resultadoDesdeExcepcion()}.
     */
    public function registrarIncidencia(RegistroIncidencia $datos): ResultadoSincronizacion
    {
        $sesion = Sesion::query()->where('uuid_cliente', $datos->sesionUuidCliente)->first();

        if ($sesion === null) {
            return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.sesion_no_existe_aun'));
        }

        $evidenciaFoto = Evidencia::query()->where('uuid_cliente', $datos->evidenciaFotoUuidCliente)->first();

        if ($evidenciaFoto === null || $evidenciaFoto->tipo !== TipoEvidencia::FotoIncidencia) {
            return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.falta_foto_incidencia'));
        }

        $yaUsadaPorOtraIncidencia = Incidencia::query()
            ->where('evidencia_foto_id', $evidenciaFoto->id)
            ->where('uuid_cliente', '!=', $datos->uuidCliente)
            ->exists();

        if ($yaUsadaPorOtraIncidencia) {
            return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.foto_incidencia_reutilizada'));
        }

        try {
            DB::transaction(function () use ($datos, $sesion, $evidenciaFoto): void {
                Incidencia::query()->create([
                    'uuid_cliente' => $datos->uuidCliente,
                    'sesion_id' => $sesion->id,
                    'tipo' => $datos->tipo,
                    'descripcion' => $datos->descripcion,
                    'hora' => $datos->hora,
                    'evidencia_foto_id' => $evidenciaFoto->id,
                ]);
            });
        } catch (QueryException $excepcion) {
            return $this->resultadoIncidenciaDesdeExcepcion($excepcion, $datos->uuidCliente);
        }

        return ResultadoSincronizacion::aplicado();
    }

    /**
     * Recepción de caldo (espec §7.2, HU-10 redefinida por CR-01, tarea 18):
     * crea una fila nueva, mismo mecanismo de idempotencia que
     * `abrirTrabajo()`/`registrarCondiciones()` — el `UNIQUE` parcial de
     * `uuid_cliente` resuelve el reintento, nunca un `SELECT` previo. El
     * trabajo se resuelve por `uuid_cliente` (puede haber llegado en el mismo
     * lote: `SincronizarLote::ORDEN_CAUSAL` aplica siempre `trabajo` antes
     * que `recepcion_caldo`).
     */
    public function registrarRecepcionCaldo(RegistroRecepcionCaldo $datos): ResultadoSincronizacion
    {
        $trabajoId = Trabajo::query()->where('uuid_cliente', $datos->trabajoUuidCliente)->value('id');

        if ($trabajoId === null) {
            return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.trabajo_no_existe_aun'));
        }

        try {
            DB::transaction(function () use ($datos, $trabajoId): void {
                RecepcionCaldo::query()->create([
                    'uuid_cliente' => $datos->uuidCliente,
                    'trabajo_id' => $trabajoId,
                    'litros' => $datos->litros,
                    'entregado_por' => $datos->entregadoPor,
                    'hora' => $datos->hora,
                ]);
            });
        } catch (QueryException $excepcion) {
            return $this->resultadoDesdeExcepcion($excepcion, 'ope_recepciones_caldo');
        }

        return ResultadoSincronizacion::aplicado();
    }

    /**
     * Recarga del dron (HU-13, tarea 23): crea una fila nueva, mismo
     * mecanismo de idempotencia que `registrarCondiciones()`/
     * `registrarRecepcionCaldo()` — el `UNIQUE` parcial de `uuid_cliente`
     * resuelve el reintento, nunca un `SELECT` previo. La sesión se resuelve
     * por `uuid_cliente` (puede haber llegado en el mismo lote:
     * `SincronizarLote::ORDEN_CAUSAL` aplica siempre `sesion` antes que
     * `recarga`).
     *
     * `alerta_temperatura` se calcula una sola vez, al momento del hecho
     * (`RegistroRecarga::alertaTemperatura()`) — nunca rechaza el registro,
     * solo lo marca (CA de HU-13: "alerta", no "bloqueo").
     *
     * Dispara `RecargaRegistrada` (HU-87, tarea 102) DENTRO de la misma
     * transacción, después del `create()`: un reintento con el mismo
     * `uuid_cliente` nunca llega a esta línea porque la `QueryException` del
     * `UNIQUE` corta la transacción antes — así el oyente de `Mantenimiento`
     * (`IncrementarCiclosBateria`, el "odómetro" de la batería) nunca cuenta
     * dos veces la misma recarga.
     */
    public function registrarRecarga(RegistroRecarga $datos): ResultadoSincronizacion
    {
        $sesion = Sesion::query()->where('uuid_cliente', $datos->sesionUuidCliente)->first();

        if ($sesion === null) {
            return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.sesion_no_existe_aun'));
        }

        try {
            DB::transaction(function () use ($datos, $sesion): void {
                $recarga = Recarga::query()->create([
                    'uuid_cliente' => $datos->uuidCliente,
                    'sesion_id' => $sesion->id,
                    'secuencia' => $datos->secuencia,
                    'litros_caldo' => $datos->litrosCaldo,
                    'bateria_saliente_id' => $datos->bateriaSalienteId,
                    'temperatura_bateria_c' => $datos->temperaturaBateriaC,
                    'alerta_temperatura' => $datos->alertaTemperatura(),
                    'motivo_retraso_caldo' => $datos->motivoRetrasoCaldo,
                    'hora_retraso' => $datos->horaRetraso,
                    'litros_combustible_generador' => $datos->litrosCombustibleGenerador,
                    'hora' => $datos->hora,
                ]);

                // HU-19 (tarea 26): "batería caliente" y, si corresponde,
                // "dron sospechoso" — nunca bloquean el registro, solo
                // alimentan la bandeja.
                if ($recarga->alerta_temperatura) {
                    $this->alertas->porBateriaCaliente($recarga, $sesion);
                }

                event(new RecargaRegistrada($datos->bateriaSalienteId));
            });
        } catch (QueryException $excepcion) {
            return $this->resultadoDesdeExcepcion($excepcion, 'ope_recargas');
        }

        return ResultadoSincronizacion::aplicado();
    }

    /**
     * Mismo criterio que `AsignarRolesUsuario::relanzarComoDuplicado()`: el
     * formato del mensaje de la violación difiere por driver (Postgres nombra
     * el índice; SQLite, el motor de los tests, nombra tabla.columna) — se
     * comprueban ambos formatos.
     */
    private function resultadoDesdeExcepcion(QueryException $excepcion, string $tabla): ResultadoSincronizacion
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, "{$tabla}_uuid_cliente_unico") || str_contains($mensaje, "{$tabla}.uuid_cliente")) {
            return ResultadoSincronizacion::duplicado();
        }

        return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.registro_invalido'));
    }

    /**
     * Variante de {@see resultadoDesdeExcepcion()} para `ope_incidencias`
     * (HU-08, tarea 22): esa tabla tiene DOS índices únicos parciales
     * (`uuid_cliente` y `evidencia_foto_id`) que un mismo `INSERT` puede
     * violar simultáneamente en un reintento EXACTO —el registro repite
     * ambos valores— y el motor de base no garantiza cuál de los dos se
     * reporta en el mensaje de la excepción (hallazgo empírico: SQLite
     * reporta acá `evidencia_foto_id` primero, no `uuid_cliente`). Si el
     * mensaje no matchea el patrón de `uuid_cliente` pero YA EXISTE una fila
     * con ese mismo `uuid_cliente` (imposible salvo por ese reintento: nadie
     * más pudo haberla creado con ese valor), es igual `duplicado` — el
     * `SELECT` acá es diagnóstico DESPUÉS de una violación real de la base,
     * no el mecanismo primario de detectarla.
     */
    private function resultadoIncidenciaDesdeExcepcion(QueryException $excepcion, string $uuidCliente): ResultadoSincronizacion
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'ope_incidencias_uuid_cliente_unico') || str_contains($mensaje, 'ope_incidencias.uuid_cliente')) {
            return ResultadoSincronizacion::duplicado();
        }

        if (Incidencia::query()->where('uuid_cliente', $uuidCliente)->exists()) {
            return ResultadoSincronizacion::duplicado();
        }

        return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.registro_invalido'));
    }

    /**
     * Cierre de trabajo (HU-05, tarea 13): MUTA una fila existente en vez de
     * crear una nueva, así que la idempotencia no se apoya en el `UNIQUE` de
     * `abrirTrabajo()` (ese protege la fila de APERTURA, no el evento de
     * cierre). Mecanismo, documentado en runs/13.md: se busca el trabajo por
     * `uuid_cliente`, se bloquea la fila (`lockForUpdate`, mismo patrón que
     * `MaquinaEstadosVersionApk::autorizar()`) y:
     *
     *   - si ya está `cerrado` con el MISMO `cierre_uuid_cliente` del evento
     *     entrante → `duplicado` (reintento exacto, no se toca nada);
     *   - si ya está `cerrado` con OTRO `cierre_uuid_cliente` (o cerrado por
     *     otra vía) → `rechazado` (la máquina de estados no permite
     *     `cerrado → cerrado`, invariante 7);
     *   - si está `abierto` → se aplica el cierre.
     *
     * El lock evita la condición de carrera de decidir "duplicado vs. aplicar"
     * fuera de una transacción: dos reintentos concurrentes del mismo evento
     * verían ambos `abierto` y ambos aplicarían el cierre por su cuenta.
     *
     * Pertenencia (tarea 12, mismo criterio extendido al cierre): un trabajo
     * no tiene piloto propio (espec §4.3), así que "es del operario" se
     * resuelve igual que en la apertura de trabajo — por participación, no
     * por dueño. Si el trabajo ya tiene sesiones, el operario debe tener al
     * menos una propia; si todavía no tiene ninguna, cualquier operario
     * legítimo puede cerrarlo (mismo criterio que abrir: "un lote puede
     * tener varios pilotos y drones").
     *
     * Evidencia obligatoria (espec §9/§10, HU-09, tarea 21): "sin captura no
     * cierra" — se busca la evidencia por el `uuid_cliente` que trae
     * `CierreTrabajo::$evidenciaImagenCampoUuidCliente`; si no existe o no es
     * de tipo `imagen_campo`, el cierre se rechaza sin frenar el resto del
     * lote (mismo patrón `rechazado` que el resto de este método) — nunca un
     * 422 del lote completo. La validación va DESPUÉS de resolver
     * "duplicado"/pertenencia: un reintento del mismo evento de cierre ya
     * aplicado no vuelve a evaluarla.
     *
     * También se rechaza si esa MISMA evidencia ya cerró otro trabajo
     * (hallazgo de la revisión crítica): sin esto, una sola foto podría
     * "demostrar" dos lotes distintos, contra el propósito literal de la HU.
     * El chequeo acá da un mensaje de rechazo claro; el índice único parcial
     * `ope_trabajos_imagen_campo_evidencia_id_unico` (migración 100015) es la
     * garantía real contra la condición de carrera de dos trabajos DISTINTOS
     * cerrándose a la vez con la misma evidencia — el `lockForUpdate()` de
     * este método solo serializa cierres del MISMO trabajo.
     */
    public function cerrarTrabajo(CierreTrabajo $datos, ?int $operarioPersonaId): ResultadoSincronizacion
    {
        $trabajoId = Trabajo::query()->where('uuid_cliente', $datos->trabajoUuidCliente)->value('id');

        if ($trabajoId === null) {
            return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.trabajo_no_existe'));
        }

        try {
            return DB::transaction(function () use ($trabajoId, $datos, $operarioPersonaId): ResultadoSincronizacion {
                /** @var Trabajo $trabajo */
                $trabajo = Trabajo::query()->whereKey($trabajoId)->lockForUpdate()->firstOrFail();

                if ($trabajo->estado === EstadoTrabajo::Cerrado) {
                    return $trabajo->cierre_uuid_cliente === $datos->uuidCliente
                        ? ResultadoSincronizacion::duplicado()
                        : ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.trabajo_ya_cerrado'));
                }

                if ($operarioPersonaId !== null && ! $this->operarioPuedeCerrarTrabajo($trabajo->id, $operarioPersonaId)) {
                    return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.operario_no_participo'));
                }

                $evidenciaImagenCampo = Evidencia::query()
                    ->where('uuid_cliente', $datos->evidenciaImagenCampoUuidCliente)
                    ->first();

                if ($evidenciaImagenCampo === null || $evidenciaImagenCampo->tipo !== TipoEvidencia::ImagenCampo) {
                    return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.falta_imagen_campo'));
                }

                $yaUsadaPorOtroTrabajo = Trabajo::query()
                    ->where('imagen_campo_evidencia_id', $evidenciaImagenCampo->id)
                    ->whereKeyNot($trabajo->id)
                    ->exists();

                if ($yaUsadaPorOtroTrabajo) {
                    return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.imagen_campo_reutilizada'));
                }

                $trabajo->imagen_campo_evidencia_id = $evidenciaImagenCampo->id;

                // `hectareas_declaradas` del trabajo es un campo DERIVADO
                // (espec §4.3: "suma de sesiones"), no un dato que traiga
                // `CierreTrabajo` (ver docblock de ese DTO). Se recalcula acá
                // también, no solo en `cerrarSesion()`, por si el trabajo se
                // cierra antes de que se hayan cerrado todas sus sesiones.
                $trabajo->hectareas_declaradas = $this->sumaHectareasSesiones($trabajo->id);

                // `litros_sobrante` (espec §7.2, tarea 18): a diferencia de
                // las hectáreas, NO es derivado — se asigna directo antes de
                // `cerrar()`, mismo patrón, para que quede en el mismo
                // `save()` de la transición. `null` si el registro no lo trae
                // (campo opcional, ver `Contratos/CierreTrabajo`).
                $trabajo->litros_sobrante = $datos->litrosSobrante;

                $this->maquinaTrabajo->cerrar($trabajo, $datos->uuidCliente, $datos->fin);

                return ResultadoSincronizacion::aplicado();
            });
        } catch (QueryException) {
            return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.trabajo_cierre_invalido'));
        } catch (TransicionTrabajoNoPermitida) {
            return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.trabajo_ya_cerrado'));
        }
    }

    /**
     * Cierre de sesión (HU-05, tarea 13): mismo mecanismo de idempotencia que
     * `cerrarTrabajo()` (lock + comparar `cierre_uuid_cliente` antes de
     * decidir). `motivo_cierre` ya viene validado contra el catálogo por
     * `CierreSesion::intentarDesdeArreglo()`.
     *
     * Pertenencia (tarea 12, mismo criterio que `abrirSesion()`): la sesión
     * SÍ tiene `piloto_id` propio, así que se compara directo contra el
     * operario del token — a diferencia de `abrirSesion()`, donde
     * `SincronizarLote` compara antes de invocar el contrato porque el DTO de
     * apertura ya trae `piloto_id`, acá el cierre no lo trae (solo referencia
     * la sesión por `uuid_cliente`): resolverlo exige leer la fila
     * persistida, así que la verificación vive en esta implementación, no en
     * `SincronizarLote`.
     *
     * Tras aplicar el cierre, recalcula `trabajo.hectareas_declaradas`
     * (hallazgo del veredicto de la tarea 13): la espec (§4.3) lo define como
     * "suma de sesiones", así que cada cierre de sesión — la única operación
     * que muta `sesion.hectareas_declaradas` con un valor real — deja
     * también al trabajo consistente con el nuevo total.
     */
    public function cerrarSesion(CierreSesion $datos, ?int $operarioPersonaId): ResultadoSincronizacion
    {
        $sesionId = Sesion::query()->where('uuid_cliente', $datos->sesionUuidCliente)->value('id');

        if ($sesionId === null) {
            return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.sesion_no_existe'));
        }

        try {
            return DB::transaction(function () use ($sesionId, $datos, $operarioPersonaId): ResultadoSincronizacion {
                /** @var Sesion $sesion */
                $sesion = Sesion::query()->whereKey($sesionId)->lockForUpdate()->firstOrFail();

                if ($sesion->estado === EstadoSesion::Cerrado) {
                    return $sesion->cierre_uuid_cliente === $datos->uuidCliente
                        ? ResultadoSincronizacion::duplicado()
                        : ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.sesion_ya_cerrada'));
                }

                if ($operarioPersonaId !== null && (int) $sesion->piloto_id !== $operarioPersonaId) {
                    return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.piloto_no_corresponde'));
                }

                // `litros_consumidos` (espec §7.2, tarea 18): igual que
                // `litros_sobrante` en `cerrarTrabajo()`, se asigna directo
                // antes de `cerrar()` para que quede en el mismo `save()` de
                // la transición. `null` si el registro no lo trae (campo
                // opcional, ver `Contratos/CierreSesion`).
                $sesion->litros_consumidos = $datos->litrosConsumidos;

                $this->maquinaSesion->cerrar($sesion, $datos->uuidCliente, $datos->fin, $datos->motivoCierre, $datos->hectareasDeclaradas);

                $this->recalcularHectareasTrabajo((int) $sesion->trabajo_id);

                return ResultadoSincronizacion::aplicado();
            });
        } catch (QueryException) {
            return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.sesion_cierre_invalido'));
        } catch (TransicionSesionNoPermitida) {
            return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.sesion_ya_cerrada'));
        }
    }

    private function operarioPuedeCerrarTrabajo(int $trabajoId, int $personaId): bool
    {
        $tieneSesiones = Sesion::query()->where('trabajo_id', $trabajoId)->exists();

        if (! $tieneSesiones) {
            return true;
        }

        return Sesion::query()->where('trabajo_id', $trabajoId)->where('piloto_id', $personaId)->exists();
    }

    /**
     * `trabajo.hectareas_declaradas` (espec §4.3: "suma de sesiones") tras el
     * cierre de una de sus sesiones. Bloquea la fila del trabajo
     * (`lockForUpdate`, mismo criterio que `cerrarTrabajo()`/`cerrarSesion()`)
     * para que dos cierres de sesión concurrentes del mismo trabajo no
     * pisen la suma del otro con una lectura desactualizada.
     */
    private function recalcularHectareasTrabajo(int $trabajoId): void
    {
        /** @var Trabajo $trabajo */
        $trabajo = Trabajo::query()->whereKey($trabajoId)->lockForUpdate()->firstOrFail();
        $trabajo->hectareas_declaradas = $this->sumaHectareasSesiones($trabajoId);
        $trabajo->save();

        // HU-19 (tarea 26): "suma excedida" — único punto donde la suma de
        // hectáreas de las sesiones de un trabajo puede cambiar, así que es
        // el único punto donde vale la pena recalcular la cobertura (ver
        // docblock de `GenerarAlertaExcepcion::porSumaExcedidaSiCorresponde()`).
        $this->alertas->porSumaExcedidaSiCorresponde($trabajo);
    }

    private function sumaHectareasSesiones(int $trabajoId): string
    {
        return (string) Sesion::query()->where('trabajo_id', $trabajoId)->sum('hectareas_declaradas');
    }

    /**
     * Estadía del equipo en una hacienda (HU-51, tarea 74): crea una fila
     * nueva, mismo mecanismo de idempotencia que `abrirTrabajo()` — el
     * `UNIQUE` parcial de `uuid_cliente` resuelve el reintento, nunca un
     * `SELECT` previo. Un segundo `UNIQUE` parcial (`equipo_trabajo_id` con
     * `salida IS NULL`) rechaza un `estadia_entrada` si el equipo ya tiene
     * una estadía abierta — sin verificarlo antes, mismo criterio que el
     * resto del contrato.
     *
     * `tipo_alojamiento` (19/9/2026): se persiste tal cual llega del DTO —
     * `null` si la app de campo no lo trae todavía, ya validado contra el
     * catálogo cerrado por `AperturaEstadiaHacienda::intentarDesdeArreglo()`
     * (un valor desconocido nunca llega hasta acá, el registro se rechazó
     * antes). Nada más del motor de sync cambia con este campo.
     */
    public function abrirEstadia(AperturaEstadiaHacienda $datos): ResultadoSincronizacion
    {
        try {
            DB::transaction(function () use ($datos): void {
                EstadiaHacienda::query()->create([
                    'uuid_cliente' => $datos->uuidCliente,
                    'equipo_trabajo_id' => $datos->equipoTrabajoId,
                    'propiedad_id' => $datos->propiedadId,
                    'entrada' => self::normalizarUtc($datos->entrada),
                    'vehiculo_id' => $datos->vehiculoId,
                    'tipo_alojamiento' => $datos->tipoAlojamiento,
                    'observacion' => $datos->observacion,
                ]);
            });
        } catch (QueryException $excepcion) {
            return $this->resultadoAperturaEstadiaDesdeExcepcion($excepcion, $datos->uuidCliente);
        }

        return ResultadoSincronizacion::aplicado();
    }

    /**
     * Cierre de estadía (HU-51, tarea 74): MUTA una fila existente, mismo
     * mecanismo de idempotencia que `cerrarTrabajo()`/`cerrarSesion()` (lock +
     * comparar `cierre_uuid_cliente` antes de decidir). `entrada`/`salida`
     * inválidas (salida anterior o igual a la entrada) se rechazan acá, antes
     * del `save()` — el `CHECK` de la migración es solo una red de seguridad
     * adicional para pgsql, no el mecanismo primario (no existe en SQLite,
     * el motor de los tests).
     */
    public function cerrarEstadia(CierreEstadiaHacienda $datos): ResultadoSincronizacion
    {
        $estadiaId = EstadiaHacienda::query()->where('uuid_cliente', $datos->estadiaUuidCliente)->value('id');

        if ($estadiaId === null) {
            return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.estadia_no_existe'));
        }

        try {
            return DB::transaction(function () use ($estadiaId, $datos): ResultadoSincronizacion {
                /** @var EstadiaHacienda $estadia */
                $estadia = EstadiaHacienda::query()->whereKey($estadiaId)->lockForUpdate()->firstOrFail();

                if ($estadia->salida !== null) {
                    return $estadia->cierre_uuid_cliente === $datos->uuidCliente
                        ? ResultadoSincronizacion::duplicado()
                        : ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.estadia_ya_cerrada'));
                }

                $salida = self::normalizarUtc($datos->salida);

                if ($salida->lessThanOrEqualTo($estadia->entrada)) {
                    return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.salida_anterior_a_entrada'));
                }

                $estadia->salida = $salida;
                $estadia->cierre_uuid_cliente = $datos->uuidCliente;
                $estadia->save();

                return ResultadoSincronizacion::aplicado();
            });
        } catch (QueryException) {
            return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.estadia_cierre_invalido'));
        }
    }

    /**
     * `CarbonImmutable::parse()` conserva el offset original del string
     * entrante (p. ej. `-04:00`) como huso horario del objeto, no lo
     * normaliza — y `ope_estadias_hacienda.entrada`/`salida` son `dateTime`
     * sin tz. Sin este `->utc()`, `format()` escribiría la hora LOCAL literal
     * y una relectura posterior la interpretaría como UTC, corriendo el
     * instante real por el valor del offset. Mismo mecanismo (y mismo fix)
     * que `MaquinaEstadosTrabajo::normalizarUtc()`/`MaquinaEstadosActa::firmar()`
     * (runs/24.md) — acá vive en esta clase y no en una máquina de estados
     * porque `ope_estadias_hacienda` no tiene una (ver docblock de la
     * migración).
     */
    private static function normalizarUtc(string $valor): CarbonImmutable
    {
        return CarbonImmutable::parse($valor)->utc();
    }

    /**
     * Variante de {@see resultadoDesdeExcepcion()} para `ope_estadias_hacienda`
     * (HU-51, tarea 74): esa tabla también tiene DOS índices únicos parciales
     * que un mismo `INSERT` puede violar a la vez —un reintento EXACTO del
     * mismo evento de entrada repite `uuid_cliente` Y dispara el de
     * `equipo_trabajo_id` (la fila original, si no se cerró, sigue con
     * `salida IS NULL`)— mismo criterio que
     * {@see resultadoIncidenciaDesdeExcepcion()}: si el mensaje no matchea el
     * patrón de `uuid_cliente` pero YA EXISTE una fila con ese valor, es igual
     * `duplicado`.
     */
    private function resultadoAperturaEstadiaDesdeExcepcion(QueryException $excepcion, string $uuidCliente): ResultadoSincronizacion
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'ope_estadias_hacienda_uuid_cliente_unico') || str_contains($mensaje, 'ope_estadias_hacienda.uuid_cliente')) {
            return ResultadoSincronizacion::duplicado();
        }

        if (EstadiaHacienda::query()->where('uuid_cliente', $uuidCliente)->exists()) {
            return ResultadoSincronizacion::duplicado();
        }

        if (str_contains($mensaje, 'ope_estadias_hacienda_equipo_abierta_unico') || str_contains($mensaje, 'ope_estadias_hacienda.equipo_trabajo_id')) {
            return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.equipo_con_estadia_abierta'));
        }

        return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.registro_invalido'));
    }

    /**
     * "Reporte de Equipos" (HU-80, tarea 86): crea una fila nueva, mismo
     * mecanismo de idempotencia que `registrarRecepcionCaldo()` — el
     * `UNIQUE` parcial de `uuid_cliente` resuelve el reintento, nunca un
     * `SELECT` previo. Las tres fotos (control, ciclo de batería y
     * balanceo, dron limpio) son OBLIGATORIAS, mismo criterio que
     * `registrarIncidencia()` con su evidencia: se rechaza el registro
     * completo si falta cualquiera, antes de intentar el `INSERT`.
     */
    public function registrarEvidenciaEquipo(RegistroEvidenciaEquipo $datos): ResultadoSincronizacion
    {
        $trabajoId = Trabajo::query()->where('uuid_cliente', $datos->trabajoUuidCliente)->value('id');

        if ($trabajoId === null) {
            return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.trabajo_no_existe_aun'));
        }

        $fotoControl = $this->evidenciaFotoEquipo($datos->fotoControlUuidCliente, TipoEvidencia::FotoControl);

        if ($fotoControl === null) {
            return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.falta_foto_control'));
        }

        $fotoCicloBateria = $this->evidenciaFotoEquipo($datos->fotoCicloBateriaBalanceoUuidCliente, TipoEvidencia::FotoCicloBateriaBalanceo);

        if ($fotoCicloBateria === null) {
            return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.falta_foto_ciclo_bateria'));
        }

        $fotoDronLimpio = $this->evidenciaFotoEquipo($datos->fotoDronLimpioUuidCliente, TipoEvidencia::FotoDronLimpio);

        if ($fotoDronLimpio === null) {
            return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.falta_foto_dron_limpio'));
        }

        // `!= $datos->uuidCliente`, mismo criterio que
        // `registrarIncidencia()`: un reintento EXACTO del mismo evento
        // repite las mismas tres fotos además del mismo `uuid_cliente` — sin
        // excluirlo acá, ese reintento se rechazaría como "foto reutilizada"
        // en vez de resolverse como `duplicado` en el `INSERT` de abajo.
        if (EvidenciaEquipo::query()->where('foto_control_id', $fotoControl->id)->where('uuid_cliente', '!=', $datos->uuidCliente)->exists()) {
            return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.foto_control_reutilizada'));
        }

        if (EvidenciaEquipo::query()->where('foto_ciclo_bateria_balanceo_id', $fotoCicloBateria->id)->where('uuid_cliente', '!=', $datos->uuidCliente)->exists()) {
            return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.foto_ciclo_bateria_reutilizada'));
        }

        if (EvidenciaEquipo::query()->where('foto_dron_limpio_id', $fotoDronLimpio->id)->where('uuid_cliente', '!=', $datos->uuidCliente)->exists()) {
            return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.foto_dron_limpio_reutilizada'));
        }

        try {
            DB::transaction(function () use ($datos, $trabajoId, $fotoControl, $fotoCicloBateria, $fotoDronLimpio): void {
                EvidenciaEquipo::query()->create([
                    'uuid_cliente' => $datos->uuidCliente,
                    'trabajo_id' => $trabajoId,
                    'horas_vuelo_dron' => $datos->horasVueloDron,
                    'foto_control_id' => $fotoControl->id,
                    'foto_ciclo_bateria_balanceo_id' => $fotoCicloBateria->id,
                    'foto_dron_limpio_id' => $fotoDronLimpio->id,
                ]);
            });
        } catch (QueryException $excepcion) {
            return $this->resultadoEvidenciaEquipoDesdeExcepcion($excepcion, $datos->uuidCliente);
        }

        return ResultadoSincronizacion::aplicado();
    }

    private function evidenciaFotoEquipo(string $uuidCliente, TipoEvidencia $tipo): ?Evidencia
    {
        $evidencia = Evidencia::query()->where('uuid_cliente', $uuidCliente)->first();

        return $evidencia !== null && $evidencia->tipo === $tipo ? $evidencia : null;
    }

    /**
     * Mismo criterio que {@see resultadoAperturaEstadiaDesdeExcepcion()}:
     * `ope_evidencias_equipo` tiene CUATRO índices únicos parciales
     * (`uuid_cliente` y los tres `foto_*_id`) que un mismo `INSERT` puede
     * violar A LA VEZ — un reintento EXACTO del mismo evento repite las
     * cuatro columnas de golpe, y el driver solo reporta UNA violación (no
     * necesariamente la de `uuid_cliente` primero: SQLite, motor de los
     * tests, reportó en la práctica la del último índice creado). Por eso se
     * pregunta primero si YA EXISTE una fila con ese `uuid_cliente` —si la
     * hay, es `duplicado` sin importar qué haya dicho el mensaje— y solo si
     * no la hay se interpreta el mensaje como una foto reutilizada por OTRO
     * registro (el chequeo de `registrarEvidenciaEquipo()` ya excluye ese
     * caso antes del `INSERT`; esto solo cubre la carrera entre ese chequeo y
     * el `INSERT` mismo).
     */
    private function resultadoEvidenciaEquipoDesdeExcepcion(QueryException $excepcion, string $uuidCliente): ResultadoSincronizacion
    {
        if (EvidenciaEquipo::query()->where('uuid_cliente', $uuidCliente)->exists()) {
            return ResultadoSincronizacion::duplicado();
        }

        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'ope_evidencias_equipo_foto_control_unico') || str_contains($mensaje, 'ope_evidencias_equipo.foto_control_id')) {
            return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.foto_control_reutilizada'));
        }

        if (str_contains($mensaje, 'ope_evidencias_equipo_foto_ciclo_bateria_unico') || str_contains($mensaje, 'ope_evidencias_equipo.foto_ciclo_bateria_balanceo_id')) {
            return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.foto_ciclo_bateria_reutilizada'));
        }

        if (str_contains($mensaje, 'ope_evidencias_equipo_foto_dron_limpio_unico') || str_contains($mensaje, 'ope_evidencias_equipo.foto_dron_limpio_id')) {
            return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.foto_dron_limpio_reutilizada'));
        }

        return ResultadoSincronizacion::rechazado(Texto::de('operaciones.sync.registro_invalido'));
    }
}
