<?php

namespace App\Dominios\Solicitudes\Aplicacion;

use App\Dominios\Solicitudes\Infraestructura\Eloquent\PrecioReferencia;
use App\Dominios\Solicitudes\Infraestructura\Eloquent\Zona;

/**
 * El precio por hectárea de referencia para un tipo de aplicación (`GET /referencias/precio`):
 * una guía que ayuda al cliente a proponer y al piloto a ofertar, no un límite. Se busca de lo
 * más específico a lo más general: la zona, su macrorregión, el precio general del tipo y, si
 * el administrador no cargó ninguno, el valor de respaldo de `config/negociacion.php`.
 */
final class PrecioDeReferencia
{
    public const string NIVEL_ZONA = 'zona';

    public const string NIVEL_MACRORREGION = 'macrorregion';

    public const string NIVEL_GENERAL = 'general';

    public const string NIVEL_RESPALDO = 'respaldo';

    /**
     * @return array{tipo_aplicacion: string, zona_id: int|null, macrorregion: string|null, precio_ha: string|null, nivel: string|null}
     */
    public function para(string $tipoAplicacion, ?int $zonaId = null, ?string $macrorregion = null): array
    {
        $zona = $zonaId === null ? null : Zona::query()->find($zonaId);
        $macrorregion ??= $zona?->macrorregion;

        $encontrado = null;
        $nivel = null;

        if ($zona !== null) {
            $encontrado = PrecioReferencia::query()->where('tipo_aplicacion', $tipoAplicacion)->where('zona_id', $zona->id)->value('precio_ha');
            $nivel = self::NIVEL_ZONA;
        }

        if ($encontrado === null && $macrorregion !== null) {
            $encontrado = PrecioReferencia::query()->where('tipo_aplicacion', $tipoAplicacion)->whereNull('zona_id')->where('macrorregion', $macrorregion)->value('precio_ha');
            $nivel = self::NIVEL_MACRORREGION;
        }

        if ($encontrado === null) {
            $encontrado = PrecioReferencia::query()->where('tipo_aplicacion', $tipoAplicacion)->whereNull('zona_id')->whereNull('macrorregion')->value('precio_ha');
            $nivel = self::NIVEL_GENERAL;
        }

        if ($encontrado === null) {
            $encontrado = config("negociacion.precio_referencia_ha.{$tipoAplicacion}");
            $nivel = self::NIVEL_RESPALDO;
        }

        return [
            'tipo_aplicacion' => $tipoAplicacion,
            'zona_id' => $zona?->id,
            'macrorregion' => $macrorregion,
            'precio_ha' => $encontrado === null ? null : (string) $encontrado,
            'nivel' => $encontrado === null ? null : $nivel,
        ];
    }
}
