<?php

namespace Database\Seeders\Demo;

use App\Dominios\Campania\Aplicacion\CambiarEstadoCampania;
use App\Dominios\Campania\Aplicacion\CrearCampania;
use App\Dominios\Campania\Dominio\EstadoCampania;
use App\Dominios\Campania\Infraestructura\Eloquent\Campania;
use App\Dominios\Comercial\Aplicacion\ActualizarUbicacionMapaPropiedad;
use App\Dominios\Comercial\Aplicacion\CrearCliente;
use App\Dominios\Comercial\Aplicacion\CrearLote;
use App\Dominios\Comercial\Aplicacion\CrearPropiedad;
use App\Dominios\Comercial\Aplicacion\GuardarSiembraCampania;
use App\Dominios\Comercial\Aplicacion\MaquinaEstados\MaquinaEstadosContrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cultivo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Comercial: campañas, clientes con sus contactos, propiedades ubicadas en
 * el mapa, lotes con terreno, siembras por campaña y contratos en todos los
 * estados que la máquina de estados permite alcanzar desde un alta:
 *
 * | Contrato                 | Campaña    | Estado     |
 * |--------------------------|------------|------------|
 * | San Marcos               | INV2026    | vigente    |
 * | El Carmen                | INV2026    | vigente    |
 * | Santa Rosa               | INV2026    | vigente    |
 * | Valle Esperanza          | INV2026    | pausado    |
 * | San Marcos (2025-2026)   | VERANO2026 | finalizado |
 * | Valle Esperanza (verano) | VERANO2027 | borrador   |
 * | Santa Rosa (verano)      | VERANO2027 | cancelado  |
 *
 * `activar()` exige que la fecha de inicio no sea pasada: para dar por
 * vigentes contratos que arrancaron hace meses se corre el reloj de Carbon a
 * esa fecha solo durante la activación — el estado sigue pasando por la
 * máquina, con sus guardas de lotes ocupados incluidas (invariante 7).
 *
 * Centinela: el NIT de Agrícola San Marcos.
 */
class CarteraDemoSeeder extends Seeder
{
    use SoporteDemo;

    public const NIT_SAN_MARCOS = '1088340017';

    public const NIT_EL_CARMEN = '1099751028';

    public const NIT_SANTA_ROSA = '1071482039';

    public const NIT_VALLE_ESPERANZA = '1023456022';

    public const CAMPANIA_INVIERNO = 'INV2026';

    public const CAMPANIA_VERANO_CERRADA = 'VERANO2026';

    public const CAMPANIA_VERANO_ABIERTA = 'VERANO2027';

    public const PORTAL_SAN_MARCOS = 'portal.sanmarcos';

    public const PORTAL_EL_CARMEN = 'portal.elcarmen';

    private const DEPARTAMENTO_SANTA_CRUZ = 1;

    /** Cultivos del catálogo, por nombre común. */
    private const TRIGO = 'Trigo';

    private const GIRASOL = 'Girasol';

    private const SORGO = 'Sorgo';

    private const SOYA = 'Soya';

    public function __construct(
        private readonly CrearCampania $crearCampania,
        private readonly CambiarEstadoCampania $cambiarEstadoCampania,
        private readonly CrearCliente $crearCliente,
        private readonly CrearPropiedad $crearPropiedad,
        private readonly ActualizarUbicacionMapaPropiedad $ubicarPropiedad,
        private readonly CrearLote $crearLote,
        private readonly GuardarSiembraCampania $guardarSiembra,
        private readonly MaquinaEstadosContrato $maquinaContrato,
    ) {}

    public function run(): void
    {
        if (Cliente::query()->where('nit', self::NIT_SAN_MARCOS)->exists()) {
            return;
        }

        $campanias = $this->campanias();

        $sanMarcos = $this->sanMarcos($campanias);
        $elCarmen = $this->elCarmen($campanias);
        $santaRosa = $this->santaRosa($campanias);
        $this->valleEsperanza($campanias);

        // La de verano 2025-2026 ya terminó: se cierra después de cargar su
        // contrato finalizado, porque la máquina no admite contratos nuevos
        // sobre una campaña cerrada.
        $this->cambiarEstadoCampania->ejecutar($campanias[self::CAMPANIA_VERANO_CERRADA], EstadoCampania::Cerrada);

        $this->cuentasDelPortal($sanMarcos, $elCarmen, $santaRosa);
    }

    /**
     * @return array<string, Campania>
     */
    private function campanias(): array
    {
        $invierno = Campania::query()->where('codigo', self::CAMPANIA_INVIERNO)->first();

        if ($invierno === null) {
            $invierno = $this->crearCampania->ejecutar(self::CAMPANIA_INVIERNO, 'Invierno 2026', '2026-05-01', '2026-10-15', 'invierno');
            $this->cambiarEstadoCampania->ejecutar($invierno, EstadoCampania::Abierta);
        }

        $veranoCerrada = Campania::query()->where('codigo', self::CAMPANIA_VERANO_CERRADA)->first();

        if ($veranoCerrada === null) {
            $veranoCerrada = $this->crearCampania->ejecutar(self::CAMPANIA_VERANO_CERRADA, 'Verano 2025-2026', '2025-10-15', '2026-04-30', 'verano');
            $this->cambiarEstadoCampania->ejecutar($veranoCerrada, EstadoCampania::Abierta);
        }

        // La del dueño (VERANO2027, abierta). Si no estuviera, se crea igual.
        $veranoAbierta = Campania::query()->where('codigo', self::CAMPANIA_VERANO_ABIERTA)->first();

        if ($veranoAbierta === null) {
            $veranoAbierta = $this->crearCampania->ejecutar(self::CAMPANIA_VERANO_ABIERTA, '2026-2027', '2026-10-27', '2027-05-20', 'verano');
            $this->cambiarEstadoCampania->ejecutar($veranoAbierta, EstadoCampania::Abierta);
        }

        return [
            self::CAMPANIA_INVIERNO => $invierno,
            self::CAMPANIA_VERANO_CERRADA => $veranoCerrada,
            self::CAMPANIA_VERANO_ABIERTA => $veranoAbierta,
        ];
    }

    /**
     * @param  array<string, Campania>  $campanias
     */
    private function sanMarcos(array $campanias): Cliente
    {
        $cliente = $this->crearCliente->ejecutar(
            'Agrícola San Marcos S.R.L.',
            'San Marcos',
            self::NIT_SAN_MARCOS,
            'juridica',
            'Av. Cristo Redentor 4to anillo, Santa Cruz de la Sierra',
            [
                ['tipo' => 'dueno', 'nombre' => 'Marcos Áñez Vaca', 'telefono' => '+591 70100001', 'email' => 'manez@sanmarcos.demo', 'observaciones' => null],
                ['tipo' => 'agronomo', 'nombre' => 'Ing. Agr. Lorena Suárez', 'telefono' => '+591 70100002', 'email' => 'lsuarez@sanmarcos.demo', 'observaciones' => 'Emite las órdenes y firma las actas.'],
                ['tipo' => 'encargado_propiedad', 'nombre' => 'Nelson Quispe', 'telefono' => '+591 70100003', 'email' => null, 'observaciones' => 'Entrega el caldo y abre la tranquera. No firma documentos.'],
                ['tipo' => 'finanzas', 'nombre' => 'Lic. Patricia Roca', 'telefono' => '+591 70100004', 'email' => 'proca@sanmarcos.demo', 'observaciones' => 'Recibe las facturas.'],
            ],
        );

        $propiedad = $this->propiedad($cliente, 'San Marcos', '520.00', 9, 39, 'Cuatro Cañadas', '#218349', '-16.701900', '-62.823900', 'Km 28 camino a Cuatro Cañadas');

        $lotes = $this->lotes($propiedad, 'SM', [
            ['80.00', 'ninguno', 'limpio', 'Canal de drenaje al norte.'],
            ['60.00', 'algunos', 'pocos_obstaculos', 'Camino vecinal al este con tránsito de motos.'],
            ['70.00', 'ninguno', 'limpio', null],
            ['50.00', 'varios', 'algunos_obstaculos', 'Torre de alta tensión en la esquina suroeste.'],
            ['60.00', 'ninguno', 'limpio', null],
            ['40.00', 'empinado', 'muchos_obstaculos', 'Curichi al centro: no sobrevolar con carga completa.'],
        ]);

        $this->siembra($propiedad, $campanias[self::CAMPANIA_INVIERNO], $lotes, self::TRIGO, 'crecimiento', '2026-05-20', '2026-09-30');
        $this->siembra($propiedad, $campanias[self::CAMPANIA_VERANO_CERRADA], array_slice($lotes, 0, 3), self::SOYA, 'cosecha', '2025-11-20', '2026-03-25');

        // Vigente: el contrato principal de la demo, con horarios por lote.
        $this->contratoVigente($cliente, $campanias[self::CAMPANIA_INVIERNO], $lotes, [
            'hectareas_contratadas' => '360.00',
            'aplicaciones_previstas' => 3,
            'precio_ha' => '72.00',
            'adelanto_monto' => '25920.00',
            'fecha_inicio' => '2026-07-20',
            'fecha_fin' => '2026-10-10',
            'brinda_alimentacion' => true,
            'brinda_hospedaje' => true,
            'brinda_combustible' => false,
            'observaciones_logistica' => 'La cuadrilla duerme en el casco de la hacienda. Almuerzo a las 12:30 en el comedor del personal.',
        ], ['06:00', '10:30']);

        // Finalizado: la campaña de verano anterior, ya cerrada.
        $anterior = $this->contratoVigente($cliente, $campanias[self::CAMPANIA_VERANO_CERRADA], array_slice($lotes, 0, 3), [
            'hectareas_contratadas' => '210.00',
            'aplicaciones_previstas' => 2,
            'precio_ha' => '70.00',
            'adelanto_monto' => '14700.00',
            'fecha_inicio' => '2025-11-01',
            'fecha_fin' => '2026-03-31',
            'brinda_alimentacion' => true,
            'brinda_hospedaje' => false,
            'brinda_combustible' => false,
        ], null);
        $this->maquinaContrato->finalizar($anterior);

        return $cliente;
    }

    /**
     * @param  array<string, Campania>  $campanias
     */
    private function elCarmen(array $campanias): Cliente
    {
        $cliente = $this->crearCliente->ejecutar(
            'Sociedad Agrícola El Carmen S.A.',
            'El Carmen',
            self::NIT_EL_CARMEN,
            'juridica',
            'Calle Sucre 245, Pailón',
            [
                ['tipo' => 'gerente_general', 'nombre' => 'Fernando Roca Melgar', 'telefono' => '+591 70200001', 'email' => 'froca@elcarmen.demo', 'observaciones' => null],
                ['tipo' => 'agronomo', 'nombre' => 'Ing. Agr. Daniel Terceros', 'telefono' => '+591 70200002', 'email' => 'dterceros@elcarmen.demo', 'observaciones' => 'Define dosis y ventana de aplicación.'],
                ['tipo' => 'secretario', 'nombre' => 'Carla Justiniano', 'telefono' => '+591 70200003', 'email' => 'cjustiniano@elcarmen.demo', 'observaciones' => null],
                ['tipo' => 'otro', 'tipo_otro' => 'Apicultor vecino', 'nombre' => 'Don Ramiro Salvatierra', 'telefono' => '+591 70200004', 'email' => null, 'observaciones' => 'Avisarle 24 h antes de aplicar insecticida por las colmenas del sureste.'],
            ],
        );

        $propiedad = $this->propiedad($cliente, 'El Carmen', '640.00', 11, 44, 'Pailón', '#1F80AD', '-16.903300', '-62.718800', 'Km 9 camino a Pailón Norte');

        $lotes = $this->lotes($propiedad, 'EC', [
            ['90.00', 'ninguno', 'limpio', 'Colmenas del vecino al sureste: no aplicar con viento del noroeste.'],
            ['70.00', 'algunos', 'pocos_obstaculos', 'Línea de media tensión cruzando el lote de este a oeste.'],
            ['60.00', 'ninguno', 'limpio', null],
            ['40.00', 'ninguno', 'limpio', null],
            ['40.00', 'varios', 'algunos_obstaculos', 'Cortina de eucaliptos al oeste.'],
        ]);

        $this->siembra($propiedad, $campanias[self::CAMPANIA_INVIERNO], $lotes, self::GIRASOL, 'floracion', '2026-06-05', '2026-10-05');

        $this->contratoVigente($cliente, $campanias[self::CAMPANIA_INVIERNO], $lotes, [
            'hectareas_contratadas' => '300.00',
            'aplicaciones_previstas' => 2,
            'precio_ha' => '65.00',
            'adelanto_monto' => null,
            'fecha_inicio' => '2026-08-01',
            'fecha_fin' => '2026-10-15',
            'brinda_alimentacion' => false,
            'brinda_hospedaje' => false,
            'brinda_combustible' => true,
            'observaciones_logistica' => 'El cliente pone el diésel del generador; se carga en el surtidor de la hacienda con vale firmado.',
        ], ['05:30', '10:00']);

        return $cliente;
    }

    /**
     * @param  array<string, Campania>  $campanias
     */
    private function santaRosa(array $campanias): Cliente
    {
        $cliente = $this->crearCliente->ejecutar(
            'Rosa Melgar de Áñez',
            'Estancia Santa Rosa',
            self::NIT_SANTA_ROSA,
            'fisica',
            'Colonia Okinawa 1',
            [
                ['tipo' => 'dueno', 'nombre' => 'Rosa Melgar de Áñez', 'telefono' => '+591 70300001', 'email' => 'rmelgar@santarosa.demo', 'observaciones' => 'Autoriza personalmente cada aplicación.'],
                ['tipo' => 'agronomo', 'nombre' => 'Ing. Agr. Pablo Cuéllar', 'telefono' => '+591 70300002', 'email' => 'pcuellar@santarosa.demo', 'observaciones' => null],
            ],
        );

        $propiedad = $this->propiedad($cliente, 'Santa Rosa', '190.00', 2, 7, 'Okinawa Uno', '#CD5E1D', '-17.218400', '-62.895100', 'Colonia Okinawa 1, camino al río Grande');

        $lotes = $this->lotes($propiedad, 'SR', [
            ['45.00', 'ninguno', 'limpio', 'Casco de la estancia y galpones al centro.'],
            ['40.00', 'algunos', 'pocos_obstaculos', null],
            ['35.00', 'ninguno', 'limpio', null],
            ['30.00', 'ninguno', 'limpio', 'Cortina de eucaliptos perimetral.'],
        ]);

        $this->siembra($propiedad, $campanias[self::CAMPANIA_INVIERNO], $lotes, self::TRIGO, 'germinacion', '2026-08-25', '2026-12-10');
        $this->siembra($propiedad, $campanias[self::CAMPANIA_VERANO_ABIERTA], array_slice($lotes, 0, 2), self::SOYA, 'preparacion', '2026-11-15', '2027-03-20');

        $this->contratoVigente($cliente, $campanias[self::CAMPANIA_INVIERNO], $lotes, [
            'hectareas_contratadas' => '150.00',
            'aplicaciones_previstas' => 2,
            'precio_ha' => '75.00',
            'adelanto_monto' => '5000.00',
            'fecha_inicio' => '2026-09-01',
            'fecha_fin' => '2026-10-15',
            'brinda_alimentacion' => true,
            'brinda_hospedaje' => false,
            'brinda_combustible' => false,
        ], ['06:30', '09:30']);

        // Cancelado: el de verano se firmó y se cayó antes de arrancar.
        $cancelado = $this->maquinaContrato->crear($this->atributosContrato($cliente, $campanias[self::CAMPANIA_VERANO_ABIERTA], [
            'hectareas_contratadas' => '85.00',
            'aplicaciones_previstas' => 1,
            'precio_ha' => '80.00',
            'adelanto_monto' => null,
            'fecha_inicio' => '2026-11-10',
            'fecha_fin' => '2027-01-31',
        ]));
        $this->lotesDelContrato($cancelado, array_slice($lotes, 0, 2), null);
        $this->maquinaContrato->cancelar($cancelado);

        return $cliente;
    }

    /**
     * @param  array<string, Campania>  $campanias
     */
    private function valleEsperanza(array $campanias): Cliente
    {
        $cliente = $this->crearCliente->ejecutar(
            'Colonia Menonita Valle Esperanza',
            'Valle Esperanza',
            self::NIT_VALLE_ESPERANZA,
            'juridica',
            'Colonia Valle Esperanza, San Julián',
            [
                ['tipo' => 'dueno', 'nombre' => 'Jakob Friesen', 'telefono' => '+591 70400001', 'email' => null, 'observaciones' => 'Solo atiende llamadas, no mensajes.'],
                ['tipo' => 'encargado_propiedad', 'nombre' => 'Peter Wiebe', 'telefono' => '+591 70400002', 'email' => null, 'observaciones' => null],
            ],
        );

        $propiedad = $this->propiedad($cliente, 'Valle Esperanza', '380.00', 9, 37, 'San Julián', '#4A30A6', '-16.882000', '-62.598000', 'Campo 12, Colonia Valle Esperanza');

        $lotes = $this->lotes($propiedad, 'VE', [
            ['80.00', 'ninguno', 'limpio', null],
            ['50.00', 'ninguno', 'limpio', null],
            ['70.00', 'algunos', 'pocos_obstaculos', 'Silo bolsa sobre la cabecera norte.'],
            ['50.00', 'ninguno', 'limpio', null],
        ]);

        $this->siembra($propiedad, $campanias[self::CAMPANIA_INVIERNO], array_slice($lotes, 2, 2), self::SORGO, 'crecimiento', '2026-06-15', '2026-10-10');
        $this->siembra($propiedad, $campanias[self::CAMPANIA_VERANO_ABIERTA], array_slice($lotes, 0, 2), self::SOYA, 'preparacion', '2026-11-15', '2027-03-20');

        // Pausado: arrancó vigente y el cliente pidió parar por lluvias.
        $pausado = $this->contratoVigente($cliente, $campanias[self::CAMPANIA_INVIERNO], array_slice($lotes, 2, 2), [
            'hectareas_contratadas' => '120.00',
            'aplicaciones_previstas' => 2,
            'precio_ha' => '68.00',
            'adelanto_monto' => '4080.00',
            'fecha_inicio' => '2026-08-10',
            'fecha_fin' => '2026-10-15',
            'brinda_alimentacion' => false,
            'brinda_hospedaje' => true,
            'brinda_combustible' => false,
        ], ['06:00', '09:00']);
        $this->maquinaContrato->pausar($pausado);

        // Borrador: el de verano todavía se está negociando.
        $borrador = $this->maquinaContrato->crear($this->atributosContrato($cliente, $campanias[self::CAMPANIA_VERANO_ABIERTA], [
            'hectareas_contratadas' => '130.00',
            'aplicaciones_previstas' => 2,
            'precio_ha' => '82.00',
            'adelanto_monto' => null,
            'fecha_inicio' => '2026-11-05',
            'fecha_fin' => '2027-03-30',
            'brinda_alimentacion' => true,
            'brinda_hospedaje' => true,
            'brinda_combustible' => true,
            'observaciones_logistica' => 'Pendiente confirmar si la colonia provee el agua para el caldo.',
        ]));
        $this->lotesDelContrato($borrador, array_slice($lotes, 0, 2), null);

        return $cliente;
    }

    private function propiedad(Cliente $cliente, string $nombre, string $hectareas, int $provinciaId, int $municipioId, string $localidad, string $color, string $latitud, string $longitud, string $referencia): Propiedad
    {
        $propiedad = $this->crearPropiedad->ejecutar(
            $cliente->id,
            $nombre,
            $hectareas,
            self::DEPARTAMENTO_SANTA_CRUZ,
            $provinciaId,
            $municipioId,
            $localidad.' — '.$referencia,
            $color,
        );

        return $this->ubicarPropiedad->ejecutar($propiedad, $latitud, $longitud, $this->poligono((float) $longitud, (float) $latitud, 0.045));
    }

    /**
     * @param  list<array{string, string, string, string|null}>  $filas  [hectáreas, desnivel, limpieza, restricciones]
     * @return list<Lote>
     */
    private function lotes(Propiedad $propiedad, string $prefijo, array $filas): array
    {
        $lotes = [];
        $latitud = (float) $propiedad->latitud;
        $longitud = (float) $propiedad->longitud;

        foreach ($filas as $indice => [$hectareas, $desnivel, $limpieza, $restricciones]) {
            // Lotes en fila de oeste a este, cada uno un cuadrado proporcional
            // a sus hectáreas (1 ha ≈ 100 m de lado ≈ 0,0009°).
            $lado = sqrt((float) $hectareas) * 0.0009;
            $oeste = $longitud - 0.040 + $indice * 0.0125;
            $norte = $latitud + 0.010;

            $lotes[] = $this->crearLote->ejecutar($propiedad->id, [
                'codigo' => sprintf('%s-%02d', $prefijo, $indice + 1),
                'hectareas' => $hectareas,
                'geometria' => $this->poligonoDesde($oeste, $norte, $lado),
                'restricciones' => $restricciones,
                'desnivel' => $desnivel,
                'limpieza' => $limpieza,
            ]);
        }

        return $lotes;
    }

    /**
     * @param  list<Lote>  $lotes
     */
    private function siembra(Propiedad $propiedad, Campania $campania, array $lotes, string $cultivo, string $etapa, string $fechaSiembra, string $fechaCosecha): void
    {
        $cultivoId = (int) Cultivo::query()->where('nombre_comun', $cultivo)->value('id');

        if ($cultivoId === 0) {
            return;
        }

        $filas = [];

        foreach ($lotes as $lote) {
            $filas[] = [
                'lote_id' => $lote->id,
                'cultivo_id' => $cultivoId,
                'etapa_cultivo' => $etapa,
                'hectareas_sembradas' => (string) $lote->hectareas,
                'fecha_siembra' => $fechaSiembra,
                'fecha_cosecha_estimada' => $fechaCosecha,
            ];
        }

        $this->guardarSiembra->ejecutar($propiedad->refresh()->load('lotes'), $campania->id, $filas);
    }

    /**
     * Alta en borrador + activación por la máquina de estados, con el reloj
     * corrido a la fecha de inicio para que la guarda «no arranca en el
     * pasado» vea el alta como la vio el panel ese día.
     *
     * @param  list<Lote>  $lotes
     * @param  array<string, mixed>  $atributos
     * @param  array{string, string}|null  $horario
     */
    private function contratoVigente(Cliente $cliente, Campania $campania, array $lotes, array $atributos, ?array $horario): Contrato
    {
        $contrato = $this->maquinaContrato->crear($this->atributosContrato($cliente, $campania, $atributos));
        $this->lotesDelContrato($contrato, $lotes, $horario);

        Carbon::setTestNow(Carbon::parse($atributos['fecha_inicio'])->setTime(9, 0));

        try {
            $this->maquinaContrato->activar($contrato->refresh());
        } finally {
            Carbon::setTestNow();
        }

        return $contrato->refresh();
    }

    /**
     * @param  array<string, mixed>  $atributos
     * @return array<string, mixed>
     */
    private function atributosContrato(Cliente $cliente, Campania $campania, array $atributos): array
    {
        $montoTotal = BigDecimal::of($atributos['hectareas_contratadas'])
            ->multipliedBy($atributos['aplicaciones_previstas'])
            ->multipliedBy($atributos['precio_ha'])
            ->toScale(2, RoundingMode::HalfUp);

        return [
            'cliente_id' => $cliente->id,
            'campania_id' => $campania->id,
            'monto_total' => (string) $montoTotal,
            ...$atributos,
        ];
    }

    /**
     * @param  list<Lote>  $lotes
     * @param  array{string, string}|null  $horario
     */
    private function lotesDelContrato(Contrato $contrato, array $lotes, ?array $horario): void
    {
        foreach ($lotes as $lote) {
            $contrato->lotes()->create([
                'lote_id' => $lote->id,
                'hora_inicio' => $horario[0] ?? null,
                'hora_fin' => $horario[1] ?? null,
            ]);
        }
    }

    /**
     * Cuentas del portal del cliente (invariante 5: cada una ve solo su
     * contrato). Contraseña `0000`, como el resto de la demo.
     */
    private function cuentasDelPortal(Cliente $sanMarcos, Cliente $elCarmen, Cliente $santaRosa): void
    {
        $cuentas = [
            [self::PORTAL_SAN_MARCOS, 'Portal Agrícola San Marcos', $sanMarcos, 'manez@sanmarcos.demo'],
            [self::PORTAL_EL_CARMEN, 'Portal El Carmen', $elCarmen, 'froca@elcarmen.demo'],
            ['portal.santarosa', 'Portal Estancia Santa Rosa', $santaRosa, 'rmelgar@santarosa.demo'],
        ];

        foreach ($cuentas as [$username, $nombre, $cliente, $email]) {
            if ($this->usuarioPorUsername($username) !== null) {
                continue;
            }

            $contratoId = Contrato::query()
                ->where('cliente_id', $cliente->id)
                ->where('estado', 'vigente')
                ->orderBy('id')
                ->value('id');

            if ($contratoId === null) {
                continue;
            }

            $usuario = new SecUser([
                'name' => $nombre,
                'username' => $username,
                'email' => $email,
                'password' => PersonalDemoSeeder::PASSWORD,
                'type' => TipoUsuario::Cliente,
                'contrato_id' => (int) $contratoId,
                'state' => true,
            ]);
            $usuario->save();
        }
    }

    /**
     * @return array<string, mixed> GeoJSON Polygon
     */
    private function poligono(float $longitud, float $latitud, float $radio): array
    {
        return $this->poligonoDesde($longitud - $radio, $latitud + $radio, $radio * 2);
    }

    /**
     * @return array<string, mixed> GeoJSON Polygon con esquina noroeste en (oeste, norte)
     */
    private function poligonoDesde(float $oeste, float $norte, float $lado): array
    {
        $este = round($oeste + $lado, 6);
        $sur = round($norte - $lado, 6);
        $oeste = round($oeste, 6);
        $norte = round($norte, 6);

        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [$oeste, $norte],
                [$este, $norte],
                [$este, $sur],
                [$oeste, $sur],
                [$oeste, $norte],
            ]],
        ];
    }
}
