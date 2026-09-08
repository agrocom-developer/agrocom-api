<?php

namespace App\Dominios\Comercial\Aplicacion\Siembra;

use App\Dominios\Comercial\Dominio\Excepciones\HectareasSembradasSuperanLote;
use App\Dominios\Comercial\Dominio\Excepciones\SiembraDuplicada;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\LoteCampania;
use Brick\Math\BigDecimal;
use Illuminate\Database\QueryException;

/**
 * Guardado de una siembra (`com_lote_campania`) con sus dos guardas de la
 * tarea 71 (etapa 2): traducción del índice único parcial
 * `com_lote_campania_lote_campania_unico` a {@see SiembraDuplicada}, y el
 * tope de hectáreas contra el propio lote. Mismo criterio que
 * `Lote\GuardadoLote` — un único colaborador estático, para que
 * `GuardarSiembraCampania` no lo duplique en su bucle.
 */
final class GuardarSiembra
{
    /**
     * @param  array{cultivo_id: int, hectareas_sembradas: string, fecha_siembra: string|null, fecha_cosecha_estimada: string|null}  $datos
     *
     * @throws HectareasSembradasSuperanLote si las hectáreas sembradas superan las del lote.
     * @throws SiembraDuplicada si el lote ya tiene una siembra vigente en esa campaña.
     */
    public static function guardar(LoteCampania $siembra, Lote $lote, array $datos): LoteCampania
    {
        self::verificarHectareas($lote, $datos['hectareas_sembradas']);

        $siembra->fill($datos);

        try {
            $siembra->save();
        } catch (QueryException $excepcion) {
            self::relanzarComoDuplicada($excepcion, $lote->codigo);
        }

        return $siembra;
    }

    /** @throws HectareasSembradasSuperanLote */
    private static function verificarHectareas(Lote $lote, string $hectareasSembradas): void
    {
        if (BigDecimal::of($hectareasSembradas)->isGreaterThan(BigDecimal::of($lote->hectareas))) {
            throw HectareasSembradasSuperanLote::paraLote($lote->codigo, $hectareasSembradas, (string) $lote->hectareas);
        }
    }

    /**
     * @throws SiembraDuplicada si la violación corresponde al índice único de lote+campaña.
     * @throws QueryException si la violación no es la contemplada.
     */
    private static function relanzarComoDuplicada(QueryException $excepcion, string $codigoLote): never
    {
        $mensaje = $excepcion->getMessage();

        if (str_contains($mensaje, 'com_lote_campania_lote_campania_unico') || str_contains($mensaje, 'com_lote_campania.campania_id')) {
            throw SiembraDuplicada::paraLote($codigoLote);
        }

        throw $excepcion;
    }
}
