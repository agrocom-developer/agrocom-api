<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Controllers\Web;

use App\Dominios\Comercial\Aplicacion\GuardarSiembraCampania;
use App\Dominios\Comercial\Dominio\Excepciones\CampaniaDeOtroCliente;
use App\Dominios\Comercial\Dominio\Excepciones\HectareasSembradasSuperanLote;
use App\Dominios\Comercial\Dominio\Excepciones\SiembraDuplicada;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cultivo;
use App\Dominios\Comercial\Infraestructura\Eloquent\LoteCampania;
use App\Dominios\Comercial\Infraestructura\Http\Requests\GuardarSiembraRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * `GET/POST /panel/campos/{campo}/siembra` (HU-48, tarea 71, etapa 3): qué
 * se sembró en cada lote del campo, por campaña — se entra desde la ficha
 * del campo, no tiene listado propio. Reusa el permiso
 * `comercial.campo.editar`: no es un ABM nuevo, es parte de mantener los
 * datos de ESE campo (mismo criterio que los lotes dentro de
 * `CamposController` antes de que tuvieran su propia ficha).
 *
 * `campaniasDelCliente()` lee `cpn_campanias` con `DB::table` directo (ADR
 * 0003 regla 3, mismo criterio que `ContratosController`), sin importar el
 * modelo Eloquent `Campania` de otro módulo — solo lo necesario para
 * alimentar el selector.
 */
final class SiembraController
{
    private const PERMISO = 'comercial.campo.editar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function mostrar(Request $request, Campo $campo): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        $campo->load('lotes', 'propiedad');
        $campanias = $this->campaniasDelCliente($campo->propiedad->cliente_id);
        $campaniaId = $request->integer('campania_id') ?: $campanias->keys()->first();

        $siembraPorLote = $campaniaId !== null
            ? LoteCampania::query()
                ->where('campania_id', $campaniaId)
                ->whereIn('lote_id', $campo->lotes->pluck('id'))
                ->get()
                ->keyBy('lote_id')
            : new Collection;

        return view('comercial::pages.campos.siembra', [
            ...$this->autorizacion->cascara($request),
            'campo' => $campo,
            'campanias' => $campanias,
            'campaniaId' => $campaniaId,
            'siembraPorLote' => $siembraPorLote,
            'cultivosDisponibles' => $this->cultivosDisponibles($siembraPorLote),
        ]);
    }

    public function guardar(GuardarSiembraRequest $request, Campo $campo, GuardarSiembraCampania $guardarSiembraCampania): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        $datos = $request->validated();
        $campaniaId = (int) $datos['campania_id'];

        /** @var list<array<string, mixed>> $lotesCrudos */
        $lotesCrudos = $datos['lotes'];

        try {
            $guardarSiembraCampania->ejecutar($campo, $campaniaId, array_map($this->normalizarFila(...), $lotesCrudos));
        } catch (CampaniaDeOtroCliente $excepcion) {
            return redirect()
                ->route('panel.campos.siembra', ['campo' => $campo, 'campania_id' => $campaniaId])
                ->withInput()
                ->withErrors(['campania_id' => $excepcion->getMessage()]);
        } catch (HectareasSembradasSuperanLote|SiembraDuplicada $excepcion) {
            return redirect()
                ->route('panel.campos.siembra', ['campo' => $campo, 'campania_id' => $campaniaId])
                ->withInput()
                ->withErrors(['lotes' => $excepcion->getMessage()]);
        }

        return redirect()
            ->route('panel.campos.siembra', ['campo' => $campo, 'campania_id' => $campaniaId])
            ->with('estado', __('comercial.siembra.guardado'));
    }

    /** @return Collection<int, string> id => código, campañas del cliente dueño del campo, la más reciente primero. */
    private function campaniasDelCliente(int $clienteId): Collection
    {
        return DB::table('cpn_campanias')
            ->where('cliente_id', $clienteId)
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
        $activos = Cultivo::query()->where('activo', true)->orderBy('nombre')->pluck('nombre', 'id');
        $usados = Cultivo::query()->whereIn('id', $siembraPorLote->pluck('cultivo_id'))->pluck('nombre', 'id');

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
