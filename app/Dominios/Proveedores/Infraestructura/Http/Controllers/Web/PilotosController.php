<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Proveedores\Aplicacion\EstadoDocumentalDeSujeto;
use App\Dominios\Proveedores\Dominio\TipoSujeto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\HistorialEstado;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Piloto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Proveedor;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Contratos\LecturaCuentas;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET /panel/pilotos` y `GET /panel/pilotos/{piloto}`: pilotos de todos los
 * proveedores y su ficha de revisión (ADR 0032). Gateada por
 * `proveedores.piloto.ver` contra el ROL ACTIVO.
 */
final class PilotosController
{
    use ArmaFichasDeAprobacion;

    private const PERMISO_VER = 'proveedores.piloto.ver';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, LecturaCuentas $cuentas): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $busqueda = TextoDeFiltro::de($request, 'q');
        $estado = EstadoAprobacion::tryFrom(TextoDeFiltro::de($request, 'estado'));

        $pilotos = Piloto::query()
            ->when($busqueda !== '', fn ($consulta) => $consulta->where(function ($interna) use ($busqueda): void {
                $interna->where('licencia_numero', 'like', '%'.$busqueda.'%');
                // El nombre vive en la cuenta (otro módulo): se resuelve por contrato, no con un join.
                $interna->orWhereIn('usuario_id', $this->idsDeCuentaPorNombre($busqueda));
            }))
            ->when($estado !== null, fn ($consulta) => $consulta->where('estado', $estado->value))
            ->orderByRaw("CASE estado WHEN 'pendiente' THEN 0 WHEN 'aprobado' THEN 1 WHEN 'suspendido' THEN 2 ELSE 3 END")
            ->orderBy('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('proveedores::pages.pilotos.index', [
            ...$this->autorizacion->cascara($request),
            'pilotos' => $pilotos,
            'nombres' => $cuentas->porIds($pilotos->getCollection()->pluck('usuario_id')->all()),
            'proveedores' => Proveedor::query()->whereIn('id', $pilotos->getCollection()->pluck('proveedor_id')->unique())->pluck('razon_social', 'id'),
            'filtros' => ['q' => $busqueda, 'estado' => $estado?->value],
            'estadosFiltro' => EstadoAprobacion::cases(),
        ]);
    }

    public function show(Request $request, Piloto $piloto, LecturaCuentas $cuentas, EstadoDocumentalDeSujeto $estadoDocumental): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $cascara = $this->autorizacion->cascara($request);

        $historial = HistorialEstado::query()
            ->where('sujeto_tipo', TipoSujeto::Piloto->value)
            ->where('sujeto_id', $piloto->id)
            ->orderByDesc('id')
            ->get();

        return view('proveedores::pages.pilotos.show', [
            ...$cascara,
            'piloto' => $piloto->load('proveedor'),
            'cuenta' => $cuentas->porIds([$piloto->usuario_id])[$piloto->usuario_id] ?? null,
            'documentos' => $this->documentosDe(TipoSujeto::Piloto, $piloto->id),
            'documentosFaltantes' => $estadoDocumental->faltantesDe(TipoSujeto::Piloto, $piloto->id),
            'acciones' => $this->accionesOfrecidas($request, $this->autorizacion, 'proveedores.piloto', $piloto->estado),
            'puedeRevisarDocumentos' => $this->autorizacion->tienePermiso($request, 'proveedores.documento.revisar'),
            'puedeVerDocumentos' => $this->autorizacion->tienePermiso($request, 'proveedores.documento.ver'),
            'puedeVerProveedor' => $this->autorizacion->tienePermiso($request, 'proveedores.proveedor.ver'),
            'lineaDeTiempo' => $this->lineaDeTiempo($historial, $cuentas, $cascara['zonaHoraria'] ?? null),
        ]);
    }

    /**
     * Ids de cuenta cuyo nombre contiene el texto. Se consulta `sec_user` por
     * contrato de lectura de cuentas no existe una búsqueda por nombre: se acota
     * a las cuentas que son pilotos (pocas) y se filtra en memoria.
     *
     * @return list<int>
     */
    private function idsDeCuentaPorNombre(string $busqueda): array
    {
        $cuentas = app(LecturaCuentas::class);
        $candidatas = Piloto::query()->pluck('usuario_id')->all();
        $patron = mb_strtolower($busqueda);

        return array_values(array_map(
            static fn ($cuenta): int => $cuenta->id,
            array_filter($cuentas->porIds($candidatas), static fn ($cuenta): bool => str_contains(mb_strtolower($cuenta->nombre), $patron)),
        ));
    }
}
