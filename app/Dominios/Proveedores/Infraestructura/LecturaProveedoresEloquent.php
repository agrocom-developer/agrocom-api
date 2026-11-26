<?php

namespace App\Dominios\Proveedores\Infraestructura;

use App\Dominios\Compartido\Dominio\EstadoAprobacion;
use App\Dominios\Proveedores\Contratos\DatosPiloto;
use App\Dominios\Proveedores\Contratos\DatosProveedor;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Proveedores\Dominio\Disponibilidad;
use App\Dominios\Proveedores\Dominio\TipoProveedor;
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

    public function registrosDeCuenta(int $usuarioId): array
    {
        return [
            'pilotos' => array_map('intval', Piloto::query()->where('usuario_id', $usuarioId)->pluck('id')->all()),
        ];
    }

    public function pilotosDe(int $proveedorId): array
    {
        $pilotos = Piloto::query()->where('proveedor_id', $proveedorId)->orderBy('id')->get();
        $nombres = $this->nombres($pilotos->pluck('usuario_id')->all());

        return $pilotos->map(fn (Piloto $piloto): DatosPiloto => $this->datosPiloto($piloto, $nombres))->all();
    }

    public function admitenAltas(): array
    {
        return Proveedor::query()
            ->whereIn('estado', [EstadoAprobacion::Pendiente->value, EstadoAprobacion::Aprobado->value])
            ->orderBy('razon_social')
            ->pluck('razon_social', 'id')
            ->all();
    }

    public function aprobadosDelGrupo(string $grupo): array
    {
        $proveedores = Proveedor::query()
            ->where('estado', EstadoAprobacion::Aprobado->value)
            ->when(
                $grupo === 'propio',
                fn ($consulta) => $consulta->where('tipo', TipoProveedor::Propio->value),
                fn ($consulta) => $consulta->where('tipo', '!=', TipoProveedor::Propio->value),
            )
            ->orderBy('id')
            ->get();

        return $proveedores->map(fn (Proveedor $proveedor): DatosProveedor => $this->datos($proveedor))->all();
    }

    public function usuariosDeAviso(int $proveedorId): array
    {
        $proveedor = Proveedor::query()->find($proveedorId);

        if ($proveedor === null) {
            return [];
        }

        $cuentas = Piloto::query()
            ->where('proveedor_id', $proveedorId)
            ->where('estado', EstadoAprobacion::Aprobado->value)
            ->pluck('usuario_id')
            ->all();

        if ($proveedor->usuario_titular_id !== null) {
            array_unshift($cuentas, $proveedor->usuario_titular_id);
        }

        return array_values(array_unique(array_map('intval', $cuentas)));
    }

    public function usuariosParaAvisoDeNuevasSolicitudes(int $proveedorId): array
    {
        $proveedor = Proveedor::query()->find($proveedorId);

        if ($proveedor === null || ! ($proveedor->disponibilidad ?? Disponibilidad::Disponible)->recibeAvisos()) {
            return [];
        }

        $cuentas = [];

        if ($proveedor->usuario_titular_id !== null) {
            $cuentas[] = $proveedor->usuario_titular_id;
        }

        $pilotos = Piloto::query()
            ->where('proveedor_id', $proveedorId)
            ->where('estado', EstadoAprobacion::Aprobado->value)
            ->get();

        foreach ($pilotos as $piloto) {
            // El titular que también es piloto (el independiente) se rige por el interruptor del proveedor.
            if ($piloto->usuario_id !== $proveedor->usuario_titular_id && ($piloto->disponibilidad ?? Disponibilidad::Disponible)->recibeAvisos()) {
                $cuentas[] = $piloto->usuario_id;
            }
        }

        return array_values(array_unique(array_map('intval', $cuentas)));
    }

    public function proveedorDeLaCuenta(int $usuarioId): ?DatosProveedor
    {
        $titular = $this->proveedorDelTitular($usuarioId);

        if ($titular !== null) {
            return $titular;
        }

        $piloto = Piloto::query()->where('usuario_id', $usuarioId)->first();

        return $piloto === null ? null : $this->proveedor($piloto->proveedor_id);
    }

    private function datos(Proveedor $proveedor): DatosProveedor
    {
        return new DatosProveedor(
            $proveedor->id,
            $proveedor->usuario_titular_id,
            $proveedor->tipo->value,
            $proveedor->razon_social,
            $proveedor->estado->value,
            $proveedor->zona_base_id,
            (string) $proveedor->radio_operacion_km,
            (string) $proveedor->calificacion_promedio,
            $proveedor->calificaciones_conteo,
            (string) $proveedor->tasa_aceptacion,
            (string) $proveedor->tasa_cancelacion,
            ($proveedor->disponibilidad ?? Disponibilidad::Disponible)->value,
        );
    }

    /** @param  array<int, string>  $nombres */
    private function datosPiloto(Piloto $piloto, array $nombres): DatosPiloto
    {
        return new DatosPiloto($piloto->id, $piloto->proveedor_id, $piloto->usuario_id, $nombres[$piloto->usuario_id] ?? "#{$piloto->usuario_id}", $piloto->estado->value, ($piloto->disponibilidad ?? Disponibilidad::Disponible)->value);
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
