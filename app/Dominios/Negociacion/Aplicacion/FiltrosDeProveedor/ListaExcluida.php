<?php

namespace App\Dominios\Negociacion\Aplicacion\FiltrosDeProveedor;

use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;

/**
 * Base de los filtros «no acepto estos…»: una lista de códigos que, si coincide con el dato de la
 * solicitud, la excluye del aviso. Lista vacía equivale a sin filtro; un dato desconocido no excluye.
 */
abstract class ListaExcluida implements FiltroDeProveedor
{
    /** Forma válida de cada código de la lista. */
    abstract protected function patron(): string;

    /** El dato de la solicitud que se compara con la lista. */
    abstract protected function datoDe(SolicitudParaAviso $solicitud): ?string;

    public function porDefecto(): mixed
    {
        return [];
    }

    public function normalizar(mixed $valor): mixed
    {
        if ($valor === null || $valor === []) {
            return null;
        }

        if (! is_array($valor) || ! array_is_list($valor)) {
            throw NegociacionInvalida::dato('filtro_invalido', 'nuevas_solicitudes.'.$this->clave());
        }

        $codigos = [];

        foreach ($valor as $codigo) {
            if (! is_string($codigo) || preg_match($this->patron(), $codigo) !== 1) {
                throw NegociacionInvalida::dato('filtro_invalido', 'nuevas_solicitudes.'.$this->clave());
            }

            $codigos[$codigo] = $codigo;
        }

        return array_values($codigos);
    }

    public function admite(mixed $valor, SolicitudParaAviso $solicitud): bool
    {
        $dato = $this->datoDe($solicitud);

        return $dato === null || ! in_array($dato, (array) $valor, true);
    }
}
