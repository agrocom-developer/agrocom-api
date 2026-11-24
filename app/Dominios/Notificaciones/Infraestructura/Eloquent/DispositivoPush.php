<?php

namespace App\Dominios\Notificaciones\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use Illuminate\Support\Carbon;

/**
 * Token de push de un aparato (`ntf_dispositivos`, `negociacion-contrato.md` §7).
 * Es un secreto del aparato: no se escribe en logs ni en la bitácora y solo lo
 * lee el envío de push.
 *
 * @property int $id
 * @property int $usuario_id
 * @property string $token
 * @property string $plataforma `android` o `ios`
 * @property Carbon|null $ultimo_uso_en
 */
class DispositivoPush extends ModeloDominio
{
    protected $table = 'ntf_dispositivos';

    /** @var list<string> */
    protected $fillable = ['usuario_id', 'token', 'plataforma', 'ultimo_uso_en'];

    /** @var list<string> */
    protected $hidden = ['token'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['usuario_id' => 'integer', 'ultimo_uso_en' => 'datetime'];
    }
}
