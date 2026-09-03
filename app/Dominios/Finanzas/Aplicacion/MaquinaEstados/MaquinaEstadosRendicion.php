<?php

namespace App\Dominios\Finanzas\Aplicacion\MaquinaEstados;

use App\Dominios\Finanzas\Dominio\EstadoRendicion;
use App\Dominios\Finanzas\Dominio\Excepciones\RendicionNoAprobable;
use App\Dominios\Finanzas\Dominio\Excepciones\RendicionNoPresentable;
use App\Dominios\Finanzas\Dominio\MaquinaEstados\TransicionesRendicion;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Gasto;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Rendicion;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;

/**
 * Única clase que crea/muta el `estado` de `rendicion` (invariante 7 de
 * CLAUDE.md), mismo criterio que `MaquinaEstadosPlanilla`.
 *
 * Las guardas de PERSONA (¿el aprobador es el mismo jefe de campo que
 * rindió?) no viven acá: esa es `Dominio/PoliticaAprobacionRendicion`,
 * aplicada por `Aplicacion/AprobarRendicion` ANTES de invocar `aprobar()` —
 * mismo reparto que `ValidarSesion` frente a `MaquinaEstadosSesion`. La única
 * guarda que SÍ vive acá es "¿tiene al menos un gasto asociado?" en
 * `presentar()`: es una guarda de DATOS propios de la rendición (no de
 * persona ni de permiso), mismo criterio que `MaquinaEstadosContrato::activar()`.
 *
 * `monto` se recalcula en cada transición (`presentar()` y `aprobar()`) como
 * la suma de los `fin_gastos.monto` asociados, con `Brick\Math\BigDecimal`
 * (invariante 6 de CLAUDE.md: nunca floats ni `array_sum()`) — nunca leído a
 * mano ni acumulado incrementalmente en `Aplicacion/AsociarGastoARendicion`,
 * así que un gasto asociado y luego dado de baja (soft delete) no deja
 * residuo: la próxima transición que recalcule vuelve a sumar solo lo vivo.
 */
final class MaquinaEstadosRendicion
{
    /**
     * @param  array<string, mixed>  $atributos  sin `estado` ni `monto`: los fija esta clase.
     */
    public function generar(array $atributos): Rendicion
    {
        return Rendicion::query()->create([
            ...$atributos,
            'estado' => EstadoRendicion::Abierta,
            'monto' => '0.00',
        ]);
    }

    /**
     * @throws RendicionNoPresentable si `$rendicion` no está `Abierta` o no tiene gastos asociados.
     */
    public function presentar(Rendicion $rendicion): Rendicion
    {
        $desde = $rendicion->estado;
        $hasta = EstadoRendicion::Presentada;

        if (! TransicionesRendicion::permitida($desde, $hasta)) {
            throw RendicionNoPresentable::porTransicionInvalida($rendicion->id, $desde);
        }

        $gastos = Gasto::query()->where('rendicion_id', $rendicion->id)->get();

        if ($gastos->isEmpty()) {
            throw RendicionNoPresentable::porSinGastosAsociados($rendicion->id);
        }

        $rendicion->estado = $hasta;
        $rendicion->monto = (string) $this->sumarMontos($gastos);
        $rendicion->save();

        return $rendicion;
    }

    /**
     * @throws RendicionNoAprobable si `$rendicion` no está `Presentada`.
     */
    public function aprobar(Rendicion $rendicion, int $aprobadorPersonaId): Rendicion
    {
        $desde = $rendicion->estado;
        $hasta = EstadoRendicion::Aprobada;

        if (! TransicionesRendicion::permitida($desde, $hasta)) {
            throw RendicionNoAprobable::porTransicionInvalida($rendicion->id, $desde);
        }

        $gastos = Gasto::query()->where('rendicion_id', $rendicion->id)->get();

        $rendicion->estado = $hasta;
        $rendicion->monto = (string) $this->sumarMontos($gastos);
        $rendicion->aprobado_por = $aprobadorPersonaId;
        $rendicion->save();

        return $rendicion;
    }

    /** @param  Collection<int, Gasto>  $gastos */
    private function sumarMontos(Collection $gastos): BigDecimal
    {
        return $gastos->reduce(
            fn (BigDecimal $acumulado, Gasto $gasto): BigDecimal => $acumulado->plus($gasto->monto),
            BigDecimal::of('0.00'),
        )->toScale(2, RoundingMode::HalfUp);
    }
}
