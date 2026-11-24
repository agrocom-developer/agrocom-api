<?php

namespace App\Dominios\Asignacion\Aplicacion;

use App\Dominios\Asignacion\Dominio\ElegibilidadDeProveedor;
use App\Dominios\Asignacion\Dominio\Geografia;
use App\Dominios\Asignacion\Dominio\GrupoOferta;
use App\Dominios\Asignacion\Dominio\PuntuacionDeTercero;
use App\Dominios\Asignacion\Dominio\RondaPlanificada;
use App\Dominios\Asignacion\Infraestructura\Eloquent\CancelacionDeProveedor;
use App\Dominios\Asignacion\Infraestructura\Eloquent\Oferta;
use App\Dominios\Flota\Contratos\DatosEquipoApto;
use App\Dominios\Flota\Contratos\LecturaDisponibilidad;
use App\Dominios\Flota\Contratos\LecturaEquipos;
use App\Dominios\Proveedores\Contratos\DatosProveedor;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Solicitudes\Contratos\DatosSolicitudParaAsignar;
use App\Dominios\Solicitudes\Contratos\LecturaSolicitudesParaAsignar;
use App\Dominios\Solicitudes\Contratos\LecturaZonas;
use Brick\Math\BigDecimal;

/**
 * Elige a quién se ofrece en una ronda (especificación §6, ADR 0030). Junta lo
 * que cada módulo sabe por sus contratos (proveedores aprobados, equipos aptos,
 * disponibilidad, agenda, zonas) y aplica {@see ElegibilidadDeProveedor}:
 *
 * - propios: del más cercano al más lejano;
 * - terceros e independientes: por puntuación descendente, y quien tiene la
 *   tasa de aceptación bajo el umbral va después de quien no (no recibe
 *   prioridad, política de calificación).
 *
 * Nunca repite a quien ya recibió una oferta en este ciclo ni a quien canceló
 * esta misma solicitud. Se queda con los primeros `max_proveedores_por_ronda`.
 */
final class SeleccionDeCandidatos
{
    public function __construct(
        private readonly LecturaProveedores $proveedores,
        private readonly LecturaEquipos $equipos,
        private readonly LecturaDisponibilidad $disponibilidad,
        private readonly LecturaSolicitudesParaAsignar $solicitudes,
        private readonly LecturaZonas $zonas,
        private readonly ParametrosDeAsignacion $parametros,
        private readonly SancionesVigentes $sanciones,
    ) {}

    /** @return list<Candidato> */
    public function ejecutar(DatosSolicitudParaAsignar $solicitud, RondaPlanificada $ronda, int $ciclo): array
    {
        $excluidos = array_flip([
            ...array_map('intval', Oferta::query()->where('solicitud_id', $solicitud->id)->where('ciclo', $ciclo)->pluck('proveedor_id')->all()),
            ...array_map('intval', CancelacionDeProveedor::query()->where('solicitud_id', $solicitud->id)->pluck('proveedor_id')->all()),
        ]);

        $ocupados = array_flip($this->solicitudes->equiposOcupados($solicitud->ventanaDesde, $solicitud->ventanaHasta, $solicitud->id));
        $servicio = UbicacionDelServicio::de($solicitud);
        $exigeLinea = $solicitud->urgente || $this->parametros->exigirEnLineaProgramada();
        $sancionados = array_flip($this->sanciones->proveedoresSinOfertas());

        $candidatos = [];

        foreach ($this->proveedores->aprobadosDelGrupo($ronda->grupo->value) as $proveedor) {
            if (isset($excluidos[$proveedor->id])) {
                continue;
            }

            $libres = $this->equiposLibres($proveedor->id, $ocupados, $exigeLinea);
            $distancia = $this->distanciaA($servicio, $proveedor, $libres['ubicaciones']);

            $motivos = ElegibilidadDeProveedor::motivosDeExclusion(
                $ronda->grupo,
                $libres['equipos'] !== [],
                isset($sancionados[$proveedor->id]),
                $proveedor->calificacionPromedio,
                $proveedor->calificacionesConteo,
                $this->parametros->umbralCalificacionParaOfertas(),
                $this->parametros->minimoCalificaciones(),
                $distancia,
                $proveedor->radioOperacionKm,
                $ronda->radioKm,
                $ronda->esUltima,
            );

            if ($motivos !== []) {
                continue;
            }

            $candidatos[] = new Candidato(
                $proveedor->id,
                $proveedor->tipo,
                $distancia,
                $ronda->grupo === GrupoOferta::Tercero ? $this->puntuacion($proveedor, $distancia, (string) $ronda->radioKm) : null,
                BigDecimal::of($proveedor->tasaAceptacion)->isGreaterThanOrEqualTo($this->parametros->umbralAceptacion()),
            );
        }

        usort($candidatos, $ronda->grupo === GrupoOferta::Propio ? $this->porCercania(...) : $this->porPuntuacion(...));

        return array_slice($candidatos, 0, $this->parametros->maxProveedoresPorRonda());
    }

    /**
     * Equipos aptos, con la capacidad pedida, libres en la ventana y, si hace
     * falta, en línea; y las ubicaciones desde las que podrían salir.
     *
     * @param  array<int, int>  $ocupados  equipos ocupados como claves.
     * @return array{equipos: list<DatosEquipoApto>, ubicaciones: list<array{lat: float, lng: float}>}
     */
    private function equiposLibres(int $proveedorId, array $ocupados, bool $exigeLinea): array
    {
        $capacidad = $this->parametros->capacidadMinimaTanqueL();

        $aptos = array_values(array_filter(
            $this->equipos->aptosDe($proveedorId),
            fn (DatosEquipoApto $equipo): bool => ! isset($ocupados[$equipo->equipoId])
                && BigDecimal::of($equipo->capacidadTanqueL)->isGreaterThanOrEqualTo($capacidad),
        ));

        $disponibilidad = $this->disponibilidad->porEquipos(array_map(fn (DatosEquipoApto $e): int => $e->equipoId, $aptos));

        $equipos = [];
        $ubicaciones = [];

        foreach ($aptos as $equipo) {
            $estado = $disponibilidad[$equipo->equipoId] ?? null;

            if ($exigeLinea && ($estado === null || ! $estado->enLinea)) {
                continue;
            }

            $equipos[] = $equipo;

            if ($estado !== null && $estado->enLinea && $estado->lat !== null && $estado->lng !== null) {
                $ubicaciones[] = ['lat' => (float) $estado->lat, 'lng' => (float) $estado->lng];
            }
        }

        return ['equipos' => $equipos, 'ubicaciones' => $ubicaciones];
    }

    /**
     * Distancia del servicio al punto más cercano desde el que el proveedor
     * puede salir: la última ubicación de un equipo en línea o, si no hay, el
     * centro de su zona base.
     *
     * @param  array{lat: float, lng: float}|null  $servicio
     * @param  list<array{lat: float, lng: float}>  $ubicaciones
     */
    private function distanciaA(?array $servicio, DatosProveedor $proveedor, array $ubicaciones): ?string
    {
        if ($servicio === null) {
            return null;
        }

        if ($ubicaciones === [] && $proveedor->zonaBaseId !== null) {
            $centro = $this->zonas->centroide($proveedor->zonaBaseId);
            $ubicaciones = $centro === null ? [] : [$centro];
        }

        if ($ubicaciones === []) {
            return null;
        }

        $mejor = min(array_map(
            fn (array $punto): float => Geografia::distanciaKm($servicio['lat'], $servicio['lng'], $punto['lat'], $punto['lng']),
            $ubicaciones,
        ));

        return number_format($mejor, 2, '.', '');
    }

    private function puntuacion(DatosProveedor $proveedor, ?string $distancia, string $radio): string
    {
        return PuntuacionDeTercero::calcular(
            $this->parametros->pesos(),
            $distancia,
            $radio,
            $proveedor->calificacionesConteo > 0 ? $proveedor->calificacionPromedio : $this->parametros->calificacionNeutra(),
            $proveedor->tasaAceptacion,
            $proveedor->tasaCancelacion,
        );
    }

    private function porCercania(Candidato $a, Candidato $b): int
    {
        return [$a->distanciaKm === null, (float) $a->distanciaKm, $a->proveedorId]
            <=> [$b->distanciaKm === null, (float) $b->distanciaKm, $b->proveedorId];
    }

    private function porPuntuacion(Candidato $a, Candidato $b): int
    {
        return [! $a->conPrioridad, -((float) $a->puntuacion), $a->proveedorId]
            <=> [! $b->conPrioridad, -((float) $b->puntuacion), $b->proveedorId];
    }
}
