<?php

namespace App\Dominios\Flota\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Flota\Infraestructura\Eloquent\Equipo;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET /panel/equipos-flota`: los equipos (piloto + dron + auxiliar) de todos los
 * proveedores, solo lectura. Los equipos los arma cada proveedor desde su app;
 * el administrador los ve para entender quién puede recibir ofertas. Se llama
 * `equipos-flota` para no chocar con las cuadrillas del panel. Gateada por
 * `flota.equipo.ver` contra el ROL ACTIVO.
 */
final class EquiposController
{
    public function __construct(
        private readonly AutorizacionPanelWeb $autorizacion,
        private readonly LecturaProveedores $proveedores,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, 'flota.equipo.ver'), 403);

        $vigencia = TextoDeFiltro::de($request, 'vigencia');
        $hoy = now()->toDateString();

        $equipos = Equipo::query()
            ->with('dron')
            ->when($vigencia === 'vigentes', fn ($consulta) => $consulta
                ->whereDate('vigente_desde', '<=', $hoy)
                ->where(fn ($interna) => $interna->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $hoy)))
            ->when($vigencia === 'cerrados', fn ($consulta) => $consulta->whereDate('vigente_hasta', '<', $hoy))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $proveedores = [];
        $pilotos = [];
        $auxiliares = [];

        foreach ($equipos->getCollection() as $equipo) {
            $proveedores[$equipo->proveedor_id] ??= $this->proveedores->proveedor($equipo->proveedor_id)->razonSocial ?? "#{$equipo->proveedor_id}";
            $pilotos[$equipo->piloto_id] ??= $this->proveedores->piloto($equipo->piloto_id)->nombre ?? "#{$equipo->piloto_id}";

            if ($equipo->auxiliar_id !== null) {
                $auxiliares[$equipo->auxiliar_id] ??= $this->proveedores->auxiliar($equipo->auxiliar_id)->nombre ?? "#{$equipo->auxiliar_id}";
            }
        }

        return view('flota::pages.equipos.index', [
            ...$this->autorizacion->cascara($request),
            'equipos' => $equipos,
            'proveedores' => $proveedores,
            'pilotos' => $pilotos,
            'auxiliares' => $auxiliares,
            'hoy' => $hoy,
            'filtros' => ['vigencia' => in_array($vigencia, ['vigentes', 'cerrados'], true) ? $vigencia : null],
        ]);
    }
}
