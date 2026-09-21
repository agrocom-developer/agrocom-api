<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Controllers\Web;

use App\Dominios\Comercial\Aplicacion\GuardarSiembraCampania;
use App\Dominios\Comercial\Dominio\Excepciones\HectareasSembradasSuperanLote;
use App\Dominios\Comercial\Dominio\Excepciones\SiembraDuplicada;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Comercial\Infraestructura\Eloquent\LoteCampania;
use App\Dominios\Comercial\Infraestructura\Http\Requests\GuardarSiembraLoteRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * `GET/POST /panel/lotes/{lote}/siembra` (21/9/2026, pedido directo): la
 * siembra de UN lote, a la que se entra desde su ficha. Es el formulario de un
 * sector sin la elección de lotes —el lote es único— y con cliente, propiedad
 * y campaña de solo lectura. La siembra de la propiedad entera (por sectores)
 * sigue en `SiembraController`.
 *
 * Es también donde se cargan hectáreas sembradas MENORES que las del lote: un
 * sector siembra el lote entero, así que el lote a medio sembrar se corrige
 * acá.
 *
 * Guarda con el mismo caso de uso, `GuardarSiembraCampania`, pasándole UNA
 * sola fila: el caso de uso solo toca los lotes que recibe, así que la siembra
 * de los demás lotes de la propiedad no se mueve.
 *
 * Mismo permiso que la siembra de la propiedad (`comercial.propiedad.editar`):
 * es el mismo dato, visto desde el lote.
 */
final class SiembraLoteController
{
    private const PERMISO = 'comercial.propiedad.editar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function mostrar(Request $request, Lote $lote): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        $lote->load('propiedad.cliente');
        $campania = $this->campania($request->integer('campania_id') ?: null);

        $siembra = $campania !== null
            ? LoteCampania::query()->where('lote_id', $lote->id)->where('campania_id', $campania->id)->first()
            : null;

        /** @var Collection<int, LoteCampania> $siembras */
        $siembras = collect($siembra !== null ? [$siembra] : []);

        return view('comercial::pages.lotes.siembra', [
            ...$this->autorizacion->cascara($request),
            'lote' => $lote,
            'campania' => $campania,
            'siembra' => $siembra,
            'cultivosDisponibles' => SiembraController::cultivosDisponibles($siembras),
            'etapasDisponibles' => SiembraController::etapasDisponibles(),
        ]);
    }

    public function guardar(GuardarSiembraLoteRequest $request, Lote $lote, GuardarSiembraCampania $guardarSiembraCampania): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        $datos = $request->validated();
        $campaniaId = (int) $datos['campania_id'];
        $destino = ['lote' => $lote, 'campania_id' => $campaniaId];

        try {
            $guardarSiembraCampania->ejecutar($lote->propiedad, $campaniaId, [[
                'lote_id' => (int) $lote->id,
                'cultivo_id' => ($datos['cultivo_id'] ?? null) === null ? null : (int) $datos['cultivo_id'],
                'etapa_cultivo' => $datos['etapa_cultivo'] ?? null,
                'hectareas_sembradas' => ($datos['hectareas_sembradas'] ?? null) === null ? null : (string) $datos['hectareas_sembradas'],
                'fecha_siembra' => $datos['fecha_siembra'] ?? null,
                'fecha_cosecha_estimada' => $datos['fecha_cosecha_estimada'] ?? null,
            ]]);
        } catch (HectareasSembradasSuperanLote|SiembraDuplicada $excepcion) {
            return redirect()
                ->route('panel.lotes.siembra', $destino)
                ->withInput()
                ->withErrors(['hectareas_sembradas' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.lotes.siembra', $destino)
            ->with('estado', __('comercial.siembra.guardado'));
    }

    /**
     * La campaña pedida o, sin pedido, la más reciente — la misma «campaña
     * vigente» que muestra el resumen de siembra de la ficha del lote. Se lee
     * con `DB::table` (ADR 0003 regla 3): `Campania` es de otro módulo.
     *
     * @return object{id: int, codigo: string}|null
     */
    private function campania(?int $campaniaId): ?object
    {
        return DB::table('cpn_campanias')
            ->whereNull('deleted_at')
            ->when($campaniaId !== null, fn ($consulta) => $consulta->where('id', $campaniaId))
            ->orderByDesc('fecha_inicio')
            ->first(['id', 'codigo']);
    }
}
