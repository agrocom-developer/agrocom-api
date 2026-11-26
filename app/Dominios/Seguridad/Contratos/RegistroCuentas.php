<?php

namespace App\Dominios\Seguridad\Contratos;

/**
 * Alta de cuentas del marketplace (ADR 0036): clientes, proveedores y pilotos se
 * registran por sí mismos o los registra su proveedor. La cuenta
 * es una cuenta `sec_user` común (un usuario, un login, varios roles — invariante
 * 10); lo que la distingue es el rol. Los módulos `Proveedores` y `Solicitudes`
 * piden la cuenta por este contrato y nunca tocan `sec_user`.
 */
interface RegistroCuentas
{
    /**
     * Crea la cuenta y le asigna los roles pedidos.
     *
     * @param  list<string>  $roles  Nombres de rol de `sec_role` (`cliente`, `proveedor`, `piloto`).
     * @return int Id de la cuenta creada.
     *
     * @throws CuentaDuplicada si el `username` o el `email` ya pertenecen a una cuenta viva.
     */
    public function crear(string $nombre, string $username, ?string $email, string $password, array $roles): int;

    /** Suma un rol a una cuenta que ya existe (el independiente es proveedor y piloto). Idempotente. */
    public function agregarRol(int $usuarioId, string $rol): void;

    /** Quita un rol a una cuenta (la baja de un piloto). Idempotente. */
    public function quitarRol(int $usuarioId, string $rol): void;
}
