<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Proveedores\Aplicacion\EstadoDocumentalDeSujeto;
use App\Dominios\Proveedores\Dominio\TipoSujeto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Auxiliar;
use App\Dominios\Proveedores\Infraestructura\Eloquent\HistorialEstado;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Piloto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Proveedor;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Contratos\LecturaCuentas;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET /panel/auxiliares` y `GET /panel/auxiliares/{auxiliar}`: auxiliares de
 * todos los proveedores y su ficha de revisión (ADR 0032, punto 2). Gateada por
 * `proveedores.auxiliar.ver` contra el ROL ACTIVO.
 */
final class AuxiliaresController
{
    use ArmaFichasDeAprobacion;

    private const PERMISO_VER = 'proveedores.auxiliar.ver';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, LecturaCuentas $cuentas): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = TextoDeFiltro::de($request, 'q');
        $estado = EstadoAprobacion::tryFrom(TextoDeFiltro::de($request, 'estado'));

        $candidatas = Auxiliar::query()->pluck('usuario_id')->all();
        $patron = mb_strtolower($busqueda);
        $idsPorNombre = $busqueda === '' ? [] : array_keys(array_filter(
            $cuentas->porIds($candidatas),
            static fn ($cuenta): bool => str_contains(mb_strtolower($cuenta->nombre), $patron),
        ));

        $auxiliares = Auxiliar::query()
            ->when($busqueda !== '', fn ($consulta) => $consulta->whereIn('usuario_id', $idsPorNombre))
            ->when($estado !== null, fn ($consulta) => $consulta->where('estado', $estado->value))
            ->orderByRaw("CASE estado WHEN 'pendiente' THEN 0 WHEN 'aprobado' THEN 1 WHEN 'suspendido' THEN 2 ELSE 3 END")
            ->orderBy('created_at')
            ->paginate(15)
            ->withQueryString();

        $pilotosIds = $auxiliares->getCollection()->pluck('registrado_por_piloto_id')->filter()->unique();
        $pilotos = Piloto::query()->whereIn('id', $pilotosIds)->get(['id', 'usuario_id']);

        return view('proveedores::pages.auxiliares.index', [
            ...$this->autorizacion->cascara($request),
            'auxiliares' => $auxiliares,
            'nombres' => $cuentas->porIds([...$auxiliares->getCollection()->pluck('usuario_id')->all(), ...$pilotos->pluck('usuario_id')->all()]),
            'proveedores' => Proveedor::query()->whereIn('id', $auxiliares->getCollection()->pluck('proveedor_id')->unique())->pluck('razon_social', 'id'),
            'usuarioDePiloto' => $pilotos->pluck('usuario_id', 'id'),
            'filtros' => ['q' => $busqueda, 'estado' => $estado?->value],
            'estadosFiltro' => EstadoAprobacion::cases(),
        ]);
    }

    public function show(Request $request, Auxiliar $auxiliar, LecturaCuentas $cuentas, EstadoDocumentalDeSujeto $estadoDocumental): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $cascara = $this->autorizacion->cascara($request);

        $piloto = $auxiliar->registrado_por_piloto_id === null ? null : Piloto::query()->find($auxiliar->registrado_por_piloto_id);
        $cuentasMostradas = $cuentas->porIds(array_filter([$auxiliar->usuario_id, $piloto?->usuario_id]));

        $historial = HistorialEstado::query()
            ->where('sujeto_tipo', TipoSujeto::Auxiliar->value)
            ->where('sujeto_id', $auxiliar->id)
            ->orderByDesc('id')
            ->get();

        return view('proveedores::pages.auxiliares.show', [
            ...$cascara,
            'auxiliar' => $auxiliar->load('proveedor'),
            'cuenta' => $cuentasMostradas[$auxiliar->usuario_id] ?? null,
            'registradoPor' => $piloto === null ? null : ($cuentasMostradas[$piloto->usuario_id]->nombre ?? "#{$piloto->usuario_id}"),
            'documentos' => $this->documentosDe(TipoSujeto::Auxiliar, $auxiliar->id),
            'documentosFaltantes' => $estadoDocumental->faltantesDe(TipoSujeto::Auxiliar, $auxiliar->id),
            'acciones' => $this->accionesOfrecidas($request, $this->autorizacion, 'proveedores.auxiliar', $auxiliar->estado),
            'puedeRevisarDocumentos' => $this->autorizacion->tienePermiso($request, 'proveedores.documento.revisar'),
            'puedeVerDocumentos' => $this->autorizacion->tienePermiso($request, 'proveedores.documento.ver'),
            'puedeVerProveedor' => $this->autorizacion->tienePermiso($request, 'proveedores.proveedor.ver'),
            'lineaDeTiempo' => $this->lineaDeTiempo($historial, $cuentas, $cascara['zonaHoraria'] ?? null),
        ]);
    }
}
