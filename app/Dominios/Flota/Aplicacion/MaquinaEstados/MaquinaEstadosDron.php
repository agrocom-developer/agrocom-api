<?php

namespace App\Dominios\Flota\Aplicacion\MaquinaEstados;

use App\Dominios\Compartido\Dominio\AccionAprobacion;
use App\Dominios\Compartido\Dominio\DecisionDeAprobacion;
use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Compartido\Dominio\Excepciones\MotivoRequerido;
use App\Dominios\Compartido\Dominio\Excepciones\TransicionDeAprobacionInvalida;
use App\Dominios\Compartido\Dominio\TransicionesAprobacion;
use App\Dominios\Flota\Infraestructura\Eloquent\Dron;
use Illuminate\Support\Carbon;

/**
 * Única clase que crea y cambia el `estado` de aprobación de un dron
 * (invariante 7 de CLAUDE.md; ADR 0029 y 0032): consulta la tabla de
 * {@see TransicionesAprobacion} antes de escribir. Un dron nace `pendiente`.
 * Mismo reparto en dos tiempos que `MaquinaEstadosAprobacion` de Proveedores:
 * `destino()` valida, el caso de uso verifica los documentos, `aplicar()`
 * escribe.
 */
final class MaquinaEstadosDron
{
    /** @param  array<string, mixed>  $atributos  sin `estado`: lo fija esta clase. */
    public function registrar(array $atributos): Dron
    {
        return Dron::query()->create([...$atributos, 'estado' => EstadoAprobacion::Pendiente]);
    }

    /**
     * @throws TransicionDeAprobacionInvalida si la acción no corresponde al estado actual.
     * @throws MotivoRequerido si rechazar, suspender o dar de baja llega sin motivo.
     */
    public function destino(Dron $dron, AccionAprobacion $accion, ?string $motivo): EstadoAprobacion
    {
        return DecisionDeAprobacion::validar($dron->estado, $accion, $motivo);
    }

    public function aplicar(Dron $dron, AccionAprobacion $accion, EstadoAprobacion $destino, ?int $actorUsuarioId, ?string $motivo): void
    {
        $dron->estado = $destino;
        $dron->motivo_estado = DecisionDeAprobacion::motivoAGuardar($accion, $motivo);

        if ($accion === AccionAprobacion::Aprobar) {
            $dron->aprobado_por = $actorUsuarioId;
            $dron->aprobado_en = Carbon::now();
        }

        $dron->save();
    }
}
