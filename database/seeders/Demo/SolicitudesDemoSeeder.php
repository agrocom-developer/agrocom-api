<?php

namespace Database\Seeders\Demo;

use App\Dominios\Asignacion\Aplicacion\IniciarAsignacion;
use App\Dominios\Flota\Infraestructura\Eloquent\Disponibilidad;
use App\Dominios\Flota\Infraestructura\Eloquent\Equipo;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Proveedor;
use App\Dominios\Solicitudes\Aplicacion\CancelarSolicitud;
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
                }
            }
        }
    }

    private function solicitud(Cliente $cliente, int $terrenoId, string $clave, EstadoSolicitud $destino, ?int $equipoId = null, ?int $proveedorId = null, bool $urgente = false): void
    {
        $uuid = Uuid::uuid5(Uuid::NAMESPACE_URL, "agrocom-demo-solicitud:{$clave}")->toString();

        if (SolicitudServicio::query()->where('uuid_cliente', $uuid)->exists()) {
            return;
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
            return;
        }

        if ($destino === EstadoSolicitud::Cancelada) {
            $this->cancelar->porCliente($cliente->usuario_id, $solicitud->id, 'Cambió la fecha de siembra del lote.');

            return;
        }

        $solicitud = Event::fakeFor(
            fn () => $this->publicar->ejecutar($cliente->usuario_id, $solicitud->id),
            [SolicitudPublicada::class],
        );

        if ($destino === EstadoSolicitud::Publicada) {
            // El motor la toma como en producción: ofrece a los propios y deja ofertas vivas.
            app(IniciarAsignacion::class)->ejecutar($solicitud->id);

            return;
        }

        $solicitud = $this->estados->transicionar($solicitud, EstadoSolicitud::EnAsignacion, ActorSolicitud::Sistema, null);

        if ($destino === EstadoSolicitud::SinProveedor) {
            $this->estados->transicionar($solicitud, EstadoSolicitud::SinProveedor, ActorSolicitud::Sistema, null, 'Ningún proveedor aceptó en las rondas previstas.');

            return;
        }

        $this->estados->transicionar($solicitud, EstadoSolicitud::Asignada, ActorSolicitud::Sistema, null, null, ['equipo_id' => $equipoId, 'proveedor_id' => $proveedorId]);
    }
}
