<?php

namespace App\Dominios\Comercial\Aplicacion\MaquinaEstados;

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Dominio\Excepciones\ActivacionContratoNoDisponible;
use App\Dominios\Comercial\Dominio\Excepciones\TransicionContratoNoPermitida;
use App\Dominios\Comercial\Dominio\MaquinaEstados\TransicionesContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use Carbon\CarbonImmutable;

/**
 * Única clase que crea/muta el `estado` de `contrato` (invariante 7 de
 * CLAUDE.md), mismo criterio que `MaquinaEstadosActa`/`MaquinaEstadosTrabajo`.
 *
 * Las guardas de AUTORIZACIÓN ("¿quién puede cambiar este estado?") no viven
 * acá — igual que `MaquinaEstadosSesion::validar()` no decide "validador ≠
 * piloto" (eso es `Aplicacion/ValidarSesion`). La guarda de `activar()` sí
 * vive acá porque es de DATOS del propio contrato (¿su fecha de inicio ya
 * pasó?), no de quién ejecuta la acción: no hay ningún otro caso de uso que
 * necesite evaluarla antes de invocar la transición.
 */
final class MaquinaEstadosContrato
{
    /**
     * @param  array<string, mixed>  $atributos  sin `estado`: lo fija esta clase.
     */
    public function crear(array $atributos): Contrato
    {
        return Contrato::create([...$atributos, 'estado' => EstadoContrato::Borrador]);
    }

    /**
     * `borrador → vigente` (HU-23, tarea 34). Una guarda de negocio —
     * decisión razonable a falta de una regla más específica en la
     * especificación funcional, documentada acá porque es donde se aplica:
     *
     * - **`fecha_inicio` no en el pasado**: vigenciar retroactivamente un
     *   contrato cuya vigencia ya debería haber empezado no tiene sentido de
     *   negocio — lo razonable es corregir la fecha antes de activar, no
     *   activar tarde.
     *
     * Hasta la tarea 70 (HU-47) había una segunda guarda ("al menos una
     * ventana horaria cargada"): se retiró por pedido explícito del dueño
     * del 7/9/2026 — cero ventanas es un contrato válido de "día completo",
     * no un contrato incompleto.
     *
     * @throws TransicionContratoNoPermitida si `$contrato` no está `borrador`.
     * @throws ActivacionContratoNoDisponible si `fecha_inicio` ya pasó.
     */
    public function activar(Contrato $contrato): Contrato
    {
        $desde = $contrato->estado;
        $hasta = EstadoContrato::Vigente;

        if (! TransicionesContrato::permitida($desde, $hasta)) {
            throw TransicionContratoNoPermitida::entre($desde, $hasta);
        }

        if ($contrato->fecha_inicio->startOfDay()->lt(CarbonImmutable::today())) {
            throw ActivacionContratoNoDisponible::porFechaInicioEnElPasado($contrato->id, $contrato->fecha_inicio);
        }

        $contrato->estado = $hasta;
        $contrato->save();

        return $contrato;
    }

    /**
     * `vigente → finalizado` (HU-23, tarea 34). Sin guarda adicional: el
     * cierre real por consumo de hectáreas es de otro dominio
     * (`Operaciones`), que todavía no dispara esta transición.
     *
     * @throws TransicionContratoNoPermitida si `$contrato` no está `vigente`.
     */
    public function finalizar(Contrato $contrato): Contrato
    {
        return $this->transicionar($contrato, EstadoContrato::Finalizado);
    }

    /**
     * `borrador → cancelado` o `vigente → cancelado` (HU-23, tarea 34): baja
     * anticipada. Sin guarda adicional — cancelar es siempre una salida
     * disponible desde cualquier estado no terminal.
     *
     * @throws TransicionContratoNoPermitida si `$contrato` ya está `finalizado` o `cancelado`.
     */
    public function cancelar(Contrato $contrato): Contrato
    {
        return $this->transicionar($contrato, EstadoContrato::Cancelado);
    }

    /**
     * `vigente → pausado` (HU-71, tarea 87): interrupción del contrato
     * vigente, no una cancelación. Sin guarda adicional — pausar es una
     * decisión del encargado, no depende de fechas como `activar()`.
     *
     * @throws TransicionContratoNoPermitida si `$contrato` no está `vigente`.
     */
    public function pausar(Contrato $contrato): Contrato
    {
        return $this->transicionar($contrato, EstadoContrato::Pausado);
    }

    /**
     * `pausado → vigente` (HU-71, tarea 87): vuelta de una interrupción. Sin
     * guarda adicional, mismo criterio que `pausar()`.
     *
     * @throws TransicionContratoNoPermitida si `$contrato` no está `pausado`.
     */
    public function reanudar(Contrato $contrato): Contrato
    {
        return $this->transicionar($contrato, EstadoContrato::Vigente);
    }

    /**
     * Punto de entrada único para `Aplicacion/CambiarEstadoContrato` (HTTP):
     * resuelve a qué método de transición corresponde `$hacia` sin que el
     * llamador tenga que conocer el nombre de cada uno. `Borrador` nunca es
     * un destino válido — ningún estado lo admite en la tabla de
     * transiciones, así que cae al camino genérico y siempre rechaza.
     *
     * `Vigente` como destino tiene dos orígenes posibles con semántica
     * distinta (HU-71, tarea 87): desde `borrador` es `activar()`, con su
     * guarda de `fecha_inicio` no en el pasado; desde `pausado` es
     * `reanudar()`, sin esa guarda — un contrato que ya estuvo vigente y se
     * pausó casi siempre tiene `fecha_inicio` en el pasado, así que
     * aplicarle la guarda de `activar()` lo dejaría sin poder reanudarse
     * nunca. Por eso se distingue por el estado ACTUAL de `$contrato`, antes
     * del `match` por destino.
     *
     * @throws TransicionContratoNoPermitida si la transición no está en la tabla.
     * @throws ActivacionContratoNoDisponible si el destino es `vigente` desde `borrador` y falta alguna guarda.
     */
    public function cambiarA(Contrato $contrato, EstadoContrato $hacia): Contrato
    {
        if ($hacia === EstadoContrato::Vigente && $contrato->estado === EstadoContrato::Pausado) {
            return $this->reanudar($contrato);
        }

        return match ($hacia) {
            EstadoContrato::Vigente => $this->activar($contrato),
            EstadoContrato::Finalizado => $this->finalizar($contrato),
            EstadoContrato::Cancelado => $this->cancelar($contrato),
            EstadoContrato::Pausado => $this->pausar($contrato),
            EstadoContrato::Borrador => $this->transicionar($contrato, $hacia),
        };
    }

    /** @throws TransicionContratoNoPermitida si la transición no está permitida. */
    private function transicionar(Contrato $contrato, EstadoContrato $hasta): Contrato
    {
        $desde = $contrato->estado;

        if (! TransicionesContrato::permitida($desde, $hasta)) {
            throw TransicionContratoNoPermitida::entre($desde, $hasta);
        }

        $contrato->estado = $hasta;
        $contrato->save();

        return $contrato;
    }
}
