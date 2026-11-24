<?php

namespace App\Dominios\Negociacion\Aplicacion;

use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Último punto de la app del piloto durante la ejecución (`negociacion-contrato.md` §6):
 * solo la última posición, sin ruta ni tiempo estimado de llegada. Se acepta únicamente
 * con la solicitud `en_ejecucion` y desde la cuenta del equipo; la ve solo el cliente
 * adjudicado. Reemplaza la fila única de la solicitud, no va por el outbox (sin red, se
 * omite) y se elimina al finalizar o cancelar. Nunca se guarda en historial, bitácora ni logs.
 */
final class UbicacionDelPiloto
{
    public function __construct(
        private readonly Participantes $participantes,
        private readonly ParametrosDeNegociacion $parametros,
    ) {}

    /** @throws NegociacionInvalida `ubicacion_no_permitida` (409) fuera de `en_ejecucion` */
    public function registrar(int $usuarioId, int $solicitudId, float $latitud, float $longitud, ?float $precisionM, ?string $capturadoEn): void
    {
        $solicitud = $this->participantes->delEquipo($usuarioId, $solicitudId, true);

        if ($solicitud->estado !== 'en_ejecucion') {
            throw NegociacionInvalida::conflicto('ubicacion_no_permitida');
        }

        DB::table('neg_ubicaciones')->updateOrInsert(
            ['solicitud_id' => $solicitudId],
            [
                'latitud' => $latitud,
                'longitud' => $longitud,
                'precision_m' => $precisionM,
                'capturado_en' => $capturadoEn === null ? Carbon::now() : Carbon::parse($capturadoEn),
                'updated_at' => Carbon::now(),
                'created_at' => Carbon::now(),
            ],
        );
    }

    /**
     * La ubicación para el cliente de la solicitud; `null` si no hay o si ya no corresponde.
     *
     * @return array{latitud: float, longitud: float, precision_m: float|null, capturado_en: string, antiguedad_s: int, vigente: bool}|null
     *
     * @throws NegociacionInvalida
     */
    public function delCliente(int $usuarioId, int $solicitudId): ?array
    {
        [$solicitud, $lado] = $this->participantes->lado($usuarioId, $solicitudId);

        if ($lado !== Participantes::CLIENTE) {
            throw NegociacionInvalida::ajeno('solicitud_ajena');
        }

        if ($solicitud->estado !== 'en_ejecucion') {
            return null;
        }

        $fila = DB::table('neg_ubicaciones')->where('solicitud_id', $solicitudId)->first();
        if ($fila === null) {
            return null;
        }

        $capturada = Carbon::parse($fila->capturado_en);
        $antiguedad = max(0, (int) $capturada->diffInSeconds(Carbon::now(), true));

        return [
            'latitud' => (float) $fila->latitud,
            'longitud' => (float) $fila->longitud,
            'precision_m' => $fila->precision_m === null ? null : (float) $fila->precision_m,
            'capturado_en' => $capturada->toIso8601String(),
            'antiguedad_s' => $antiguedad,
            'vigente' => $antiguedad <= $this->parametros->ubicacionCaducaS(),
        ];
    }
}
