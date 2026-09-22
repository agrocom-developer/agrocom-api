<?php

namespace Database\Seeders\Demo;

use App\Dominios\Campania\Infraestructura\Eloquent\Campania;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\ClienteContacto;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Finanzas\Infraestructura\Eloquent\Tarifa;
use App\Dominios\Mantenimiento\Infraestructura\Eloquent\Vehiculo;
use App\Dominios\Operaciones\Aplicacion\AtenderAlerta;
use App\Dominios\Operaciones\Aplicacion\CrearOrdenTrabajo;
use App\Dominios\Operaciones\Aplicacion\FirmarActa;
use App\Dominios\Operaciones\Aplicacion\GenerarActaTrabajo;
use App\Dominios\Operaciones\Aplicacion\GenerarAlertaExcepcion;
use App\Dominios\Operaciones\Aplicacion\GenerarReporteTecnico;
use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosOrden;
use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosSesion;
use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosTrabajo;
use App\Dominios\Operaciones\Aplicacion\RechazarSesion;
use App\Dominios\Operaciones\Aplicacion\RegistrarEstadiaHacienda;
use App\Dominios\Operaciones\Aplicacion\RegistrarPausa;
use App\Dominios\Operaciones\Aplicacion\ValidarSesion;
use App\Dominios\Operaciones\Contratos\Eventos\RecargaRegistrada;
use App\Dominios\Operaciones\Dominio\CausaCancelacionOrden;
use App\Dominios\Operaciones\Dominio\CausaPausa;
use App\Dominios\Operaciones\Dominio\TipoAlojamiento;
use App\Dominios\Operaciones\Dominio\TipoEvidencia;
use App\Dominios\Operaciones\Dominio\TipoIncidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Alerta;
use App\Dominios\Operaciones\Infraestructura\Eloquent\CategoriaInsumo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Condiciones;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Dron;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Evidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\EvidenciaEquipo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Incidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenTrabajo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Recarga;
use App\Dominios\Operaciones\Infraestructura\Eloquent\RecepcionCaldo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Infraestructura\Eloquent\EquipoTrabajo;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * El flujo operativo completo sobre los contratos de {@see CarteraDemoSeeder},
 * construido con las 21 capturas REALES del control remoto del dron que viven
 * en `docs/gestion/respuestas_campo/capturas_rc/`.
 *
 * ### Órdenes de aplicación (una por estado alcanzable)
 *
 * | Orden | Contrato        | Estado    | Qué cuelga                                             |
 * |-------|-----------------|-----------|--------------------------------------------------------|
 * | A1    | San Marcos      | consumida | OT con EQ3, dos trabajos cerrados, actas firmadas      |
 * | A2    | San Marcos      | vigente   | OT con EQ3 (por día) y EQ4 (negociado por hectárea)    |
 * | B1    | El Carmen       | pausada   | OT con EQ4, un trabajo cerrado y acta firmada          |
 * | C1    | Santa Rosa      | emitida   | sin reparto todavía                                    |
 * | D1    | Valle Esperanza | cancelada | causa cliente                                          |
 *
 * ### Sesiones (todos los estados)
 *
 * Validadas (con sus devengos, invariante 3), cerradas sin validar (para la
 * bandeja de seguimiento), una rechazada (anulada con motivo) y una abierta
 * en curso. Con condiciones, recargas (una con la batería caliente), pausas,
 * incidencias, recepciones de caldo, mezclas, evidencias de equipo, actas
 * firmadas y pendientes, reportes técnicos, alertas de los cuatro tipos y
 * estadías (dos cerradas, una abierta).
 *
 * Todo pasa por las máquinas de estado y los casos de uso reales — nunca un
 * `estado = ...` a mano (invariante 7). Lo único que se corrige después son
 * las fechas de inicio de los trabajos, que `CrearOrdenTrabajo` fija en
 * `now()`: no son estado, y el relato de agosto necesita que digan agosto.
 *
 * Centinela: la orden A1 (cualquier orden del contrato de San Marcos).
 */
class OperacionDemoSeeder extends Seeder
{
    use SoporteDemo;

    private const ORIGEN_CAPTURAS = 'docs/gestion/respuestas_campo/capturas_rc';

    /**
     * Sesiones: clave → [trabajo, piloto (CI), auxiliar (CI)|null, dron, ha,
     * litros, inicio UTC, fin UTC, motivo de cierre, captura de RC, qué pasa
     * después: validar | pendiente | rechazar | abierta].
     *
     * Bolivia es UTC−4: 10:00 UTC son las 06:00 locales, dentro de las
     * ventanas de la mañana que fija cada contrato.
     *
     * @var array<string, array{string, string, string|null, string, string, string, string, string|null, string|null, string|null, string}>
     */
    private const SESIONES = [
        'S-01' => ['T-SM01', PersonalDemoSeeder::CI_PILOTO_JOSUE, PersonalDemoSeeder::CI_AUXILIAR_LUIS, 'AG-07', '42.50', '510.00', '2026-08-03 10:30:00', '2026-08-03 12:45:00', 'fin_jornada', 'rc_14.jpeg', 'validar'],
        'S-02' => ['T-SM01', PersonalDemoSeeder::CI_PILOTO_JOSUE, PersonalDemoSeeder::CI_AUXILIAR_WILDER, 'AG-07', '37.50', '450.00', '2026-08-04 10:15:00', '2026-08-04 12:20:00', 'completado', 'rc_15.jpeg', 'validar'],
        'S-03' => ['T-SM02', PersonalDemoSeeder::CI_PILOTO_JOSUE, PersonalDemoSeeder::CI_AUXILIAR_LUIS, 'AG-07', '31.00', '372.00', '2026-08-05 10:00:00', '2026-08-05 12:10:00', 'completado', 'rc_16.jpeg', 'validar'],
        'S-04' => ['T-SM02', PersonalDemoSeeder::CI_PILOTO_JOSUE, null, 'AG-07', '30.50', '366.00', '2026-08-06 10:05:00', '2026-08-06 11:40:00', 'completado', 'rc_05.jpeg', 'validar'],
        'S-10' => ['T-EC01', PersonalDemoSeeder::CI_PILOTO_RODRIGO, PersonalDemoSeeder::CI_AUXILIAR_DANIELA, 'AG-02', '45.00', '411.75', '2026-08-20 09:40:00', '2026-08-20 12:00:00', 'fin_jornada', 'rc_08.jpeg', 'validar'],
        'S-11' => ['T-EC01', PersonalDemoSeeder::CI_PILOTO_RODRIGO, PersonalDemoSeeder::CI_AUXILIAR_DANIELA, 'AG-02', '45.00', '411.75', '2026-08-21 09:35:00', '2026-08-21 11:50:00', 'completado', 'rc_17.jpeg', 'validar'],
        'S-05' => ['T-SM03', PersonalDemoSeeder::CI_PILOTO_JOSUE, PersonalDemoSeeder::CI_AUXILIAR_LUIS, 'AG-07', '30.00', '300.00', '2026-09-08 10:00:00', '2026-09-08 11:50:00', 'fin_jornada', 'rc_12.jpeg', 'validar'],
        'S-06' => ['T-SM03', PersonalDemoSeeder::CI_PILOTO_JOSUE, PersonalDemoSeeder::CI_AUXILIAR_WILDER, 'AG-07', '40.00', '400.00', '2026-09-09 10:10:00', '2026-09-09 12:30:00', 'completado', 'rc_20.jpeg', 'pendiente'],
        'S-07' => ['T-SM04', PersonalDemoSeeder::CI_PILOTO_RODRIGO, PersonalDemoSeeder::CI_AUXILIAR_DANIELA, 'AG-02', '50.00', '500.00', '2026-09-10 21:30:00', '2026-09-10 23:50:00', 'completado', 'rc_21.jpeg', 'validar'],
        'S-08' => ['T-SM05', PersonalDemoSeeder::CI_PILOTO_RODRIGO, null, 'AG-02', '25.00', '180.00', '2026-09-11 10:00:00', '2026-09-11 11:30:00', 'otro', 'rc_11.jpeg', 'rechazar'],
        'S-09' => ['T-SM05', PersonalDemoSeeder::CI_PILOTO_RODRIGO, PersonalDemoSeeder::CI_AUXILIAR_DANIELA, 'AG-02', '0.00', '0.00', '2026-09-22 10:05:00', null, null, null, 'abierta'],
    ];

    /**
     * Actas firmadas: trabajo → [carta de conformidad fotografiada, firmante, fecha de firma].
     *
     * @var array<string, array{string, string, string}>
     */
    private const ACTAS_FIRMADAS = [
        'T-SM01' => ['rc_03.jpeg', 'Ing. Agr. Lorena Suárez', '2026-08-04 18:20:00'],
        'T-SM02' => ['rc_06.jpeg', 'Ing. Agr. Lorena Suárez', '2026-08-06 17:05:00'],
        'T-EC01' => ['rc_07.jpeg', 'Ing. Agr. Daniel Terceros', '2026-08-21 17:00:00'],
    ];

    /**
     * @var array<string, string>
     */
    private const IMAGENES_CAMPO = [
        'T-SM01' => 'rc_18.jpeg',
        'T-SM02' => 'rc_19.jpeg',
        'T-EC01' => 'rc_02.jpeg',
        'T-SM04' => 'rc_09.jpeg',
    ];

    /**
     * Incidencias: sesión → [tipo, descripción, hora UTC, foto].
     *
     * @var array<string, array{TipoIncidencia, string, string, string}>
     */
    private const INCIDENCIAS = [
        'S-02' => [TipoIncidencia::Caldo, 'Filtro de la bomba tapado en la segunda recarga: 40 minutos parados hasta limpiarlo y volver a cargar.', '2026-08-04 11:20:00', 'rc_10.jpeg'],
        'S-11' => [TipoIncidencia::Mecanica, 'Aviso de sobretemperatura del ESC del brazo 3. Se completó el lote a velocidad reducida.', '2026-08-21 11:05:00', 'rc_04.jpeg'],
        'S-08' => [TipoIncidencia::Otro, 'El piloto cerró la sesión desde la app sin adjuntar la captura final; se repite el vuelo.', '2026-09-11 11:28:00', 'rc_13.jpeg'],
    ];

    /**
     * Pausas: sesión → [causa, inicio UTC, fin UTC].
     *
     * @var array<string, array{CausaPausa, string, string}>
     */
    private const PAUSAS = [
        'S-01' => [CausaPausa::Logistica, '2026-08-03 11:10:00', '2026-08-03 11:25:00'],
        'S-10' => [CausaPausa::Clima, '2026-08-20 10:30:00', '2026-08-20 10:50:00'],
        'S-06' => [CausaPausa::ImprevistoDelCliente, '2026-09-09 11:00:00', '2026-09-09 11:35:00'],
    ];

    public function __construct(
        private readonly MaquinaEstadosOrden $maquinaOrden,
        private readonly CrearOrdenTrabajo $crearOrdenTrabajo,
        private readonly MaquinaEstadosTrabajo $maquinaTrabajo,
        private readonly MaquinaEstadosSesion $maquinaSesion,
        private readonly ValidarSesion $validarSesion,
        private readonly RechazarSesion $rechazarSesion,
        private readonly RegistrarPausa $registrarPausa,
        private readonly GenerarActaTrabajo $generarActa,
        private readonly FirmarActa $firmarActa,
        private readonly GenerarReporteTecnico $generarReporte,
        private readonly GenerarAlertaExcepcion $generarAlerta,
        private readonly AtenderAlerta $atenderAlerta,
        private readonly RegistrarEstadiaHacienda $registrarEstadia,
    ) {}

    /** @var array<string, Contrato> */
    private array $contratos = [];

    /** @var array<string, Lote> */
    private array $lotes = [];

    /** @var array<string, int> */
    private array $equipos = [];

    /** @var array<string, int> */
    private array $drones = [];

    /** @var array<string, Evidencia> */
    private array $evidencias = [];

    /** @var array<string, Trabajo> */
    private array $trabajos = [];

    /** @var array<string, Sesion> */
    private array $sesiones = [];

    private ?int $tarifaPredeterminadaId = null;

    /** @var array<string, string> hectáreas acumuladas por dron, en orden de vuelo */
    private array $acumuladoPorDron = [];

    /** @var array<string, int> */
    private array $secuenciaPorTrabajo = [];

    public function run(): void
    {
        if (! $this->cargarContexto()) {
            return;
        }

        if (OrdenAplicacion::query()->where('contrato_id', $this->contratos['san_marcos']->id)->exists()) {
            return;
        }

        $this->evidencias = $this->sembrarEvidencias();

        // Una orden por vez: la base solo admite una orden abierta (emitida,
        // vigente o pausada) por contrato, así que A1 se consume entera antes
        // de emitir A2.
        $ordenA1 = $this->ordenA1();
        $this->sembrarSesiones(['T-SM01', 'T-SM02']);
        $this->cerrarTrabajos(['T-SM01', 'T-SM02']);
        $this->evidenciaEquipo('T-SM01', '38.50', '2026-08-04 17:00:00');
        $this->actasYReportes(['T-SM01', 'T-SM02']);
        $this->maquinaOrden->cerrar($ordenA1->refresh());

        $this->ordenA2();
        $this->sembrarSesiones(['T-SM03', 'T-SM04', 'T-SM05']);
        $this->cerrarTrabajos(['T-SM04']);
        $this->actasYReportes(['T-SM04']);

        $ordenB1 = $this->ordenB1();
        $this->sembrarSesiones(['T-EC01']);
        $this->cerrarTrabajos(['T-EC01']);
        $this->evidenciaEquipo('T-EC01', '21.25', '2026-08-21 16:30:00');
        $this->actasYReportes(['T-EC01']);
        $this->maquinaOrden->pausar($ordenB1->refresh(), 'Lluvias: el lote EC-02 está anegado. Se retoma cuando seque.');

        $this->ordenC1();
        $this->ordenD1();

        $this->alertas();
        $this->estadias();
    }

    private function cargarContexto(): bool
    {
        $campaniaId = (int) Campania::query()
            ->where('codigo', CarteraDemoSeeder::CAMPANIA_INVIERNO)
            ->value('id');

        foreach ([
            'san_marcos' => CarteraDemoSeeder::NIT_SAN_MARCOS,
            'el_carmen' => CarteraDemoSeeder::NIT_EL_CARMEN,
            'santa_rosa' => CarteraDemoSeeder::NIT_SANTA_ROSA,
            'valle_esperanza' => CarteraDemoSeeder::NIT_VALLE_ESPERANZA,
        ] as $clave => $nit) {
            $clienteId = Cliente::query()->where('nit', $nit)->value('id');
            $contrato = $clienteId === null ? null : Contrato::query()
                ->where('cliente_id', $clienteId)
                ->where('campania_id', $campaniaId)
                ->orderBy('id')
                ->first();

            if ($contrato === null) {
                return false; // falta CarteraDemoSeeder
            }

            $this->contratos[$clave] = $contrato;
        }

        foreach (Lote::query()->whereIn('propiedad_id', Propiedad::query()->whereIn('nombre', ['San Marcos', 'El Carmen', 'Santa Rosa', 'Valle Esperanza'])->select('id'))->get() as $lote) {
            $this->lotes[$lote->codigo] = $lote;
        }

        $this->equipos = EquipoTrabajo::query()->pluck('id', 'codigo')->all();
        $this->drones = Dron::query()->pluck('id', 'identificador')->all();
        $this->tarifaPredeterminadaId = Tarifa::query()->where('predeterminada', true)->value('id');

        return isset($this->equipos[CuadrillasDemoSeeder::CODIGO_PAILON], $this->equipos[CuadrillasDemoSeeder::CODIGO_SAN_JULIAN], $this->drones['AG-07'], $this->drones['AG-02'])
            && $this->tarifaPredeterminadaId !== null;
    }

    // ------------------------------------------------------------------
    // Órdenes de aplicación y órdenes de trabajo
    // ------------------------------------------------------------------

    /**
     * @param  list<string>  $codigosLote
     */
    private function orden(Contrato $contrato, int $nro, string $fechaEmision, string $tipo, string $categoria, ?string $litrosHa, int $equipos, string $observaciones, array $codigosLote, ?string $kilosPorVuelo = null): OrdenAplicacion
    {
        $agronomo = ClienteContacto::query()
            ->where('cliente_id', $contrato->cliente_id)
            ->where('tipo', 'agronomo')
            ->value('id');

        $orden = $this->maquinaOrden->crear([
            'contrato_id' => $contrato->id,
            'nro_aplicacion' => $nro,
            'tipo_aplicacion' => $tipo,
            'fecha_emision' => $fechaEmision,
            'cantidad_equipos_necesarios' => $equipos,
            'categoria_insumo_id' => CategoriaInsumo::query()->where('nombre', $categoria)->value('id'),
            'litros_ha' => $litrosHa,
            'kilos_por_vuelo' => $kilosPorVuelo,
            'observaciones' => $observaciones,
            'emitida_por_contacto_id' => $agronomo,
        ]);

        foreach ($codigosLote as $codigo) {
            $lote = $this->lotes[$codigo];
            $orden->ordenLotes()->create([
                'lote_id' => $lote->id,
                'hectareas_solicitadas' => (string) $lote->hectareas,
            ]);
        }

        return $orden;
    }

    private function ordenA1(): OrdenAplicacion
    {
        $orden = $this->orden(
            $this->contratos['san_marcos'], 1, '2026-08-01', 'desarrollo', 'Fungicidas', '12.00', 1,
            'Primera aplicación de la campaña: fungicida preventivo sobre trigo en macollaje.',
            ['SM-01', 'SM-02'],
        );
        $this->maquinaOrden->activar($orden);

        $ordenTrabajo = $this->crearOrdenTrabajo->ejecutar($orden->refresh(), [
            'humedad_min_pct' => '60.00',
            'humedad_max_pct' => '90.00',
            'viento_max_kmh' => '15.00',
            'temperatura_max_c' => '32.00',
            'altura_vuelo_m' => '3.00',
            'velocidad_vuelo_kmh' => '18.00',
            'ancho_pasada_m' => '6.00',
            'ph_agua' => '6.50',
            'ph_calda' => '6.00',
            'litros_ha' => '12.00',
            'kilos_ha' => null,
            'calda_productos' => ['glifosato', 'agua'],
            'calda' => [
                ['producto' => 'Azoxistrobina + Ciproconazol', 'cantidad' => '0.30', 'unidad' => 'l'],
                ['producto' => 'Agua', 'cantidad' => '11.70', 'unidad' => 'l'],
            ],
        ], [
            [
                'equipo_trabajo_id' => $this->equipos[CuadrillasDemoSeeder::CODIGO_PAILON],
                'pago' => $this->pagoConTarifa(),
                'lotes' => [
                    ['lote_id' => $this->lotes['SM-01']->id, 'hectareas' => '80.00', 'turno' => 'manana', 'turno_hora_inicio' => '06:00', 'turno_hora_fin' => '10:30'],
                    ['lote_id' => $this->lotes['SM-02']->id, 'hectareas' => '60.00', 'turno' => 'manana', 'turno_hora_inicio' => '06:00', 'turno_hora_fin' => '10:30'],
                ],
            ],
        ]);

        $this->registrarTrabajos($ordenTrabajo, ['SM-01' => 'T-SM01', 'SM-02' => 'T-SM02'], '2026-08-03 09:40:00');
        $this->recepcionCaldo('T-SM01', '1750.00', 'Nelson Quispe', '2026-08-03 10:00:00', '40.00');
        $this->recepcionCaldo('T-SM02', '740.00', 'Nelson Quispe', '2026-08-05 09:45:00', '2.00');

        return $orden;
    }

    private function ordenA2(): OrdenAplicacion
    {
        $orden = $this->orden(
            $this->contratos['san_marcos'], 2, '2026-09-05', 'desarrollo', 'Insecticidas', '10.00', 2,
            'Segunda aplicación: insecticida por presencia de chinche. Dos cuadrillas para cerrar en la semana.',
            ['SM-03', 'SM-04', 'SM-05', 'SM-06'],
        );
        $this->maquinaOrden->activar($orden);

        $ordenTrabajo = $this->crearOrdenTrabajo->ejecutar($orden->refresh(), [
            'humedad_min_pct' => '55.00',
            'humedad_max_pct' => '95.00',
            'viento_max_kmh' => '18.00',
            'temperatura_max_c' => '34.00',
            'altura_vuelo_m' => '3.50',
            'velocidad_vuelo_kmh' => '20.00',
            'ancho_pasada_m' => '7.00',
            'ph_agua' => '6.80',
            'ph_calda' => '5.50',
            'litros_ha' => '10.00',
            'kilos_ha' => null,
            'calda_productos' => ['dos_cuatro_d', 'agua'],
            'calda' => [
                ['producto' => 'Lambdacialotrina 5%', 'cantidad' => '0.25', 'unidad' => 'l'],
                ['producto' => 'Coadyuvante siliconado', 'cantidad' => '50', 'unidad' => 'ml'],
                ['producto' => 'Agua', 'cantidad' => '9.70', 'unidad' => 'l'],
            ],
        ], [
            [
                'equipo_trabajo_id' => $this->equipos[CuadrillasDemoSeeder::CODIGO_PAILON],
                'pago' => $this->pagoConTarifa(),
                'lotes' => [
                    ['lote_id' => $this->lotes['SM-03']->id, 'hectareas' => '70.00', 'turno' => 'manana', 'turno_hora_inicio' => '06:00', 'turno_hora_fin' => '10:30'],
                ],
            ],
            [
                'equipo_trabajo_id' => $this->equipos[CuadrillasDemoSeeder::CODIGO_SAN_JULIAN],
                'pago' => [
                    'tarifa_id' => null,
                    'negociado' => true,
                    'modalidad' => 'por_ha',
                    'monto_piloto' => '14.50',
                    'monto_auxiliar' => '9.00',
                    'motivo' => 'Lotes con obstáculos y turno nocturno: la cuadrilla pidió cobrar por hectárea.',
                ],
                'lotes' => [
                    ['lote_id' => $this->lotes['SM-04']->id, 'hectareas' => '50.00', 'turno' => 'noche', 'turno_hora_inicio' => '17:30', 'turno_hora_fin' => '20:30'],
                    ['lote_id' => $this->lotes['SM-05']->id, 'hectareas' => '60.00', 'turno' => 'todo_el_dia', 'turno_hora_inicio' => '06:00', 'turno_hora_fin' => '18:00'],
                ],
            ],
        ]);

        $this->registrarTrabajos($ordenTrabajo, ['SM-03' => 'T-SM03', 'SM-04' => 'T-SM04', 'SM-05' => 'T-SM05'], '2026-09-08 09:30:00');
        $this->recepcionCaldo('T-SM03', '700.00', 'Nelson Quispe', '2026-09-08 09:45:00', null);
        $this->recepcionCaldo('T-SM04', '500.00', 'Nelson Quispe', '2026-09-10 21:00:00', '0.00');
        $this->recepcionCaldo('T-SM05', '600.00', 'Nelson Quispe', '2026-09-11 09:40:00', null);

        return $orden;
    }

    private function ordenB1(): OrdenAplicacion
    {
        $orden = $this->orden(
            $this->contratos['el_carmen'], 1, '2026-08-18', 'desarrollo', 'Insecticidas', '9.15', 1,
            'Insecticida por presencia de chinche en floración. Respetar la restricción de colmenas del EC-01: avisar al apicultor 24 h antes.',
            ['EC-01', 'EC-02', 'EC-03', 'EC-04', 'EC-05'],
        );
        $this->maquinaOrden->activar($orden);

        $ordenTrabajo = $this->crearOrdenTrabajo->ejecutar($orden->refresh(), [
            'humedad_min_pct' => '60.00',
            'humedad_max_pct' => '92.00',
            'viento_max_kmh' => '18.00',
            'temperatura_max_c' => '33.00',
            'altura_vuelo_m' => '4.00',
            'velocidad_vuelo_kmh' => '16.00',
            'ancho_pasada_m' => '6.00',
            'ph_agua' => '7.00',
            'ph_calda' => '6.20',
            'litros_ha' => '9.15',
            'kilos_ha' => null,
            'calda_productos' => ['glifosato', 'urea', 'agua'],
            'calda' => [
                ['producto' => 'Imidacloprid 35%', 'cantidad' => '0.20', 'unidad' => 'l'],
                ['producto' => 'Urea', 'cantidad' => '0.50', 'unidad' => 'kg'],
                ['producto' => 'Agua', 'cantidad' => '8.45', 'unidad' => 'l'],
            ],
        ], [
            [
                'equipo_trabajo_id' => $this->equipos[CuadrillasDemoSeeder::CODIGO_SAN_JULIAN],
                'pago' => $this->pagoConTarifa(),
                'lotes' => [
                    ['lote_id' => $this->lotes['EC-01']->id, 'hectareas' => '90.00', 'turno' => 'manana', 'turno_hora_inicio' => '05:30', 'turno_hora_fin' => '10:00'],
                ],
            ],
        ]);

        $this->registrarTrabajos($ordenTrabajo, ['EC-01' => 'T-EC01'], '2026-08-20 09:20:00');
        $this->recepcionCaldo('T-EC01', '830.00', 'Encargado El Carmen', '2026-08-20 09:30:00', '6.50');

        return $orden;
    }

    private function ordenC1(): void
    {
        $this->orden(
            $this->contratos['santa_rosa'], 1, '2026-09-20', 'siembra', 'Semillas de Pasto', null, 1,
            'Siembra de pasto al voleo sobre los cuatro lotes. La dueña autoriza cada salida por teléfono.',
            ['SR-01', 'SR-02', 'SR-03', 'SR-04'],
            kilosPorVuelo: '35.00',
        );
    }

    private function ordenD1(): void
    {
        $orden = $this->orden(
            $this->contratos['valle_esperanza'], 1, '2026-08-12', 'desarrollo', 'Herbicida', '11.00', 1,
            'Herbicida post-emergente sobre sorgo.',
            ['VE-03', 'VE-04'],
        );
        $this->maquinaOrden->activar($orden);
        $this->maquinaOrden->cancelar($orden, CausaCancelacionOrden::Cliente, 'El cliente pausó el contrato por las lluvias antes de que saliera la cuadrilla.');
    }

    /**
     * @return array<string, mixed>
     */
    private function pagoConTarifa(): array
    {
        return [
            'tarifa_id' => $this->tarifaPredeterminadaId,
            'negociado' => false,
            'modalidad' => null,
            'monto_piloto' => null,
            'monto_auxiliar' => null,
            'motivo' => null,
        ];
    }

    /**
     * Guarda los trabajos que creó la OT bajo la clave del relato y les corrige
     * la fecha de inicio (la OT los abre con `now()`).
     *
     * @param  array<string, string>  $clavePorLote  código de lote → clave del trabajo
     */
    private function registrarTrabajos(OrdenTrabajo $ordenTrabajo, array $clavePorLote, string $inicio): void
    {
        foreach ($ordenTrabajo->trabajos as $trabajo) {
            $codigo = array_search((int) $trabajo->lote_id, array_map(fn (Lote $lote): int => (int) $lote->id, $this->lotes), true);

            if ($codigo === false || ! isset($clavePorLote[$codigo])) {
                continue;
            }

            $trabajo->inicio = $inicio;

            $imagen = self::IMAGENES_CAMPO[$clavePorLote[$codigo]] ?? null;

            if ($imagen !== null && isset($this->evidencias[$imagen])) {
                $trabajo->imagen_campo_evidencia_id = $this->evidencias[$imagen]->id;
            }

            $trabajo->save();
            $this->trabajos[$clavePorLote[$codigo]] = $trabajo;
        }
    }

    private function recepcionCaldo(string $claveTrabajo, string $litros, string $entregadoPor, string $hora, ?string $sobrante): void
    {
        $trabajo = $this->trabajos[$claveTrabajo] ?? null;

        if ($trabajo === null) {
            return;
        }

        RecepcionCaldo::query()->create([
            'uuid_cliente' => $this->uuid('caldo', $claveTrabajo),
            'trabajo_id' => $trabajo->id,
            'litros' => $litros,
            'entregado_por' => $entregadoPor,
            'hora' => $hora,
        ]);

        if ($sobrante !== null) {
            $trabajo->litros_sobrante = $sobrante;
            $trabajo->save();
        }
    }

    // ------------------------------------------------------------------
    // Evidencias
    // ------------------------------------------------------------------

    /**
     * Copia las capturas al disco `r2` con la ruta del ADR 0009 y su hash
     * real, como lo haría `RegistrarEvidencia` si llegaran por la API.
     *
     * @return array<string, Evidencia> archivo → evidencia
     */
    private function sembrarEvidencias(): array
    {
        $tipos = [];

        foreach (self::SESIONES as [, $pilotoCi, , , , , $inicio, , , $archivo]) {
            if ($archivo !== null) {
                $tipos[$archivo] = [TipoEvidencia::CapturaRc, $pilotoCi, $inicio];
            }
        }

        foreach (self::ACTAS_FIRMADAS as $trabajoClave => [$archivo, , $fechaFirma]) {
            $tipos[$archivo] = [TipoEvidencia::FirmaActa, $this->pilotoDelTrabajo($trabajoClave), $fechaFirma];
        }

        foreach (self::IMAGENES_CAMPO as $trabajoClave => $archivo) {
            $tipos[$archivo] = [TipoEvidencia::ImagenCampo, $this->pilotoDelTrabajo($trabajoClave), $this->inicioDelTrabajo($trabajoClave)];
        }

        foreach (self::INCIDENCIAS as $sesionClave => [, , $hora, $archivo]) {
            $tipos[$archivo] = [TipoEvidencia::FotoIncidencia, self::SESIONES[$sesionClave][1], $hora];
        }

        $evidencias = [];

        foreach ($tipos as $archivo => [$tipo, $pilotoCi, $fecha]) {
            $evidencia = $this->evidenciaDesdeCaptura($archivo, $tipo, $archivo, $pilotoCi, $fecha);

            if ($evidencia !== null) {
                $evidencias[$archivo] = $evidencia;
            }
        }

        return $evidencias;
    }

    private function evidenciaDesdeCaptura(string $archivo, TipoEvidencia $tipo, string $clave, ?string $pilotoCi, string $fecha): ?Evidencia
    {
        $origen = base_path(self::ORIGEN_CAPTURAS.'/'.$archivo);

        if (! is_file($origen)) {
            return null; // captura ausente del repo: se omite, no se inventa
        }

        $uuidCliente = $this->uuid('evidencia', $tipo->value.':'.$clave);

        $existente = Evidencia::query()->where('uuid_cliente', $uuidCliente)->first();

        if ($existente !== null) {
            return $existente;
        }

        $evidencia = Evidencia::query()->create([
            'uuid_cliente' => $uuidCliente,
            'tipo' => $tipo,
            // La ruta del ADR 0009 lleva el `id`, que recién existe después
            // del INSERT — mismo orden que `RegistrarEvidencia`.
            'archivo_url' => $uuidCliente,
            'hash' => (string) hash_file('sha256', $origen),
            'subido_por' => $pilotoCi === null ? null : $this->personaIdPorCi($pilotoCi),
            'fecha' => $fecha,
        ]);

        $ruta = sprintf(
            'evidencias/%s/%s/%s-%d.jpeg',
            $tipo->value,
            date('Y/m', (int) strtotime($fecha)),
            $uuidCliente,
            $evidencia->id,
        );

        Storage::disk('r2')->put($ruta, (string) file_get_contents($origen));

        $evidencia->archivo_url = $ruta;
        $evidencia->save();

        return $evidencia;
    }

    // ------------------------------------------------------------------
    // Sesiones y lo que cuelga de cada una
    // ------------------------------------------------------------------

    /**
     * @param  list<string>  $trabajosClave
     */
    private function sembrarSesiones(array $trabajosClave): void
    {
        $jefeCampo = $this->personaIdPorCi(PersonalDemoSeeder::CI_JEFE_CAMPO);

        foreach (self::SESIONES as $clave => [$trabajoClave, $pilotoCi, $auxiliarCi, $dron, $hectareas, $litros, $inicio, $fin, $motivo, $captura, $destino]) {
            $trabajo = $this->trabajos[$trabajoClave] ?? null;

            if ($trabajo === null || ! in_array($trabajoClave, $trabajosClave, true)) {
                continue;
            }

            $this->secuenciaPorTrabajo[$trabajoClave] = ($this->secuenciaPorTrabajo[$trabajoClave] ?? 0) + 1;
            $acumulado = $this->acumuladoPorDron[$dron] ?? '0.00';

            $sesion = $this->maquinaSesion->abrir([
                'uuid_cliente' => $this->uuid('sesion', $clave),
                'trabajo_id' => $trabajo->id,
                'secuencia' => $this->secuenciaPorTrabajo[$trabajoClave],
                'piloto_id' => $this->personaIdPorCi($pilotoCi),
                'auxiliar_id' => $auxiliarCi === null ? null : $this->personaIdPorCi($auxiliarCi),
                'dron_id' => $this->drones[$dron] ?? null,
                'hectarea_inicial_acumulada' => $acumulado,
                'inicio' => $inicio,
            ]);

            $this->condiciones($sesion, $trabajo, $clave, $inicio);

            if ($destino === 'abierta') {
                $this->sesiones[$clave] = $sesion;

                continue; // en curso: sin recargas cerradas, sin fin
            }

            $this->recargas($sesion, $clave, $litros, $inicio);
            $this->pausa($clave, $sesion);

            // `BigDecimal` y no `bcadd`: `ext-bcmath` no está instalada.
            $this->acumuladoPorDron[$dron] = (string) BigDecimal::of($acumulado)->plus($hectareas)->toScale(2);

            $this->maquinaSesion->cerrar($sesion, $this->uuid('cierre', $clave), (string) $fin, (string) $motivo, $hectareas);

            $sesion->litros_consumidos = $litros;
            $sesion->captura_rc_id = $captura === null ? null : ($this->evidencias[$captura] ?? null)?->id;
            $sesion->save();

            $this->incidencia($sesion, $clave);

            match ($destino) {
                // Validar es lo que genera el devengo (invariante 3); el
                // validador nunca es el piloto de la sesión (invariante 4).
                'validar' => $this->validarSesion->ejecutar($sesion, $jefeCampo),
                'rechazar' => $this->rechazarSesion->ejecutar($sesion, 'Las hectáreas declaradas (25,00) no coinciden con la captura del RC (18,40 ha). Se repite el vuelo.', $jefeCampo),
                default => null,
            };

            $this->sesiones[$clave] = $sesion;
        }
    }

    private function condiciones(Sesion $sesion, Trabajo $trabajo, string $clave, string $inicio): void
    {
        // S-11 arrancó con viento por encima del límite de la OT (18 km/h) y
        // el agrónomo autorizó igual: es lo que sostiene la alerta de
        // condiciones forzadas.
        $forzada = $clave === 'S-11';

        Condiciones::query()->create([
            'uuid_cliente' => $this->uuid('condiciones', $clave),
            'trabajo_id' => $trabajo->id,
            'sesion_id' => $sesion->id,
            'momento' => 'inicio_sesion',
            'viento_kmh' => $forzada ? '21.00' : (string) (8 + (crc32($clave) % 7)).'.00',
            'temperatura_c' => $forzada ? '31.00' : (string) (22 + (crc32($clave) % 6)).'.50',
            'humedad_pct' => $forzada ? '58.00' : (string) (70 + (crc32($clave) % 18)).'.00',
            'autorizado' => ! $forzada,
            'observacion_agronomo' => $forzada
                ? 'Viento por encima del límite. Autorizo igual para no perder la ventana antes de la lluvia; cortar si pasa de 25 km/h.'
                : null,
            'firma_observacion' => $forzada ? 'Ing. Agr. Daniel Terceros' : null,
        ]);
    }

    private function recargas(Sesion $sesion, string $clave, string $litros, string $inicio): void
    {
        $baterias = match (true) {
            str_starts_with((string) $sesion->dron?->identificador, 'AG-07') => ['BAT-01', 'BAT-02', 'BAT-03'],
            default => ['BAT-04', 'BAT-05', 'BAT-06'],
        };

        $cantidad = BigDecimal::of($litros)->isGreaterThan('400') ? 3 : 2;
        $porRecarga = (string) BigDecimal::of($litros)->dividedBy($cantidad, 2, RoundingMode::Down);
        $hora = CarbonImmutable::parse($inicio);

        for ($indice = 0; $indice < $cantidad; $indice++) {
            // En la primera recarga de S-02 la batería sale caliente: sostiene
            // la alerta `bateria_caliente`. S-11 lleva un retraso por filtro.
            $caliente = $clave === 'S-02' && $indice === 0;
            $retraso = $clave === 'S-11' && $indice === 1;
            $horaRecarga = $hora->addMinutes(25 + $indice * 35);

            $recarga = Recarga::query()->create([
                'uuid_cliente' => $this->uuid('recarga', $clave.'-'.$indice),
                'sesion_id' => $sesion->id,
                'secuencia' => $indice + 1,
                'litros_caldo' => $porRecarga,
                'bateria_saliente_id' => $baterias[$indice % count($baterias)],
                'temperatura_bateria_c' => $caliente ? '68.50' : (string) (39 + $indice * 2).'.00',
                'alerta_temperatura' => $caliente,
                'motivo_retraso_caldo' => $retraso ? 'filtro_tapado' : null,
                'hora_retraso' => $retraso ? $horaRecarga->addMinutes(3)->toDateTimeString() : null,
                'litros_combustible_generador' => '4.00',
                'hora' => $horaRecarga->toDateTimeString(),
            ]);

            // El mismo evento que dispara el motor de sync: suma un ciclo a la
            // batería que salió del dron (Mantenimiento).
            event(new RecargaRegistrada($recarga->bateria_saliente_id));

            if ($caliente) {
                $this->generarAlerta->porBateriaCaliente($recarga, $sesion);
            }
        }
    }

    private function pausa(string $clave, Sesion $sesion): void
    {
        if (! isset(self::PAUSAS[$clave])) {
            return;
        }

        [$causa, $inicio, $fin] = self::PAUSAS[$clave];

        $this->registrarPausa->ejecutar($sesion->id, $causa, $inicio, $fin);
    }

    private function incidencia(Sesion $sesion, string $clave): void
    {
        if (! isset(self::INCIDENCIAS[$clave])) {
            return;
        }

        [$tipo, $descripcion, $hora, $archivo] = self::INCIDENCIAS[$clave];
        $foto = $this->evidencias[$archivo] ?? null;

        if ($foto === null) {
            return; // `evidencia_foto_id` es NOT NULL: sin foto no hay incidencia
        }

        Incidencia::query()->create([
            'uuid_cliente' => $this->uuid('incidencia', $clave),
            'sesion_id' => $sesion->id,
            'tipo' => $tipo,
            'descripcion' => $descripcion,
            'hora' => $hora,
            'evidencia_foto_id' => $foto->id,
        ]);
    }

    // ------------------------------------------------------------------
    // Cierre de trabajos, actas, reportes, alertas y estadías
    // ------------------------------------------------------------------

    /**
     * Cierra los trabajos cuyas sesiones (todas) ya están decididas y cubren
     * las hectáreas asignadas. T-SM03 queda abierto (una sesión pendiente) y
     * T-SM05 también (una rechazada y una en curso).
     *
     * @param  list<string>  $claves
     */
    private function cerrarTrabajos(array $claves): void
    {
        foreach ($claves as $clave) {
            $trabajo = $this->trabajos[$clave] ?? null;

            if ($trabajo === null) {
                continue;
            }

            $ultimaSesion = Sesion::query()
                ->where('trabajo_id', $trabajo->id)
                ->orderByDesc('secuencia')
                ->first();

            if ($ultimaSesion?->fin === null) {
                continue;
            }

            $this->maquinaTrabajo->cerrar($trabajo, $this->uuid('cierre-trabajo', $clave), $ultimaSesion->fin->toDateTimeString());
        }
    }

    private function evidenciaEquipo(string $claveTrabajo, string $horasVuelo, string $fecha): void
    {
        $trabajo = $this->trabajos[$claveTrabajo] ?? null;

        if ($trabajo === null) {
            return;
        }

        $fotos = [];

        foreach ([TipoEvidencia::FotoControl, TipoEvidencia::FotoCicloBateriaBalanceo, TipoEvidencia::FotoDronLimpio] as $tipo) {
            // Las 21 capturas ya están repartidas una a una; para las tres
            // fotos de chequeo del equipo se reutiliza `rc_01.jpeg`.
            $evidencia = $this->evidenciaDesdeCaptura('rc_01.jpeg', $tipo, $claveTrabajo, $this->pilotoDelTrabajo($claveTrabajo), $fecha);

            if ($evidencia === null) {
                return;
            }

            $fotos[$tipo->value] = $evidencia;
        }

        EvidenciaEquipo::query()->create([
            'uuid_cliente' => $this->uuid('evidencia_equipo', $claveTrabajo),
            'trabajo_id' => $trabajo->id,
            'horas_vuelo_dron' => $horasVuelo,
            'foto_control_id' => $fotos[TipoEvidencia::FotoControl->value]->id,
            'foto_ciclo_bateria_balanceo_id' => $fotos[TipoEvidencia::FotoCicloBateriaBalanceo->value]->id,
            'foto_dron_limpio_id' => $fotos[TipoEvidencia::FotoDronLimpio->value]->id,
        ]);
    }

    /**
     * @param  list<string>  $claves
     */
    private function actasYReportes(array $claves): void
    {
        foreach ($claves as $clave) {
            $trabajo = ($this->trabajos[$clave] ?? null)?->refresh();

            if ($trabajo === null) {
                continue;
            }

            $acta = $this->generarActa->ejecutar($trabajo, $this->uuid('acta', $clave));

            if (isset(self::ACTAS_FIRMADAS[$clave])) {
                [$archivo, $firmante, $fechaFirma] = self::ACTAS_FIRMADAS[$clave];
                $firma = $this->evidencias[$archivo] ?? null;

                if ($firma !== null) {
                    // Firmar el acta genera también el reporte técnico.
                    $this->firmarActa->ejecutar($acta, $firma->uuid_cliente, $firmante, $fechaFirma);

                    continue;
                }
            }

            // Acta pendiente de firma: el reporte técnico se genera aparte.
            $this->generarReporte->ejecutar($trabajo->refresh());
        }
    }

    private function alertas(): void
    {
        // Condiciones forzadas de S-11.
        $condicionesForzadas = Condiciones::query()->where('uuid_cliente', $this->uuid('condiciones', 'S-11'))->first();

        if ($condicionesForzadas !== null) {
            $this->generarAlerta->porCondicionesForzadas($condicionesForzadas);
        }

        // T-SM02: 31,00 + 30,50 = 61,50 ha sobre un lote de 60,00.
        if (isset($this->trabajos['T-SM02'])) {
            $this->generarAlerta->porSumaExcedidaSiCorresponde($this->trabajos['T-SM02']->refresh());
        }

        // Con tres recargas calientes en el mismo dron saltaría «dron
        // sospechoso»; en la demo alcanza con una atendida y las otras
        // pendientes.
        $bateriaCaliente = Alerta::query()->where('tipo', 'bateria_caliente')->orderBy('id')->first();

        if ($bateriaCaliente !== null) {
            $this->atenderAlerta->ejecutar($bateriaCaliente, $this->personaIdPorCi(PersonalDemoSeeder::CI_ENCARGADA));
        }
    }

    private function estadias(): void
    {
        $sanMarcos = Propiedad::query()->where('nombre', 'San Marcos')->value('id');
        $elCarmen = Propiedad::query()->where('nombre', 'El Carmen')->value('id');
        $vehiculos = Vehiculo::query()->pluck('id', 'identificador')->all();

        if ($sanMarcos === null || $elCarmen === null) {
            return;
        }

        $pailon = $this->equipos[CuadrillasDemoSeeder::CODIGO_PAILON];
        $sanJulian = $this->equipos[CuadrillasDemoSeeder::CODIGO_SAN_JULIAN];

        $this->registrarEstadia->ejecutar($pailon, (int) $sanMarcos, '2026-08-02 22:00:00', TipoAlojamiento::Hacienda, $vehiculos['CAM-01'] ?? null, 'Primera aplicación de San Marcos. Dormimos en el casco de la hacienda.', '2026-08-06 16:00:00');
        $this->registrarEstadia->ejecutar($sanJulian, (int) $elCarmen, '2026-08-19 20:00:00', TipoAlojamiento::Pueblo, $vehiculos['CAM-03'] ?? null, 'Alojamiento en Pailón, a 9 km del campo.', '2026-08-21 17:30:00');
        $this->registrarEstadia->ejecutar($pailon, (int) $sanMarcos, '2026-09-07 22:00:00', TipoAlojamiento::Hacienda, $vehiculos['CAM-01'] ?? null, null, '2026-09-09 15:00:00');
        // Abierta: la cuadrilla de San Julián sigue en San Marcos.
        $this->registrarEstadia->ejecutar($sanJulian, (int) $sanMarcos, '2026-09-21 22:00:00', TipoAlojamiento::Camping, $vehiculos['CAM-03'] ?? null, 'Carpa junto al lote SM-05 para arrancar de madrugada.', null);
    }

    // ------------------------------------------------------------------
    // Ayudas
    // ------------------------------------------------------------------

    private function pilotoDelTrabajo(string $trabajoClave): ?string
    {
        foreach (self::SESIONES as $fila) {
            if ($fila[0] === $trabajoClave) {
                return $fila[1];
            }
        }

        return null;
    }

    private function inicioDelTrabajo(string $trabajoClave): string
    {
        foreach (self::SESIONES as $fila) {
            if ($fila[0] === $trabajoClave) {
                return $fila[6];
            }
        }

        return '2026-08-01 10:00:00';
    }
}
