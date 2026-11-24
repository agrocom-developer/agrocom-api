<?php

namespace App\Dominios\Asignacion\Aplicacion;

use App\Dominios\Asignacion\Aplicacion\MaquinaEstados\MaquinaEstadosOferta;
use App\Dominios\Asignacion\Contratos\Eventos\SolicitudAsignada;
use App\Dominios\Asignacion\Dominio\EstadoOferta;
use App\Dominios\Asignacion\Dominio\Excepciones\AsignacionInvalida;
use App\Dominios\Asignacion\Dominio\MotivoRetiro;
use App\Dominios\Asignacion\Infraestructura\Eloquent\Oferta;
use App\Dominios\Flota\Contratos\DatosEquipoApto;
use App\Dominios\Flota\Contratos\LecturaDisponibilidad;
use App\Dominios\Flota\Contratos\LecturaEquipos;
use App\Dominios\Proveedores\Contratos\DatosProveedor;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Solicitudes\Contratos\AsignacionDeSolicitud;
use App\Dominios\Solicitudes\Contratos\DatosSolicitudParaAsignar;
use App\Dominios\Solicitudes\Contratos\LecturaSolicitudesParaAsignar;
use App\Dominios\Solicitudes\Contratos\SolicitudNoAsignable;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * El proveedor acepta o rechaza una oferta (`POST /ofertas/{id}/aceptar` y
 * `/rechazar`, especificación §10.2). Requiere red: es la única acción de
 * negocio que no admite outbox (ADR 0028).
 *
 * Aceptar es atómico sobre la solicitud: la transacción empieza bloqueando su
 * fila y, con ella bloqueada, la solicitud pasa a `asignada` con el equipo, la
 * oferta a `aceptada` y las demás vivas a `retiradas`. Dos proveedores que
 * aceptan a la vez se serializan: el segundo encuentra su oferta retirada y
 * recibe conflicto (409). Responder dos veces lo mismo es inofensivo: aceptar
 * una oferta ya aceptada por el mismo proveedor devuelve la misma oferta sin
 * repetir avisos.
 */
final class ResponderOferta
{
    public function __construct(
        private readonly ProveedorDeLaCuenta $cuentas,
        private readonly LecturaSolicitudesParaAsignar $solicitudes,
        private readonly AsignacionDeSolicitud $asignacion,
        private readonly MaquinaEstadosOferta $ofertas,
        private readonly RetirarOfertas $retirar,
        private readonly ContinuarAsignacion $continuar,
        private readonly EvaluarProveedor $evaluar,
        private readonly SancionesVigentes $sanciones,
        private readonly LecturaProveedores $proveedores,
        private readonly LecturaEquipos $equipos,
        private readonly LecturaDisponibilidad $disponibilidad,
        private readonly ParametrosDeAsignacion $parametros,
    ) {}

    /**
     * @throws AsignacionInvalida
     */
    public function aceptar(int $usuarioId, int $ofertaId, ?int $equipoId = null): Oferta
    {
        $proveedor = $this->cuentas->ejecutar($usuarioId);

        $oferta = DB::transaction(function () use ($proveedor, $ofertaId, $equipoId, $usuarioId): Oferta {
            $oferta = $this->propia($proveedor, $ofertaId);
            $solicitud = $this->solicitudes->datosBloqueando($oferta->solicitud_id) ?? throw AsignacionInvalida::ofertaAjena();
            $oferta = $this->bloquear($oferta);

            if ($oferta->estado === EstadoOferta::Aceptada) {
                return $oferta;
            }

            $this->exigirViva($oferta);

            if ($solicitud->estado !== 'en_asignacion') {
                throw AsignacionInvalida::solicitudYaAsignada();
            }

            if ($this->sanciones->impideOfertas($proveedor->id)) {
                throw AsignacionInvalida::proveedorSancionado();
            }

            $equipo = $this->elegirEquipo($proveedor, $solicitud, $equipoId);

            try {
                $this->asignacion->asignar($solicitud->id, $proveedor->id, $equipo->equipoId, 'proveedor', $usuarioId, null);
            } catch (SolicitudNoAsignable) {
                throw AsignacionInvalida::solicitudYaAsignada();
            }

            $this->ofertas->transicionar($oferta, EstadoOferta::Aceptada, ['equipo_id' => $equipo->equipoId]);
            $this->retirar->retirarVivas($solicitud->id, $oferta->id, MotivoRetiro::OtroAcepto);
            $this->retirar->cerrarRondas($solicitud->id);

            event(new SolicitudAsignada(
                $solicitud->id,
                $oferta->ciclo,
                $solicitud->clienteUsuarioId,
                $proveedor->id,
                $proveedor->razonSocial,
                $equipo->equipoId,
                $this->proveedores->usuariosDeAviso($proveedor->id),
                'proveedor',
            ));

            return $oferta;
        });

        $this->evaluar->ejecutar($proveedor->id);

        return $oferta;
    }

    /**
     * @throws AsignacionInvalida
     */
    public function rechazar(int $usuarioId, int $ofertaId, string $motivo): Oferta
    {
        $proveedor = $this->cuentas->ejecutar($usuarioId);
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw AsignacionInvalida::motivoRequerido();
        }

        $oferta = DB::transaction(function () use ($proveedor, $ofertaId, $motivo): Oferta {
            $oferta = $this->propia($proveedor, $ofertaId);
            $solicitud = $this->solicitudes->datosBloqueando($oferta->solicitud_id) ?? throw AsignacionInvalida::ofertaAjena();
            $oferta = $this->bloquear($oferta);

            if ($oferta->estado === EstadoOferta::Rechazada) {
                return $oferta;
            }

            $this->exigirViva($oferta);

            $this->ofertas->transicionar($oferta, EstadoOferta::Rechazada, ['motivo_rechazo' => $motivo]);
            $this->continuar->tras($solicitud);

            return $oferta;
        });

        $this->evaluar->ejecutar($proveedor->id);

        return $oferta;
    }

    private function propia(DatosProveedor $proveedor, int $ofertaId): Oferta
    {
        return Oferta::query()->where('id', $ofertaId)->where('proveedor_id', $proveedor->id)->first() ?? throw AsignacionInvalida::ofertaAjena();
    }

    /** Relee la oferta con su fila bloqueada, ya con la solicitud bloqueada: siempre en ese orden. */
    private function bloquear(Oferta $oferta): Oferta
    {
        return Oferta::query()->lockForUpdate()->findOrFail($oferta->id);
    }

    private function exigirViva(Oferta $oferta): void
    {
        if (! $oferta->estado->esViva()) {
            throw AsignacionInvalida::ofertaNoDisponible($oferta->estado->value);
        }

        if ($oferta->vence_en->lessThanOrEqualTo(Carbon::now())) {
            throw AsignacionInvalida::ofertaVencida();
        }
    }

    /**
     * El equipo con que el proveedor atiende: el que indica o, sin indicación,
     * el primero apto y libre, prefiriendo uno en línea.
     */
    private function elegirEquipo(DatosProveedor $proveedor, DatosSolicitudParaAsignar $solicitud, ?int $equipoId): DatosEquipoApto
    {
        $capacidad = $this->parametros->capacidadMinimaTanqueL();

        $aptos = array_values(array_filter(
            $this->equipos->aptosDe($proveedor->id),
            fn (DatosEquipoApto $equipo): bool => BigDecimal::of($equipo->capacidadTanqueL)->isGreaterThanOrEqualTo($capacidad),
        ));

        $ocupados = array_flip($this->solicitudes->equiposOcupados($solicitud->ventanaDesde, $solicitud->ventanaHasta, $solicitud->id));
        $libres = array_values(array_filter($aptos, fn (DatosEquipoApto $equipo): bool => ! isset($ocupados[$equipo->equipoId])));

        if ($equipoId !== null) {
            foreach ($aptos as $apto) {
                if ($apto->equipoId === $equipoId) {
                    return isset($ocupados[$equipoId]) ? throw AsignacionInvalida::sinEquipoDisponible() : $apto;
                }
            }

            throw AsignacionInvalida::equipoNoApto();
        }

        if ($libres === []) {
            throw AsignacionInvalida::sinEquipoDisponible();
        }

        $enLinea = $this->disponibilidad->porEquipos(array_map(fn (DatosEquipoApto $e): int => $e->equipoId, $libres));

        usort($libres, fn (DatosEquipoApto $a, DatosEquipoApto $b): int => [! ($enLinea[$a->equipoId]->enLinea ?? false), $a->equipoId] <=> [! ($enLinea[$b->equipoId]->enLinea ?? false), $b->equipoId]);

        return $libres[0];
    }
}
