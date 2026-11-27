<?php

namespace App\Dominios\Negociacion\Aplicacion;

use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;
use App\Dominios\Negociacion\Infraestructura\Eloquent\PrecioFinalConfirmado;
use App\Dominios\Solicitudes\Contratos\DatosSolicitudNegociable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * El cliente paga, así que tiene la última palabra sobre el precio final (hectáreas
 * ejecutadas por precio acordado, ADR 0034): lo confirma aquí o abre un reclamo. Un solo
 * rol escritor (el cliente de la solicitud); solo con el servicio cerrado por el piloto
 * (`finalizada` o `calificada`, con monto final congelado). Idempotente por solicitud:
 * repetir devuelve la misma confirmación y lo confirmado no se reescribe. No cambia el
 * estado ni mueve dinero: el pago es directo, fuera de la aplicación. Los valores
 * confirmados los copia el servidor, nunca los manda la app.
 */
final class ConfirmarPrecioFinal
{
    /** Estados con el cierre del piloto ya congelado en los que el cliente puede confirmar. */
    private const array ESTADOS = ['finalizada', 'calificada'];

    public function __construct(
        private readonly Participantes $participantes,
        private readonly Avisador $avisos,
    ) {}

    /**
     * @return array{0: array{hectareas_ejecutadas: string, precio_ha_acordado: string, monto_final: string, confirmacion: ?PrecioFinalConfirmado}, 1: bool} el precio final con su confirmación y si se confirmó ahora.
     *
     * @throws NegociacionInvalida
     */
    public function ejecutar(int $usuarioId, int $solicitudId): array
    {
        return DB::transaction(function () use ($usuarioId, $solicitudId): array {
            [$solicitud, $lado] = $this->participantes->lado($usuarioId, $solicitudId, true);

            if ($lado !== Participantes::CLIENTE) {
                throw NegociacionInvalida::prohibido('precio_final_solo_cliente');
            }

            $existente = PrecioFinalConfirmado::query()->where('solicitud_id', $solicitud->id)->first();

            if ($existente !== null) {
                return [$this->vista($solicitud, $existente), false];
            }

            if (! in_array($solicitud->estado, self::ESTADOS, true)
                || $solicitud->montoFinal === null
                || $solicitud->hectareasEjecutadas === null
                || $solicitud->precioHaAcordado === null) {
                throw NegociacionInvalida::conflicto('estado_invalido');
            }

            try {
                $confirmacion = PrecioFinalConfirmado::query()->create([
                    'solicitud_id' => $solicitud->id,
                    'confirmado_por_usuario_id' => $usuarioId,
                    'hectareas_ejecutadas' => $solicitud->hectareasEjecutadas,
                    'precio_ha_acordado' => $solicitud->precioHaAcordado,
                    'monto' => $solicitud->montoFinal,
                    'confirmado_en' => Carbon::now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                return [$this->vista($solicitud, PrecioFinalConfirmado::query()->where('solicitud_id', $solicitud->id)->firstOrFail()), false];
            }

            $this->avisos->avisar(
                'precio_final_confirmado',
                "precio_final_confirmado:{$solicitud->id}",
                ['solicitud' => $solicitud->id, 'monto' => $solicitud->montoFinal],
                $solicitud->id,
                $this->participantes->cuentasDelProveedor($solicitud),
            );

            return [$this->vista($solicitud, $confirmacion->refresh()), true];
        });
    }

    /**
     * El precio final y su confirmación, o `null` si el piloto aún no cerró; lo ven las dos partes.
     *
     * @return array{hectareas_ejecutadas: string, precio_ha_acordado: string, monto_final: string, confirmacion: ?PrecioFinalConfirmado}|null
     *
     * @throws NegociacionInvalida `solicitud_ajena` (404)
     */
    public function ver(int $usuarioId, int $solicitudId): ?array
    {
        [$solicitud] = $this->participantes->lado($usuarioId, $solicitudId);

        if ($solicitud->montoFinal === null || $solicitud->hectareasEjecutadas === null || $solicitud->precioHaAcordado === null) {
            return null;
        }

        return $this->vista($solicitud, PrecioFinalConfirmado::query()->where('solicitud_id', $solicitud->id)->first());
    }

    /** @return array{hectareas_ejecutadas: string, precio_ha_acordado: string, monto_final: string, confirmacion: ?PrecioFinalConfirmado} */
    private function vista(DatosSolicitudNegociable $solicitud, ?PrecioFinalConfirmado $confirmacion): array
    {
        return [
            'hectareas_ejecutadas' => (string) $solicitud->hectareasEjecutadas,
            'precio_ha_acordado' => (string) $solicitud->precioHaAcordado,
            'monto_final' => (string) $solicitud->montoFinal,
            'confirmacion' => $confirmacion,
        ];
    }
}
