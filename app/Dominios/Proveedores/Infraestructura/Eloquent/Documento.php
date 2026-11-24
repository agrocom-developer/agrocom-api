<?php

namespace App\Dominios\Proveedores\Infraestructura\Eloquent;

use App\Dominios\Compartido\Dominio\TipoDocumento;
use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraBitacora;
use App\Dominios\Proveedores\Dominio\EstadoDocumento;
use App\Dominios\Proveedores\Dominio\TipoSujeto;
use Illuminate\Support\Carbon;

/**
 * Documento con vencimiento de un sujeto (`prv_documentos`). El archivo vive en
 * el disco privado bajo `archivo_ref` (ADR 0026) y solo lo ven administradores
 * y soporte (política de verificación, «Privacidad»).
 *
 * @property int $id
 * @property TipoSujeto $sujeto_tipo
 * @property int $sujeto_id
 * @property TipoDocumento $tipo
 * @property string $archivo_ref
 * @property string|null $nombre_original
 * @property string $mime
 * @property int $bytes
 * @property Carbon|null $vence_en
 * @property EstadoDocumento $estado
 * @property int $subido_por
 * @property int|null $revisado_por
 * @property Carbon|null $revisado_en
 * @property string|null $motivo_rechazo
 * @property Carbon|null $aviso_30_en
 * @property Carbon|null $aviso_7_en
 * @property Carbon $created_at
 */
class Documento extends ModeloDominio
{
    use RegistraBitacora;

    protected $table = 'prv_documentos';

    /** @var list<string> */
    protected $fillable = [
        'sujeto_tipo',
        'sujeto_id',
        'tipo',
        'archivo_ref',
        'nombre_original',
        'mime',
        'bytes',
        'vence_en',
        'estado',
        'subido_por',
        'revisado_por',
        'revisado_en',
        'motivo_rechazo',
        'aviso_30_en',
        'aviso_7_en',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sujeto_tipo' => TipoSujeto::class,
            'tipo' => TipoDocumento::class,
            'estado' => EstadoDocumento::class,
            'sujeto_id' => 'integer',
            'bytes' => 'integer',
            'vence_en' => 'date',
            'revisado_en' => 'datetime',
            'aviso_30_en' => 'date',
            'aviso_7_en' => 'date',
        ];
    }
}
