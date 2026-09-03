<?php

namespace App\Dominios\Finanzas\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rubro de gasto de campaña (espec §4.4, línea 143; HU-33, tarea 47): catálogo
 * sembrado por `Database\Seeders\Catalogo\FinanzasRubrosSeeder` con los 8
 * rubros del presupuesto + "Indirectos". Sin `RegistraBitacora`: es catálogo
 * de plataforma, mismo criterio que `SecRole`/`SecPermission` — a diferencia
 * de `Gasto`, que sí es dinero real y sí la lleva.
 *
 * @property int $id
 * @property string $nombre
 * @property string $presupuesto_bs_ha
 */
class Rubro extends ModeloDominio
{
    protected $table = 'fin_rubros';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'presupuesto_bs_ha',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'presupuesto_bs_ha' => 'decimal:2',
        ];
    }

    /** @return HasMany<Subrubro, $this> */
    public function subrubros(): HasMany
    {
        return $this->hasMany(Subrubro::class, 'rubro_id');
    }
}
