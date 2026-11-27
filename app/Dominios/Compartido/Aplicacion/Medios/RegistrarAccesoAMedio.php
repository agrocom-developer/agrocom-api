<?php

namespace App\Dominios\Compartido\Aplicacion\Medios;

use App\Dominios\Compartido\Dominio\AccionBitacora;
use App\Dominios\Compartido\Infraestructura\Eloquent\Bitacora;
use Illuminate\Support\Facades\DB;

/**
 * Deja constancia en la bitácora de quién abrió o descargó el ORIGINAL de un archivo sensible (documentos de
 * identidad y licencia): quién, cuándo y qué registro; nunca el contenido ni el nombre del archivo. La
 * miniatura y el tamaño medio de la cuadrícula no se registran, solo el acceso al original.
 */
final class RegistrarAccesoAMedio
{
    /** @param  string  $medio  `visto` (en el visor) o `descargado`. */
    public function ejecutar(?int $usuarioId, string $tabla, int $registroId, string $medio): void
    {
        Bitacora::query()->create([
            'user_id' => $usuarioId,
            'tabla' => $tabla,
            'registro_id' => $registroId,
            'accion' => AccionBitacora::Descargado,
            'antes' => null,
            'despues' => ['medio' => $medio],
            'zona_horaria' => $usuarioId === null ? null : DB::table('sec_user_preferencia')->where('user_id', $usuarioId)->value('zona_horaria'),
        ]);
    }
}
