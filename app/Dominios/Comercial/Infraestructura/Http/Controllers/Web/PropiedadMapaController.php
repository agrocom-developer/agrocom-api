<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Controllers\Web;

use App\Dominios\Comercial\Aplicacion\ActualizarUbicacionMapaPropiedad;
use App\Dominios\Comercial\Aplicacion\ResolverCentroReferenciaPropiedad;
use App\Dominios\Comercial\Aplicacion\ResolverProveedorMapa;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Comercial\Infraestructura\Http\Requests\GuardarUbicacionMapaPropiedadRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET/POST /panel/propiedades/{propiedad}/mapa` — punto de referencia
 * (latitud/longitud) y perímetro (`geometria`, GeoJSON `MultiPolygon`) de la
 * propiedad, en pantalla propia (adenda 16/9/2026 a ADR 0018 punto 1 / ADR
 * 0020: "el editor de mapa multi-polígono se construye en un feature
 * aparte"). Se entra desde el summary "Coordenadas del mapa" del formulario
 * de la propiedad — reusa `comercial.propiedad.editar`, no es un ABM propio,
 * mismo criterio que `SiembraController`.
 */
final class PropiedadMapaController
{
    private const PERMISO = 'comercial.propiedad.editar';

    public function __construct(
        private readonly AutorizacionPanelWeb $autorizacion,
        private readonly ResolverProveedorMapa $resolverProveedorMapa,
        private readonly ResolverCentroReferenciaPropiedad $resolverCentroReferencia,
    ) {}

    public function mostrar(Request $request, Propiedad $propiedad): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        return view('comercial::pages.propiedades.mapa', [
            ...$this->autorizacion->cascara($request),
            'propiedad' => $propiedad,
            'proveedorMapa' => $this->resolverProveedorMapa->ejecutar(),
            'centroDefecto' => $this->resolverCentroReferencia->ejecutar($propiedad),
        ]);
    }

    public function guardar(
        GuardarUbicacionMapaPropiedadRequest $request,
        Propiedad $propiedad,
        ActualizarUbicacionMapaPropiedad $actualizarUbicacionMapaPropiedad,
    ): RedirectResponse {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        $datos = $request->validated();

        $actualizarUbicacionMapaPropiedad->ejecutar(
            $propiedad,
            $this->cadenaONull($datos['latitud'] ?? null),
            $this->cadenaONull($datos['longitud'] ?? null),
            $this->decodificarGeometria($datos['geometria'] ?? null),
        );

        return redirect()
            ->route('panel.propiedades.mapa', $propiedad)
            ->with('estado', __('comercial.propiedades.mapa_guardado'));
    }

    /**
     * El Form Request ya validó que, si viene, es JSON bien formado con la
     * forma mínima de un GeoJSON `MultiPolygon` — acá solo se decodifica, no
     * se revalida.
     *
     * @return array<string, mixed>|null
     */
    private function decodificarGeometria(mixed $valor): ?array
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        /** @var array<string, mixed> $decodificado */
        $decodificado = json_decode((string) $valor, true);

        return $decodificado;
    }

    private function cadenaONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }
}
