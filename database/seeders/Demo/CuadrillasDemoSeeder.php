<?php

namespace Database\Seeders\Demo;

use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Bateria;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Generador;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Vehiculo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Dron;
use App\Dominios\Personal\Aplicacion\AgregarAccesorioEquipo;
use App\Dominios\Personal\Aplicacion\ArmarCuadrilla;
use App\Dominios\Personal\Aplicacion\AsignarRecursoEquipo;
use App\Dominios\Personal\Aplicacion\CambiarEstadoEquipoTrabajo;
use App\Dominios\Personal\Aplicacion\DesasignarIntegranteEquipo;
use App\Dominios\Personal\Aplicacion\DesasignarRecursoEquipo;
use App\Dominios\Personal\Dominio\EstadoEquipoTrabajo;
use App\Dominios\Personal\Dominio\RecursoTipoEquipo;
use App\Dominios\Personal\Infraestructura\Eloquent\Accesorio;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;
use Illuminate\Database\Seeder;

/**
 * Cuadrillas de la demo, armadas por el caso de uso real (`ArmarCuadrilla`):
 *
 * - EQ3 «Cuadrilla Pailón»: la que vuela San Marcos desde julio.
 * - EQ4 «Cuadrilla San Julián»: la de El Carmen y la segunda aplicación de
 *   San Marcos, con la condición de pago negociada por hectárea.
 * - EQ5 «Cuadrilla de invierno»: ya cerrada (vigencia hasta el 30/6 e
 *   inactiva), para que el listado muestre los dos estados.
 *
 * A las cuadrillas del dueño (EQ1, EQ2) se les suman vehículo, generador y
 * accesorios, sin tocar sus integrantes ni su dron.
 *
 * Centinela: el código `EQ3`.
 */
class CuadrillasDemoSeeder extends Seeder
{
    use SoporteDemo;

    public const CODIGO_PAILON = 'EQ3';

    public const CODIGO_SAN_JULIAN = 'EQ4';

    public const CODIGO_INVIERNO = 'EQ5';

    public function __construct(
        private readonly ArmarCuadrilla $armarCuadrilla,
        private readonly AsignarRecursoEquipo $asignarRecurso,
        private readonly AgregarAccesorioEquipo $agregarAccesorio,
        private readonly DesasignarIntegranteEquipo $desasignarIntegrante,
        private readonly DesasignarRecursoEquipo $desasignarRecurso,
        private readonly CambiarEstadoEquipoTrabajo $cambiarEstado,
    ) {}

    public function run(): void
    {
        if (EquipoTrabajo::query()->where('codigo', self::CODIGO_PAILON)->exists()) {
            return;
        }

        $pailon = $this->basePorNombre(PersonalDemoSeeder::BASE_PAILON);
        $sanJulian = $this->basePorNombre(PersonalDemoSeeder::BASE_SAN_JULIAN);
        $central = $this->basePorNombre(PersonalDemoSeeder::BASE_CENTRAL);
        $drones = Dron::query()->pluck('id', 'identificador')->all();
        $vehiculos = Vehiculo::query()->pluck('id', 'identificador')->all();
        $generadores = Generador::query()->pluck('id', 'identificador')->all();
        $baterias = Bateria::query()->pluck('id', 'identificador')->all();

        if ($pailon === null || $sanJulian === null || $central === null || ! isset($drones['AG-07'], $drones['AG-02'])) {
            return; // faltan PersonalDemoSeeder / FlotaDemoSeeder
        }

        $cuadrillaPailon = $this->armarCuadrilla->ejecutar(
            codigo: self::CODIGO_PAILON,
            nombre: 'Cuadrilla Pailón',
            baseId: $pailon->id,
            desde: '2026-07-01',
            hasta: null,
            pilotoId: $this->personaIdPorCi(PersonalDemoSeeder::CI_PILOTO_JOSUE),
            ayudanteId: $this->personaIdPorCi(PersonalDemoSeeder::CI_AUXILIAR_LUIS),
            ayudante2Id: $this->personaIdPorCi(PersonalDemoSeeder::CI_AUXILIAR_WILDER),
            dronId: $drones['AG-07'],
            vehiculoId: $vehiculos['CAM-01'] ?? null,
            generadorId: $generadores['GEN-01'] ?? null,
            bateriaIds: array_values(array_filter([$baterias['BAT-01'] ?? null, $baterias['BAT-02'] ?? null, $baterias['BAT-03'] ?? null])),
        )->equipo;

        $cuadrillaSanJulian = $this->armarCuadrilla->ejecutar(
            codigo: self::CODIGO_SAN_JULIAN,
            nombre: 'Cuadrilla San Julián',
            baseId: $sanJulian->id,
            desde: '2026-07-15',
            hasta: null,
            pilotoId: $this->personaIdPorCi(PersonalDemoSeeder::CI_PILOTO_RODRIGO),
            ayudanteId: $this->personaIdPorCi(PersonalDemoSeeder::CI_AUXILIAR_DANIELA),
            ayudante2Id: null,
            dronId: $drones['AG-02'],
            vehiculoId: $vehiculos['CAM-03'] ?? null,
            generadorId: $generadores['GEN-02'] ?? null,
            bateriaIds: array_values(array_filter([$baterias['BAT-04'] ?? null, $baterias['BAT-05'] ?? null, $baterias['BAT-06'] ?? null])),
        )->equipo;

        $cuadrillaInvierno = $this->armarCuadrilla->ejecutar(
            codigo: self::CODIGO_INVIERNO,
            nombre: 'Cuadrilla de invierno',
            baseId: $central->id,
            desde: '2026-05-01',
            hasta: '2026-06-30',
            pilotoId: $this->personaIdPorCi(PersonalDemoSeeder::CI_JEFE_CAMPO),
            ayudanteId: $this->personaIdPorCi(PersonalDemoSeeder::CI_AUXILIAR_PEDRO),
            ayudante2Id: null,
            // Sin vehículo, generador ni baterías: los que usó ya están en
            // taller, de baja o retirados, y solo se asigna lo activo.
            dronId: $drones['AG-09'],
            vehiculoId: null,
            generadorId: null,
            bateriaIds: [],
        )->equipo;

        $this->cerrarCuadrillaDeInvierno($cuadrillaInvierno);
        $this->accesorios($cuadrillaPailon, $cuadrillaSanJulian);
        $this->completarCuadrillasDelDueno($vehiculos);
    }

    /**
     * Integrantes y recursos con fecha de fin, y el equipo inactivo — por los
     * casos de uso y la máquina de estados, como lo haría el panel.
     */
    private function cerrarCuadrillaDeInvierno(EquipoTrabajo $equipo): void
    {
        foreach ($equipo->integrantes as $integrante) {
            $this->desasignarIntegrante->ejecutar($integrante, '2026-06-30');
        }

        foreach ($equipo->recursos as $recurso) {
            $this->desasignarRecurso->ejecutar($recurso, '2026-06-30');
        }

        $this->cambiarEstado->ejecutar($equipo->refresh(), EstadoEquipoTrabajo::Inactivo);
    }

    private function accesorios(EquipoTrabajo $pailon, EquipoTrabajo $sanJulian): void
    {
        $catalogo = [
            [$pailon, 'Bidón de 20 L', 6, 'Dos con caldo de reserva, cuatro vacíos.'],
            [$pailon, 'Embudo con filtro', 2, null],
            [$pailon, 'Radio handy', 3, 'Canal 4.'],
            [$pailon, 'Botiquín', 1, null],
            [$pailon, 'Lona de sombra', 1, null],
            [$sanJulian, 'Bidón de 20 L', 4, null],
            [$sanJulian, 'Balde graduado', 2, null],
            [$sanJulian, 'Probeta de 1 L', 1, null],
            [$sanJulian, 'Cargador de baterías', 2, 'Uno de los dos con el ventilador ruidoso.'],
            [$sanJulian, 'Botiquín', 1, null],
        ];

        foreach ($catalogo as [$equipo, $nombre, $cantidad, $observacion]) {
            $accesorioId = Accesorio::query()->whereRaw('lower(nombre) = ?', [mb_strtolower($nombre)])->value('id');

            $this->agregarAccesorio->ejecutar($equipo, $accesorioId !== null ? (int) $accesorioId : null, $nombre, $cantidad, $observacion);
        }
    }

    /**
     * @param  array<string, int>  $vehiculos
     */
    private function completarCuadrillasDelDueno(array $vehiculos): void
    {
        $eq1 = EquipoTrabajo::query()->where('codigo', 'EQ1')->first();
        $eq2 = EquipoTrabajo::query()->where('codigo', 'EQ2')->first();

        if ($eq1 !== null) {
            $this->recursoSiFalta($eq1, RecursoTipoEquipo::Vehiculo, $vehiculos['MOT-01'] ?? null);
            $this->agregarAccesorio->ejecutar($eq1, null, 'Bidón de 20 L', 3, null);
            $this->agregarAccesorio->ejecutar($eq1, null, 'Radio handy', 2, null);
        }

        if ($eq2 !== null) {
            $this->agregarAccesorio->ejecutar($eq2, null, 'Embudo con filtro', 1, null);
            $this->agregarAccesorio->ejecutar($eq2, null, 'Botiquín', 1, null);
        }
    }

    private function recursoSiFalta(EquipoTrabajo $equipo, RecursoTipoEquipo $tipo, ?int $recursoId): void
    {
        if ($recursoId === null) {
            return;
        }

        $yaAsignado = $equipo->recursos()
            ->where('recurso_tipo', $tipo->value)
            ->where('recurso_id', $recursoId)
            ->exists();

        if (! $yaAsignado) {
            $this->asignarRecurso->ejecutar($equipo, $tipo, $recursoId, $equipo->desde->toDateString(), null);
        }
    }
}
