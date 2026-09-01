<?php

namespace App\Dominios\Distribucion\Aplicacion;

use App\Dominios\Distribucion\Dominio\EstadoVersionApk;
use App\Dominios\Distribucion\Infraestructura\Eloquent\VersionApk;

/**
 * La versión autorizada vigente (HU-20), o `null` si el dueño todavía no
 * autorizó ninguna. Con la invariante "una sola autorizada a la vez", hoy es
 * también la versión mínima aceptada — ver `runs/10-diseno.md` sobre por qué
 * no existe (todavía) una columna separada para eso.
 */
final class ObtenerVersionVigente
{
    public function ejecutar(): ?VersionApk
    {
        return VersionApk::query()
            ->where('estado', EstadoVersionApk::Autorizada->value)
            ->first();
    }
}
