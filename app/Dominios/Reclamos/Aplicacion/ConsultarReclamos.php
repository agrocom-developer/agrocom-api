<?php

namespace App\Dominios\Reclamos\Aplicacion;

use App\Dominios\Reclamos\Dominio\EstadoReclamo;
use App\Dominios\Reclamos\Dominio\Excepciones\ReclamoInvalido;
use App\Dominios\Reclamos\Dominio\ParteDeReclamo;
use App\Dominios\Reclamos\Infraestructura\Eloquent\Reclamo;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Lo que cada lado ve de los reclamos. Parte de la cuenta (invariante 5 de CLAUDE.md):
 * el cliente, de lo que es de su cuenta; el proveedor, de las de su proveedor. Una
 * ajena responde 404.
 */
final class ConsultarReclamos
{
    private const POR_PAGINA = 20;

    public function __construct(private readonly ParticipanteDeReclamo $participantes) {}

    /**
     * Los reclamos de la cuenta, de la más nueva a la más vieja.
     *
     * @return LengthAwarePaginator<int, Reclamo>
     *
     * @throws ReclamoInvalido
     */
    public function propias(int $usuarioId, ParteDeReclamo $parte, ?string $estado = null, int $pagina = 1): LengthAwarePaginator
    {
        $proveedorId = $this->participantes->proveedorDe($usuarioId, $parte);

        return Reclamo::query()
            ->when($parte === ParteDeReclamo::Cliente, fn ($consulta) => $consulta->where('cliente_usuario_id', $usuarioId))
            ->when($parte === ParteDeReclamo::Proveedor, fn ($consulta) => $consulta->where('proveedor_id', $proveedorId))
            ->when(EstadoReclamo::tryFrom((string) $estado), fn ($consulta, EstadoReclamo $e) => $consulta->where('estado', $e->value))
            ->orderByDesc('id')
            ->paginate(self::POR_PAGINA, ['*'], 'page', max(1, $pagina));
    }

    /**
     * Un reclamo de la cuenta con su conversación y sus resoluciones.
     *
     * @throws ReclamoInvalido
     */
    public function una(int $usuarioId, ParteDeReclamo $parte, int $reclamoId): Reclamo
    {
        return $this->participantes->reclamo($usuarioId, $parte, $reclamoId)->load(['mensajes', 'resoluciones']);
    }

    /**
     * El reclamo de una solicitud, si es de la cuenta.
     *
     * @throws ReclamoInvalido `reclamoAjeno` (404) si la solicitud no tiene reclamo o no es de la cuenta.
     */
    public function deLaSolicitud(int $usuarioId, ParteDeReclamo $parte, int $solicitudId): Reclamo
    {
        $proveedorId = $this->participantes->proveedorDe($usuarioId, $parte);
        $reclamo = Reclamo::query()->where('solicitud_id', $solicitudId)->first();

        if ($reclamo === null || ! $this->participantes->esDeLaCuenta($reclamo, $usuarioId, $parte, $proveedorId)) {
            throw ReclamoInvalido::reclamoAjeno();
        }

        return $reclamo->load(['mensajes', 'resoluciones']);
    }
}
