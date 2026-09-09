<?php

namespace App\Dominios\Personal\Aplicacion;

use App\Dominios\Personal\Dominio\Excepciones\RecursoEquipoInvalido;
use App\Dominios\Personal\Dominio\Excepciones\VigenciaEquipoSolapada;
use App\Dominios\Personal\Dominio\RecursoTipoEquipo;
use App\Dominios\Personal\Dominio\ResultadoSolapamientoVigencias;
use App\Dominios\Personal\Dominio\ValidadorSolapamientoVigencias;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoRecurso;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;
use Illuminate\Support\Facades\DB;

/**
 * Asigna un dron, un vehículo o un generador a un equipo de trabajo, con
 * vigencia (tarea 72, HU-49, ADR 0015 punto 3). Mismo criterio de aviso-no-
 * bloqueo que {@see AsignarIntegranteEquipo}: un recurso prestado entre
 * cuadrillas se guarda igual, con aviso; lo que se rechaza es el MISMO
 * recurso, en el MISMO equipo, con vigencias que se pisan.
 *
 * `recurso_id` no tiene FK (`per_equipo_recursos` cruza a `ope_drones` en
 * Operaciones y a `man_vehiculos`/`man_generadores` en Mantenimiento, ver el
 * docblock de la migración): la integridad la sostiene ESTE caso de uso,
 * verificando que el recurso exista y esté activo en su propia tabla ANTES
 * de guardar — es la única guarda que puede reemplazar la FK que esa columna
 * no puede tener.
 */
final class AsignarRecursoEquipo
{
    /**
     * @throws RecursoEquipoInvalido si el recurso no existe o no está activo
     *                               en la tabla que le corresponde.
     * @throws VigenciaEquipoSolapada si el recurso ya está asignado a ESTE
     *                                equipo en una vigencia que se pisa.
     */
    public function ejecutar(
        EquipoTrabajo $equipo,
        RecursoTipoEquipo $tipo,
        int $recursoId,
        string $desde,
        ?string $hasta,
    ): ResultadoSolapamientoVigencias {
        if (! $this->existeYActivo($tipo, $recursoId)) {
            throw RecursoEquipoInvalido::porTipoYId($tipo, $recursoId);
        }

        $vigenciasExistentes = EquipoRecurso::query()
            ->where('recurso_tipo', $tipo->value)
            ->where('recurso_id', $recursoId)
            ->get(['equipo_trabajo_id', 'desde', 'hasta'])
            ->map(fn (EquipoRecurso $fila): array => [
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
            throw VigenciaEquipoSolapada::recurso("{$tipo->value} #{$recursoId}", $desde, $hasta);
        }

        EquipoRecurso::query()->create([
            'equipo_trabajo_id' => $equipo->id,
            'recurso_tipo' => $tipo->value,
            'recurso_id' => $recursoId,
            'desde' => $desde,
            'hasta' => $hasta,
        ]);

        return $resultado;
    }

    /**
     * Existe (viva, no borrada lógicamente) y activa. `ope_drones` no tiene
     * columna `estado` (catálogo deliberadamente mínimo, ver su migración) —
     * para un dron "activo" es sinónimo de "no borrado". `man_vehiculos`/
     * `man_generadores` sí la tienen, y ahí "activo" es el valor explícito
     * (`taller`/`de_baja` no habilitan la asignación).
     */
    private function existeYActivo(RecursoTipoEquipo $tipo, int $recursoId): bool
    {
        return match ($tipo) {
            RecursoTipoEquipo::Dron => DB::table('ope_drones')
                ->where('id', $recursoId)
                ->whereNull('deleted_at')
                ->exists(),
            RecursoTipoEquipo::Vehiculo => DB::table('man_vehiculos')
                ->where('id', $recursoId)
                ->whereNull('deleted_at')
                ->where('estado', 'activo')
                ->exists(),
            RecursoTipoEquipo::Generador => DB::table('man_generadores')
                ->where('id', $recursoId)
                ->whereNull('deleted_at')
                ->where('estado', 'activo')
                ->exists(),
        };
    }
}
