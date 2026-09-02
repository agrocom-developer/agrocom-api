<?php

namespace App\Dominios\Operaciones\Aplicacion\MaquinaEstados;

use App\Dominios\Operaciones\Dominio\EstadoAlerta;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Alerta;
use Carbon\CarbonImmutable;

/**
 * Única clase que crea/muta el `estado` de `Alerta` (invariante 7 de
 * CLAUDE.md, `tests/Unit/TransicionesEstadoTest.php`). Ciclo de vida de dos
 * estados, un solo sentido — sin tabla de transiciones/guardas propia bajo
 * `Dominio/MaquinaEstados/` (a diferencia de `Trabajo`/`Sesion`): la única
 * regla es "no reabrir una alerta ya atendida", y esa idempotencia vive acá
 * mismo, mismo criterio que `MaquinaEstadosSesion::validar()` con una sesión
 * ya `validado`.
 *
 * La idempotencia de "no duplicar la misma alerta" (invariante 1, extendida)
 * NO vive acá: es responsabilidad del índice único parcial de `ope_alertas` +
 * el `try/catch` de `GenerarAlertaExcepcion::crear()` — esta clase solo sabe
 * crear con el estado inicial correcto, mismo reparto que
 * `MaquinaEstadosTrabajo::abrir()`.
 */
final class MaquinaEstadosAlerta
{
    /** @param  array<string, mixed>  $atributos  sin `estado`: lo fija esta clase. */
    public function generar(array $atributos): Alerta
    {
        return Alerta::create([...$atributos, 'estado' => EstadoAlerta::Pendiente]);
    }

    /**
     * `atender()` (HU-19, tarea 26): idempotente — si `$alerta` ya está
     * `atendida`, no vuelve a tocar la fila ni a pisar
     * `atendida_por`/`atendida_en` (CA: "reintento sobre una ya atendida →
     * idempotente, no error").
     */
    public function atender(Alerta $alerta, int $personaId): Alerta
    {
        if ($alerta->estado === EstadoAlerta::Atendida) {
            return $alerta;
        }

        $alerta->estado = EstadoAlerta::Atendida;
        $alerta->atendida_por = $personaId;
        $alerta->atendida_en = CarbonImmutable::now();
        $alerta->save();

        return $alerta;
    }
}
