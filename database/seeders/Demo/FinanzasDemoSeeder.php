<?php

namespace Database\Seeders\Demo;

use App\Dominios\Comercial\Aplicacion\EmitirFactura;
use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Finanzas\Aplicacion\AprobarPlanilla;
use App\Dominios\Finanzas\Aplicacion\GenerarPlanilla;
use App\Dominios\Finanzas\Dominio\EstadoRendicion;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Anticipo;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Combustible;
use App\Dominios\Finanzas\Infraestructura\Eloquent\DevengoPersonal;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Gasto;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Rendicion;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Rubro;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Subrubro;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Generador;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Vehiculo;
use App\Dominios\Operaciones\Dominio\EstadoActa;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Acta;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * La cara financiera de lo que ya voló: gastos con su rendición, combustible,
 * anticipos, la planilla del período y las facturas de las actas firmadas.
 *
 * Corre DESPUÉS de `OperacionDemoSeeder` porque casi todo depende de lo que
 * este dejó: los gastos se imputan a trabajos que existen, las facturas salen
 * de actas realmente firmadas, y la planilla se arma sobre los devengos que
 * generó validar las sesiones — no se inventan montos, se derivan.
 *
 * ### El período de la planilla
 *
 * `GenerarDevengosSesion` fecha cada devengo con `Carbon::now()`, no con la
 * fecha del vuelo: el devengo nace cuando alguien valida, y eso pasa hoy. Así
 * que la planilla se genera para el período CORRIENTE, calculado en tiempo de
 * ejecución — no una constante `'2026-09'` que quedaría desalineada la próxima
 * vez que se resiembre la base en otro mes.
 *
 * ### Anticipos por debajo del tope
 *
 * `RegistrarAnticipo` valida que un anticipo no supere lo devengado menos lo
 * ya adelantado (HU-29). Los anticipos de acá se calculan como una fracción
 * de lo efectivamente devengado por cada persona, así que entran holgados —
 * y la planilla los descuenta: `neto = devengado − anticipos`.
 *
 * Idempotente: si ya hay un anticipo sembrado, no hace nada.
 */
class FinanzasDemoSeeder extends Seeder
{
    public function __construct(
        private readonly GenerarPlanilla $generarPlanilla,
        private readonly AprobarPlanilla $aprobarPlanilla,
        private readonly EmitirFactura $emitirFactura,
    ) {}

    public function run(): void
    {
        if (Anticipo::query()->exists()) {
            return;
        }

        $autorId = PersonalDemoSeeder::autorId();

        /** @var array<string, int> $bases */
        $bases = PerBase::query()->pluck('id', 'nombre')->all();
        /** @var array<string, int> $personas */
        $personas = PerPersona::query()->pluck('id', 'nombre')->all();

        if ($bases === [] || $personas === []) {
            return;
        }

        $cuatroCanadas = (int) ($bases['Cuatro Cañadas'] ?? 0);
        $pailon = (int) ($bases['Pailón'] ?? $cuatroCanadas);

        $this->combustible($cuatroCanadas, $pailon, $autorId);
        $this->gastosYRendiciones($cuatroCanadas, $pailon, $personas, $autorId);
        $this->anticipos($personas, $autorId);
        $this->planilla($personas, $autorId);
        $this->facturas($autorId);
    }

    /**
     * Imputada al equipo y al recurso concreto que la consumió (tarea 73,
     * HU-50): cada fila usa el equipo/generador/vehículo que
     * `EquiposTrabajoDemoSeeder` ya sembró para esa misma base — EQ-01 con
     * GEN-01 en Cuatro Cañadas, EQ-02 con GEN-02/CAM-03 en Pailón —, así que
     * la imputación es real, no inventada. Sin `EquiposTrabajoDemoSeeder`
     * (por ejemplo, un test que llama a esta clase suelta) no hay equipo ni
     * recurso al que atribuirle nada, y la carga se saltea entera.
     */
    private function combustible(int $cuatroCanadas, int $pailon, int $autorId): void
    {
        $equipos = EquipoTrabajo::query()->pluck('id', 'codigo')->all();
        $generadores = Generador::query()->pluck('id', 'identificador')->all();
        $vehiculos = Vehiculo::query()->pluck('id', 'identificador')->all();

        if ($equipos === [] || $generadores === [] || $vehiculos === []) {
            return;
        }

        // Diésel del generador que alimenta las cargadoras de batería en
        // campo, y gasolina de los vehículos: los dos recursos del catálogo.
        $catalogo = [
            ['2026-08-01', $cuatroCanadas, 'EQ-01', 'generador', 'GEN-01', '40.00', '148.00', 'Diésel para el generador en el cabecero del L-12.'],
            ['2026-08-04', $pailon, 'EQ-02', 'generador', 'GEN-02', '55.00', '203.50', 'Diésel para la jornada en El Carmen L-03.'],
            ['2026-08-07', $pailon, 'EQ-02', 'vehiculo', 'CAM-03', '60.00', '222.00', 'Carga de CAM-03 antes de la salida a El Carmen.'],
            ['2026-08-13', $cuatroCanadas, 'EQ-01', 'generador', 'GEN-01', '48.00', '177.60', 'Diésel para la jornada larga en San Marcos.'],
            ['2026-08-19', $pailon, 'EQ-02', 'vehiculo', 'CAM-03', '58.00', '214.60', 'Carga de CAM-03, viaje a El Carmen L-08.'],
            ['2026-08-22', $cuatroCanadas, 'EQ-01', 'generador', 'GEN-01', '22.00', '81.40', 'Diésel del generador; jornada corta por falla del AG-09.'],
        ];

        foreach ($catalogo as [$fecha, $baseId, $codigoEquipo, $recursoTipo, $identificadorRecurso, $litros, $monto, $descripcion]) {
            $recursoId = $recursoTipo === 'generador' ? ($generadores[$identificadorRecurso] ?? null) : ($vehiculos[$identificadorRecurso] ?? null);

            if (($equipos[$codigoEquipo] ?? null) === null || $recursoId === null) {
                continue;
            }

            $this->crear(new Combustible([
                'fecha' => $fecha,
                'base_id' => $baseId,
                'equipo_trabajo_id' => $equipos[$codigoEquipo],
                'recurso_tipo' => $recursoTipo,
                'recurso_id' => $recursoId,
                'litros' => $litros,
                'monto' => $monto,
                'descripcion' => $descripcion,
            ]), $autorId);
        }
    }

    /**
     * Dos rendiciones cerradas por el jefe de campo (una aprobada, otra
     * presentada y esperando aprobación) más una abierta con gastos sueltos:
     * los tres estados de la máquina, cada uno con gastos reales adentro.
     *
     * @param  array<string, int>  $personas
     */
    private function gastosYRendiciones(int $cuatroCanadas, int $pailon, array $personas, int $autorId): void
    {
        $jefeCampo = $personas['Abraham Gutiérrez Contreras'] ?? null;
        $encargada = $personas['Jorge Richard Scheidel Dorado'] ?? null;

        if ($jefeCampo === null) {
            return;
        }

        /** @var array<string, int> $rubros */
        $rubros = Rubro::query()->pluck('id', 'nombre')->all();
        /** @var array<string, int> $subrubros */
        $subrubros = Subrubro::query()->pluck('id', 'nombre')->all();

        // `fin_gastos.rubro_id` es NOT NULL y el catálogo de rubros lo siembra
        // `Catalogo\FinanzasRubrosSeeder`, no la familia demo. Un test que
        // invoca `DemoSeeder` suelto (sin `CatalogoSeeder`) no tiene rubros:
        // sin ellos no hay gasto posible, y inventarlos sería que la demo
        // escriba catálogo que no le pertenece.
        if ($rubros === []) {
            return;
        }

        $rendiciones = [
            ['2026-08-08', $cuatroCanadas, EstadoRendicion::Aprobada, 'Rendición de la primera semana de campaña: San Marcos y El Carmen.', $encargada],
            ['2026-08-18', $pailon, EstadoRendicion::Presentada, 'Rendición de la segunda semana: El Carmen L-03 y L-08.', null],
            ['2026-08-25', $cuatroCanadas, EstadoRendicion::Abierta, 'Rendición en curso de la tercera semana.', null],
        ];

        $gastosPorRendicion = [
            0 => [
                ['Alojamiento y viáticos', 'Alimentación', '2026-08-01', '3.00', '80.00'],
                ['Transporte y logística', 'Fletes', '2026-08-04', '1.00', '350.00'],
                ['Insumos varios', 'Herramientas menores', '2026-08-07', '4.00', '62.50'],
            ],
            1 => [
                ['Alojamiento y viáticos', 'Alimentación', '2026-08-13', '4.00', '80.00'],
                ['Comunicaciones', 'Datos móviles', '2026-08-16', '2.00', '45.00'],
                ['Insumos varios', 'Elementos de protección', '2026-08-16', '1.00', '210.00'],
            ],
            2 => [
                ['Personal de apoyo', 'Jornales', '2026-08-22', '2.00', '150.00'],
                ['Insumos varios', 'Herramientas menores', '2026-08-22', '6.00', '38.00'],
            ],
        ];

        foreach ($rendiciones as $indice => [$fecha, $baseId, $estado, $descripcion, $aprobadoPor]) {
            $filas = $gastosPorRendicion[$indice];

            // `monto` de la rendición = suma exacta de sus gastos
            // (invariante 6: todo monto derivado cuadra desde su origen).
            $total = BigDecimal::zero();

            foreach ($filas as [, , , $cantidad, $precio]) {
                $total = $total->plus(BigDecimal::of($cantidad)->multipliedBy($precio));
            }

            $rendicion = $this->crear(new Rendicion([
                'base_id' => $baseId,
                'jefe_campo_id' => $jefeCampo,
                'fecha' => $fecha,
                'descripcion' => $descripcion,
                'monto' => (string) $total->toScale(2, RoundingMode::HalfUp),
                'estado' => $estado,
                'aprobado_por' => $aprobadoPor,
            ]), $autorId);

            foreach ($filas as [$rubro, $subrubro, $fechaGasto, $cantidad, $precio]) {
                $this->crear(new Gasto([
                    'fecha' => $fechaGasto,
                    'rubro_id' => $rubros[$rubro] ?? null,
                    'subrubro_id' => $subrubro === null ? null : ($subrubros[$subrubro] ?? null),
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                    'monto' => (string) BigDecimal::of($cantidad)->multipliedBy($precio)->toScale(2, RoundingMode::HalfUp),
                    'base_id' => $baseId,
                    'rendicion_id' => $rendicion->id,
                ]), $autorId);
            }
        }
    }

    /**
     * Anticipos por debajo del tope: un tercio de lo devengado por cada
     * piloto/auxiliar que efectivamente cobró algo, redondeado hacia abajo a
     * la decena — así la planilla muestra un neto distinto del devengado sin
     * que ningún anticipo choque contra la validación de HU-29.
     *
     * @param  array<string, int>  $personas
     */
    private function anticipos(array $personas, int $autorId): void
    {
        $devengadoPorPersona = DevengoPersonal::query()
            ->selectRaw('persona_id, SUM(monto) AS total')
            ->groupBy('persona_id')
            ->pluck('total', 'persona_id');

        $motivos = [
            'Adelanto quincenal a cuenta de la campaña.',
            'Adelanto por gastos de viaje a la base de Pailón.',
            'Adelanto solicitado a cuenta de las sesiones validadas del mes.',
        ];

        $indice = 0;

        foreach ($devengadoPorPersona as $personaId => $total) {
            $tope = BigDecimal::of((string) $total)->dividedBy('3', 2, RoundingMode::Down);

            if ($tope->isLessThan('50')) {
                continue; // un anticipo de menos de 50 Bs no dice nada en la demo
            }

            $monto = $tope->dividedBy('10', 0, RoundingMode::Down)->multipliedBy('10');

            $this->crear(new Anticipo([
                'persona_id' => (int) $personaId,
                'monto' => (string) $monto->toScale(2),
                'fecha' => Carbon::now()->subDays(3)->toDateString(),
                'motivo' => $motivos[$indice % count($motivos)],
            ]), $autorId);

            $indice++;
        }
    }

    /**
     * Genera y aprueba la planilla del período corriente — el que tienen los
     * devengos recién creados. Va por los casos de uso reales
     * ({@see GenerarPlanilla}, {@see AprobarPlanilla}) y no escribiendo
     * `fin_planillas` a mano: el total y cada detalle se derivan de los
     * devengos y anticipos, que es justo lo que hay que poder verificar.
     *
     * @param  array<string, int>  $personas
     */
    private function planilla(array $personas, int $autorId): void
    {
        if (DevengoPersonal::query()->doesntExist()) {
            return;
        }

        $planilla = $this->generarPlanilla->ejecutar(Carbon::now()->format('Y-m'));

        $this->autoria($planilla, $autorId);

        foreach ($planilla->detalles as $detalle) {
            $this->autoria($detalle, $autorId);
        }

        $this->aprobarPlanilla->ejecutar($planilla, $autorId);
    }

    /**
     * Una factura por cada acta firmada: `EmitirFactura` la rechaza si el
     * acta no está firmada, así que solo entran las cinco que
     * `OperacionDemoSeeder` firmó.
     */
    private function facturas(int $autorId): void
    {
        $actasFirmadas = Acta::query()
            ->where('estado', EstadoActa::Firmada)
            ->orderBy('id')
            ->get();

        foreach ($actasFirmadas as $acta) {
            $factura = $this->emitirFactura->ejecutar($acta->id);

            $this->autoria($factura, $autorId);
        }
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

    private function autoria(ModeloDominio $modelo, int $autorId): void
    {
        $modelo->created_by ??= $autorId;
        $modelo->updated_by = $autorId;
        $modelo->save();
    }
}
