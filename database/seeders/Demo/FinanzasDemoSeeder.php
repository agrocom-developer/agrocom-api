<?php

namespace Database\Seeders\Demo;

use App\Dominios\Campania\Infraestructura\Eloquent\Campania;
use App\Dominios\Comercial\Aplicacion\EmitirFactura;
use App\Dominios\Comercial\Dominio\Excepciones\ActaNoFacturable;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Finanzas\Aplicacion\AprobarPlanilla;
use App\Dominios\Finanzas\Aplicacion\AprobarRendicion;
use App\Dominios\Finanzas\Aplicacion\AsociarGastoARendicion;
use App\Dominios\Finanzas\Aplicacion\CrearCombustible;
use App\Dominios\Finanzas\Aplicacion\CrearGasto;
use App\Dominios\Finanzas\Aplicacion\CrearRendicion;
use App\Dominios\Finanzas\Aplicacion\GenerarPlanilla;
use App\Dominios\Finanzas\Aplicacion\PresentarRendicion;
use App\Dominios\Finanzas\Aplicacion\RegistrarAnticipo;
use App\Dominios\Finanzas\Dominio\EstadoPlanilla;
use App\Dominios\Finanzas\Dominio\Excepciones\AnticipoExcedeTope;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Anticipo;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Combustible;
use App\Dominios\Finanzas\Infraestructura\Eloquent\DevengoPersonal;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Planilla;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Rendicion;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Rubro;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Subrubro;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Generador;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Vehiculo;
use App\Dominios\Operaciones\Dominio\EstadoActa;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Acta;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Financiero: combustible, gastos con sus rendiciones (abierta,
 * presentada y aprobada), anticipos a cuenta de lo devengado, planillas
 * (agosto aprobada con recibos, septiembre en borrador) y facturas de las
 * actas firmadas.
 *
 * Los devengos NO se siembran acá: nacen de validar sesiones en
 * {@see OperacionDemoSeeder} (invariante 3). Los anticipos respetan el tope
 * real (70 % de lo devengado en el mes, máximo 3.000 Bs) porque pasan por
 * `RegistrarAnticipo`.
 */
class FinanzasDemoSeeder extends Seeder
{
    use SoporteDemo;

    public function __construct(
        private readonly CrearCombustible $crearCombustible,
        private readonly CrearGasto $crearGasto,
        private readonly CrearRendicion $crearRendicion,
        private readonly AsociarGastoARendicion $asociarGasto,
        private readonly PresentarRendicion $presentarRendicion,
        private readonly AprobarRendicion $aprobarRendicion,
        private readonly RegistrarAnticipo $registrarAnticipo,
        private readonly GenerarPlanilla $generarPlanilla,
        private readonly AprobarPlanilla $aprobarPlanilla,
        private readonly EmitirFactura $emitirFactura,
    ) {}

    public function run(): void
    {
        $this->combustible();
        $this->gastosYRendiciones();
        $this->anticipos();
        $this->planillas();
        $this->facturas();
    }

    private function combustible(): void
    {
        if (Combustible::query()->exists()) {
            return;
        }

        $campaniaId = Campania::query()->where('codigo', CarteraDemoSeeder::CAMPANIA_INVIERNO)->value('id');
        $equipos = EquipoTrabajo::query()->pluck('id', 'codigo')->all();
        $generadores = Generador::query()->pluck('id', 'identificador')->all();
        $vehiculos = Vehiculo::query()->pluck('id', 'identificador')->all();
        $pailon = $this->basePorNombre(PersonalDemoSeeder::BASE_PAILON)?->id;
        $sanJulian = $this->basePorNombre(PersonalDemoSeeder::BASE_SAN_JULIAN)?->id;

        if ($campaniaId === null || $pailon === null || $sanJulian === null) {
            return;
        }

        $eq3 = $equipos[CuadrillasDemoSeeder::CODIGO_PAILON] ?? null;
        $eq4 = $equipos[CuadrillasDemoSeeder::CODIGO_SAN_JULIAN] ?? null;

        // [fecha, base, equipo, tipo de recurso, recurso, litros, monto, descripción]
        $catalogo = [
            ['2026-08-03', $pailon, $eq3, 'generador', $generadores['GEN-01'] ?? null, '40.00', '148.00', 'Diésel para el generador en la cabecera del SM-01.'],
            ['2026-08-05', $pailon, $eq3, 'vehiculo', $vehiculos['CAM-01'] ?? null, '60.00', '222.00', 'Carga de la camioneta antes de salir a San Marcos.'],
            ['2026-08-20', $sanJulian, $eq4, 'generador', $generadores['GEN-02'] ?? null, '55.00', '203.50', 'Diésel para la jornada en El Carmen (vale del cliente).'],
            ['2026-08-21', $sanJulian, $eq4, 'vehiculo', $vehiculos['CAM-03'] ?? null, '70.00', '259.00', 'Carga de la chata, viaje de vuelta desde El Carmen.'],
            ['2026-09-08', $pailon, $eq3, 'generador', $generadores['GEN-01'] ?? null, '35.00', '129.50', 'Diésel del generador, segunda aplicación de San Marcos.'],
            ['2026-09-10', $sanJulian, $eq4, 'vehiculo', $vehiculos['CAM-03'] ?? null, '65.00', '240.50', 'Carga de la chata para el turno nocturno del SM-04.'],
            ['2026-09-21', $sanJulian, $eq4, 'generador', $generadores['GEN-02'] ?? null, '30.00', '111.00', 'Diésel para el generador del campamento en SM-05.'],
        ];

        foreach ($catalogo as [$fecha, $baseId, $equipoId, $tipo, $recursoId, $litros, $monto, $descripcion]) {
            if ($equipoId === null || $recursoId === null) {
                continue;
            }

            $this->crearCombustible->ejecutar($fecha, (int) $baseId, (int) $equipoId, (int) $campaniaId, $tipo, (int) $recursoId, $litros, $monto, $descripcion);
        }
    }

    private function gastosYRendiciones(): void
    {
        if (Rendicion::query()->exists()) {
            return;
        }

        $jefeCampo = $this->personaIdPorCi(PersonalDemoSeeder::CI_JEFE_CAMPO);
        $encargada = $this->personaIdPorCi(PersonalDemoSeeder::CI_ENCARGADA);
        $pailon = $this->basePorNombre(PersonalDemoSeeder::BASE_PAILON)?->id;
        $sanJulian = $this->basePorNombre(PersonalDemoSeeder::BASE_SAN_JULIAN)?->id;
        $campaniaId = Campania::query()->where('codigo', CarteraDemoSeeder::CAMPANIA_INVIERNO)->value('id');
        $equipos = EquipoTrabajo::query()->pluck('id', 'codigo')->all();

        if ($jefeCampo === 0 || $encargada === 0 || $pailon === null || $sanJulian === null || $campaniaId === null) {
            return;
        }

        $eq3 = $equipos[CuadrillasDemoSeeder::CODIGO_PAILON] ?? null;
        $eq4 = $equipos[CuadrillasDemoSeeder::CODIGO_SAN_JULIAN] ?? null;

        // [rubro, subrubro, fecha, cantidad, precio unitario, base, trabajo (lote), equipo]
        $rendiciones = [
            [
                'rendicion' => ['2026-08-08', $pailon, 'Rendición de la primera aplicación de San Marcos (3 al 6 de agosto).', 'aprobada'],
                'gastos' => [
                    ['Alojamiento y viáticos', 'Alimentación', '2026-08-03', '3.00', '80.00', $pailon, 'SM-01', $eq3],
                    ['Transporte y logística', 'Peajes', '2026-08-03', '2.00', '15.00', $pailon, null, $eq3],
                    ['Insumos varios', 'Herramientas menores', '2026-08-05', '4.00', '62.50', $pailon, 'SM-02', $eq3],
                    ['Comunicaciones', 'Datos móviles', '2026-08-06', '1.00', '45.00', $pailon, null, $eq3],
                ],
            ],
            [
                'rendicion' => ['2026-08-23', $sanJulian, 'Rendición de El Carmen (20 y 21 de agosto).', 'presentada'],
                'gastos' => [
                    ['Alojamiento y viáticos', 'Hospedaje', '2026-08-19', '2.00', '120.00', $sanJulian, 'EC-01', $eq4],
                    ['Alojamiento y viáticos', 'Alimentación', '2026-08-20', '4.00', '35.00', $sanJulian, 'EC-01', $eq4],
                    ['Insumos varios', 'Elementos de protección', '2026-08-20', '2.00', '210.00', $sanJulian, null, $eq4],
                ],
            ],
            [
                'rendicion' => ['2026-09-22', $sanJulian, 'Rendición en curso: segunda aplicación de San Marcos.', 'abierta'],
                'gastos' => [
                    ['Personal de apoyo', 'Jornales', '2026-09-10', '1.00', '150.00', $sanJulian, 'SM-04', $eq4],
                    ['Transporte y logística', 'Fletes', '2026-09-11', '1.00', '350.00', $sanJulian, null, $eq4],
                    ['Insumos varios', 'Herramientas menores', '2026-09-21', '6.00', '38.00', $sanJulian, 'SM-05', $eq4],
                ],
            ],
        ];

        foreach ($rendiciones as ['rendicion' => [$fecha, $baseId, $descripcion, $estado], 'gastos' => $gastos]) {
            $rendicion = $this->crearRendicion->ejecutar((int) $baseId, $jefeCampo, $fecha, $descripcion);

            foreach ($gastos as [$rubro, $subrubro, $fechaGasto, $cantidad, $precio, $baseGasto, $codigoLote, $equipoId]) {
                $gasto = $this->crearGasto->ejecutar(
                    $fechaGasto,
                    $this->rubroId($rubro),
                    $this->subrubroId($rubro, $subrubro),
                    $cantidad,
                    $precio,
                    (int) $baseGasto,
                    $codigoLote === null ? null : $this->trabajoIdPorLote($codigoLote),
                    (int) $campaniaId,
                    null,
                    $equipoId === null ? null : (int) $equipoId,
                );

                $this->asociarGasto->ejecutar($gasto, $rendicion);
            }

            if ($estado === 'presentada' || $estado === 'aprobada') {
                $rendicion = $this->presentarRendicion->ejecutar($rendicion->refresh());
            }

            if ($estado === 'aprobada') {
                // La aprueba la encargada de operaciones, nunca el mismo jefe
                // de campo que la rindió.
                $this->aprobarRendicion->ejecutar($rendicion->refresh(), $encargada);
            }
        }

        // Un gasto suelto, sin rendición y sin cuadrilla: el seguro anual.
        $this->crearGasto->ejecutar('2026-09-01', $this->rubroId('Seguros'), $this->subrubroId('Seguros', 'Seguro de equipo'), '1.00', '4800.00', $pailon, null, (int) $campaniaId, null, null);
    }

    private function anticipos(): void
    {
        if (Anticipo::query()->exists() || DevengoPersonal::query()->doesntExist()) {
            return;
        }

        $catalogo = [
            [PersonalDemoSeeder::CI_AUXILIAR_LUIS, '100.00', '2026-08-10', 'Adelanto por gastos de viaje a la base de Pailón.'],
            [PersonalDemoSeeder::CI_PILOTO_RODRIGO, '150.00', '2026-08-25', 'Adelanto quincenal a cuenta de la campaña.'],
            [PersonalDemoSeeder::CI_PILOTO_JOSUE, '100.00', '2026-09-15', 'Adelanto solicitado a cuenta de las sesiones validadas del mes.'],
            [PersonalDemoSeeder::CI_PILOTO_RODRIGO, '300.00', '2026-09-16', 'Adelanto a cuenta del turno nocturno del SM-04.'],
            [PersonalDemoSeeder::CI_AUXILIAR_DANIELA, '200.00', '2026-09-16', 'Adelanto quincenal a cuenta de la campaña.'],
        ];

        foreach ($catalogo as [$ci, $monto, $fecha, $motivo]) {
            $personaId = $this->personaIdPorCi($ci);

            if ($personaId === 0) {
                continue;
            }

            try {
                $this->registrarAnticipo->ejecutar($personaId, $monto, $fecha, $motivo);
            } catch (AnticipoExcedeTope) {
                // Sin devengo suficiente ese mes no hay anticipo: es la regla.
            }
        }
    }

    private function planillas(): void
    {
        if (DevengoPersonal::query()->doesntExist()) {
            return;
        }

        $agosto = $this->generarPlanilla->ejecutar('2026-08');

        if ($agosto->estado === EstadoPlanilla::Borrador && $agosto->detalles()->exists()) {
            // Aprobar genera el recibo PDF de cada persona.
            $this->aprobarPlanilla->ejecutar($agosto, (int) Auth::guard('interno')->id());
        }

        if (Planilla::query()->where('periodo', '2026-09')->doesntExist()) {
            $this->generarPlanilla->ejecutar('2026-09');
        }
    }

    private function facturas(): void
    {
        $actasFirmadas = Acta::query()
            ->where('estado', EstadoActa::Firmada)
            ->orderBy('id')
            ->get();

        foreach ($actasFirmadas as $acta) {
            try {
                $this->emitirFactura->ejecutar($acta->id);
            } catch (ActaNoFacturable) {
                // Ya facturada en una corrida anterior.
            }
        }
    }

    private function rubroId(string $nombre): int
    {
        return (int) Rubro::query()->where('nombre', $nombre)->value('id');
    }

    private function subrubroId(string $rubro, string $nombre): ?int
    {
        $id = Subrubro::query()
            ->where('rubro_id', $this->rubroId($rubro))
            ->where('nombre', $nombre)
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    private function trabajoIdPorLote(string $codigoLote): ?int
    {
        $loteId = Lote::query()->where('codigo', $codigoLote)->value('id');

        if ($loteId === null) {
            return null;
        }

        $id = Trabajo::query()->where('lote_id', $loteId)->orderBy('id')->value('id');

        return $id === null ? null : (int) $id;
    }
}
