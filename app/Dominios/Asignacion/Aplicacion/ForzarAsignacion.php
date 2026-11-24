<?php

namespace App\Dominios\Asignacion\Aplicacion;

use App\Dominios\Asignacion\Aplicacion\MaquinaEstados\MaquinaEstadosOferta;
use App\Dominios\Asignacion\Contratos\Eventos\SolicitudAsignada;
use App\Dominios\Asignacion\Dominio\EstadoOferta;
use App\Dominios\Asignacion\Dominio\Excepciones\AsignacionInvalida;
use App\Dominios\Asignacion\Dominio\GrupoOferta;
use App\Dominios\Asignacion\Dominio\MotivoRetiro;
use App\Dominios\Asignacion\Dominio\OrigenOferta;
use App\Dominios\Asignacion\Infraestructura\Eloquent\Oferta;
use App\Dominios\Asignacion\Infraestructura\Eloquent\Ronda;
use App\Dominios\Flota\Contratos\DatosEquipoApto;
use App\Dominios\Flota\Contratos\LecturaEquipos;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Solicitudes\Contratos\AsignacionDeSolicitud;
use App\Dominios\Solicitudes\Contratos\LecturaSolicitudesParaAsignar;
use App\Dominios\Solicitudes\Contratos\SolicitudNoAsignable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Un administrador asigna una solicitud a un proveedor a mano, con motivo
 * (`/admin/solicitudes`, especificación §10.4; ADR 0030, punto 6). Sirve para
 * una solicitud `en_asignacion` o `sin_proveedor` y para reasignar una
 * `asignada` o `en_camino`. Pasa por las mismas reglas que una aceptación: el
 * proveedor debe estar aprobado y tener un equipo apto y libre, y la solicitud
 * pasa por su máquina de estados con origen `administrador` y el motivo en el
 * historial.
 *
 * Abre un ciclo nuevo, retira lo que siga vivo y deja constancia en
 * `asg_ofertas` con origen `administrador`: así el historial de la solicitud
 * cuenta toda la asignación en un solo lugar y las métricas no se pierden. Una
 * reasignación no cuenta contra el proveedor anterior.
 */
final class ForzarAsignacion
{
    public function __construct(
        private readonly LecturaSolicitudesParaAsignar $solicitudes,
        private readonly AsignacionDeSolicitud $asignacion,
        private readonly LecturaProveedores $proveedores,
        private readonly LecturaEquipos $equipos,
        private readonly MaquinaEstadosOferta $ofertas,
        private readonly RetirarOfertas $retirar,
        private readonly AvanzarAsignacion $avanzar,
    ) {}

    /**
     * @throws AsignacionInvalida
     */
    public function ejecutar(int $actorUsuarioId, int $solicitudId, int $proveedorId, ?int $equipoId, string $motivo): Oferta
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw AsignacionInvalida::motivoRequerido();
        }

        $proveedor = $this->proveedores->proveedor($proveedorId);

        if ($proveedor === null || ! $proveedor->estaAprobado()) {
            throw AsignacionInvalida::proveedorNoElegible();
        }

        return DB::transaction(function () use ($actorUsuarioId, $solicitudId, $proveedor, $equipoId, $motivo): Oferta {
            $solicitud = $this->solicitudes->datosBloqueando($solicitudId) ?? throw AsignacionInvalida::solicitudAjena();

            $equipo = $this->elegirEquipo($proveedor->id, $solicitud->ventanaDesde, $solicitud->ventanaHasta, $solicitudId, $equipoId);

            try {
                match ($solicitud->estado) {
                    'en_asignacion' => null,
                    'sin_proveedor' => $this->asignacion->reabrir($solicitudId, $actorUsuarioId, $motivo),
                    'asignada', 'en_camino' => $this->asignacion->devolver($solicitudId, 'administrador', $actorUsuarioId, $motivo),
                    default => throw AsignacionInvalida::solicitudNoForzable(),
                };
            } catch (SolicitudNoAsignable) {
                throw AsignacionInvalida::solicitudNoForzable();
            }

            // Lo que quedaba vivo ya no corresponde, y el proveedor que la tenía asignada la pierde sin costo.
            $this->retirar->retirarVivas($solicitudId, null, MotivoRetiro::AsignacionForzada);

            Oferta::query()
                ->where('solicitud_id', $solicitudId)
                ->where('estado', EstadoOferta::Aceptada->value)
                ->lockForUpdate()
                ->get()
                ->each(fn (Oferta $anterior) => $this->retirar->retirar($anterior, MotivoRetiro::Reasignada));

            $ciclo = (int) Ronda::query()->where('solicitud_id', $solicitudId)->max('ciclo') + 1;
            $ahora = Carbon::now();

            Ronda::query()->where('solicitud_id', $solicitudId)->whereNull('cerrada_en')
                ->get()->each(fn (Ronda $ronda) => $ronda->forceFill(['cerrada_en' => $ahora])->save());

            Ronda::query()->create([
                'solicitud_id' => $solicitudId,
                'ciclo' => $ciclo,
                'ronda' => 1,
                'grupo' => GrupoOferta::deTipoDeProveedor($proveedor->tipo)->value,
                'radio_km' => null,
                'proveedores_ofrecidos' => 1,
                'iniciada_en' => $ahora,
                'cerrada_en' => $ahora,
            ]);

            $oferta = $this->ofertas->ofrecer([
                'solicitud_id' => $solicitudId,
                'proveedor_id' => $proveedor->id,
                'ciclo' => $ciclo,
                'ronda' => 1,
                'grupo' => GrupoOferta::deTipoDeProveedor($proveedor->tipo)->value,
                'origen' => OrigenOferta::Administrador->value,
                'ofrecida_en' => $ahora,
                'vence_en' => $ahora,
                'pago_estimado_proveedor' => $this->avanzar->pagoEstimado($solicitud->montoEstimado, $proveedor->tipo),
            ]);

            $this->ofertas->transicionar($oferta, EstadoOferta::Aceptada, ['equipo_id' => $equipo->equipoId]);

            try {
                $this->asignacion->asignar($solicitudId, $proveedor->id, $equipo->equipoId, 'administrador', $actorUsuarioId, $motivo);
            } catch (SolicitudNoAsignable) {
                throw AsignacionInvalida::solicitudNoForzable();
            }

            event(new SolicitudAsignada(
                $solicitudId,
                $ciclo,
                $solicitud->clienteUsuarioId,
                $proveedor->id,
                $proveedor->razonSocial,
                $equipo->equipoId,
                $this->proveedores->usuariosDeAviso($proveedor->id),
                'administrador',
            ));

            return $oferta;
        });
    }

    private function elegirEquipo(int $proveedorId, string $desde, string $hasta, int $solicitudId, ?int $equipoId): DatosEquipoApto
    {
        $aptos = $this->equipos->aptosDe($proveedorId);
        $ocupados = array_flip($this->solicitudes->equiposOcupados($desde, $hasta, $solicitudId));
        $libres = array_values(array_filter($aptos, fn (DatosEquipoApto $equipo): bool => ! isset($ocupados[$equipo->equipoId])));

        if ($equipoId === null) {
            return $libres[0] ?? throw AsignacionInvalida::sinEquipoDisponible();
        }

        foreach ($aptos as $apto) {
            if ($apto->equipoId === $equipoId) {
                return isset($ocupados[$equipoId]) ? throw AsignacionInvalida::sinEquipoDisponible() : $apto;
            }
        }

        throw AsignacionInvalida::equipoNoApto();
    }
}
