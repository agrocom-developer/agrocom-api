<?php

namespace App\Dominios\Seguridad\Contratos;

use App\Dominios\Seguridad\Dominio\NumeroDeTelefono;

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
     * @param  string|null  $telefono  Teléfono de la cuenta, en cualquier formato que {@see NumeroDeTelefono}
     *                                 entienda; se guarda en E.164 y es único por tipo de cuenta.
     * @param  DatosDePersona|null  $persona  La persona de la cuenta: se busca por documento y, si no existe, se crea;
     *                                        nunca se sobrescribe una persona que ya existía.
     * @return int Id de la cuenta creada.
     *
     * @throws CuentaDuplicada si el `username` o el `email` ya pertenecen a una cuenta viva (del mismo tipo, en el caso del correo).
     * @throws CuentaYaRegistrada si el teléfono o el documento ya tienen una cuenta de este tipo.
     * @throws \InvalidArgumentException si el tipo es `panel` o el teléfono o el documento no son válidos.
     */
    public function crear(
        string $nombre,
        string $username,
        ?string $email,
        string $password,
        TipoCuenta $tipoCuenta,
        array $roles,
        ?string $telefono = null,
        ?DatosDePersona $persona = null,
    ): int;

    /** Suma un rol a una cuenta que ya existe (el independiente es proveedor y piloto). Idempotente. */
    public function agregarRol(int $usuarioId, string $rol): void;

    /** Quita un rol a una cuenta (la baja de un piloto). Idempotente. */
    public function quitarRol(int $usuarioId, string $rol): void;
}
