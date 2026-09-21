<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Campania\Contratos\DatosCampania;
use App\Dominios\Campania\Contratos\LecturaCampania;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cultivo;
use App\Dominios\Comercial\Infraestructura\Eloquent\LoteCampania;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Resumen relacionado de un cultivo (tarea 120, §6.3.1 de la guía de
 * pantalla): en qué lotes y propiedades está sembrado en la campaña vigente.
 * Solo lectura.
 *
 * El cultivo no es atributo del lote sino dato de la siembra dentro de una
 * campaña (`com_lote_campania`, ADR 0015 punto 4), así que la pregunta
 * «¿dónde está?» siempre es «¿dónde está en tal campaña?». «Vigente» es la
 * campaña `abierta` (ADR 0015 punto 1): la que admite imputaciones. Cuáles
 * son lo dice `Campania` por su contrato de lectura ({@see LecturaCampania});
 * lotes y propiedades son de este mismo módulo. Sin campaña abierta no hay
 * nada que resumir, y el resumen lo dice en vez de caer a otra campaña.
 *
 * Las sumas son `BigDecimal`, nunca `float` (invariante 6). Una siembra cuyo
 * lote o propiedad está dado de baja no cuenta: esos registros ya no existen
 * para el resto del panel.
 */
final class ResumirSiembraDeCultivo
{
    public function __construct(private readonly LecturaCampania $lecturaCampania) {}

    public function ejecutar(Cultivo $cultivo): ResumenSiembraDeCultivo
    {
        $campanias = $this->lecturaCampania->abiertas();

        if ($campanias === []) {
            return new ResumenSiembraDeCultivo([], 0, '0.00', []);
        }

        $siembras = LoteCampania::query()
            ->where('cultivo_id', $cultivo->id)
            ->whereIn('campania_id', array_map(fn (DatosCampania $campania): int => $campania->id, $campanias))
            ->whereHas('lote.propiedad')
            ->with('lote.propiedad:id,nombre')
            ->get();

        $total = BigDecimal::zero();
        $porPropiedad = [];

        foreach ($siembras as $siembra) {
            $propiedad = $siembra->lote->propiedad;
            $hectareas = BigDecimal::of($siembra->hectareas_sembradas);

            $total = $total->plus($hectareas);

            $acumulado = $porPropiedad[$propiedad->id] ?? ['nombre' => $propiedad->nombre, 'lotes' => 0, 'hectareas' => BigDecimal::zero()];
            $acumulado['lotes']++;
            $acumulado['hectareas'] = $acumulado['hectareas']->plus($hectareas);
            $porPropiedad[$propiedad->id] = $acumulado;
        }

        // Más hectáreas primero; a igualdad, por nombre, para que el orden no dependa de la consulta.
        uasort($porPropiedad, fn (array $a, array $b): int => $b['hectareas']->compareTo($a['hectareas']) ?: strcmp($a['nombre'], $b['nombre']));

        return new ResumenSiembraDeCultivo(
            campanias: array_map(fn (DatosCampania $campania): string => $campania->codigo, $campanias),
            lotesSembrados: $siembras->count(),
            hectareasSembradas: (string) $total->toScale(2, RoundingMode::HalfUp),
            propiedades: array_values(array_map(
                fn (array $fila): array => [
                    'nombre' => $fila['nombre'],
                    'lotes' => $fila['lotes'],
                    'hectareas' => (string) $fila['hectareas']->toScale(2, RoundingMode::HalfUp),
                ],
                $porPropiedad,
            )),
        );
    }
}
