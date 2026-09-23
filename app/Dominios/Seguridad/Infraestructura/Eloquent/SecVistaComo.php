<?php

namespace App\Dominios\Seguridad\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Seguridad\Aplicacion\IniciarVistaComo;
use App\Dominios\Seguridad\Aplicacion\TerminarVistaComo;
use App\Dominios\Seguridad\Dominio\MotivoFinVistaComo;
use App\Dominios\Seguridad\Dominio\TipoUsuario;
use Illuminate\Support\Carbon;

/**
 * Una vez que un administrador de la plataforma mira el panel o el portal como
 * otro usuario (tarea 140). La abre {@see IniciarVistaComo} y la cierra
 * {@see TerminarVistaComo}.
 *
 * Lleva `RegistraBitacora` por la invariante 9 de CLAUDE.md, y es justamente
 * lo que da la bitácora de esta capacidad sin que nadie tenga que acordarse de
 * llamarla: la entrada es el `creado`, la salida el `actualizado` con
 * `finalizada_at` y `motivo_fin`. El actor de cada fila de bitácora es el
 * administrador REAL — las dos operaciones corren con el guard sin sustituir
 * (ver `AplicarVistaComo`), nunca con la cuenta observada.
 *
 * Sin `state` ni `id_role`: no es un catálogo de acceso, así que la aduana de
 * `BitacoraAuditoriaTest` no la exige; el trait es voluntario.
 *
 * @property int $id
 * @property int $admin_id
 * @property int $usuario_id
 * @property TipoUsuario $tipo
 * @property int|null $rol_id
 * @property Carbon $iniciada_at
 * @property Carbon|null $finalizada_at
 * @property MotivoFinVistaComo|null $motivo_fin
 */
class SecVistaComo extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'sec_vistas_como';

    /** @var list<string> */
    protected $fillable = [
        'admin_id',
        'usuario_id',
        'tipo',
        'rol_id',
        'iniciada_at',
        'finalizada_at',
        'motivo_fin',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'admin_id' => 'integer',
            'usuario_id' => 'integer',
            'tipo' => TipoUsuario::class,
            'rol_id' => 'integer',
            'iniciada_at' => 'datetime',
            'finalizada_at' => 'datetime',
            'motivo_fin' => MotivoFinVistaComo::class,
        ];
    }
}
