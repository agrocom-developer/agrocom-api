<?php

namespace App\Dominios\Negociacion\Aplicacion;

use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Mensaje;
use App\Dominios\Solicitudes\Contratos\DatosSolicitudNegociable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

/**
 * Chat sencillo entre el cliente y el proveedor adjudicado (`negociacion-contrato.md` §5).
 * Desde `adjudicada`; se escribe hasta el cierre del plazo de calificación (7 días
 * después de `finalizada`) o mientras haya una disputa abierta, y después queda de solo
 * lectura. La consulta es incremental por cursor (el id del último mensaje recibido): el
 * cliente sondea cada pocos segundos con el chat abierto; un canal en tiempo real
 * podría reemplazar el sondeo sin cambiar los recursos.
 */
final class Mensajeria
{
    public const int LIMITE_TEXTO = 1000;

    public function __construct(
        private readonly Participantes $participantes,
        private readonly ParametrosDeNegociacion $parametros,
        private readonly Avisador $avisos,
    ) {}

    /**
     * @return array{0: Mensaje, 1: bool} el mensaje y si se creó ahora (`false` = reintento del mismo uuid).
     *
     * @throws NegociacionInvalida
     */
    public function enviar(int $usuarioId, int $solicitudId, string $uuidCliente, string $texto): array
    {
        [$solicitud, $lado] = $this->participantes->lado($usuarioId, $solicitudId);

        $existente = Mensaje::query()->where('uuid_cliente', $uuidCliente)->first();
        if ($existente !== null) {
            if ($existente->solicitud_id !== $solicitudId || $existente->autor_usuario_id !== $usuarioId) {
                throw NegociacionInvalida::dato('uuid_en_uso', 'uuid_cliente');
            }

            return [$existente, false];
        }

        $this->exigirAbierto($solicitud);

        $texto = trim($texto);
        if ($texto === '' || mb_strlen($texto) > self::LIMITE_TEXTO) {
            throw NegociacionInvalida::dato('mensaje_invalido', 'texto', ['maximo' => self::LIMITE_TEXTO]);
        }

        try {
            $mensaje = Mensaje::query()->create([
                'uuid_cliente' => $uuidCliente,
                'solicitud_id' => $solicitudId,
                'autor_usuario_id' => $usuarioId,
                'autor_rol' => $lado,
                'texto' => $texto,
            ]);
        } catch (UniqueConstraintViolationException) {
            return [Mensaje::query()->where('uuid_cliente', $uuidCliente)->firstOrFail(), false];
        }

        $destinatarios = $lado === Participantes::CLIENTE ? $this->participantes->cuentasDelProveedor($solicitud) : [$solicitud->clienteUsuarioId];
        $this->avisos->avisar('mensaje_nuevo', "mensaje_nuevo:{$mensaje->id}", ['solicitud' => $solicitudId], $solicitudId, array_values(array_diff($destinatarios, [$usuarioId])), 'mensajes');

        return [$mensaje, true];
    }

    /**
     * Los mensajes posteriores al cursor, en orden de creación.
     *
     * @return array{mensajes: list<Mensaje>, cursor: int|null, solo_lectura: bool}
     *
     * @throws NegociacionInvalida
     */
    public function listar(int $usuarioId, int $solicitudId, ?int $despues, int $limite): array
    {
        [$solicitud] = $this->participantes->lado($usuarioId, $solicitudId);

        $mensajes = Mensaje::query()
            ->where('solicitud_id', $solicitudId)
            ->when($despues !== null, fn ($q) => $q->where('id', '>', $despues))
            ->orderBy('id')
            ->limit(max(1, min($limite, 100)))
            ->get();

        return [
            'mensajes' => $mensajes->all(),
            'cursor' => $mensajes->isEmpty() ? $despues : $mensajes->last()->id,
            'solo_lectura' => $this->soloLectura($solicitud),
        ];
    }

    /** Marca como leídos los mensajes de la contraparte hasta el cursor. */
    public function marcarLeidos(int $usuarioId, int $solicitudId, int $hasta): int
    {
        $this->participantes->lado($usuarioId, $solicitudId);

        return Mensaje::query()
            ->where('solicitud_id', $solicitudId)
            ->where('id', '<=', $hasta)
            ->where('autor_usuario_id', '!=', $usuarioId)
            ->whereNull('leido_en')
            ->update(['leido_en' => Carbon::now()]);
    }

    /** @throws NegociacionInvalida `chat_cerrado` (409) */
    private function exigirAbierto(DatosSolicitudNegociable $solicitud): void
    {
        if ($this->soloLectura($solicitud)) {
            throw NegociacionInvalida::conflicto('chat_cerrado');
        }
    }

    private function soloLectura(DatosSolicitudNegociable $solicitud): bool
    {
        return match ($solicitud->estado) {
            'adjudicada', 'en_ejecucion', 'disputada' => false,
            'finalizada', 'calificada' => $solicitud->finalizadaEn === null
                || Carbon::parse($solicitud->finalizadaEn)->addDays($this->parametros->plazoCalificacionDias())->lt(Carbon::now()),
            default => true,
        };
    }
}
