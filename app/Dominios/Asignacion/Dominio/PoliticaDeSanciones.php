<?php

namespace App\Dominios\Asignacion\Dominio;

use Brick\Math\BigDecimal;

/**
 * Política de cancelación del proveedor (ADR 0033) y de calificación (ADR
 * 0031) como reglas puras. Ninguna sanción da de baja ni suspende la cuenta:
 * la reincidencia abre una revisión que decide un administrador (política de
 * cancelación, «Cancelación del proveedor»).
 *
 * - Tres cancelaciones en 30 días: advertencia. Cinco: restricción temporal de
 *   ofertas. Reincidir tras una restricción: revisión de suspensión.
 * - Calificación con al menos 10 servicios: bajo 4.0 advertencia y plan de 30
 *   días; bajo 3.7 pasado ese plazo, o bajo 3.3 en cualquier momento,
 *   restricción de ofertas hasta la revisión del administrador.
 * - Tasa de aceptación bajo 60 % en 30 días: advertencia (sin prioridad en la
 *   asignación, que aplica el selector de candidatos).
 */
final class PoliticaDeSanciones
{
    /**
     * @param  array{minimo_conteo: int, advertencia_bajo: string, restriccion_tras_plazo_bajo: string, restriccion_inmediata_bajo: string, plazo_mejora_dias: int}  $calificacion
     * @param  array{advertencia_desde: int, restriccion_desde: int, restriccion_dias: int}  $cancelacion
     * @param  array{umbral: string, minimo_ofertas: int}  $aceptacion
     * @return list<DecisionDeSancion>
     */
    public static function evaluar(HechosDeSancion $hechos, array $calificacion, array $cancelacion, array $aceptacion): array
    {
        $decisiones = [];

        // Cancelaciones.
        $n = $hechos->cancelacionesEnVentana;

        if ($n >= $cancelacion['restriccion_desde']) {
            if (! $hechos->restriccionPorCancelacionesVigente) {
                $decisiones[] = $hechos->hayRestriccionPorCancelacionesReciente
                    ? new DecisionDeSancion(TipoSancion::RevisionSuspension, CausaSancion::Cancelaciones, null)
                    : new DecisionDeSancion(TipoSancion::RestriccionOfertas, CausaSancion::Cancelaciones, $cancelacion['restriccion_dias']);
            }
        } elseif ($n >= $cancelacion['advertencia_desde'] && ! $hechos->advertenciaPorCancelacionesVigente) {
            $decisiones[] = new DecisionDeSancion(TipoSancion::Advertencia, CausaSancion::Cancelaciones, null);
        }

        // Calificación.
        if ($hechos->calificacionesConteo >= $calificacion['minimo_conteo'] && ! $hechos->restriccionPorCalificacionVigente) {
            $promedio = BigDecimal::of($hechos->calificacionPromedio);
            $planVencido = $hechos->diasDesdeAdvertenciaDeCalificacion !== null
                && $hechos->diasDesdeAdvertenciaDeCalificacion >= $calificacion['plazo_mejora_dias'];

            if ($promedio->isLessThan($calificacion['restriccion_inmediata_bajo'])
                || ($planVencido && $promedio->isLessThan($calificacion['restriccion_tras_plazo_bajo']))) {
                $decisiones[] = new DecisionDeSancion(TipoSancion::RestriccionOfertas, CausaSancion::Calificacion, null);
            } elseif ($promedio->isLessThan($calificacion['advertencia_bajo']) && ! $hechos->advertenciaPorCalificacionVigente) {
                $decisiones[] = new DecisionDeSancion(TipoSancion::Advertencia, CausaSancion::Calificacion, null);
            }
        }

        // Aceptación.
        if ($hechos->ofertasRespondidasEnVentana >= $aceptacion['minimo_ofertas']
            && BigDecimal::of($hechos->tasaAceptacion)->isLessThan($aceptacion['umbral'])
            && ! $hechos->advertenciaPorAceptacionVigente) {
            $decisiones[] = new DecisionDeSancion(TipoSancion::Advertencia, CausaSancion::Aceptacion, null);
        }

        return $decisiones;
    }
}
