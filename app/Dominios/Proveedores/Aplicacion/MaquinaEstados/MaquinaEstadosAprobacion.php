<?php

namespace App\Dominios\Proveedores\Aplicacion\MaquinaEstados;

use App\Dominios\Compartido\Dominio\AccionAprobacion;
use App\Dominios\Compartido\Dominio\DecisionDeAprobacion;
use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Compartido\Dominio\Excepciones\MotivoRequerido;
use App\Dominios\Compartido\Dominio\Excepciones\TransicionDeAprobacionInvalida;
use App\Dominios\Compartido\Dominio\TransicionesAprobacion;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Piloto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Proveedor;
use Illuminate\Support\Carbon;

/**
 * Única clase que crea y cambia el `estado` de aprobación de un proveedor
 * o piloto (invariante 7 de CLAUDE.md; ADR 0032): consulta la tabla
 * de {@see TransicionesAprobacion} (a través de {@see DecisionDeAprobacion})
 * antes de escribir. Todo nace `pendiente`; el estado nunca llega de un
 * formulario.
 *
 * La transición se resuelve en dos tiempos porque aprobar exige, además de la
 * tabla, que los documentos obligatorios estén vigentes: `destino()` valida y
 * `aplicar()` escribe, y el caso de uso mete la verificación documental en el
 * medio.
 */
final class MaquinaEstadosAprobacion
{
    /** @param  array<string, mixed>  $atributos  sin `estado`: lo fija esta clase. */
    public function registrarProveedor(array $atributos): Proveedor
    {
        return Proveedor::query()->create([...$atributos, 'estado' => EstadoAprobacion::Pendiente]);
    }

    /** @param  array<string, mixed>  $atributos  sin `estado`: lo fija esta clase. */
    public function registrarPiloto(array $atributos): Piloto
    {
        return Piloto::query()->create([...$atributos, 'estado' => EstadoAprobacion::Pendiente]);
    }

    /**
     * Estado al que llevaría la acción, sin escribir nada.
     *
     * @throws TransicionDeAprobacionInvalida si la acción no corresponde al estado actual.
     * @throws MotivoRequerido si rechazar, suspender o dar de baja llega sin motivo.
     */
    public function destino(Proveedor|Piloto $sujeto, AccionAprobacion $accion, ?string $motivo): EstadoAprobacion
    {
        return DecisionDeAprobacion::validar($sujeto->estado, $accion, $motivo);
    }

    /** Escribe el estado que devolvió {@see destino()}. Aprobar deja quién y cuándo. */
    public function aplicar(Proveedor|Piloto $sujeto, AccionAprobacion $accion, EstadoAprobacion $destino, ?int $actorUsuarioId, ?string $motivo): void
    {
        $sujeto->estado = $destino;
        $sujeto->motivo_estado = DecisionDeAprobacion::motivoAGuardar($accion, $motivo);

        if ($accion === AccionAprobacion::Aprobar) {
            $sujeto->aprobado_por = $actorUsuarioId;
            $sujeto->aprobado_en = Carbon::now();
        }

        $sujeto->save();
    }
}
