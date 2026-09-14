<?php

namespace Database\Seeders\Demo;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Inventario\Dominio\SentidoAjusteInventario;
use App\Dominios\Inventario\Dominio\TipoMovimientoInventario;
use App\Dominios\Inventario\Infraestructura\Eloquent\MovimientoStock;
use App\Dominios\Inventario\Infraestructura\Eloquent\Repuesto;
use App\Dominios\Inventario\Infraestructura\Eloquent\Stock;
use App\Dominios\Mantenimiento\Dominio\EstadoBateria;
use App\Dominios\Mantenimiento\Dominio\EstadoOrdenMantenimiento;
use App\Dominios\Mantenimiento\Dominio\EstadoVehiculo;
use App\Dominios\Mantenimiento\Dominio\TipoVehiculo;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Bateria;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\OrdenMantenimiento;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\PlanMantenimiento;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Vehiculo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Dron;
use App\Dominios\Personal\Infraestructura\Eloquent\PerBase;
use Illuminate\Database\Seeder;

/**
 * Los recursos físicos de la operación: drones, baterías, vehículos,
 * repuestos con su stock por base, y el mantenimiento de todo eso.
 *
 * Cubre de una sola vez las pantallas de Recursos (§4.2) y Mantenimiento e
 * inventario (§4.5), que hasta ahora abrían vacías porque ningún seeder las
 * sembraba — solo existían las filas que iban dejando los fixtures de los
 * tests visuales.
 *
 * Los identificadores de dron (`AG-02`, `AG-04`, `AG-07`, `AG-09`) son los
 * del relato de las capturas de RC; `OperacionDemoSeeder` los usa para que la
 * evidencia real de cada vuelo quede colgando del dron que la voló.
 * `capacidad_l` está limitada por CHECK a 30/50/60 L (espec §178, "volumen
 * real por modelo"), así que la flota es T30/T50/T60 aunque el relato
 * mencione un T100.
 *
 * Estados repartidos a propósito, para que las pantallas tengan algo que
 * mostrar además de una lista uniforme: una batería retirada por ciclos, un
 * vehículo en taller, una orden de mantenimiento abierta y otra cerrada, y un
 * repuesto por debajo de su stock mínimo.
 *
 * Idempotente: si el primer dron ya existe, no hace nada.
 */
class FlotaDemoSeeder extends Seeder
{
    private const DRON_CENTINELA = 'AG-02';

    public function run(): void
    {
        if (Dron::query()->where('identificador', self::DRON_CENTINELA)->exists()) {
            return;
        }

        $autorId = PersonalDemoSeeder::autorId();

        /** @var array<string, int> $bases */
        $bases = PerBase::query()->pluck('id', 'nombre')->all();
        $cuatroCanadas = $bases['Cuatro Cañadas'] ?? null;
        $pailon = $bases['Pailón'] ?? $cuatroCanadas;

        if ($cuatroCanadas === null) {
            return; // sin bases sembradas no hay dónde colgar la flota
        }

        $drones = $this->drones($autorId);
        $this->baterias($autorId, $cuatroCanadas, (int) $pailon);
        $vehiculos = $this->vehiculos($autorId, $cuatroCanadas, (int) $pailon);
        $this->planes($autorId);
        $ordenes = $this->ordenesMantenimiento($autorId, $drones, $vehiculos);
        $this->inventario($autorId, $cuatroCanadas, (int) $pailon, $ordenes);
    }

    /**
     * @return array<string, int> identificador → id
     */
    private function drones(int $autorId): array
    {
        $catalogo = [
            ['AG-02', 'DJI Agras T30', '30.00'],
            ['AG-04', 'DJI Agras T50', '50.00'],
            ['AG-07', 'DJI Agras T60', '60.00'],
            ['AG-09', 'DJI Agras T50', '50.00'],
        ];

        $ids = [];

        foreach ($catalogo as [$identificador, $modelo, $capacidad]) {
            $dron = $this->crear(new Dron([
                'identificador' => $identificador,
                'modelo' => $modelo,
                'capacidad_l' => $capacidad,
            ]), $autorId);

            $ids[$identificador] = $dron->id;
        }

        return $ids;
    }

    private function baterias(int $autorId, int $cuatroCanadas, int $pailon): void
    {
        // Ocho activas repartidas entre las dos bases + una retirada por
        // haber pasado el umbral de ciclos: la pantalla de baterías existe
        // justamente para ver ese desgaste acumulado (HU-39).
        $catalogo = [
            ['BAT-01', 180, EstadoBateria::Activa, $cuatroCanadas],
            ['BAT-02', 240, EstadoBateria::Activa, $cuatroCanadas],
            ['BAT-03', 95, EstadoBateria::Activa, $cuatroCanadas],
            ['BAT-04', 310, EstadoBateria::Activa, $cuatroCanadas],
            ['BAT-05', 420, EstadoBateria::Activa, $cuatroCanadas],
            ['BAT-06', 60, EstadoBateria::Activa, $pailon],
            ['BAT-07', 275, EstadoBateria::Activa, $pailon],
            ['BAT-08', 140, EstadoBateria::Activa, $pailon],
            ['BAT-09', 812, EstadoBateria::Retirada, $pailon],
        ];

        foreach ($catalogo as [$identificador, $ciclos, $estado, $baseId]) {
            $this->crear(new Bateria([
                'identificador' => $identificador,
                'ciclos_acumulados' => $ciclos,
                'estado' => $estado,
                'base_id' => $baseId,
            ]), $autorId);
        }
    }

    /**
     * @return array<string, int> identificador → id
     */
    private function vehiculos(int $autorId, int $cuatroCanadas, int $pailon): array
    {
        // 'tipo' (HU-90, tarea 105): CAM-03 queda como 'chata' a propósito
        // (en vez de 'camioneta'/'camion' como el resto de los CAM-*), para
        // que el valor nuevo del catálogo se vea en el panel de demo.
        $catalogo = [
            ['CAM-01', $cuatroCanadas, EstadoVehiculo::Activo, TipoVehiculo::Camioneta],
            ['CAM-02', $cuatroCanadas, EstadoVehiculo::Taller, TipoVehiculo::Camion],
            ['CAM-03', $pailon, EstadoVehiculo::Activo, TipoVehiculo::Chata],
            ['MOT-01', $pailon, EstadoVehiculo::Activo, TipoVehiculo::Moto],
        ];

        $ids = [];

        foreach ($catalogo as [$identificador, $baseId, $estado, $tipo]) {
            $vehiculo = $this->crear(new Vehiculo([
                'identificador' => $identificador,
                'tipo' => $tipo,
                'base_id' => $baseId,
                'estado' => $estado,
            ]), $autorId);

            $ids[$identificador] = $vehiculo->id;
        }

        return $ids;
    }

    private function planes(int $autorId): void
    {
        $catalogo = [
            ['DJI Agras T30', 'Cambio de filtros de bomba y limpieza de boquillas', '50.00'],
            ['DJI Agras T30', 'Revisión de hélices y ajuste de brazos', '100.00'],
            ['DJI Agras T50', 'Cambio de filtros de bomba y limpieza de boquillas', '50.00'],
            ['DJI Agras T50', 'Calibración de radar y sensores de obstáculo', '150.00'],
            ['DJI Agras T60', 'Cambio de filtros de bomba y limpieza de boquillas', '50.00'],
            ['DJI Agras T60', 'Revisión de ESC y cableado de potencia', '200.00'],
        ];

        foreach ($catalogo as [$modelo, $tarea, $horas]) {
            $this->crear(new PlanMantenimiento([
                'modelo' => $modelo,
                'tarea' => $tarea,
                'horas_umbral' => $horas,
            ]), $autorId);
        }
    }

    /**
     * @param  array<string, int>  $drones
     * @param  array<string, int>  $vehiculos
     * @return array<string, int> clave → id de orden
     */
    private function ordenesMantenimiento(int $autorId, array $drones, array $vehiculos): array
    {
        $catalogo = [
            ['esc-ag02', 'dron', $drones['AG-02'], 'correctivo', 'ESC del brazo 3 con sobretemperatura reportada en vuelo. Reemplazo de módulo.', EstadoOrdenMantenimiento::Cerrada, '2026-08-08 07:20:00', '2026-08-09 15:40:00'],
            ['filtros-ag04', 'dron', $drones['AG-04'], 'preventivo', 'Cambio de filtros de bomba a las 50 h de vuelo, según plan.', EstadoOrdenMantenimiento::Cerrada, '2026-08-14 06:00:00', '2026-08-14 09:30:00'],
            ['radar-ag07', 'dron', $drones['AG-07'], 'preventivo', 'Calibración de radar y sensores de obstáculo a las 150 h.', EstadoOrdenMantenimiento::Abierta, '2026-09-01 07:00:00', null],
            ['embrague-cam02', 'vehiculo', $vehiculos['CAM-02'], 'correctivo', 'Embrague patinando en pendiente. Vehículo inmovilizado en taller.', EstadoOrdenMantenimiento::Abierta, '2026-08-30 16:10:00', null],
        ];

        $ids = [];

        foreach ($catalogo as [$clave, $equipoTipo, $equipoId, $tipo, $descripcion, $estado, $apertura, $cierre]) {
            $orden = $this->crear(new OrdenMantenimiento([
                'equipo_tipo' => $equipoTipo,
                'equipo_id' => $equipoId,
                'tipo' => $tipo,
                'descripcion' => $descripcion,
                'estado' => $estado,
                'fecha_apertura' => $apertura,
                'fecha_cierre' => $cierre,
            ]), $autorId);

            $ids[$clave] = $orden->id;
        }

        return $ids;
    }

    /**
     * @param  array<string, int>  $ordenes
     */
    private function inventario(int $autorId, int $cuatroCanadas, int $pailon, array $ordenes): void
    {
        $catalogo = [
            ['RP-001', 'Filtro de bomba Agras (juego x2)', 'juego', '85.00', '24.00', '6.00', '4.00', '6.00'],
            ['RP-002', 'Boquilla de aspersión XR11001', 'unidad', '32.50', '48.00', '12.00', '20.00', '12.00'],
            ['RP-003', 'Hélice 38 pulgadas (par)', 'par', '410.00', '9.00', '4.00', '3.00', '4.00'],
            ['RP-004', 'Módulo ESC 80 A', 'unidad', '1250.00', '2.00', '3.00', '1.00', '3.00'],
            ['RP-005', 'Manguera de alta presión 8 mm', 'metro', '18.75', '60.00', '20.00', '35.00', '20.00'],
        ];

        foreach ($catalogo as [$codigo, $descripcion, $unidad, $costo, $cantidadCC, $minimoCC, $cantidadPailon, $minimoPailon]) {
            $repuesto = $this->crear(new Repuesto([
                'codigo' => $codigo,
                'descripcion' => $descripcion,
                'unidad' => $unidad,
                'costo_unitario' => $costo,
            ]), $autorId);

            foreach ([[$cuatroCanadas, $cantidadCC, $minimoCC], [$pailon, $cantidadPailon, $minimoPailon]] as [$baseId, $cantidad, $minimo]) {
                $this->crear(new Stock([
                    'repuesto_id' => $repuesto->id,
                    'base_id' => $baseId,
                    'cantidad' => $cantidad,
                    'stock_minimo' => $minimo,
                ]), $autorId);
            }

            // Compra que dejó el saldo de arriba en Cuatro Cañadas.
            $this->crear(new MovimientoStock([
                'repuesto_id' => $repuesto->id,
                'base_id' => $cuatroCanadas,
                'tipo' => TipoMovimientoInventario::Compra,
                'cantidad' => $cantidadCC,
                'costo_unitario' => $costo,
                'motivo' => 'Compra de reposición de campaña.',
            ]), $autorId);
        }

        // El ESC que consumió la orden correctiva de AG-02: es lo que deja a
        // RP-004 por DEBAJO de su stock mínimo en Cuatro Cañadas (2 < 3), el
        // caso que la pantalla de stock existe para hacer visible.
        $esc = Repuesto::query()->where('codigo', 'RP-004')->firstOrFail();

        $this->crear(new MovimientoStock([
            'repuesto_id' => $esc->id,
            'base_id' => $cuatroCanadas,
            'tipo' => TipoMovimientoInventario::Salida,
            'cantidad' => '1.00',
            'costo_unitario' => '1250.00',
            'motivo' => 'Reemplazo de ESC del brazo 3 en AG-02.',
            'orden_mantenimiento_id' => $ordenes['esc-ag02'],
        ]), $autorId);

        // Traslado entre bases y un ajuste por conteo físico: los otros dos
        // tipos de movimiento del catálogo, para que el filtro por tipo de la
        // pantalla tenga las cuatro opciones con datos.
        $manguera = Repuesto::query()->where('codigo', 'RP-005')->firstOrFail();

        $this->crear(new MovimientoStock([
            'repuesto_id' => $manguera->id,
            'base_id' => $cuatroCanadas,
            'base_destino_id' => $pailon,
            'tipo' => TipoMovimientoInventario::Traslado,
            'cantidad' => '10.00',
            'motivo' => 'Refuerzo de stock en Pailón para la campaña de El Carmen.',
        ]), $autorId);

        $boquilla = Repuesto::query()->where('codigo', 'RP-002')->firstOrFail();

        $this->crear(new MovimientoStock([
            'repuesto_id' => $boquilla->id,
            'base_id' => $pailon,
            'tipo' => TipoMovimientoInventario::Ajuste,
            'cantidad' => '2.00',
            'sentido' => SentidoAjusteInventario::Decremento,
            'motivo' => 'Conteo físico de fin de mes: dos boquillas rotas no descargadas.',
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
