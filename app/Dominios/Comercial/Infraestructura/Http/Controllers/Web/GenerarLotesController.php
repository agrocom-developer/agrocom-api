<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Controllers\Web;

use App\Dominios\Comercial\Aplicacion\CrearLotesMasivo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cultivo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Comercial\Infraestructura\Http\Requests\GenerarLotesRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * `GET/POST /panel/propiedades/{propiedad}/lotes/generar` (HU-72
 * reconstruida, 16/9/2026 — la original vivía en `CrearCampo`/
 * `CamposController`, borrados enteros al colapsar `Campo`, ADR 0020).
 * "Crear Lotes" con un solo botón: cuántos y, opcionalmente, qué cultivo
 * sembrar en qué campaña. Entra desde la ficha de la propiedad, mismo
 * molde que `SiembraController`/`PropiedadMapaController` — pantalla propia
 * sin listado, sin ABM propio.
 *
 * Reusa el permiso `comercial.lote.crear` (de Lote, no de Propiedad): esta
 * pantalla ES un alta de lotes, aunque se entre desde la ficha de la
 * propiedad — mismo criterio que la tarjeta "Lotes" del aside.
 */
final class GenerarLotesController
{
    private const PERMISO = 'comercial.lote.crear';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function mostrar(Request $request, Propiedad $propiedad): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        return view('comercial::pages.propiedades.lotes-generar', [
            ...$this->autorizacion->cascara($request),
            'propiedad' => $propiedad,
            'cultivosDisponibles' => Cultivo::query()->where('activo', true)->orderBy('nombre')->pluck('nombre', 'id'),
            'campaniasDisponibles' => $this->campaniasDisponibles(),
        ]);
    }

    public function guardar(GenerarLotesRequest $request, Propiedad $propiedad, CrearLotesMasivo $crearLotesMasivo): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        $datos = $request->validated();

        $lotes = $crearLotesMasivo->ejecutar(
            $propiedad,
            (int) $datos['cantidad'],
            $this->enteroONull($datos['cultivo_id'] ?? null),
            $this->enteroONull($datos['campania_id'] ?? null),
        );

        return redirect()
            ->route('panel.lotes.index', ['propiedad_id' => $propiedad->id])
            ->with('estado', __('comercial.propiedades.lotes_generados', ['cantidad' => count($lotes)]));
    }

    /** @return Collection<int, string> id => código, la más reciente primero. */
    private function campaniasDisponibles(): Collection
    {
        return DB::table('cpn_campanias')
            ->whereNull('deleted_at')
            ->orderByDesc('fecha_inicio')
            ->get(['id', 'codigo'])
            ->mapWithKeys(fn (object $fila): array => [(int) $fila->id => $fila->codigo]);
    }

    private function enteroONull(mixed $valor): ?int
    {
        return $valor === null || $valor === '' ? null : (int) $valor;
    }
}
