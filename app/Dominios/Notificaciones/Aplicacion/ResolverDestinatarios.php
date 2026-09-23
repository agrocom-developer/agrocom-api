<?php

namespace App\Dominios\Notificaciones\Aplicacion;

use App\Dominios\Notificaciones\Dominio\Destinatario;
use App\Dominios\Personal\Contratos\LecturaEquipoTrabajo;
use App\Dominios\Seguridad\Contratos\LecturaUsuarioDePersona;
use App\Dominios\Seguridad\Contratos\LecturaUsuariosPorRol;

/**
 * Convierte lo que una regla declara (roles, personas, equipos) en las
 * cuentas concretas a las que se reparte el aviso (ADR 0025, punto 3). Es la
 * única clase que sabe qué contrato consultar para cada tipo de destinatario;
 * `Notificaciones` no lee ninguna tabla ajena, todo va por `Contratos/`.
 *
 * - `rol`: toda cuenta interna activa con el rol ASIGNADO, aunque hoy opere
 *   con otro. Una notificación no es un permiso (invariante 10).
 * - `persona`: la cuenta vinculada; sin cuenta o bloqueada, no hay a quién
 *   avisar y no es un error.
 * - `equipo`: los integrantes vigentes en `$fecha` → sus personas → sus
 *   cuentas.
 */
final class ResolverDestinatarios
{
    public function __construct(
        private readonly LecturaUsuariosPorRol $usuariosPorRol,
        private readonly LecturaUsuarioDePersona $usuarioDePersona,
        private readonly LecturaEquipoTrabajo $equipos,
    ) {}

    /**
     * @param  list<Destinatario>  $destinatarios
     * @param  string|null  $fecha  `Y-m-d` en que se evalúa la vigencia de un equipo; hoy si es `null`
     * @return list<int> ids de `sec_user`, sin repetir y en orden ascendente
     */
    public function ejecutar(array $destinatarios, ?string $fecha = null): array
    {
        $fecha ??= now()->toDateString();
        $cuentas = [];

        foreach ($destinatarios as $destinatario) {
            foreach ($this->cuentasDe($destinatario, $fecha) as $usuarioId) {
                $cuentas[$usuarioId] = $usuarioId;
            }
        }

        ksort($cuentas);

        return array_values($cuentas);
    }

    /** @return list<int> */
    private function cuentasDe(Destinatario $destinatario, string $fecha): array
    {
        return match ($destinatario->tipo) {
            Destinatario::ROL => $this->usuariosPorRol->idsConRol((string) $destinatario->referencia),
            Destinatario::PERSONA => $this->cuentaDePersona((int) $destinatario->referencia),
            Destinatario::EQUIPO => $this->cuentasDeEquipo((int) $destinatario->referencia, $fecha),
            default => [],
        };
    }

    /** @return list<int> */
    private function cuentaDePersona(int $personaId): array
    {
        $usuario = $this->usuarioDePersona->dePersona($personaId);

        return $usuario !== null && $usuario->activo ? [$usuario->id] : [];
    }

    /** @return list<int> */
    private function cuentasDeEquipo(int $equipoTrabajoId, string $fecha): array
    {
        $cuentas = [];

        foreach ($this->equipos->integrantesAFecha($equipoTrabajoId, $fecha) as $integrante) {
            foreach ($this->cuentaDePersona($integrante->personaId) as $usuarioId) {
                $cuentas[] = $usuarioId;
            }
        }

        return $cuentas;
    }
}
