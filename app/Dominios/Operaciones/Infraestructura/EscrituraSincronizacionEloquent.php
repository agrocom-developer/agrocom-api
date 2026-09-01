<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosSesion;
use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosTrabajo;
use App\Dominios\Operaciones\Contratos\AperturaSesion;
use App\Dominios\Operaciones\Contratos\AperturaTrabajo;
use App\Dominios\Operaciones\Contratos\CierreSesion;
use App\Dominios\Operaciones\Contratos\CierreTrabajo;
use App\Dominios\Operaciones\Contratos\EscrituraSincronizacion;
use App\Dominios\Operaciones\Contratos\RegistroCondiciones;
use App\Dominios\Operaciones\Contratos\ResultadoSincronizacion;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\EstadoSesion;
use App\Dominios\Operaciones\Dominio\EstadoTrabajo;
use App\Dominios\Operaciones\Dominio\Excepciones\TransicionSesionNoPermitida;
use App\Dominios\Operaciones\Dominio\Excepciones\TransicionTrabajoNoPermitida;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Condiciones;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
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
    ) {}

    /**
     * Antes de aplicar (tarea 12, hallazgo 2), verifica que `orden_id` y
     * `lote_id` formen un par legítimo: la orden debe existir, estar
     * `Vigente`, y su `lote_id` debe coincidir con el declarado. La espec
     * (§4.3: "el trabajo no lleva piloto propio — un lote puede tener varios
     * pilotos y drones por relevo, falla o logística") descarta que exista
     * una noción de "lote del operario"; lo único verificable con lo que hay
     * en el repo es que el par orden/lote sea consistente con el catálogo
     * vigente que cualquier operario legítimo puede operar — un `lote_id`
     * que no es el de esa orden (o una orden ya consumida/vencida/emitida
     * sin vigencia) no es algo que el operario pueda tocar, aunque ambos ids
     * existan físicamente y la FK los acepte.
     */
    public function abrirTrabajo(AperturaTrabajo $datos): ResultadoSincronizacion
    {
        $orden = OrdenAplicacion::query()->find($datos->ordenId);

        if ($orden === null || $orden->estado !== EstadoOrdenAplicacion::Vigente || (int) $orden->lote_id !== $datos->loteId) {
            return ResultadoSincronizacion::rechazado('la orden y el lote declarados no forman un par vigente');
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
            return ResultadoSincronizacion::rechazado('el trabajo referenciado no existe todavía');
        }

        try {
            DB::transaction(function () use ($datos, $trabajoId): void {
                $this->maquinaSesion->abrir([
                    'uuid_cliente' => $datos->uuidCliente,
                    'trabajo_id' => $trabajoId,
                    'secuencia' => $datos->secuencia,
                    'piloto_id' => $datos->pilotoId,
                    'auxiliar_id' => $datos->auxiliarId,
                    'hectareas_declaradas' => $datos->hectareasDeclaradas,
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
     * Condiciones de vuelo (HU-06, tarea 17): mismo mecanismo de
     * idempotencia que `abrirTrabajo()`/`abrirSesion()` — el `UNIQUE` parcial
     * de `uuid_cliente` resuelve `duplicado`, nunca un `SELECT` previo.
     *
     * `trabajo_id` se resuelve leyendo la sesión (denormalizado, ver
     * docblock de `RegistroCondiciones` y de la migración): no hace falta un
     * segundo `uuid_cliente` de trabajo en el payload.
     *
     * La decisión de rechazar un registro fuera de rango sin observación
     * firmada (espec §5) es el próximo commit — acá solo se persiste
     * `autorizado` según {@see RegistroCondiciones::dentroDeRango()}.
     */
    public function registrarCondiciones(RegistroCondiciones $datos): ResultadoSincronizacion
    {
        $sesion = Sesion::query()->where('uuid_cliente', $datos->sesionUuidCliente)->first();

        if ($sesion === null) {
            return ResultadoSincronizacion::rechazado('la sesión referenciada no existe todavía');
        }

        try {
            DB::transaction(function () use ($datos, $sesion): void {
                Condiciones::query()->create([
                    'uuid_cliente' => $datos->uuidCliente,
                    'trabajo_id' => $sesion->trabajo_id,
                    'sesion_id' => $sesion->id,
                    'momento' => $datos->momento,
                    'viento_kmh' => $datos->vientoKmh,
                    'temperatura_c' => $datos->temperaturaC,
                    'humedad_pct' => $datos->humedadPct,
                    'autorizado' => $datos->dentroDeRango(),
                    'observacion_agronomo' => $datos->observacionAgronomo,
                    'firma_observacion' => $datos->firmaObservacion,
                ]);
            });
        } catch (QueryException $excepcion) {
            return $this->resultadoDesdeExcepcion($excepcion, 'ope_condiciones');
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

        return ResultadoSincronizacion::rechazado('no se pudo aplicar el registro: referencia o dato inválido');
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
     */
    public function cerrarTrabajo(CierreTrabajo $datos, ?int $operarioPersonaId): ResultadoSincronizacion
    {
        $trabajoId = Trabajo::query()->where('uuid_cliente', $datos->trabajoUuidCliente)->value('id');

        if ($trabajoId === null) {
            return ResultadoSincronizacion::rechazado('el trabajo referenciado no existe');
        }

        try {
            return DB::transaction(function () use ($trabajoId, $datos, $operarioPersonaId): ResultadoSincronizacion {
                /** @var Trabajo $trabajo */
                $trabajo = Trabajo::query()->whereKey($trabajoId)->lockForUpdate()->firstOrFail();

                if ($trabajo->estado === EstadoTrabajo::Cerrado) {
                    return $trabajo->cierre_uuid_cliente === $datos->uuidCliente
                        ? ResultadoSincronizacion::duplicado()
                        : ResultadoSincronizacion::rechazado('el trabajo ya está cerrado');
                }

                if ($operarioPersonaId !== null && ! $this->operarioPuedeCerrarTrabajo($trabajo->id, $operarioPersonaId)) {
                    return ResultadoSincronizacion::rechazado('el operario no participó en este trabajo');
                }

                // `hectareas_declaradas` del trabajo es un campo DERIVADO
                // (espec §4.3: "suma de sesiones"), no un dato que traiga
                // `CierreTrabajo` (ver docblock de ese DTO). Se recalcula acá
                // también, no solo en `cerrarSesion()`, por si el trabajo se
                // cierra antes de que se hayan cerrado todas sus sesiones.
                $trabajo->hectareas_declaradas = $this->sumaHectareasSesiones($trabajo->id);

                $this->maquinaTrabajo->cerrar($trabajo, $datos->uuidCliente, $datos->fin);

                return ResultadoSincronizacion::aplicado();
            });
        } catch (QueryException) {
            return ResultadoSincronizacion::rechazado('no se pudo cerrar el trabajo: referencia o dato inválido');
        } catch (TransicionTrabajoNoPermitida) {
            return ResultadoSincronizacion::rechazado('el trabajo ya está cerrado');
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
            return ResultadoSincronizacion::rechazado('la sesión referenciada no existe');
        }

        try {
            return DB::transaction(function () use ($sesionId, $datos, $operarioPersonaId): ResultadoSincronizacion {
                /** @var Sesion $sesion */
                $sesion = Sesion::query()->whereKey($sesionId)->lockForUpdate()->firstOrFail();

                if ($sesion->estado === EstadoSesion::Cerrado) {
                    return $sesion->cierre_uuid_cliente === $datos->uuidCliente
                        ? ResultadoSincronizacion::duplicado()
                        : ResultadoSincronizacion::rechazado('la sesión ya está cerrada');
                }

                if ($operarioPersonaId !== null && (int) $sesion->piloto_id !== $operarioPersonaId) {
                    return ResultadoSincronizacion::rechazado('el piloto de la sesión no corresponde al operario autenticado');
                }

                $this->maquinaSesion->cerrar($sesion, $datos->uuidCliente, $datos->fin, $datos->motivoCierre, $datos->hectareasDeclaradas);

                $this->recalcularHectareasTrabajo((int) $sesion->trabajo_id);

                return ResultadoSincronizacion::aplicado();
            });
        } catch (QueryException) {
            return ResultadoSincronizacion::rechazado('no se pudo cerrar la sesión: referencia o dato inválido');
        } catch (TransicionSesionNoPermitida) {
            return ResultadoSincronizacion::rechazado('la sesión ya está cerrada');
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
    }

    private function sumaHectareasSesiones(int $trabajoId): string
    {
        return (string) Sesion::query()->where('trabajo_id', $trabajoId)->sum('hectareas_declaradas');
    }
}
