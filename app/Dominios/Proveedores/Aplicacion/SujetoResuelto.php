<?php

namespace App\Dominios\Proveedores\Aplicacion;

use App\Dominios\Proveedores\Dominio\TipoSujeto;

/**
 * Lo que hace falta saber de un sujeto para avisarle y mostrarlo: a qué
 * proveedor pertenece, cómo se llama y a qué cuentas se les avisa: la cuenta
 * propia del sujeto (si la tiene: el piloto) y el titular del
 * proveedor, que es quien gestiona a su gente y su flota.
 */
final readonly class SujetoResuelto
{
    public function __construct(
        public TipoSujeto $tipo,
        public int $id,
        public ?int $proveedorId,
        public string $nombre,
        /** @var list<int> */
        public array $usuariosAAvisar,
        public string $estado,
    ) {}
}
