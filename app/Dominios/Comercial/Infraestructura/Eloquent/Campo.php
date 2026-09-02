<?php

namespace App\Dominios\Comercial\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Campo (propiedad) del cliente (espec §4.1, tabla com_campos).
 *
 * `RegistraBitacora` (HU-24, tarea 35): mismo criterio que {@see Cliente} —
 * el esquema no lo marca como catálogo de rol/permiso
 * (`tests/Unit/BitacoraAuditoriaTest.php` no lo exige), pero el alta,
 * edición y baja de un campo es una mutación de negocio con autor y momento
 * auditables, y el criterio de aceptación de esta HU lo pide explícito.
 *
 * @property int $id
 * @property int $cliente_id
 * @property string $nombre
 * @property string|null $ubicacion
 */
class Campo extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'com_campos';

    /** @var list<string> */
    protected $fillable = [
        'cliente_id',
        'nombre',
        'ubicacion',
    ];

    /** @return BelongsTo<Cliente, $this> */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /** @return HasMany<Lote, $this> */
    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class, 'campo_id');
    }
}
