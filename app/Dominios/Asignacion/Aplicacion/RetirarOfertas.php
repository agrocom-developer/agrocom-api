<?php

namespace App\Dominios\Asignacion\Aplicacion;

use App\Dominios\Asignacion\Aplicacion\MaquinaEstados\MaquinaEstadosOferta;
use App\Dominios\Asignacion\Contratos\Eventos\OfertaRetirada;
use App\Dominios\Asignacion\Dominio\EstadoOferta;
use App\Dominios\Asignacion\Dominio\MotivoRetiro;
use App\Dominios\Asignacion\Infraestructura\Eloquent\Oferta;
use App\Dominios\Asignacion\Infraestructura\Eloquent\Ronda;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Solicitudes\Contratos\LecturaSolicitudesParaAsignar;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Retira las ofertas vivas de una solicitud (especificación §5.3): porque otro
 * proveedor aceptó antes, porque el cliente canceló o porque un administrador
 * asignó a mano. Una oferta retirada no cuenta contra el proveedor: no
 * dependió de él.
 */
final class RetirarOfertas
{
    public function __construct(
        private readonly MaquinaEstadosOferta $ofertas,
        private readonly LecturaProveedores $proveedores,
        private readonly LecturaSolicitudesParaAsignar $solicitudes,
    ) {}

    /**
     * Oyente de `SolicitudCancelada`: retira lo que siga vivo. Idempotente.
     *
     * @return int ofertas que se retiraron.
     */
    public function porCancelacion(int $solicitudId): int
    {
        return DB::transaction(function () use ($solicitudId): int {
            $this->solicitudes->datosBloqueando($solicitudId);

            $retiradas = $this->retirarVivas($solicitudId, null, MotivoRetiro::Cancelada);

            $this->cerrarRondas($solicitudId);

            return $retiradas;
        });
    }

    /**
     * Retira las vivas de la solicitud, salvo la indicada. Quien llama ya tiene
     * bloqueada la solicitud.
     *
     * @return int ofertas que se retiraron.
     */
    public function retirarVivas(int $solicitudId, ?int $exceptoOfertaId, MotivoRetiro $motivo): int
    {
        $vivas = Oferta::query()
            ->where('solicitud_id', $solicitudId)
            ->where('estado', EstadoOferta::Ofrecida->value)
            ->when($exceptoOfertaId !== null, fn ($consulta) => $consulta->where('id', '!=', $exceptoOfertaId))
            ->lockForUpdate()
            ->get();

        foreach ($vivas as $oferta) {
            $this->retirar($oferta, $motivo);
        }

        return $vivas->count();
    }

    public function retirar(Oferta $oferta, MotivoRetiro $motivo): void
    {
        $this->ofertas->transicionar($oferta, EstadoOferta::Retirada, ['motivo_retiro' => $motivo->value]);

        event(new OfertaRetirada(
            $oferta->id,
            $oferta->solicitud_id,
            $oferta->proveedor_id,
            $this->proveedores->usuariosDeAviso($oferta->proveedor_id),
            $motivo->value,
        ));
    }

    public function cerrarRondas(int $solicitudId): void
    {
        Ronda::query()
            ->where('solicitud_id', $solicitudId)
            ->whereNull('cerrada_en')
            ->get()
            ->each(fn (Ronda $ronda) => $ronda->forceFill(['cerrada_en' => Carbon::now()])->save());
    }
}
