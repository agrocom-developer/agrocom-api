<?php

namespace Database\Seeders\Demo;

use App\Dominios\Calificaciones\Aplicacion\CalificarComoCliente;
use App\Dominios\Calificaciones\Aplicacion\CalificarComoProveedor;
use App\Dominios\Disputas\Aplicacion\AbrirDisputa;
use App\Dominios\Disputas\Aplicacion\AgregarMensajeDeDisputa;
use App\Dominios\Disputas\Dominio\ParteDeDisputa;
use App\Dominios\Negociacion\Aplicacion\CancelarComoProveedor;
use App\Dominios\Negociacion\Aplicacion\Mensajeria;
use App\Dominios\Negociacion\Aplicacion\Negociar;
use App\Dominios\Negociacion\Aplicacion\PreferenciasDeAviso;
use App\Dominios\Negociacion\Aplicacion\Seguimiento;
use App\Dominios\Negociacion\Aplicacion\UbicacionDelPiloto;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Oferta;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Proveedor;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Solicitudes\Aplicacion\CancelarSolicitud;
use App\Dominios\Solicitudes\Aplicacion\CrearSolicitud;
use App\Dominios\Solicitudes\Aplicacion\PublicarSolicitud;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Cliente;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Terreno;
use Database\Seeders\Catalogo\UbicacionesSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Ramsey\Uuid\Uuid;

/**
 * Recorrido completo del marketplace por negociación para las apps: una solicitud en cada estado
 * (`negociacion-contrato.md` §1), con varias ofertas y contraofertas por rondas, chat, fotos de
 * avance y ubicación del piloto. Todo pasa por los casos de uso y las máquinas de estado reales
 * (invariante 7), con el reloj simulado: cada solicitud arranca «hace N horas» y sus pasos se
 * espacian en el tiempo, así que los plazos y las marcas de hora son relativos a `now()`.
 *
 * Idempotente: cada solicitud lleva un `uuid_cliente` determinista; volver a sembrar no duplica
 * nada. {@see RenovarVigenciaDemoSeeder} renueva el plazo de las ofertas para que la demo siga viva días después.
 *
 * Los clientes y proveedores salen de {@see ProveedoresDemoSeeder}: cuatro equipos (Agrocom con
 * Rodrigo y Valeria, Mamani con Carlos, Fumigaciones del Oriente con Marcelo).
 */
class NegociacionDemoSeeder extends Seeder
{
    /** Departamento de todos los terrenos de la demo: Santa Cruz (código del INE). */
    private const DEPARTAMENTO = '07';

    /**
     * Terrenos de la demo: cliente, nombre, provincia (código del INE), municipio, latitud y longitud
     * del centro del lote (de ahí nace el polígono), hectáreas y cultivo (ADR 0045, punto 3).
     */
    private const TERRENOS = [
        ['agro.sanrafael', 'Lote Norte', '0702', 'Warnes', '-17.5141', '-63.1668', '80', 'Soya'],
        ['agro.sanrafael', 'Las Palmas', '0701', 'Santa Cruz de la Sierra', '-17.7833', '-63.1821', '120', 'Maíz'],
        ['luis.antelo', 'Lote Sur', '0710', 'Montero', '-17.3395', '-63.2528', '120', 'Maíz'],
        ['luis.antelo', 'La Esperanza', '0710', 'Montero', '-17.3395', '-63.2528', '65', 'Soya'],
        ['luis.antelo', 'Estancia El Cerro', '0705', 'San José de Chiquitos', '-17.8450', '-60.7390', '200', 'Soya'],
        ['coop.valle.grande', 'Lote Este', '0711', 'Cuatro Cañadas', '-17.3583', '-62.5667', '160', 'Girasol'],
        ['coop.valle.grande', 'Parcela Central', '0711', 'San Julián', '-16.9164', '-62.6167', '90', 'Soya'],
        ['coop.valle.grande', 'Fundo Los Valles', '0708', 'Vallegrande', '-18.4890', '-64.1090', '70', 'Maíz'],
        ['coop.valle.grande', 'Hacienda Velasco', '0703', 'San Ignacio de Velasco', '-16.3733', '-60.9633', '300', 'Soya'],
    ];

    /** @var array<string, Cliente> */
    private array $clientes = [];

    /** @var array<string, Terreno> */
    private array $terrenos = [];

    /** @var array<string, int> username => id de usuario */
    private array $usuarios = [];

    /** @var array<string, int> */
    private array $proveedores = [];

    private int $contadorDeFotos = 0;

    public function __construct(
        private readonly CrearSolicitud $crear,
        private readonly PublicarSolicitud $publicar,
        private readonly CancelarSolicitud $cancelar,
        private readonly Negociar $negociar,
        private readonly Seguimiento $seguimiento,
        private readonly Mensajeria $mensajeria,
        private readonly UbicacionDelPiloto $ubicacion,
        private readonly CancelarComoProveedor $cancelarComoProveedor,
        private readonly PreferenciasDeAviso $preferencias,
    ) {}

    public function run(): void
    {
        foreach (['agro.sanrafael', 'luis.antelo', 'coop.valle.grande'] as $username) {
            $cliente = Cliente::query()->where('usuario_id', SecUser::query()->where('username', $username)->value('id'))->first();

            if ($cliente === null) {
                $this->command?->warn("No existe el cliente {$username}: corre primero ProveedoresDemoSeeder.");

                return;
            }

            $this->clientes[$username] = $cliente;
        }

        foreach (['rodrigo.suarez', 'valeria.montano', 'carlos.mamani', 'marcelo.terrazas', 'fumigaciones.oriente', 'agro.sanrafael', 'luis.antelo', 'coop.valle.grande'] as $username) {
            $this->usuarios[$username] = (int) SecUser::query()->where('username', $username)->value('id');
        }

        $this->proveedores = [
            'agrocom' => (int) Proveedor::query()->where('tipo', 'propio')->value('id'),
            'mamani' => (int) Proveedor::query()->where('razon_social', 'Mamani Agroservicios')->value('id'),
            'oriente' => (int) Proveedor::query()->where('razon_social', 'Fumigaciones del Oriente SRL')->value('id'),
        ];

        if (in_array(0, $this->proveedores, true) || in_array(0, $this->usuarios, true)) {
            $this->command?->warn('Faltan proveedores o cuentas de demostración: corre primero ProveedoresDemoSeeder.');

            return;
        }

        // Los terrenos apuntan al catálogo oficial de ubicaciones (idempotente: no pisa nada).
        $this->call(UbicacionesSeeder::class);

        $this->origen();
        $this->terrenos();
        $this->preferenciasDeLosPilotos();

        $ahora = $this->origen();

        try {
            $this->abiertas();
            $this->conOfertas();
            $this->adjudicadas();
            $this->enEjecucion();
            $this->finalizadas();
            $this->cerradasSinServicio();
        } finally {
            Carbon::setTestNow();
        }

        $this->command?->info('Demo de negociación sembrada a las '.$ahora->format('H:i'));
    }

    // ── Escenarios ───────────────────────────────────────────────────────────

    /** Solicitudes abiertas sin ofertas, repartidas entre valles, Chiquitania y el norte integrado. */
    private function abiertas(): void
    {
        $this->solicitud('neg-borrador', 'agro.sanrafael', 'Las Palmas', 30, '125', hace: 3, hasta: 'borrador');

        $this->solicitud('neg-abierta-chiquitania', 'luis.antelo', 'Estancia El Cerro', 180, '118', hace: 5, observaciones: 'Soya en R3. Hay un camino de tierra de 6 km desde la ruta; en lluvia se cierra.');
        $this->solicitud('neg-abierta-velasco', 'coop.valle.grande', 'Hacienda Velasco', 250, '105', hace: 9, producto: 'Fungicida triazol + estrobilurina, 0,8 L/ha', tipo: 'fungicida', observaciones: 'Lote grande y parejo, sin obstáculos. Hay galpón para cargar el producto.');
        $this->solicitud('neg-abierta-valles', 'coop.valle.grande', 'Fundo Los Valles', 60, '130', hace: 2, producto: 'Insecticida para gusano cogollero, 0,3 L/ha', tipo: 'insecticida', observaciones: 'Terreno con pendiente: avisar antes de ir para coordinar el acceso.');
        $this->solicitud('neg-abierta-warnes', 'agro.sanrafael', 'Lote Norte', 70, '120', hace: 1, urgente: true, observaciones: 'Hay una línea de alta tensión en el borde este.');
    }

    /** Solicitudes con varias ofertas y contraofertas por rondas. */
    private function conOfertas(): void
    {
        // Tres proveedores ofertan sobre las 100 ha de Las Palmas; el cliente negocia con dos.
        $this->solicitud('neg-con-ofertas-palmas', 'agro.sanrafael', 'Las Palmas', 100, '125', hace: 30, observaciones: 'Entrar por el portón de la calle lateral. Maíz de 1,8 m de altura.', despues: function (int $id): void {
            $uuid = fn (string $clave): string => $this->uuid("{$id}:{$clave}");

            $rodrigo = $this->tras(26, fn () => $this->negociar->ofertar($this->usuarios['rodrigo.suarez'], $id, ['uuid_cliente' => $uuid('rodrigo-1'), 'tipo' => 'contraoferta', 'precio_ha' => '135', 'nota' => 'Dron de 30 litros: terminamos en una jornada.']))[0];
            $carlos = $this->tras(22, fn () => $this->negociar->ofertar($this->usuarios['carlos.mamani'], $id, ['uuid_cliente' => $uuid('carlos-1'), 'tipo' => 'contraoferta', 'precio_ha' => '118']))[0];
            $this->tras(20, fn () => $this->negociar->ofertar($this->usuarios['marcelo.terrazas'], $id, ['uuid_cliente' => $uuid('marcelo-1'), 'tipo' => 'contraoferta', 'precio_ha' => '110', 'nota' => 'Tenemos disponibilidad esta semana.']));

            // El cliente le responde a Agrocom con otro precio, que sube un poco.
            $respuesta = $this->tras(14, fn () => $this->negociar->contraofertar(Negociar::LADO_CLIENTE, $this->usuarios['agro.sanrafael'], $rodrigo->id, '122', 'Tengo otra oferta más baja; ¿puedes acercarte a este precio?', $uuid('cliente-a-rodrigo')))[0];
            $this->tras(10, fn () => $this->negociar->contraofertar(Negociar::LADO_PROVEEDOR, $this->usuarios['rodrigo.suarez'], $respuesta->id, '128', null, $uuid('rodrigo-2')));

            // Y a Carlos le baja un poco el precio.
            $this->tras(8, fn () => $this->negociar->contraofertar(Negociar::LADO_CLIENTE, $this->usuarios['agro.sanrafael'], $carlos->id, '115', null, $uuid('cliente-a-carlos')));
        });

        // Dos ofertas sobre Lote Sur: una rechazada por el cliente y otra vigente.
        $this->solicitud('neg-con-ofertas-sur', 'luis.antelo', 'Lote Sur', 90, '115', hace: 20, despues: function (int $id): void {
            $uuid = fn (string $clave): string => $this->uuid("{$id}:{$clave}");

            $carlos = $this->tras(18, fn () => $this->negociar->ofertar($this->usuarios['carlos.mamani'], $id, ['uuid_cliente' => $uuid('carlos-1'), 'tipo' => 'acepta_propuesta']))[0];
            $marcelo = $this->tras(16, fn () => $this->negociar->ofertar($this->usuarios['marcelo.terrazas'], $id, ['uuid_cliente' => $uuid('marcelo-1'), 'tipo' => 'contraoferta', 'precio_ha' => '140']))[0];
            $this->tras(12, fn () => $this->negociar->rechazar(Negociar::LADO_CLIENTE, $this->usuarios['luis.antelo'], $marcelo->id));
            unset($carlos);
        });
    }

    /** Adjudicadas: el cliente eligió una oferta y ya conversan por el chat. */
    private function adjudicadas(): void
    {
        $this->solicitud('neg-adjudicada-central', 'coop.valle.grande', 'Parcela Central', 90, '122', hace: 40, observaciones: 'Preguntar por Don Gonzalo en el portón.', despues: function (int $id): void {
            $oferta = $this->tras(38, fn () => $this->negociar->ofertar($this->usuarios['marcelo.terrazas'], $id, ['uuid_cliente' => $this->uuid("{$id}:marcelo-1"), 'tipo' => 'contraoferta', 'precio_ha' => '118']))[0];
            $this->tras(30, fn () => $this->negociar->aceptar(Negociar::LADO_CLIENTE, $this->usuarios['coop.valle.grande'], $oferta->id));

            $this->conversacion($id, 'coop.valle.grande', 'marcelo.terrazas', [
                [26, 'c', 'Hola, ya quedamos en el precio. ¿Qué día pueden venir?'],
                [24, 'p', 'Buen día. Podemos el jueves temprano, si el viento lo permite.'],
                [23, 'c', 'Perfecto. El portón está a 2 km del cruce, lo espera el encargado.'],
                [2, 'p', 'Confirmado el jueves a las 7. Llevamos el dron de 40 litros.'],
            ]);
        }, ventana: [3, 5]);

        // Fecha deseada lejana a propósito: el equipo de Carlos queda libre del día 6 al 9 desde hoy
        // (30-nov a 3-dic con la demo sembrada el 24-nov) para demostrar una adjudicación sin conflicto.
        $this->solicitud('neg-adjudicada-esperanza', 'luis.antelo', 'La Esperanza', 65, '120', hace: 26, despues: function (int $id): void {
            $oferta = $this->tras(24, fn () => $this->negociar->ofertar($this->usuarios['carlos.mamani'], $id, ['uuid_cliente' => $this->uuid("{$id}:carlos-1"), 'tipo' => 'acepta_propuesta']))[0];
            $this->tras(20, fn () => $this->negociar->aceptar(Negociar::LADO_CLIENTE, $this->usuarios['luis.antelo'], $oferta->id));

            $this->conversacion($id, 'luis.antelo', 'carlos.mamani', [
                [18, 'p', 'Don Luis, le escribe Carlos Mamani. ¿Le parece que vayamos mañana?'],
                [16, 'c', 'Sí, mañana está bien. Avíseme cuando salga.'],
            ]);
        }, ventana: [16, 19]);
    }

    /** En ejecución: el piloto marcó inicio, sube fotos de avance y comparte su última ubicación. */
    private function enEjecucion(): void
    {
        $this->solicitud('neg-ejecucion-norte', 'agro.sanrafael', 'Lote Norte', 80, '115', hace: 60, despues: function (int $id): void {
            $oferta = $this->tras(58, fn () => $this->negociar->ofertar($this->usuarios['rodrigo.suarez'], $id, ['uuid_cliente' => $this->uuid("{$id}:rodrigo-1"), 'tipo' => 'acepta_propuesta']))[0];
            $this->tras(50, fn () => $this->negociar->aceptar(Negociar::LADO_CLIENTE, $this->usuarios['agro.sanrafael'], $oferta->id));

            $piloto = $this->usuarios['rodrigo.suarez'];
            $this->tras(5, fn () => $this->seguimiento->iniciar($piloto, $id, ['uuid_cliente' => $this->uuid("{$id}:inicio"), 'secuencia' => 1]));
            $this->avance($id, $piloto, 4, 2, '32', 'Primera mitad del lote, franja oeste.');
            $this->avance($id, $piloto, 2, 3, '64', 'Terminamos el sector central.');

            $this->conversacion($id, 'agro.sanrafael', 'rodrigo.suarez', [
                [4, 'p', 'Empezamos. La franja oeste ya está cubierta.'],
                [3, 'c', 'Gracias por avisar. Mañana a la mañana paso por el lote.'],
                [1, 'p', 'Seguimos con el sector este después del almuerzo.'],
            ]);

            $this->enMovimiento($id, $piloto, -17.5120, -63.1700);
        }, ventana: [-1, 2]);

        $this->solicitud('neg-ejecucion-este', 'coop.valle.grande', 'Lote Este', 150, '110', hace: 30, observaciones: 'Girasol en floración: fumigar temprano, antes de que salgan las abejas.', despues: function (int $id): void {
            $oferta = $this->tras(29, fn () => $this->negociar->ofertar($this->usuarios['marcelo.terrazas'], $id, ['uuid_cliente' => $this->uuid("{$id}:marcelo-1"), 'tipo' => 'acepta_propuesta']))[0];
            $this->tras(24, fn () => $this->negociar->aceptar(Negociar::LADO_CLIENTE, $this->usuarios['coop.valle.grande'], $oferta->id));

            $piloto = $this->usuarios['marcelo.terrazas'];
            $this->tras(3, fn () => $this->seguimiento->iniciar($piloto, $id, ['uuid_cliente' => $this->uuid("{$id}:inicio"), 'secuencia' => 1]));
            $this->avance($id, $piloto, 1, 2, '25', null);

            $this->enMovimiento($id, $piloto, -17.3600, -62.5700);
        }, ventana: [-1, 1]);
    }

    /** Finalizadas y calificadas, una con su disputa abierta. */
    private function finalizadas(): void
    {
        // Finalizada hace un par de días, sin calificar todavía: el cliente ve el monto a pagar.
        $this->solicitud('neg-finalizada-sur', 'luis.antelo', 'Lote Sur', 110, '112', hace: 96, despues: function (int $id): void {
            $this->recorridoCompleto($id, 'luis.antelo', 'carlos.mamani', 'acepta_propuesta', null, '110');
        }, ventana: [-3, -2]);

        // Calificada por ambas partes.
        $this->solicitud('neg-calificada-palmas', 'agro.sanrafael', 'Las Palmas', 120, '118', hace: 200, despues: function (int $id): void {
            $this->recorridoCompleto($id, 'agro.sanrafael', 'valeria.montano', 'contraoferta', '124', '120', equipo: 2);

            $cliente = $this->usuarios['agro.sanrafael'];
            $this->tras(0, function () use ($id, $cliente): void {
                app(CalificarComoCliente::class)->ejecutar($cliente, $id, [
                    ['uuid_cliente' => $this->uuid("{$id}:cal-proveedor"), 'sujeto_tipo' => 'proveedor', 'estrellas' => 5, 'etiquetas' => ['puntualidad', 'cobertura_pareja'], 'comentario' => 'Cumplieron el precio y la cobertura quedó pareja.'],
                    ['uuid_cliente' => $this->uuid("{$id}:cal-piloto"), 'sujeto_tipo' => 'piloto', 'estrellas' => 4, 'etiquetas' => ['comunicacion'], 'comentario' => null],
                ]);
                app(CalificarComoProveedor::class)->ejecutar($this->usuarios['valeria.montano'], $id, [
                    'uuid_cliente' => $this->uuid("{$id}:cal-cliente"), 'estrellas' => 5, 'etiquetas' => ['acceso_lote', 'claridad_pedido'], 'comentario' => 'Acceso sin problemas y pedido claro.',
                ]);
            });
        }, ventana: [-8, -7]);

        // Finalizada con una disputa del cliente en curso.
        $this->solicitud('neg-disputada-este', 'coop.valle.grande', 'Lote Este', 140, '108', hace: 120, despues: function (int $id): void {
            $this->recorridoCompleto($id, 'coop.valle.grande', 'marcelo.terrazas', 'acepta_propuesta', null, '128');

            $this->tras(0, function () use ($id): void {
                $disputa = app(AbrirDisputa::class)->ejecutar($this->usuarios['coop.valle.grande'], ParteDeDisputa::Cliente, $id, 'cobertura_incompleta', 'Quedaron franjas sin cubrir en el borde norte del lote.');
                $admin = (int) (auth('interno')->id() ?? 1);
                app(AgregarMensajeDeDisputa::class)->comoSoporte($admin, $disputa->id, 'Pedimos las fotos del avance a ambas partes. Respondan antes del plazo.', true);
            });
        }, ventana: [-6, -5]);
    }

    /** Cancelada, reabierta y una abierta sin trato: sin penalidad en dinero. */
    private function cerradasSinServicio(): void
    {
        $this->solicitud('neg-cancelada', 'luis.antelo', 'La Esperanza', 50, '119', hace: 70, despues: function (int $id): void {
            $this->tras(60, fn () => $this->cancelar->porCliente($this->usuarios['luis.antelo'], $id, 'Cambió la fecha de siembra del lote.'));
        });

        // Un proveedor devolvió el servicio después de adjudicado: la solicitud vuelve a estar abierta.
        $this->solicitud('neg-reabierta', 'agro.sanrafael', 'Lote Norte', 40, '121', hace: 36, despues: function (int $id): void {
            $oferta = $this->tras(34, fn () => $this->negociar->ofertar($this->usuarios['carlos.mamani'], $id, ['uuid_cliente' => $this->uuid("{$id}:carlos-1"), 'tipo' => 'acepta_propuesta']))[0];
            $this->tras(30, fn () => $this->negociar->aceptar(Negociar::LADO_CLIENTE, $this->usuarios['agro.sanrafael'], $oferta->id));
            $this->tras(12, fn () => $this->cancelarComoProveedor->ejecutar($this->usuarios['carlos.mamani'], $id, 'El dron entró a mantenimiento y no llego a la fecha.'));
        }, ventana: [12, 15]);

        // Sin trato: lleva nueve días abierta, nadie ofertó y no pidió fecha. No vence: sigue en el mercado hasta que se adjudique o el cliente la cancele.
        $this->solicitud('neg-sin-trato', 'coop.valle.grande', 'Parcela Central', 55, '100', hace: 9 * 24, ventana: null);
    }

    // ── Piezas de los escenarios ─────────────────────────────────────────────

    /**
     * Crea la solicitud con el reloj simulado `hace` horas atrás, la abre y corre `$despues`.
     *
     * @param  (callable(int): void)|null  $despues
     * @param  array{0: int, 1: int}|null  $ventana  fecha deseada (opcional): días desde hoy, de inicio y de fin
     */
    private function solicitud(
        string $clave,
        string $cliente,
        string $terreno,
        int $hectareas,
        string $precio,
        int $hace,
        string $hasta = 'abierta',
        string $producto = 'Glifosato 480 SL, 2 L/ha',
        string $tipo = 'herbicida',
        bool $urgente = false,
        ?string $observaciones = null,
        ?callable $despues = null,
        ?array $ventana = [2, 9],
    ): ?SolicitudServicio {
        $uuid = $this->uuid($clave);

        if (SolicitudServicio::query()->where('uuid_cliente', $uuid)->exists()) {
            return null;
        }

        Carbon::setTestNow(Carbon::now()->subHours($hace));
        $usuario = $this->clientes[$cliente]->usuario_id;

        [$solicitud] = $this->crear->ejecutar($usuario, [
            'uuid_cliente' => $uuid,
            'terreno_id' => $this->terrenos["{$cliente}|{$terreno}"]->id,
            'tipo_aplicacion' => $tipo,
            'hectareas_solicitadas' => (string) $hectareas,
            'producto_indicado' => $producto,
            // Días contados desde la hora real de la siembra: cada equipo ocupa fechas distintas.
            'ventana_desde' => $ventana === null ? null : $this->origen()->copy()->addDays($ventana[0])->toDateString(),
            'ventana_hasta' => $ventana === null ? null : $this->origen()->copy()->addDays($ventana[1])->toDateString(),
            'precio_ha_propuesto' => $precio,
            'urgente' => $urgente,
            'observaciones' => $observaciones,
        ]);

        if ($hasta === 'borrador') {
            Carbon::setTestNow();

            return $solicitud;
        }

        $abierta = $this->publicar->ejecutar($usuario, $solicitud->id);

        if ($despues !== null) {
            $despues($abierta->id);
        }

        Carbon::setTestNow();

        return $abierta->refresh();
    }

    /**
     * Pone el reloj simulado en «hace `$horas` horas» (contadas desde la hora real de la siembra) y ejecuta el paso allí.
     *
     * @template T
     *
     * @param  callable(): T  $paso
     * @return T
     */
    private function tras(int $horas, callable $paso): mixed
    {
        // El reloj corre hacia adelante: el instante se mide desde la hora real de la siembra.
        Carbon::setTestNow($this->origen()->copy()->subHours($horas));

        return $paso();
    }

    private ?Carbon $origenReal = null;

    private function origen(): Carbon
    {
        return $this->origenReal ??= Carbon::now();
    }

    /** @param  list<array{0: int, 1: string, 2: string}>  $mensajes  horas atrás, autor (`c` cliente, `p` piloto) y texto */
    private function conversacion(int $solicitudId, string $cliente, string $piloto, array $mensajes): void
    {
        foreach ($mensajes as $indice => [$horas, $autor, $texto]) {
            $usuario = $autor === 'c' ? $this->usuarios[$cliente] : $this->usuarios[$piloto];

            $this->tras($horas, fn () => $this->mensajeria->enviar($usuario, $solicitudId, $this->uuid("{$solicitudId}:mensaje-{$indice}"), $texto));
        }
    }

    /** Marca un avance con su foto (cola separada de los registros livianos). */
    private function avance(int $solicitudId, int $piloto, int $horasAtras, int $secuencia, string $porcentaje, ?string $nota): void
    {
        [$registro] = $this->tras($horasAtras, fn () => $this->seguimiento->avanzar($piloto, $solicitudId, [
            'uuid_cliente' => $this->uuid("{$solicitudId}:avance-{$secuencia}"), 'secuencia' => $secuencia, 'porcentaje' => $porcentaje, 'nota' => $nota,
        ]));

        $this->tras($horasAtras, fn () => $this->seguimiento->subirEvidencia($piloto, $solicitudId, [
            'uuid_cliente' => $this->uuid("{$solicitudId}:foto-{$secuencia}"), 'tipo' => 'foto_avance', 'avance_uuid_cliente' => $registro->uuid_cliente,
        ], $this->foto("Avance {$porcentaje} %")));
    }

    /** La última ubicación del piloto: un punto cerca del lote, de hace unos minutos. */
    private function enMovimiento(int $solicitudId, int $piloto, float $latitud, float $longitud): void
    {
        Carbon::setTestNow($this->origen()->copy()->subMinutes(2));
        $this->ubicacion->registrar($piloto, $solicitudId, $latitud, $longitud, 12.0, Carbon::now()->toIso8601String());
    }

    /**
     * Lleva una solicitud ya abierta hasta `finalizada`: oferta, adjudicación, inicio, fotos y cierre.
     * El reloj parte de la hora de la solicitud y avanza hasta «hace unas horas».
     */
    private function recorridoCompleto(int $id, string $cliente, string $piloto, string $tipo, ?string $precioOferta, string $hectareasEjecutadas, ?int $equipo = null): void
    {
        $uuid = fn (string $clave): string => $this->uuid("{$id}:{$clave}");
        $horas = (int) max(1, $this->origen()->diffInHours(Carbon::now(), true));
        // La solicitud nació en `Carbon::now()` (simulado): todo lo demás ocurre después.
        $t = fn (float $fraccion): int => (int) round($horas * (1 - $fraccion));

        $oferta = $this->tras($t(0.05), fn () => $this->negociar->ofertar($this->usuarios[$piloto], $id, array_filter(['uuid_cliente' => $uuid('oferta'), 'tipo' => $tipo, 'precio_ha' => $precioOferta, 'equipo_id' => $equipo])))[0];
        $this->tras($t(0.1), fn () => $this->negociar->aceptar(Negociar::LADO_CLIENTE, $this->usuarios[$cliente], $oferta->id));

        $this->tras($t(0.4), fn () => $this->seguimiento->iniciar($this->usuarios[$piloto], $id, ['uuid_cliente' => $uuid('inicio'), 'secuencia' => 1]));
        $this->avance($id, $this->usuarios[$piloto], $t(0.5), 2, '50', 'Mitad del lote cubierta.');

        $this->tras($t(0.7), fn () => $this->seguimiento->subirEvidencia($this->usuarios[$piloto], $id, ['uuid_cliente' => $uuid('foto-final'), 'tipo' => 'foto_final'], $this->foto('Servicio terminado')));
        $this->tras($t(0.7), fn () => $this->seguimiento->cerrar($this->usuarios[$piloto], $id, ['uuid_cliente' => $uuid('cierre'), 'secuencia' => 3, 'hectareas_ejecutadas' => $hectareasEjecutadas, 'nota' => 'Servicio terminado.']));

        $this->conversacion($id, $cliente, $piloto, [
            [$t(0.3), 'c', 'Gracias por el servicio, ya vi las fotos.'],
            [$t(0.3), 'p', 'A usted. Cualquier cosa nos avisa por aquí.'],
        ]);

    }

    // ── Terrenos, preferencias y archivos ────────────────────────────────────

    private function terrenos(): void
    {
        foreach (self::TERRENOS as $indice => [$username, $nombre, $provincia, $municipio, $latitud, $longitud, $hectareas, $cultivo]) {
            $cliente = $this->clientes[$username];

            $terreno = Terreno::query()->where('cliente_id', $cliente->id)->where('nombre', $nombre)->first()
                ?? new Terreno(['cliente_id' => $cliente->id, 'nombre' => $nombre]);

            $terreno->fill([
                'hectareas' => $terreno->exists ? $terreno->hectareas : $hectareas,
                'cultivo' => $terreno->cultivo ?? $cultivo,
                'referencias_acceso' => $terreno->referencias_acceso ?? 'Camino vecinal a 3 km de la ruta principal.',
                'departamento_codigo' => self::DEPARTAMENTO,
                'provincia_codigo' => $provincia,
                'municipio' => $terreno->municipio ?? $municipio,
                'comunidad' => $terreno->comunidad ?? "Comunidad {$nombre}",
                'geometria' => $this->poligono((float) $latitud, (float) $longitud, $indice),
            ]);
            $terreno->save();

            $this->terrenos["{$username}|{$nombre}"] = $terreno;
        }
    }

    /** Un cuadrado de ~1 km de lado cerca del centro indicado, desplazado por terreno. */
    private function poligono(float $lat, float $lng, int $indice): array
    {
        $lat += ($indice % 3 - 1) * 0.02;
        $lng += (intdiv($indice, 3) - 1) * 0.03;
        $d = 0.005;

        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [round($lng - $d, 6), round($lat - $d, 6)],
                [round($lng + $d, 6), round($lat - $d, 6)],
                [round($lng + $d, 6), round($lat + $d, 6)],
                [round($lng - $d, 6), round($lat + $d, 6)],
                [round($lng - $d, 6), round($lat - $d, 6)],
            ]],
        ];
    }

    /** Los pilotos reciben avisos de nuevas solicitudes: Marcelo no quiere menos de 50 ha ni desecantes, el resto de todo. */
    private function preferenciasDeLosPilotos(): void
    {
        $this->preferencias->guardar($this->usuarios['marcelo.terrazas'], [
            'nuevas_solicitudes' => ['activo' => true, 'hectareas_minimas' => '50', 'tipos_aplicacion_excluidos' => ['desecante']],
        ]);
    }

    /** Una imagen real y liviana (menos de 300 KB) para la foto de avance. */
    private function foto(string $texto): UploadedFile
    {
        $this->contadorDeFotos++;
        $imagen = imagecreatetruecolor(640, 480);
        $verde = 40 + ($this->contadorDeFotos * 23) % 90;
        imagefilledrectangle($imagen, 0, 0, 640, 480, imagecolorallocate($imagen, 60, 120 + $verde % 80, 70));
        imagefilledrectangle($imagen, 0, 330, 640, 480, imagecolorallocate($imagen, 120, 90, 60));
        imagestring($imagen, 5, 20, 20, $texto, imagecolorallocate($imagen, 255, 255, 255));

        $ruta = (string) tempnam(sys_get_temp_dir(), 'foto-demo-');
        imagejpeg($imagen, $ruta, 70);

        return new UploadedFile($ruta, "avance-{$this->contadorDeFotos}.jpg", 'image/jpeg', null, true);
    }

    private function uuid(string $clave): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, "agrocom-demo-negociacion:{$clave}")->toString();
    }
}
