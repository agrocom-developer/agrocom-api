<?php

namespace App\Dominios\Seguridad\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Seguridad\Contratos\CanalDeCodigo;
use App\Dominios\Seguridad\Contratos\PropositoDeCodigo;
use Illuminate\Support\Carbon;

/**
 * Un código de un solo uso (ADR 0046, punto 27). Solo guarda el hash y el destino enmascarado; nunca el código ni el
 * teléfono o correo completos. Es una credencial efímera, sin valores en la bitácora.
 *
 * @property int $id
 * @property int $usuario_id
 * @property PropositoDeCodigo $proposito
 * @property CanalDeCodigo $canal
 * @property string $destino_enmascarado
 * @property string $codigo_hash
 * @property int $intentos
 * @property Carbon $vence_en
 * @property Carbon|null $usado_en
 */
class SecCodigoVerificacion extends ModeloDominio
{
    protected $table = 'sec_codigos_verificacion';

    /** @var list<string> */
    protected $fillable = ['usuario_id', 'proposito', 'canal', 'destino_enmascarado', 'codigo_hash', 'intentos', 'vence_en', 'usado_en'];

    /** @var list<string> */
    protected $hidden = ['codigo_hash'];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'proposito' => PropositoDeCodigo::class,
            'canal' => CanalDeCodigo::class,
            'intentos' => 'integer',
            'vence_en' => 'datetime',
            'usado_en' => 'datetime',
        ];
    }
}
