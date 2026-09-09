<?php

namespace App\Dominios\Operaciones\Aplicacion;

use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Dominio\TipoAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Caso de uso: listado de órdenes de aplicación con filtros (espec §8,
 * `GET /api/ordenes?lote_id=&estado=`). Solo lectura — vive una sola vez acá
 * y lo invocan tanto el controller de API como, cuando exista, el listado
 * del panel (ADR 0008).
 *
 * El soft delete queda excluido por el global scope de Eloquent (ADR 0007):
 * una orden borrada lógicamente no aparece en ningún listado por defecto.
 */
final class ListarOrdenesAplicacion
{
    /**
     * @param  bool  $soloVigentes  Filtro de conveniencia para la app piloto:
     *                              equivale a estado = vigente. Se combina por
     *                              AND con `estado`; si se contradicen, el
     *                              resultado es vacío (a propósito: no hay
     *                              prevalencia silenciosa entre filtros).
     * @return LengthAwarePaginator<int, OrdenAplicacion>
     */
    public function ejecutar(
        ?EstadoOrdenAplicacion $estado = null,
        ?int $loteId = null,
        ?int $contratoId = null,
        ?int $nroAplicacion = null,
        ?TipoAplicacion $tipoAplicacion = null,
        bool $soloVigentes = false,
        int $porPagina = 15,
    ): LengthAwarePaginator {
        return OrdenAplicacion::query()
            ->when($estado !== null, fn ($consulta) => $consulta->where('estado', $estado))
            ->when($soloVigentes, fn ($consulta) => $consulta->where('estado', EstadoOrdenAplicacion::Vigente))
            ->when($loteId !== null, fn ($consulta) => $consulta->where('lote_id', $loteId))
            ->when($contratoId !== null, fn ($consulta) => $consulta->where('contrato_id', $contratoId))
            ->when($nroAplicacion !== null, fn ($consulta) => $consulta->where('nro_aplicacion', $nroAplicacion))
            ->when($tipoAplicacion !== null, fn ($consulta) => $consulta->where('tipo_aplicacion', $tipoAplicacion))
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->paginate($porPagina);
    }
}
