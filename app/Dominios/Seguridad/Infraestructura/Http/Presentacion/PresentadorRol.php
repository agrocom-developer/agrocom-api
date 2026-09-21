<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Presentacion;

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use Illuminate\Support\Facades\Lang;

/**
 * Metadata de PRESENTACIÓN de un rol para la pantalla de selección (quinta
 * vuelta, maqueta 5c): nombre legible ("Dueño", nunca el slug `dueno`),
 * ícono propio y chips con los permisos que habilita.
 *
 * No contradice ADR 0013 punto 3 ("los nombres de rol no se traducen"): esa
 * regla evita traducir el VOCABULARIO del dominio a otros idiomas; esto es
 * metadata de presentación que el catálogo `sec_role` no modela (un slug no
 * es un nombre mostrable, y los chips son un resumen comercial, no filas de
 * `sec_permission`). El slug sigue viajando intacto como `name` — y si un
 * rol nuevo aparece en el catálogo sin metadata declarada, el fallback
 * imprime el vocabulario crudo (`name`/`description` de la base) en vez de
 * romperse: la pantalla degrada, nunca miente.
 *
 * Los textos viven en `lang/es/seguridad.php` → `rol.meta.<name>`; el ícono
 * vive acá (no es copy, es presentación — mismo criterio que los nombres de
 * archivo de imagen en auth-layout).
 */
final class PresentadorRol
{
    /** @var array<string, string> Ícono Material Symbols por slug de rol. */
    private const ICONOS = [
        'dueno' => 'shield_person',
        'piloto' => 'flight_takeoff',
        'auxiliar' => 'engineering',
        'jefe_campo' => 'supervisor_account',
        'encargado_operaciones' => 'inventory_2',
        'admin_plataforma' => 'admin_panel_settings',
    ];

    /**
     * @return array{id: int, name: string, nombre: string, descripcion: ?string, permisos: list<string>, icono: string}
     */
    public static function presentar(SecRole $rol): array
    {
        $claveMeta = "seguridad.rol.meta.{$rol->name}";

        $nombre = Lang::has("{$claveMeta}.nombre") ? __("{$claveMeta}.nombre") : $rol->name;
        $descripcion = Lang::has("{$claveMeta}.descripcion") ? __("{$claveMeta}.descripcion") : $rol->description;

        $permisos = Lang::has("{$claveMeta}.permisos") ? __("{$claveMeta}.permisos") : [];

        return [
            'id' => (int) $rol->id,
            'name' => $rol->name,
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'permisos' => is_array($permisos) ? array_values($permisos) : [],
            'icono' => self::ICONOS[$rol->name] ?? 'badge',
        ];
    }

    /**
     * Nombre legible a secas (para el rol activo del header/user-menu).
     */
    public static function nombreLegible(SecRole $rol): string
    {
        $clave = "seguridad.rol.meta.{$rol->name}.nombre";

        return Lang::has($clave) ? __($clave) : $rol->name;
    }
}
