<?php

namespace App\Dominios\Negociacion\Aplicacion;

use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;
use App\Dominios\Negociacion\Infraestructura\Eloquent\PagoConfirmado;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * El pago ocurre fuera de la aplicación (ADR 0034): el proveedor adjudicado informa que lo
 * recibió. Un solo rol escritor: el titular del proveedor (quien cobra); el cliente solo lo
 * ve. Registro informativo, único por solicitud e idempotente por `uuid_cliente`: no cambia
 * el estado y no se reescribe una vez confirmado. El monto es el monto final congelado de
 * la solicitud al confirmar, nunca un valor que mande la app.
 */
final class ConfirmarPago
{
    public const array METODOS = ['efectivo', 'transferencia', 'qr', 'otro'];

    /** Estados con monto final congelado en los que se puede informar el pago. */
    private const array ESTADOS = ['finalizada', 'calificada', 'disputada'];

    public function __construct(
        private readonly Participantes $participantes,
        private readonly LecturaProveedores $proveedores,
    ) {}

    /**
     * @param  array{uuid_cliente: string, metodo?: string|null, nota?: string|null}  $datos
     * @return array{0: PagoConfirmado, 1: bool} el pago y si se creó ahora.
     *
     * @throws NegociacionInvalida
     */
    public function ejecutar(int $usuarioId, int $solicitudId, array $datos): array
    {
        return DB::transaction(function () use ($usuarioId, $solicitudId, $datos): array {
            [$solicitud, $lado] = $this->participantes->lado($usuarioId, $solicitudId, true);

            if ($lado !== Participantes::PROVEEDOR) {
                throw NegociacionInvalida::prohibido('pago_solo_proveedor');
            }

            if ($this->proveedores->proveedorDelTitular($usuarioId)?->id !== $solicitud->proveedorId) {
                throw NegociacionInvalida::prohibido('pago_solo_titular');
            }

            $existente = PagoConfirmado::query()->where('solicitud_id', $solicitud->id)->first();

            if ($existente !== null) {
                if ($existente->uuid_cliente === $datos['uuid_cliente']) {
                    return [$existente, false];
                }

                throw NegociacionInvalida::conflicto('pago_ya_confirmado');
            }

            if (! in_array($solicitud->estado, self::ESTADOS, true) || $solicitud->montoFinal === null) {
                throw NegociacionInvalida::conflicto('estado_invalido');
            }

            try {
                $pago = PagoConfirmado::query()->create([
                    'uuid_cliente' => $datos['uuid_cliente'],
                    'solicitud_id' => $solicitud->id,
                    'confirmado_por_usuario_id' => $usuarioId,
                    'monto' => $solicitud->montoFinal,
                    'metodo' => $datos['metodo'] ?? null,
                    'nota' => $datos['nota'] ?? null,
                    'confirmado_en' => Carbon::now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                throw NegociacionInvalida::dato('uuid_en_uso', 'uuid_cliente');
            }

            return [$pago->refresh(), true];
        });
    }

    /**
     * El pago confirmado de la solicitud, o `null`; lo ven el cliente y el proveedor adjudicado.
     *
     * @throws NegociacionInvalida `solicitud_ajena` (404)
     */
    public function ver(int $usuarioId, int $solicitudId): ?PagoConfirmado
    {
        [$solicitud] = $this->participantes->lado($usuarioId, $solicitudId);

        return PagoConfirmado::query()->where('solicitud_id', $solicitud->id)->first();
    }
}
