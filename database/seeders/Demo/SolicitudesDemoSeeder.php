<?php

namespace Database\Seeders\Demo;

use App\Dominios\Asignacion\Aplicacion\IniciarAsignacion;
use App\Dominios\Calificaciones\Aplicacion\CalificarComoCliente;
use App\Dominios\Calificaciones\Aplicacion\CalificarComoProveedor;
use App\Dominios\Disputas\Aplicacion\AbrirDisputa;
use App\Dominios\Disputas\Aplicacion\AgregarMensajeDeDisputa;
use App\Dominios\Disputas\Aplicacion\ResolverDisputa;
use App\Dominios\Disputas\Dominio\ParteDeDisputa;
use App\Dominios\Disputas\Dominio\ResultadoDisputa;
use App\Dominios\Flota\Infraestructura\Eloquent\Disponibilidad;
use App\Dominios\Flota\Infraestructura\Eloquent\Equipo;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Proveedor;
use App\Dominios\Solicitudes\Aplicacion\CancelarSolicitud;
use App\Dominios\Solicitudes\Aplicacion\ConformarSolicitud;
use App\Dominios\Solicitudes\Aplicacion\CrearSolicitud;
use App\Dominios\Solicitudes\Aplicacion\GestionarTerrenos;
use App\Dominios\Solicitudes\Aplicacion\MaquinaEstados\MaquinaEstadosSolicitud;
use App\Dominios\Solicitudes\Aplicacion\PublicarSolicitud;
use App\Dominios\Solicitudes\Contratos\Eventos\SolicitudPublicada;
use App\Dominios\Solicitudes\Dominio\ActorSolicitud;
use App\Dominios\Solicitudes\Dominio\EstadoSolicitud;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Cliente;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\SolicitudServicio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Ramsey\Uuid\Uuid;

/**
 * Datos de demostración del marketplace: terrenos y solicitudes de servicio en
 * cada paso del ciclo (ADR 0027), para recorrer el panel y las apps del cliente
 * y del proveedor sin cargar nada a mano. Se apoya en los clientes, proveedores
 * y equipos que siembra `ProveedoresDemoSeeder`.
 *
 * Idempotente: el `uuid_cliente` de cada solicitud es determinista, así que
 * volver a correrlo no crea nada de nuevo. Los estados pasan por los casos de uso
 * y la máquina de estados reales (invariante 7): una solicitud asignada se arma
 * publicándola y fijando su equipo con la transición `en_asignacion → asignada`.
 *
 * Publicar anuncia `SolicitudPublicada`, que el motor de asignación toma al
 * instante (ADR 0030). Para armar cada paso del ciclo sin que el motor se
 * adelante, la siembra publica sin anunciar y, para la solicitud que debe
 * quedar con ofertas vivas, entrega el motor a mano al final. Los equipos
 * quedan «en línea» para que una solicitud urgente tenga a quién ofrecer.
 *
 * El posventa (ADR 0031, 0033 y 0039) deja tres servicios ejecutados con su
 * recorrido por los casos de uso reales: uno completado y calificado por ambas
 * partes, otro con una disputa abierta y mediada por soporte y otro con una
 * disputa resuelta con reembolso parcial y todavía apelable.
 */
class SolicitudesDemoSeeder extends Seeder
{
    public function __construct(
        private readonly GestionarTerrenos $terrenos,
        private readonly CrearSolicitud $crear,
        private readonly PublicarSolicitud $publicar,
        private readonly CancelarSolicitud $cancelar,
        private readonly MaquinaEstadosSolicitud $estados,
    ) {}

    public function run(): void
    {
        $clientes = Cliente::query()->orderBy('id')->get();

        if ($clientes->isEmpty()) {
            $this->command?->warn('No hay clientes de demostración: corre primero ProveedoresDemoSeeder.');

            return;
        }

        $equipo = Equipo::query()->orderBy('id')->first();
        $proveedor = $equipo === null ? null : Proveedor::query()->find($equipo->proveedor_id);

        Equipo::query()->orderBy('id')->get()->each(fn (Equipo $enLinea) => Disponibilidad::query()->firstOrCreate(
            ['equipo_id' => $enLinea->id],
            ['en_linea' => true, 'actualizado_en' => Carbon::now()],
        ));

        foreach ($clientes->values() as $indice => $cliente) {
            $terreno = $this->terrenos->crear($cliente->usuario_id, [
                'nombre' => 'Lote '.['Norte', 'Sur', 'Este'][$indice % 3],
                'hectareas' => (string) (80 + $indice * 40),
                'cultivo' => ['Soya', 'Maíz', 'Girasol'][$indice % 3],
                'referencias_acceso' => 'Camino vecinal a 3 km de la ruta principal.',
                'acceso_dificil' => $indice === 2,
            ]);

            $this->solicitud($cliente, $terreno->id, "demo-{$indice}-a", EstadoSolicitud::Borrador);
            $this->solicitud($cliente, $terreno->id, "demo-{$indice}-b", EstadoSolicitud::Publicada);

            if ($indice === 0) {
                $this->solicitud($cliente, $terreno->id, "demo-{$indice}-c", EstadoSolicitud::SinProveedor);
                $this->solicitud($cliente, $terreno->id, "demo-{$indice}-d", EstadoSolicitud::Cancelada);
                if ($equipo !== null && $proveedor !== null) {
                    $this->solicitud($cliente, $terreno->id, "demo-{$indice}-e", EstadoSolicitud::Asignada, $equipo->id, $proveedor->id);
                    $this->posventa($cliente, $terreno->id, $equipo, $proveedor);
                }
            }
        }
    }

    private function solicitud(Cliente $cliente, int $terrenoId, string $clave, EstadoSolicitud $destino, ?int $equipoId = null, ?int $proveedorId = null, bool $urgente = false): ?SolicitudServicio
    {
        $uuid = Uuid::uuid5(Uuid::NAMESPACE_URL, "agrocom-demo-solicitud:{$clave}")->toString();

        if (SolicitudServicio::query()->where('uuid_cliente', $uuid)->exists()) {
            return null;
        }

        [$solicitud] = $this->crear->ejecutar($cliente->usuario_id, [
            'uuid_cliente' => $uuid,
            'terreno_id' => $terrenoId,
            'tipo_aplicacion' => 'herbicida',
            'hectareas_solicitadas' => '60',
            'producto_indicado' => 'Glifosato 480 SL, 2 L/ha',
            'aporta_producto' => 'cliente',
            'ventana_desde' => now()->addDays(3)->toDateString(),
            'ventana_hasta' => now()->addDays(12)->toDateString(),
            'urgente' => $urgente,
        ]);

        if ($destino === EstadoSolicitud::Borrador) {
            return $solicitud;
        }

        if ($destino === EstadoSolicitud::Cancelada) {
            return $this->cancelar->porCliente($cliente->usuario_id, $solicitud->id, 'Cambió la fecha de siembra del lote.');
        }

        $solicitud = Event::fakeFor(
            fn () => $this->publicar->ejecutar($cliente->usuario_id, $solicitud->id),
            [SolicitudPublicada::class],
        );

        if ($destino === EstadoSolicitud::Publicada) {
            // El motor la toma como en producción: ofrece a los propios y deja ofertas vivas.
            app(IniciarAsignacion::class)->ejecutar($solicitud->id);

            return $solicitud;
        }

        $solicitud = $this->estados->transicionar($solicitud, EstadoSolicitud::EnAsignacion, ActorSolicitud::Sistema, null);

        if ($destino === EstadoSolicitud::SinProveedor) {
            return $this->estados->transicionar($solicitud, EstadoSolicitud::SinProveedor, ActorSolicitud::Sistema, null, 'Ningún proveedor aceptó en las rondas previstas.');
        }

        return $this->estados->transicionar($solicitud, EstadoSolicitud::Asignada, ActorSolicitud::Sistema, null, null, ['equipo_id' => $equipoId, 'proveedor_id' => $proveedorId]);
    }

    /** Lleva una solicitud asignada hasta `ejecutada`, como lo hace la ejecución en el lote. */
    private function ejecutada(Cliente $cliente, int $terrenoId, string $clave, Equipo $equipo, Proveedor $proveedor): ?SolicitudServicio
    {
        $solicitud = $this->solicitud($cliente, $terrenoId, $clave, EstadoSolicitud::Asignada, $equipo->id, $proveedor->id);

        if ($solicitud === null) {
            return null;
        }

        foreach ([EstadoSolicitud::EnCamino, EstadoSolicitud::EnEjecucion, EstadoSolicitud::Ejecutada] as $paso) {
            $solicitud = $this->estados->transicionar($solicitud, $paso, ActorSolicitud::Sistema, null);
        }

        return $solicitud;
    }

    /**
     * El posventa de tres servicios del primer cliente (ADR 0031, 0033 y 0039): uno
     * completado y calificado, uno con una disputa abierta y uno con una disputa ya
     * resuelta. Solo corre si todavía no se sembraron.
     */
    private function posventa(Cliente $cliente, int $terrenoId, Equipo $equipo, Proveedor $proveedor): void
    {
        $admin = Auth::guard('interno')->user();
        $adminId = $admin === null ? 1 : (int) $admin->getAuthIdentifier();
        $titular = (int) $proveedor->usuario_titular_id;
        $uuid = fn (string $clave): string => Uuid::uuid5(Uuid::NAMESPACE_URL, "agrocom-demo-posventa:{$clave}")->toString();

        $completada = $this->ejecutada($cliente, $terrenoId, 'demo-0-f', $equipo, $proveedor);

        if ($completada !== null) {
            app(ConformarSolicitud::class)->ejecutar($cliente->usuario_id, $completada->id);

            $calificaciones = [
                ['uuid_cliente' => $uuid('f-proveedor'), 'sujeto_tipo' => 'proveedor', 'estrellas' => 5, 'etiquetas' => ['puntualidad', 'cobertura_pareja'], 'comentario' => 'Llegaron a la hora y la cobertura fue pareja.'],
                ['uuid_cliente' => $uuid('f-piloto'), 'sujeto_tipo' => 'piloto', 'estrellas' => 4, 'etiquetas' => ['comunicacion'], 'comentario' => null],
            ];

            if ($equipo->auxiliar_id !== null) {
                $calificaciones[] = ['uuid_cliente' => $uuid('f-auxiliar'), 'sujeto_tipo' => 'auxiliar', 'estrellas' => 5, 'etiquetas' => ['orden_lote'], 'comentario' => null];
            }

            app(CalificarComoCliente::class)->ejecutar($cliente->usuario_id, $completada->id, $calificaciones);

            if ($titular > 0) {
                app(CalificarComoProveedor::class)->ejecutar($titular, $completada->id, [
                    'uuid_cliente' => $uuid('f-cliente'),
                    'estrellas' => 4,
                    'etiquetas' => ['acceso_lote', 'claridad_pedido'],
                    'comentario' => 'Acceso al lote sin problemas.',
                ]);
            }
        }

        $enDisputa = $this->ejecutada($cliente, $terrenoId, 'demo-0-g', $equipo, $proveedor);

        if ($enDisputa !== null) {
            $disputa = app(AbrirDisputa::class)->ejecutar($cliente->usuario_id, ParteDeDisputa::Cliente, $enDisputa->id, 'cobertura_incompleta', 'Quedaron franjas sin cubrir en el lado norte del lote.');
            app(AgregarMensajeDeDisputa::class)->comoSoporte($adminId, $disputa->id, 'Pedimos el registro del vuelo a ambas partes. Respondan por favor antes del plazo.', true);
        }

        $resuelta = $this->ejecutada($cliente, $terrenoId, 'demo-0-h', $equipo, $proveedor);

        if ($resuelta !== null) {
            $disputa = app(AbrirDisputa::class)->ejecutar($cliente->usuario_id, ParteDeDisputa::Cliente, $resuelta->id, 'mala_aplicacion', 'Hubo doble pasada en una franja y se quemó el cultivo.');
            app(ResolverDisputa::class)->ejecutar($adminId, $disputa->id, ResultadoDisputa::Parcial, 'La doble pasada quedó probada en el registro; se reembolsa la franja afectada.', '1000.00');
        }
    }
}
