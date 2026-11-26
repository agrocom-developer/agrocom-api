<?php

namespace Tests\Soporte;

use App\Dominios\Proveedores\Contratos\DatosPiloto;
use App\Dominios\Proveedores\Contratos\DatosProveedor;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;

/**
 * `LecturaProveedores` con datos controlados por la prueba: la cuenta titular de
 * cada proveedor es su `usuarioTitularId`; los pilotos se dan por separado.
 */
final class ProveedoresFalsos implements LecturaProveedores
{
    /**
     * @param  array<int, DatosProveedor>  $proveedores
     * @param  array<int, DatosPiloto>  $pilotos
     */
    public function __construct(
        private readonly array $proveedores,
        private readonly array $pilotos = [],
    ) {}

    public function proveedor(int $id): ?DatosProveedor
    {
        return $this->proveedores[$id] ?? null;
    }

    public function admitenAltas(): array
    {
        return [];
    }

    public function proveedorDelTitular(int $usuarioId): ?DatosProveedor
    {
        foreach ($this->proveedores as $proveedor) {
            if ($proveedor->usuarioTitularId === $usuarioId) {
                return $proveedor;
            }
        }

        return null;
    }

    public function proveedorPropio(): ?DatosProveedor
    {
        foreach ($this->proveedores as $proveedor) {
            if ($proveedor->tipo === 'propio') {
                return $proveedor;
            }
        }

        return null;
    }

    public function piloto(int $id): ?DatosPiloto
    {
        return $this->pilotos[$id] ?? null;
    }

    public function registrosDeCuenta(int $usuarioId): array
    {
        return [
            'pilotos' => array_values(array_map(fn (DatosPiloto $p): int => $p->id, array_filter($this->pilotos, fn (DatosPiloto $p): bool => $p->usuarioId === $usuarioId))),
        ];
    }

    public function pilotosDe(int $proveedorId): array
    {
        return array_values(array_filter($this->pilotos, fn (DatosPiloto $p): bool => $p->proveedorId === $proveedorId));
    }

    public function aprobadosDelGrupo(string $grupo): array
    {
        return array_values(array_filter(
            $this->proveedores,
            fn (DatosProveedor $p): bool => $p->estaAprobado() && (($grupo === 'propio') === ($p->tipo === 'propio')),
        ));
    }

    public function usuariosDeAviso(int $proveedorId): array
    {
        $titular = $this->proveedores[$proveedorId]->usuarioTitularId ?? null;
        $pilotos = array_map(fn (DatosPiloto $p): int => $p->usuarioId, $this->pilotosDe($proveedorId));

        return array_values(array_unique(array_filter([$titular, ...$pilotos])));
    }

    public function proveedorDeLaCuenta(int $usuarioId): ?DatosProveedor
    {
        $titular = $this->proveedorDelTitular($usuarioId);

        if ($titular !== null) {
            return $titular;
        }

        foreach ($this->pilotos as $piloto) {
            if ($piloto->usuarioId === $usuarioId) {
                return $this->proveedores[$piloto->proveedorId] ?? null;
            }
        }

        return null;
    }
}
