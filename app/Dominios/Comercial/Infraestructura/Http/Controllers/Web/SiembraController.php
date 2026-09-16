<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Controllers\Web;

use App\Dominios\Comercial\Aplicacion\GuardarSiembraCampania;
use App\Dominios\Comercial\Dominio\Excepciones\HectareasSembradasSuperanLote;
use App\Dominios\Comercial\Dominio\Excepciones\SiembraDuplicada;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cultivo;
use App\Dominios\Comercial\Infraestructura\Eloquent\LoteCampania;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Comercial\Infraestructura\Http\Requests\GuardarSiembraRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * `GET/POST /panel/propiedades/{propiedad}/siembra` (HU-48, tarea 71, etapa 3): qué
 * se sembró en cada lote de la propiedad, por campaña — se entra desde la ficha
 * de la propiedad, no tiene listado propio. Reusa el permiso
 * `comercial.propiedad.editar`: no es un ABM nuevo, es parte de mantener los
 * datos de ESA propiedad (mismo criterio que los lotes dentro de
 * `LotesController`, que tienen su propia ficha).
 *
 * `campaniasDisponibles()` lee `cpn_campanias` con `DB::table` directo (ADR
 * 0003 regla 3, mismo criterio que `ContratosController`), sin importar el
 * modelo Eloquent `Campania` de otro módulo — solo lo necesario para
 * alimentar el selector. Sin filtro por cliente (ADR 0015, corregido el
 * 15/9/2026): la campaña es un catálogo compartido.
 */
final class SiembraController
{
    private const PERMISO = 'comercial.propiedad.editar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function mostrar(Request $request, Propiedad $propiedad): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        $propiedad->load('lotes');
        $campanias = $this->campaniasDisponibles();
        $campaniaId = $request->integer('campania_id') ?: $campanias->keys()->first();

        $siembraPorLote = $campaniaId !== null
            ? LoteCampania::query()
                ->where('campania_id', $campaniaId)
                ->whereIn('lote_id', $propiedad->lotes->pluck('id'))
                ->get()
                ->keyBy('lote_id')
            : new Collection;

        return view('comercial::pages.propiedades.siembra', [
            ...$this->autorizacion->cascara($request),
            'propiedad' => $propiedad,
            'campanias' => $campanias,
            'campaniaId' => $campaniaId,
            'siembraPorLote' => $siembraPorLote,
            'cultivosDisponibles' => $this->cultivosDisponibles($siembraPorLote),
        ]);
    }

    public function guardar(GuardarSiembraRequest $request, Propiedad $propiedad, GuardarSiembraCampania $guardarSiembraCampania): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        $datos = $request->validated();
        $campaniaId = (int) $datos['campania_id'];

        /** @var list<array<string, mixed>> $lotesCrudos */
        $lotesCrudos = $datos['lotes'];

        try {
            $guardarSiembraCampania->ejecutar($propiedad, $campaniaId, array_map($this->normalizarFila(...), $lotesCrudos));
        } catch (HectareasSembradasSuperanLote|SiembraDuplicada $excepcion) {
            return redirect()
                ->route('panel.propiedades.siembra', ['propiedad' => $propiedad, 'campania_id' => $campaniaId])
                ->withInput()
                ->withErrors(['lotes' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.propiedades.siembra', ['propiedad' => $propiedad, 'campania_id' => $campaniaId])
            ->with('estado', __('comercial.siembra.guardado'));
    }

    /** @return Collection<int, string> id => código, todas las campañas del catálogo, la más reciente primero. */
    private function campaniasDisponibles(): Collection
    {
        return DB::table('cpn_campanias')
            ->whereNull('deleted_at')
            ->orderByDesc('fecha_inicio')
            ->get(['id', 'codigo'])
            ->mapWithKeys(fn (object $fila): array => [(int) $fila->id => $fila->codigo]);
    }

    /**
     * Cultivos activos, más los que ya estén cargados en la campaña que se
     * está mostrando aunque se hayan dado de baja después: una siembra ya
     * cargada con un cultivo hoy inactivo tiene que poder seguir viéndose
     * en su propio select.
     *
     * @param  Collection<int, LoteCampania>  $siembraPorLote
     * @return Collection<int, string>
     */
    private function cultivosDisponibles(Collection $siembraPorLote): Collection
    {
        $activos = Cultivo::query()->where('activo', true)->orderBy('nombre_comun')->pluck('nombre_comun', 'id');
        $usados = Cultivo::query()->whereIn('id', $siembraPorLote->pluck('cultivo_id'))->pluck('nombre_comun', 'id');

        return $activos->union($usados)->sort();
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array{lote_id: int, cultivo_id: int|null, hectareas_sembradas: string|null, fecha_siembra: string|null, fecha_cosecha_estimada: string|null}
     */
    private function normalizarFila(array $fila): array
    {
        return [
            'lote_id' => (int) $fila['lote_id'],
            'cultivo_id' => $this->enteroONull($fila['cultivo_id'] ?? null),
            'hectareas_sembradas' => $this->cadenaONull($fila['hectareas_sembradas'] ?? null),
            'fecha_siembra' => $this->cadenaONull($fila['fecha_siembra'] ?? null),
            'fecha_cosecha_estimada' => $this->cadenaONull($fila['fecha_cosecha_estimada'] ?? null),
        ];
    }

    private function enteroONull(mixed $valor): ?int
    {
        return $valor === null || $valor === '' ? null : (int) $valor;
    }

    private function cadenaONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }
}
