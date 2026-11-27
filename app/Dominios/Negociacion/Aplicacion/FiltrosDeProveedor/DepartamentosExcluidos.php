<?php

namespace App\Dominios\Negociacion\Aplicacion\FiltrosDeProveedor;

/** «No acepto Pando»: departamentos (código de dos dígitos del catálogo) de los que no se avisa. */
final class DepartamentosExcluidos extends ListaExcluida
{
    public const string CLAVE = 'departamentos_excluidos';

    public function clave(): string
    {
        return self::CLAVE;
    }

    public function tipo(): string
    {
        return 'departamentos';
    }

    protected function patron(): string
    {
        return '/^\d{2}$/';
    }

    protected function datoDe(SolicitudParaAviso $solicitud): ?string
    {
        return $solicitud->departamentoCodigo;
    }
}
