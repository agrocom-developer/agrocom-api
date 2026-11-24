<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaTexto;
use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Flota\Contratos\LecturaEquipos;
use App\Dominios\Proveedores\Aplicacion\EstadoDocumentalDeSujeto;
use App\Dominios\Proveedores\Dominio\TipoProveedor;
use App\Dominios\Proveedores\Dominio\TipoSujeto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Auxiliar;
use App\Dominios\Proveedores\Infraestructura\Eloquent\HistorialEstado;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Piloto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Proveedor;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Contratos\LecturaCuentas;
use App\Dominios\Solicitudes\Contratos\LecturaZonas;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET /panel/proveedores` y `GET /panel/proveedores/{proveedor}`: la cola de
 * proveedores y su ficha de revisión (ADR 0032). Cuatro estados de aprobación
 * y tres tipos; la ficha reúne datos, documentos, personal, flota e historial,
 * y ofrece las acciones de aprobación que el rol activo puede hacer.
 *
 * Gateada por `proveedores.proveedor.ver`, verificado DENTRO del controlador
 * contra el ROL ACTIVO (invariante 10).
 */
final class ProveedoresController
{
    use ArmaFichasDeAprobacion;

    private const PERMISO_VER = 'proveedores.proveedor.ver';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, LecturaCuentas $cuentas): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = TextoDeFiltro::de($request, 'q');
        $estado = EstadoAprobacion::tryFrom(TextoDeFiltro::de($request, 'estado'));
        $tipo = TipoProveedor::tryFrom(TextoDeFiltro::de($request, 'tipo'));

        $proveedores = Proveedor::query()
            ->when($busqueda !== '', fn ($consulta) => BusquedaTexto::aplicar($consulta, ['razon_social', 'documento_fiscal'], $busqueda))
            ->when($estado !== null, fn ($consulta) => $consulta->where('estado', $estado->value))
            ->when($tipo !== null, fn ($consulta) => $consulta->where('tipo', $tipo->value))
            // La cola de revisión primero: los pendientes más antiguos arriba.
            ->orderByRaw("CASE estado WHEN 'pendiente' THEN 0 WHEN 'aprobado' THEN 1 WHEN 'suspendido' THEN 2 ELSE 3 END")
            ->orderBy('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('proveedores::pages.proveedores.index', [
            ...$this->autorizacion->cascara($request),
            'proveedores' => $proveedores,
            'titulares' => $cuentas->porIds($proveedores->getCollection()->pluck('usuario_titular_id')->filter()->all()),
            'filtros' => ['q' => $busqueda, 'estado' => $estado?->value, 'tipo' => $tipo?->value],
            'estadosFiltro' => EstadoAprobacion::cases(),
            'tiposFiltro' => TipoProveedor::cases(),
        ]);
    }

    public function show(
        Request $request,
        Proveedor $proveedor,
        LecturaCuentas $cuentas,
        LecturaZonas $zonas,
        LecturaEquipos $equipos,
        EstadoDocumentalDeSujeto $estadoDocumental,
    ): View {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $cascara = $this->autorizacion->cascara($request);

        $titular = $proveedor->usuario_titular_id === null ? null : ($cuentas->porIds([$proveedor->usuario_titular_id])[$proveedor->usuario_titular_id] ?? null);

        $pilotos = Piloto::query()->where('proveedor_id', $proveedor->id)->orderBy('id')->get();
        $auxiliares = Auxiliar::query()->where('proveedor_id', $proveedor->id)->orderBy('id')->get();
        $nombres = $cuentas->porIds([...$pilotos->pluck('usuario_id')->all(), ...$auxiliares->pluck('usuario_id')->all()]);

        $historial = HistorialEstado::query()
            ->where('sujeto_tipo', TipoSujeto::Proveedor->value)
            ->where('sujeto_id', $proveedor->id)
            ->orderByDesc('id')
            ->get();

        return view('proveedores::pages.proveedores.show', [
            ...$cascara,
            'proveedor' => $proveedor,
            'titular' => $titular,
            'zona' => $proveedor->zona_base_id === null ? null : $zonas->nombre($proveedor->zona_base_id),
            'pilotos' => $pilotos,
            'auxiliares' => $auxiliares,
            'nombres' => $nombres,
            'documentos' => $this->documentosDe(TipoSujeto::Proveedor, $proveedor->id),
            'documentosFaltantes' => $estadoDocumental->faltantesDe(TipoSujeto::Proveedor, $proveedor->id),
            'aptoParaOfertas' => $equipos->tieneEquipoApto($proveedor->id),
            'acciones' => $this->accionesOfrecidas($request, $this->autorizacion, 'proveedores.proveedor', $proveedor->estado),
            'puedeRevisarDocumentos' => $this->autorizacion->tienePermiso($request, 'proveedores.documento.revisar'),
            'puedeVerDocumentos' => $this->autorizacion->tienePermiso($request, 'proveedores.documento.ver'),
            'puedeVerPilotos' => $this->autorizacion->tienePermiso($request, 'proveedores.piloto.ver'),
            'puedeVerAuxiliares' => $this->autorizacion->tienePermiso($request, 'proveedores.auxiliar.ver'),
            'lineaDeTiempo' => $this->lineaDeTiempo($historial, $cuentas, $cascara['zonaHoraria'] ?? null),
        ]);
    }
}
