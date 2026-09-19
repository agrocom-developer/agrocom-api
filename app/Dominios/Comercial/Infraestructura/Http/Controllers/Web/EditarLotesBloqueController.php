<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Controllers\Web;

use App\Dominios\Comercial\Aplicacion\EditarLotesEnBloque;
use App\Dominios\Comercial\Aplicacion\Lote\ResultadoEdicionEnBloque;
use App\Dominios\Comercial\Aplicacion\ResumirLotesEnBloque;
use App\Dominios\Comercial\Dominio\Excepciones\LoteConHistorialAsociado;
use App\Dominios\Comercial\Dominio\Excepciones\LotesNuevosSinHectareas;
use App\Dominios\Comercial\Infraestructura\Eloquent\Propiedad;
use App\Dominios\Comercial\Infraestructura\Http\Requests\EditarLotesBloqueRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET/PUT /panel/propiedades/{propiedad}/lotes/bloque` (19/9/2026, pedido
 * directo): "Editar en bloque" — corregir de una vez las hectáreas y el
 * terreno de TODOS los lotes de la propiedad, y sumar o quitar lotes
 * cambiando la cantidad. Es la pantalla hermana de `GenerarLotesController`
 * (mismo formulario, ver `_lotes-bloque-formulario.blade.php`); se entra
 * desde el resumen "Lotes" de la ficha de la propiedad cuando ya tiene lotes.
 *
 * Pantalla propia, sin listado ni ABM: reusa los permisos de Lote. Editar
 * pide `comercial.lote.editar`; subir la cantidad pide además
 * `comercial.lote.crear` y bajarla `comercial.lote.eliminar` — cada cosa que
 * el formulario puede hacer respeta el permiso de esa cosa, aunque se entre
 * por la misma pantalla.
 */
final class EditarLotesBloqueController
{
    private const PERMISO_EDITAR = 'comercial.lote.editar';

    private const PERMISO_CREAR = 'comercial.lote.crear';

    private const PERMISO_ELIMINAR = 'comercial.lote.eliminar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function mostrar(Request $request, Propiedad $propiedad, ResumirLotesEnBloque $resumirLotes): View|RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $resumen = $resumirLotes->ejecutar($propiedad);

        // Sin lotes no hay nada que editar en bloque: lo que corresponde es generarlos.
        if ($resumen->total() === 0) {
            return redirect()->route('panel.propiedades.lotes.generar', $propiedad);
        }

        return view('comercial::pages.propiedades.lotes-editar-bloque', [
            ...$this->autorizacion->cascara($request),
            'propiedad' => $propiedad->load('cliente'),
            'resumen' => $resumen,
            'puedeCrear' => $this->autorizacion->tienePermiso($request, self::PERMISO_CREAR),
            'puedeEliminar' => $this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR),
        ]);
    }

    public function guardar(EditarLotesBloqueRequest $request, Propiedad $propiedad, EditarLotesEnBloque $editarLotesEnBloque): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);

        $datos = $request->validated();
        $cantidad = (int) $datos['cantidad'];
        $actuales = $propiedad->lotes()->count();

        if ($cantidad > $actuales && ! $this->autorizacion->tienePermiso($request, self::PERMISO_CREAR)) {
            return $this->volverConError($propiedad, 'cantidad', __('comercial.propiedades.lotes_bloque_sin_permiso_crear'));
        }

        if ($cantidad < $actuales && ! $this->autorizacion->tienePermiso($request, self::PERMISO_ELIMINAR)) {
            return $this->volverConError($propiedad, 'cantidad', __('comercial.propiedades.lotes_bloque_sin_permiso_eliminar'));
        }

        try {
            $resultado = $editarLotesEnBloque->ejecutar(
                $propiedad,
                (string) ($datos['prefijo'] ?? ''),
                $cantidad,
                ($datos['hectareas'] ?? '') === '' ? null : (string) $datos['hectareas'],
                $request->atributosTerreno(),
            );
        } catch (LotesNuevosSinHectareas $excepcion) {
            return $this->volverConError($propiedad, 'hectareas', $excepcion->getMessage());
        } catch (LoteConHistorialAsociado $excepcion) {
            return $this->volverConError($propiedad, 'cantidad', $excepcion->getMessage());
        }

        // Se queda en la propia pantalla (mismo criterio que la ficha de la
        // propiedad, 16/9/2026): ya ve los valores nuevos y puede seguir.
        return redirect()
            ->route('panel.propiedades.lotes.bloque', $propiedad)
            ->with('estado', $this->mensaje($resultado));
    }

    private function volverConError(Propiedad $propiedad, string $campo, string $mensaje): RedirectResponse
    {
        return redirect()
            ->route('panel.propiedades.lotes.bloque', $propiedad)
            ->withInput()
            ->withErrors([$campo => $mensaje]);
    }

    private function mensaje(ResultadoEdicionEnBloque $resultado): string
    {
        if (! $resultado->huboCambios()) {
            return __('comercial.propiedades.lotes_bloque_sin_cambios');
        }

        $partes = [];

        if ($resultado->actualizados > 0) {
            $partes[] = trans_choice('comercial.propiedades.lotes_bloque_actualizados', $resultado->actualizados, ['cantidad' => $resultado->actualizados]);
        }

        if ($resultado->creados > 0) {
            $partes[] = trans_choice('comercial.propiedades.lotes_bloque_creados', $resultado->creados, ['cantidad' => $resultado->creados]);
        }

        if ($resultado->eliminados > 0) {
            $partes[] = trans_choice('comercial.propiedades.lotes_bloque_quitados', $resultado->eliminados, ['cantidad' => $resultado->eliminados]);
        }

        return implode(' ', $partes);
    }
}
