<?php

namespace App\Dominios\Solicitudes\Aplicacion\MaquinaEstados;

use App\Dominios\Compartido\Dominio\AccionAprobacion;
use App\Dominios\Compartido\Dominio\DecisionDeAprobacion;
use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Compartido\Dominio\Excepciones\MotivoRequerido;
use App\Dominios\Compartido\Dominio\Excepciones\TransicionDeAprobacionInvalida;
use App\Dominios\Compartido\Dominio\OrigenCambio;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Cliente;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\ClienteHistorialEstado;
use Illuminate\Support\Carbon;

/**
 * Única clase que crea y cambia el `estado` de un cliente (invariante 7 de CLAUDE.md): el
 * ciclo único de aprobación que comparten proveedores, pilotos y drones (ADR 0046, punto 11),
 * con su tabla de transiciones ({@see DecisionDeAprobacion}). `pendiente → aprobado ⇄
 * suspendido`, `pendiente → rechazado → pendiente` y cualquiera → `baja`. Un cliente nace
 * `pendiente`; el estado nunca llega de un formulario.
 *
 * Cada cambio deja una fila de historial con autor, motivo y momento (solo se inserta) y,
 * si aprueba, quién y cuándo. El cambio queda además en la bitácora (invariante 9).
 */
final class MaquinaEstadosCliente
{
    /** @param  array<string, mixed>  $atributos  sin `estado`: lo fija esta clase. */
    public function registrar(array $atributos, ?int $actorUsuarioId): Cliente
    {
        $cliente = Cliente::query()->create([...$atributos, 'estado' => EstadoAprobacion::Pendiente]);

        $this->historial($cliente, null, EstadoAprobacion::Pendiente, AccionAprobacion::Reenviar, OrigenCambio::Solicitante, $actorUsuarioId, null);

        return $cliente;
    }

    /**
     * Estado al que llevaría la acción, sin escribir nada.
     *
     * @throws TransicionDeAprobacionInvalida si la acción no corresponde al estado actual.
     * @throws MotivoRequerido si rechazar, suspender o dar de baja llega sin motivo.
     */
    public function destino(Cliente $cliente, AccionAprobacion $accion, ?string $motivo): EstadoAprobacion
    {
        return DecisionDeAprobacion::validar($cliente->estado, $accion, $motivo);
    }

    /** Escribe el estado que devolvió {@see destino()} y deja la fila de historial. Aprobar deja quién y cuándo. */
    public function aplicar(Cliente $cliente, AccionAprobacion $accion, EstadoAprobacion $destino, ?int $actorUsuarioId, ?string $motivo, OrigenCambio $origen): ClienteHistorialEstado
    {
        $anterior = $cliente->estado;

        $cliente->forceFill([
            'estado' => $destino,
            'motivo_estado' => DecisionDeAprobacion::motivoAGuardar($accion, $motivo),
        ]);

        if ($accion === AccionAprobacion::Aprobar) {
            $cliente->forceFill(['aprobado_por' => $actorUsuarioId, 'aprobado_en' => Carbon::now()]);
        }

        $cliente->save();

        return $this->historial($cliente, $anterior, $destino, $accion, $origen, $actorUsuarioId, $motivo);
    }

    private function historial(Cliente $cliente, ?EstadoAprobacion $anterior, EstadoAprobacion $nuevo, AccionAprobacion $accion, OrigenCambio $origen, ?int $actorUsuarioId, ?string $motivo): ClienteHistorialEstado
    {
        return ClienteHistorialEstado::query()->create([
            'cliente_id' => $cliente->id,
            'estado_anterior' => $anterior,
            'estado_nuevo' => $nuevo,
            'accion' => $accion,
            'origen' => $origen,
            'actor_usuario_id' => $actorUsuarioId,
            'motivo' => $motivo !== null && trim($motivo) !== '' ? trim($motivo) : null,
        ]);
    }
}
