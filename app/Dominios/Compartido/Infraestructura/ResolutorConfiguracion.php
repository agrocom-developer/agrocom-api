<?php

namespace App\Dominios\Compartido\Infraestructura;

use App\Dominios\Compartido\Contratos\LecturaConfiguracion;
use App\Dominios\Compartido\Infraestructura\Eloquent\Configuracion;

/**
 * Implementación de {@see LecturaConfiguracion} (tarea 78, HU-55): la base
 * manda si tiene una fila viva con valor para esa clave, `config('configuracion.claves')`
 * (que a su vez apunta a `.env` vía otros archivos de `config/`) es el
 * respaldo. Vía Eloquent, nunca `DB::table` — es lo que hace que `valor` viaje
 * descifrado por el cast `encrypted` de {@see Configuracion} antes de llegar
 * acá.
 */
final class ResolutorConfiguracion implements LecturaConfiguracion
{
    public function valor(string $clave): ?string
    {
        $configuracion = Configuracion::query()->where('clave', $clave)->first();

        if ($configuracion !== null && $configuracion->valor !== null) {
            return $configuracion->valor;
        }

        return $this->respaldo($clave);
    }

    /**
     * `config('configuracion.claves')` primero, como ARRAY (una sola llamada
     * a `config()`, sin más puntos que resolver) y RECIÉN DESPUÉS se busca
     * `$clave` como llave literal dentro de ese array — nunca
     * `config("configuracion.claves.{$clave}.respaldo")` armando la ruta
     * completa: `$clave` (p. ej. `mapas.google_maps_api_key`) ya trae puntos
     * propios, y `config()` los interpretaría como más niveles anidados en
     * vez de una única llave del array plano que declara `config/configuracion.php`.
     */
    private function respaldo(string $clave): ?string
    {
        $ruta = config('configuracion.claves')[$clave]['respaldo'] ?? null;

        if ($ruta === null) {
            return null;
        }

        $valor = config($ruta);

        return $valor === null ? null : (string) $valor;
    }
}
