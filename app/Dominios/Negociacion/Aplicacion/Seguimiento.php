<?php

namespace App\Dominios\Negociacion\Aplicacion;

use App\Dominios\Compartido\Dominio\MontoDelServicio;
use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Avance;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Evidencia;
use App\Dominios\Solicitudes\Contratos\DatosSolicitudNegociable;
use App\Dominios\Solicitudes\Contratos\PasosDeSolicitud;
use App\Dominios\Solicitudes\Contratos\SolicitudNoNegociable;
use Brick\Math\BigDecimal;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * La ejecución del servicio (`negociacion-contrato.md` §4): el piloto marca el inicio,
 * sube fotos y marca el avance, y cierra con las hectáreas ejecutadas. Los registros
 * nacen en el dispositivo con su `uuid_cliente` y se aplican idempotentemente (aplicar
 * dos veces el mismo uuid devuelve el mismo registro y deja la base idéntica); lo ya
 * registrado no se reescribe, una corrección es un registro nuevo con motivo. Lo
 * operativo se acuerda en persona: la app solo registra estado y resultado.
 *
 * El servicio pasa a `finalizada` cuando hay `cierre` y al menos
 * `seguimiento.evidencias_minimas_cierre` fotos de avance o finales subidas, en el orden
 * en que lleguen: la evidencia que completa el mínimo revisa el cierre pendiente.
 */
final class Seguimiento
{
    public function __construct(
        private readonly Participantes $participantes,
        private readonly PasosDeSolicitud $solicitudes,
        private readonly ParametrosDeNegociacion $parametros,
        private readonly Avisador $avisos,
    ) {}

    /**
     * `adjudicada` pasa a `en_ejecucion` (registro `inicio`).
     *
     * @param  array{uuid_cliente: string, secuencia?: int|null, registrado_en?: string|null}  $datos
     * @return array{0: Avance, 1: bool}
     *
     * @throws NegociacionInvalida
     */
    public function iniciar(int $usuarioId, int $solicitudId, array $datos): array
    {
        return $this->registrar($usuarioId, $solicitudId, 'inicio', $datos, function (DatosSolicitudNegociable $solicitud, Avance $registro) use ($usuarioId): void {
            if ($solicitud->estado !== 'adjudicada') {
                throw NegociacionInvalida::conflicto('estado_invalido');
            }

            try {
                $this->solicitudes->iniciarEjecucion($solicitud->id, $usuarioId, $registro->registrado_en);
            } catch (SolicitudNoNegociable) {
                throw NegociacionInvalida::conflicto('estado_invalido');
            }

            $this->avisos->avisar('ejecucion_iniciada', "ejecucion_iniciada:{$solicitud->id}", ['solicitud' => $solicitud->id], $solicitud->id, [$solicitud->clienteUsuarioId]);
        });
    }

    /**
     * Marca el avance con hectáreas ejecutadas o con porcentaje (uno de los dos).
     *
     * @param  array{uuid_cliente: string, secuencia?: int|null, registrado_en?: string|null, hectareas_ejecutadas?: string|int|float|null, porcentaje?: string|int|float|null, nota?: string|null, motivo_correccion?: string|null}  $datos
     * @return array{0: Avance, 1: bool}
     *
     * @throws NegociacionInvalida
     */
    public function avanzar(int $usuarioId, int $solicitudId, array $datos): array
    {
        return $this->registrar($usuarioId, $solicitudId, 'avance', $datos, function (DatosSolicitudNegociable $solicitud, Avance $registro) use ($datos): void {
            $this->exigirEnEjecucion($solicitud);
            [$hectareas, $porcentaje] = $this->cifras($solicitud, $datos);

            if (BigDecimal::of($hectareas)->isLessThan(BigDecimal::of($solicitud->hectareasEjecutadas ?? '0')) && trim((string) ($datos['motivo_correccion'] ?? '')) === '') {
                throw NegociacionInvalida::dato('correccion_sin_motivo', 'motivo_correccion');
            }

            $registro->forceFill(['hectareas_ejecutadas' => $hectareas, 'porcentaje' => $porcentaje])->save();
            $this->solicitudes->registrarAvance($solicitud->id, $hectareas, $porcentaje, $registro->registrado_en);

            $this->avisos->avisar('avance_registrado', "avance_registrado:{$registro->id}", ['solicitud' => $solicitud->id, 'porcentaje' => $porcentaje], $solicitud->id, [$solicitud->clienteUsuarioId], 'avance');
        });
    }

    /**
     * El piloto pide el cierre con las hectáreas ejecutadas; finaliza si ya están las fotos mínimas.
     *
     * @param  array{uuid_cliente: string, secuencia?: int|null, registrado_en?: string|null, hectareas_ejecutadas: string|int|float, nota?: string|null}  $datos
     * @return array{0: Avance, 1: bool}
     *
     * @throws NegociacionInvalida
     */
    public function cerrar(int $usuarioId, int $solicitudId, array $datos): array
    {
        return $this->registrar($usuarioId, $solicitudId, 'cierre', $datos, function (DatosSolicitudNegociable $solicitud, Avance $registro) use ($datos, $usuarioId): void {
            $this->exigirEnEjecucion($solicitud);
            [$hectareas, $porcentaje] = $this->cifras($solicitud, ['hectareas_ejecutadas' => $datos['hectareas_ejecutadas']]);

            $registro->forceFill(['hectareas_ejecutadas' => $hectareas, 'porcentaje' => $porcentaje])->save();
            $this->solicitudes->marcarCierreSolicitado($solicitud->id, $hectareas, $registro->registrado_en);
            $this->finalizarSiCorresponde($solicitud->id, $usuarioId);
        });
    }

    /**
     * Guarda una foto (cola separada de los registros livianos). Idempotente por `uuid_cliente`.
     *
     * @param  array{uuid_cliente: string, tipo: string, avance_uuid_cliente?: string|null}  $datos
     * @return array{0: Evidencia, 1: bool}
     *
     * @throws NegociacionInvalida
     */
    public function subirEvidencia(int $usuarioId, int $solicitudId, array $datos, UploadedFile $archivo): array
    {
        $existente = Evidencia::query()->where('uuid_cliente', $datos['uuid_cliente'])->first();
        if ($existente !== null) {
            $this->participantes->delEquipo($usuarioId, $existente->solicitud_id, false);

            return [$existente, false];
        }

        $solicitud = $this->participantes->delEquipo($usuarioId, $solicitudId, false);

        if ($solicitud->estado !== 'en_ejecucion' && ! ($solicitud->estado === 'adjudicada' && $datos['tipo'] === 'foto_incidencia')) {
            throw NegociacionInvalida::conflicto('estado_invalido');
        }

        if ($archivo->getSize() > $this->parametros->evidenciaMaxKb() * 1024) {
            throw NegociacionInvalida::dato('evidencia_muy_grande', 'archivo', ['maximo' => $this->parametros->evidenciaMaxKb()]);
        }

        $avanceId = ($datos['avance_uuid_cliente'] ?? null) === null
            ? null
            : Avance::query()->where('solicitud_id', $solicitudId)->where('uuid_cliente', $datos['avance_uuid_cliente'])->value('id');

        $ruta = $archivo->storeAs("evidencias/{$solicitudId}", $datos['uuid_cliente'].'.'.($archivo->guessExtension() ?: 'jpg'), 'local');

        try {
            $evidencia = DB::transaction(function () use ($datos, $solicitudId, $usuarioId, $archivo, $ruta, $avanceId): Evidencia {
                $evidencia = Evidencia::query()->create([
                    'uuid_cliente' => $datos['uuid_cliente'],
                    'solicitud_id' => $solicitudId,
                    'avance_id' => $avanceId,
                    'tipo' => $datos['tipo'],
                    'archivo' => (string) $ruta,
                    'hash' => hash_file('sha256', $archivo->getRealPath()) ?: '',
                    'bytes' => (int) $archivo->getSize(),
                    'usuario_id' => $usuarioId,
                ]);

                $this->finalizarSiCorresponde($solicitudId, $usuarioId);

                return $evidencia;
            });
        } catch (UniqueConstraintViolationException) {
            return [Evidencia::query()->where('uuid_cliente', $datos['uuid_cliente'])->firstOrFail(), false];
        }

        return [$evidencia, true];
    }

    /**
     * El seguimiento que ven el cliente y el proveedor adjudicado.
     *
     * @return array<string, mixed>
     *
     * @throws NegociacionInvalida
     */
    public function consultar(int $usuarioId, int $solicitudId): array
    {
        [$solicitud, $lado] = $this->participantes->lado($usuarioId, $solicitudId);

        $registros = Avance::query()->where('solicitud_id', $solicitudId)->orderBy('registrado_en')->orderBy('id')->get();
        $fotos = Evidencia::query()->where('solicitud_id', $solicitudId)->orderBy('id')->get();

        return [
            'solicitud_id' => $solicitud->id,
            'estado' => $solicitud->estado,
            'lado' => $lado,
            'hectareas_solicitadas' => $solicitud->hectareasSolicitadas,
            'hectareas_ejecutadas' => $solicitud->hectareasEjecutadas,
            'porcentaje_avance' => $solicitud->porcentajeAvance,
            'precio_ha_acordado' => $solicitud->precioHaAcordado,
            'monto_estimado' => $solicitud->montoEstimado,
            'monto_final' => $solicitud->montoFinal,
            'iniciada_en' => $solicitud->iniciadaEn,
            'ultimo_avance_en' => $solicitud->ultimoAvanceEn,
            'cierre_solicitado_en' => $solicitud->cierreSolicitadoEn,
            'finalizada_en' => $solicitud->finalizadaEn,
            'registros' => $registros->map(fn (Avance $a): array => Presentacion::avance($a))->all(),
            'fotos' => $fotos->map(fn (Evidencia $e): array => Presentacion::evidencia($e))->all(),
        ];
    }

    /**
     * El archivo de una foto, para las dos partes del servicio.
     *
     * @return array{0: Evidencia, 1: string} la evidencia y la ruta en el disco.
     *
     * @throws NegociacionInvalida
     */
    public function archivoDeEvidencia(int $usuarioId, int $evidenciaId): array
    {
        $evidencia = Evidencia::query()->find($evidenciaId) ?? throw NegociacionInvalida::ajeno('evidencia_ajena');
        $this->participantes->lado($usuarioId, $evidencia->solicitud_id);

        if (! Storage::disk('local')->exists($evidencia->archivo)) {
            throw NegociacionInvalida::ajeno('evidencia_ajena');
        }

        return [$evidencia, Storage::disk('local')->path($evidencia->archivo)];
    }

    /**
     * Crea el registro idempotente y corre la acción de su tipo dentro de una transacción con
     * la solicitud bloqueada.
     *
     * @param  array<string, mixed>  $datos
     * @param  callable(DatosSolicitudNegociable, Avance): void  $accion
     * @return array{0: Avance, 1: bool}
     */
    private function registrar(int $usuarioId, int $solicitudId, string $tipo, array $datos, callable $accion): array
    {
        $existente = Avance::query()->where('uuid_cliente', $datos['uuid_cliente'])->first();
        if ($existente !== null) {
            $this->participantes->delEquipo($usuarioId, $existente->solicitud_id, true);

            return [$existente, false];
        }

        try {
            return [DB::transaction(function () use ($usuarioId, $solicitudId, $tipo, $datos, $accion): Avance {
                $solicitud = $this->participantes->delEquipo($usuarioId, $solicitudId, true, true);

                $registro = Avance::query()->create([
                    'uuid_cliente' => $datos['uuid_cliente'],
                    'solicitud_id' => $solicitudId,
                    'secuencia' => (int) ($datos['secuencia'] ?? 0),
                    'tipo' => $tipo,
                    'nota' => isset($datos['nota']) ? trim((string) $datos['nota']) : null,
                    'motivo_correccion' => isset($datos['motivo_correccion']) ? trim((string) $datos['motivo_correccion']) : null,
                    'registrado_en' => isset($datos['registrado_en']) ? Carbon::parse($datos['registrado_en']) : Carbon::now(),
                    'usuario_id' => $usuarioId,
                ]);

                $accion($solicitud, $registro);

                return $registro->refresh();
            }), true];
        } catch (UniqueConstraintViolationException) {
            // `inicio` duplicado con otro uuid, o dos reintentos en paralelo: el registro ya existe.
            $ganador = Avance::query()->where('uuid_cliente', $datos['uuid_cliente'])->first();
            if ($ganador === null) {
                throw NegociacionInvalida::conflicto('estado_invalido');
            }

            return [$ganador, false];
        }
    }

    private function exigirEnEjecucion(DatosSolicitudNegociable $solicitud): void
    {
        if ($solicitud->estado !== 'en_ejecucion') {
            throw NegociacionInvalida::conflicto('estado_invalido');
        }
    }

    /**
     * Hectáreas y porcentaje coherentes entre sí, dentro de la tolerancia.
     *
     * @param  array<string, mixed>  $datos
     * @return array{0: string, 1: string}
     *
     * @throws NegociacionInvalida
     */
    private function cifras(DatosSolicitudNegociable $solicitud, array $datos): array
    {
        $hectareas = $datos['hectareas_ejecutadas'] ?? null;
        $porcentaje = $datos['porcentaje'] ?? null;

        if (($hectareas === null) === ($porcentaje === null)) {
            throw NegociacionInvalida::dato('avance_requiere_uno', 'hectareas_ejecutadas');
        }

        $hectareas = $hectareas !== null
            ? (string) BigDecimal::of((string) $hectareas)->toScale(4)
            : MontoDelServicio::hectareasDePorcentaje((string) $porcentaje, $solicitud->hectareasSolicitadas);

        if (BigDecimal::of($hectareas)->isNegative()) {
            throw NegociacionInvalida::dato('avance_invalido', 'hectareas_ejecutadas');
        }

        if (BigDecimal::of($hectareas)->isGreaterThan(BigDecimal::of(MontoDelServicio::tope($solicitud->hectareasSolicitadas, $this->parametros->toleranciaHectareasPct())))) {
            throw NegociacionInvalida::dato('hectareas_exceden_tolerancia', 'hectareas_ejecutadas', ['tolerancia' => $this->parametros->toleranciaHectareasPct()]);
        }

        return [$hectareas, MontoDelServicio::porcentaje($hectareas, $solicitud->hectareasSolicitadas)];
    }

    /** Finaliza si hay un `cierre` pendiente y las fotos mínimas están subidas. Idempotente. */
    private function finalizarSiCorresponde(int $solicitudId, ?int $usuarioId): void
    {
        $solicitud = $this->solicitudes->datos($solicitudId, true);

        if ($solicitud === null || $solicitud->estado !== 'en_ejecucion' || $solicitud->cierreSolicitadoEn === null) {
            return;
        }

        $fotos = Evidencia::query()->where('solicitud_id', $solicitudId)->whereIn('tipo', ['foto_avance', 'foto_final'])->count();
        if ($fotos < $this->parametros->evidenciasMinimasCierre()) {
            return;
        }

        $hectareas = (string) ($solicitud->hectareasEjecutadas ?? '0');
        $this->solicitudes->finalizar($solicitudId, $hectareas, $usuarioId, Carbon::now());
        DB::table('neg_ubicaciones')->where('solicitud_id', $solicitudId)->delete();

        $monto = MontoDelServicio::monto($hectareas, (string) $solicitud->precioHaAcordado);
        $this->avisos->avisar('solicitud_finalizada', "solicitud_finalizada:{$solicitudId}", ['solicitud' => $solicitudId, 'monto' => $monto], $solicitudId, [$solicitud->clienteUsuarioId, ...$this->participantes->cuentasDelProveedor($solicitud)]);
    }
}
