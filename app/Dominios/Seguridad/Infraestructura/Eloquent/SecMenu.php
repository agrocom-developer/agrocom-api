<?php

namespace App\Dominios\Seguridad\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Seguridad\Aplicacion\ObtenerMenuPorRolActivo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ítem de menú del panel (ADR 0002 punto 5; ADR 0004 "Consecuencias"): el
 * menú se arma DESDE esta tabla y `sec_permission`, nunca al revés —
 * ningún botón/ítem de navegación se protege "por rol" directamente
 * (invariante 2 del modelo de seguridad).
 *
 * Este modelo no decide visibilidad por sí solo: es catálogo puro. Ver
 * {@see ObtenerMenuPorRolActivo} para el
 * árbol ya filtrado por el ROL ACTIVO de la sesión (CLAUDE.md invariante 10
 * — nunca la unión de roles del usuario).
 *
 * @property int $id
 * @property string $label clave de traducción (ADR 0013), no texto plano
 * @property string|null $descripcion clave de traducción de la bajada del módulo (solo raíces), no texto plano
 * @property string $icono nombre de ícono Material Symbols, no un asset
 * @property string|null $ruta nombre de ruta Laravel o URL; null = agrupador visual sin link propio
 * @property int|null $padre_id
 * @property int $orden
 * @property int|null $permission_id
 */
class SecMenu extends ModeloDominio
{
    protected $table = 'sec_menu';

    /** @var list<string> */
    protected $fillable = [
        'label',
        'descripcion',
        'icono',
        'ruta',
        'padre_id',
        'orden',
        'permission_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'padre_id' => 'integer',
            'orden' => 'integer',
            'permission_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SecMenu, $this>
     */
    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'padre_id');
    }

    /**
     * Hijos directos, ordenados por `orden` (mismo criterio con el que
     * {@see ObtenerMenuPorRolActivo} arma
     * cada nivel del árbol). Self-referencing dentro del mismo módulo: no
     * aplica la restricción de relación cross-módulo de ADR 0011 (extensión
     * 26/8/2026, punto 5).
     *
     * @return HasMany<SecMenu, $this>
     */
    public function hijos(): HasMany
    {
        return $this->hasMany(self::class, 'padre_id')->orderBy('orden');
    }

    /**
     * Permiso que gobierna la visibilidad de este ítem. `null` = visible
     * para cualquier usuario autenticado del panel (ver docblock de la
     * migración). Referencia intra-módulo a `SecPermission`, permitida
     * (ADR 0011, extensión 26/8/2026, punto 5: la restricción de no cruzar
     * módulos con `belongsTo`/`hasOne` rige entre módulos distintos, no
     * dentro de `Seguridad`).
     *
     * @return BelongsTo<SecPermission, $this>
     */
    public function permiso(): BelongsTo
    {
        return $this->belongsTo(SecPermission::class, 'permission_id');
    }
}
