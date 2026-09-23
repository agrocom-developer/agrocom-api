<?php

namespace App\Dominios\Finanzas\Infraestructura\Http\Controllers\Web;

use App\Dominios\Finanzas\Aplicacion\ListarDevengosPersona;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * `GET /panel/devengos*` (HU-28, tarea 40): "como piloto o auxiliar, quiero
 * ver mis devengos por período" — primer `Http/` del módulo `Finanzas`.
 *
 * Un único permiso (`finanzas.devengo.ver`) gatea ambas acciones. La
 * identidad se resuelve por PERSONA, no por rol/permiso (invariante distinta
 * de todo lo demás en el panel, que solo filtra por `tienePermiso()`): `show()`
 * hace `abort(404)` si la `persona_id` pedida no es la del usuario
 * autenticado, sin importar qué permiso tenga el actor — "lo suyo", no "lo
 * suyo y lo de quien tenga más permiso".
 *
 * Depende de {@see AutorizacionPanelWeb} (contrato de Seguridad, ADR 0003
 * regla 2), nunca de `SecUser` directo.
 */
final class DevengosController
{
    private const PERMISO_VER = 'finanzas.devengo.ver';

    /** Tarea 126: el vínculo "Relacionado" a los anticipos de la persona gatea por SU permiso. */
    private const PERMISO_VER_ANTICIPO = 'finanzas.anticipo.ver';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $personaId = $this->autorizacion->personaId($request);

        abort_if($personaId === null, 404);

        return redirect()->route('panel.devengos.show', $personaId);
    }

    /**
     * Arquetipo Detalle (tarea 126, guía §6.4): única ficha SIN objeto padre
     * — la persona autenticada. La comparación `$persona !== $personaId` de
     * abajo es la invariante de exposición entre usuarios (CLAUDE.md §Testing)
     * y NO se toca: es una comparación de enteros inline, sin una clase de
     * dominio propia que extraer — ver runs/126.md.
     */
    public function show(Request $request, int $persona, ListarDevengosPersona $listarDevengos): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $personaId = $this->autorizacion->personaId($request);

        abort_if($personaId === null || $persona !== $personaId, 404);

        $periodo = $request->string('periodo')->toString();
        $resultado = $listarDevengos->ejecutar($persona, $periodo !== '' ? $periodo : null);

        return view('finanzas::pages.devengos.show', [
            ...$this->autorizacion->cascara($request),
            'personaId' => $persona,
            'nombrePersona' => DB::table('per_personas')->where('id', $persona)->value('nombre') ?? "#{$persona}",
            'devengos' => $resultado['devengos'],
            'total' => $resultado['total'],
            'periodoFiltro' => $resultado['periodo'],
            'vinculos' => $this->vinculosDePersona($request, $persona),
        ]);
    }

    /**
     * "Vínculos" de `show()` (arquetipo Detalle): los anticipos de esta
     * misma persona, si el rol activo tiene permiso para verlos — mismo
     * criterio de gateo por permiso del módulo destino que el resto del
     * panel. Mismo módulo (Finanzas), así que es una ruta directa, sin
     * contrato de lectura cruzado.
     *
     * @return list<array{href: string, icon: string, title: string, meta: ?string, tone: string}>
     */
    private function vinculosDePersona(Request $request, int $persona): array
    {
        $vinculos = [];

        if ($this->autorizacion->tienePermiso($request, self::PERMISO_VER_ANTICIPO)) {
            $vinculos[] = [
                'href' => route('panel.anticipos.index', ['persona_id' => $persona]),
                'icon' => 'account_balance_wallet',
                'title' => __('finanzas.devengos.vinculo_anticipos'),
                'meta' => null,
                'tone' => 'primary-2',
            ];
        }

        return $vinculos;
    }
}
