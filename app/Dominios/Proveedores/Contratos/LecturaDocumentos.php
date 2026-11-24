<?php

namespace App\Dominios\Proveedores\Contratos;

/** Documentos de un sujeto para la ficha del panel, también cuando el sujeto es de otro módulo (el dron, de `Flota`). */
interface LecturaDocumentos
{
    /**
     * Del más reciente al más antiguo.
     *
     * @return list<DatosDocumento>
     */
    public function de(string $sujetoTipo, int $sujetoId): array;
}
