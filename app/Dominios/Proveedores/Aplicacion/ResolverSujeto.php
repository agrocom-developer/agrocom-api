<?php

namespace App\Dominios\Proveedores\Aplicacion;

use App\Dominios\Flota\Contratos\LecturaDrones;
use App\Dominios\Proveedores\Dominio\TipoSujeto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Auxiliar;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Piloto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Proveedor;
use App\Dominios\Seguridad\Contratos\LecturaCuentas;

/**
 * Resuelve «de qué sujeto es este documento»: nombre, proveedor y a quién
 * avisar. Un documento apunta a su sujeto por (`sujeto_tipo`, `sujeto_id`) sin
 * FK (cuatro tablas de dos módulos); acá se deshace esa indirección. El dron se
 * pregunta por contrato a `Flota`.
 */
final class ResolverSujeto
{
    public function __construct(
        private readonly LecturaCuentas $cuentas,
        private readonly LecturaDrones $drones,
    ) {}

    public function ejecutar(TipoSujeto $tipo, int $id): ?SujetoResuelto
    {
        return match ($tipo) {
            TipoSujeto::Proveedor => $this->deProveedor($id),
            TipoSujeto::Piloto => $this->dePiloto($id),
            TipoSujeto::Auxiliar => $this->deAuxiliar($id),
            TipoSujeto::Dron => $this->deDron($id),
        };
    }

    private function deProveedor(int $id): ?SujetoResuelto
    {
        $proveedor = Proveedor::query()->find($id);

        return $proveedor === null ? null : new SujetoResuelto(
            TipoSujeto::Proveedor, $proveedor->id, $proveedor->id, $proveedor->razon_social,
            $this->cuentasUnicas([$proveedor->usuario_titular_id]), $proveedor->estado->value,
        );
    }

    private function dePiloto(int $id): ?SujetoResuelto
    {
        $piloto = Piloto::query()->with('proveedor')->find($id);

        return $piloto === null ? null : new SujetoResuelto(
            TipoSujeto::Piloto, $piloto->id, $piloto->proveedor_id,
            $this->nombreDeCuenta($piloto->usuario_id), $this->cuentasUnicas([$piloto->usuario_id, $piloto->proveedor->usuario_titular_id]),
            $piloto->estado->value,
        );
    }

    private function deAuxiliar(int $id): ?SujetoResuelto
    {
        $auxiliar = Auxiliar::query()->with('proveedor')->find($id);

        return $auxiliar === null ? null : new SujetoResuelto(
            TipoSujeto::Auxiliar, $auxiliar->id, $auxiliar->proveedor_id,
            $this->nombreDeCuenta($auxiliar->usuario_id), $this->cuentasUnicas([$auxiliar->usuario_id, $auxiliar->proveedor->usuario_titular_id]),
            $auxiliar->estado->value,
        );
    }

    private function deDron(int $id): ?SujetoResuelto
    {
        $dron = $this->drones->dron($id);

        if ($dron === null) {
            return null;
        }

        $dueno = Proveedor::query()->find($dron->duenoProveedorId);

        return new SujetoResuelto(
            TipoSujeto::Dron, $dron->id, $dron->duenoProveedorId, $dron->etiqueta(),
            $this->cuentasUnicas([$dueno?->usuario_titular_id]), $dron->estado,
        );
    }

    /**
     * @param  list<int|null>  $ids
     * @return list<int>
     */
    private function cuentasUnicas(array $ids): array
    {
        return array_values(array_unique(array_filter($ids, static fn (?int $id): bool => $id !== null)));
    }

    private function nombreDeCuenta(int $usuarioId): string
    {
        return ($this->cuentas->porIds([$usuarioId])[$usuarioId] ?? null)->nombre ?? "#{$usuarioId}";
    }
}
