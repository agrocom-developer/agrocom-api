<?php

namespace Tests\Soporte;

use App\Dominios\Seguridad\Contratos\DatosCuenta;
use App\Dominios\Seguridad\Contratos\LecturaCuentas;
use App\Dominios\Seguridad\Contratos\TipoCuenta;

/**
 * Lectura de cuentas de mentira para las pruebas de otros módulos: cada cuenta tiene un nombre y,
 * si se indica, la persona (`sec_personas.id`) a la que pertenece. Evita levantar `sec_user` cuando
 * lo único que importa es qué cuentas son de la misma persona.
 */
final class CuentasFalsas implements LecturaCuentas
{
    /** @param  array<int, int|null>  $personaPorUsuario  id de cuenta => persona (o `null`) */
    public function __construct(private readonly array $personaPorUsuario = []) {}

    public function porIds(array $ids): array
    {
        $cuentas = [];

        foreach ($ids as $id) {
            $cuentas[$id] = new DatosCuenta($id, "Cuenta {$id}", "cuenta.{$id}", null, true, TipoCuenta::Proveedor, $this->personaPorUsuario[$id] ?? null);
        }

        return $cuentas;
    }

    public function idPorUsername(string $username): ?int
    {
        return null;
    }
}
