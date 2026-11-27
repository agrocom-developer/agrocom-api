<?php

namespace App\Dominios\Seguridad\Infraestructura\Eloquent;

use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Seguridad\Contratos\TipoCuenta;
use App\Dominios\Seguridad\Dominio\EstadoDeEliminacion;
use Illuminate\Support\Carbon;

/**
 * Una solicitud de eliminar una cuenta de app (ADR 0047). Nunca guarda nombre, teléfono, correo ni documento.
 *
 * @property int $id
 * @property int $usuario_id
 * @property TipoCuenta $tipo_cuenta
 * @property string $origen `app`, `web` u `oficina`.
 * @property EstadoDeEliminacion $estado
 * @property Carbon $solicitada_en
 * @property Carbon $ejecutar_desde
 * @property Carbon|null $ejecutada_en
 * @property Carbon|null $cancelada_en
 * @property string|null $motivo_posposicion
 * @property array<string, int>|null $resumen
 */
class SecEliminacion extends ModeloDominio
{
    protected $table = 'sec_eliminaciones';

    /** @var list<string> */
    protected $fillable = ['usuario_id', 'tipo_cuenta', 'origen', 'estado', 'solicitada_en', 'ejecutar_desde', 'ejecutada_en', 'cancelada_en', 'motivo_posposicion', 'resumen'];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'tipo_cuenta' => TipoCuenta::class,
            'estado' => EstadoDeEliminacion::class,
            'solicitada_en' => 'datetime',
            'ejecutar_desde' => 'datetime',
            'ejecutada_en' => 'datetime',
            'cancelada_en' => 'datetime',
            'resumen' => 'array',
        ];
    }
}
