<?php

namespace App\Dominios\Proveedores\Contratos;

/** Estado documental de un sujeto, para quien lo aprueba desde otro módulo (el dron, en `Flota`). */
interface VerificacionDocumental
{
    /**
     * Documentos obligatorios que no tienen uno aprobado y vigente hoy.
     *
     * @return list<string> valores de `TipoDocumento`
     */
    public function faltantes(string $sujetoTipo, int $sujetoId): array;
}
