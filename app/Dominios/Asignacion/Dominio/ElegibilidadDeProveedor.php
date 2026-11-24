<?php

namespace App\Dominios\Asignacion\Dominio;

use Brick\Math\BigDecimal;

/**
 * Reglas de elegibilidad de un proveedor para una ronda (especificación §6,
 * puntos 1 y 7; ADR 0030, punto 5). La aptitud de piloto, dron y documentos
 * la resuelve `Flota`; acá se juntan los hechos que dependen de la solicitud y
 * de la ronda. Pura: recibe los hechos ya resueltos.
 */
final class ElegibilidadDeProveedor
{
    /**
     * @param  bool  $tieneEquipoLibre  al menos un equipo apto, libre en la ventana y, si la solicitud lo exige, en línea.
     * @param  string|null  $distanciaKm  `null` si no se pudo medir.
     * @param  string|null  $radioDeLaRondaKm  `null` en la ronda de propios (sin radio de ronda).
     * @param  bool  $esUltimaRonda  solo en la última ronda se admite a quien no tiene ubicación medible.
     * @return list<MotivoExclusion> vacío si el proveedor es elegible.
     */
    public static function motivosDeExclusion(
        GrupoOferta $grupo,
        bool $tieneEquipoLibre,
        bool $sancionVigente,
        string $calificacionPromedio,
        int $calificacionesConteo,
        string $umbralCalificacion,
        int $minimoCalificaciones,
        ?string $distanciaKm,
        string $radioOperacionKm,
        ?string $radioDeLaRondaKm,
        bool $esUltimaRonda,
    ): array {
        $motivos = [];

        if (! $tieneEquipoLibre) {
            $motivos[] = MotivoExclusion::SinEquipoLibre;
        }

        if ($sancionVigente) {
            $motivos[] = MotivoExclusion::SancionVigente;
        }

        if ($calificacionesConteo >= $minimoCalificaciones && BigDecimal::of($calificacionPromedio)->isLessThan($umbralCalificacion)) {
            $motivos[] = MotivoExclusion::CalificacionBajoUmbral;
        }

        if ($distanciaKm !== null) {
            if (BigDecimal::of($distanciaKm)->isGreaterThan($radioOperacionKm)) {
                $motivos[] = MotivoExclusion::FueraDelRadioDeOperacion;
            }

            if ($grupo === GrupoOferta::Tercero && $radioDeLaRondaKm !== null && BigDecimal::of($distanciaKm)->isGreaterThan($radioDeLaRondaKm)) {
                $motivos[] = MotivoExclusion::FueraDelRadioDeLaRonda;
            }
        } elseif ($grupo === GrupoOferta::Tercero && ! $esUltimaRonda) {
            $motivos[] = MotivoExclusion::SinUbicacion;
        }

        return $motivos;
    }

    /** ¿Recibe oferta en esta ronda? */
    public static function esElegible(
        GrupoOferta $grupo,
        bool $tieneEquipoLibre,
        bool $sancionVigente,
        string $calificacionPromedio,
        int $calificacionesConteo,
        string $umbralCalificacion,
        int $minimoCalificaciones,
        ?string $distanciaKm,
        string $radioOperacionKm,
        ?string $radioDeLaRondaKm,
        bool $esUltimaRonda,
    ): bool {
        return self::motivosDeExclusion(
            $grupo, $tieneEquipoLibre, $sancionVigente, $calificacionPromedio, $calificacionesConteo,
            $umbralCalificacion, $minimoCalificaciones, $distanciaKm, $radioOperacionKm, $radioDeLaRondaKm, $esUltimaRonda,
        ) === [];
    }
}
