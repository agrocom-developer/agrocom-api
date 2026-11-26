<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Dominio\Excepciones\MotivoRequerido;
use App\Dominios\Compartido\Dominio\TipoDocumento;
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
use Illuminate\Support\Facades\Storage;
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

    private const RUTA_FICHA = [
        'proveedor' => 'panel.proveedores.show',
        'piloto' => 'panel.pilotos.show',
        'dron' => 'panel.drones-flota.show',
    ];

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, ResolverSujeto $sujetos): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $estado = TextoDeFiltro::de($request, 'estado');
        $estado = $request->has('estado')
            ? EstadoDocumento::tryFrom($estado)
            : EstadoDocumento::EnRevision;
        $sujetoTipo = TipoSujeto::tryFrom(TextoDeFiltro::de($request, 'sujeto_tipo'));
        $tipo = TipoDocumento::tryFrom(TextoDeFiltro::de($request, 'tipo'));

        $documentos = Documento::query()
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
            'filtros' => ['estado' => $estado?->value, 'sujeto_tipo' => $sujetoTipo?->value, 'tipo' => $tipo?->value],
            'filtroEstadoExplicito' => $request->has('estado'),
            'estadosFiltro' => EstadoDocumento::cases(),
            'sujetosFiltro' => TipoSujeto::cases(),
            'tiposFiltro' => TipoDocumento::cases(),
            'puedeRevisar' => $this->autorizacion->tienePermiso($request, self::PERMISO_REVISAR),
        ]);
    }

    public function archivo(Request $request, Documento $documento): StreamedResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $disco = Storage::disk((string) config('proveedores.disco_documentos', 'local'));

        abort_unless($disco->exists($documento->archivo_ref), 404, __('proveedores.errores.archivo_no_disponible'));

        return $disco->response($documento->archivo_ref, $documento->nombre_original, [
            'Content-Type' => $documento->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
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
}
