<?php

namespace App\Dominios\Asignacion\Aplicacion;

use App\Dominios\Asignacion\Dominio\EstadoOferta;
use App\Dominios\Asignacion\Dominio\Excepciones\AsignacionInvalida;
use App\Dominios\Asignacion\Infraestructura\Eloquent\Oferta;
use App\Dominios\Solicitudes\Contratos\LecturaSolicitudesParaAsignar;
use Illuminate\Support\Carbon;

/**
 * Las ofertas del proveedor de la cuenta (`GET /ofertas`, especificación §10.2).
 * La app consulta esta lista (polling) y las notificaciones de la cuenta para
 * enterarse de una oferta nueva. Parte siempre del proveedor de la cuenta: una
 * oferta ajena no aparece y su detalle responde 404 (invariante 5).
 */
final class ListarOfertasDelProveedor
{
    public function __construct(
        private readonly ProveedorDeLaCuenta $cuentas,
        private readonly LecturaSolicitudesParaAsignar $solicitudes,
    ) {}

    /**
     * @param  string  $vista  `vivas` (esperan respuesta) o `historial` (el resto).
     * @param  string|null  $desde  solo las ofertas creadas o resueltas después de este instante (ISO 8601).
     * @return list<OfertaParaProveedor>
     */
    public function ejecutar(int $usuarioId, string $vista = 'vivas', ?string $desde = null, ?Carbon $ahora = null): array
    {
        $proveedor = $this->cuentas->ejecutar($usuarioId);
        $ahora ??= Carbon::now();

        $ofertas = Oferta::query()
            ->where('proveedor_id', $proveedor->id)
            ->when(
                $vista === 'historial',
                fn ($consulta) => $consulta->where('estado', '!=', EstadoOferta::Ofrecida->value),
                fn ($consulta) => $consulta->where('estado', EstadoOferta::Ofrecida->value)->where('vence_en', '>', $ahora),
            )
            ->when($desde !== null, fn ($consulta) => $consulta->where('updated_at', '>', Carbon::parse($desde)))
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return $ofertas->map(fn (Oferta $oferta): OfertaParaProveedor => $this->armar($oferta, $ahora))->all();
    }

    /** @throws AsignacionInvalida */
    public function una(int $usuarioId, int $ofertaId, ?Carbon $ahora = null): OfertaParaProveedor
    {
        $proveedor = $this->cuentas->ejecutar($usuarioId);

        $oferta = Oferta::query()->where('id', $ofertaId)->where('proveedor_id', $proveedor->id)->first() ?? throw AsignacionInvalida::ofertaAjena();

        return $this->armar($oferta, $ahora ?? Carbon::now());
    }

    private function armar(Oferta $oferta, Carbon $ahora): OfertaParaProveedor
    {
        $solicitud = $this->solicitudes->datos($oferta->solicitud_id);
        $centro = $solicitud === null ? null : UbicacionDelServicio::de($solicitud);

        return new OfertaParaProveedor(
            $oferta,
            $solicitud,
            // Dos decimales de grado son unos 1,1 km: sirve para decidir, no para llegar al lote.
            $centro === null ? null : ['lat' => number_format($centro['lat'], 2, '.', ''), 'lng' => number_format($centro['lng'], 2, '.', '')],
            $oferta->estado->esViva() ? max(0, (int) $ahora->diffInSeconds($oferta->vence_en, false)) : 0,
        );
    }
}
