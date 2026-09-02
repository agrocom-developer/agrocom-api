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
 * vive acá porque es de DATOS del propio contrato (¿tiene ventanas
 * cargadas?, ¿su fecha de inicio ya pasó?), no de quién ejecuta la acción: no
 * hay ningún otro caso de uso que necesite evaluarla antes de invocar la
 * transición.
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
     * `borrador → vigente` (HU-23, tarea 34). Dos guardas de negocio —
     * decisión razonable a falta de una regla más específica en la
     * especificación funcional, documentada acá porque es donde se aplica:
     *
     * - **Al menos una ventana horaria cargada**: un contrato vigente sin
     *   ventanas no tiene cuándo aplicar — el criterio de aceptación del
     *   plan de sprints ("no permite ventana fuera del rango del contrato")
     *   presupone que existe al menos una.
     * - **`fecha_inicio` no en el pasado**: vigenciar retroactivamente un
     *   contrato cuya vigencia ya debería haber empezado no tiene sentido de
     *   negocio — lo razonable es corregir la fecha antes de activar, no
     *   activar tarde.
     *
     * @throws TransicionContratoNoPermitida si `$contrato` no está `borrador`.
     * @throws ActivacionContratoNoDisponible si falta alguna de las dos guardas.
     */
    public function activar(Contrato $contrato): Contrato
    {
        $desde = $contrato->estado;
        $hasta = EstadoContrato::Vigente;

        if (! TransicionesContrato::permitida($desde, $hasta)) {
            throw TransicionContratoNoPermitida::entre($desde, $hasta);
        }

        if ($contrato->ventanas()->count() === 0) {
            throw ActivacionContratoNoDisponible::porFaltaDeVentanas($contrato->id);
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
     * Punto de entrada único para `Aplicacion/CambiarEstadoContrato` (HTTP):
     * resuelve a qué método de transición corresponde `$hacia` sin que el
     * llamador tenga que conocer el nombre de cada uno. `Borrador` nunca es
     * un destino válido — ningún estado lo admite en la tabla de
     * transiciones, así que cae al camino genérico y siempre rechaza.
     *
     * @throws TransicionContratoNoPermitida si la transición no está en la tabla.
     * @throws ActivacionContratoNoDisponible si el destino es `vigente` y falta alguna guarda.
     */
    public function cambiarA(Contrato $contrato, EstadoContrato $hacia): Contrato
    {
        return match ($hacia) {
            EstadoContrato::Vigente => $this->activar($contrato),
            EstadoContrato::Finalizado => $this->finalizar($contrato),
            EstadoContrato::Cancelado => $this->cancelar($contrato),
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
