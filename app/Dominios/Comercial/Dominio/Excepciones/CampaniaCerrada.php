<?php

namespace App\Dominios\Comercial\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Guarda central de HU-46 (ADR 0015 punto 1, corregido el 8/9/2026): ningún
 * contrato nuevo puede imputarse a una campaña `cerrada` — sin esto el
 * modelo permite seguir cargando compromisos sobre un ciclo productivo ya
 * liquidado. La verifican `Aplicacion/CrearContrato` y
 * `Aplicacion/ActualizarContrato` vía `Campania\Contratos\LecturaCampania`
 * (ADR 0003 regla 2: la lógica de qué estado admite imputaciones es de
 * `Campania`, esta excepción es la reacción de `Comercial` ante un `false`) —
 * mismo criterio que su guarda hermana {@see CampaniaDeOtroCliente}.
 */
final class CampaniaCerrada extends RuntimeException
{
    public static function paraCampania(string $codigo): self
    {
        return new self(Texto::de('comercial.errores.campania_cerrada', ['codigo' => $codigo]));
    }
}
