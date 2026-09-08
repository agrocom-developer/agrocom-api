<?php

namespace App\Dominios\Campania\Infraestructura;

use App\Dominios\Campania\Contratos\CampaniaActivaSesion;
use App\Dominios\Campania\Infraestructura\Eloquent\Campania;
use Illuminate\Support\Facades\Session;

/**
 * Implementación Eloquent de {@see CampaniaActivaSesion}. Vive fuera de
 * `Infraestructura/Eloquent/` a propósito, mismo criterio que
 * `Comercial\Infraestructura\LecturaContratoEloquent`: esa subcarpeta está
 * reservada a modelos que extienden `ModeloDominio` — esta clase no es un
 * modelo, es el adaptador que `CampaniaServiceProvider` liga a la interfaz.
 *
 * Confía en `session('cpn_campania_activa_id')` sin revalidar contra la base:
 * `ResolverCampaniaActiva` ya garantiza, en cada request, que ese valor (si
 * existe) apunta a una campaña no borrada — mismo criterio de confianza que
 * `AutorizacionPanelWebSesion::tienePermiso()` con el rol activo de sesión.
 */
final class CampaniaActivaSesionEloquent implements CampaniaActivaSesion
{
    private const CLAVE_SESION = 'cpn_campania_activa_id';

    public function idActivo(): ?int
    {
        $id = Session::get(self::CLAVE_SESION);

        return $id !== null ? (int) $id : null;
    }

    public function etiquetaActiva(): ?string
    {
        $id = $this->idActivo();

        if ($id === null) {
            return null;
        }

        return Campania::query()->whereKey($id)->value('codigo');
    }
}
