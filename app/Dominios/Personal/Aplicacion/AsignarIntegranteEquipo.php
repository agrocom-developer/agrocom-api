<?php

namespace App\Dominios\Personal\Aplicacion;

use App\Dominios\Personal\Dominio\Excepciones\VigenciaEquipoSolapada;
use App\Dominios\Personal\Dominio\ResultadoSolapamientoVigencias;
use App\Dominios\Personal\Dominio\RolEquipo;
use App\Dominios\Personal\Dominio\ValidadorSolapamientoVigencias;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoIntegrante;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;

/**
 * Asigna una persona a un equipo de trabajo, con vigencia (tarea 72, HU-49,
 * ADR 0015 punto 3). La pertenencia a un equipo NO es exclusiva (corrección
 * del dueño del 7/9/2026): una persona vigente en OTRO equipo en fechas que
 * se pisan se guarda igual, con aviso — {@see ValidadorSolapamientoVigencias}.
 * Lo único que se rechaza es la MISMA persona, en el MISMO equipo, con
 * vigencias que se pisan (es la misma fila dos veces).
 */
final class AsignarIntegranteEquipo
{
    /**
     * @throws VigenciaEquipoSolapada si la persona ya integra ESTE equipo en
     *                                una vigencia que se pisa con la nueva.
     */
    public function ejecutar(
        EquipoTrabajo $equipo,
        int $personaId,
        RolEquipo $rol,
        string $desde,
        ?string $hasta,
    ): ResultadoSolapamientoVigencias {
        $vigenciasExistentes = EquipoIntegrante::query()
            ->where('persona_id', $personaId)
            ->get(['equipo_trabajo_id', 'desde', 'hasta'])
            ->map(fn (EquipoIntegrante $fila): array => [
                'equipo_trabajo_id' => $fila->equipo_trabajo_id,
                'desde' => $fila->desde->toDateString(),
                'hasta' => $fila->hasta?->toDateString(),
            ])
            ->all();

        $resultado = ValidadorSolapamientoVigencias::evaluar(
            $vigenciasExistentes,
            ['desde' => $desde, 'hasta' => $hasta],
            $equipo->id,
        );

        if ($resultado->rechazada) {
            $persona = PerPersona::query()->find($personaId);
            $nombrePersona = $persona instanceof PerPersona ? $persona->nombre : "#{$personaId}";

            throw VigenciaEquipoSolapada::integrante($nombrePersona, $desde, $hasta);
        }

        EquipoIntegrante::query()->create([
            'equipo_trabajo_id' => $equipo->id,
            'persona_id' => $personaId,
            'rol_equipo' => $rol->value,
            'desde' => $desde,
            'hasta' => $hasta,
        ]);

        return $resultado;
    }
}
