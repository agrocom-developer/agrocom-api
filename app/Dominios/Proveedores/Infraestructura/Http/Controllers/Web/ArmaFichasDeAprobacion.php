<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Dominio\AccionAprobacion;
use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Compartido\Dominio\TransicionesAprobacion;
use App\Dominios\Proveedores\Contratos\DatosDocumento;
use App\Dominios\Proveedores\Contratos\LecturaDocumentos;
use App\Dominios\Proveedores\Dominio\TipoSujeto;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Contratos\LecturaCuentas;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Lo que comparten las fichas de proveedor, piloto, auxiliar y dron del panel:
 * qué acciones de aprobación ofrecer según el estado y el permiso del rol
 * activo, la línea de tiempo del historial y las filas de documentos. Las
 * decisiones (la tabla de transiciones, el motivo, los documentos exigidos) no
 * viven acá: las toma el caso de uso. Esto solo arma lo que la vista dibuja.
 */
trait ArmaFichasDeAprobacion
{
    /**
     * Acciones que el rol activo puede ofrecer sobre un sujeto en `$estado`:
     * las que la tabla de transiciones admite, menos el reenvío (es del
     * solicitante) y las que el rol no tiene permiso de hacer.
     *
     * @param  string  $prefijoPermiso  `proveedores.proveedor`, `proveedores.piloto`, `proveedores.auxiliar` o `flota.dron`.
     * @return list<AccionAprobacion>
     */
    private function accionesOfrecidas(Request $request, AutorizacionPanelWeb $autorizacion, string $prefijoPermiso, EstadoAprobacion $estado): array
    {
        return array_values(array_filter(
            TransicionesAprobacion::accionesDesde($estado),
            fn (AccionAprobacion $accion): bool => ! $accion->esDelSolicitante()
                && $autorizacion->tienePermiso($request, $prefijoPermiso.'.'.self::permisoDeAccion($accion)),
        ));
    }

    /** Permiso (sufijo) que exige cada acción: aprobar, rechazar y rehabilitar comparten `aprobar`. */
    public static function permisoDeAccion(AccionAprobacion $accion): string
    {
        return match ($accion) {
            AccionAprobacion::Aprobar, AccionAprobacion::Rechazar, AccionAprobacion::Rehabilitar, AccionAprobacion::Reenviar => 'aprobar',
            AccionAprobacion::Suspender => 'suspender',
            AccionAprobacion::DarDeBaja => 'dar_baja',
        };
    }

    /**
     * @param  Collection<int, covariant object>  $filas  Filas de historial (con `estado_nuevo`, `accion`, `origen`, `actor_usuario_id`, `motivo`, `created_at`), de la más nueva a la más vieja.
     * @return list<array{title: string, meta: string, tone: string}>
     */
    private function lineaDeTiempo(Collection $filas, LecturaCuentas $cuentas, ?string $zonaHoraria): array
    {
        $actores = $cuentas->porIds($filas->pluck('actor_usuario_id')->filter()->unique()->values()->all());

        return $filas->map(function (object $fila) use ($actores, $zonaHoraria): array {
            $actor = $fila->actor_usuario_id === null ? null : ($actores[$fila->actor_usuario_id]->nombre ?? "#{$fila->actor_usuario_id}");
            $partes = [$this->fechaHora($fila->created_at, $zonaHoraria)];
            $partes[] = $fila->origen->value === 'sistema'
                ? __('proveedores.historial.por_el_sistema')
                : ($actor ?? __('proveedores.historial.sin_autor'));

            if ($fila->motivo !== null && $fila->motivo !== '') {
                $partes[] = $fila->motivo;
            }

            return [
                'title' => __('proveedores.historial.cambio', [
                    'de' => __('aprobacion.estado.'.$fila->estado_anterior->value),
                    'a' => __('aprobacion.estado.'.$fila->estado_nuevo->value),
                ]),
                'meta' => implode(' · ', $partes),
                'tone' => $fila->estado_nuevo->tono(),
            ];
        })->values()->all();
    }

    /**
     * Documentos de un sujeto para la tabla de la ficha, con los días para
     * vencer ya calculados.
     *
     * @return list<DatosDocumento>
     */
    private function documentosDe(TipoSujeto $sujeto, int $sujetoId): array
    {
        return app(LecturaDocumentos::class)->de($sujeto->value, $sujetoId);
    }

    private function fechaHora(?DateTimeInterface $momento, ?string $zonaHoraria): string
    {
        if ($momento === null) {
            return '—';
        }

        return Carbon::instance($momento)->timezone($zonaHoraria ?: config('app.timezone'))->format('d/m/Y H:i');
    }
}
