<?php

namespace Database\Seeders\Demo;

use App\Dominios\Inventario\Aplicacion\CrearRepuesto;
use App\Dominios\Inventario\Aplicacion\RegistrarMovimientoStock;
use App\Dominios\Inventario\Dominio\SentidoAjusteInventario;
use App\Dominios\Inventario\Dominio\TipoMovimientoInventario;
use App\Dominios\Inventario\Infraestructura\Eloquent\Repuesto;
use App\Dominios\Inventario\Infraestructura\Eloquent\Stock;
use App\Dominios\Mantenimiento\Aplicacion\CrearBateria;
use App\Dominios\Mantenimiento\Aplicacion\CrearFichaDron;
use App\Dominios\Mantenimiento\Aplicacion\CrearGenerador;
use App\Dominios\Mantenimiento\Aplicacion\CrearPlanMantenimiento;
use App\Dominios\Mantenimiento\Aplicacion\CrearVehiculo;
use App\Dominios\Mantenimiento\Aplicacion\MaquinaEstados\MaquinaEstadosOrdenMantenimiento;
use App\Dominios\Mantenimiento\Dominio\EstadoBateria;
use App\Dominios\Mantenimiento\Dominio\EstadoGenerador;
use App\Dominios\Mantenimiento\Dominio\EstadoVehiculo;
use App\Dominios\Mantenimiento\Dominio\TipoCombustibleVehiculo;
use App\Dominios\Mantenimiento\Dominio\TipoVehiculo;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Bateria;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\FichaDron;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Generador;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\OrdenMantenimiento;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\PlanMantenimiento;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Vehiculo;
use App\Dominios\Operaciones\Aplicacion\CrearDron;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Dron;
use Illuminate\Database\Seeder;

/**
 * Recursos y Mantenimiento: drones y sus fichas, baterías, vehículos,
 * generadores, planes de mantenimiento, repuestos con su stock por base y
 * las órdenes de mantenimiento (dos cerradas por la máquina de estados, que
 * es lo que descuenta repuestos y registra el gasto; dos abiertas).
 *
 * Centinela: el dron `AG-02`.
 */
class FlotaDemoSeeder extends Seeder
{
    use SoporteDemo;

    public const DRON_CENTINELA = 'AG-02';

    public function __construct(
        private readonly CrearDron $crearDron,
        private readonly CrearFichaDron $crearFichaDron,
        private readonly CrearBateria $crearBateria,
        private readonly CrearVehiculo $crearVehiculo,
        private readonly CrearGenerador $crearGenerador,
        private readonly CrearPlanMantenimiento $crearPlan,
        private readonly CrearRepuesto $crearRepuesto,
        private readonly RegistrarMovimientoStock $registrarMovimiento,
        private readonly MaquinaEstadosOrdenMantenimiento $maquinaOrden,
    ) {}

    public function run(): void
    {
        if (Dron::query()->where('identificador', self::DRON_CENTINELA)->exists()) {
            return;
        }

        $central = (int) $this->basePorNombre(PersonalDemoSeeder::BASE_CENTRAL)?->id;
        $pailon = (int) $this->basePorNombre(PersonalDemoSeeder::BASE_PAILON)?->id;
        $sanJulian = (int) $this->basePorNombre(PersonalDemoSeeder::BASE_SAN_JULIAN)?->id;

        if ($central === 0 || $pailon === 0 || $sanJulian === 0) {
            return; // falta PersonalDemoSeeder
        }

        $this->drones();
        $this->fichasDron();
        $this->baterias($central, $pailon, $sanJulian);
        $this->vehiculos($central, $pailon, $sanJulian);
        $this->generadores($pailon, $sanJulian, $central);
        $this->planes();
        $this->repuestosYStock($central, $pailon, $sanJulian);
        $this->ordenesMantenimiento($central);
    }

    private function drones(): void
    {
        $catalogo = [
            ['AG-02', 'DJI Agras T30', '30.00', '40.00'],
            ['AG-07', 'DJI Agras T50', '50.00', '50.00'],
            ['AG-09', 'DJI Agras T60', '60.00', '70.00'],
        ];

        foreach ($catalogo as [$identificador, $modelo, $litros, $kilos]) {
            $this->crearDron->ejecutar($identificador, $modelo, $litros, $kilos);
        }
    }

    private function fichasDron(): void
    {
        $catalogo = [
            ['DRONT50', '1ZNBJ7C00C00A1', 'T50-CH-00471', 'v03.02.0412', 'LATAM', 'RC-PLUS-88213', true, true, true],
            ['AG-02', '1ZNBJ4A00B00K9', 'T30-CH-00218', 'v02.05.0117', 'LATAM', 'RC-PLUS-55120', true, false, true],
            ['AG-07', '1ZNBJ7C00C00F3', 'T50-CH-00502', 'v03.02.0412', 'LATAM', 'RC-PLUS-88240', true, true, false],
        ];

        foreach ($catalogo as [$identificador, $serie, $chasis, $software, $region, $serieControl, $cargador, $modem, $maletin]) {
            if (FichaDron::query()->where('identificador_dron', $identificador)->exists()) {
                continue;
            }

            $this->crearFichaDron->ejecutar($identificador, $serie, $chasis, $software, $region, $serieControl, $cargador, $modem, $maletin);
        }
    }

    private function baterias(int $central, int $pailon, int $sanJulian): void
    {
        $catalogo = [
            ['BAT-01', 0, 180, EstadoBateria::Activa, $pailon],
            ['BAT-02', 0, 240, EstadoBateria::Activa, $pailon],
            ['BAT-03', 50, 95, EstadoBateria::Activa, $pailon],
            ['BAT-04', 0, 310, EstadoBateria::Activa, $sanJulian],
            ['BAT-05', 0, 420, EstadoBateria::Activa, $sanJulian],
            ['BAT-06', 120, 160, EstadoBateria::Activa, $sanJulian],
            ['BAT-07', 0, 275, EstadoBateria::Mantenimiento, $central],
            ['BAT-08', 0, 812, EstadoBateria::Retirada, $central],
        ];

        foreach ($catalogo as [$identificador, $inicial, $acumulados, $estado, $baseId]) {
            if (Bateria::query()->where('identificador', $identificador)->exists()) {
                continue;
            }

            $this->crearBateria->ejecutar($identificador, $inicial, $acumulados, $baseId, $estado);
        }
    }

    private function vehiculos(int $central, int $pailon, int $sanJulian): void
    {
        $catalogo = [
            ['CAM-01', $pailon, EstadoVehiculo::Activo, 'Toyota', 'Hilux SRV', 2022, TipoCombustibleVehiculo::Diesel, true, '38500.00', '61240.00', TipoVehiculo::Camioneta],
            ['CAM-02', $central, EstadoVehiculo::Taller, 'Volvo', 'FH 440', 2018, TipoCombustibleVehiculo::Diesel, false, '210000.00', '287300.00', TipoVehiculo::Camion],
            ['CAM-03', $sanJulian, EstadoVehiculo::Activo, 'Ford', 'F-350', 2020, TipoCombustibleVehiculo::Diesel, true, '72000.00', '98750.00', TipoVehiculo::Chata],
            ['MOT-01', $pailon, EstadoVehiculo::Activo, 'Honda', 'XR 150L', 2023, TipoCombustibleVehiculo::Gasolina, false, '1200.00', '9860.00', TipoVehiculo::Moto],
            ['CAM-04', $central, EstadoVehiculo::DeBaja, 'Nissan', 'Frontier', 2012, TipoCombustibleVehiculo::Diesel, true, '150000.00', '312000.00', TipoVehiculo::Camioneta],
        ];

        foreach ($catalogo as [$identificador, $baseId, $estado, $marca, $modelo, $anio, $combustible, $es4x4, $kmInicial, $kmActual, $tipo]) {
            if (Vehiculo::query()->where('identificador', $identificador)->exists()) {
                continue;
            }

            $this->crearVehiculo->ejecutar($identificador, $baseId, $estado, $marca, $modelo, $anio, $combustible, $es4x4, $kmInicial, $kmActual, $tipo);
        }
    }

    private function generadores(int $pailon, int $sanJulian, int $central): void
    {
        $catalogo = [
            ['GEN-01', 'Honda EU70is', $pailon, EstadoGenerador::Activo, '120.00', '342.50'],
            ['GEN-02', 'Yamaha EF7200DE', $sanJulian, EstadoGenerador::Activo, '0.00', '198.25'],
            ['GEN-03', 'Toyama TG8000CXE', $central, EstadoGenerador::Taller, '540.00', '1210.00'],
        ];

        foreach ($catalogo as [$identificador, $modelo, $baseId, $estado, $inicial, $actual]) {
            if (Generador::query()->where('identificador', $identificador)->exists()) {
                continue;
            }

            $this->crearGenerador->ejecutar($identificador, $modelo, $baseId, $estado, $inicial, $actual);
        }
    }

    private function planes(): void
    {
        if (PlanMantenimiento::query()->exists()) {
            return;
        }

        $catalogo = [
            ['DJI Agras T30', 'Cambio de filtros de bomba y limpieza de boquillas', '50.00'],
            ['DJI Agras T30', 'Revisión de hélices y ajuste de brazos', '100.00'],
            ['DJI Agras T50', 'Cambio de filtros de bomba y limpieza de boquillas', '50.00'],
            ['DJI Agras T50', 'Calibración de radar y sensores de obstáculo', '150.00'],
            ['DJI Agras T60', 'Cambio de filtros de bomba y limpieza de boquillas', '50.00'],
            ['DJI Agras T60', 'Revisión de ESC y cableado de potencia', '200.00'],
        ];

        foreach ($catalogo as [$modelo, $tarea, $horas]) {
            $this->crearPlan->ejecutar($modelo, $tarea, $horas);
        }
    }

    private function repuestosYStock(int $central, int $pailon, int $sanJulian): void
    {
        if (Repuesto::query()->exists()) {
            return;
        }

        // [código, descripción, unidad, costo, compra en Central, mínimo Central, traslado a Pailón, mínimo Pailón]
        $catalogo = [
            ['RP-001', 'Filtro de bomba Agras (juego x2)', 'juego', '85.00', '24.00', '6.00', '8.00', '4.00'],
            ['RP-002', 'Boquilla de aspersión XR11001', 'unidad', '32.50', '48.00', '12.00', '20.00', '10.00'],
            ['RP-003', 'Hélice 38 pulgadas (par)', 'par', '410.00', '9.00', '4.00', '3.00', '2.00'],
            ['RP-004', 'Módulo ESC 80 A', 'unidad', '1250.00', '3.00', '3.00', '1.00', '1.00'],
            ['RP-005', 'Manguera de alta presión 8 mm', 'metro', '18.75', '60.00', '20.00', '25.00', '10.00'],
            ['RP-006', 'Aceite 15W-40 (bidón 4 L)', 'bidón', '95.00', '12.00', '4.00', '4.00', '2.00'],
        ];

        foreach ($catalogo as [$codigo, $descripcion, $unidad, $costo, $compra, $minimoCentral, $traslado, $minimoPailon]) {
            $repuesto = $this->crearRepuesto->ejecutar($codigo, $descripcion, $unidad, $costo);

            $this->registrarMovimiento->ejecutar(
                TipoMovimientoInventario::Compra,
                $repuesto,
                $central,
                $compra,
                costoUnitario: $costo,
                motivo: 'Compra de reposición para la campaña de invierno.',
            );

            $this->registrarMovimiento->ejecutar(
                TipoMovimientoInventario::Traslado,
                $repuesto,
                $central,
                $traslado,
                baseDestinoId: $pailon,
                motivo: 'Refuerzo de la base de Pailón para la cuadrilla de El Carmen.',
            );

            // El mínimo no tiene pantalla que lo edite todavía (tarea 117):
            // se fija acá para que «Bajo el mínimo» tenga algo que mostrar.
            Stock::query()->where('repuesto_id', $repuesto->id)->where('base_id', $central)->update(['stock_minimo' => $minimoCentral]);
            Stock::query()->where('repuesto_id', $repuesto->id)->where('base_id', $pailon)->update(['stock_minimo' => $minimoPailon]);
        }

        $boquilla = Repuesto::query()->where('codigo', 'RP-002')->firstOrFail();
        $this->registrarMovimiento->ejecutar(
            TipoMovimientoInventario::Ajuste,
            $boquilla,
            $pailon,
            '2.00',
            sentido: SentidoAjusteInventario::Decremento,
            motivo: 'Conteo físico de fin de mes: dos boquillas rotas no descargadas.',
        );

        $manguera = Repuesto::query()->where('codigo', 'RP-005')->firstOrFail();
        $this->registrarMovimiento->ejecutar(
            TipoMovimientoInventario::Traslado,
            $manguera,
            $central,
            '10.00',
            baseDestinoId: $sanJulian,
            motivo: 'Primer stock de San Julián.',
        );
    }

    private function ordenesMantenimiento(int $central): void
    {
        if (OrdenMantenimiento::query()->exists()) {
            return;
        }

        $drones = Dron::query()->pluck('id', 'identificador')->all();
        $vehiculos = Vehiculo::query()->pluck('id', 'identificador')->all();
        $esc = Repuesto::query()->where('codigo', 'RP-004')->firstOrFail();
        $filtros = Repuesto::query()->where('codigo', 'RP-001')->firstOrFail();

        // Cerradas: el cierre por la máquina de estados descuenta los repuestos
        // (Inventario) y registra el gasto de mantenimiento (Finanzas).
        $escAg02 = $this->maquinaOrden->abrir([
            'equipo_tipo' => 'dron',
            'equipo_id' => $drones['AG-02'],
            'tipo' => 'correctivo',
            'descripcion' => 'ESC del brazo 3 con sobretemperatura reportada en vuelo. Reemplazo de módulo.',
        ]);
        $this->maquinaOrden->cerrar(
            $escAg02,
            [['repuesto_id' => $esc->id, 'base_id' => $central, 'cantidad' => '1.00']],
            'Se reemplazó el módulo ESC del brazo 3 y se probó en vuelo estacionario 10 minutos sin alarmas.',
        );
        $this->fechar($escAg02, '2026-08-08 11:20:00', '2026-08-09 19:40:00');

        $filtrosAg07 = $this->maquinaOrden->abrir([
            'equipo_tipo' => 'dron',
            'equipo_id' => $drones['AG-07'],
            'tipo' => 'preventivo',
            'descripcion' => 'Cambio de filtros de bomba a las 50 h de vuelo, según plan.',
        ]);
        $this->maquinaOrden->cerrar(
            $filtrosAg07,
            [['repuesto_id' => $filtros->id, 'base_id' => $central, 'cantidad' => '1.00']],
            'Filtros cambiados y boquillas limpias. Caudal verificado en banco.',
        );
        $this->fechar($filtrosAg07, '2026-08-14 10:00:00', '2026-08-14 13:30:00');

        // Abiertas.
        $radarAg09 = $this->maquinaOrden->abrir([
            'equipo_tipo' => 'dron',
            'equipo_id' => $drones['AG-09'],
            'tipo' => 'preventivo',
            'descripcion' => 'Calibración de radar y sensores de obstáculo a las 150 h.',
        ]);
        $this->fechar($radarAg09, '2026-09-15 11:00:00', null);

        $embragueCam02 = $this->maquinaOrden->abrir([
            'equipo_tipo' => 'vehiculo',
            'equipo_id' => $vehiculos['CAM-02'],
            'tipo' => 'correctivo',
            'descripcion' => 'Embrague patinando en pendiente. Vehículo inmovilizado en taller.',
        ]);
        $this->fechar($embragueCam02, '2026-09-10 20:10:00', null);
    }

    /**
     * La máquina de estados fecha la apertura y el cierre con `now()`; para
     * que el listado cuente una historia con fechas creíbles se corrigen
     * después. No son campos de estado.
     */
    private function fechar(OrdenMantenimiento $orden, string $apertura, ?string $cierre): void
    {
        $orden->fecha_apertura = $apertura;

        if ($cierre !== null) {
            $orden->fecha_cierre = $cierre;
        }

        $orden->save();
    }
}
