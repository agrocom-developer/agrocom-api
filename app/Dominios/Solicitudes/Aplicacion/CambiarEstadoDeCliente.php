<?php

namespace App\Dominios\Solicitudes\Aplicacion;

use App\Dominios\Compartido\Contratos\Eventos\EstadoDeAprobacionCambiado;
use App\Dominios\Compartido\Dominio\AccionAprobacion;
use App\Dominios\Compartido\Dominio\Excepciones\MotivoRequerido;
use App\Dominios\Compartido\Dominio\Excepciones\TransicionDeAprobacionInvalida;
use App\Dominios\Compartido\Dominio\OrigenCambio;
use App\Dominios\Solicitudes\Aplicacion\MaquinaEstados\MaquinaEstadosCliente;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Cliente;
use Illuminate\Support\Facades\DB;

/**
 * Aprueba, rechaza, suspende, rehabilita o da de baja a un cliente desde el panel (ADR 0046,
 * punto 11), con el mismo ciclo de aprobación que proveedores y pilotos. Un cliente que no
 * está `aprobado` no publica solicitudes ni acepta o contraoferta; lo que ya tiene en curso
 * sigue su camino y se atiende por solicitud (cancelar o intervenir). No es una sanción
 * automática: la decide una persona, con motivo cuando es un rechazo, una suspensión o una
 * baja (invariante 14). Quién puede decidir (el permiso) lo verifica el controlador.
 *
 * Avisa a la cuenta del cliente (`EstadoDeAprobacionCambiado`): la aprobación se notifica por la app.
 */
final class CambiarEstadoDeCliente
{
    public function __construct(private readonly MaquinaEstadosCliente $estados) {}

    /**
     * @throws TransicionDeAprobacionInvalida si la acción no corresponde al estado actual.
     * @throws MotivoRequerido si rechazar, suspender o dar de baja llega sin motivo.
     */
    public function ejecutar(int $clienteId, AccionAprobacion $accion, ?string $motivo, ?int $actorUsuarioId): Cliente
    {
        return DB::transaction(function () use ($clienteId, $accion, $motivo, $actorUsuarioId): Cliente {
            $cliente = Cliente::query()->lockForUpdate()->findOrFail($clienteId);

            $destino = $this->estados->destino($cliente, $accion, $motivo);
            $historial = $this->estados->aplicar($cliente, $accion, $destino, $actorUsuarioId, $motivo, OrigenCambio::Administrador);

            event(new EstadoDeAprobacionCambiado(
                sujetoTipo: 'cliente',
                sujetoId: $cliente->id,
                nombre: $cliente->razon_social,
                historialId: $historial->id,
                accion: $accion,
                motivo: $motivo !== null && trim($motivo) !== '' ? trim($motivo) : null,
                usuariosDestinatarios: [$cliente->usuario_id],
            ));

            return $cliente;
        });
    }

    /** @throws TransicionDeAprobacionInvalida|MotivoRequerido */
    public function suspender(int $clienteId, string $motivo, ?int $actorUsuarioId = null): Cliente
    {
        return $this->ejecutar($clienteId, AccionAprobacion::Suspender, $motivo, $actorUsuarioId);
    }

    /** @throws TransicionDeAprobacionInvalida */
    public function reactivar(int $clienteId, ?int $actorUsuarioId = null): Cliente
    {
        return $this->ejecutar($clienteId, AccionAprobacion::Rehabilitar, null, $actorUsuarioId);
    }
}
