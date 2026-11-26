<?php

namespace App\Dominios\Seguridad\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Seguridad\Dominio\TipoDocumento;
use Illuminate\Support\Carbon;

/**
 * Persona real, única por su documento (ADR 0046, punto 7): `(documento_tipo, documento_numero)`
 * normalizado. Una persona tiene a lo sumo una cuenta de cliente y una de proveedor
 * (`sec_user.persona_id` + `tipo_cuenta`), y los datos de la persona se comparten entre las dos
 * sin duplicarse. El teléfono y el correo NO son de la persona sino de cada cuenta.
 *
 * Contiene datos personales: la bitácora de auditoría registra quién la creó o cambió, y
 * ninguna pantalla ni respuesta de la API expone el número de documento (solo su tipo).
 *
 * @property int $id
 * @property TipoDocumento $documento_tipo
 * @property string $documento_numero
 * @property string $nombres
 * @property string $apellidos
 * @property Carbon|null $fecha_nacimiento
 */
class SecPersona extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'sec_personas';

    /** @var list<string> */
    protected $fillable = [
        'documento_tipo',
        'documento_numero',
        'nombres',
        'apellidos',
        'fecha_nacimiento',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'documento_tipo' => TipoDocumento::class,
            'fecha_nacimiento' => 'date:Y-m-d',
        ];
    }
}
