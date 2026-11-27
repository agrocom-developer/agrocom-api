<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Aplicacion\Medios\AlmacenDeMedios;
use App\Dominios\Compartido\Aplicacion\Medios\RegistrarAccesoAMedio;
use App\Dominios\Compartido\Dominio\Excepciones\MotivoRequerido;
use App\Dominios\Compartido\Dominio\TipoDocumento;
use App\Dominios\Compartido\Infraestructura\Busqueda\BusquedaTexto;
use App\Dominios\Compartido\Dominio\VarianteDeMedio;
use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Proveedores\Aplicacion\ResolverSujeto;
use App\Dominios\Proveedores\Aplicacion\RevisarDocumento;
use App\Dominios\Proveedores\Dominio\EstadoDocumento;
use App\Dominios\Proveedores\Dominio\Excepciones\RegistroInvalido;
use App\Dominios\Proveedores\Dominio\TipoSujeto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Documento;
use App\Dominios\Proveedores\Infraestructura\Http\Requests\RevisarDocumentoRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cola de revisión de documentos (ADR 0032, punto 3): el administrador ve lo que
 * espera revisión, abre el archivo (acceso restringido, nunca por URL pública) y
 * lo aprueba o lo rechaza con motivo.
 *
 * `proveedores.documento.ver` abre la cola y el archivo; `.revisar` aprueba o
 * rechaza. Los dos se verifican contra el ROL ACTIVO.
 */
final class DocumentosController
{
    private const PERMISO_VER = 'proveedores.documento.ver';

    private const PERMISO_REVISAR = 'proveedores.documento.revisar';

    private const PERMISO_DESCARGAR = 'proveedores.documento.descargar';

    private const RUTA_FICHA = [
        'proveedor' => 'panel.proveedores.show',
        'piloto' => 'panel.pilotos.show',
        'dron' => 'panel.drones-flota.show',
    ];

    public function __construct(
        private readonly AutorizacionPanelWeb $autorizacion,
        private readonly AlmacenDeMedios $almacen,
        private readonly RegistrarAccesoAMedio $accesos,
    ) {}

    public function index(Request $request, ResolverSujeto $sujetos): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $estado = TextoDeFiltro::de($request, 'estado');
        $estado = $request->has('estado')
            ? EstadoDocumento::tryFrom($estado)
            : EstadoDocumento::EnRevision;
        $sujetoTipo = TipoSujeto::tryFrom(TextoDeFiltro::de($request, 'sujeto_tipo'));
        $tipo = TipoDocumento::tryFrom(TextoDeFiltro::de($request, 'tipo'));

        $busqueda = TextoDeFiltro::de($request, 'q');

        $documentos = Documento::query()
            ->when($busqueda !== '', fn ($consulta) => BusquedaTexto::aplicar($consulta, ['nombre_original'], $busqueda))
            ->when($estado !== null, fn ($consulta) => $consulta->where('estado', $estado->value))
            ->when($sujetoTipo !== null, fn ($consulta) => $consulta->where('sujeto_tipo', $sujetoTipo->value))
            ->when($tipo !== null, fn ($consulta) => $consulta->where('tipo', $tipo->value))
            ->orderBy('created_at')
            ->paginate(15)
            ->withQueryString();

        $etiquetas = [];
        foreach ($documentos->getCollection() as $documento) {
            $etiquetas[$documento->id] = $sujetos->ejecutar($documento->sujeto_tipo, $documento->sujeto_id)->nombre ?? "#{$documento->sujeto_id}";
        }

        return view('proveedores::pages.documentos.index', [
            ...$this->autorizacion->cascara($request),
            'documentos' => $documentos,
            'etiquetas' => $etiquetas,
            'rutaFicha' => self::RUTA_FICHA,
            'filtros' => ['q' => $busqueda, 'estado' => $estado?->value, 'sujeto_tipo' => $sujetoTipo?->value, 'tipo' => $tipo?->value],
            'estadosFiltro' => EstadoDocumento::cases(),
            'sujetosFiltro' => TipoSujeto::cases(),
            'tiposFiltro' => TipoDocumento::cases(),
            'puedeRevisar' => $this->autorizacion->tienePermiso($request, self::PERMISO_REVISAR),
        ]);
    }

    /**
     * El archivo en el visor del panel. `?variante=miniatura|media` entrega el derivado reducido (cuadrículas y
     * vistas previas); sin variante, el original, y solo ese acceso queda en la bitácora (quién y cuándo).
     */
    public function archivo(Request $request, Documento $documento): StreamedResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $variante = VarianteDeMedio::desde($request->query('variante'));

        $respuesta = $this->almacen->respuesta(
            $documento->archivo_ref,
            $variante,
            $this->disco(),
            mime: $documento->mime,
        );

        abort_if($respuesta === null, 404, __('proveedores.errores.archivo_no_disponible'));

        if ($variante === VarianteDeMedio::Original) {
            $this->accesos->ejecutar($this->actor(), $documento->getTable(), $documento->id, 'visto');
        }

        return $respuesta;
    }

    /**
     * Descarga del ORIGINAL con un permiso propio (`proveedores.documento.descargar`): los documentos de
     * identidad y licencia son sensibles. Cada descarga queda en la bitácora (quién, qué y cuándo). El nombre
     * del archivo lo arma el servidor (`{tipo}-{id}.{ext}`): no repite el que subió la persona.
     */
    public function descargar(Request $request, Documento $documento): StreamedResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_DESCARGAR), 403);

        $extension = pathinfo($documento->archivo_ref, PATHINFO_EXTENSION);
        $nombre = $documento->tipo->value.'-'.$documento->id.($extension === '' ? '' : '.'.$extension);

        $respuesta = $this->almacen->respuesta($documento->archivo_ref, VarianteDeMedio::Original, $this->disco(), $nombre, true, $documento->mime);

        abort_if($respuesta === null, 404, __('proveedores.errores.archivo_no_disponible'));

        $this->accesos->ejecutar($this->actor(), $documento->getTable(), $documento->id, 'descargado');

        return $respuesta;
    }

    public function revisar(RevisarDocumentoRequest $request, Documento $documento, RevisarDocumento $revisar): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_REVISAR), 403);

        $volver = redirect()->back(fallback: route('panel.documentos.index'));
        $actor = (int) Auth::guard('interno')->id();

        try {
            if ($request->validated('decision') === 'aprobar') {
                $revisar->aprobar($documento->id, $actor);
            } else {
                $revisar->rechazar($documento->id, $actor, $request->validated('motivo'));
            }
        } catch (RegistroInvalido|MotivoRequerido $excepcion) {
            return $volver->withErrors(['documento' => $excepcion->getMessage()]);
        }

        return $volver->with('estado', __('proveedores.documentos.revisado.'.$request->validated('decision')));
    }

    private function disco(): string
    {
        return (string) config('proveedores.disco_documentos', 'local');
    }

    private function actor(): ?int
    {
        $id = Auth::guard('interno')->id();

        return $id === null ? null : (int) $id;
    }
}
