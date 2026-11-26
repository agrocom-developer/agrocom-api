<?php

namespace App\Dominios\Seguridad\Contratos;

/**
 * Alta de cuentas del marketplace (ADR 0036 y 0046): clientes, proveedores y pilotos
 * se registran por sí mismos o los registra su proveedor. Cada cuenta es de UN tipo
 * ({@see TipoCuenta}: `cliente` o `proveedor`) para siempre, con su propio usuario y
 * contraseña. Los módulos `Proveedores` y `Solicitudes` piden la cuenta por este
 * contrato y nunca tocan `sec_user`.
 *
 * TRANSITORIO hasta la fase A5 (contrato del token): la cuenta además lleva los roles
 * de app (`cliente`, `proveedor`, `piloto`) con los que el token del dispositivo
 * resuelve permisos y que las apps leen como `rol.name`.
 */
interface RegistroCuentas
{
    /**
     * Crea la cuenta del tipo pedido y le asigna los roles de app.
     *
     * @param  TipoCuenta  $tipoCuenta  `cliente` o `proveedor`: una cuenta de panel no se crea por acá.
     * @param  list<string>  $roles  Nombres de rol de `sec_role` (`cliente`, `proveedor`, `piloto`); transitorio hasta A5.
     * @return int Id de la cuenta creada.
     *
     * @throws CuentaDuplicada si el `username` o el `email` ya pertenecen a una cuenta viva.
     * @throws \InvalidArgumentException si el tipo es `panel`.
     */
    public function crear(string $nombre, string $username, ?string $email, string $password, TipoCuenta $tipoCuenta, array $roles): int;

    /** Suma un rol a una cuenta que ya existe (el independiente es proveedor y piloto). Idempotente. */
    public function agregarRol(int $usuarioId, string $rol): void;

    /** Quita un rol a una cuenta (la baja de un piloto). Idempotente. */
    public function quitarRol(int $usuarioId, string $rol): void;
}
