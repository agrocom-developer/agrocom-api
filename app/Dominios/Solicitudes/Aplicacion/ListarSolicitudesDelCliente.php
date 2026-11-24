<?php

namespace App\Dominios\Solicitudes\Aplicacion;

use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Solicitudes del cliente de la cuenta, de la más nueva a la más vieja
 * (`GET /solicitudes`). `vista` separa lo que sigue vivo (`activas`) del
 * historial: las que terminaron, `completada` o `cancelada`.
 */
final class ListarSolicitudesDelCliente
{
    public const string VISTA_ACTIVAS = 'activas';

    public const string VISTA_HISTORIAL = 'historial';

    public function __construct(private readonly ClienteDeLaCuenta $clientes) {}

    /** @return LengthAwarePaginator<int, SolicitudServicio> */
    public function ejecutar(int $usuarioId, ?string $vista = null, ?EstadoSolicitud $estado = null, int $porPagina = 20): LengthAwarePaginator
    {
        $cliente = $this->clientes->resolver($usuarioId);
        $terminales = [EstadoSolicitud::Completada->value, EstadoSolicitud::Cancelada->value];

        return SolicitudServicio::query()
            ->where('cliente_id', $cliente->id)
            ->with('terreno')
            ->when($vista === self::VISTA_ACTIVAS, fn ($consulta) => $consulta->whereNotIn('estado', $terminales))
            ->when($vista === self::VISTA_HISTORIAL, fn ($consulta) => $consulta->whereIn('estado', $terminales))
            ->when($estado !== null, fn ($consulta) => $consulta->where('estado', $estado->value))
            ->orderByDesc('id')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
