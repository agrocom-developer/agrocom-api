<?php

namespace App\Dominios\Personal\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Personal\Dominio\RolOperativoPersona;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persona operativa de campo (espec §4.2, tabla per_personas), alcance
 * mínimo para HU-01 (ADR 0011, extensión 26/8/2026, punto 6): id, nombre,
 * rol, base_id, activo.
 *
 * Sin datos de pago: el marketplace no lleva planilla ni condición de pago al
 * personal (ADR 0029, punto 4).
 *
 * `PerBase` es del mismo módulo (Personal), así que el `belongsTo` es
 * legítimo — lo que está prohibido es cruzar hacia modelos Eloquent de
 * OTRO módulo (p. ej. Seguridad), nunca las relaciones intra-módulo.
 *
 * `RegistraBitacora` (HU-26, tarea 37): mismo criterio que {@see PerBase} —
 * el alta, edición y baja de una persona es una mutación de negocio con
 * autor y momento auditables.
 *
 * Datos personales y de referencia (21/9/2026): `nombres`,
 * `apellido_paterno`, `apellido_materno`, `ci`, `celular`, `correo` y
 * `direccion`. `nombre` sigue siendo el nombre completo que lee todo el
 * resto del sistema; lo compone `Aplicacion/DatosPersona`, no se escribe a
 * mano. Las personas anteriores a esa fecha tienen el nombre entero en
 * `nombres` y lo demás vacío, hasta que se las edite.
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $nombres
 * @property string|null $apellido_paterno
 * @property string|null $apellido_materno
 * @property string|null $ci
 * @property string|null $celular
 * @property string|null $correo
 * @property string|null $direccion
 * @property RolOperativoPersona $rol
 * @property int|null $base_id
 * @property bool $activo
 */
class PerPersona extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'per_personas';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'nombres',
        'apellido_paterno',
        'apellido_materno',
        'ci',
        'celular',
        'correo',
        'direccion',
        'rol',
        'base_id',
        'activo',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'rol' => RolOperativoPersona::class,
            'activo' => 'boolean',
        ];
    }

    /** @return BelongsTo<PerBase, $this> */
    public function base(): BelongsTo
    {
        return $this->belongsTo(PerBase::class, 'base_id');
    }
}
