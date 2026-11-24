<?php

namespace Database\Seeders\Demo;

use App\Dominios\Asignacion\Aplicacion\AvanzarAsignacion;
use App\Dominios\Calificaciones\Aplicacion\CalificarComoCliente;
use App\Dominios\Calificaciones\Aplicacion\CalificarComoProveedor;
use App\Dominios\Flota\Infraestructura\Eloquent\Equipo;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Proveedor;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Solicitudes\Aplicacion\CancelarSolicitud;
use App\Dominios\Solicitudes\Aplicacion\ConformarSolicitud;
use App\Dominios\Solicitudes\Aplicacion\CrearSolicitud;
use App\Dominios\Solicitudes\Aplicacion\MaquinaEstados\MaquinaEstadosSolicitud;
use App\Dominios\Solicitudes\Aplicacion\PublicarSolicitud;
use App\Dominios\Solicitudes\Contratos\Eventos\SolicitudEjecutada;
use App\Dominios\Solicitudes\Contratos\Eventos\SolicitudPublicada;
use App\Dominios\Solicitudes\Contratos\LecturaSolicitudesParaAsignar;
use App\Dominios\Solicitudes\Dominio\ActorSolicitud;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Cliente;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Terreno;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Zona;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Event;
use Ramsey\Uuid\Uuid;

/**
 * Recorrido completo del marketplace para las apps: lo que
 * {@see SolicitudesDemoSeeder} no cubre. Deja, entre los tres clientes y los
 * cuatro equipos de la demo, una solicitud en cada estado visible (en ofertas
 * a propios, en ofertas a terceros con cuenta regresiva, asignada, en camino,
 * en ejecución, suspendida por clima, ejecutada a la espera de conformidad,
 * completada con y sin calificar, en disputa y cancelada), terrenos con zona y
 * polígono, y servicios completados que dan reputación a cada proveedor.
 *
 * Idempotente: cada solicitud lleva un `uuid_cliente` determinista, así que
 * volver a correrlo no duplica nada. Los plazos son relativos a `now()`; la
 * vigencia de las ofertas se renueva en {@see RenovarVigenciaDemoSeeder}.
 */
class RecorridoDemoSeeder extends Seeder
{
    /** Terrenos de la demo: cliente, nombre, zona, hectáreas, cultivo. */
    private const TERRENOS = [
        ['agro.sanrafael', 'Lote Norte', 'Warnes', '80', 'Soya'],
        ['agro.sanrafael', 'Las Palmas', 'Santa Cruz de la Sierra', '120', 'Maíz'],
        ['luis.antelo', 'Lote Sur', 'Montero', '120', 'Maíz'],
        ['luis.antelo', 'La Esperanza', 'Montero', '65', 'Soya'],
        ['coop.valle.grande', 'Lote Este', 'Cuatro Cañadas', '160', 'Girasol'],
        ['coop.valle.grande', 'Parcela Central', 'San Julián', '90', 'Soya'],
    ];

    public function __construct(
        private readonly CrearSolicitud $crear,
        private readonly PublicarSolicitud $publicar,
        private readonly CancelarSolicitud $cancelar,
        private readonly MaquinaEstadosSolicitud $estados,
        private readonly AvanzarAsignacion $avanzar,
        private readonly LecturaSolicitudesParaAsignar $lectura,
    ) {}

    public function run(): void
    {
        /** @var array<string, Cliente> $clientes */
        $clientes = [];
        foreach (['agro.sanrafael', 'luis.antelo', 'coop.valle.grande'] as $username) {
            $usuario = SecUser::query()->where('username', $username)->first();
            $cliente = $usuario === null ? null : Cliente::query()->where('usuario_id', $usuario->id)->first();

            if ($cliente === null) {
                $this->command?->warn("No existe el cliente {$username}: corre primero ProveedoresDemoSeeder.");

                return;
            }

            $clientes[$username] = $cliente;
        }

        $equipos = Equipo::query()->orderBy('id')->get()->values();

        if ($equipos->count() < 4) {
            $this->command?->warn('Faltan equipos de demostración: corre primero ProveedoresDemoSeeder.');

            return;
        }

        $terrenos = $this->terrenos($clientes);

        [$rodrigo, $valeria, $carlos, $oriente] = $equipos->all();

        $sanRafael = $clientes['agro.sanrafael'];
        $luis = $clientes['luis.antelo'];
        $coop = $clientes['coop.valle.grande'];

        // Agrocom y sus pilotos: el servicio avanza por el lote.
        $this->solicitud($sanRafael, $terrenos['agro.sanrafael|Lote Norte'], 'rec-0-camino', EstadoSolicitud::EnCamino, $valeria, hectareas: '70', diasDesde: -1, dias: 3);
        $this->solicitud($sanRafael, $terrenos['agro.sanrafael|Las Palmas'], 'rec-0-ejecucion', EstadoSolicitud::EnEjecucion, $rodrigo, hectareas: '120', diasDesde: -1, dias: 3);
        $this->solicitud($sanRafael, $terrenos['agro.sanrafael|Lote Norte'], 'rec-0-clima', EstadoSolicitud::SuspendidaPorClima, $valeria, hectareas: '40', diasDesde: -1, dias: 3);
        $this->solicitud($sanRafael, $terrenos['agro.sanrafael|Las Palmas'], 'rec-0-conformidad', EstadoSolicitud::Ejecutada, $valeria, hectareas: '100', diasDesde: -4, dias: 2);
        $this->solicitud($sanRafael, $terrenos['agro.sanrafael|Las Palmas'], 'rec-0-borrador', EstadoSolicitud::Borrador, null, hectareas: '30');

        // Mamani Agroservicios (independiente).
        $this->solicitud($luis, $terrenos['luis.antelo|Lote Sur'], 'rec-1-asignada', EstadoSolicitud::Asignada, $carlos, hectareas: '80', diasDesde: 12);
        $this->solicitud($luis, $terrenos['luis.antelo|La Esperanza'], 'rec-1-ejecucion', EstadoSolicitud::EnEjecucion, $carlos, hectareas: '65', diasDesde: -1, dias: 3);
        $this->solicitud($luis, $terrenos['luis.antelo|Lote Sur'], 'rec-1-conformidad', EstadoSolicitud::Ejecutada, $carlos, hectareas: '90', diasDesde: -5, dias: 2);
        $completada = $this->solicitud($luis, $terrenos['luis.antelo|Lote Sur'], 'rec-1-completada', EstadoSolicitud::Completada, $carlos, hectareas: '110', diasDesde: -14, dias: 3);
        $this->calificar($luis, $completada, $carlos, 5, 4);
        $this->solicitud($luis, $terrenos['luis.antelo|La Esperanza'], 'rec-1-completada-pendiente', EstadoSolicitud::Completada, $carlos, hectareas: '60', diasDesde: -6, dias: 2);
        $this->solicitud($luis, $terrenos['luis.antelo|Lote Sur'], 'rec-1-cancelada', EstadoSolicitud::Cancelada, null, hectareas: '50');

        // Ofertas vivas para terceros: la solicitud sale de los propios y se ofrece a quien cubre la zona.
        // Su ventana cae en otras fechas: un equipo con un servicio en esos días no recibe la oferta.
        $this->solicitud($luis, $terrenos['luis.antelo|Lote Sur'], 'rec-1-ofertada-terceros', EstadoSolicitud::EnAsignacion, null, hectareas: '75', aTerceros: true, diasDesde: 20);
        $this->solicitud($luis, $terrenos['luis.antelo|La Esperanza'], 'rec-1-ofertada-urgente', EstadoSolicitud::EnAsignacion, null, hectareas: '45', aTerceros: true, urgente: true, diasDesde: 20);

        // Fumigaciones del Oriente (tercero).
        $this->solicitud($coop, $terrenos['coop.valle.grande|Parcela Central'], 'rec-2-ofertada', EstadoSolicitud::EnAsignacion, null, hectareas: '80', aTerceros: true, diasDesde: 20);
        $this->solicitud($coop, $terrenos['coop.valle.grande|Parcela Central'], 'rec-2-asignada', EstadoSolicitud::Asignada, $oriente, hectareas: '90', diasDesde: 12);
        $this->solicitud($coop, $terrenos['coop.valle.grande|Lote Este'], 'rec-2-conformidad', EstadoSolicitud::Ejecutada, $oriente, hectareas: '140', diasDesde: -3, dias: 2);
        $completada = $this->solicitud($coop, $terrenos['coop.valle.grande|Lote Este'], 'rec-2-completada', EstadoSolicitud::Completada, $oriente, hectareas: '150', diasDesde: -12, dias: 3);
        $this->calificar($coop, $completada, $oriente, 4, 3, 'Cumplieron, aunque llegaron tarde al lote.');
        $this->solicitud($coop, $terrenos['coop.valle.grande|Parcela Central'], 'rec-2-sin-proveedor', EstadoSolicitud::SinProveedor, null, hectareas: '85');
        $this->solicitud($coop, $terrenos['coop.valle.grande|Lote Este'], 'rec-2-borrador', EstadoSolicitud::Borrador, null, hectareas: '55');
    }

    /**
     * Da a cada terreno de la demo su zona y su polígono (para que la oferta
     * muestre zona y ubicación aproximada) y crea los que faltan.
     *
     * @param  array<string, Cliente>  $clientes
     * @return array<string, Terreno>
     */
    private function terrenos(array $clientes): array
    {
        $porClave = [];

        foreach (self::TERRENOS as $indice => [$username, $nombre, $zonaNombre, $hectareas, $cultivo]) {
            $cliente = $clientes[$username];
            $zona = Zona::query()->where('nombre', $zonaNombre)->first();

            $terreno = Terreno::query()->where('cliente_id', $cliente->id)->where('nombre', $nombre)->first()
                ?? new Terreno(['cliente_id' => $cliente->id, 'nombre' => $nombre]);

            $terreno->fill([
                'hectareas' => $terreno->exists ? $terreno->hectareas : $hectareas,
                'cultivo' => $terreno->cultivo ?? $cultivo,
                'referencias_acceso' => $terreno->referencias_acceso ?? 'Camino vecinal a 3 km de la ruta principal.',
                'zona_id' => $zona?->id,
                'geometria' => $zona === null ? null : $this->poligono((float) $zona->centroide_lat, (float) $zona->centroide_lng, $indice),
            ]);
            $terreno->save();

            $porClave["{$username}|{$nombre}"] = $terreno;
        }

        return $porClave;
    }

    /** Un cuadrado de ~1 km de lado cerca del centro de la zona, desplazado por terreno. */
    private function poligono(float $lat, float $lng, int $indice): array
    {
        $lat += ($indice % 3 - 1) * 0.02;
        $lng += (intdiv($indice, 3) - 0.5) * 0.03;
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

    /**
     * Arma una solicitud en el estado pedido por los casos de uso y la máquina
     * de estados reales. Devuelve `null` si ya existía (idempotencia).
     */
    private function solicitud(
        Cliente $cliente,
        Terreno $terreno,
        string $clave,
        EstadoSolicitud $destino,
        ?Equipo $equipo,
        string $hectareas = '60',
        bool $aTerceros = false,
        bool $urgente = false,
        int $diasDesde = 3,
        int $dias = 7,
    ): ?SolicitudServicio {
        $solicitud = $this->armar($cliente, $terreno, $clave, $destino, $equipo, $hectareas, $aTerceros, $urgente, $diasDesde);

        // Lo que ya ocurre o ya ocurrió tiene su ventana en torno a hoy; el alta y la
        // publicación no admiten fechas pasadas, así que se ajusta al terminar.
        if ($solicitud !== null && $diasDesde < 1) {
            SolicitudServicio::query()->whereKey($solicitud->id)->update([
                'ventana_desde' => now()->addDays($diasDesde)->toDateString(),
                'ventana_hasta' => now()->addDays($diasDesde + $dias)->toDateString(),
            ]);
            $solicitud->refresh();
        }

        return $solicitud;
    }

    private function armar(
        Cliente $cliente,
        Terreno $terreno,
        string $clave,
        EstadoSolicitud $destino,
        ?Equipo $equipo,
        string $hectareas = '60',
        bool $aTerceros = false,
        bool $urgente = false,
        int $diasDesde = 3,
    ): ?SolicitudServicio {
        $uuid = Uuid::uuid5(Uuid::NAMESPACE_URL, "agrocom-demo-recorrido:{$clave}")->toString();

        if (SolicitudServicio::query()->where('uuid_cliente', $uuid)->exists()) {
            return null;
        }

        [$solicitud] = $this->crear->ejecutar($cliente->usuario_id, [
            'uuid_cliente' => $uuid,
            'terreno_id' => $terreno->id,
            'tipo_aplicacion' => ['herbicida', 'fungicida', 'insecticida'][crc32($clave) % 3],
            'hectareas_solicitadas' => $hectareas,
            'producto_indicado' => ['Glifosato 480 SL, 2 L/ha', 'Azoxistrobina 25 SC, 0,4 L/ha', 'Lambda-cialotrina 5 CS, 0,3 L/ha'][crc32($clave) % 3],
            'aporta_producto' => $clave === 'rec-1-ofertada-terceros' ? 'proveedor' : 'cliente',
            'ventana_desde' => now()->addDays(max(1, $diasDesde))->toDateString(),
            'ventana_hasta' => now()->addDays(max(1, $diasDesde) + 7)->toDateString(),
            'urgente' => $urgente,
            'observaciones' => $urgente ? 'Hay que aplicar antes de la lluvia del fin de semana.' : null,
        ]);

        if ($destino === EstadoSolicitud::Borrador) {
            return $solicitud;
        }

        if ($destino === EstadoSolicitud::Cancelada) {
            return $this->cancelar->porCliente($cliente->usuario_id, $solicitud->id, 'Se adelantó la cosecha y ya no hace falta aplicar.');
        }

        $solicitud = Event::fakeFor(
            fn () => $this->publicar->ejecutar($cliente->usuario_id, $solicitud->id),
            [SolicitudPublicada::class],
        );
        $solicitud = $this->estados->transicionar($solicitud, EstadoSolicitud::EnAsignacion, ActorSolicitud::Sistema, null);

        if ($destino === EstadoSolicitud::SinProveedor) {
            return $this->estados->transicionar($solicitud, EstadoSolicitud::SinProveedor, ActorSolicitud::Sistema, null, 'Ningún proveedor aceptó en las rondas previstas.');
        }

        if ($destino === EstadoSolicitud::EnAsignacion) {
            if ($aTerceros) {
                // Ronda 2 en adelante: la ofrece a independientes y terceros, con su cuenta regresiva.
                $datos = $this->lectura->datos($solicitud->id);
                $this->avanzar->ejecutar($datos, 1, 2);
            }

            return $solicitud;
        }

        $proveedor = Proveedor::query()->findOrFail($equipo->proveedor_id);
        $solicitud = $this->estados->transicionar($solicitud, EstadoSolicitud::Asignada, ActorSolicitud::Sistema, null, null, ['equipo_id' => $equipo->id, 'proveedor_id' => $proveedor->id]);

        $camino = match ($destino) {
            EstadoSolicitud::Asignada => [],
            EstadoSolicitud::EnCamino => [EstadoSolicitud::EnCamino],
            EstadoSolicitud::EnEjecucion => [EstadoSolicitud::EnCamino, EstadoSolicitud::EnEjecucion],
            EstadoSolicitud::SuspendidaPorClima => [EstadoSolicitud::EnCamino, EstadoSolicitud::EnEjecucion, EstadoSolicitud::SuspendidaPorClima],
            default => [EstadoSolicitud::EnCamino, EstadoSolicitud::EnEjecucion, EstadoSolicitud::Ejecutada],
        };

        foreach ($camino as $paso) {
            $solicitud = $this->estados->transicionar($solicitud, $paso, ActorSolicitud::Sistema, null);
        }

        if ($solicitud->estado === EstadoSolicitud::Ejecutada) {
            event(new SolicitudEjecutada($solicitud->id, $solicitud->cliente_id, $cliente->usuario_id, $solicitud->proveedor_id));
        }

        if ($destino === EstadoSolicitud::Completada) {
            app(ConformarSolicitud::class)->ejecutar($cliente->usuario_id, $solicitud->id);
            $solicitud->refresh();
        }

        return $solicitud;
    }

    /**
     * El cliente califica al proveedor, al piloto y al auxiliar, y el proveedor
     * al cliente: la reputación de cada uno sale de estos registros.
     */
    private function calificar(Cliente $cliente, ?SolicitudServicio $solicitud, Equipo $equipo, int $estrellasProveedor, int $estrellasPiloto, ?string $comentario = null): void
    {
        if ($solicitud === null) {
            return;
        }

        $uuid = fn (string $clave): string => Uuid::uuid5(Uuid::NAMESPACE_URL, "agrocom-demo-recorrido:{$solicitud->uuid_cliente}:{$clave}")->toString();
        $proveedor = Proveedor::query()->findOrFail($equipo->proveedor_id);

        $calificaciones = [
            ['uuid_cliente' => $uuid('proveedor'), 'sujeto_tipo' => 'proveedor', 'estrellas' => $estrellasProveedor, 'etiquetas' => ['puntualidad'], 'comentario' => $comentario ?? 'Buen servicio, cobertura pareja.'],
            ['uuid_cliente' => $uuid('piloto'), 'sujeto_tipo' => 'piloto', 'estrellas' => $estrellasPiloto, 'etiquetas' => ['comunicacion'], 'comentario' => null],
        ];

        if ($equipo->auxiliar_id !== null) {
            $calificaciones[] = ['uuid_cliente' => $uuid('auxiliar'), 'sujeto_tipo' => 'auxiliar', 'estrellas' => 5, 'etiquetas' => ['orden_lote'], 'comentario' => null];
        }

        app(CalificarComoCliente::class)->ejecutar($cliente->usuario_id, $solicitud->id, $calificaciones);

        if ((int) $proveedor->usuario_titular_id > 0) {
            app(CalificarComoProveedor::class)->ejecutar((int) $proveedor->usuario_titular_id, $solicitud->id, [
                'uuid_cliente' => $uuid('cliente'),
                'estrellas' => 5,
                'etiquetas' => ['claridad_pedido'],
                'comentario' => 'Cliente puntual y con el lote listo.',
            ]);
        }
    }
}
