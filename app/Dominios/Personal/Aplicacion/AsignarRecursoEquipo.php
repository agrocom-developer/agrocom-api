<?php

namespace App\Dominios\Personal\Aplicacion;

use App\Dominios\Mantenimiento\Contratos\LecturaEquipamiento;
use App\Dominios\Operaciones\Contratos\LecturaDrones;
use App\Dominios\Personal\Dominio\Excepciones\RecursoEquipoInvalido;
use App\Dominios\Personal\Dominio\Excepciones\VigenciaEquipoSolapada;
use App\Dominios\Personal\Dominio\RecursoTipoEquipo;
use App\Dominios\Personal\Dominio\ResultadoSolapamientoVigencias;
use App\Dominios\Personal\Dominio\ValidadorSolapamientoVigencias;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoRecurso;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;

/**
 * Asigna un dron, un vehículo, un generador o una batería a un equipo de
 * trabajo, con vigencia (tarea 72, HU-49, ADR 0015 punto 3; batería agregada
 * por la tarea "cuadrillas-estadias", 19/9/2026). Mismo criterio de
 * aviso-no-bloqueo que {@see AsignarIntegranteEquipo}: un recurso prestado
 * entre cuadrillas se guarda igual, con aviso; lo que se rechaza es el MISMO
 * recurso, en el MISMO equipo, con vigencias que se pisan.
 *
 * `recurso_id` no tiene FK (`per_equipo_recursos` cruza a `ope_drones` en
 * Operaciones y a `man_vehiculos`/`man_generadores`/`man_baterias` en
 * Mantenimiento, ver el docblock de la migración): la integridad la sostiene
 * ESTE caso de uso, verificando que el recurso exista y esté disponible en su
 * propia tabla ANTES de guardar — es la única guarda que puede reemplazar la
 * FK que esa columna no puede tener.
 *
 * `existeYActivo()` consulta los contratos de lectura de cada módulo dueño
 * ({@see LecturaDrones} en Operaciones, {@see LecturaEquipamiento} en
 * Mantenimiento, ADR 0003 regla 2) — corregido el 19/9/2026: antes hacía
 * `DB::table('ope_drones'|'man_vehiculos'|'man_generadores')` directo, un
 * cruce de módulo por SQL crudo que no dejaba rastro para
 * `ArquitecturaModulosTest` pero violaba la frontera igual.
 */
final class AsignarRecursoEquipo
{
    public function __construct(
        private readonly LecturaDrones $lecturaDrones,
        private readonly LecturaEquipamiento $lecturaEquipamiento,
    ) {}

    /**
     * @throws RecursoEquipoInvalido si el recurso no existe o no está disponible
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
     * Existe y se puede asignar hoy, según el contrato de lectura del módulo
     * dueño de cada tabla.
     */
    private function existeYActivo(RecursoTipoEquipo $tipo, int $recursoId): bool
    {
        return match ($tipo) {
            RecursoTipoEquipo::Dron => $this->lecturaDrones->estaDisponible($recursoId),
            RecursoTipoEquipo::Vehiculo => $this->lecturaEquipamiento->estaDisponible(LecturaEquipamiento::TIPO_VEHICULO, $recursoId),
            RecursoTipoEquipo::Generador => $this->lecturaEquipamiento->estaDisponible(LecturaEquipamiento::TIPO_GENERADOR, $recursoId),
            RecursoTipoEquipo::Bateria => $this->lecturaEquipamiento->estaDisponible(LecturaEquipamiento::TIPO_BATERIA, $recursoId),
        };
    }
}
