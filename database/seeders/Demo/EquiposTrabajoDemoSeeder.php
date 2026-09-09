<?php

namespace Database\Seeders\Demo;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Generador;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Vehiculo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Dron;
use App\Dominios\Personal\Dominio\EstadoEquipoTrabajo;
use App\Dominios\Personal\Dominio\RecursoTipoEquipo;
use App\Dominios\Personal\Dominio\RolEquipo;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoIntegrante;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoRecurso;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use Illuminate\Database\Seeder;

/**
 * Dos cuadrillas (tarea 72, HU-49, ADR 0015 punto 3): piloto, auxiliar y el
 * equipamiento que tienen asignado — dron, vehículo y generador — no "dónde
 * están trabajando".
 *
 * "De la campaña `2025-2026`" (como pide el enunciado de la tarea) es, acá,
 * solo que la vigencia de ambos equipos ARRANCA dentro del rango de esa
 * campaña (`2025-07-01` a `2026-06-30`, sembrada por
 * {@see NucleoComercialSeeder}) y sigue abierta (`hasta = null`): no existe
 * ninguna FK ni filtro que ate el equipo a esa campaña, porque
 * `per_equipos_trabajo` no tiene `campania_id` — el equipo es de Agrocom y
 * trabaja para los clientes que hagan falta en la misma semana (corrección
 * del dueño del 8/9/2026). Un equipo de trabajo no "termina" cuando termina
 * una campaña ajena.
 *
 * Los dos generadores (`GEN-01`, `GEN-02`) se siembran ACÁ y no en
 * `FlotaDemoSeeder`: `man_generadores` es una tabla de catálogo cuyo único
 * consumidor hoy es la asignación a un equipo (ver el docblock de su
 * migración) — sin un equipo al que asignarlos no había ninguna razón para
 * que existieran antes de esta tarea.
 *
 * Escribe por los modelos Eloquent de cada módulo (regla dura del ADR 0012),
 * no por los casos de uso (`CrearEquipoTrabajo`/`AsignarIntegranteEquipo`/
 * `AsignarRecursoEquipo`): esos casos de uso dependen de `Auth::id()` para la
 * autoría (vía `RegistraAutoria`) y en un seeder no hay usuario autenticado,
 * mismo criterio que el resto de la familia demo. Los datos ya nacen
 * consistentes (código único, sin solapes, recursos existentes y activos),
 * así que las guardas de esos casos de uso no hacen falta acá.
 *
 * Idempotente: si el primer equipo (`EQ-01`) ya existe, no hace nada.
 */
class EquiposTrabajoDemoSeeder extends Seeder
{
    private const CODIGO_CENTINELA = 'EQ-01';

    /** Arranca dentro de la campaña `2025-2026` sembrada por {@see NucleoComercialSeeder}. */
    private const VIGENCIA_DESDE = '2025-08-01';

    public function run(): void
    {
        if (EquipoTrabajo::query()->where('codigo', self::CODIGO_CENTINELA)->exists()) {
            return;
        }

        $autorId = PersonalDemoSeeder::autorId();

        /** @var array<string, int> $bases */
        $bases = PerBase::query()->pluck('id', 'nombre')->all();
        $cuatroCanadas = $bases['Cuatro Cañadas'] ?? null;
        $pailon = $bases['Pailón'] ?? $cuatroCanadas;

        if ($cuatroCanadas === null) {
            return; // sin bases sembradas no hay dónde asignar la cuadrilla
        }

        /** @var array<string, int> $personas */
        $personas = PerPersona::query()->pluck('id', 'nombre')->all();
        /** @var array<string, int> $drones */
        $drones = Dron::query()->pluck('id', 'identificador')->all();
        /** @var array<string, int> $vehiculos */
        $vehiculos = Vehiculo::query()->pluck('id', 'identificador')->all();

        if ($personas === [] || $drones === [] || $vehiculos === []) {
            return; // sin cuadrilla ni flota sembradas no hay con qué armar un equipo
        }

        $generadores = $this->generadores($autorId, $cuatroCanadas, (int) $pailon);

        $equipoUno = $this->crear(new EquipoTrabajo([
            'codigo' => 'EQ-01',
            'nombre' => 'Cuadrilla Cuatro Cañadas',
            'base_id' => $cuatroCanadas,
            'estado' => EstadoEquipoTrabajo::Activo,
            'desde' => self::VIGENCIA_DESDE,
            'hasta' => null,
        ]), $autorId);

        $this->integrante($equipoUno, $personas['Josue Haenke'], RolEquipo::Piloto, $autorId);
        $this->integrante($equipoUno, $personas['David Omar Ríos Lino'], RolEquipo::Auxiliar, $autorId);
        $this->recurso($equipoUno, RecursoTipoEquipo::Dron, $drones['AG-02'], $autorId);
        $this->recurso($equipoUno, RecursoTipoEquipo::Vehiculo, $vehiculos['CAM-01'], $autorId);
        $this->recurso($equipoUno, RecursoTipoEquipo::Generador, $generadores['GEN-01'], $autorId);

        $equipoDos = $this->crear(new EquipoTrabajo([
            'codigo' => 'EQ-02',
            'nombre' => 'Cuadrilla Pailón',
            'base_id' => (int) $pailon,
            'estado' => EstadoEquipoTrabajo::Activo,
            'desde' => self::VIGENCIA_DESDE,
            'hasta' => null,
        ]), $autorId);

        $this->integrante($equipoDos, $personas['Miguelito Justiniano Dorado'], RolEquipo::Piloto, $autorId);
        $this->integrante($equipoDos, $personas['Abraham Gutiérrez Contreras'], RolEquipo::Auxiliar, $autorId);
        $this->recurso($equipoDos, RecursoTipoEquipo::Dron, $drones['AG-04'], $autorId);
        $this->recurso($equipoDos, RecursoTipoEquipo::Vehiculo, $vehiculos['CAM-03'], $autorId);
        $this->recurso($equipoDos, RecursoTipoEquipo::Generador, $generadores['GEN-02'], $autorId);
    }

    /**
     * @return array<string, int> identificador → id
     */
    private function generadores(int $autorId, int $cuatroCanadas, int $pailon): array
    {
        $catalogo = [
            ['GEN-01', 'Honda EU70is', $cuatroCanadas, '38.50'],
            ['GEN-02', 'Honda EU70is', $pailon, '12.00'],
        ];

        $ids = [];

        foreach ($catalogo as [$identificador, $modelo, $baseId, $horasUso]) {
            $generador = $this->crear(new Generador([
                'identificador' => $identificador,
                'modelo' => $modelo,
                'base_id' => $baseId,
                'estado' => 'activo',
                'horas_uso' => $horasUso,
            ]), $autorId);

            $ids[$identificador] = $generador->id;
        }

        return $ids;
    }

    private function integrante(EquipoTrabajo $equipo, int $personaId, RolEquipo $rol, int $autorId): void
    {
        $this->crear(new EquipoIntegrante([
            'equipo_trabajo_id' => $equipo->id,
            'persona_id' => $personaId,
            'rol_equipo' => $rol,
            'desde' => self::VIGENCIA_DESDE,
            'hasta' => null,
        ]), $autorId);
    }

    private function recurso(EquipoTrabajo $equipo, RecursoTipoEquipo $tipo, int $recursoId, int $autorId): void
    {
        $this->crear(new EquipoRecurso([
            'equipo_trabajo_id' => $equipo->id,
            'recurso_tipo' => $tipo,
            'recurso_id' => $recursoId,
            'desde' => self::VIGENCIA_DESDE,
            'hasta' => null,
        ]), $autorId);
    }

    /**
     * @template TModelo of ModeloDominio
     *
     * @param  TModelo  $modelo
     * @return TModelo
     */
    private function crear(ModeloDominio $modelo, int $autorId): ModeloDominio
    {
        $modelo->created_by = $autorId;
        $modelo->updated_by = $autorId;
        $modelo->save();

        return $modelo;
    }
}
