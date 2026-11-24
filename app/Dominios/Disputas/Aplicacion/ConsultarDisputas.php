<?php

namespace App\Dominios\Disputas\Aplicacion;

use App\Dominios\Disputas\Dominio\EstadoDisputa;
use App\Dominios\Disputas\Dominio\Excepciones\DisputaInvalida;
use App\Dominios\Disputas\Dominio\ParteDeDisputa;
use App\Dominios\Disputas\Infraestructura\Eloquent\Disputa;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Lo que cada lado ve de las disputas. Parte de la cuenta (invariante 5 de CLAUDE.md):
 * el cliente, de lo que es de su cuenta; el proveedor, de las de su proveedor. Una
 * ajena responde 404.
 */
final class ConsultarDisputas
{
    private const POR_PAGINA = 20;

    public function __construct(private readonly ParticipanteDeDisputa $participantes) {}

    /**
     * Las disputas de la cuenta, de la más nueva a la más vieja.
     *
     * @return LengthAwarePaginator<int, Disputa>
     *
     * @throws DisputaInvalida
     */
    public function propias(int $usuarioId, ParteDeDisputa $parte, ?string $estado = null, int $pagina = 1): LengthAwarePaginator
    {
        $proveedorId = $this->participantes->proveedorDe($usuarioId, $parte);

        return Disputa::query()
            ->when($parte === ParteDeDisputa::Cliente, fn ($consulta) => $consulta->where('cliente_usuario_id', $usuarioId))
            ->when($parte === ParteDeDisputa::Proveedor, fn ($consulta) => $consulta->where('proveedor_id', $proveedorId))
            ->when(EstadoDisputa::tryFrom((string) $estado), fn ($consulta, EstadoDisputa $e) => $consulta->where('estado', $e->value))
            ->orderByDesc('id')
            ->paginate(self::POR_PAGINA, ['*'], 'page', max(1, $pagina));
    }

    /**
     * Una disputa de la cuenta con su conversación y sus resoluciones.
     *
     * @throws DisputaInvalida
     */
    public function una(int $usuarioId, ParteDeDisputa $parte, int $disputaId): Disputa
    {
        return $this->participantes->disputa($usuarioId, $parte, $disputaId)->load(['mensajes', 'resoluciones']);
    }

    /**
     * La disputa de una solicitud, si es de la cuenta.
     *
     * @throws DisputaInvalida `disputaAjena` (404) si la solicitud no tiene disputa o no es de la cuenta.
     */
    public function deLaSolicitud(int $usuarioId, ParteDeDisputa $parte, int $solicitudId): Disputa
    {
        $proveedorId = $this->participantes->proveedorDe($usuarioId, $parte);
        $disputa = Disputa::query()->where('solicitud_id', $solicitudId)->first();

        if ($disputa === null || ! $this->participantes->esDeLaCuenta($disputa, $usuarioId, $parte, $proveedorId)) {
            throw DisputaInvalida::disputaAjena();
        }

        return $disputa->load(['mensajes', 'resoluciones']);
    }
}
