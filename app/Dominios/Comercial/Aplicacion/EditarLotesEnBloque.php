<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Aplicacion\Lote\ResultadoEdicionEnBloque;
use App\Dominios\Comercial\Dominio\Excepciones\LoteConHistorialAsociado;
use App\Dominios\Comercial\Dominio\Excepciones\LotesNuevosSinHectareas;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Editar en bloque" los lotes de una propiedad (19/9/2026, pedido directo):
 * corregir de una vez lo que los lotes tienen en común —hectáreas y atributos
 * de terreno— y ajustar CUÁNTOS son, en la misma pantalla con la que se
 * generaron ({@see CrearLotesMasivo}).
 *
 * - Los lotes que quedan reciben los atributos de terreno, y las hectáreas si
 *   vienen (`null` = cada lote conserva las suyas: los lotes ya pueden haber
 *   corregido las suyas dibujando el polígono, y una edición en bloque no las
 *   pisa si no se pidió).
 * - Si `$cantidad` supera los lotes actuales, nacen los que faltan con ese
 *   mismo prefijo, hectáreas y terreno — la numeración sigue donde iba.
 * - Si es menor, se dan de baja los ÚLTIMOS lotes creados (soft delete, vía
 *   {@see EliminarLote}: no hay borrado físico). Un lote con órdenes o
 *   trabajos no se puede dar de baja, y en ese caso no se aplica NADA — todo
 *   corre en una transacción.
 *
 * Los códigos de los lotes que ya existen no se tocan: renombrar sigue siendo
 * cosa de la ficha de cada lote.
 */
final class EditarLotesEnBloque
{
    public function __construct(
        private readonly CrearLotesMasivo $crearLotesMasivo,
        private readonly EliminarLote $eliminarLote,
    ) {}

    /**
     * @param  array{desnivel: string|null, limpieza: string|null, restricciones: string|null}  $atributosTerreno  aplicados a TODOS los lotes, los que quedan y los nuevos.
     *
     * @throws LotesNuevosSinHectareas si hay que crear lotes y no se indicaron hectáreas.
     * @throws LoteConHistorialAsociado si alguno de los lotes a quitar tiene órdenes o trabajos asociados.
     */
    public function ejecutar(Propiedad $propiedad, string $prefijo, int $cantidad, ?string $hectareas, array $atributosTerreno): ResultadoEdicionEnBloque
    {
        // A dos decimales, como se guardan: sin esto "25" contra "25.00" haría
        // pasar por modificado un lote que no cambió.
        $hectareas = $hectareas === null ? null : (string) BigDecimal::of($hectareas)->toScale(2, RoundingMode::HalfUp);

        return DB::transaction(function () use ($propiedad, $prefijo, $cantidad, $hectareas, $atributosTerreno): ResultadoEdicionEnBloque {
            /** @var Collection<int, Lote> $lotes */
            $lotes = $propiedad->lotes()->orderBy('id')->get();
            $actuales = $lotes->count();

            if ($cantidad > $actuales && $hectareas === null) {
                throw LotesNuevosSinHectareas::alSubirLaCantidad();
            }

            $aQuitar = $lotes->sortByDesc('id')->take(max(0, $actuales - $cantidad))->values();

            foreach ($aQuitar as $lote) {
                $this->eliminarLote->ejecutar($lote);
            }

            $actualizados = 0;

            foreach ($lotes->diff($aQuitar) as $lote) {
                $lote->fill([
                    'hectareas' => $hectareas ?? $lote->hectareas,
                    'desnivel' => $atributosTerreno['desnivel'],
                    'limpieza' => $atributosTerreno['limpieza'],
                    'restricciones' => $atributosTerreno['restricciones'],
                ]);

                if ($lote->isDirty()) {
                    $lote->save();
                    $actualizados++;
                }
            }

            $creados = $cantidad > $actuales
                ? count($this->crearLotesMasivo->ejecutar($propiedad, $prefijo, $cantidad - $actuales, (string) $hectareas, $atributosTerreno))
                : 0;

            return new ResultadoEdicionEnBloque($actualizados, $creados, $aQuitar->count());
        });
    }
}
