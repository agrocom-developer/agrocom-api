<?php

namespace App\Dominios\Proveedores\Infraestructura;

use App\Dominios\Proveedores\Contratos\DatosAuxiliar;
use App\Dominios\Proveedores\Contratos\DatosPiloto;
use App\Dominios\Proveedores\Contratos\DatosProveedor;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Proveedores\Dominio\TipoProveedor;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Auxiliar;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Piloto;
use App\Dominios\Proveedores\Infraestructura\Eloquent\Proveedor;
use App\Dominios\Seguridad\Contratos\LecturaCuentas;

/** Implementación del contrato de lectura de proveedores para `Flota` y la asignación. */
final class LecturaProveedoresEloquent implements LecturaProveedores
{
    public function __construct(private readonly LecturaCuentas $cuentas) {}

    public function proveedor(int $id): ?DatosProveedor
    {
        $proveedor = Proveedor::query()->find($id);

        return $proveedor === null ? null : $this->datos($proveedor);
    }

    public function proveedorDelTitular(int $usuarioId): ?DatosProveedor
    {
        $proveedor = Proveedor::query()->where('usuario_titular_id', $usuarioId)->first();

        return $proveedor === null ? null : $this->datos($proveedor);
    }

    public function proveedorPropio(): ?DatosProveedor
    {
        $proveedor = Proveedor::query()->where('tipo', TipoProveedor::Propio->value)->first();

        return $proveedor === null ? null : $this->datos($proveedor);
    }

    public function piloto(int $id): ?DatosPiloto
    {
        $piloto = Piloto::query()->find($id);

        return $piloto === null ? null : $this->datosPiloto($piloto, $this->nombres([$piloto->usuario_id]));
    }

    public function auxiliar(int $id): ?DatosAuxiliar
    {
        $auxiliar = Auxiliar::query()->find($id);

        if ($auxiliar === null) {
            return null;
        }

        $nombres = $this->nombres([$auxiliar->usuario_id]);

        return new DatosAuxiliar($auxiliar->id, $auxiliar->proveedor_id, $auxiliar->usuario_id, $nombres[$auxiliar->usuario_id] ?? "#{$auxiliar->usuario_id}", $auxiliar->estado->value);
    }

    public function pilotosDe(int $proveedorId): array
    {
        $pilotos = Piloto::query()->where('proveedor_id', $proveedorId)->orderBy('id')->get();
        $nombres = $this->nombres($pilotos->pluck('usuario_id')->all());

        return $pilotos->map(fn (Piloto $piloto): DatosPiloto => $this->datosPiloto($piloto, $nombres))->all();
    }

    private function datos(Proveedor $proveedor): DatosProveedor
    {
        return new DatosProveedor($proveedor->id, $proveedor->usuario_titular_id, $proveedor->tipo->value, $proveedor->razon_social, $proveedor->estado->value);
    }

    /** @param  array<int, string>  $nombres */
    private function datosPiloto(Piloto $piloto, array $nombres): DatosPiloto
    {
        return new DatosPiloto($piloto->id, $piloto->proveedor_id, $piloto->usuario_id, $nombres[$piloto->usuario_id] ?? "#{$piloto->usuario_id}", $piloto->estado->value);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function nombres(array $ids): array
    {
        return array_map(static fn ($cuenta): string => $cuenta->nombre, $this->cuentas->porIds($ids));
    }
}
