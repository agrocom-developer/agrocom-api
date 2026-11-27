<?php

namespace App\Dominios\Negociacion\Aplicacion\FiltrosDeProveedor;

/**
 * «No quiero desecantes»: tipos de aplicación de los que no se avisa. Los códigos son los de la
 * solicitud (`herbicida`, `desecante`…); un tipo nuevo que el servidor aún no conozca simplemente no coincide.
 */
final class TiposAplicacionExcluidos extends ListaExcluida
{
    public const string CLAVE = 'tipos_aplicacion_excluidos';

    public function clave(): string
    {
        return self::CLAVE;
    }

    public function tipo(): string
    {
        return 'tipos_aplicacion';
    }

    protected function patron(): string
    {
        return '/^[a-z][a-z0-9_]{0,39}$/';
    }

    protected function datoDe(SolicitudParaAviso $solicitud): ?string
    {
        return $solicitud->tipoAplicacion;
    }
}
