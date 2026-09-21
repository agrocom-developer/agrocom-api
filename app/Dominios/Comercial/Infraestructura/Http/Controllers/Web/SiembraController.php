<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Controllers\Web;

use App\Dominios\Comercial\Aplicacion\GuardarSiembraCampania;
use App\Dominios\Comercial\Dominio\EtapaCultivo;
use App\Dominios\Comercial\Dominio\Excepciones\HectareasSembradasSuperanLote;
use App\Dominios\Comercial\Dominio\Excepciones\SiembraDuplicada;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cultivo;
use App\Dominios\Comercial\Infraestructura\Eloquent\LoteCampania;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Comercial\Infraestructura\Http\Requests\GuardarSiembraRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * `GET/POST /panel/propiedades/{propiedad}/siembra` y `GET /panel/siembra` (HU-48, tarea 71, etapa 3): qué
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
 *
 * 21/9/2026 (pedido directo): la pantalla pasa al arquetipo Formulario de la
 * guía y la siembra se carga por SECTORES — un cultivo, su etapa y sus fechas,
 * más los lotes que lo comparten—, porque una fila con cinco campos por lote
 * no sirve para una propiedad de mil lotes. El sector no es una entidad: al
 * mostrar, las siembras guardadas se agrupan por lo que tienen en común
 * (`sectoresGuardados()`); al guardar, cada sector se expande a una fila por
 * lote (`filasDeSectores()`) y el caso de uso sigue recibiendo el mismo set
 * completo de filas de siempre. Un lote que no quedó en ningún sector viaja
 * sin cultivo: su siembra se da de baja.
 *
 * Un sector siembra el lote ENTERO: las hectáreas sembradas son las del lote.
 * Una siembra ya guardada con menos hectáreas conserva su valor — el sector
 * no la pisa.
 *
 * Sin siembra guardada en la campaña la pantalla es un ALTA y va sin columna
 * lateral; con siembra es una EDICIÓN y muestra su resumen (guía §6.3.1).
 */
final class SiembraController
{
    private const PERMISO = 'comercial.propiedad.editar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    /**
     * `GET /panel/siembra` (21/9/2026, pedido directo): la misma pantalla, sin
     * propiedad elegida todavía. Es la entrada desde la ficha de un cultivo
     * («sembrar este cultivo»): ofrece todos los clientes, y las propiedades
     * se cargan según el cliente elegido. Con `propiedad_id` en la query se
     * comporta igual que la ruta de la propiedad.
     */
    public function elegir(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        $propiedad = $request->integer('propiedad_id') !== 0
            ? Propiedad::query()->find($request->integer('propiedad_id'))
            : null;

        return $this->pantalla($request, $propiedad);
    }

    public function mostrar(Request $request, Propiedad $propiedad): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        return $this->pantalla($request, $propiedad);
    }

    /**
     * Cliente y propiedad son selects, como la campaña: cambiar cualquiera de
     * los tres es un GET nuevo (lo dispara `siembra-form.js`), nunca un dato
     * que viaje con el guardado — lo que se guarda es siempre la propiedad de
     * la ruta y el `campania_id` oculto. Desde la propiedad llegan los dos ya
     * elegidos; desde un cultivo, ninguno, y `cultivo_id` deja ese cultivo
     * puesto en el sector en blanco. Sin propiedad, la sección de sectores
     * muestra su vacío (guía §6.3.5: no se esconde).
     */
    private function pantalla(Request $request, ?Propiedad $propiedad): View
    {
        $propiedad?->load(['cliente', 'lotes' => fn ($consulta) => $consulta->ordenadosPorCodigo()]);

        $clienteId = $propiedad !== null ? (int) $propiedad->cliente_id : ($request->integer('cliente_id') ?: null);
        $campanias = $this->campaniasDisponibles();
        $campaniaId = $request->integer('campania_id') ?: $campanias->keys()->first();
        $lotes = $propiedad !== null ? $propiedad->lotes : new EloquentCollection;

        $siembraPorLote = $campaniaId !== null && $lotes->isNotEmpty()
            ? LoteCampania::query()
                ->where('campania_id', $campaniaId)
                ->whereIn('lote_id', $lotes->pluck('id'))
                ->get()
                ->keyBy('lote_id')
            : new Collection;

        $cultivosDisponibles = self::cultivosDisponibles($siembraPorLote);
        $cultivoSugerido = $request->integer('cultivo_id');

        return view('comercial::pages.propiedades.siembra', [
            ...$this->autorizacion->cascara($request),
            'propiedad' => $propiedad,
            'clientes' => Cliente::query()->orderBy('razon_social')->pluck('razon_social', 'id'),
            'clienteId' => $clienteId,
            'propiedadesDelCliente' => $clienteId !== null
                ? Propiedad::query()->where('cliente_id', $clienteId)->orderBy('nombre')->pluck('nombre', 'id')
                : new Collection,
            'campanias' => $campanias,
            'campaniaId' => $campaniaId,
            'esEdicion' => $siembraPorLote->isNotEmpty(),
            'sectores' => $propiedad !== null ? $this->sectoresGuardados($propiedad, $siembraPorLote) : [],
            // Se llegó desde la ficha de un cultivo: es el que trae puesto el
            // sector en blanco. Solo si sigue siendo uno de los que se ofrecen.
            'cultivoSugeridoId' => $cultivosDisponibles->has($cultivoSugerido) ? $cultivoSugerido : null,
            // Catálogo de lotes para el selector del navegador (`siembra-form.js`):
            // en orden natural, con las hectáreas como string (invariante 6).
            'lotesCatalogo' => $lotes
                ->map(fn ($lote): array => ['id' => (int) $lote->id, 'codigo' => $lote->codigo, 'hectareas' => (string) $lote->hectareas])
                ->values()
                ->all(),
            'cultivosDisponibles' => $cultivosDisponibles,
            'resumenSiembra' => $propiedad !== null
                ? $this->resumenSiembra($propiedad, $siembraPorLote, $cultivosDisponibles)
                : ['tieneDatos' => false, 'items' => []],
            'etapasDisponibles' => self::etapasDisponibles(),
        ]);
    }

    /** @return Collection<string, string> valor => etiqueta, en el orden del ciclo. */
    public static function etapasDisponibles(): Collection
    {
        return collect(EtapaCultivo::cases())
            ->mapWithKeys(fn (EtapaCultivo $etapa): array => [$etapa->value => __("comercial.siembra.etapa_opcion.{$etapa->value}")]);
    }

    public function guardar(GuardarSiembraRequest $request, Propiedad $propiedad, GuardarSiembraCampania $guardarSiembraCampania): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        $datos = $request->validated();
        $campaniaId = (int) $datos['campania_id'];

        /** @var list<array<string, mixed>> $sectores */
        $sectores = array_values($datos['sectores'] ?? []);

        try {
            $guardarSiembraCampania->ejecutar($propiedad, $campaniaId, $this->filasDeSectores($propiedad, $campaniaId, $sectores));
        } catch (HectareasSembradasSuperanLote|SiembraDuplicada $excepcion) {
            return redirect()
                ->route('panel.propiedades.siembra', ['propiedad' => $propiedad, 'campania_id' => $campaniaId])
                ->withInput()
                ->withErrors(['sectores' => $excepcion->getMessage()]);
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
    public static function cultivosDisponibles(Collection $siembraPorLote): Collection
    {
        $activos = Cultivo::query()->where('activo', true)->orderBy('nombre_comun')->pluck('nombre_comun', 'id');
        $usados = Cultivo::query()->whereIn('id', $siembraPorLote->pluck('cultivo_id'))->pluck('nombre_comun', 'id');

        return $activos->union($usados)->sort();
    }

    /**
     * Resumen de la campaña que se está mostrando, para el aside: cuántos
     * lotes tienen siembra, cuántas hectáreas suman y el desglose por
     * cultivo. Se suma con `BigDecimal` (invariante 6) y cuenta lo GUARDADO,
     * no lo que se esté escribiendo en el formulario.
     *
     * @param  Collection<int, LoteCampania>  $siembraPorLote
     * @param  Collection<int, string>  $cultivos  id => nombre
     * @return array{tieneDatos: bool, items: list<array{label: string, value: string, mono?: bool}>}
     */
    private function resumenSiembra(Propiedad $propiedad, Collection $siembraPorLote, Collection $cultivos): array
    {
        $sumar = fn (Collection $valores): string => (string) $valores
            ->reduce(fn (BigDecimal $acumulado, mixed $valor): BigDecimal => $acumulado->plus((string) $valor), BigDecimal::zero())
            ->toScale(2);
        $formatear = fn (string $hectareas): string => __('comercial.siembra.lote_hectareas_valor', [
            'cantidad' => number_format((float) $hectareas, 2, ',', '.'),
        ]);

        $items = [
            [
                'label' => __('comercial.siembra.resumen_lotes'),
                'value' => __('comercial.siembra.resumen_lotes_valor', ['sembrados' => $siembraPorLote->count(), 'total' => $propiedad->lotes->count()]),
                'mono' => true,
            ],
            [
                'label' => __('comercial.siembra.resumen_hectareas'),
                'value' => $formatear($sumar($siembraPorLote->pluck('hectareas_sembradas'))),
                'mono' => true,
            ],
        ];

        $porCultivo = $siembraPorLote
            ->groupBy('cultivo_id')
            ->map(fn (Collection $grupo, int|string $cultivoId): array => [
                'label' => $cultivos->get((int) $cultivoId) ?? __('comercial.lotes.aside_siembra_cultivo_desconocido'),
                'value' => $formatear($sumar($grupo->pluck('hectareas_sembradas'))),
                'mono' => true,
            ])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        return [
            'tieneDatos' => $siembraPorLote->isNotEmpty(),
            'items' => [...$items, ...$porCultivo],
        ];
    }

    /**
     * Las siembras guardadas, agrupadas en sectores: van juntos los lotes que
     * comparten cultivo, etapa y fechas. El orden es el del primer lote de
     * cada sector (los lotes ya vienen en orden natural), y dentro del sector
     * los lotes quedan en ese mismo orden.
     *
     * @param  Collection<int, LoteCampania>  $siembraPorLote
     * @return list<array{cultivo_id: int, etapa_cultivo: string|null, fecha_siembra: string|null, fecha_cosecha_estimada: string|null, lotes: string}>
     */
    private function sectoresGuardados(Propiedad $propiedad, Collection $siembraPorLote): array
    {
        $sectores = [];

        foreach ($propiedad->lotes as $lote) {
            $siembra = $siembraPorLote->get($lote->id);

            if ($siembra === null) {
                continue;
            }

            $datos = [
                'cultivo_id' => (int) $siembra->cultivo_id,
                'etapa_cultivo' => $siembra->etapa_cultivo?->value,
                'fecha_siembra' => $siembra->fecha_siembra?->format('Y-m-d'),
                'fecha_cosecha_estimada' => $siembra->fecha_cosecha_estimada?->format('Y-m-d'),
            ];
            $clave = implode('|', array_map(strval(...), $datos));

            $sectores[$clave] ??= [...$datos, 'lotes' => []];
            $sectores[$clave]['lotes'][] = (int) $lote->id;
        }

        return array_values(array_map(
            fn (array $sector): array => [...$sector, 'lotes' => implode(',', $sector['lotes'])],
            $sectores,
        ));
    }

    /**
     * Expande los sectores al set completo de filas que espera
     * `GuardarSiembraCampania`: una por lote de la propiedad. El lote de un
     * sector lleva su cultivo, etapa y fechas; el que no quedó en ninguno va
     * sin cultivo (su siembra, si la tenía, se da de baja).
     *
     * Hectáreas sembradas: las del lote entero, salvo que ya tuviera una
     * siembra guardada — esa conserva las suyas.
     *
     * @param  list<array<string, mixed>>  $sectores
     * @return list<array{lote_id: int, cultivo_id: int|null, etapa_cultivo: string|null, hectareas_sembradas: string|null, fecha_siembra: string|null, fecha_cosecha_estimada: string|null}>
     */
    private function filasDeSectores(Propiedad $propiedad, int $campaniaId, array $sectores): array
    {
        $sectorPorLote = [];

        foreach ($sectores as $sector) {
            foreach (GuardarSiembraRequest::idsDeLotes($sector['lotes'] ?? '') as $loteId) {
                $sectorPorLote[$loteId] = $sector;
            }
        }

        $hectareasGuardadas = LoteCampania::query()
            ->where('campania_id', $campaniaId)
            ->whereIn('lote_id', $propiedad->lotes->pluck('id'))
            ->pluck('hectareas_sembradas', 'lote_id');

        return $propiedad->lotes
            ->map(function ($lote) use ($sectorPorLote, $hectareasGuardadas): array {
                $sector = $sectorPorLote[$lote->id] ?? null;

                return [
                    'lote_id' => (int) $lote->id,
                    'cultivo_id' => $this->enteroONull($sector['cultivo_id'] ?? null),
                    'etapa_cultivo' => $this->cadenaONull($sector['etapa_cultivo'] ?? null),
                    'hectareas_sembradas' => $sector === null ? null : (string) ($hectareasGuardadas->get($lote->id) ?? $lote->hectareas),
                    'fecha_siembra' => $this->cadenaONull($sector['fecha_siembra'] ?? null),
                    'fecha_cosecha_estimada' => $this->cadenaONull($sector['fecha_cosecha_estimada'] ?? null),
                ];
            })
            ->values()
            ->all();
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
