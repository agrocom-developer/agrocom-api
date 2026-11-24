<?php

namespace App\Dominios\Asignacion\Aplicacion;

use App\Dominios\Asignacion\Infraestructura\Eloquent\Sancion;
use App\Dominios\Proveedores\Contratos\DatosProveedor;

/**
 * Lo que el proveedor ve de su reputación (`GET /reputacion`, especificación
 * §10.2): su calificación, sus tasas y las sanciones vigentes con motivo y vía
 * de apelación. La calificación la escribe otro módulo; acá solo se lee.
 */
final class ReputacionDelProveedor
{
    public function __construct(private readonly ProveedorDeLaCuenta $cuentas) {}

    /** @return array{proveedor: DatosProveedor, sanciones: list<Sancion>} */
    public function ejecutar(int $usuarioId): array
    {
        $proveedor = $this->cuentas->ejecutar($usuarioId);

        $sanciones = Sancion::query()
            ->where('proveedor_id', $proveedor->id)
            ->whereNull('levantada_en')
            ->where(fn ($consulta) => $consulta->whereNull('hasta')->orWhere('hasta', '>', now()))
            ->orderByDesc('id')
            ->get()
            ->all();

        return ['proveedor' => $proveedor, 'sanciones' => $sanciones];
    }
}
